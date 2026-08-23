<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property int|null $store_id
 * @property string $name
 * @property string $username
 * @property string|null $email
 * @property string $password
 * @property UserRole $role
 * @property bool $is_active
 */
class User extends Authenticatable
{
    use BelongsToStore, HasFactory, Notifiable;

    protected $fillable = [
        'store_id',
        'name',
        'username',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ───────────────────────────

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function stockLogs(): HasMany
    {
        return $this->hasMany(StockLog::class);
    }

    // ─── Helpers ─────────────────────────────────

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isManager(): bool
    {
        return $this->role->isManager();
    }

    /**
     * Runs the platform rather than any one shop.
     *
     * Deliberately requires BOTH halves. The null store_id is the mechanism
     * that unscopes their queries (StoreScope no-ops on a null context), but
     * on its own it is an absence, not a grant — an owner whose store_id got
     * nulled by a bad migration or a botched delete would otherwise inherit
     * every client's data. The role is the claim that has to be made
     * explicitly; the null store is what that claim then buys.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin && $this->store_id === null;
    }

    /**
     * Carries the super_admin role but is pinned to a store — which should
     * never happen. Treated as powerless by isSuperAdmin() and surfaced here
     * so the platform screens can say so rather than fail silently.
     */
    public function isMisconfiguredSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin && $this->store_id !== null;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role->value, $roles);
    }
}
