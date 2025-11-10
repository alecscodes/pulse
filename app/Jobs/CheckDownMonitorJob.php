<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Services\MonitorCheckService;
use App\Services\MonitorStatusService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckDownMonitorJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $monitorId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        MonitorCheckService $checkService,
        MonitorStatusService $statusService
    ): void {
        $monitor = Monitor::find($this->monitorId);

        if (! $monitor || ! $monitor->is_active) {
            return; // Monitor no longer exists or is inactive
        }

        // Check if monitor still has active downtime
        $hasActiveDowntime = $monitor->currentDowntime()->exists();

        if (! $hasActiveDowntime) {
            return; // Monitor is no longer down, stop checking
        }

        // Perform the check
        if (! $checkService->checkConnectivity()) {
            // If no connectivity, reschedule for 3 seconds later
            self::dispatch($this->monitorId)->delay(now()->addSeconds(3));

            return;
        }

        $checkResult = $checkService->checkMonitor($monitor);
        $check = $checkService->createCheck($monitor, $checkResult);

        if ($checkResult['status'] === 'down') {
            // Still down, update downtime and reschedule
            $statusService->updateDowntimeIfNeeded($monitor, $check);

            // Reschedule for 3 seconds later
            self::dispatch($this->monitorId)->delay(now()->addSeconds(3));
        } else {
            // Monitor is back up, handle recovery
            $statusService->handleMonitorUp($monitor);
        }
    }
}
