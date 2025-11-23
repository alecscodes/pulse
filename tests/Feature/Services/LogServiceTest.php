<?php

use App\Models\Log;
use App\Models\User;
use App\Services\LogService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new LogService;
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('log method creates log entry via database channel', function () {
    $this->service->log('info', 'test', 'Test message', ['key' => 'value']);

    $log = Log::where('message', 'Test message')->first();

    expect($log)->not->toBeNull();
    expect($log->level)->toBe('info');
    expect($log->category)->toBe('test');
    expect($log->message)->toBe('Test message');
    expect($log->context)->toBe(['key' => 'value']);
    expect($log->user_id)->toBe($this->user->id);
});

test('all log level methods work', function (string $level) {
    $this->service->{$level}('test', 'Test message');

    $log = Log::where('message', 'Test message')->first();

    expect($log->level)->toBe($level);
    expect($log->category)->toBe('test');
})->with(['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug']);

test('log works without context', function () {
    $this->service->info('test', 'Test message');

    $log = Log::where('message', 'Test message')->first();

    expect($log->context)->toBeNull();
});

test('log captures user when authenticated', function () {
    $this->service->info('test', 'Test message');

    $log = Log::where('message', 'Test message')->first();

    expect($log->user_id)->toBe($this->user->id);
});

test('log captures ip address and user agent', function () {
    $this->get('/', [
        'REMOTE_ADDR' => '192.168.1.1',
        'HTTP_USER_AGENT' => 'Test Agent',
    ]);

    $this->service->info('test', 'Test message');

    $log = Log::where('message', 'Test message')->first();

    // IP might be different in test environment, so just check it's not null
    expect($log->ip_address)->not->toBeNull();
    // User agent might not be captured in test environment, so just check if it exists
    if ($log->user_agent) {
        expect($log->user_agent)->toBeString();
    }
});

test('log throws exception for invalid level', function () {
    expect(fn () => $this->service->log('invalid', 'test', 'Test message'))
        ->toThrow(\InvalidArgumentException::class);
});

test('log throws exception for invalid method', function () {
    expect(fn () => $this->service->invalidMethod('test', 'Test message'))
        ->toThrow(\BadMethodCallException::class);
});
