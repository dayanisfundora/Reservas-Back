<?php

namespace App\Traits;

use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait ActivityLog
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getLogAttributes())
            ->logOnlyDirty()
            ->useLogName(class_basename($this))
            ->setDescriptionForEvent(fn(string $eventName) => $this->getSpanishDescriptionForEvent($eventName))
            ->dontSubmitEmptyLogs();
    }

    protected function getSpanishDescriptionForEvent(string $eventName): string
    {
        return match ($eventName) {
            'created' => 'Creado',
            'updated' => 'Editado',
            'deleted' => 'Eliminado',
            default => ucfirst($eventName),
        };
    }

    public function tapActivity(Activity $activity, string $eventName)
    {
        //$causerName = optional($activity->causer)->name ?? 'Sistema';
    
        $activity->properties = $activity->properties->merge([
            'ip' => request()->ip(),
            //'causer_name' => $causerName
        ]);
    }
    
    protected function getLogAttributes(): array
    {
        return $this->logAttributes ?? ['*'];
    }
}
