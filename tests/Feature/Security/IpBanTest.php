<?php

use App\Http\Middleware\CheckBannedIp;
use App\Models\User;
use App\Services\IpBanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Cache::flush();
});

test('middleware blocks banned IPs', function () {
    $request = Request::create('/test', 'GET');
    $request->server->set('REMOTE_ADDR', '192.168.1.100');

    DB::table('banned_ips')->insert([
        'ip' => '192.168.1.100',
        'reason' => 'Test ban',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Cache::flush();

    $middleware = new CheckBannedIp(app(IpBanService::class));

    try {
        $response = $middleware->handle($request, fn ($req) => response('ok'));
        expect($response->getStatusCode())->toBe(403);
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});

test('middleware allows non-banned IPs', function () {
    $request = Request::create('/test', 'GET');
    $request->server->set('REMOTE_ADDR', '192.168.1.200');

    Cache::flush();

    $middleware = new CheckBannedIp(app(IpBanService::class));

    $response = $middleware->handle($request, fn ($req) => response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('service bans IP after 2 failed login attempts', function () {
    $request = Request::create('/login', 'POST');
    $request->server->set('REMOTE_ADDR', '192.168.1.100');

    $service = app(IpBanService::class);

    $service->recordFailedLogin($request);
    expect(DB::table('banned_ips')->where('ip', '192.168.1.100')->exists())->toBeFalse();

    $service->recordFailedLogin($request);
    expect(DB::table('banned_ips')->where('ip', '192.168.1.100')->exists())->toBeTrue();
});

test('service detects all IP sources', function () {
    $request = Request::create('/test', 'GET');
    $request->server->set('REMOTE_ADDR', '192.168.1.1');
    $request->headers->set('X-Forwarded-For', '10.0.0.1, 10.0.0.2');
    $request->headers->set('CF-Connecting-IP', '172.16.0.1');

    $service = app(IpBanService::class);
    $ips = $service->getAllIps($request);

    expect($ips)->toContain('192.168.1.1')
        ->toContain('10.0.0.1')
        ->toContain('10.0.0.2')
        ->toContain('172.16.0.1');
});

test('failed login event triggers IP ban after 2 attempts', function () {
    $user = User::factory()->create();

    $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.100']);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    expect(DB::table('banned_ips')->where('ip', '192.168.1.100')->exists())->toBeFalse();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    expect(DB::table('banned_ips')->where('ip', '192.168.1.100')->exists())->toBeTrue();
});

test('non-existent routes ban IPs on GET requests', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.50']);

    $response = $this->get('/wordpress');

    expect(DB::table('banned_ips')->where('ip', '192.168.1.50')->exists())->toBeTrue();
    expect(DB::table('banned_ips')->where('reason', 'like', '%wordpress%')->exists())->toBeTrue();
});

test('non-existent routes ban IPs on POST requests', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.51']);

    $response = $this->post('/wordpress');

    expect(DB::table('banned_ips')->where('ip', '192.168.1.51')->exists())->toBeTrue();
    expect(DB::table('banned_ips')->where('reason', 'like', '%wordpress%')->exists())->toBeTrue();
});

test('banned IPs cannot access any routes', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.52']);

    DB::table('banned_ips')->insert([
        'ip' => '192.168.1.52',
        'reason' => 'Test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Cache::flush();

    $response = $this->get('/');

    $response->assertForbidden();
});

test('service bans all related IPs from request', function () {
    $request = Request::create('/test', 'GET');
    $request->server->set('REMOTE_ADDR', '192.168.1.1');
    $request->headers->set('X-Forwarded-For', '10.0.0.1');

    $service = app(IpBanService::class);
    $service->ban($request, 'Test ban');

    expect(DB::table('banned_ips')->where('ip', '192.168.1.1')->exists())->toBeTrue();
    expect(DB::table('banned_ips')->where('ip', '10.0.0.1')->exists())->toBeTrue();
});

test('service caches banned IP checks', function () {
    $request = Request::create('/test', 'GET');
    $request->server->set('REMOTE_ADDR', '192.168.1.100');

    DB::table('banned_ips')->insert([
        'ip' => '192.168.1.100',
        'reason' => 'Test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(IpBanService::class);

    expect($service->isBanned($request))->toBeTrue();

    DB::table('banned_ips')->where('ip', '192.168.1.100')->delete();

    expect($service->isBanned($request))->toBeTrue();

    Cache::forget('banned_ip_192.168.1.100');
    expect($service->isBanned($request))->toBeFalse();
});

test('service can unban a specific IP', function () {
    $service = app(IpBanService::class);

    DB::table('banned_ips')->insert([
        'ip' => '192.168.1.200',
        'reason' => 'Test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($service->unban('192.168.1.200'))->toBeTrue();
    expect(DB::table('banned_ips')->where('ip', '192.168.1.200')->exists())->toBeFalse();
});

test('service can unban all IPs', function () {
    $service = app(IpBanService::class);

    DB::table('banned_ips')->insert([
        ['ip' => '192.168.1.1', 'reason' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['ip' => '192.168.1.2', 'reason' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['ip' => '192.168.1.3', 'reason' => 'Test', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $count = $service->unbanAll();

    expect($count)->toBe(3);
    expect(DB::table('banned_ips')->count())->toBe(0);
});

test('artisan command unban specific IP', function () {
    DB::table('banned_ips')->insert([
        'ip' => '192.168.1.100',
        'reason' => 'Test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('ip:unban', ['ip' => '192.168.1.100'])
        ->expectsOutput('IP address 192.168.1.100 has been unbanned.')
        ->assertSuccessful();

    expect(DB::table('banned_ips')->where('ip', '192.168.1.100')->exists())->toBeFalse();
});

test('artisan command unban all IPs', function () {
    DB::table('banned_ips')->insert([
        ['ip' => '192.168.1.1', 'reason' => 'Test', 'created_at' => now(), 'updated_at' => now()],
        ['ip' => '192.168.1.2', 'reason' => 'Test', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->artisan('ip:unban', ['--all' => true])
        ->expectsOutput('Unbanned 2 IP address(es).')
        ->assertSuccessful();

    expect(DB::table('banned_ips')->count())->toBe(0);
});

test('artisan command validates IP address', function () {
    $this->artisan('ip:unban', ['ip' => 'invalid-ip'])
        ->expectsOutput('Invalid IP address: invalid-ip')
        ->assertFailed();
});

test('artisan command warns when IP not found', function () {
    $this->artisan('ip:unban', ['ip' => '10.0.0.1'])
        ->expectsOutput('IP address 10.0.0.1 was not found in the banned list.')
        ->assertSuccessful();
});
