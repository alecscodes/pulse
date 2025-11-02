<?php

namespace App\Services;

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
    protected function handleMonitorUp(Monitor $monitor): void
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
        Monitor::where('is_active', true)
            ->get()
            ->filter(function (Monitor $monitor) {
                /** @var MonitorCheck|null $latestCheck */
                $latestCheck = $monitor->checks()->latest('checked_at')->first();

                if (! $latestCheck) {
                    return true; // Never checked
                }

                $secondsSinceLastCheck = now()->diffInSeconds($latestCheck->checked_at);

                return $secondsSinceLastCheck >= $monitor->check_interval;
            })
            ->chunk(10)
            ->each(function (\Illuminate\Database\Eloquent\Collection $monitors) {
                foreach ($monitors as $monitor) {
                    $this->processMonitorCheck($monitor);
                }
            });
    }
}
