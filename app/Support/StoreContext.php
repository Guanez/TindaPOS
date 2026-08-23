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
        return $this->runAs($storeId, $callback);
    }

    /**
     * Run a callback with no store at all, then restore what was set before.
     *
     * The only way to genuinely create a storeless row: BelongsToStore fills
     * store_id from the current context and cannot tell "not given" from
     * "given as null", so a platform admin has to be made in this state
     * rather than made and then corrected.
     */
    public function runForNone(Closure $callback): mixed
    {
        return $this->runAs(null, $callback);
    }

    private function runAs(?int $storeId, Closure $callback): mixed
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
