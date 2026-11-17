<?php

namespace App\Filament\Superadmin\Resources\Users;

use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Filament\Superadmin\Resources\Users\Pages\CreateUser;
use App\Filament\Superadmin\Resources\Users\Pages\EditUser;
use App\Filament\Superadmin\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Usuarios de la instalación vistos desde la plataforma: todos, incluido el SuperAdmin
 * (el panel del cliente nunca lo muestra). Reusa el formulario y la tabla del panel del
 * cliente, con el rol SuperAdmin disponible.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema, forPlatform: true);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table, forPlatform: true);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
