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
