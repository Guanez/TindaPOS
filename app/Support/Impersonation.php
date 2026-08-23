<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Store;
use Illuminate\Contracts\Session\Session;

/**
 * Which client store the platform admin is currently standing inside.
 *
 * Kept in the session rather than on the user row so that stepping into a
 * shop is per-browser and evaporates on logout — support work should never
 * be a state you forget you are in on another device.
 *
 * Only ever consulted for a super admin. ResolveStoreContext checks that
 * before asking, and enter() is reached through the `platform` middleware,
 * so an ordinary user cannot plant the key and ride it into another store.
 */
class Impersonation
{
    private const KEY = 'platform.acting_store_id';

    public function __construct(
        private readonly Session $session
    ) {}

    public function enter(Store $store): void
    {
        $this->session->put(self::KEY, $store->id);
    }

    public function leave(): void
    {
        $this->session->forget(self::KEY);
    }

    public function storeId(): ?int
    {
        $id = $this->session->get(self::KEY);

        return is_int($id) ? $id : null;
    }

    public function isActive(): bool
    {
        return $this->storeId() !== null;
    }

    /**
     * The store being stood inside, or null. A suspended or deleted store
     * resolves to null so that support access ends the moment the store does.
     */
    public function store(): ?Store
    {
        $id = $this->storeId();

        return $id === null ? null : Store::query()->find($id);
    }
}
