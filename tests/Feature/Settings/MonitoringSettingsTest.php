<?php

use App\Models\Setting;
use App\Models\User;

use function Pest\Laravel\actingAs;

test('authenticated user can view monitoring settings page', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get('/settings/monitoring')
        ->assertSuccessful();
});

test('authenticated user can update monitoring settings', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->patch('/settings/monitoring', [
            'telegram_bot_token' => 'test-bot-token',
            'telegram_chat_id' => 'test-chat-id',
        ])
        ->assertRedirect('/settings/monitoring')
        ->assertSessionHas('success');

    expect(Setting::get('telegram_bot_token'))->toBe('test-bot-token');
    expect(Setting::get('telegram_chat_id'))->toBe('test-chat-id');
});

test('monitoring settings update accepts empty values', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->patch('/settings/monitoring', [
            'telegram_bot_token' => '',
            'telegram_chat_id' => '',
        ])
        ->assertRedirect('/settings/monitoring');

    $token = Setting::get('telegram_bot_token');
    $chatId = Setting::get('telegram_chat_id');
    // Empty strings are stored as null or empty string depending on implementation
    expect($token === null || $token === '')->toBeTrue();
    expect($chatId === null || $chatId === '')->toBeTrue();
});
