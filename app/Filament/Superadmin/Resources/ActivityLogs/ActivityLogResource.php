<?php

namespace App\Filament\Superadmin\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Schemas\ActivityLogInfolist;
use App\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use App\Filament\Superadmin\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Superadmin\Resources\ActivityLogs\Pages\ViewActivityLog;
use App\Models\ActivityLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Registro de actividad completo, visto desde la plataforma: incluye lo hecho por y
 * sobre los SuperAdmin, que el panel del cliente no muestra. Solo lectura.
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'registro de actividad';

    protected static ?string $pluralModelLabel = 'actividad';

    protected static ?string $navigationLabel = 'Actividad';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return ActivityLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivityLogsTable::configure($table, forPlatform: true);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
            'view' => ViewActivityLog::route('/{record}'),
        ];
    }
}
