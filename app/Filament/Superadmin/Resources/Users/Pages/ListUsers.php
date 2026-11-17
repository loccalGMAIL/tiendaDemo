<?php

namespace App\Filament\Superadmin\Resources\Users\Pages;

use App\Filament\Resources\Users\Pages\ListUsers as ClientListUsers;
use App\Filament\Superadmin\Resources\Users\UserResource;

class ListUsers extends ClientListUsers
{
    protected static string $resource = UserResource::class;
}
