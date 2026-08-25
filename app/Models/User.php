<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\LogsActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role_id',
        'receive_notifications',
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
     * Values the activity log must never store. The field NAME is still
     * recorded, so the audit trail shows a phone number was changed without
     * the log becoming a second place phone numbers live.
     *
     * phone is not in $hidden: the Users and Profile screens legitimately
     * display it to authorised staff. It is only redacted from the log.
     *
     * @var list<string>
     */
    protected array $activityRedacted = [
        'phone',
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
            'receive_notifications' => 'boolean',
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

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
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

    /**
     * Can this user actually be notified?
     *
     * Both halves are required: the opt-in AND a phone number to reach them on.
     * The permission is what selects recipients today (in-app), and the phone is
     * what a future SMS or WhatsApp channel will send to — so a user flagged for
     * notifications with no number is a misconfiguration, not a recipient.
     */
    public function canReceiveNotifications(): bool
    {
        return $this->receive_notifications && filled($this->phone);
    }

    /**
     * Everyone who should be alerted. Membership is decided purely by the
     * opt-in — never by role. Do not add "or isAdmin()" here: that would make
     * the permission meaningless and re-hardcode recipients.
     */
    public function scopeNotifiable($query)
    {
        return $query->where('receive_notifications', true)->whereNotNull('phone');
    }
}
