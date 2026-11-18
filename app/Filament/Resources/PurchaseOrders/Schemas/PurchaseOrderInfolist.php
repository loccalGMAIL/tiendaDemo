<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Orden de compra')
                ->columns(3)
                ->schema([
                    TextEntry::make('number')
                        ->label('N°')
                        ->state(fn (PurchaseOrder $record): string => $record->number()),
                    TextEntry::make('date')
                        ->label('Fecha')
                        ->date('d/m/Y'),
                    TextEntry::make('status')
                        ->label('Estado')
                        ->badge(),
                    TextEntry::make('supplier.name')
                        ->label('Proveedor'),
                    TextEntry::make('issued_at')
                        ->label('Emitida el')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('—'),
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
                ->visible(fn (PurchaseOrder $record): bool => $record->isCancelled())
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
                        ->columns(3)
                        ->schema([
                            TextEntry::make('variant')
                                ->label('Producto')
                                ->state(fn (PurchaseOrderItem $record): string => $record->variant->fullName()),
                            TextEntry::make('quantity')->label('Pedido'),
                            TextEntry::make('received_quantity')->label('Recibido'),
                        ]),
                ]),
            Section::make('Factura')
                ->columns(3)
                ->visible(fn (PurchaseOrder $record): bool => $record->purchase()->exists())
                ->schema([
                    TextEntry::make('purchase.invoice_number')->label('Número'),
                    TextEntry::make('purchase.date')->label('Fecha')->date('d/m/Y'),
                    TextEntry::make('purchase.total')->label('Total')->money('ARS', locale: 'es_AR'),
                ]),
        ]);
    }
}
