<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Correo electrónico')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? 'Dejala vacía para no cambiarla.'
                        : null),
                Select::make('role')
                    ->label('Rol')
                    ->options(Role::assignable())
                    ->default(Role::Seller->value)
                    ->required()
                    ->native(false),
                Toggle::make('active')
                    ->label('Activo')
                    ->helperText('Un usuario inactivo no puede ingresar.')
                    ->default(true),
                Toggle::make('is_default_seller')
                    ->label('Vendedor por defecto')
                    ->helperText('Se propone al cargar una venta. Solo un usuario puede serlo.')
                    ->default(false),
            ]);
    }
}
