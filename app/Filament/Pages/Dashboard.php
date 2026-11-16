<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Sales\SaleResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Escritorio del panel: ventas del mes, últimas ventas y acceso directo a Nueva venta.
 */
class Dashboard extends BaseDashboard
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('newSale')
                ->label('Nueva venta')
                ->icon(Heroicon::Plus)
                ->url(fn (): string => SaleResource::getUrl('create'))
                ->visible(fn (): bool => SaleResource::canCreate()),
        ];
    }
}
