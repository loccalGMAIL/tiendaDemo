<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * Ajustes de esta instalación (contenido del sitio y apariencia). Cacheado y
 * resistente a que la tabla todavía no exista (por ejemplo, en medio de un
 * migrate:fresh): en ese caso se usan los valores por defecto.
 */
class Settings
{
    private const CACHE_KEY = 'settings.all';

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        $this->flush();
    }

    public function forget(string $key): void
    {
        Setting::query()->where('key', $key)->delete();

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function all(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()->pluck('value', 'key')->all());
        } catch (QueryException) {
            return [];
        }
    }
}
