<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Concerns\PersistsTableColumns;
use App\Filament\Resources\Sales\SaleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSales extends ListRecords
{
    use PersistsTableColumns;

    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva venta'),
        ];
    }
}
