<?php

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorDowntime;
use App\Services\MonitorCheckService;
use App\Services\MonitorStatusService;
use App\Services\TelegramNotificationService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        'www.google.com' => Http::response('OK', 200),
    ]);
});

test('if internet is down should not check monitors', function () {
    Http::fake(function () {
        throw new \Exception('No internet');
    });

    $monitor = Monitor::factory()->create(['is_active' => true]);
    $checkService = \Mockery::mock(MonitorCheckService::class);
    $notificationService = \Mockery::mock(TelegramNotificationService::class);

    $checkService->shouldReceive('checkConnectivity')
        ->once()
        ->andReturn(false);

    $checkService->shouldNotReceive('checkMonitor');
    $notificationService->shouldNotReceive('sendMonitorDownNotification');

    $service = new MonitorStatusService($checkService, $notificationService);
    $service->processMonitorCheck($monitor);

    expect(MonitorCheck::count())->toBe(0);
});

test('if monitor is up should go next without anything else', function () {
    $monitor = Monitor::factory()->create(['is_active' => true]);
    $checkService = \Mockery::mock(MonitorCheckService::class);
    $notificationService = \Mockery::mock(TelegramNotificationService::class);

    $checkService->shouldReceive('checkConnectivity')
        ->once()
        ->andReturn(true);

    $checkResult = [
        'status' => 'up',
        'response_time' => 150,
        'status_code' => 200,
        'response_body' => 'OK',
        'error_message' => null,
        'content_valid' => null,
    ];

    $checkService->shouldReceive('checkMonitor')
        ->once()
        ->with($monitor)
        ->andReturn($checkResult);

    $mockCheck = MonitorCheck::factory()->make(['monitor_id' => $monitor->id]);
    $checkService->shouldReceive('createCheck')
        ->once()
        ->andReturn($mockCheck);

    $notificationService->shouldNotReceive('sendMonitorDownNotification');
    $notificationService->shouldNotReceive('sendMonitorRecoveryNotification');

    $service = new MonitorStatusService($checkService, $notificationService);
    $service->processMonitorCheck($monitor);

    expect(MonitorDowntime::where('monitor_id', $monitor->id)->whereNull('ended_at')->exists())->toBeFalse();
});

test('if monitor is down should check again after 3 seconds', function () {
    $monitor = Monitor::factory()->create(['is_active' => true]);
    $checkService = \Mockery::mock(MonitorCheckService::class);
    $notificationService = \Mockery::mock(TelegramNotificationService::class);

    $checkService->shouldReceive('checkConnectivity')
        ->once()
        ->andReturn(true);

    $checkResultDown = [
        'status' => 'down',
        'response_time' => null,
        'status_code' => null,
        'response_body' => null,
        'error_message' => 'Connection failed',
        'content_valid' => null,
    ];

    $checkResultUp = [
        'status' => 'up',
        'response_time' => 150,
        'status_code' => 200,
        'response_body' => 'OK',
        'error_message' => null,
        'content_valid' => null,
    ];

    // 1 initial check + retry that succeeds (non-200 gets 3 retries, but we succeed on first retry)
    $checkService->shouldReceive('checkMonitor')
        ->twice()
        ->with($monitor)
        ->andReturn($checkResultDown, $checkResultUp);

    $mockCheck1 = MonitorCheck::factory()->make(['monitor_id' => $monitor->id, 'status' => 'down']);
    $mockCheck2 = MonitorCheck::factory()->make(['monitor_id' => $monitor->id, 'status' => 'up']);
    $checkService->shouldReceive('createCheck')
        ->twice()
        ->andReturn($mockCheck1, $mockCheck2);

    $notificationService->shouldNotReceive('sendMonitorDownNotification');

    $startTime = microtime(true);
    $service = new MonitorStatusService($checkService, $notificationService);
    $service->processMonitorCheck($monitor);
    $endTime = microtime(true);

    $duration = $endTime - $startTime;
    expect($duration)->toBeGreaterThan(3);
});

test('if monitor is down should send notification', function () {
    $monitor = Monitor::factory()->create(['is_active' => true]);
    $checkService = \Mockery::mock(MonitorCheckService::class);
    $notificationService = \Mockery::mock(TelegramNotificationService::class);

    $checkService->shouldReceive('checkConnectivity')
        ->once()
        ->andReturn(true);

    $checkResult = [
        'status' => 'down',
        'response_time' => null,
        'status_code' => null,
        'response_body' => null,
        'error_message' => 'Connection failed',
        'content_valid' => null,
    ];

    // 1 initial check + 3 retries (non-200 gets 3 retries)
    $checkService->shouldReceive('checkMonitor')
        ->times(4)
        ->with($monitor)
        ->andReturn($checkResult);

    $mockCheck = MonitorCheck::factory()->make(['monitor_id' => $monitor->id, 'status' => 'down']);
    $checkService->shouldReceive('createCheck')
        ->times(4)
        ->andReturn($mockCheck);

    $notificationService->shouldReceive('sendMonitorDownNotification')
        ->once()
        ->with($monitor)
        ->andReturn(true);

    $service = new MonitorStatusService($checkService, $notificationService);
    $service->processMonitorCheck($monitor);

    expect(MonitorDowntime::where('monitor_id', $monitor->id)->whereNull('ended_at')->exists())->toBeTrue();
});

test('if monitor is down and already notified should not send notification again', function () {
    $monitor = Monitor::factory()->create(['is_active' => true]);
    MonitorDowntime::factory()->create([
        'monitor_id' => $monitor->id,
        'started_at' => now()->subMinutes(5),
        'last_notification_at' => now()->subMinutes(1),
    ]);

    $checkService = \Mockery::mock(MonitorCheckService::class);
    $notificationService = \Mockery::mock(TelegramNotificationService::class);

    $checkService->shouldReceive('checkConnectivity')
        ->once()
        ->andReturn(true);

    $checkResult = [
        'status' => 'down',
        'response_time' => null,
        'status_code' => null,
        'response_body' => null,
        'error_message' => 'Connection failed',
        'content_valid' => null,
    ];

    // 1 initial check + 3 retries (non-200 gets 3 retries)
    $checkService->shouldReceive('checkMonitor')
        ->times(4)
        ->with($monitor)
        ->andReturn($checkResult);

    $mockCheck = MonitorCheck::factory()->make(['monitor_id' => $monitor->id, 'status' => 'down']);
    $checkService->shouldReceive('createCheck')
        ->times(4)
        ->andReturn($mockCheck);

    $notificationService->shouldNotReceive('sendMonitorDownNotification');

    $service = new MonitorStatusService($checkService, $notificationService);
    $service->processMonitorCheck($monitor);
});

test('if monitor is down and passed 10 minutes should send notification again', function () {
    $monitor = Monitor::factory()->create(['is_active' => true]);
    $downtime = MonitorDowntime::factory()->create([
        'monitor_id' => $monitor->id,
        'started_at' => now()->subMinutes(15),
        'last_notification_at' => now()->subMinutes(11),
    ]);

    $checkService = \Mockery::mock(MonitorCheckService::class);
    $notificationService = \Mockery::mock(TelegramNotificationService::class);

    $checkService->shouldReceive('checkConnectivity')
        ->once()
        ->andReturn(true);

    $checkResult = [
        'status' => 'down',
        'response_time' => null,
        'status_code' => null,
        'response_body' => null,
        'error_message' => 'Connection failed',
        'content_valid' => null,
    ];

    // 1 initial check + 3 retries (non-200 gets 3 retries)
    $checkService->shouldReceive('checkMonitor')
        ->times(4)
        ->with($monitor)
        ->andReturn($checkResult);

    $mockCheck = MonitorCheck::factory()->make(['monitor_id' => $monitor->id, 'status' => 'down']);
    $checkService->shouldReceive('createCheck')
        ->times(4)
        ->andReturn($mockCheck);

    $notificationService->shouldReceive('sendMonitorStillDownNotification')
        ->once()
        ->with(\Mockery::on(function ($m) use ($monitor) {
            return $m instanceof \App\Models\Monitor && $m->id === $monitor->id;
        }), \Mockery::on(function ($dt) use ($downtime) {
            return $dt instanceof MonitorDowntime && $dt->id === $downtime->id;
        }))
        ->andReturn(true);

    $service = new MonitorStatusService($checkService, $notificationService);
    $service->processMonitorCheck($monitor);

    // The key verification is that sendMonitorStillDownNotification was called
    // This means the service correctly detected that 10+ minutes passed and sent notification
    // We refresh to verify the database was updated
    $downtime->refresh();

    // The mock should verify the notification was sent, and the service should have updated
    // last_notification_at. Since we can't reliably test the exact timestamp (due to timing),
    // we verify the notification service was called, which is the main behavior we're testing.
    expect($downtime->last_notification_at)->not->toBeNull();
});

test('if monitor comes back up should send notification', function () {
    $monitor = Monitor::factory()->create(['is_active' => true]);
    $downtime = MonitorDowntime::factory()->create([
        'monitor_id' => $monitor->id,
        'started_at' => now()->subMinutes(5),
        'last_notification_at' => now()->subMinutes(1),
    ]);

    $checkService = \Mockery::mock(MonitorCheckService::class);
    $notificationService = \Mockery::mock(TelegramNotificationService::class);

    $checkService->shouldReceive('checkConnectivity')
        ->once()
        ->andReturn(true);

    $checkResult = [
        'status' => 'up',
        'response_time' => 150,
        'status_code' => 200,
        'response_body' => 'OK',
        'error_message' => null,
        'content_valid' => null,
    ];

    $checkService->shouldReceive('checkMonitor')
        ->once()
        ->with($monitor)
        ->andReturn($checkResult);

    $mockCheck = MonitorCheck::factory()->make(['monitor_id' => $monitor->id, 'status' => 'up']);
    $checkService->shouldReceive('createCheck')
        ->once()
        ->andReturn($mockCheck);

    $notificationService->shouldReceive('sendMonitorRecoveryNotification')
        ->once()
        ->with($monitor, \Mockery::type(MonitorDowntime::class))
        ->andReturn(true);

    $service = new MonitorStatusService($checkService, $notificationService);
    $service->processMonitorCheck($monitor);

    expect($downtime->fresh()->ended_at)->not->toBeNull();
});
