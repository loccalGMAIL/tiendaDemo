<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Customer;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::fields());
    }

    /**
     * Los campos se reutilizan en el alta rápida de cliente desde la venta.
     *
     * @return array<int, Component>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),
            TextInput::make('document_number')
                ->label('Documento')
                ->maxLength(255)
                // Tabla explícita: este formulario también se usa en el alta rápida de la venta.
                ->unique(Customer::class, 'document_number', ignoreRecord: true),
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
                ->maxLength(255),
            TextInput::make('city')
                ->label('Ciudad')
                ->maxLength(255),
            Textarea::make('notes')
                ->label('Notas')
                ->columnSpanFull(),
            Toggle::make('active')
                ->label('Activo')
                ->helperText('Un cliente inactivo no se puede elegir en una venta nueva.')
                ->default(true),
        ];
    }
}
