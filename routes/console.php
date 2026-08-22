<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// An unpaid order that nobody came to the till for should clear itself off
// the queue screen. Needs a cron entry on the server running `schedule:run`.
Schedule::command('orders:expire')->everyFiveMinutes();
