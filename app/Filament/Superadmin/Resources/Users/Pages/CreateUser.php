<?php

namespace App\Filament\Superadmin\Resources\Users\Pages;

use App\Actions\Users\CreateUser as CreateUserAction;
use App\Filament\Superadmin\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateUserAction::class)->handle($data, withSuperAdmin: true);
    }
}
