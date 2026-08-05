<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    protected $fillable = [
        'department_id',
        'full_name',
        'email',
        'password_hash',
        'role_id',
        'status',
        'photo_path',
    ];

    protected $hidden = [
        'password_hash',
    ];

    public $timestamps = true;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    public function getAuthPassword(): string
    {
        // Legacy seeds use $2b$ (PHP password_hash); Laravel bcrypt expects $2y$.
        return str_replace('$2b$', '$2y$', $this->password_hash);
    }

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password_hash'] = Hash::make($value);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasRole(string ...$codes): bool
    {
        $code = $this->role?->code;

        return $code !== null && in_array($code, $codes, true);
    }

    /** @return list<string> */
    public function permissionCodes(): array
    {
        if (! $this->relationLoaded('role')) {
            $this->load('role.permissions');
        } elseif ($this->role && ! $this->role->relationLoaded('permissions')) {
            $this->role->load('permissions');
        }

        return $this->role?->permissions?->pluck('code')->all() ?? [];
    }

    public function canPermission(string $code): bool
    {
        return in_array($code, $this->permissionCodes(), true);
    }
}
