<?php

namespace App\Filament\Resources\CashRegisters\Pages;

use App\Filament\Concerns\PersistsTableColumns;
use App\Filament\Resources\CashRegisters\CashRegisterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCashRegisters extends ListRecords
{
    use PersistsTableColumns;

    protected static string $resource = CashRegisterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
