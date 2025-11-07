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
        $duration = $this->formatDuration((int) now()->diffInSeconds($downtime->started_at));

        $message = "?? <b>Monitor Still Down</b>\n\n";
        $message .= "Name: {$monitor->name}\n";
        $message .= "URL: {$monitor->url}\n";
        $message .= "Downtime: {$duration}\n";
        $message .= 'Time: '.now()->format('Y-m-d H:i:s');

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
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }
}
