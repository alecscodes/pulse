<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use App\Services\DomainExpirationService;
use App\Services\TelegramNotificationService;
use Illuminate\Console\Command;

class CheckDomainCommand extends Command
{
    protected $signature = 'domain:check';

    protected $description = 'Check domain expiration for all active monitors';

    public function handle(
        DomainExpirationService $domainService,
        TelegramNotificationService $notificationService
    ): int {
        $this->info('Checking domain expiration...');

        $monitors = Monitor::where('is_active', true)->get();

        if ($monitors->isEmpty()) {
            $this->info('No active monitors found.');

            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($monitors->count());
        $bar->start();

        $stats = ['checked' => 0, 'expiring' => 0, 'expired' => 0, 'errors' => 0];

        foreach ($monitors as $monitor) {
            $result = $domainService->getDomainExpiration($monitor);

            $monitor->update([
                'domain_expires_at' => $result['expires_at'],
                'domain_days_until_expiration' => $result['days_until_expiration'],
                'domain_error_message' => $result['error_message'],
                'domain_last_checked_at' => now(),
            ]);

            $stats['checked']++;

            if ($result['error_message']) {
                $stats['errors']++;
            } elseif ($result['days_until_expiration'] !== null) {
                if ($result['days_until_expiration'] <= 0) {
                    $stats['expired']++;
                    $notificationService->sendDomainExpiredNotification($monitor, $result);
                } elseif ($domainService->isExpiringSoon($result['days_until_expiration'])) {
                    $stats['expiring']++;
                    $notificationService->sendDomainExpiringNotification($monitor, $result);
                }
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
                ['Expired', $stats['expired']],
                ['Errors', $stats['errors']],
            ]
        );

        \Illuminate\Support\Facades\Log::channel('database')->info('Domain check command completed', [
            'category' => 'system',
            'stats' => $stats,
        ]);

        return Command::SUCCESS;
    }
}
