<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\MonitoringUpdateRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
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

        return redirect()->route('monitoring.edit')->with('success', 'Monitoring settings updated successfully.');
    }
}
