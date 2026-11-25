<?php

namespace App\Filament\Resources\CashSessions\Schemas;

use App\Actions\CashRegister\CloseCashSession;
use App\Services\Money;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Campos del cierre de un turno (módulo Control de caja): lo contado a mano o billete por
 * billete, el fondo que queda y una nota. Lo usan la Caja y la ficha del turno. Con arqueo
 * ciego, a quien cierra no se le muestra lo esperado.
 */
class CloseCashSessionForm
{
    /**
     * @return array<int, Component>
     */
    public static function fields(?string $expected): array
    {
        return [
            Text::make(fn (): string => 'Efectivo esperado en el cajón: '.Money::format((string) $expected))
                ->weight('bold')
                ->visible($expected !== null),
            Text::make('Contá el efectivo del cajón sin mirar el sistema: la diferencia la revisa el administrador.')
                ->visible($expected === null),
            Toggle::make('by_bills')
                ->label('Contar billete por billete')
                ->live(),
            TextInput::make('counted_amount')
                ->label('Efectivo contado')
                ->prefix('$')
                ->numeric()
                ->minValue(0)
                ->step('0.01')
                ->required(fn (Get $get): bool => ! $get('by_bills'))
                ->visible(fn (Get $get): bool => ! $get('by_bills')),
            Grid::make(['default' => 2, 'sm' => 3])
                ->visible(fn (Get $get): bool => (bool) $get('by_bills'))
                ->schema([
                    ...array_map(
                        fn (string $bill): TextInput => TextInput::make('bill_'.$bill)
                            ->label('Billetes de $ '.number_format((int) $bill, 0, ',', '.'))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->live(onBlur: true),
                        CloseCashSession::BILLS,
                    ),
                    TextInput::make('coins')
                        ->label('Monedas ($)')
                        ->numeric()
                        ->minValue(0)
                        ->step('0.01')
                        ->live(onBlur: true),
                ]),
            Text::make(fn (Get $get): string => 'Total contado: '.Money::format(self::billsTotal($get)))
                ->weight('bold')
                ->visible(fn (Get $get): bool => (bool) $get('by_bills')),
            TextInput::make('left_amount')
                ->label('Fondo que queda en el cajón')
                ->helperText('Lo que se deja para abrir el próximo turno. El resto se retira.')
                ->prefix('$')
                ->numeric()
                ->minValue(0)
                ->step('0.01')
                ->default('0'),
            Textarea::make('notes')
                ->label('Notas')
                ->rows(2)
                ->maxLength(500),
        ];
    }

    /**
     * Lo que espera la acción de cierre.
     *
     * @param  array<string, mixed>  $data
     *                                      Los billetes van por su valor (PHP convierte «20000» en clave entera) y las monedas en «coins».
     * @return array{counted_amount: mixed, counted_bills: array<int|string, mixed>|null, left_amount: mixed, notes: mixed}
     */
    public static function toActionData(array $data): array
    {
        $bills = null;

        if ($data['by_bills'] ?? false) {
            $bills = ['coins' => $data['coins'] ?? null];

            foreach (CloseCashSession::BILLS as $bill) {
                $bills[$bill] = $data['bill_'.$bill] ?? null;
            }
        }

        return [
            'counted_amount' => $data['counted_amount'] ?? null,
            'counted_bills' => $bills,
            'left_amount' => $data['left_amount'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private static function billsTotal(Get $get): string
    {
        $total = '0.00';

        foreach (CloseCashSession::BILLS as $bill) {
            $quantity = filter_var($get('bill_'.$bill), FILTER_VALIDATE_INT);

            if ($quantity !== false && $quantity > 0) {
                $total = Money::add($total, Money::mul($bill, $quantity));
            }
        }

        $coins = $get('coins');

        return is_numeric($coins) && ! Money::isNegative(Money::from($coins)) ? Money::add($total, Money::from($coins)) : $total;
    }
}
