<?php

use App\Models\Setting;
use App\Models\User;

it('can view monitoring settings page in browser', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $page = visit('/login')
        ->type('email', 'test@example.com')
        ->type('password', 'password')
        ->click('Log in')
        ->navigate('/settings/monitoring');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('can update monitoring settings through browser', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $page = visit('/login')
        ->type('email', 'test@example.com')
        ->type('password', 'password')
        ->click('Log in')
        ->navigate('/settings/monitoring')
        ->assertNoJavascriptErrors();

    $page->type('telegram_bot_token', 'test-bot-token')
        ->type('telegram_chat_id', 'test-chat-id')
        ->click('Update Settings')
        ->assertSee('Monitoring settings updated successfully')
        ->assertPathIs('/settings/monitoring');

    expect(Setting::get('telegram_bot_token'))->toBe('test-bot-token');
    expect(Setting::get('telegram_chat_id'))->toBe('test-chat-id');
});
