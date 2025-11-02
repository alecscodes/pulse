<?php

use App\Models\Setting;
use App\Models\User;

it('can view registration settings page in browser', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $page = visit('/login')
        ->type('email', 'test@example.com')
        ->type('password', 'password')
        ->click('Log in')
        ->navigate('/settings/registration');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('can enable registration through browser', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $page = visit('/login')
        ->type('email', 'test@example.com')
        ->type('password', 'password')
        ->click('Log in')
        ->navigate('/settings/registration')
        ->assertNoJavascriptErrors();

    $page->check('registration_enabled')
        ->click('Update Settings')
        ->assertSee('Registration settings updated successfully')
        ->assertPathIs('/settings/registration');

    expect(filter_var(Setting::get('registration_enabled'), FILTER_VALIDATE_BOOLEAN))->toBeTrue();
});

it('can disable registration through browser', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password',
    ]);
    Setting::set('registration_enabled', '1');

    $page = visit('/login')
        ->type('email', 'test@example.com')
        ->type('password', 'password')
        ->click('Log in')
        ->navigate('/settings/registration')
        ->assertNoJavascriptErrors();

    $page->uncheck('registration_enabled')
        ->click('Update Settings')
        ->assertSee('Registration settings updated successfully');

    expect(filter_var(Setting::get('registration_enabled'), FILTER_VALIDATE_BOOLEAN))->toBeFalse();
});

it('registration page shows register link when enabled', function () {
    $user = User::factory()->create();
    Setting::set('registration_enabled', '1');

    $page = visit('/login');

    $page->assertSee('Sign up', false)
        ->assertNoJavascriptErrors();
});

it('registration page does not show register link when disabled', function () {
    $user = User::factory()->create();

    $page = visit('/login');

    $page->assertDontSee('Sign up', false)
        ->assertNoJavascriptErrors();
});
