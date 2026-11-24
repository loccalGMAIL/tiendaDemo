<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Enums\Module;
use App\Facades\Modules;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Services\Money;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Venta')
                ->columns(3)
                ->schema([
                    TextEntry::make('number')
                        ->label('N°')
                        ->state(fn (Sale $record): string => $record->number()),
                    TextEntry::make('date')
                        ->label('Fecha')
                        ->date('d/m/Y'),
                    TextEntry::make('status')
                        ->label('Estado')
                        ->badge(),
                    TextEntry::make('customer.name')
                        ->label('Cliente')
                        ->placeholder('Consumidor final'),
                    TextEntry::make('seller.name')
                        ->label('Vendedor'),
                    TextEntry::make('channel')
                        ->label('Canal')
                        ->badge(),
                    TextEntry::make('notes')
                        ->label('Notas')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),
            Section::make('Anulación')
                ->columns(3)
                ->visible(fn (Sale $record): bool => $record->isCancelled())
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
                            TextEntry::make('description')->label('Producto'),
                            TextEntry::make('quantity')->label('Cantidad'),
                            TextEntry::make('unit_price')->label('Precio')->money('ARS', locale: 'es_AR'),
                            TextEntry::make('subtotal')->label('Subtotal')->money('ARS', locale: 'es_AR'),
                            // El vendedor no ve costos.
                            TextEntry::make('unit_cost')
                                ->label('Costo unitario')
                                ->money('ARS', locale: 'es_AR')
                                ->visible(fn (): bool => self::isAdmin()),
                        ]),
                ]),
            Section::make('Cobros')
                ->schema([
                    RepeatableEntry::make('payments')
                        ->hiddenLabel()
                        ->columns(6)
                        ->schema([
                            TextEntry::make('number')
                                ->label('N°')
                                ->state(fn (Payment $record): string => $record->number()),
                            TextEntry::make('method.name')->label('Medio de pago'),
                            TextEntry::make('installments')->label('Cuotas'),
                            TextEntry::make('base_amount')->label('Monto base')->money('ARS', locale: 'es_AR'),
                            TextEntry::make('adjustment_amount')->label('Recargo / descuento')->money('ARS', locale: 'es_AR'),
                            TextEntry::make('amount')->label('Cobrado')->money('ARS', locale: 'es_AR'),
                        ]),
                ]),
            Section::make('Cuenta corriente')
                ->columns(4)
                ->visible(fn (Sale $record): bool => Modules::enabled(Module::CurrentAccount) && Money::compare((string) $record->financed_amount, '0') > 0)
                ->schema([
                    TextEntry::make('down_payment')->label('Cobrado en el acto')->money('ARS', locale: 'es_AR'),
                    TextEntry::make('financed_amount')->label('Quedó a cuenta')->money('ARS', locale: 'es_AR'),
                    TextEntry::make('first_due_date')->label('Vencimiento')->date('d/m/Y'),
                    TextEntry::make('pending')
                        ->label('Saldo pendiente')
                        ->state(fn (Sale $record): string => Money::sum(
                            $record->installments()->open()->get()->map(fn (Installment $installment): string => $installment->balance())->all(),
                        ))
                        ->money('ARS', locale: 'es_AR'),
                    // Cuotas propias: el plan con el que se vendió (copiado a la venta).
                    TextEntry::make('financing')
                        ->label('Plan de pago')
                        ->state(fn (Sale $record): string => $record->installments_count.' cuotas'.(Money::compare((string) $record->financing_surcharge, '0') > 0
                            ? ' · recargo '.Money::format((string) $record->financing_surcharge).' ('.rtrim(rtrim(number_format((float) $record->financing_surcharge_percent, 2, ',', ''), '0'), ',').' %)'
                            : ''))
                        ->visible(fn (Sale $record): bool => $record->installments_count !== null)
                        ->columnSpanFull(),
                    RepeatableEntry::make('installments')
                        ->label('Cuotas')
                        ->visible(fn (Sale $record): bool => $record->installments()->count() > 1)
                        ->columns(5)
                        ->columnSpanFull()
                        ->schema([
                            TextEntry::make('number')->label('N°'),
                            TextEntry::make('due_date')->label('Vence')->date('d/m/Y'),
                            TextEntry::make('amount')->label('Monto')->money('ARS', locale: 'es_AR'),
                            TextEntry::make('paid_amount')->label('Pagado')->money('ARS', locale: 'es_AR'),
                            TextEntry::make('status')->label('Estado')->badge(),
                        ]),
                ]),
            Section::make('Importes')
                ->columns(3)
                ->schema([
                    TextEntry::make('subtotal')->label('Subtotal')->money('ARS', locale: 'es_AR'),
                    TextEntry::make('adjustment')->label('Recargos y descuentos')->money('ARS', locale: 'es_AR'),
                    TextEntry::make('total')->label('Total')->money('ARS', locale: 'es_AR'),
                    TextEntry::make('total_cost')
                        ->label('Costo')
                        ->money('ARS', locale: 'es_AR')
                        ->visible(fn (): bool => self::isAdmin()),
                    // La ganancia no incluye el recargo de la tarjeta: se informa aparte.
                    TextEntry::make('profit')
                        ->label('Ganancia')
                        ->state(fn (Sale $record): string => Money::sub($record->subtotal, $record->total_cost))
                        ->money('ARS', locale: 'es_AR')
                        ->visible(fn (): bool => self::isAdmin()),
                ]),
        ]);
    }

    private static function isAdmin(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }
}
