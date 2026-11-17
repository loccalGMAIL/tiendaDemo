<?php

namespace App\Filament\Superadmin\Resources\Users\Pages;

use App\Actions\Users\UpdateUser;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Filament\Superadmin\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(fn (User $record): bool => UsersTable::delete($record)),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof User) {
            throw new LogicException('El registro a editar no es un usuario.');
        }

        return app(UpdateUser::class)->handle($record, $data, withSuperAdmin: true);
    }
}
