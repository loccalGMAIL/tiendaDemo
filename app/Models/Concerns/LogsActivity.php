<?php

namespace App\Models\Concerns;

use App\Enums\ActivityEvent;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra altas, cambios y bajas con los valores antes y después.
 * Nunca guarda atributos ocultos (contraseñas, tokens): de esos solo anota
 * que cambiaron.
 *
 * @mixin Model
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function (self $model): void {
            app(ActivityLogger::class)->log(
                ActivityEvent::Created,
                'Alta de '.$model->activitySubjectLabel().': '.$model->activityName(),
                $model,
                ['new' => $model->loggableAttributes($model->getAttributes())],
            );
        });

        static::updated(function (self $model): void {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $previous = $model->getPrevious();
            $loggable = $model->loggableAttributes($changes);
            $properties = [
                'old' => array_intersect_key($model->loggableAttributes($previous), $loggable),
                'new' => $loggable,
            ];

            $sensitive = array_keys(array_diff_key($changes, $loggable));

            if ($sensitive !== []) {
                $properties['sensitive_changed'] = $sensitive;
            }

            app(ActivityLogger::class)->log(
                ActivityEvent::Updated,
                'Cambio en '.$model->activitySubjectLabel().': '.$model->activityName(),
                $model,
                $properties,
            );
        });

        static::deleted(function (self $model): void {
            app(ActivityLogger::class)->log(
                ActivityEvent::Deleted,
                'Baja de '.$model->activitySubjectLabel().': '.$model->activityName(),
                $model,
                ['old' => $model->loggableAttributes($model->getAttributes())],
            );
        });
    }

    protected function activitySubjectLabel(): string
    {
        return strtolower(class_basename($this));
    }

    protected function activityName(): string
    {
        return (string) ($this->getAttribute('name') ?? '#'.$this->getKey());
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function loggableAttributes(array $attributes): array
    {
        $excluded = array_merge($this->getHidden(), ['password', 'remember_token', 'updated_at', 'created_at']);

        return array_diff_key($attributes, array_flip($excluded));
    }
}
