<?php

namespace App\Http\Controllers;

use App\Http\Requests\MonitorStoreRequest;
use App\Http\Requests\MonitorUpdateRequest;
use App\Models\Monitor;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MonitorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $monitors = Monitor::where('user_id', auth()->id())
            ->with(['checks' => function ($query) {
                $query->latest('checked_at')->limit(1);
            }])
            ->latest()
            ->get()
            ->map(function (Monitor $monitor) {
                $latestCheck = $monitor->checks->first();
                $isDown = $monitor->currentDowntime()->exists();

                return [
                    'id' => $monitor->id,
                    'name' => $monitor->name,
                    'type' => $monitor->type,
                    'url' => $monitor->url,
                    'method' => $monitor->method,
                    'is_active' => $monitor->is_active,
                    'check_interval' => $monitor->check_interval,
                    'status' => $latestCheck !== null ? $latestCheck->status : 'unknown',
                    'last_checked_at' => $latestCheck?->checked_at?->toISOString(),
                    'response_time' => $latestCheck?->response_time,
                    'is_down' => $isDown,
                    'domain_expires_at' => $monitor->domain_expires_at?->toISOString(),
                    'domain_days_until_expiration' => $monitor->domain_days_until_expiration,
                    'domain_error_message' => $monitor->domain_error_message,
                ];
            });

        return Inertia::render('Monitors/Index', [
            'monitors' => $monitors,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Monitors/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(MonitorStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $monitor = Monitor::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'type' => $validated['type'],
            'url' => $validated['url'],
            'method' => $validated['method'],
            'headers' => $validated['headers'] ?? [],
            'parameters' => $validated['parameters'] ?? [],
            'enable_content_validation' => $validated['enable_content_validation'] ?? false,
            'expected_title' => $validated['expected_title'] ?? null,
            'expected_content' => $validated['expected_content'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'check_interval' => $validated['check_interval'] ?? 60,
        ]);

        return redirect()->route('monitors.show', $monitor)->with('success', 'Monitor created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Monitor $monitor): Response
    {
        $this->authorize('view', $monitor);

        $monitor->load(['checks' => function ($query) {
            $query->latest('checked_at')->limit(100);
        }, 'downtimes' => function ($query) {
            $query->latest('started_at')->limit(50);
        }]);

        return Inertia::render('Monitors/Show', [
            'monitor' => [
                'id' => $monitor->id,
                'name' => $monitor->name,
                'type' => $monitor->type,
                'url' => $monitor->url,
                'method' => $monitor->method,
                'headers' => $monitor->headers ?? [],
                'parameters' => $monitor->parameters ?? [],
                'enable_content_validation' => $monitor->enable_content_validation,
                'expected_title' => $monitor->expected_title,
                'expected_content' => $monitor->expected_content,
                'is_active' => $monitor->is_active,
                'check_interval' => $monitor->check_interval,
                'domain_expires_at' => $monitor->domain_expires_at?->toISOString(),
                'domain_days_until_expiration' => $monitor->domain_days_until_expiration,
                'domain_error_message' => $monitor->domain_error_message,
                'domain_last_checked_at' => $monitor->domain_last_checked_at?->toISOString(),
                'checks' => $monitor->checks->map(function (\App\Models\MonitorCheck $check) {
                    return [
                        'id' => $check->id,
                        'status' => $check->status,
                        'response_time' => $check->response_time,
                        'status_code' => $check->status_code,
                        'error_message' => $check->error_message,
                        'content_valid' => $check->content_valid,
                        'checked_at' => $check->checked_at->toISOString(),
                    ];
                })->values()->all(),
                'downtimes' => $monitor->downtimes->map(function (\App\Models\MonitorDowntime $downtime) {
                    return [
                        'id' => $downtime->id,
                        'started_at' => $downtime->started_at->toISOString(),
                        'ended_at' => $downtime->ended_at?->toISOString(),
                        'duration_seconds' => $downtime->duration_seconds,
                    ];
                })->values()->all(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Monitor $monitor): Response
    {
        $this->authorize('update', $monitor);

        return Inertia::render('Monitors/Edit', [
            'monitor' => [
                'id' => $monitor->id,
                'name' => $monitor->name,
                'type' => $monitor->type,
                'url' => $monitor->url,
                'method' => $monitor->method,
                'headers' => collect($monitor->headers ?? [])->map(function ($value, $key) {
                    return ['key' => $key, 'value' => $value];
                })->values()->toArray(),
                'parameters' => collect($monitor->parameters ?? [])->map(function ($value, $key) {
                    return ['key' => $key, 'value' => $value];
                })->values()->toArray(),
                'enable_content_validation' => $monitor->enable_content_validation,
                'expected_title' => $monitor->expected_title,
                'expected_content' => $monitor->expected_content,
                'is_active' => $monitor->is_active,
                'check_interval' => $monitor->check_interval,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(MonitorUpdateRequest $request, Monitor $monitor): RedirectResponse
    {
        $this->authorize('update', $monitor);

        $validated = $request->validated();

        $monitor->update([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'url' => $validated['url'],
            'method' => $validated['method'],
            'headers' => $validated['headers'] ?? [],
            'parameters' => $validated['parameters'] ?? [],
            'enable_content_validation' => $validated['enable_content_validation'] ?? false,
            'expected_title' => $validated['expected_title'] ?? null,
            'expected_content' => $validated['expected_content'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'check_interval' => $validated['check_interval'] ?? 60,
        ]);

        return redirect()->route('monitors.show', $monitor)->with('success', 'Monitor updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Monitor $monitor): RedirectResponse
    {
        $this->authorize('delete', $monitor);

        $monitor->delete();

        return redirect()->route('monitors.index')->with('success', 'Monitor deleted successfully.');
    }
}
