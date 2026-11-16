<?php

namespace App\Services;

use App\Enums\Module;
use App\Models\ModuleState;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Interruptores de módulos de esta instalación. El estado se cachea y se
 * limpia en cada cambio. Apagar un módulo oculta la función; nunca borra datos.
 */
class Modules
{
    private const CACHE_KEY = 'modules.states';

    public function enabled(Module $module): bool
    {
        return $this->states()[$module->value]['enabled'] ?? false;
    }

    /**
     * Enciende un módulo. No se enciende sin los módulos que requiere ni
     * con configuración del servidor faltante.
     *
     * @throws ValidationException
     */
    public function enable(Module $module): void
    {
        DB::transaction(function () use ($module): void {
            $states = $this->lockStates([$module, ...$module->requires()]);

            if ($states[$module->value]->enabled) {
                return;
            }

            $missingConfiguration = $module->missingConfiguration();

            if ($missingConfiguration !== null) {
                throw ValidationException::withMessages(['module' => $missingConfiguration]);
            }

            $notEnabled = array_filter(
                $module->requires(),
                fn (Module $required): bool => ! $states[$required->value]->enabled,
            );

            if ($notEnabled !== []) {
                throw ValidationException::withMessages([
                    'module' => "Para habilitar {$module->getLabel()} primero hay que habilitar: {$this->labels($notEnabled)}.",
                ]);
            }

            $states[$module->value]->update(['enabled' => true, 'enabled_at' => now()]);
        });

        $this->flush();
    }

    /**
     * Apaga un módulo. No se apaga si otro módulo encendido depende de él.
     *
     * @throws ValidationException
     */
    public function disable(Module $module): void
    {
        DB::transaction(function () use ($module): void {
            $states = $this->lockStates([$module, ...$module->dependents()]);

            if (! $states[$module->value]->enabled) {
                return;
            }

            $inUse = array_filter(
                $module->dependents(),
                fn (Module $dependent): bool => $states[$dependent->value]->enabled,
            );

            if ($inUse !== []) {
                throw ValidationException::withMessages([
                    'module' => "No se puede apagar {$module->getLabel()}: lo usa {$this->labels($inUse)}.",
                ]);
            }

            $states[$module->value]->update(['enabled' => false, 'enabled_at' => null]);
        });

        $this->flush();
    }

    /**
     * Enciende todo lo que se pueda, en orden de dependencia. Devuelve los
     * módulos encendidos ahora y los omitidos con su motivo.
     *
     * @return array{enabled: list<Module>, skipped: array<string, string>}
     */
    public function enableAll(): array
    {
        $enabled = [];
        $skipped = [];

        foreach (Module::cases() as $module) {
            if ($this->enabled($module)) {
                continue;
            }

            try {
                $this->enable($module);
                $enabled[] = $module;
            } catch (ValidationException $e) {
                $skipped[$module->value] = $e->validator->errors()->first();
            }
        }

        return ['enabled' => $enabled, 'skipped' => $skipped];
    }

    /**
     * Ajustes del módulo: los guardados sobre los valores por defecto.
     *
     * @return array<string, mixed>
     */
    public function settings(Module $module): array
    {
        return array_merge($module->defaultSettings(), $this->states()[$module->value]['settings'] ?? []);
    }

    public function setting(Module $module, string $key, mixed $default = null): mixed
    {
        $settings = $this->settings($module);

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    /**
     * Guarda ajustes del módulo. Solo se aceptan las claves que el módulo declara.
     *
     * @param  array<string, mixed>  $settings
     */
    public function updateSettings(Module $module, array $settings): void
    {
        $known = array_intersect_key($settings, $module->defaultSettings());

        DB::transaction(function () use ($module, $known): void {
            $state = $this->lockStates([$module])[$module->value];

            $state->update(['settings' => array_merge($state->settings ?? [], $known)]);
        });

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array{enabled: bool, settings: array<string, mixed>}>
     */
    private function states(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $states = [];

            foreach (ModuleState::query()->get() as $state) {
                $states[$state->key] = ['enabled' => $state->enabled, 'settings' => $state->settings ?? []];
            }

            return $states;
        });
    }

    /**
     * Crea las filas que falten y bloquea las indicadas, siempre en el mismo orden (por clave).
     *
     * @param  list<Module>  $modules
     * @return Collection<string, ModuleState>
     */
    private function lockStates(array $modules): Collection
    {
        $keys = collect($modules)->map(fn (Module $module): string => $module->value)->unique()->sort()->values();

        foreach ($keys as $key) {
            ModuleState::query()->firstOrCreate(['key' => $key], ['enabled' => false]);
        }

        return ModuleState::query()
            ->whereIn('key', $keys)
            ->orderBy('key')
            ->lockForUpdate()
            ->get()
            ->keyBy('key');
    }

    /**
     * @param  array<int, Module>  $modules
     */
    private function labels(array $modules): string
    {
        return implode(', ', array_map(fn (Module $module): string => $module->getLabel(), $modules));
    }
}
