<?php

it('redirects panel routes to the installer when not installed', function () {
    config(['app.installed' => false]);

    $this->get('/admin')->assertRedirect(route('installer'));
});

it('does not redirect to the installer when installed', function () {
    config(['app.installed' => true]);

    // A guest hits the auth layer (401) instead of being sent to the installer.
    $response = $this->get('/admin');

    $response->assertUnauthorized();
    expect($response->headers->get('Location'))->not->toBe(route('installer'));
});

it('allows the installer route itself when not installed', function () {
    config(['app.installed' => false]);

    $this->get(route('installer'))->assertOk();
});
