<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('shares pharmacy permissions for authenticated users with both permissions', function () {
    $user = User::factory()->create();

    Gate::define('inventory.view', fn () => true);
    Gate::define('dispensing.view', fn () => true);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();

    $props = $response->viewData('page')['props'];

    expect($props['auth']['permissions']['pharmacy']['inventory'])->toBeTrue();
    expect($props['auth']['permissions']['pharmacy']['dispensing'])->toBeTrue();
});

test('shares pharmacy permissions for authenticated users with only inventory permission', function () {
    $user = User::factory()->create();

    Gate::define('inventory.view', fn () => true);
    Gate::define('dispensing.view', fn () => false);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();

    $props = $response->viewData('page')['props'];

    expect($props['auth']['permissions']['pharmacy']['inventory'])->toBeTrue();
    expect($props['auth']['permissions']['pharmacy']['dispensing'])->toBeFalse();
});

test('shares pharmacy permissions for authenticated users with only dispensing permission', function () {
    $user = User::factory()->create();

    Gate::define('inventory.view', fn () => false);
    Gate::define('dispensing.view', fn () => true);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();

    $props = $response->viewData('page')['props'];

    expect($props['auth']['permissions']['pharmacy']['inventory'])->toBeFalse();
    expect($props['auth']['permissions']['pharmacy']['dispensing'])->toBeTrue();
});

test('shares pharmacy permissions for authenticated users with no permissions', function () {
    $user = User::factory()->create();

    Gate::define('inventory.view', fn () => false);
    Gate::define('dispensing.view', fn () => false);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();

    $props = $response->viewData('page')['props'];

    expect($props['auth']['permissions']['pharmacy']['inventory'])->toBeFalse();
    expect($props['auth']['permissions']['pharmacy']['dispensing'])->toBeFalse();
});

test('handles guest users without errors', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect(route('login'));

    // Get the redirect response to check the shared props
    $redirectResponse = $this->followingRedirects()->get('/dashboard');

    $props = $redirectResponse->viewData('page')['props'];

    expect($props['auth']['permissions']['pharmacy']['inventory'])->toBeFalse();
    expect($props['auth']['permissions']['pharmacy']['dispensing'])->toBeFalse();
});
