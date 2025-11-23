<?php

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Services\SslCheckService;
use Carbon\Carbon;

test('checkSslCertificate returns error for non-HTTPS URL', function () {
    $monitor = Monitor::factory()->create([
        'url' => 'http://example.com',
    ]);

    $service = new SslCheckService;
    $result = $service->checkSslCertificate($monitor);

    expect($result['valid'])->toBeFalse();
    expect($result['error_message'])->toBe('URL is not HTTPS');
    expect($result['issuer'])->toBeNull();
});

test('checkSslCertificate extracts host correctly', function () {
    $monitor = Monitor::factory()->create([
        'url' => 'https://example.com/path?query=1',
    ]);

    $service = new SslCheckService;
    $result = $service->checkSslCertificate($monitor);

    // Should attempt to check (may fail if no real connection, but should not error on URL parsing)
    expect($result)->toHaveKeys(['valid', 'issuer', 'valid_from', 'valid_to', 'days_until_expiration', 'error_message']);
});

test('checkSslCertificate handles custom port', function () {
    $monitor = Monitor::factory()->create([
        'url' => 'https://example.com:8443',
    ]);

    $service = new SslCheckService;
    $result = $service->checkSslCertificate($monitor);

    expect($result)->toHaveKeys(['valid', 'issuer', 'valid_from', 'valid_to', 'days_until_expiration', 'error_message']);
});

test('isExpiringSoon returns true when certificate expires within 30 days', function () {
    $service = new SslCheckService;

    expect($service->isExpiringSoon(15))->toBeTrue();
    expect($service->isExpiringSoon(30))->toBeTrue();
    expect($service->isExpiringSoon(0))->toBeTrue();
});

test('isExpiringSoon returns false when certificate expires after 30 days', function () {
    $service = new SslCheckService;

    expect($service->isExpiringSoon(31))->toBeFalse();
    expect($service->isExpiringSoon(60))->toBeFalse();
    expect($service->isExpiringSoon(365))->toBeFalse();
});

test('isExpiringSoon returns false for null days', function () {
    $service = new SslCheckService;

    expect($service->isExpiringSoon(null))->toBeFalse();
});

test('updateMonitorCheckWithSsl updates latest check with SSL information', function () {
    $monitor = Monitor::factory()->create([
        'url' => 'https://example.com',
    ]);

    $check = MonitorCheck::factory()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now(),
    ]);

    $sslResult = [
        'valid' => true,
        'issuer' => 'Test CA',
        'valid_from' => Carbon::now()->subYear(),
        'valid_to' => Carbon::now()->addMonths(3),
        'days_until_expiration' => 90,
        'error_message' => null,
    ];

    $service = new SslCheckService;
    $service->updateMonitorCheckWithSsl($monitor, $sslResult);

    $check->refresh();

    expect($check->ssl_valid)->toBeTrue();
    expect($check->ssl_issuer)->toBe('Test CA');
    expect($check->ssl_days_until_expiration)->toBe(90);
    expect($check->ssl_error_message)->toBeNull();
});

test('updateMonitorCheckWithSsl handles expired certificate', function () {
    $monitor = Monitor::factory()->create([
        'url' => 'https://example.com',
    ]);

    $check = MonitorCheck::factory()->create([
        'monitor_id' => $monitor->id,
        'checked_at' => now(),
    ]);

    $sslResult = [
        'valid' => false,
        'issuer' => 'Test CA',
        'valid_from' => Carbon::now()->subYear(),
        'valid_to' => Carbon::now()->subDays(5),
        'days_until_expiration' => 0,
        'error_message' => 'Certificate has expired',
    ];

    $service = new SslCheckService;
    $service->updateMonitorCheckWithSsl($monitor, $sslResult);

    $check->refresh();

    expect($check->ssl_valid)->toBeFalse();
    expect($check->ssl_days_until_expiration)->toBe(0);
    expect($check->ssl_error_message)->toBe('Certificate has expired');
});

test('updateMonitorCheckWithSsl does nothing when no check exists', function () {
    $monitor = Monitor::factory()->create([
        'url' => 'https://example.com',
    ]);

    $sslResult = [
        'valid' => true,
        'issuer' => 'Test CA',
        'valid_from' => Carbon::now()->subYear(),
        'valid_to' => Carbon::now()->addMonths(3),
        'days_until_expiration' => 90,
        'error_message' => null,
    ];

    $service = new SslCheckService;
    $service->updateMonitorCheckWithSsl($monitor, $sslResult);

    // Should not throw an error
    expect(true)->toBeTrue();
});

test('checkSslCertificate validates real valid SSL certificate', function () {
    $monitor = Monitor::factory()->create([
        'url' => 'https://github.com',
    ]);

    $service = new SslCheckService;
    $result = $service->checkSslCertificate($monitor);

    expect($result['valid'])->toBeTrue();
    expect($result['issuer'])->not->toBeNull();
    expect($result['issuer'])->not->toBe('Unknown');
    expect($result['valid_from'])->toBeInstanceOf(Carbon::class);
    expect($result['valid_to'])->toBeInstanceOf(Carbon::class);
    expect($result['days_until_expiration'])->toBeGreaterThan(0);
    expect($result['error_message'])->toBeNull();
});

test('checkSslCertificate detects real expired SSL certificate', function () {
    $monitor = Monitor::factory()->create([
        'url' => 'https://expired.badssl.com',
    ]);

    $service = new SslCheckService;
    $result = $service->checkSslCertificate($monitor);

    expect($result['valid'])->toBeFalse();
    expect($result['issuer'])->not->toBeNull();
    expect($result['days_until_expiration'])->toBe(0);
    expect($result['error_message'])->toBe('Certificate has expired');
    expect($result['valid_to'])->toBeInstanceOf(Carbon::class);
    expect($result['valid_to']->isPast())->toBeTrue();
});
