<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    // Eventos automáticos de Eloquent
    public static function bootLogsActivity()
    {
        static::created(function ($model) {
            $model->logActivity('created', null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $model->logActivity('updated', $model->getOriginal(), $model->getChanges());
        });

        static::deleted(function ($model) {
            $model->logActivity('deleted', $model->getOriginal(), null);
        });
    }

    protected function logActivity($event, $old = null, $new = null)
    {
        $user = Auth::user();
        ActivityLog::create([
            'loggable_type' => get_class($this),
            'loggable_id' => $this->getKey(),
            'event' => $event,
            'old_values' => $old ? json_encode($old) : null,
            'new_values' => $new ? json_encode($new) : null,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Sistema',
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
