<?php

use App\Models\Monitor;
use App\Models\User;

use function Pest\Laravel\actingAs;

test('user can view monitors index', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->get('/monitors')
        ->assertSuccessful();
});

test('user can view create monitor page', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get('/monitors/create')
        ->assertSuccessful();
});

test('user can create a monitor', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->post('/monitors', [
            'name' => 'Test Monitor',
            'type' => 'website',
            'url' => 'https://example.com',
            'method' => 'GET',
            'headers' => [],
            'parameters' => [],
            'enable_content_validation' => false,
            'is_active' => true,
            'check_interval' => 60,
        ])
        ->assertRedirect();

    expect(Monitor::where('name', 'Test Monitor')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('user cannot create monitor with invalid data', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->post('/monitors', [
            'name' => '',
            'type' => 'invalid',
            'url' => 'not-a-url',
        ])
        ->assertSessionHasErrors(['name', 'type', 'url']);
});

test('user can view their own monitor', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->get("/monitors/{$monitor->id}")
        ->assertSuccessful();
});

test('user cannot view another user monitor', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $otherUser->id]);

    actingAs($user)
        ->get("/monitors/{$monitor->id}")
        ->assertForbidden();
});

test('user can view monitor history', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->get("/monitors/{$monitor->id}")
        ->assertSuccessful();
});

test('user can view edit monitor page', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->get("/monitors/{$monitor->id}/edit")
        ->assertSuccessful();
});

test('user cannot edit another user monitor', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $otherUser->id]);

    actingAs($user)
        ->get("/monitors/{$monitor->id}/edit")
        ->assertForbidden();
});

test('user can update their monitor', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create([
        'user_id' => $user->id,
        'name' => 'Original Name',
    ]);

    actingAs($user)
        ->put("/monitors/{$monitor->id}", [
            'name' => 'Updated Name',
            'type' => $monitor->type,
            'url' => $monitor->url,
            'method' => $monitor->method,
            'headers' => [],
            'parameters' => [],
            'enable_content_validation' => false,
            'is_active' => true,
            'check_interval' => 60,
        ])
        ->assertRedirect();

    expect($monitor->fresh()->name)->toBe('Updated Name');
});

test('user cannot update another user monitor', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $otherUser->id]);

    actingAs($user)
        ->put("/monitors/{$monitor->id}", [
            'name' => 'Hacked Name',
            'type' => $monitor->type,
            'url' => $monitor->url,
            'method' => $monitor->method,
            'headers' => [],
            'parameters' => [],
            'enable_content_validation' => false,
            'is_active' => true,
            'check_interval' => 60,
        ])
        ->assertForbidden();
});

test('user can delete their monitor', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->delete("/monitors/{$monitor->id}")
        ->assertRedirect();

    expect(Monitor::find($monitor->id))->toBeNull();
});

test('user cannot delete another user monitor', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $otherUser->id]);

    actingAs($user)
        ->delete("/monitors/{$monitor->id}")
        ->assertForbidden();

    expect(Monitor::find($monitor->id))->not->toBeNull();
});

test('monitor creation requires authentication', function () {
    $this->post('/monitors', [
        'name' => 'Test Monitor',
        'type' => 'website',
        'url' => 'https://example.com',
    ])
        ->assertRedirect('/login');
});

test('monitor view requires authentication', function () {
    $monitor = Monitor::factory()->create();

    $this->get("/monitors/{$monitor->id}")
        ->assertRedirect('/login');
});

test('monitor can be created with content validation', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->post('/monitors', [
            'name' => 'Test Monitor',
            'type' => 'website',
            'url' => 'https://example.com',
            'method' => 'GET',
            'headers' => [],
            'parameters' => [],
            'enable_content_validation' => true,
            'expected_title' => 'Example Domain',
            'expected_content' => 'Example',
            'is_active' => true,
            'check_interval' => 60,
        ])
        ->assertRedirect();

    $monitor = Monitor::where('name', 'Test Monitor')->first();
    expect($monitor->enable_content_validation)->toBeTrue();
    expect($monitor->expected_title)->toBe('Example Domain');
    expect($monitor->expected_content)->toBe('Example');
});

test('monitor can be created with headers and parameters', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->post('/monitors', [
            'name' => 'Test Monitor',
            'type' => 'website',
            'url' => 'https://example.com',
            'method' => 'POST',
            'headers' => [
                ['key' => 'Authorization', 'value' => 'Bearer token'],
            ],
            'parameters' => [
                ['key' => 'param1', 'value' => 'value1'],
            ],
            'enable_content_validation' => false,
            'is_active' => true,
            'check_interval' => 60,
        ])
        ->assertRedirect();

    $monitor = Monitor::where('name', 'Test Monitor')->where('user_id', $user->id)->first();
    expect($monitor)->not->toBeNull();
    expect($monitor->headers)->toBeArray();
    expect($monitor->headers)->toHaveKey('Authorization');
    expect($monitor->parameters)->toBeArray();
    expect($monitor->parameters)->toHaveKey('param1');
});
