<?php

namespace App\Console\Commands;

use App\Enums\Module;
use App\Facades\Modules;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('modules:list')]
#[Description('Lista los módulos y si están encendidos en esta instalación')]
class ModulesListCommand extends Command
{
    public function handle(): int
    {
        $this->table(
            ['Clave', 'Módulo', 'Estado', 'Requiere', 'Aviso'],
            array_map(fn (Module $module): array => [
                $module->value,
                $module->getLabel(),
                Modules::enabled($module) ? 'Encendido' : 'Apagado',
                implode(', ', array_map(fn (Module $required): string => $required->value, $module->requires())) ?: '—',
                $module->missingConfiguration() ?? '',
            ], Module::cases()),
        );

        return self::SUCCESS;
    }
}
