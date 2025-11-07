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
        $text = $request->data()['text'];

        return $text === '⚠️ The website https://example.com appears to be down.';
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
        $text = $request->data()['text'];

        return str_contains($text, '✅ The website https://example.com is back up.')
            && str_contains($text, '⏰ It was down for approximately 00:30:00.');
    });
});

test('sendMonitorRecoveryNotification formats duration correctly for 86 seconds', function () {
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
        'started_at' => now()->subSeconds(86),
        'ended_at' => now(),
        'duration_seconds' => 86,
    ]);

    $service = new TelegramNotificationService;

    expect($service->sendMonitorRecoveryNotification($monitor, $downtime))->toBeTrue();

    Http::assertSent(function ($request) {
        $text = $request->data()['text'];

        return $text === '✅ The website https://example.com is back up. ⏰ It was down for approximately 00:01:26.';
    });
});

test('sendMonitorRecoveryNotification formats duration correctly for various durations', function (int $seconds, string $expectedFormat) {
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
        'started_at' => now()->subSeconds($seconds),
        'ended_at' => now(),
        'duration_seconds' => $seconds,
    ]);

    $service = new TelegramNotificationService;

    expect($service->sendMonitorRecoveryNotification($monitor, $downtime))->toBeTrue();

    Http::assertSent(function ($request) use ($expectedFormat) {
        $text = $request->data()['text'];

        return str_contains($text, "approximately {$expectedFormat}.");
    });
})->with([
    'zero seconds' => [0, '00:00:00'],
    'one second' => [1, '00:00:01'],
    'one minute' => [60, '00:01:00'],
    'one hour' => [3600, '01:00:00'],
    'one hour one minute one second' => [3661, '01:01:01'],
    'two hours thirty minutes fifteen seconds' => [9015, '02:30:15'],
    'twenty four hours' => [86400, '24:00:00'],
]);

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
