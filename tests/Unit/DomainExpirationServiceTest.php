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

test('cached whois result is read back from the database store with current days', function () {
    config(['cache.default' => 'database']);
    $this->travelTo('2026-09-23 10:00');
    Cache::put('domain_expiration_example.com', ['expires_at' => '2027-06-15', 'error_message' => null], 3600);

    $service = new DomainExpirationService;
    $first = $service->getDomainExpiration(Monitor::factory()->create(['url' => 'https://example.com']));
    $second = $service->getDomainExpiration(Monitor::factory()->create(['url' => 'https://www.example.com']));

    expect($first['expires_at']->toDateString())->toBe('2027-06-15')
        ->and($first['days_until_expiration'])->toBe(265)
        ->and($second['days_until_expiration'])->toBe(265);
});

test('monitor domain days are computed from today', function () {
    $monitor = Monitor::factory()->create(['domain_expires_at' => '2026-09-24']);

    $this->travelTo('2026-09-23 23:59');
    expect($monitor->domain_days_until_expiration)->toBe(1);

    $this->travelTo('2026-09-25 00:01');
    expect($monitor->domain_days_until_expiration)->toBe(0);
});
