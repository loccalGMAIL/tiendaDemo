<?php

namespace App\Filament\Concerns;

use App\Models\TableColumnPreference;

/**
 * Guarda las columnas visibles de una tabla en la base, por usuario y por
 * tabla, en lugar de la sesión de Filament. Se usa en las páginas List*.
 */
trait PersistsTableColumns
{
    protected function loadTableColumnsFromSession(): array
    {
        $saved = TableColumnPreference::query()
            ->where('user_id', auth()->id())
            ->where('table', static::class)
            ->value('columns');

        return is_array($saved) ? $saved : $this->getDefaultTableColumnState();
    }

    protected function persistTableColumns(): void
    {
        $userId = auth()->id();

        if ($userId === null) {
            return;
        }

        $preference = TableColumnPreference::query()
            ->where('user_id', $userId)
            ->where('table', static::class)
            ->first();

        // No se escribe nada mientras la tabla esté como viene por defecto
        // y no haya una elección previa.
        if ($preference === null && $this->tableColumns == $this->getDefaultTableColumnState()) {
            return;
        }

        if ($preference?->columns == $this->tableColumns) {
            return;
        }

        TableColumnPreference::query()->updateOrCreate(
            ['user_id' => $userId, 'table' => static::class],
            ['columns' => $this->tableColumns],
        );
    }
}
