<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * A shop's week, and the one question worth asking it: are you open?
 *
 * Kept out of the Store model because the arithmetic — midnight crossings,
 * "closed today, open tomorrow", the wording a customer reads — is a good
 * deal more than an accessor, and all of it is worth testing without a
 * database behind it.
 *
 * The stored shape is one entry per day, keyed by lowercase three-letter day:
 *
 *   { "mon": { "open": "07:00", "close": "18:00" }, "tue": null, … }
 *
 * A null day is a closing day. A missing day is also closed, so a partial
 * record cannot accidentally read as open.
 */
final class OpeningHours
{
    /** @var list<string> */
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /** @var array<string, string> */
    private const NAMES = [
        'mon' => 'Monday',
        'tue' => 'Tuesday',
        'wed' => 'Wednesday',
        'thu' => 'Thursday',
        'fri' => 'Friday',
        'sat' => 'Saturday',
        'sun' => 'Sunday',
    ];

    /**
     * @param  array<string, array{open: string, close: string}|null>|null  $week
     */
    private function __construct(
        private readonly ?array $week
    ) {}

    /**
     * @param  array<string, mixed>|null  $week
     */
    public static function from(?array $week): self
    {
        if ($week === null || $week === []) {
            return new self(null);
        }

        $clean = [];

        foreach (self::DAYS as $day) {
            $entry = $week[$day] ?? null;

            if (! is_array($entry)) {
                $clean[$day] = null;

                continue;
            }

            $open = self::normalise($entry['open'] ?? null);
            $close = self::normalise($entry['close'] ?? null);

            // A half-filled day is not a guess worth making. Treated as closed
            // rather than as open-until-whenever.
            $clean[$day] = ($open === null || $close === null)
                ? null
                : ['open' => $open, 'close' => $close];
        }

        return new self($clean);
    }

    /**
     * A shop that has never set hours keeps working exactly as it did.
     */
    public function alwaysOpen(): bool
    {
        return $this->week === null;
    }

    public function isOpenAt(DateTimeInterface $moment): bool
    {
        if ($this->week === null) {
            return true;
        }

        $now = CarbonImmutable::instance($moment);

        // Today's window, and yesterday's in case it runs past midnight — a
        // bar closing at 01:00 is still open at half past twelve, and that
        // window belongs to the previous day's entry.
        return $this->windowCovers($now, $now)
            || $this->windowCovers($now->subDay(), $now);
    }

    /**
     * What to tell a customer who has arrived outside opening hours.
     *
     * Deliberately a sentence rather than a data structure: there is exactly
     * one place this is shown, and "Opens Monday at 7:00 AM" is more useful
     * than a payload the page has to reassemble.
     */
    public function nextOpening(DateTimeInterface $moment): ?string
    {
        if ($this->week === null) {
            return null;
        }

        $now = CarbonImmutable::instance($moment);

        // Today included: a shop shut at 6am opens again this morning.
        for ($ahead = 0; $ahead <= 7; $ahead++) {
            $day = $now->addDays($ahead);
            $entry = $this->week[$this->key($day)] ?? null;

            if ($entry === null) {
                continue;
            }

            $opensAt = $this->at($day, $entry['open']);

            if ($opensAt->lessThanOrEqualTo($now)) {
                continue;
            }

            $time = $opensAt->format('g:i A');

            if ($ahead === 0) {
                return "Opens today at {$time}";
            }

            if ($ahead === 1) {
                return "Opens tomorrow at {$time}";
            }

            return 'Opens '.self::NAMES[$this->key($day)]." at {$time}";
        }

        // Every day is a closing day. Saying "opens never" helps nobody.
        return null;
    }

    /**
     * The week as stored, for the settings form to edit.
     *
     * @return array<string, array{open: string, close: string}|null>|null
     */
    public function toArray(): ?array
    {
        return $this->week;
    }

    /**
     * Does the window belonging to $day contain $moment?
     */
    private function windowCovers(CarbonImmutable $day, CarbonImmutable $moment): bool
    {
        $entry = $this->week[$this->key($day)] ?? null;

        if ($entry === null) {
            return false;
        }

        $opens = $this->at($day, $entry['open']);
        $closes = $this->at($day, $entry['close']);

        // Closing at or before opening means the shop trades past midnight.
        if ($closes->lessThanOrEqualTo($opens)) {
            $closes = $closes->addDay();
        }

        return $moment->greaterThanOrEqualTo($opens) && $moment->lessThan($closes);
    }

    private function at(CarbonImmutable $day, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $day->setTime($hour, $minute);
    }

    private function key(CarbonImmutable $day): string
    {
        return self::DAYS[$day->dayOfWeekIso - 1];
    }

    /**
     * Accepts "7:00", "07:00" and "07:00:00"; rejects anything else.
     */
    private static function normalise(mixed $time): ?string
    {
        if (! is_string($time) || preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', $time, $m) !== 1) {
            return null;
        }

        $hour = (int) $m[1];
        $minute = (int) $m[2];

        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }
}
