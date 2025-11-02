<?php

use App\Models\Monitor;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('can view monitors index page in browser', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'email' => 'test@example.com',
        'password' => 'password',
    ]);
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $page = visit('/login')
        ->type('email', 'test@example.com')
        ->type('password', 'password')
        ->click('Log in')
        ->assertPathIs('/dashboard')
        ->navigate('/monitors');

    $page->assertSee($monitor->name)
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('can view create monitor page in browser', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $page = visit('/login')
        ->type('email', 'test@example.com')
        ->type('password', 'password')
        ->click('Log in')
        ->navigate('/monitors/create');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('can create a monitor through browser', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $page = visit('/login')
        ->type('email', 'test@example.com')
        ->type('password', 'password')
        ->click('Log in')
        ->navigate('/monitors/create')
        ->assertNoJavascriptErrors();

    $page->type('name', 'Test Monitor')
        ->type('url', 'https://example.com')
        ->select('type', 'website')
        ->type('check_interval', '60')
        ->click('Create Monitor')
        ->assertSee('Monitor created successfully')
        ->assertPathIs('/monitors/*');

    expect(Monitor::where('name', 'Test Monitor')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('can view monitor details in browser', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password',
    ]);
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $page = visit('/login')
        ->type('email', 'test@example.com')
        ->type('password', 'password')
        ->click('Log in')
        ->navigate("/monitors/{$monitor->id}");

    $page->assertSee($monitor->name)
        ->assertSee($monitor->url)
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('can view monitor history in browser', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password',
    ]);
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $page = visit('/login')
        ->type('email', 'test@example.com')
        ->type('password', 'password')
        ->click('Log in')
        ->navigate("/monitors/{$monitor->id}");

    $page->assertNoJavascriptErrors();
});

it('can view edit monitor page in browser', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password',
    ]);
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $page = visit('/login')
        ->type('email', 'test@example.com')
        ->type('password', 'password')
        ->click('Log in')
        ->navigate("/monitors/{$monitor->id}/edit");

    $page->assertSee($monitor->name)
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('can edit monitor through browser', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password',
    ]);
    $monitor = Monitor::factory()->create([
        'user_id' => $user->id,
        'name' => 'Original Name',
    ]);

    $page = visit('/login')
        ->type('email', 'test@example.com')
        ->type('password', 'password')
        ->click('Log in')
        ->navigate("/monitors/{$monitor->id}/edit")
        ->assertNoJavascriptErrors();

    $page->type('name', 'Updated Name')
        ->click('Update Monitor')
        ->assertSee('Monitor updated successfully')
        ->assertPathIs("/monitors/{$monitor->id}");

    expect($monitor->fresh()->name)->toBe('Updated Name');
});

it('can delete monitor through browser', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password',
    ]);
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $page = visit('/login')
        ->type('email', 'test@example.com')
        ->type('password', 'password')
        ->click('Log in')
        ->navigate("/monitors/{$monitor->id}")
        ->assertNoJavascriptErrors();

    $page->click('Delete')
        ->assertSee('Monitor deleted successfully')
        ->assertPathIs('/monitors');

    expect(Monitor::find($monitor->id))->toBeNull();
});

it('cannot access another user monitor in browser', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password',
    ]);
    $otherUser = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $otherUser->id]);

    /** @var \App\Models\User $authenticatedUser */
    $authenticatedUser = $user;

    actingAs($authenticatedUser)
        ->get("/monitors/{$monitor->id}")
        ->assertForbidden();
});
