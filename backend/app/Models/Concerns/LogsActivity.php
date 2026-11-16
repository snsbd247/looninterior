<?php

namespace App\Models\Concerns;

use App\Support\ActivityLogger;

/** Records create / update / delete of the model in the activity log. */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn ($m) => ActivityLogger::model('created', $m));
        static::updated(fn ($m) => ActivityLogger::model('updated', $m));
        static::deleted(fn ($m) => ActivityLogger::model('deleted', $m));
    }
}
