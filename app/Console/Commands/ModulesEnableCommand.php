<?php

namespace App\Console\Commands;

use App\Enums\Module;
use App\Services\Modules;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('modules:enable {key? : Clave del módulo (ver modules:list)} {--all : Enciende todos los que se puedan}')]
#[Description('Enciende un módulo, o todos con --all')]
class ModulesEnableCommand extends Command
{
    public function handle(Modules $modules): int
    {
        if ($this->option('all')) {
            return $this->enableAll($modules);
        }

        $module = Module::tryFrom((string) $this->argument('key'));

        if ($module === null) {
            $this->error('Indicá la clave de un módulo o usá --all. Claves válidas: '.$this->validKeys().'.');

            return self::FAILURE;
        }

        try {
            $modules->enable($module);
        } catch (ValidationException $e) {
            $this->error($e->validator->errors()->first());

            return self::FAILURE;
        }

        $this->info("Módulo encendido: {$module->getLabel()}.");

        return self::SUCCESS;
    }

    private function enableAll(Modules $modules): int
    {
        $result = $modules->enableAll();

        foreach ($result['enabled'] as $module) {
            $this->info("Encendido: {$module->getLabel()}.");
        }

        foreach ($result['skipped'] as $key => $reason) {
            $label = Module::from($key)->getLabel();
            $this->warn("Omitido: {$label}. {$reason}");
        }

        if ($result['enabled'] === [] && $result['skipped'] === []) {
            $this->info('Todos los módulos ya estaban encendidos.');
        }

        return self::SUCCESS;
    }

    private function validKeys(): string
    {
        return implode(', ', array_map(fn (Module $module): string => $module->value, Module::cases()));
    }
}
