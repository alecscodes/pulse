<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use App\Services\SslCheckService;
use App\Services\TelegramNotificationService;
use Illuminate\Console\Command;

class CheckSslCommand extends Command
{
    protected $signature = 'ssl:check';

    protected $description = 'Check SSL certificates for all active monitors';

    public function handle(
        SslCheckService $sslService,
        TelegramNotificationService $notificationService
    ): int {
        $this->info('Checking SSL certificates...');

        $monitors = Monitor::where('is_active', true)
            ->where('url', 'like', 'https://%')
            ->get();

        if ($monitors->isEmpty()) {
            $this->info('No HTTPS monitors found.');

            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($monitors->count());
        $bar->start();

        $stats = ['checked' => 0, 'expiring' => 0, 'expired' => 0];

        foreach ($monitors as $monitor) {
            $sslResult = $sslService->checkSslCertificate($monitor);
            $sslService->updateMonitorCheckWithSsl($monitor, $sslResult);

            $stats['checked']++;

            if (! $sslResult['valid']) {
                $stats['expired']++;
                $notificationService->sendSslExpiredNotification($monitor, $sslResult);
            } elseif ($sslService->isExpiringSoon($sslResult['days_until_expiration'])) {
                $stats['expiring']++;
                $notificationService->sendSslExpiringNotification($monitor, $sslResult);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Status', 'Count'],
            [
                ['Checked', $stats['checked']],
                ['Expiring Soon (≤30 days)', $stats['expiring']],
                ['Expired/Invalid', $stats['expired']],
            ]
        );

        return Command::SUCCESS;
    }
}
