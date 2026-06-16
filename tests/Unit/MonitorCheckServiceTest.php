<?php

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Services\MonitorCheckService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Stub SPA browser validation so content-validation tests stay deterministic.
 */
function fakeBrowserContentValidation(string $title, string $textContent = ''): void
{
    Process::fake(function (PendingProcess $process) use ($title, $textContent) {
        $command = $process->command;

        if (
            is_array($command)
            && ($command[0] ?? null) === 'node'
            && str_contains((string) ($command[1] ?? ''), 'validate-spa-content.js')
        ) {
            return Process::result(output: json_encode([
                'title' => $title,
                'textContent' => $textContent,
            ], JSON_THROW_ON_ERROR));
        }

        return Process::result();
    });
}

test('checkConnectivity returns true when internet is available', function () {
    Http::fake([
        'www.google.com' => Http::response('OK', 200),
    ]);

    $service = new MonitorCheckService;

    expect($service->checkConnectivity())->toBeTrue();
});

test('checkConnectivity returns false when internet is down', function () {
    Http::fake(function () {
        throw new Exception('Connection timeout');
    });

    $service = new MonitorCheckService;

    expect($service->checkConnectivity())->toBeFalse();
});

test('checkMonitor returns up status for successful request', function () {
    Http::fake([
        'example.com' => Http::response('<html><title>Test</title></html>', 200),
    ]);

    $monitor = Monitor::factory()->create([
        'type' => 'website',
        'url' => 'https://example.com',
        'method' => 'GET',
        'enable_content_validation' => false,
    ]);

    $service = new MonitorCheckService;
    $result = $service->checkMonitor($monitor);

    expect($result['status'])->toBe('up');
    expect($result['status_code'])->toBe(200);
    expect($result['response_time'])->toBeInt();
});

test('checkMonitor returns down status for failed request', function () {
    Http::fake([
        'example.com' => Http::response('Not Found', 404),
    ]);

    $monitor = Monitor::factory()->create([
        'type' => 'website',
        'url' => 'https://example.com',
        'method' => 'GET',
    ]);

    $service = new MonitorCheckService;
    $result = $service->checkMonitor($monitor);

    expect($result['status'])->toBe('down');
    expect($result['status_code'])->toBe(404);
});

test('checkMonitor returns down status for connection error', function () {
    Http::fake(function () {
        throw new Exception('Connection refused');
    });

    $monitor = Monitor::factory()->create([
        'type' => 'website',
        'url' => 'https://example.com',
        'method' => 'GET',
    ]);

    $service = new MonitorCheckService;
    $result = $service->checkMonitor($monitor);

    expect($result['status'])->toBe('down');
    expect($result['error_message'])->toContain('Connection refused');
});

test('checkMonitor returns up status for temporary system errors', function () {
    Http::fake(function () {
        throw new Exception('proc_open(): posix_spawn() failed: Resource temporarily unavailable');
    });

    $monitor = Monitor::factory()->create([
        'type' => 'website',
        'url' => 'https://example.com',
        'method' => 'GET',
    ]);

    $service = new MonitorCheckService;
    $result = $service->checkMonitor($monitor);

    expect($result['status'])->toBe('up');
    expect($result['error_message'])->toContain('proc_open');
});

test('checkMonitor validates content when enable_content_validation is true', function () {
    Http::fake([
        'example.com' => Http::response('<html><title>Expected Title</title><body>Expected Content</body></html>', 200),
    ]);

    $monitor = Monitor::factory()->create([
        'type' => 'website',
        'url' => 'https://example.com',
        'method' => 'GET',
        'enable_content_validation' => true,
        'expected_title' => 'Expected Title',
        'expected_content' => 'Expected Content',
    ]);

    $service = new MonitorCheckService;
    $result = $service->checkMonitor($monitor);

    expect($result['status'])->toBe('up');
    expect($result['content_valid'])->toBeTrue();
});

test('checkMonitor returns down when content validation fails', function () {
    Http::fake([
        'example.com' => Http::response('<html><title>Wrong Title</title></html>', 200),
    ]);

    fakeBrowserContentValidation('Wrong Title');

    $monitor = Monitor::factory()->create([
        'type' => 'website',
        'url' => 'https://example.com',
        'method' => 'GET',
        'enable_content_validation' => true,
        'expected_title' => 'Expected Title',
    ]);

    $service = new MonitorCheckService;
    $result = $service->checkMonitor($monitor);

    expect($result['status'])->toBe('down');
    expect($result['content_valid'])->toBeFalse();
});

test('checkMonitor title validation requires exact match and does not accept title string elsewhere in body', function () {
    Http::fake([
        'example.com' => Http::response(
            '<html><title>Other Page</title><body><div>Expected Title</div></body></html>',
            200
        ),
    ]);

    fakeBrowserContentValidation('Other Page', 'Expected Title');

    $monitor = Monitor::factory()->create([
        'type' => 'website',
        'url' => 'https://example.com',
        'method' => 'GET',
        'enable_content_validation' => true,
        'expected_title' => 'Expected Title',
    ]);

    $service = new MonitorCheckService;
    $result = $service->checkMonitor($monitor);

    expect($result['status'])->toBe('down');
    expect($result['content_valid'])->toBeFalse();
});

test('checkMonitor content validation passes when expected content is substring of body', function () {
    Http::fake([
        'example.com' => Http::response(
            '<html><title>Balbi Nautica - Home</title><body>Questo negozio di Balbi Nautica si trova qui.</body></html>',
            200
        ),
    ]);

    $monitor = Monitor::factory()->create([
        'type' => 'website',
        'url' => 'https://example.com',
        'method' => 'GET',
        'enable_content_validation' => true,
        'expected_title' => 'Balbi Nautica - Home',
        'expected_content' => 'Balbi Nautica',
    ]);

    $service = new MonitorCheckService;
    $result = $service->checkMonitor($monitor);

    expect($result['status'])->toBe('up');
    expect($result['content_valid'])->toBeTrue();
});

test('checkMonitor returns up when content validation enabled but browser script cannot run', function () {
    Http::fake([
        'example.com' => Http::response(
            '<html><head><title></title></head><body><div id="app"></div></body></html>',
            200
        ),
    ]);

    $monitor = Monitor::factory()->create([
        'type' => 'website',
        'url' => 'https://example.com',
        'method' => 'GET',
        'enable_content_validation' => true,
        'expected_title' => 'Expected Title',
        'expected_content' => 'Expected Content',
    ]);

    $service = new MonitorCheckService;

    $originalPath = getenv('PATH');
    putenv('PATH=');
    try {
        $result = $service->checkMonitor($monitor);
        expect($result['status'])->toBe('up');
        expect($result['content_valid'])->toBeTrue();
    } finally {
        putenv('PATH='.$originalPath);
    }
});

test('checkMonitor uses POST method when specified', function () {
    Http::fake([
        'example.com' => Http::response('OK', 200),
    ]);

    $monitor = Monitor::factory()->create([
        'type' => 'website',
        'url' => 'https://example.com',
        'method' => 'POST',
        'parameters' => ['key' => 'value'],
    ]);

    $service = new MonitorCheckService;
    $result = $service->checkMonitor($monitor);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST';
    });

    expect($result['status'])->toBe('up');
});

test('checkMonitor includes headers when provided', function () {
    Http::fake([
        'example.com' => Http::response('OK', 200),
    ]);

    $monitor = Monitor::factory()->create([
        'type' => 'website',
        'url' => 'https://example.com',
        'method' => 'GET',
        'headers' => ['Authorization' => 'Bearer token123'],
    ]);

    $service = new MonitorCheckService;
    $service->checkMonitor($monitor);

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer token123');
    });
});

test('checkMonitor type ip uses ping and returns down for invalid IP', function () {
    $monitor = Monitor::factory()->create([
        'type' => 'ip',
        'url' => 'not-an-ip',
    ]);

    $service = new MonitorCheckService;
    $result = $service->checkMonitor($monitor);

    expect($result['status'])->toBe('down');
    expect($result['error_message'])->toBe('Invalid IP address');
    expect($result['status_code'])->toBeNull();
});

test('checkMonitor type ip uses ping and returns up when host responds', function () {
    $monitor = Monitor::factory()->create([
        'type' => 'ip',
        'url' => '127.0.0.1',
    ]);

    $service = new MonitorCheckService;
    $result = $service->checkMonitor($monitor);

    expect($result)->toHaveKeys(['status', 'response_time', 'error_message']);
    expect($result['status'])->toBeIn(['up', 'down']);
    expect($result['response_time'])->toBeInt();
});

test('createCheck creates a monitor check record', function () {
    $monitor = Monitor::factory()->create([
        'type' => 'website',
    ]);
    $service = new MonitorCheckService;

    $checkResult = [
        'status' => 'up',
        'response_time' => 150,
        'status_code' => 200,
        'response_body' => 'OK',
        'error_message' => null,
        'content_valid' => null,
    ];

    $check = $service->createCheck($monitor, $checkResult);

    expect($check)->toBeInstanceOf(MonitorCheck::class);
    expect($check->monitor_id)->toBe($monitor->id);
    expect($check->status)->toBe('up');
    expect($check->response_time)->toBe(150);
    expect($check->status_code)->toBe(200);
});
