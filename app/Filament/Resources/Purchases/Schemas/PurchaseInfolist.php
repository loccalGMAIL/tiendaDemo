<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Factura de compra')
                ->columns(3)
                ->schema([
                    TextEntry::make('invoice_number')
                        ->label('Número'),
                    TextEntry::make('date')
                        ->label('Fecha')
                        ->date('d/m/Y'),
                    TextEntry::make('state')
                        ->label('Estado')
                        ->badge()
                        ->state(fn (Purchase $record): string => $record->isCancelled() ? 'Anulada' : 'Vigente')
                        ->color(fn (Purchase $record): string => $record->isCancelled() ? 'danger' : 'success'),
                    TextEntry::make('supplier.name')
                        ->label('Proveedor'),
                    TextEntry::make('order')
                        ->label('Orden de compra')
                        ->state(fn (Purchase $record): ?string => $record->order?->number())
                        ->placeholder('Factura directa'),
                    TextEntry::make('user.name')
                        ->label('Cargada por')
                        ->placeholder('—'),
                    TextEntry::make('notes')
                        ->label('Notas')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),
            Section::make('Anulación')
                ->columns(3)
                ->visible(fn (Purchase $record): bool => $record->isCancelled())
                ->schema([
                    TextEntry::make('cancelled_at')
                        ->label('Anulada el')
                        ->dateTime('d/m/Y H:i'),
                    TextEntry::make('cancelledBy.name')
                        ->label('Anulada por'),
                    TextEntry::make('cancellation_reason')
                        ->label('Motivo'),
                ]),
            Section::make('Productos')
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->columns(5)
                        ->schema([
                            TextEntry::make('variant')
                                ->label('Producto')
                                ->state(fn (PurchaseItem $record): string => $record->variant->fullName()),
                            TextEntry::make('ordered_quantity')
                                ->label('Pedido')
                                ->placeholder('—'),
                            TextEntry::make('quantity')->label('Llegó'),
                            TextEntry::make('unit_cost')->label('Costo unitario')->money('ARS', locale: 'es_AR'),
                            TextEntry::make('subtotal')->label('Subtotal')->money('ARS', locale: 'es_AR'),
                        ]),
                ]),
            Section::make('Importe')
                ->schema([
                    TextEntry::make('total')
                        ->label('Total')
                        ->money('ARS', locale: 'es_AR'),
                ]),
        ]);
    }
}
