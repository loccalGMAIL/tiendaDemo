<?php

namespace App\Filament\Superadmin\Resources\ActivityLogs\Pages;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs as ClientListActivityLogs;
use App\Filament\Superadmin\Resources\ActivityLogs\ActivityLogResource;

class ListActivityLogs extends ClientListActivityLogs
{
    protected static string $resource = ActivityLogResource::class;
}
