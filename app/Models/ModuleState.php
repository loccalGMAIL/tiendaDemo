<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Estado de un módulo en esta instalación. Una fila ausente equivale a módulo apagado.
 */
#[Table('modules')]
#[Fillable(['key', 'enabled', 'settings', 'enabled_at'])]
class ModuleState extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'settings' => 'array',
            'enabled_at' => 'datetime',
        ];
    }
}
