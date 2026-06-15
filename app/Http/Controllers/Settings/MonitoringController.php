<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\MonitoringUpdateRequest;
use App\Models\Setting;
use App\Services\TelegramNotificationService;
use App\Support\FlashToast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringController extends Controller
{
    /**
     * Display the monitoring settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Monitoring', [
            'telegram_bot_token' => Setting::get('telegram_bot_token'),
            'telegram_chat_id' => Setting::get('telegram_chat_id'),
        ]);
    }

    /**
     * Update the monitoring settings.
     */
    public function update(MonitoringUpdateRequest $request): RedirectResponse
    {
        Setting::set('telegram_bot_token', $request->telegram_bot_token);
        Setting::set('telegram_chat_id', $request->telegram_chat_id);

        FlashToast::success('Monitoring settings updated successfully.');

        return redirect()->route('monitoring.edit');
    }

    /**
     * Send a test Telegram message.
     */
    public function test(Request $request, TelegramNotificationService $telegramService): RedirectResponse
    {
        $request->validate([
            'telegram_bot_token' => ['required', 'string'],
            'telegram_chat_id' => ['required', 'string'],
        ]);

        $originalToken = Setting::get('telegram_bot_token');
        $originalChatId = Setting::get('telegram_chat_id');

        Setting::set('telegram_bot_token', $request->telegram_bot_token);
        Setting::set('telegram_chat_id', $request->telegram_chat_id);

        try {
            $success = $telegramService->sendNotification('Pulse message delivered 🫀');
        } catch (\Exception $e) {
            $success = false;
        }

        if (! $success) {
            Setting::set('telegram_bot_token', $originalToken);
            Setting::set('telegram_chat_id', $originalChatId);

            FlashToast::error('Failed to send test message. Please check your bot token and chat ID.');

            return back();
        }

        FlashToast::success('Test message sent successfully!');

        return back();
    }
}
