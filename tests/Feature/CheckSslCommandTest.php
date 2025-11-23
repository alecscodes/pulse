<?php

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Services\SslCheckService;
use App\Services\TelegramNotificationService;
use Illuminate\Support\Facades\Artisan;

test('ssl:check command checks all active HTTPS monitors', function () {
    $httpsMonitor = Monitor::factory()->create([
        'url' => 'https://example.com',
        'is_active' => true,
    ]);

    $httpMonitor = Monitor::factory()->create([
        'url' => 'http://example.com',
        'is_active' => true,
    ]);

    $inactiveMonitor = Monitor::factory()->create([
        'url' => 'https://inactive.com',
        'is_active' => false,
    ]);

    // Create a check for the HTTPS monitor so SSL info can be updated
    MonitorCheck::factory()->create([
        'monitor_id' => $httpsMonitor->id,
        'checked_at' => now(),
    ]);

    $sslService = \Mockery::mock(SslCheckService::class);
    $notificationService = \Mockery::mock(TelegramNotificationService::class);

    $sslService->shouldReceive('checkSslCertificate')
        ->once()
        ->with(\Mockery::on(function ($monitor) use ($httpsMonitor) {
            return $monitor->id === $httpsMonitor->id;
        }))
        ->andReturn([
            'valid' => true,
            'issuer' => 'Test CA',
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addMonths(3),
            'days_until_expiration' => 90,
            'error_message' => null,
        ]);

    $sslService->shouldReceive('updateMonitorCheckWithSsl')
        ->once()
        ->with(\Mockery::on(function ($monitor) use ($httpsMonitor) {
            return $monitor->id === $httpsMonitor->id;
        }), \Mockery::type('array'));

    $sslService->shouldReceive('isExpiringSoon')
        ->once()
        ->with(90)
        ->andReturn(false);

    app()->instance(SslCheckService::class, $sslService);
    app()->instance(TelegramNotificationService::class, $notificationService);

    Artisan::call('ssl:check');

    expect(Artisan::output())->toContain('Checked');
});

test('ssl:check command sends notification for expiring certificates', function () {
    $monitor = Monitor::factory()->create([
        'url' => 'https://example.com',
        'is_active' => true,
    ]);

    MonitorCheck::factory()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now(),
    ]);

    $sslService = \Mockery::mock(SslCheckService::class);
    $notificationService = \Mockery::mock(TelegramNotificationService::class);

    $sslService->shouldReceive('checkSslCertificate')
        ->once()
        ->andReturn([
            'valid' => true,
            'issuer' => 'Test CA',
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addDays(15),
            'days_until_expiration' => 15,
            'error_message' => null,
        ]);

    $sslService->shouldReceive('updateMonitorCheckWithSsl')
        ->once();

    $sslService->shouldReceive('isExpiringSoon')
        ->once()
        ->with(15)
        ->andReturn(true);

    $notificationService->shouldReceive('sendSslExpiringNotification')
        ->once()
        ->with(\Mockery::on(function ($mon) use ($monitor) {
            return $mon->id === $monitor->id;
        }), \Mockery::type('array'))
        ->andReturn(true);

    app()->instance(SslCheckService::class, $sslService);
    app()->instance(TelegramNotificationService::class, $notificationService);

    Artisan::call('ssl:check');

    expect(Artisan::output())->toContain('Expiring Soon');
});

test('ssl:check command sends notification for expired certificates', function () {
    $monitor = Monitor::factory()->create([
        'url' => 'https://example.com',
        'is_active' => true,
    ]);

    MonitorCheck::factory()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now(),
    ]);

    $sslService = \Mockery::mock(SslCheckService::class);
    $notificationService = \Mockery::mock(TelegramNotificationService::class);

    $sslService->shouldReceive('checkSslCertificate')
        ->once()
        ->andReturn([
            'valid' => false,
            'issuer' => 'Test CA',
            'valid_from' => now()->subYear(),
            'valid_to' => now()->subDays(5),
            'days_until_expiration' => 0,
            'error_message' => 'Certificate has expired',
        ]);

    $sslService->shouldReceive('updateMonitorCheckWithSsl')
        ->once();

    $notificationService->shouldReceive('sendSslExpiredNotification')
        ->once()
        ->with(\Mockery::on(function ($mon) use ($monitor) {
            return $mon->id === $monitor->id;
        }), \Mockery::type('array'))
        ->andReturn(true);

    app()->instance(SslCheckService::class, $sslService);
    app()->instance(TelegramNotificationService::class, $notificationService);

    Artisan::call('ssl:check');

    expect(Artisan::output())->toContain('Expired/Invalid');
});

test('ssl:check command works with real valid SSL certificate', function () {
    $monitor = Monitor::factory()->create([
        'url' => 'https://github.com',
        'is_active' => true,
    ]);

    MonitorCheck::factory()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now(),
    ]);

    Artisan::call('ssl:check');

    $check = MonitorCheck::where('monitor_id', $monitor->id)
        ->latest('checked_at')
        ->first();

    expect($check->ssl_valid)->toBeTrue();
    expect($check->ssl_issuer)->not->toBeNull();
    expect($check->ssl_days_until_expiration)->toBeGreaterThan(0);
    expect($check->ssl_error_message)->toBeNull();
    expect($check->ssl_valid_to)->toBeInstanceOf(\Carbon\Carbon::class);
    expect($check->ssl_valid_to->isFuture())->toBeTrue();
});

test('ssl:check command works with real expired SSL certificate', function () {
    $monitor = Monitor::factory()->create([
        'url' => 'https://expired.badssl.com',
        'is_active' => true,
    ]);

    MonitorCheck::factory()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now(),
    ]);

    Artisan::call('ssl:check');

    $check = MonitorCheck::where('monitor_id', $monitor->id)
        ->latest('checked_at')
        ->first();

    expect($check->ssl_valid)->toBeFalse();
    expect($check->ssl_issuer)->not->toBeNull();
    expect($check->ssl_days_until_expiration)->toBe(0);
    expect($check->ssl_error_message)->toBe('Certificate has expired');
    expect($check->ssl_valid_to)->toBeInstanceOf(\Carbon\Carbon::class);
    expect($check->ssl_valid_to->isPast())->toBeTrue();
});
