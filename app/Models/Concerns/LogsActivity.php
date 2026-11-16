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
            if (! $model->activityEnabled()) {
                return;
            }

            app(ActivityLogger::class)->log(
                ActivityEvent::Created,
                'Alta de '.$model->activitySubjectLabel().': '.$model->activityName(),
                $model,
                ['new' => $model->loggableAttributes($model->getAttributes())],
            );
        });

        static::updated(function (self $model): void {
            if (! $model->activityEnabled()) {
                return;
            }

            $changes = array_diff_key($model->getChanges(), array_flip(['updated_at', ...$model->activityIgnoredAttributes()]));

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
            if (! $model->activityEnabled()) {
                return;
            }

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

    /**
     * Permite que un modelo omita ciertos registros (p. ej. la variante implícita de un producto).
     */
    protected function activityEnabled(): bool
    {
        return true;
    }

    /**
     * Atributos que cambian por operaciones del sistema y ya tienen su propio historial
     * (p. ej. el stock): sus cambios no se registran en la actividad.
     *
     * @return list<string>
     */
    protected function activityIgnoredAttributes(): array
    {
        return [];
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
        $excluded = array_merge($this->getHidden(), $this->activityIgnoredAttributes(), ['password', 'remember_token', 'updated_at', 'created_at']);

        return array_diff_key($attributes, array_flip($excluded));
    }
}
