<?php

namespace App\Console\Commands;

use App\Services\MonitorStatusService;
use Illuminate\Console\Command;

class CheckMonitorsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitors:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check all active monitors';

    /**
     * Execute the console command.
     */
    public function handle(MonitorStatusService $statusService): int
    {
        $this->info('Checking monitors...');

        \Illuminate\Support\Facades\Log::channel('database')->info('Monitor check command started', [
            'category' => 'system',
        ]);

        $statusService->checkAllMonitors();

        \Illuminate\Support\Facades\Log::channel('database')->info('Monitor check command completed', [
            'category' => 'system',
        ]);

        $this->info('Monitors checked successfully.');

        return Command::SUCCESS;
    }
}
