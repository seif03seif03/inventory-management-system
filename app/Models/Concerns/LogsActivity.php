<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;

/**
 * Records create / update / delete on the model into activity_logs.
 *
 * This hooks Eloquent's model events rather than the controllers, so a record
 * cannot be changed through some other code path — tinker, a command, a future
 * controller — without the change being logged.
 *
 * Deliberately NOT applied to StockMovement: that table is already the ledger
 * and already explains every quantity change. Logging it here would double the
 * write volume on the hottest table in the system and create two competing
 * audit trails.
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            ActivityLog::record(ActivityLog::ACTION_CREATED, $model);
        });

        static::updated(function ($model) {
            $properties = $model->activityChanges();

            // A save that changed nothing but the timestamp is noise, not an
            // event. Skip it so the log stays readable.
            if ($properties === null) {
                return;
            }

            ActivityLog::record(ActivityLog::ACTION_UPDATED, $model, $properties);
        });

        static::deleted(function ($model) {
            ActivityLog::record(ActivityLog::ACTION_DELETED, $model);
        });
    }

    /**
     * Attributes whose values must never reach the log. Field NAMES are still
     * recorded, so the trail shows that a password or phone number was changed
     * without the log itself becoming a place those values leak from.
     *
     * A model can extend this by declaring $activityRedacted.
     */
    protected function activityRedactedAttributes(): array
    {
        return array_unique(array_merge(
            $this->getHidden(),
            ['password', 'remember_token'],
            property_exists($this, 'activityRedacted') ? $this->activityRedacted : []
        ));
    }

    /**
     * What changed on this update, redacted. Returns null when nothing
     * meaningful changed.
     */
    protected function activityChanges(): ?array
    {
        $redacted   = $this->activityRedactedAttributes();
        $properties = [];

        foreach ($this->getChanges() as $key => $value) {
            if ($key === 'updated_at') {
                continue;
            }

            if (in_array($key, $redacted, true)) {
                $properties[$key] = '[redacted]';
                continue;
            }

            $properties[$key] = is_scalar($value) || $value === null
                ? $value
                : json_encode($value);
        }

        return $properties === [] ? null : $properties;
    }
}
