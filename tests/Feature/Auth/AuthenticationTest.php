<?php

declare(strict_types=1);

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Authentication Tests (adapted for TindaPOS username-based login)
|--------------------------------------------------------------------------
*/

it('shows the login screen', function () {
    $this->get('/login')->assertStatus(200);
});

/*
 * The seeder's password used to be hard-coded into the login template, which
 * meant it shipped in the production bundle whether or not the card rendered.
 * The server decides now, so there is nothing to hide client-side.
 */
it('offers demo credentials in local development', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->get('/login')
        ->assertInertia(fn ($page) => $page->where('demoCredentials', 'owner / owner123'));
});

it('never sends demo credentials outside local', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('/login')
        ->assertInertia(fn ($page) => $page->where('demoCredentials', null));
});

it('authenticates with valid username and password', function () {
    // Arrange
    $user = User::factory()->create(['username' => 'cashier1']);

    // Act
    $response = $this->post('/login', [
        'username' => 'cashier1',
        'password' => 'password',
    ]);

    // Assert
    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

it('rejects invalid credentials', function () {
    // Arrange
    $user = User::factory()->create(['username' => 'cashier1']);

    // Act
    $this->post('/login', [
        'username' => 'cashier1',
        'password' => 'wrong-password',
    ]);

    // Assert
    $this->assertGuest();
});

it('blocks inactive users from logging in', function () {
    // Arrange
    $user = User::factory()->inactive()->create(['username' => 'blocked']);

    // Act
    $response = $this->post('/login', [
        'username' => 'blocked',
        'password' => 'password',
    ]);

    // Assert
    $this->assertGuest();
    $response->assertSessionHasErrors('username');
});

it('signs out a user deactivated mid-session', function () {
    // Arrange — a cashier already signed in and working
    $user = User::factory()->cashier()->create();
    $this->actingAs($user)->get(route('pos.index'))->assertStatus(200);

    // Act — the owner deactivates the account while the shift is running
    $user->update(['is_active' => false]);

    // Assert — the very next request ends the session
    $this->get(route('pos.index'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

it('keeps active users signed in', function () {
    $user = User::factory()->cashier()->create();

    $this->actingAs($user)->get(route('pos.index'))->assertStatus(200);

    $this->assertAuthenticated();
});

it('registration routes are disabled', function () {
    $this->get('/register')->assertStatus(404);
    $this->post('/register', [])->assertStatus(404);
});

it('allows logout', function () {
    // Arrange
    $user = User::factory()->create();

    // Act
    $this->actingAs($user)->post('/logout');

    // Assert
    $this->assertGuest();
});
