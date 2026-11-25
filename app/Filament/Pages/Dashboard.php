<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Sales\SaleResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Escritorio del panel: ventas del mes, últimas ventas y accesos directos a Caja, Nueva venta,
 * Nueva compra y Etiquetas (cada uno solo para quien puede usarlo y con su módulo encendido).
 */
class Dashboard extends BaseDashboard
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('pointOfSale')
                ->label('Caja')
                ->icon(Heroicon::OutlinedCalculator)
                ->url(fn (): string => PointOfSale::getUrl())
                ->visible(fn (): bool => PointOfSale::canAccess()),
            Action::make('newSale')
                ->label('Nueva venta')
                ->icon(Heroicon::Plus)
                ->color('gray')
                ->url(fn (): string => SaleResource::getUrl('create'))
                ->visible(fn (): bool => SaleResource::canCreate()),
            Action::make('newPurchase')
                ->label('Nueva compra')
                ->icon(Heroicon::Plus)
                ->url(fn (): string => PurchaseResource::getUrl('create'))
                ->visible(fn (): bool => PurchaseResource::canAccess() && PurchaseResource::canCreate()),
            Action::make('labels')
                ->label('Etiquetas')
                ->icon(Heroicon::QrCode)
                ->color('gray')
                ->url(fn (): string => BarcodeLabels::getUrl())
                ->visible(fn (): bool => BarcodeLabels::canAccess()),
        ];
    }
}
