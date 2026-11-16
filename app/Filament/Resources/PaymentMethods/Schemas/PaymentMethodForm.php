<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PaymentMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255)
                ->helperText('«Tarjeta 3 cuotas» y «Tarjeta 6 cuotas» son dos medios distintos.'),
            TextInput::make('installments')
                ->label('Cuotas')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(60)
                ->default(1)
                ->required(),
            TextInput::make('adjustment_percent')
                ->label('Recargo o descuento (%)')
                ->numeric()
                ->minValue(-100)
                ->maxValue(1000)
                ->step('0.01')
                ->default(0)
                ->required()
                ->suffix('%')
                ->helperText('Positivo = recargo (por ejemplo 15), negativo = descuento (por ejemplo -10). Se aplica sobre el monto que cubre este medio.'),
            TextInput::make('position')
                ->label('Orden')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->default(0),
            Toggle::make('active')
                ->label('Activo')
                ->default(true),
        ]);
    }
}
