<?php

namespace App\Filament\Resources\CashSessions\Pages;

use App\Filament\Concerns\PersistsTableColumns;
use App\Filament\Resources\CashSessions\CashSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListCashSessions extends ListRecords
{
    use PersistsTableColumns;

    protected static string $resource = CashSessionResource::class;
}
