<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A person who signs in: an advisor, or the read-only admin.
 */
class Advisor extends Authenticatable
{
    use HasUuids;

    protected $fillable = ['username', 'display_name', 'role', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }
}
