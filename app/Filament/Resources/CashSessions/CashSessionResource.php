<?php

namespace App\Filament\Resources\CashSessions;

use App\Enums\Module;
use App\Filament\Concerns\BelongsToModule;
use App\Filament\Resources\CashSessions\Pages\ListCashSessions;
use App\Filament\Resources\CashSessions\Pages\ViewCashSession;
use App\Filament\Resources\CashSessions\Schemas\CashSessionInfolist;
use App\Filament\Resources\CashSessions\Tables\CashSessionsTable;
use App\Models\CashSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Turnos de caja (módulo Control de caja, solo administrador): apertura, movimientos y arqueo
 * de cada turno. Se abren y se cierran desde la Caja; acá solo se cierra uno que quedó abierto.
 */
class CashSessionResource extends Resource
{
    use BelongsToModule;

    protected static ?string $model = CashSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'turno de caja';

    protected static ?string $pluralModelLabel = 'turnos de caja';

    protected static ?string $navigationLabel = 'Turnos de caja';

    protected static string|\UnitEnum|null $navigationGroup = 'Ventas';

    protected static ?int $navigationSort = 3;

    protected static function module(): Module
    {
        return Module::CashControl;
    }

    public static function infolist(Schema $schema): Schema
    {
        return CashSessionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CashSessionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCashSessions::route('/'),
            'view' => ViewCashSession::route('/{record}'),
        ];
    }
}
