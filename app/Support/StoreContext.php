<?php

declare(strict_types=1);

namespace App\Support;

use Closure;

/**
 * Which store the current request, command, or test is acting for.
 *
 * A null id means "no store selected" — the platform owner, an artisan
 * command, or the moment during login before a user is known. Queries are
 * left unscoped in that state, so anything reachable without a store must
 * set one explicitly before touching tenant data.
 */
class StoreContext
{
    private ?int $storeId = null;

    public function set(?int $storeId): void
    {
        $this->storeId = $storeId;
    }

    public function id(): ?int
    {
        return $this->storeId;
    }

    public function isSet(): bool
    {
        return $this->storeId !== null;
    }

    public function forget(): void
    {
        $this->storeId = null;
    }

    /**
     * Run a callback as one store, then restore whatever was set before.
     */
    public function runFor(int $storeId, Closure $callback): mixed
    {
        $previous = $this->storeId;
        $this->storeId = $storeId;

        try {
            return $callback();
        } finally {
            $this->storeId = $previous;
        }
    }
}
