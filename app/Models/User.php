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
     * A user with no store operates the platform itself rather than a shop,
     * and is deliberately left unscoped.
     */
    public function isPlatformOwner(): bool
    {
        return $this->store_id === null;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role->value, $roles);
    }
}
