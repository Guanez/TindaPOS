<?php

declare(strict_types=1);

use App\Models\Sale;
use App\Models\User;

/**
 * The sales history as a CSV.
 *
 * The screen is open to everyone, because a cashier needs to look a receipt
 * up. Pulling the whole history out as a file is a different act, and it sits
 * with the other manager tools.
 */
function csvFrom($response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

it('lets a manager export the sales history', function () {
    $owner = User::factory()->owner()->create();
    Sale::factory()->completed()->for($owner)->count(3)->create();

    $response = $this->actingAs($owner)->get(route('sales.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

it('keeps a cashier out of the export', function () {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)
        ->get(route('sales.export'))
        ->assertForbidden();
});

it('turns nobody away who is not signed in, by sending them to sign in', function () {
    $this->get(route('sales.export'))->assertRedirect(route('login'));
});

it('writes a header row and one line per sale', function () {
    $owner = User::factory()->owner()->create();
    Sale::factory()->completed()->for($owner)->count(2)->create();

    $csv = csvFrom($this->actingAs($owner)->get(route('sales.export')));

    // Header, two sales, and the trailing newline.
    expect(trim($csv))->toContain('Receipt')
        ->and(substr_count(trim($csv), "\n"))->toBe(2);
});

/*
 * An export that quietly ignores the filters on screen is worse than no
 * export: the file looks complete and is not.
 */
it('exports only what the filters on screen would show', function () {
    $owner = User::factory()->owner()->create();
    Sale::factory()->completed()->for($owner)->count(2)->create();
    Sale::factory()->voided()->for($owner)->count(3)->create();

    $csv = csvFrom(
        $this->actingAs($owner)->get(route('sales.export', ['status' => 'voided']))
    );

    expect(substr_count(trim($csv), "\n"))->toBe(3);
});

/*
 * Excel on a Philippine desk reads a UTF-8 file as the system codepage unless
 * it finds a byte order mark, which turns every peso sign into mojibake.
 */
it('starts the file with a byte order mark so Excel reads the peso sign', function () {
    $owner = User::factory()->owner()->create();
    Sale::factory()->completed()->for($owner)->create();

    $csv = csvFrom($this->actingAs($owner)->get(route('sales.export')));

    expect(substr($csv, 0, 3))->toBe("\xEF\xBB\xBF");
});
