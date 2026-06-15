<?php

namespace App\Console\Commands;

use App\Models\MonitorCheck;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupMonitorHistoryCommand extends Command
{
    protected $signature = 'monitor-history:cleanup {--vacuum}';

    protected $description = 'Delete old monitor check history';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) Setting::get('monitor_check_retention_days', 30));
        $count = MonitorCheck::where('checked_at', '<', $cutoff)->count();

        if ($count === 0) {
            $this->info('No monitor history to clean up.');

            return self::SUCCESS;
        }

        MonitorCheck::where('checked_at', '<', $cutoff)
            ->chunkById(1000, fn ($records) => $records->each->delete());

        $this->info("Deleted {$count} monitor history records.");

        if ($this->option('vacuum') && config('database.default') === 'sqlite') {
            DB::statement('VACUUM');
        }

        return self::SUCCESS;
    }
}
