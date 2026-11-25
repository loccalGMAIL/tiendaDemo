<?php

namespace App\Filament\Resources\CashSessions\Schemas;

use App\Models\CashSession;
use App\Services\CashSessionSummary;
use App\Services\Money;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use WeakMap;

class CashSessionInfolist
{
    /**
     * El resumen de cada turno mientras se arma su ficha (atado al objeto: no sobrevive al pedido).
     *
     * @var WeakMap<CashSession, array{opening: string, sales: string, deposits: string, withdrawals: string, refunds: string, expected: string, sales_count: int, by_method: list<array{name: string, is_cash: bool, count: int, amount: string}>}>|null
     */
    private static ?WeakMap $summaries = null;

    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Turno')
                ->columns(3)
                ->schema([
                    TextEntry::make('number')->label('N°')->state(fn (CashSession $record): string => $record->number()),
                    TextEntry::make('register.name')->label('Caja'),
                    TextEntry::make('status')
                        ->label('Estado')
                        ->badge()
                        ->state(fn (CashSession $record): string => $record->isOpen() ? 'Abierto' : 'Cerrado')
                        ->color(fn (string $state): string => $state === 'Abierto' ? 'success' : 'gray'),
                    TextEntry::make('openedBy.name')->label('Abrió'),
                    TextEntry::make('opened_at')->label('Apertura')->dateTime('d/m/Y H:i'),
                    TextEntry::make('sales_count')->label('Ventas')->state(fn (CashSession $record): int => self::summary($record)['sales_count']),
                    TextEntry::make('closedBy.name')->label('Cerró')->placeholder('—'),
                    TextEntry::make('closed_at')->label('Cierre')->dateTime('d/m/Y H:i')->placeholder('—'),
                    TextEntry::make('closing_notes')->label('Notas del cierre')->placeholder('—'),
                ]),
            Section::make('Efectivo')
                ->columns(3)
                ->schema([
                    self::money('opening', 'Fondo inicial'),
                    self::money('sales', 'Ventas en efectivo'),
                    self::money('deposits', 'Ingresos'),
                    self::money('withdrawals', 'Retiros'),
                    self::money('refunds', 'Devoluciones'),
                    TextEntry::make('expected')
                        ->label(fn (CashSession $record): string => $record->isOpen() ? 'Esperado ahora' : 'Esperado')
                        ->state(fn (CashSession $record): string => Money::format($record->isOpen() ? self::summary($record)['expected'] : (string) $record->expected_amount))
                        ->weight('bold'),
                    TextEntry::make('counted_amount')->label('Contado')->money('ARS', locale: 'es_AR')->placeholder('—'),
                    TextEntry::make('difference')
                        ->label('Diferencia')
                        ->state(fn (CashSession $record): ?string => $record->difference === null ? null : self::differenceLabel((string) $record->difference))
                        ->color(fn (CashSession $record): string => $record->difference !== null && Money::compare((string) $record->difference, '0') === 0 ? 'success' : 'danger')
                        ->weight('bold')
                        ->placeholder('—'),
                    TextEntry::make('left_amount')->label('Fondo que quedó')->money('ARS', locale: 'es_AR')->placeholder('—'),
                    TextEntry::make('counted_bills')
                        ->label('Conteo por billete')
                        ->state(fn (CashSession $record): ?string => self::billsLabel($record->counted_bills))
                        ->columnSpanFull()
                        ->visible(fn (CashSession $record): bool => filled($record->counted_bills)),
                ]),
            Section::make('Cobros por medio de pago')
                ->description('Todo lo cobrado en este turno. Los medios que no son efectivo se controlan contra los cupones o el posnet.')
                ->schema([
                    RepeatableEntry::make('by_method')
                        ->hiddenLabel()
                        ->state(fn (CashSession $record): array => self::summary($record)['by_method'])
                        ->columns(3)
                        ->schema([
                            TextEntry::make('name')->label('Medio'),
                            TextEntry::make('count')->label('Cobros'),
                            TextEntry::make('amount')->label('Total')->money('ARS', locale: 'es_AR'),
                        ])
                        ->placeholder('Sin cobros'),
                ]),
            Section::make('Movimientos de efectivo')
                ->schema([
                    RepeatableEntry::make('movements')
                        ->hiddenLabel()
                        ->columns(5)
                        ->schema([
                            TextEntry::make('created_at')->label('Hora')->dateTime('H:i'),
                            TextEntry::make('type')->label('Tipo')->badge(),
                            TextEntry::make('description')->label('Detalle'),
                            TextEntry::make('user.name')->label('Usuario'),
                            TextEntry::make('amount')->label('Monto')->money('ARS', locale: 'es_AR'),
                        ]),
                ]),
        ]);
    }

    private static function money(string $key, string $label): TextEntry
    {
        return TextEntry::make($key)
            ->label($label)
            ->state(fn (CashSession $record): string => Money::format(self::summary($record)[$key]));
    }

    /**
     * @return array{opening: string, sales: string, deposits: string, withdrawals: string, refunds: string, expected: string, sales_count: int, by_method: list<array{name: string, is_cash: bool, count: int, amount: string}>}
     */
    private static function summary(CashSession $record): array
    {
        self::$summaries ??= new WeakMap;

        return self::$summaries[$record] ??= app(CashSessionSummary::class)->for($record);
    }

    public static function differenceLabel(string $difference): string
    {
        return match (Money::compare($difference, '0')) {
            0 => 'Exacto',
            1 => 'Sobran '.Money::format($difference),
            default => 'Faltan '.Money::format(Money::sub('0', $difference)),
        };
    }

    /**
     * @param  array<string, int|string>|null  $bills
     */
    public static function billsLabel(?array $bills): ?string
    {
        if ($bills === null || $bills === []) {
            return null;
        }

        $parts = [];

        foreach ($bills as $bill => $quantity) {
            $parts[] = $bill === 'coins'
                ? 'monedas '.Money::format((string) $quantity)
                : $quantity.' × $ '.number_format((int) $bill, 0, ',', '.');
        }

        return implode(' · ', $parts);
    }
}
