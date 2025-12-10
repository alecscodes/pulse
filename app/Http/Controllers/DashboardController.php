<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use App\Models\MonitorDowntime;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index(): Response
    {
        $userId = auth()->id();
        $baseQuery = fn () => Monitor::where('user_id', $userId);

        // Stats
        $totalMonitors = $baseQuery()->count();
        $downMonitors = $baseQuery()
            ->whereHas('downtimes', fn ($q) => $q->whereNull('ended_at'))
            ->count();

        // Data
        $monitors = $baseQuery()
            ->with(['checks' => fn ($q) => $q->latest('checked_at')->limit(1)])
            ->addSelect([
                'has_active_downtime' => MonitorDowntime::selectRaw('1')
                    ->whereColumn('monitor_id', 'monitors.id')
                    ->whereNull('ended_at')
                    ->limit(1),
            ])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Monitor $m) => $this->formatMonitor($m));

        $downWebsites = $baseQuery()
            ->whereHas('downtimes', fn ($q) => $q->whereNull('ended_at'))
            ->with([
                'downtimes' => fn ($q) => $q->whereNull('ended_at')->latest('started_at')->limit(1),
                'checks' => fn ($q) => $q->latest('checked_at')->limit(1),
            ])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Monitor $m) => $this->formatDownWebsite($m));

        $domains = $baseQuery()
            ->whereNotNull('domain_expires_at')
            ->orderBy('domain_expires_at')
            ->limit(10)
            ->get()
            ->map(fn (Monitor $m) => $this->formatDomain($m));

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_monitors' => $totalMonitors,
                'up_monitors' => $totalMonitors - $downMonitors,
                'down_monitors' => $downMonitors,
                'expiring_domains' => $baseQuery()
                    ->whereNotNull('domain_expires_at')
                    ->whereBetween('domain_days_until_expiration', [1, 30])
                    ->count(),
            ],
            'monitors' => $monitors,
            'downWebsites' => $downWebsites,
            'domains' => $domains,
        ]);
    }

    private function formatMonitor(Monitor $monitor): array
    {
        $check = $monitor->checks->first();

        return [
            'id' => $monitor->id,
            'name' => $monitor->name,
            'url' => $monitor->url,
            'status' => $check?->status ?? 'unknown',
            'is_down' => (bool) $monitor->has_active_downtime,
            'response_time' => $check?->response_time,
        ];
    }

    private function formatDownWebsite(Monitor $monitor): array
    {
        return [
            'id' => $monitor->id,
            'name' => $monitor->name,
            'url' => $monitor->url,
            'started_at' => $monitor->downtimes->first()?->started_at?->toISOString(),
            'status' => $monitor->checks->first()?->status ?? 'unknown',
            'response_time' => $monitor->checks->first()?->response_time,
        ];
    }

    private function formatDomain(Monitor $monitor): array
    {
        return [
            'id' => $monitor->id,
            'name' => $monitor->name,
            'url' => $monitor->url,
            'expires_at' => $monitor->domain_expires_at?->toISOString(),
            'days_until_expiration' => $monitor->domain_days_until_expiration,
            'error_message' => $monitor->domain_error_message,
        ];
    }
}
