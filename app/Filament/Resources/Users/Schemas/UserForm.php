<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    /**
     * @param  bool  $forPlatform  Formulario del panel de la plataforma: ofrece también el rol SuperAdmin.
     */
    public static function configure(Schema $schema, bool $forPlatform = false): Schema
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
                    ->options(Role::assignable($forPlatform))
                    ->default(Role::Seller->value)
                    ->required()
                    // En la plataforma nadie se quita a sí mismo el acceso (podría quedar sin SuperAdmin):
                    // el rol y el estado de la propia cuenta no se editan. Un campo deshabilitado no se envía.
                    ->disabled(fn (?User $record): bool => $forPlatform && ($record?->is(auth()->user()) ?? false))
                    ->native(false),
                Toggle::make('active')
                    ->label('Activo')
                    ->helperText('Un usuario inactivo no puede ingresar.')
                    ->disabled(fn (?User $record): bool => $forPlatform && ($record?->is(auth()->user()) ?? false))
                    ->default(true),
                Toggle::make('is_default_seller')
                    ->label('Vendedor por defecto')
                    ->helperText('Se propone al cargar una venta. Solo un usuario puede serlo.')
                    ->default(false),
            ]);
    }
}
