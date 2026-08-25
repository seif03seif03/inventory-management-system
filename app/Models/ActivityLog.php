<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'properties',
        'ip_address',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';

    /**
     * Every action this log records, for the filter dropdown.
     */
    public const ACTIONS = [
        self::ACTION_CREATED,
        self::ACTION_UPDATED,
        self::ACTION_DELETED,
    ];

    /**
     * Who performed the action. Null when the actor's account was later
     * deleted, or when the change came from a seeder or artisan command.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The record acted upon. Resolves through the morph map registered in
     * AppServiceProvider. Returns null once the subject has been deleted,
     * which is exactly why subject_label is stored alongside it.
     */
    public function subject()
    {
        return $this->morphTo();
    }

    /**
     * Write one entry. Called from the LogsActivity trait, never directly from
     * a controller — putting it in the model events means a record cannot be
     * changed through some other code path without being logged.
     */
    public static function record(string $action, Model $subject, ?array $properties = null): self
    {
        return self::create([
            'user_id'       => self::resolveActorId($action, $subject),
            'action'        => $action,
            'subject_type'  => $subject->getMorphClass(),
            'subject_id'    => $subject->getKey(),
            'subject_label' => self::labelFor($subject),
            'properties'    => $properties,
            // Nullable: there is no request during a seeder or scheduled task.
            'ip_address'    => request()?->ip(),
        ]);
    }

    /**
     * The acting user's id, or null when it cannot be referenced.
     *
     * user_id carries a foreign key, and the `deleted` model event fires AFTER
     * the row is gone. Deleting a user account therefore tries to insert a log
     * row pointing at a user that no longer exists, and the constraint rejects
     * it — taking the whole delete down with it.
     *
     * The check is scoped to user deletions so the common create/update path
     * stays a single insert with no extra query.
     */
    private static function resolveActorId(string $action, Model $subject): ?int
    {
        $actorId = Auth::id();

        if ($actorId === null || $action !== self::ACTION_DELETED || ! $subject instanceof User) {
            return $actorId;
        }

        return User::whereKey($actorId)->exists() ? $actorId : null;
    }

    /**
     * How a record identifies itself to a human. Master records have a name,
     * documents have a reference number, users have an email; anything else
     * falls back to its id.
     */
    private static function labelFor(Model $subject): string
    {
        foreach (['name', 'reference_number', 'email'] as $attribute) {
            $value = $subject->getAttribute($attribute);

            if (! empty($value)) {
                return (string) $value;
            }
        }

        return '#' . $subject->getKey();
    }

    /**
     * 'product' -> 'Product', 'warehouse_transfer' -> 'Warehouse Transfer'.
     * Used for display and for the subject filter dropdown.
     */
    public function subjectName(): string
    {
        return ucwords(str_replace('_', ' ', $this->subject_type));
    }
}
