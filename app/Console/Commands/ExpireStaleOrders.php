<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class ExpireStaleOrders extends Command
{
    protected $signature = 'orders:expire {--minutes=20 : How long an unpaid order may sit}';

    protected $description = 'Expire orders that were never paid for at the till';

    public function handle(OrderService $orders): int
    {
        $minutes = (int) $this->option('minutes');
        $expired = $orders->expireStale($minutes);

        $this->info("Expired {$expired} order(s) older than {$minutes} minutes.");

        return self::SUCCESS;
    }
}
