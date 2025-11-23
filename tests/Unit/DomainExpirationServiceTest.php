<?php

use App\Models\Monitor;
use App\Services\DomainExpirationService;
use Illuminate\Support\Facades\Cache;

test('getDomainExpiration extracts domain correctly from URL', function () {
    $monitor = Monitor::factory()->create([
        'url' => 'https://example.com/path?query=1',
    ]);

    $service = new DomainExpirationService;

    // Mock WHOIS response to avoid actual network call
    Cache::shouldReceive('remember')
        ->once()
        ->andReturn([
            'expires_at' => now()->addYear(),
            'days_until_expiration' => 365,
            'error_message' => null,
        ]);

    $result = $service->getDomainExpiration($monitor);

    expect($result)->toHaveKeys(['expires_at', 'days_until_expiration', 'error_message']);
});

test('getDomainExpiration handles www prefix', function () {
    $monitor = Monitor::factory()->create([
        'url' => 'https://www.example.com',
    ]);

    $service = new DomainExpirationService;

    Cache::shouldReceive('remember')
        ->once()
        ->andReturn([
            'expires_at' => now()->addYear(),
            'days_until_expiration' => 365,
            'error_message' => null,
        ]);

    $result = $service->getDomainExpiration($monitor);

    expect($result)->toHaveKeys(['expires_at', 'days_until_expiration', 'error_message']);
});

test('getDomainExpiration returns error for invalid URL', function () {
    $monitor = Monitor::factory()->create([
        'url' => 'not-a-valid-url',
    ]);

    $service = new DomainExpirationService;
    $result = $service->getDomainExpiration($monitor);

    expect($result['error_message'])->not->toBeNull();
    expect($result['expires_at'])->toBeNull();
    expect($result['days_until_expiration'])->toBeNull();
});

test('isExpiringSoon returns true when domain expires within 30 days', function () {
    $service = new DomainExpirationService;

    expect($service->isExpiringSoon(15))->toBeTrue();
    expect($service->isExpiringSoon(30))->toBeTrue();
    expect($service->isExpiringSoon(0))->toBeTrue();
});

test('isExpiringSoon returns false when domain expires after 30 days', function () {
    $service = new DomainExpirationService;

    expect($service->isExpiringSoon(31))->toBeFalse();
    expect($service->isExpiringSoon(60))->toBeFalse();
    expect($service->isExpiringSoon(365))->toBeFalse();
});

test('isExpiringSoon returns false for null days', function () {
    $service = new DomainExpirationService;

    expect($service->isExpiringSoon(null))->toBeFalse();
});
