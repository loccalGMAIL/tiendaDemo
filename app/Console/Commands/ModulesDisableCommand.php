<?php

namespace App\Console\Commands;

use App\Enums\Module;
use App\Services\Modules;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('modules:disable {key : Clave del módulo (ver modules:list)}')]
#[Description('Apaga un módulo (oculta la función, no borra datos)')]
class ModulesDisableCommand extends Command
{
    public function handle(Modules $modules): int
    {
        $module = Module::tryFrom((string) $this->argument('key'));

        if ($module === null) {
            $this->error('Clave de módulo inválida. Claves válidas: '.implode(', ', array_map(
                fn (Module $case): string => $case->value,
                Module::cases(),
            )).'.');

            return self::FAILURE;
        }

        try {
            $modules->disable($module);
        } catch (ValidationException $e) {
            $this->error($e->validator->errors()->first());

            return self::FAILURE;
        }

        $this->info("Módulo apagado: {$module->getLabel()}.");

        return self::SUCCESS;
    }
}
