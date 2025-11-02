<?php

use App\Models\Monitor;
use App\Models\MonitorDowntime;
use App\Models\Setting;
use App\Services\TelegramNotificationService;
use Illuminate\Support\Facades\Http;

test('sendNotification sends message to Telegram when configured', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    Setting::set('telegram_bot_token', 'test-token');
    Setting::set('telegram_chat_id', 'test-chat-id');

    $service = new TelegramNotificationService;

    expect($service->sendNotification('Test message'))->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
            && $request->data()['chat_id'] === 'test-chat-id'
            && $request->data()['text'] === 'Test message';
    });
});

test('sendNotification returns false when not configured', function () {
    Setting::set('telegram_bot_token', null);
    Setting::set('telegram_chat_id', null);

    $service = new TelegramNotificationService;

    expect($service->sendNotification('Test message'))->toBeFalse();
});

test('sendMonitorDownNotification sends correct message', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    Setting::set('telegram_bot_token', 'test-token');
    Setting::set('telegram_chat_id', 'test-chat-id');

    $monitor = Monitor::factory()->create([
        'name' => 'Test Monitor',
        'url' => 'https://example.com',
    ]);

    $service = new TelegramNotificationService;

    expect($service->sendMonitorDownNotification($monitor))->toBeTrue();

    Http::assertSent(function ($request) {
        return str_contains($request->data()['text'], 'Monitor Down')
            && str_contains($request->data()['text'], 'Test Monitor')
            && str_contains($request->data()['text'], 'https://example.com');
    });
});

test('sendMonitorRecoveryNotification sends correct message with duration', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    Setting::set('telegram_bot_token', 'test-token');
    Setting::set('telegram_chat_id', 'test-chat-id');

    $monitor = Monitor::factory()->create([
        'name' => 'Test Monitor',
        'url' => 'https://example.com',
    ]);

    $downtime = MonitorDowntime::factory()->create([
        'monitor_id' => $monitor->id,
        'started_at' => now()->subMinutes(30),
        'ended_at' => now(),
        'duration_seconds' => 1800,
    ]);

    $service = new TelegramNotificationService;

    expect($service->sendMonitorRecoveryNotification($monitor, $downtime))->toBeTrue();

    Http::assertSent(function ($request) {
        return str_contains($request->data()['text'], 'Monitor Recovered')
            && str_contains($request->data()['text'], 'Test Monitor');
    });
});

test('sendMonitorStillDownNotification sends correct message', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    Setting::set('telegram_bot_token', 'test-token');
    Setting::set('telegram_chat_id', 'test-chat-id');

    $monitor = Monitor::factory()->create([
        'name' => 'Test Monitor',
        'url' => 'https://example.com',
    ]);

    $downtime = MonitorDowntime::factory()->create([
        'monitor_id' => $monitor->id,
        'started_at' => now()->subMinutes(15),
    ]);

    $service = new TelegramNotificationService;

    expect($service->sendMonitorStillDownNotification($monitor, $downtime))->toBeTrue();

    Http::assertSent(function ($request) {
        return str_contains($request->data()['text'], 'Monitor Still Down')
            && str_contains($request->data()['text'], 'Test Monitor');
    });
});
