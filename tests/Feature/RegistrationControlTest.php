<?php

use App\Models\Setting;
use App\Models\User;

test('registration is disabled by default', function () {
    $user = User::factory()->create();

    expect(Setting::isRegistrationAllowed())->toBeFalse();
});

test('registration is allowed when no users exist', function () {
    // Ensure no users exist
    User::query()->delete();

    expect(Setting::isRegistrationAllowed())->toBeTrue();
});

test('registration can be enabled by admin', function () {
    $user = User::factory()->create();

    Setting::set('registration_enabled', '1');

    expect(Setting::isRegistrationAllowed())->toBeTrue();
});

test('register route is blocked when registration is disabled', function () {
    $user = User::factory()->create();

    $response = $this->get('/register');
    $response->assertForbidden();
    $response->assertSee('Registration is currently disabled');
});

test('register route is accessible when no users exist', function () {
    // Ensure no users exist
    User::query()->delete();

    $response = $this->get('/register');
    $response->assertSuccessful();
});

test('register route is accessible when registration is enabled', function () {
    $user = User::factory()->create();
    Setting::set('registration_enabled', '1');

    $response = $this->get('/register');
    $response->assertSuccessful();
});

test('registration POST request fails when registration is disabled', function () {
    $user = User::factory()->create();

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $response->assertSessionHasErrors(['email' => 'Registration is currently disabled.']);
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('registration POST request succeeds when no users exist', function () {
    // Ensure no users exist
    User::query()->delete();

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect('/dashboard');
    expect(User::where('email', 'test@example.com')->exists())->toBeTrue();
});

test('registration is automatically disabled after first user registers', function () {
    // Ensure no users exist
    User::query()->delete();

    // Registration should be allowed initially
    expect(Setting::isRegistrationAllowed())->toBeTrue();

    // Register the first user
    $response = $this->post('/register', [
        'name' => 'First User',
        'email' => 'first@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect('/dashboard');
    expect(User::where('email', 'first@example.com')->exists())->toBeTrue();

    // Registration should now be disabled
    expect(Setting::isRegistrationAllowed())->toBeFalse();

    // Verify registration_enabled setting was set to false
    expect(filter_var(Setting::get('registration_enabled'), FILTER_VALIDATE_BOOLEAN))->toBeFalse();
});

test('registration POST request succeeds when registration is enabled', function () {
    $user = User::factory()->create();
    Setting::set('registration_enabled', '1');

    $response = $this->post('/register', [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect('/dashboard');
    expect(User::where('email', 'newuser@example.com')->exists())->toBeTrue();
});

test('authenticated user can access registration settings page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/settings/registration');
    $response->assertSuccessful();
});

test('authenticated user can update registration settings', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->patch('/settings/registration', [
        'registration_enabled' => true,
    ]);

    $response->assertRedirect('/settings/registration');
    $response->assertSessionHas('success');
    expect(filter_var(Setting::get('registration_enabled'), FILTER_VALIDATE_BOOLEAN))->toBeTrue();
});

test('authenticated user can disable registration', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Setting::set('registration_enabled', '1');

    $response = $this->patch('/settings/registration', [
        'registration_enabled' => false,
    ]);

    $response->assertRedirect('/settings/registration');
    expect(filter_var(Setting::get('registration_enabled'), FILTER_VALIDATE_BOOLEAN))->toBeFalse();
});

test('login page shows register link when registration is allowed', function () {
    // No users exist
    User::query()->delete();

    $response = $this->get('/login');
    $response->assertSuccessful();
    $response->assertSee('canRegister', false);
});

test('login page does not show register link when registration is disabled', function () {
    $user = User::factory()->create();

    $response = $this->get('/login');
    $response->assertSuccessful();
    $response->assertDontSee('Sign up', false);
    $response->assertDontSee('Don\'t have an account?', false);
});
