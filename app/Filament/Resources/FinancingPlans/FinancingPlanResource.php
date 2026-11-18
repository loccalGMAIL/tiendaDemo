<?php

namespace App\Filament\Resources\FinancingPlans;

use App\Enums\Module;
use App\Filament\Concerns\BelongsToModule;
use App\Filament\Resources\FinancingPlans\Pages\CreateFinancingPlan;
use App\Filament\Resources\FinancingPlans\Pages\EditFinancingPlan;
use App\Filament\Resources\FinancingPlans\Pages\ListFinancingPlans;
use App\Filament\Resources\FinancingPlans\Schemas\FinancingPlanForm;
use App\Filament\Resources\FinancingPlans\Tables\FinancingPlansTable;
use App\Models\FinancingPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Planes de financiación (módulo Cuotas propias, solo administrador): en cuántas cuotas se
 * divide el saldo de una venta y con qué recargo.
 */
class FinancingPlanResource extends Resource
{
    use BelongsToModule;

    protected static ?string $model = FinancingPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'plan de financiación';

    protected static ?string $pluralModelLabel = 'planes de financiación';

    protected static ?string $navigationLabel = 'Planes de financiación';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function module(): Module
    {
        return Module::Installments;
    }

    public static function form(Schema $schema): Schema
    {
        return FinancingPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FinancingPlansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFinancingPlans::route('/'),
            'create' => CreateFinancingPlan::route('/create'),
            'edit' => EditFinancingPlan::route('/{record}/edit'),
        ];
    }
}
