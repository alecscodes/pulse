<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

test('authenticated user can check for updates', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get('/settings/updates/check')
        ->assertSuccessful()
        ->assertJsonStructure([
            'available',
            'current_commit',
            'remote_commit',
            'commits_behind',
            'error',
        ]);
});

test('authenticated user can attempt to update', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->post('/settings/updates/update')
        ->assertSuccessful()
        ->assertJsonStructure([
            'success',
            'message',
            'output',
            'error',
        ]);
});

test('guest cannot check for updates', function () {
    $this->get('/settings/updates/check')
        ->assertRedirect('/login');
});

test('guest cannot perform update', function () {
    $this->post('/settings/updates/update')
        ->assertRedirect('/login');
});
