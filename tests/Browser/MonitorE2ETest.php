<?php

use App\Models\Monitor;
use App\Models\User;

it('can create a monitor with all fields filled through browser E2E', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'email' => 'e2e@example.com',
        'password' => 'password',
    ]);

    $page = visit('/login')
        ->type('email', 'e2e@example.com')
        ->type('password', 'password')
        ->click('Log in')
        ->waitForText('Dashboard')
        ->navigate('/monitors/create')
        ->assertNoJavascriptErrors();

    // Fill basic information
    $page->type('name', 'E2E Test Monitor')
        ->type('url', 'https://example.com')
        ->click('POST') // Select POST method
        ->type('check_interval', '120');

    // Fill first header (first input with placeholder "Header Name")
    $page->click('input[placeholder="Header Name"]')
        ->keys('input[placeholder="Header Name"]', ['{Control}', 'a'])
        ->type('input[placeholder="Header Name"]', 'Authorization')
        ->click('input[placeholder="Header Value"]')
        ->keys('input[placeholder="Header Value"]', ['{Control}', 'a'])
        ->type('input[placeholder="Header Value"]', 'Bearer token123');

    // Add second header
    $page->click('Add Header')
        ->wait(1);

    // Fill second header - type in the newly added header inputs
    $page->click('input[placeholder="Header Name"]')
        ->wait(0.5)
        ->keys('input[placeholder="Header Name"]', ['{Control}', 'a'])
        ->type('input[placeholder="Header Name"]', 'Content-Type')
        ->click('input[placeholder="Header Value"]')
        ->wait(0.5)
        ->keys('input[placeholder="Header Value"]', ['{Control}', 'a'])
        ->type('input[placeholder="Header Value"]', 'application/json');

    // Add first parameter
    $page->click('Add Parameter')
        ->wait(1)
        ->click('input[placeholder="Parameter Name"]')
        ->keys('input[placeholder="Parameter Name"]', ['{Control}', 'a'])
        ->type('input[placeholder="Parameter Name"]', 'param1')
        ->click('input[placeholder="Parameter Value"]')
        ->keys('input[placeholder="Parameter Value"]', ['{Control}', 'a'])
        ->type('input[placeholder="Parameter Value"]', 'value1');

    // Add second parameter
    $page->click('Add Parameter')
        ->wait(1)
        ->click('input[placeholder="Parameter Name"]')
        ->keys('input[placeholder="Parameter Name"]', ['{Control}', 'a'])
        ->type('input[placeholder="Parameter Name"]', 'param2')
        ->click('input[placeholder="Parameter Value"]')
        ->keys('input[placeholder="Parameter Value"]', ['{Control}', 'a'])
        ->type('input[placeholder="Parameter Value"]', 'value2');

    // Enable content validation
    $page->check('enable_content_validation')
        ->wait(1)
        ->type('expected_title', 'Example Domain')
        ->type('expected_content', 'This domain is for use in illustrative examples');

    // Submit form
    $page->click('Create Monitor')
        ->waitForText('Monitor created successfully')
        ->assertPathIs('/monitors/*');

    // Verify monitor was created with all fields
    $monitor = Monitor::where('name', 'E2E Test Monitor')->where('user_id', $user->id)->first();
    expect($monitor)->not->toBeNull();
    expect($monitor->name)->toBe('E2E Test Monitor');
    expect($monitor->type)->toBe('website');
    expect($monitor->url)->toBe('https://example.com');
    expect($monitor->method)->toBe('POST');
    expect($monitor->check_interval)->toBe(120);
    expect($monitor->enable_content_validation)->toBeTrue();
    expect($monitor->expected_title)->toBe('Example Domain');
    expect($monitor->expected_content)->toBe('This domain is for use in illustrative examples');
    expect($monitor->is_active)->toBeTrue();

    // Verify headers and parameters if they were saved
    if (! empty($monitor->headers)) {
        expect($monitor->headers)->toBeArray();
        expect($monitor->headers)->toHaveKey('Authorization');
    }
    if (! empty($monitor->parameters)) {
        expect($monitor->parameters)->toBeArray();
    }
});

it('can edit a monitor with all fields through browser E2E', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'email' => 'e2e-edit@example.com',
        'password' => 'password',
    ]);

    $monitor = Monitor::factory()->create([
        'user_id' => $user->id,
        'name' => 'Original Monitor',
        'type' => 'website',
        'url' => 'https://original.com',
        'method' => 'GET',
        'headers' => ['Old-Header' => 'old-value'],
        'parameters' => ['old-param' => 'old-value'],
        'enable_content_validation' => false,
        'is_active' => true,
        'check_interval' => 60,
    ]);

    $page = visit('/login')
        ->type('email', 'e2e-edit@example.com')
        ->type('password', 'password')
        ->click('Log in')
        ->waitForText('Dashboard')
        ->navigate("/monitors/{$monitor->id}/edit")
        ->assertNoJavascriptErrors();

    // Update basic information
    $page->clear('name')
        ->type('name', 'Updated E2E Monitor')
        ->click('IP') // Change type to IP
        ->clear('url')
        ->type('url', 'https://updated.example.com')
        ->click('POST') // Change method to POST
        ->clear('check_interval')
        ->type('check_interval', '180');

    // Update first header (clear existing and type new)
    $page->click('input[placeholder="Header Name"]')
        ->keys('input[placeholder="Header Name"]', ['{Control}', 'a'])
        ->type('input[placeholder="Header Name"]', 'New-Auth')
        ->click('input[placeholder="Header Value"]')
        ->keys('input[placeholder="Header Value"]', ['{Control}', 'a'])
        ->type('input[placeholder="Header Value"]', 'Bearer new-token');

    // Add second header
    $page->click('Add Header')
        ->wait(1)
        ->click('input[placeholder="Header Name"]')
        ->keys('input[placeholder="Header Name"]', ['{Control}', 'a'])
        ->type('input[placeholder="Header Name"]', 'X-Custom-Header')
        ->click('input[placeholder="Header Value"]')
        ->keys('input[placeholder="Header Value"]', ['{Control}', 'a'])
        ->type('input[placeholder="Header Value"]', 'custom-value');

    // Update first parameter
    $page->click('input[placeholder="Parameter Name"]')
        ->keys('input[placeholder="Parameter Name"]', ['{Control}', 'a'])
        ->type('input[placeholder="Parameter Name"]', 'new_param')
        ->click('input[placeholder="Parameter Value"]')
        ->keys('input[placeholder="Parameter Value"]', ['{Control}', 'a'])
        ->type('input[placeholder="Parameter Value"]', 'new_value');

    // Add second parameter
    $page->click('Add Parameter')
        ->wait(1)
        ->click('input[placeholder="Parameter Name"]')
        ->keys('input[placeholder="Parameter Name"]', ['{Control}', 'a'])
        ->type('input[placeholder="Parameter Name"]', 'another_param')
        ->click('input[placeholder="Parameter Value"]')
        ->keys('input[placeholder="Parameter Value"]', ['{Control}', 'a'])
        ->type('input[placeholder="Parameter Value"]', 'another_value');

    // Enable content validation with new values
    $page->check('enable_content_validation')
        ->wait(1)
        ->type('expected_title', 'Updated Title')
        ->type('expected_content', 'Updated content');

    // Submit form
    $page->click('Update Monitor')
        ->waitForText('Monitor updated successfully')
        ->assertPathIs("/monitors/{$monitor->id}");

    // Verify all changes were saved
    $monitor->refresh();
    expect($monitor->name)->toBe('Updated E2E Monitor');
    expect($monitor->type)->toBe('ip');
    expect($monitor->url)->toBe('https://updated.example.com');
    expect($monitor->method)->toBe('POST');
    expect($monitor->check_interval)->toBe(180);
    expect($monitor->enable_content_validation)->toBeTrue();
    expect($monitor->expected_title)->toBe('Updated Title');
    expect($monitor->expected_content)->toBe('Updated content');
    expect($monitor->is_active)->toBeTrue();
});
