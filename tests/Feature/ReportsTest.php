<?php

declare(strict_types=1);

use App\Models\Sale;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Reports — Access Control
|--------------------------------------------------------------------------
*/

it('allows admin to access reports', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('reports.index'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Reports/Index'));
});

it('denies cashier access to reports', function () {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)
        ->get(route('reports.index'))
        ->assertStatus(403);
});

/*
|--------------------------------------------------------------------------
| Reports — Daily
|--------------------------------------------------------------------------
*/

it('returns daily report data', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('reports.daily', ['date' => today()->toDateString()]))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->has('dailyReport.hourly', 24)
        );
});

it('buckets daily revenue into the hour the sale happened', function () {
    $admin = User::factory()->admin()->create();

    Sale::factory()->completed()->for($admin)->create([
        'total' => 250,
        'created_at' => today()->setTime(14, 30),
    ]);

    $this->actingAs($admin)
        ->get(route('reports.daily', ['date' => today()->toDateString()]))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->where('dailyReport.transactions', 1)
            ->where('dailyReport.hourly.14.transactions', 1)
            ->where('dailyReport.hourly.14.revenue', 250)
            ->where('dailyReport.hourly.13.transactions', 0)
        );
});

it('validates daily report date', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('reports.daily', ['date' => 'invalid']))
        ->assertSessionHasErrors('date');
});

it('rejects future date for daily report', function () {
    $admin = User::factory()->admin()->create();
    $future = now()->addDays(5)->toDateString();

    $this->actingAs($admin)
        ->get(route('reports.daily', ['date' => $future]))
        ->assertSessionHasErrors('date');
});

/*
|--------------------------------------------------------------------------
| Reports — Range
|--------------------------------------------------------------------------
*/

it('returns range report data', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('reports.range', [
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => today()->toDateString(),
        ]))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->has('rangeReport')
            ->where('activeTab', 'range')
        );
});

it('validates end_date after start_date', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('reports.range', [
            'start_date' => today()->toDateString(),
            'end_date' => now()->subDays(3)->toDateString(),
        ]))
        ->assertSessionHasErrors('end_date');
});

/*
|--------------------------------------------------------------------------
| Reports — Top Products
|--------------------------------------------------------------------------
*/

it('returns top products data', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('reports.topProducts', [
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => today()->toDateString(),
        ]))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->has('topProducts')
        );
});
