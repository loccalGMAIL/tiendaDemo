<?php

namespace App\Facades;

use App\Enums\Module;
use App\Services\Modules as ModulesService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool enabled(Module $module)
 * @method static void enable(Module $module)
 * @method static void disable(Module $module)
 * @method static array{enabled: list<Module>, skipped: array<string, string>} enableAll()
 * @method static array<string, mixed> settings(Module $module)
 * @method static mixed setting(Module $module, string $key, mixed $default = null)
 * @method static void updateSettings(Module $module, array<string, mixed> $settings)
 * @method static void flush()
 *
 * @see ModulesService
 */
class Modules extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ModulesService::class;
    }
}
