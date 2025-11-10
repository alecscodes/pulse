<?php

namespace App\Services;

use App\Jobs\CheckDownMonitorJob;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorDowntime;

class MonitorStatusService
{
    public function __construct(
        protected MonitorCheckService $checkService,
        protected TelegramNotificationService $notificationService
    ) {}

    /**
     * Process monitor status check and handle downtime logic.
     */
    public function processMonitorCheck(Monitor $monitor): void
    {
        // First check connectivity
        if (! $this->checkService->checkConnectivity()) {
            return; // Skip checks if no internet connectivity
        }

        // Perform the check
        $checkResult = $this->checkService->checkMonitor($monitor);
        $check = $this->checkService->createCheck($monitor, $checkResult);

        // If check failed, wait 3 seconds and check again
        if ($checkResult['status'] === 'down') {
            sleep(3);

            $recheckResult = $this->checkService->checkMonitor($monitor);
            $recheck = $this->checkService->createCheck($monitor, $recheckResult);

            // If second check also failed, handle downtime
            if ($recheckResult['status'] === 'down') {
                $this->handleMonitorDown($monitor, $recheck);
            } else {
                // Monitor recovered quickly, no downtime recorded
                $this->handleMonitorUp($monitor);
            }
        } else {
            $this->handleMonitorUp($monitor);
        }
    }

    /**
     * Update downtime if needed (for queue jobs checking down monitors).
     */
    public function updateDowntimeIfNeeded(Monitor $monitor, MonitorCheck $check): void
    {
        /** @var MonitorDowntime|null $currentDowntime */
        $currentDowntime = $monitor->currentDowntime()->first();

        if ($currentDowntime) {
            // Update existing downtime
            // Calculate seconds since last notification (ensure positive value)
            $lastNotificationAt = $currentDowntime->last_notification_at;
            $secondsSinceLastNotification = $lastNotificationAt !== null
                ? abs($lastNotificationAt->diffInSeconds(now()))
                : 0;

            // Send notification every 10 minutes
            if ($secondsSinceLastNotification >= 600) {
                $currentDowntime->update([
                    'last_notification_at' => now(),
                ]);

                /** @var MonitorDowntime $currentDowntime */
                $this->notificationService->sendMonitorStillDownNotification($monitor, $currentDowntime);
            }
        }
    }

    /**
     * Handle monitor being down.
     */
    protected function handleMonitorDown(Monitor $monitor, MonitorCheck $check): void
    {
        /** @var MonitorDowntime|null $currentDowntime */
        $currentDowntime = $monitor->currentDowntime()->first();

        if (! $currentDowntime) {
            // Start new downtime
            $downtime = MonitorDowntime::create([
                'monitor_id' => $monitor->id,
                'started_at' => now(),
                'last_notification_at' => now(),
            ]);

            // Send initial notification
            $this->notificationService->sendMonitorDownNotification($monitor);

            // Schedule job to check this monitor every 3 seconds until recovery
            // Only dispatch if not in testing environment (tests should mock this)
            if (! app()->environment('testing')) {
                CheckDownMonitorJob::dispatch($monitor->id)->delay(now()->addSeconds(3));
            }
        } else {
            // Update existing downtime
            // Calculate seconds since last notification (ensure positive value)
            $lastNotificationAt = $currentDowntime->last_notification_at;
            $secondsSinceLastNotification = $lastNotificationAt !== null
                ? abs($lastNotificationAt->diffInSeconds(now()))
                : 0;

            // Send notification every 10 minutes
            if ($secondsSinceLastNotification >= 600) {
                $currentDowntime->update([
                    'last_notification_at' => now(),
                ]);

                /** @var MonitorDowntime $currentDowntime */
                $this->notificationService->sendMonitorStillDownNotification($monitor, $currentDowntime);
            }
        }
    }

    /**
     * Handle monitor being up.
     */
    public function handleMonitorUp(Monitor $monitor): void
    {
        /** @var MonitorDowntime|null $currentDowntime */
        $currentDowntime = $monitor->currentDowntime()->first();

        if ($currentDowntime) {
            // End the downtime
            $currentDowntime->update([
                'ended_at' => now(),
            ]);

            $currentDowntime->calculateDuration();
            $currentDowntime->save();

            // Send recovery notification
            /** @var MonitorDowntime $currentDowntime */
            $this->notificationService->sendMonitorRecoveryNotification($monitor, $currentDowntime);
        }
    }

    /**
     * Check all active monitors.
     */
    public function checkAllMonitors(): void
    {
        // Get all active monitors with their latest check
        // Use subquery to check for active downtimes to avoid N+1 queries
        $monitors = Monitor::where('is_active', true)
            ->with(['checks' => function ($query) {
                $query->latest('checked_at')->limit(1);
            }])
            ->addSelect([
                'has_active_downtime' => MonitorDowntime::selectRaw('1')
                    ->whereColumn('monitor_id', 'monitors.id')
                    ->whereNull('ended_at')
                    ->limit(1),
            ])
            ->get()
            ->filter(function (Monitor $monitor) {
                /** @var MonitorCheck|null $latestCheck */
                $latestCheck = $monitor->checks->first();

                if (! $latestCheck) {
                    return true; // Never checked
                }

                // Down monitors are checked via queue jobs every 3 seconds
                // Skip them in the regular check cycle
                $hasActiveDowntime = (bool) $monitor->has_active_downtime;

                if ($hasActiveDowntime) {
                    return false; // Skip - handled by queue jobs
                }

                // For monitors with 60-second or less intervals, always check when scheduler runs
                // This ensures we never miss a check, even if it means checking twice in a minute
                if ($monitor->check_interval <= 60) {
                    return true;
                }

                // For longer intervals, check if enough time has passed
                $secondsSinceLastCheck = abs(now()->diffInSeconds($latestCheck->checked_at));

                return $secondsSinceLastCheck >= $monitor->check_interval;
            });

        // Re-dispatch queue jobs for monitors with active downtime that haven't been checked recently
        // This ensures monitors continue to be checked even if queue jobs were lost
        Monitor::where('is_active', true)
            ->whereHas('downtimes', function ($query) {
                $query->whereNull('ended_at');
            })
            ->with(['checks' => function ($query) {
                $query->latest('checked_at')->limit(1);
            }])
            ->get()
            ->each(function (Monitor $monitor) {
                /** @var MonitorCheck|null $latestCheck */
                $latestCheck = $monitor->checks->first();

                // If no recent check (more than 10 seconds ago), re-dispatch queue job
                if (! $latestCheck || abs(now()->diffInSeconds($latestCheck->checked_at)) > 10) {
                    if (! app()->environment('testing')) {
                        CheckDownMonitorJob::dispatch($monitor->id)->delay(now()->addSeconds(3));
                    }
                }
            });

        $monitors->chunk(10)
            ->each(function (\Illuminate\Database\Eloquent\Collection $monitors) {
                foreach ($monitors as $monitor) {
                    $this->processMonitorCheck($monitor);
                }
            });
    }
}
