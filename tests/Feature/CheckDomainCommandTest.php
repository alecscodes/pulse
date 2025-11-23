<?php

use App\Models\Monitor;
use App\Services\DomainExpirationService;
use App\Services\TelegramNotificationService;
use Illuminate\Support\Facades\Artisan;

test('domain:check command checks all active monitors', function () {
    $monitor1 = Monitor::factory()->create([
        'url' => 'https://example.com',
        'is_active' => true,
    ]);

    $monitor2 = Monitor::factory()->create([
        'url' => 'https://test.com',
        'is_active' => true,
    ]);

    $inactiveMonitor = Monitor::factory()->create([
        'url' => 'https://inactive.com',
        'is_active' => false,
    ]);

    $domainService = \Mockery::mock(DomainExpirationService::class);
    $notificationService = \Mockery::mock(TelegramNotificationService::class);

    $domainService->shouldReceive('getDomainExpiration')
        ->twice()
        ->andReturn([
            'expires_at' => now()->addYear(),
            'days_until_expiration' => 365,
            'error_message' => null,
        ]);

    $domainService->shouldReceive('isExpiringSoon')
        ->twice()
        ->with(365)
        ->andReturn(false);

    app()->instance(DomainExpirationService::class, $domainService);
    app()->instance(TelegramNotificationService::class, $notificationService);

    Artisan::call('domain:check');

    expect(Artisan::output())->toContain('Checked');

    $monitor1->refresh();
    $monitor2->refresh();

    expect($monitor1->domain_expires_at)->not->toBeNull();
    expect($monitor1->domain_days_until_expiration)->toBe(365);
    expect($monitor1->domain_last_checked_at)->not->toBeNull();
});

test('domain:check command sends notification for expiring domains', function () {
    $monitor = Monitor::factory()->create([
        'url' => 'https://example.com',
        'is_active' => true,
    ]);

    $domainService = \Mockery::mock(DomainExpirationService::class);
    $notificationService = \Mockery::mock(TelegramNotificationService::class);

    $domainService->shouldReceive('getDomainExpiration')
        ->once()
        ->andReturn([
            'expires_at' => now()->addDays(15),
            'days_until_expiration' => 15,
            'error_message' => null,
        ]);

    $domainService->shouldReceive('isExpiringSoon')
        ->once()
        ->with(15)
        ->andReturn(true);

    $notificationService->shouldReceive('sendDomainExpiringNotification')
        ->once()
        ->with(\Mockery::on(function ($mon) use ($monitor) {
            return $mon->id === $monitor->id;
        }), \Mockery::type('array'))
        ->andReturn(true);

    app()->instance(DomainExpirationService::class, $domainService);
    app()->instance(TelegramNotificationService::class, $notificationService);

    Artisan::call('domain:check');

    expect(Artisan::output())->toContain('Expiring Soon');
});

test('domain:check command sends notification for expired domains', function () {
    $monitor = Monitor::factory()->create([
        'url' => 'https://example.com',
        'is_active' => true,
    ]);

    $domainService = \Mockery::mock(DomainExpirationService::class);
    $notificationService = \Mockery::mock(TelegramNotificationService::class);

    $domainService->shouldReceive('getDomainExpiration')
        ->once()
        ->andReturn([
            'expires_at' => now()->subDays(5),
            'days_until_expiration' => 0,
            'error_message' => null,
        ]);

    $notificationService->shouldReceive('sendDomainExpiredNotification')
        ->once()
        ->with(\Mockery::on(function ($mon) use ($monitor) {
            return $mon->id === $monitor->id;
        }), \Mockery::type('array'))
        ->andReturn(true);

    app()->instance(DomainExpirationService::class, $domainService);
    app()->instance(TelegramNotificationService::class, $notificationService);

    Artisan::call('domain:check');

    expect(Artisan::output())->toContain('Expired');
});

test('domain:check command handles errors gracefully', function () {
    $monitor = Monitor::factory()->create([
        'url' => 'https://example.com',
        'is_active' => true,
    ]);

    $domainService = \Mockery::mock(DomainExpirationService::class);
    $notificationService = \Mockery::mock(TelegramNotificationService::class);

    $domainService->shouldReceive('getDomainExpiration')
        ->once()
        ->andReturn([
            'expires_at' => null,
            'days_until_expiration' => null,
            'error_message' => 'Connection failed',
        ]);

    app()->instance(DomainExpirationService::class, $domainService);
    app()->instance(TelegramNotificationService::class, $notificationService);

    Artisan::call('domain:check');

    $monitor->refresh();

    expect($monitor->domain_error_message)->toBe('Connection failed');
    expect(Artisan::output())->toContain('Errors');
});

test('domain:check command handles no active monitors', function () {
    Monitor::factory()->create([
        'url' => 'https://example.com',
        'is_active' => false,
    ]);

    Artisan::call('domain:check');

    expect(Artisan::output())->toContain('No active monitors found');
});
