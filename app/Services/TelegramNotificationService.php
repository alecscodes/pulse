<?php

namespace App\Services;

use App\Models\Monitor;
use App\Models\MonitorDowntime;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class TelegramNotificationService
{
    /**
     * Send a notification to Telegram.
     */
    public function sendNotification(string $message): bool
    {
        $botToken = Setting::get('telegram_bot_token');
        $chatId = Setting::get('telegram_chat_id');

        if (! $botToken || ! $chatId) {
            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Send a monitor down notification.
     */
    public function sendMonitorDownNotification(Monitor $monitor): bool
    {
        $message = "⚠️ The website {$monitor->url} appears to be down.";

        return $this->sendNotification($message);
    }

    /**
     * Send a monitor recovery notification.
     */
    public function sendMonitorRecoveryNotification(Monitor $monitor, MonitorDowntime $downtime): bool
    {
        $duration = $this->formatDurationAsTime($downtime->duration_seconds ?? 0);

        $message = "✅ The website {$monitor->url} is back up. ⏰ It was down for approximately {$duration}.";

        return $this->sendNotification($message);
    }

    /**
     * Send a monitor still down notification (periodic update).
     */
    public function sendMonitorStillDownNotification(Monitor $monitor, MonitorDowntime $downtime): bool
    {
        $duration = $this->formatDurationAsTime((int) $downtime->started_at->diffInSeconds(now()));

        $message = "⚠️ The website {$monitor->url} is still down. ⏰ It has been down for approximately {$duration}.";

        return $this->sendNotification($message);
    }

    /**
     * Format duration in seconds to human-readable format.
     */
    protected function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $parts = [];

        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }

        if ($minutes > 0) {
            $parts[] = "{$minutes}m";
        }

        if ($secs > 0 || empty($parts)) {
            $parts[] = "{$secs}s";
        }

        return implode(' ', $parts);
    }

    /**
     * Format duration in seconds to HH:MM:SS format.
     */
    protected function formatDurationAsTime(int $seconds): string
    {
        $seconds = abs($seconds);
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    /**
     * Send an SSL certificate expiring notification.
     *
     * @param  array{valid: bool, issuer: string|null, valid_from: \Carbon\Carbon|null, valid_to: \Carbon\Carbon|null, days_until_expiration: int|null, error_message: string|null}  $sslResult
     */
    public function sendSslExpiringNotification(Monitor $monitor, array $sslResult): bool
    {
        $days = $sslResult['days_until_expiration'] ?? 0;
        $expiryDate = $sslResult['valid_to']?->format('Y-m-d') ?? 'Unknown';
        $issuer = $sslResult['issuer'] ?? 'Unknown';

        $message = "🔒 SSL Certificate Expiring Soon\n\n"
            ."<b>Monitor:</b> {$monitor->name}\n"
            ."<b>URL:</b> {$monitor->url}\n"
            ."<b>Days until expiration:</b> {$days}\n"
            ."<b>Expires on:</b> {$expiryDate}\n"
            ."<b>Issuer:</b> {$issuer}";

        return $this->sendNotification($message);
    }

    /**
     * Send an SSL certificate expired notification.
     *
     * @param  array{valid: bool, issuer: string|null, valid_from: \Carbon\Carbon|null, valid_to: \Carbon\Carbon|null, days_until_expiration: int|null, error_message: string|null}  $sslResult
     */
    public function sendSslExpiredNotification(Monitor $monitor, array $sslResult): bool
    {
        $error = $sslResult['error_message'] ?? 'Certificate is invalid or expired';
        $expiryDate = $sslResult['valid_to']?->format('Y-m-d') ?? 'Unknown';
        $issuer = $sslResult['issuer'] ?? 'Unknown';

        $message = "🚨 SSL Certificate Expired or Invalid\n\n"
            ."<b>Monitor:</b> {$monitor->name}\n"
            ."<b>URL:</b> {$monitor->url}\n"
            ."<b>Error:</b> {$error}\n"
            ."<b>Expired on:</b> {$expiryDate}\n"
            ."<b>Issuer:</b> {$issuer}";

        return $this->sendNotification($message);
    }

    /**
     * Send a domain expiring notification.
     *
     * @param  array{expires_at: \Carbon\Carbon|null, days_until_expiration: int|null, error_message: string|null}  $domainResult
     */
    public function sendDomainExpiringNotification(Monitor $monitor, array $domainResult): bool
    {
        $days = $domainResult['days_until_expiration'] ?? 0;
        $expiryDate = $domainResult['expires_at']?->format('Y-m-d') ?? 'Unknown';

        $message = "🌐 Domain Expiring Soon\n\n"
            ."<b>Monitor:</b> {$monitor->name}\n"
            ."<b>URL:</b> {$monitor->url}\n"
            ."<b>Days until expiration:</b> {$days}\n"
            ."<b>Expires on:</b> {$expiryDate}";

        return $this->sendNotification($message);
    }

    /**
     * Send a domain expired notification.
     *
     * @param  array{expires_at: \Carbon\Carbon|null, days_until_expiration: int|null, error_message: string|null}  $domainResult
     */
    public function sendDomainExpiredNotification(Monitor $monitor, array $domainResult): bool
    {
        $expiryDate = $domainResult['expires_at']?->format('Y-m-d') ?? 'Unknown';

        $message = "🚨 Domain Expired\n\n"
            ."<b>Monitor:</b> {$monitor->name}\n"
            ."<b>URL:</b> {$monitor->url}\n"
            ."<b>Expired on:</b> {$expiryDate}\n"
            .'⚠️ <b>Action required:</b> Renew domain immediately!';

        return $this->sendNotification($message);
    }
}
