<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Category Management
|--------------------------------------------------------------------------
*/

it('creates a category', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('categories.store'), [
            'name' => 'Beverages',
            'description' => 'Cold drinks and juices',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('categories', ['name' => 'Beverages']);
});

it('rejects duplicate category name', function () {
    $admin = User::factory()->admin()->create();
    Category::factory()->create(['name' => 'Snacks']);

    $this->actingAs($admin)
        ->post(route('categories.store'), ['name' => 'Snacks'])
        ->assertSessionHasErrors('name');
});

it('updates a category', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create(['name' => 'Old Name']);

    $this->actingAs($admin)
        ->put(route('categories.update', $category), ['name' => 'New Name'])
        ->assertRedirect();

    expect($category->fresh()->name)->toBe('New Name');
});

it('deletes an empty category', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();

    $this->actingAs($admin)
        ->delete(route('categories.destroy', $category))
        ->assertRedirect();

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

it('cannot delete category with products', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();
    Product::factory()->for($category)->create();

    $this->actingAs($admin)
        ->delete(route('categories.destroy', $category))
        ->assertRedirect()
        ->assertSessionHasErrors('category');

    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

it('denies cashier from managing categories', function () {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)
        ->post(route('categories.store'), ['name' => 'Test'])
        ->assertStatus(403);
});
