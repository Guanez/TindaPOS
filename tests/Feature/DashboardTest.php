<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

it('shows dashboard for authenticated users', function () {
    // Arrange
    $user = User::factory()->cashier()->create();

    // Act & Assert
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('stats')
            ->has('recentSales')
            ->has('lowStockProducts')
        );
});

it('redirects guests to login', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

it('includes today stats in dashboard', function () {
    // Arrange
    $user = User::factory()->cashier()->create();
    Sale::factory()->completed()->today()->for($user)->count(3)->create();
    Product::factory()->lowStock()->count(2)->create();

    // Act & Assert
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('stats.revenue')
            ->has('stats.transactions')
            ->has('stats.low_stock_count')
        );
});
