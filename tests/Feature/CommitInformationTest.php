<?php

test('commit is shared with inertia pages as a short hash', function () {
    config(['app.commit' => 'abcdef1234567890']);

    $response = $this->get(route('home'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->where('commit', 'abcdef1'));
});

test('commit is null on inertia pages when app commit is not configured', function () {
    config(['app.commit' => null]);

    $response = $this->get(route('home'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->where('commit', null));
});
