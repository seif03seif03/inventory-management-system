<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relationship: A user belongs to one Role.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function warehouseTransfers()
    {
        return $this->hasMany(WarehouseTransfer::class, 'created_by');
    }

    /**
     * Check if user has a specific role name.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->role && $this->role->name === $roleName;
    }

    /**
     * Convenience helper: Is user an Administrator?
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('Admin');
    }

    /**
     * Convenience helper: Is user a Warehouse Manager?
     */
    public function isManager(): bool
    {
        return $this->hasRole('Warehouse Manager');
    }

    /**
     * Convenience helper: Is user a Warehouse Employee?
     */
    public function isEmployee(): bool
    {
        return $this->hasRole('Warehouse Employee');
    }
}
