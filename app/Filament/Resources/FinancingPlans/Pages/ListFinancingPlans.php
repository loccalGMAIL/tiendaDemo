<?php

namespace App\Filament\Resources\FinancingPlans\Pages;

use App\Filament\Concerns\PersistsTableColumns;
use App\Filament\Resources\FinancingPlans\FinancingPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFinancingPlans extends ListRecords
{
    use PersistsTableColumns;

    protected static string $resource = FinancingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
