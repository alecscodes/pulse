<?php

use App\Models\Monitor;
use App\Models\User;

use function Pest\Laravel\actingAs;

function validWebsitePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Monitor',
        'type' => 'website',
        'url' => 'https://example.com',
        'method' => 'GET',
        'headers' => [],
        'parameters' => [],
        'enable_content_validation' => false,
        'is_active' => true,
        'check_interval' => 60,
    ], $overrides);
}

function validIpPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'IP Monitor',
        'type' => 'ip',
        'url' => '192.168.1.1',
        'method' => 'GET',
        'headers' => [],
        'parameters' => [],
        'enable_content_validation' => false,
        'is_active' => true,
        'check_interval' => 60,
    ], $overrides);
}

test('user can view monitors index', function () {
    $user = User::factory()->create();
    Monitor::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->get(route('monitors.index'))
        ->assertSuccessful();
});

test('user can view create monitor page', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('monitors.create'))
        ->assertSuccessful();
});

test('user can create a monitor', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->post(route('monitors.store'), validWebsitePayload())
        ->assertRedirect();

    expect(Monitor::where('name', 'Test Monitor')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('user can create a monitor with IP address', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->post(route('monitors.store'), validIpPayload())
        ->assertRedirect();

    $monitor = Monitor::where('name', 'IP Monitor')->where('user_id', $user->id)->first();
    expect($monitor)->not->toBeNull()
        ->and($monitor->type)->toBe('ip')
        ->and($monitor->url)->toBe('192.168.1.1');
});

test('user cannot create monitor with invalid data', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->post(route('monitors.store'), [
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
        ->get(route('monitors.show', $monitor))
        ->assertSuccessful();
});

test('user cannot view another user monitor', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $otherUser->id]);

    actingAs($user)
        ->get(route('monitors.show', $monitor))
        ->assertForbidden();
});

test('user can view monitor history', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->get(route('monitors.show', $monitor))
        ->assertSuccessful();
});

test('user can view edit monitor page', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->get(route('monitors.edit', $monitor))
        ->assertSuccessful();
});

test('user cannot edit another user monitor', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $otherUser->id]);

    actingAs($user)
        ->get(route('monitors.edit', $monitor))
        ->assertForbidden();
});

test('user can update their monitor', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create([
        'user_id' => $user->id,
        'name' => 'Original Name',
        'type' => 'website',
        'url' => 'https://example.com',
    ]);

    actingAs($user)
        ->patch(route('monitors.update', $monitor), validWebsitePayload([
            'name' => 'Updated Name',
        ]))
        ->assertRedirect();

    expect($monitor->fresh()->name)->toBe('Updated Name');
});

test('user cannot update another user monitor', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $monitor = Monitor::factory()->create([
        'user_id' => $otherUser->id,
        'type' => 'website',
        'url' => 'https://example.com',
    ]);

    actingAs($user)
        ->patch(route('monitors.update', $monitor), validWebsitePayload([
            'name' => 'Hacked Name',
        ]))
        ->assertForbidden();
});

test('user can delete their monitor', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->delete(route('monitors.destroy', $monitor))
        ->assertRedirect();

    expect(Monitor::find($monitor->id))->toBeNull();
});

test('user cannot delete another user monitor', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $otherUser->id]);

    actingAs($user)
        ->delete(route('monitors.destroy', $monitor))
        ->assertForbidden();

    expect(Monitor::find($monitor->id))->not->toBeNull();
});

test('monitor creation requires authentication', function () {
    $this->post(route('monitors.store'), [
        'name' => 'Test Monitor',
        'type' => 'website',
        'url' => 'https://example.com',
    ])->assertRedirect(route('login'));
});

test('monitor view requires authentication', function () {
    $monitor = Monitor::factory()->create();

    $this->get(route('monitors.show', $monitor))
        ->assertRedirect(route('login'));
});

test('monitor can be created with content validation', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->post(route('monitors.store'), validWebsitePayload([
            'enable_content_validation' => true,
            'expected_title' => 'Example Domain',
            'expected_content' => 'Example',
        ]))
        ->assertRedirect();

    $monitor = Monitor::where('name', 'Test Monitor')->first();
    expect($monitor->enable_content_validation)->toBeTrue()
        ->and($monitor->expected_title)->toBe('Example Domain')
        ->and($monitor->expected_content)->toBe('Example');
});

test('monitor can be created with headers and parameters', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->post(route('monitors.store'), validWebsitePayload([
            'method' => 'POST',
            'headers' => [['key' => 'Authorization', 'value' => 'Bearer token']],
            'parameters' => [['key' => 'param1', 'value' => 'value1']],
        ]))
        ->assertRedirect();

    $monitor = Monitor::where('name', 'Test Monitor')->where('user_id', $user->id)->first();
    expect($monitor)->not->toBeNull()
        ->and($monitor->headers)->toBeArray()
        ->and($monitor->headers)->toHaveKey('Authorization')
        ->and($monitor->parameters)->toBeArray()
        ->and($monitor->parameters)->toHaveKey('param1');
});
