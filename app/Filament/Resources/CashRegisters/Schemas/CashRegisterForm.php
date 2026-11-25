<?php

namespace App\Filament\Resources\CashRegisters\Schemas;

use App\Services\SaleTicket;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CashRegisterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true)
                ->helperText('Por ejemplo «Caja 1» o «Mostrador».'),
            Select::make('paper_width')
                ->label('Papel del ticket en esta caja')
                ->options(SaleTicket::PAPER_WIDTHS)
                ->placeholder('El de Configuración → Ticket')
                ->native(false)
                ->helperText('Si la impresora de esta caja usa otro rollo.'),
            TextInput::make('position')
                ->label('Orden')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->default(0),
            Toggle::make('active')
                ->label('Activa')
                ->default(true)
                ->helperText('Una caja desactivada no se puede abrir; sus turnos quedan en el historial.'),
        ]);
    }
}
