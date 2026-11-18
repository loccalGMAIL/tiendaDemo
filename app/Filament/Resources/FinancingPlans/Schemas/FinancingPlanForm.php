<?php

namespace App\Filament\Resources\FinancingPlans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FinancingPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255)
                ->helperText('Por ejemplo «6 cuotas» o «Plan Navidad». Es lo que se elige al vender.'),
            TextInput::make('installments_count')
                ->label('Cantidad de cuotas')
                ->numeric()
                ->integer()
                ->minValue(2)
                ->maxValue(60)
                ->default(3)
                ->required()
                ->helperText('Cuotas mensuales y consecutivas. La regla de vencimiento la fija la plataforma.'),
            TextInput::make('surcharge_percent')
                ->label('Recargo de financiación (%)')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->step('0.01')
                ->default(0)
                ->required()
                ->suffix('%')
                ->helperText('Se suma a lo que queda a cuenta (después del anticipo) y se reparte en las cuotas. 0 = sin recargo. Una venta que ya se hizo conserva el recargo con el que se vendió.'),
            TextInput::make('position')
                ->label('Orden')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->default(0),
            Toggle::make('active')
                ->label('Activo')
                ->helperText('Un plan inactivo no se puede elegir en una venta nueva.')
                ->default(true),
        ]);
    }
}
