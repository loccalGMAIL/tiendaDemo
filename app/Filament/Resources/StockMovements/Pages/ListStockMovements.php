<?php

namespace App\Filament\Resources\StockMovements\Pages;

use App\Filament\Concerns\PersistsTableColumns;
use App\Filament\Resources\StockMovements\StockMovementResource;
use Filament\Resources\Pages\ListRecords;

class ListStockMovements extends ListRecords
{
    use PersistsTableColumns;

    protected static string $resource = StockMovementResource::class;
}
