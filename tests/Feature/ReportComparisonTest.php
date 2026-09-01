<?php

declare(strict_types=1);

use App\Models\Sale;
use App\Models\User;
use App\Services\ReportService;

/**
 * A number on its own is not a report.
 *
 * ₱48,000 is a good week or a bad one depending entirely on the week before
 * it, so the range report carries the same span of days immediately before,
 * and the change between them.
 */
function saleOn(string $date, float $total, User $user): void
{
    Sale::factory()->completed()->for($user)->create([
        'total' => $total,
        'subtotal' => $total,
        'discount' => 0,
        'created_at' => $date.' 12:00:00',
    ]);
}

it('compares a range against the same number of days before it', function () {
    $user = User::factory()->owner()->create();

    // The week being asked about, and the week before it.
    saleOn('2026-06-15', 1000, $user);
    saleOn('2026-06-08', 500, $user);

    $report = app(ReportService::class)->range('2026-06-15', '2026-06-21');

    expect($report['comparison']['days'])->toBe(7)
        ->and($report['comparison']['start_date'])->toBe('2026-06-08')
        ->and($report['comparison']['end_date'])->toBe('2026-06-14')
        ->and($report['comparison']['previous']['revenue'])->toBe(500.0)
        ->and($report['comparison']['change']['revenue'])->toBe(100.0);
});

it('reads a single day as a single day', function () {
    $user = User::factory()->owner()->create();

    saleOn('2026-06-15', 300, $user);
    saleOn('2026-06-14', 300, $user);

    $report = app(ReportService::class)->range('2026-06-15', '2026-06-15');

    expect($report['comparison']['days'])->toBe(1)
        ->and($report['comparison']['start_date'])->toBe('2026-06-14')
        ->and($report['comparison']['change']['revenue'])->toBe(0.0);
});

/*
 * Going from no sales to some sales is not a percentage. Printing "+100%" for
 * a shop's first week is a number nobody computed from anything.
 */
it('refuses to invent a percentage against an empty period', function () {
    $user = User::factory()->owner()->create();

    saleOn('2026-06-15', 1000, $user);

    $report = app(ReportService::class)->range('2026-06-15', '2026-06-21');

    expect($report['comparison']['previous']['revenue'])->toBe(0.0)
        ->and($report['comparison']['change']['revenue'])->toBeNull();
});

it('shows a fall as a negative change', function () {
    $user = User::factory()->owner()->create();

    saleOn('2026-06-15', 250, $user);
    saleOn('2026-06-08', 1000, $user);

    $report = app(ReportService::class)->range('2026-06-15', '2026-06-21');

    expect($report['comparison']['change']['revenue'])->toBe(-75.0);
});
