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
    $user = User::factory()->owner()->create();
    Sale::factory()->completed()->today()->for($user)->count(3)->create();
    Product::factory()->lowStock()->count(2)->create();

    // Act & Assert
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('stats.revenue')
            ->has('stats.transactions')
            ->has('stats.profit')
            ->has('stats.low_stock_count')
        );
});

/*
 * This route carries no role middleware, because a cashier belongs on it. That
 * makes it the one manager-only surface guarded by a condition rather than by
 * the router, and the condition has to be asserted somewhere.
 *
 * Profit is (selling_price - cost_price) summed over the day: publishing it to
 * a cashier hands them the buy price of everything they sold, which is exactly
 * what ProductResource withholds from the same person on the very next screen.
 */
it('withholds cost-derived figures from a cashier', function () {
    // Arrange
    $cashier = User::factory()->cashier()->create();
    Sale::factory()->completed()->today()->for($cashier)->count(2)->create();
    Product::factory()->lowStock()->count(2)->create();

    // Act & Assert
    $this->actingAs($cashier)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('stats.revenue')
            ->has('stats.transactions')
            ->has('stats.discounts')
            ->has('stats.total_products')
            ->missing('stats.profit')
            ->missing('stats.low_stock_count')
        );
});

it('withholds the low stock list from a cashier', function () {
    // Arrange
    $cashier = User::factory()->cashier()->create();
    Product::factory()->lowStock()->count(3)->create();

    // Act & Assert
    $this->actingAs($cashier)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('lowStockProducts', []));
});

it('gives a manager the low stock list', function () {
    // Arrange
    $owner = User::factory()->owner()->create();
    Product::factory()->lowStock()->count(3)->create();

    // Act & Assert
    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('lowStockProducts.data', 3));
});
