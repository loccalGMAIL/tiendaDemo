<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::fields());
    }

    /**
     * @return array<int, Component>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),
            TextInput::make('tax_id')
                ->label('CUIT')
                ->maxLength(255),
            TextInput::make('phone')
                ->label('Teléfono')
                ->tel()
                ->maxLength(255),
            TextInput::make('email')
                ->label('Correo electrónico')
                ->email()
                ->maxLength(255),
            TextInput::make('address')
                ->label('Dirección')
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('notes')
                ->label('Notas')
                ->columnSpanFull(),
            Toggle::make('active')
                ->label('Activo')
                ->helperText('Un proveedor inactivo no se puede elegir en una orden o factura nueva.')
                ->default(true),
        ];
    }
}
