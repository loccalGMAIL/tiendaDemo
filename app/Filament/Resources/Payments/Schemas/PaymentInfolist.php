<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Models\Payment;
use App\Services\Money;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Cobro')
                ->columns(3)
                ->schema([
                    TextEntry::make('number')
                        ->label('N°')
                        ->state(fn (Payment $record): string => $record->number()),
                    TextEntry::make('date')
                        ->label('Fecha')
                        ->date('d/m/Y'),
                    TextEntry::make('state')
                        ->label('Estado')
                        ->badge()
                        ->state(fn (Payment $record): string => $record->isCancelled() ? 'Anulado' : 'Vigente')
                        ->color(fn (Payment $record): string => $record->isCancelled() ? 'danger' : 'success'),
                    TextEntry::make('customer.name')
                        ->label('Cliente'),
                    TextEntry::make('method.name')
                        ->label('Medio de pago'),
                    TextEntry::make('collector.name')
                        ->label('Cobrado por'),
                    TextEntry::make('notes')
                        ->label('Notas')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),
            Section::make('Anulación')
                ->columns(3)
                ->visible(fn (Payment $record): bool => $record->isCancelled())
                ->schema([
                    TextEntry::make('cancelled_at')
                        ->label('Anulado el')
                        ->dateTime('d/m/Y H:i'),
                    TextEntry::make('cancelledBy.name')
                        ->label('Anulado por'),
                    TextEntry::make('cancellation_reason')
                        ->label('Motivo'),
                ]),
            Section::make('Importes')
                ->columns(3)
                ->schema([
                    TextEntry::make('base_amount')->label('Baja la deuda')->money('ARS', locale: 'es_AR'),
                    TextEntry::make('adjustment_amount')->label('Recargo / descuento')->money('ARS', locale: 'es_AR'),
                    TextEntry::make('amount')->label('Cobrado')->money('ARS', locale: 'es_AR'),
                ]),
            Section::make('Vencimientos cancelados')
                ->schema([
                    RepeatableEntry::make('imputations')
                        ->hiddenLabel()
                        ->columns(3)
                        ->state(fn (Payment $record): array => self::imputations($record))
                        ->schema([
                            TextEntry::make('sale')->label('Venta'),
                            TextEntry::make('due')->label('Vencimiento'),
                            TextEntry::make('amount')->label('Imputado'),
                        ]),
                ]),
        ]);
    }

    /**
     * @return list<array{sale: string, due: string, amount: string}>
     */
    private static function imputations(Payment $payment): array
    {
        return DB::table('installment_payment')
            ->join('installments', 'installments.id', '=', 'installment_payment.installment_id')
            ->where('installment_payment.payment_id', $payment->getKey())
            ->orderBy('installments.due_date')
            ->orderBy('installments.id')
            ->get(['installments.sale_id', 'installments.due_date', 'installment_payment.amount'])
            ->map(fn (object $row): array => [
                'sale' => 'V-'.str_pad((string) $row->sale_id, 5, '0', STR_PAD_LEFT),
                'due' => date('d/m/Y', (int) strtotime((string) $row->due_date)),
                'amount' => Money::format(Money::from($row->amount)),
            ])
            ->all();
    }
}
