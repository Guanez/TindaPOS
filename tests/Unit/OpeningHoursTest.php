<?php

declare(strict_types=1);

use App\Support\OpeningHours;
use Carbon\CarbonImmutable;

/*
|--------------------------------------------------------------------------
| Opening hours
|--------------------------------------------------------------------------
*/

/** A week that is open 07:00–18:00 every day. */
function everyDay(string $open = '07:00', string $close = '18:00'): array
{
    return array_fill_keys(OpeningHours::DAYS, ['open' => $open, 'close' => $close]);
}

function at(string $when): CarbonImmutable
{
    return CarbonImmutable::parse($when, 'Asia/Manila');
}

it('treats a shop with no hours as always open', function () {
    $hours = OpeningHours::from(null);

    expect($hours->alwaysOpen())->toBeTrue()
        ->and($hours->isOpenAt(at('2026-09-01 03:00')))->toBeTrue()
        ->and($hours->nextOpening(at('2026-09-01 03:00')))->toBeNull();
});

it('opens and closes on the hours it was given', function () {
    $hours = OpeningHours::from(everyDay());

    expect($hours->isOpenAt(at('2026-09-01 06:59')))->toBeFalse()
        ->and($hours->isOpenAt(at('2026-09-01 07:00')))->toBeTrue()
        ->and($hours->isOpenAt(at('2026-09-01 17:59')))->toBeTrue()
        // Closing time is closed: an order placed at 18:00:00 sharp is one
        // nobody is there to make.
        ->and($hours->isOpenAt(at('2026-09-01 18:00')))->toBeFalse();
});

it('treats a day that is absent or null as closed', function () {
    $week = everyDay();
    $week['tue'] = null;
    unset($week['wed']);

    $hours = OpeningHours::from($week);

    // 2026-09-01 is a Tuesday.
    expect($hours->isOpenAt(at('2026-09-01 10:00')))->toBeFalse()
        ->and($hours->isOpenAt(at('2026-09-02 10:00')))->toBeFalse()
        ->and($hours->isOpenAt(at('2026-09-03 10:00')))->toBeTrue();
});

it('carries a late window past midnight into the next morning', function () {
    // Open 18:00 until 01:00 the following day.
    $hours = OpeningHours::from(everyDay('18:00', '01:00'));

    expect($hours->isOpenAt(at('2026-09-01 19:00')))->toBeTrue()
        ->and($hours->isOpenAt(at('2026-09-02 00:30')))->toBeTrue()
        ->and($hours->isOpenAt(at('2026-09-02 01:00')))->toBeFalse()
        ->and($hours->isOpenAt(at('2026-09-02 12:00')))->toBeFalse();
});

it('refuses to guess at a half-filled day', function () {
    $hours = OpeningHours::from([
        'tue' => ['open' => '07:00'],
    ]);

    expect($hours->isOpenAt(at('2026-09-01 10:00')))->toBeFalse();
});

it('rejects a time that is not a time', function () {
    $hours = OpeningHours::from([
        'tue' => ['open' => 'ten past six', 'close' => '18:00'],
    ]);

    expect($hours->isOpenAt(at('2026-09-01 10:00')))->toBeFalse();
});

it('rejects an hour or minute out of range', function () {
    $hours = OpeningHours::from([
        'tue' => ['open' => '25:00', 'close' => '18:00'],
    ]);

    expect($hours->isOpenAt(at('2026-09-01 10:00')))->toBeFalse();
});

it('tells a customer when the shop opens again today', function () {
    $hours = OpeningHours::from(everyDay());

    expect($hours->nextOpening(at('2026-09-01 05:00')))
        ->toBe('Opens today at 7:00 AM');
});

it('rolls on to tomorrow once today is over', function () {
    $hours = OpeningHours::from(everyDay());

    expect($hours->nextOpening(at('2026-09-01 20:00')))
        ->toBe('Opens tomorrow at 7:00 AM');
});

it('names the day when it is further off than tomorrow', function () {
    // Open Fridays only. 2026-09-01 is a Tuesday.
    $week = array_fill_keys(OpeningHours::DAYS, null);
    $week['fri'] = ['open' => '09:00', 'close' => '17:00'];

    expect(OpeningHours::from($week)->nextOpening(at('2026-09-01 20:00')))
        ->toBe('Opens Friday at 9:00 AM');
});

it('says nothing when no day is ever open', function () {
    $hours = OpeningHours::from(array_fill_keys(OpeningHours::DAYS, null));

    expect($hours->nextOpening(at('2026-09-01 10:00')))->toBeNull();
});
