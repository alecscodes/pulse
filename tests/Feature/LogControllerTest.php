<?php

use App\Models\Log;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->withoutVite();
});

test('index returns logs page for authenticated user', function () {
    Log::factory()->count(5)->create();

    $response = $this->get('/logs');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Logs/Index')
        ->has('logs.data', 5)
        ->has('categories')
        ->has('levels')
    );
});

test('index filters logs by category', function () {
    Log::factory()->create(['category' => 'monitor']);
    Log::factory()->create(['category' => 'api']);
    Log::factory()->create(['category' => 'monitor']);

    $response = $this->get('/logs?category=monitor');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Logs/Index')
        ->has('logs.data', 2)
        ->where('filters.category', 'monitor')
    );
});

test('index filters logs by level', function () {
    Log::factory()->create(['level' => 'error']);
    Log::factory()->create(['level' => 'info']);
    Log::factory()->create(['level' => 'error']);

    $response = $this->get('/logs?level=error');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Logs/Index')
        ->has('logs.data', 2)
        ->where('filters.level', 'error')
    );
});

test('index filters logs by user', function () {
    $otherUser = User::factory()->create();
    Log::factory()->create(['user_id' => $this->user->id]);
    Log::factory()->create(['user_id' => $otherUser->id]);
    Log::factory()->create(['user_id' => $this->user->id]);

    $response = $this->get('/logs?user_id='.$this->user->id);

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Logs/Index')
        ->has('logs.data', 2)
        ->where('filters.user_id', $this->user->id)
    );
});

test('index filters logs by monitor', function () {
    $monitor = Monitor::factory()->create(['user_id' => $this->user->id]);
    Log::factory()->create(['context' => ['monitor_id' => $monitor->id]]);
    Log::factory()->create(['context' => ['monitor_id' => $monitor->id]]);
    Log::factory()->create(['context' => ['other' => 'data']]);

    $response = $this->get('/logs?monitor_id='.$monitor->id);

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Logs/Index')
        ->has('logs.data', 2)
        ->where('filters.monitor_id', $monitor->id)
    );
});

test('index searches logs by message', function () {
    Log::factory()->create(['message' => 'Test error message']);
    Log::factory()->create(['message' => 'Another message']);
    Log::factory()->create(['message' => 'Test success message']);

    $response = $this->get('/logs?search=Test');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Logs/Index')
        ->has('logs.data', 2)
        ->where('filters.search', 'Test')
    );
});

test('index filters logs by date range', function () {
    Log::factory()->create(['created_at' => now()->subDays(5)]);
    Log::factory()->create(['created_at' => now()->subDays(2)]);
    Log::factory()->create(['created_at' => now()->subDays(10)]);

    $dateFrom = now()->subDays(3)->format('Y-m-d\TH:i');
    $dateTo = now()->format('Y-m-d\TH:i');

    $response = $this->get("/logs?date_from={$dateFrom}&date_to={$dateTo}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Logs/Index')
        ->has('logs.data', 1)
    );
});

test('destroy deletes logs matching filters', function () {
    Log::factory()->create(['category' => 'monitor', 'level' => 'error']);
    Log::factory()->create(['category' => 'monitor', 'level' => 'info']);
    Log::factory()->create(['category' => 'api', 'level' => 'error']);

    $response = $this->delete('/logs', [
        'category' => 'monitor',
        'level' => 'error',
    ]);

    $response->assertRedirect('/logs');
    // Should have 2 remaining logs (1 monitor/info + 1 api/error) + 1 deletion log
    $this->assertDatabaseCount('logs', 3);
    $this->assertDatabaseMissing('logs', [
        'category' => 'monitor',
        'level' => 'error',
        'message' => 'Monitor check failed', // Original log message
    ]);
});

test('destroy deletes all logs when no filters', function () {
    Log::factory()->count(5)->create();

    $response = $this->delete('/logs');

    $response->assertRedirect('/logs');
    // Should have 0 original logs, but 1 deletion log
    $this->assertDatabaseCount('logs', 1);
    $this->assertDatabaseHas('logs', [
        'message' => 'Logs deleted',
    ]);
});
