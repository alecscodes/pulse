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

        $checkResult = $this->checkService->checkMonitor($monitor);
        $this->checkService->createCheck($monitor, $checkResult);

        // If check failed, retry with appropriate strategy
        if ($checkResult['status'] === 'down') {
            $initialFailureTime = now();
            $isContentValidationFailure = $checkResult['status_code'] === 200 && $checkResult['content_valid'] === false;
            $maxRetries = $isContentValidationFailure ? 5 : 3;
            $retryDelay = $isContentValidationFailure ? 2 : 3;

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                sleep($retryDelay);

                $retryResult = $this->checkService->checkMonitor($monitor);
                $this->checkService->createCheck($monitor, $retryResult);

                if ($retryResult['status'] === 'up') {
                    $this->handleMonitorUp($monitor);

                    return;
                }

                // Stop retrying if failure type changed
                if ($isContentValidationFailure && $retryResult['status_code'] !== 200 && $retryResult['status_code'] !== null) {
                    break;
                }
            }

            $this->handleMonitorDown($monitor, $initialFailureTime);
        } else {
            $this->handleMonitorUp($monitor);
        }
    }

    /**
     * Update downtime if needed (for queue jobs checking down monitors).
     */
    public function updateDowntimeIfNeeded(Monitor $monitor): void
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
     *
     * @param  \Illuminate\Support\Carbon|null  $startedAt  The time when the downtime actually started (before retries)
     */
    protected function handleMonitorDown(Monitor $monitor, ?\Illuminate\Support\Carbon $startedAt = null): void
    {
        /** @var MonitorDowntime|null $currentDowntime */
        $currentDowntime = $monitor->currentDowntime()->first();

        if (! $currentDowntime) {
            // Start new downtime with the initial failure time (best practice for accurate tracking)
            $downtimeStartTime = $startedAt ?? now();
            MonitorDowntime::create([
                'monitor_id' => $monitor->id,
                'started_at' => $downtimeStartTime,
                'last_notification_at' => now(),
            ]);

            // Send initial notification
            $this->notificationService->sendMonitorDownNotification($monitor);

            \Illuminate\Support\Facades\Log::channel('database')->error('Monitor downtime started', [
                'category' => 'monitor',
                'monitor_id' => $monitor->id,
                'monitor_name' => $monitor->name,
                'started_at' => $downtimeStartTime->toIso8601String(),
            ]);

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

            \Illuminate\Support\Facades\Log::channel('database')->info('Monitor recovered', [
                'category' => 'monitor',
                'monitor_id' => $monitor->id,
                'monitor_name' => $monitor->name,
                'duration_seconds' => $currentDowntime->duration_seconds,
            ]);
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
                /** @var Monitor $monitor */
                foreach ($monitors as $monitor) {
                    $this->processMonitorCheck($monitor);
                }
            });
    }
}
