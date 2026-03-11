<?php

namespace App\Observers;

use App\Models\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class GenericObserver
{
    protected function storeLog($model, $action, $old = null, $new = null)
    {
        try {
            // \Log::error(get_class($model));

            Log::create([
                'user_id' => auth()->id(),
                'loggable_type' => get_class($model),
                'loggable_id' => $model->id,
                'action' => $action,
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => request()->ip(),
                'http_method' => request()->method(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('❌ Error al guardar log: ');
        }
    }


    public function created($model)
    {
        $this->storeLog($model, 'created', null, $model->getAttributes());
    }

    public function updated($model)
    {
        $this->storeLog($model, 'updated', $model->getOriginal(), $model->getDirty());
    }

    public function deleted($model)
    {
        $this->storeLog($model, 'deleted', $model->getOriginal());
    }

    public function restored($model)
    {
        $this->storeLog($model, 'restored', null, $model->getAttributes());
    }

    public function forceDeleted($model)
    {
        $this->storeLog($model, 'forceDeleted', $model->getOriginal());
    }

    // public function saved($model)
    // {
    //     $this->storeLog($model, 'saved', null, $model->getAttributes());
    // }
}
