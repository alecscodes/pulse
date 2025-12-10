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

        $monitors = Monitor::where('user_id', $userId)
            ->with(['checks' => fn ($query) => $query->latest('checked_at')->limit(1)])
            ->addSelect([
                'has_active_downtime' => MonitorDowntime::selectRaw('1')
                    ->whereColumn('monitor_id', 'monitors.id')
                    ->whereNull('ended_at')
                    ->limit(1),
            ])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Monitor $monitor) => $this->formatMonitor($monitor));

        $downWebsites = Monitor::where('user_id', $userId)
            ->whereHas('downtimes', fn ($query) => $query->whereNull('ended_at'))
            ->with([
                'downtimes' => fn ($query) => $query->whereNull('ended_at')
                    ->latest('started_at')
                    ->limit(1),
                'checks' => fn ($query) => $query->latest('checked_at')->limit(1),
            ])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Monitor $monitor) => $this->formatDownWebsite($monitor));

        return Inertia::render('Dashboard', [
            'monitors' => $monitors,
            'downWebsites' => $downWebsites,
        ]);
    }

    /**
     * Format monitor data for dashboard.
     */
    private function formatMonitor(Monitor $monitor): array
    {
        $latestCheck = $monitor->checks->first();

        return [
            'id' => $monitor->id,
            'name' => $monitor->name,
            'url' => $monitor->url,
            'status' => $latestCheck?->status ?? 'unknown',
            'is_down' => (bool) $monitor->has_active_downtime,
            'response_time' => $latestCheck?->response_time,
        ];
    }

    /**
     * Format down website data for dashboard.
     */
    private function formatDownWebsite(Monitor $monitor): array
    {
        $latestDowntime = $monitor->downtimes->first();
        $latestCheck = $monitor->checks->first();

        return [
            'id' => $monitor->id,
            'name' => $monitor->name,
            'url' => $monitor->url,
            'started_at' => $latestDowntime?->started_at?->toISOString(),
            'status' => $latestCheck?->status ?? 'unknown',
            'response_time' => $latestCheck?->response_time,
        ];
    }
}
