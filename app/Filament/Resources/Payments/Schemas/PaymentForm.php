<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Models\Customer;
use App\Models\Installment;
use App\Models\PaymentMethod;
use App\Services\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Nuevo cobro a cuenta: cliente, medio de pago, monto (lo que baja la deuda), y
 * opcionalmente los vencimientos a cancelar. Sin elegir, se imputa al más viejo primero.
 */
class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Cliente')
                ->columns(2)
                ->schema([
                    Select::make('customer_id')
                        ->label('Cliente')
                        ->required()
                        ->searchable()
                        ->live()
                        ->getSearchResultsUsing(fn (string $search): array => self::searchCustomers($search))
                        ->options(fn (): array => self::searchCustomers(''))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => Customer::query()->find($value)?->name)
                        ->afterStateUpdated(function (Set $set): void {
                            $set('installment_ids', []);
                            $set('base_amount', null);
                        }),
                    Text::make(fn (Get $get): string => self::debtLabel($get('customer_id')))
                        ->color(fn (Get $get): string => str_contains(self::debtLabel($get('customer_id')), 'vencido') ? 'danger' : 'gray'),
                ]),
            Section::make('Cobro')
                ->columns(2)
                ->schema([
                    Select::make('payment_method_id')
                        ->label('Medio de pago')
                        ->required()
                        ->native(false)
                        ->live()
                        ->options(fn (): array => PaymentMethod::query()
                            ->where('active', true)
                            ->orderBy('position')
                            ->get()
                            ->mapWithKeys(fn (PaymentMethod $method): array => [
                                $method->getKey() => $method->label().($method->installments > 1 ? " · {$method->installments} cuotas" : ''),
                            ])
                            ->all()),
                    DatePicker::make('date')
                        ->label('Fecha')
                        ->default(fn (): string => now()->toDateString())
                        ->required(),
                    TextInput::make('base_amount')
                        ->label('Monto que baja la deuda')
                        ->required()
                        ->numeric()
                        ->minValue(0.01)
                        ->step('0.01')
                        ->prefix('$')
                        ->live(onBlur: true)
                        ->helperText('El recargo o descuento del medio de pago se suma o resta a lo que paga el cliente, pero no cambia la deuda.')
                        ->suffixAction(
                            Action::make('all')
                                ->label('Toda la deuda')
                                ->action(function (Get $get, Set $set): void {
                                    $set('base_amount', self::selectedBalance($get('customer_id'), $get('installment_ids') ?? []));
                                }),
                        ),
                    Text::make(fn (Get $get): string => self::preview($get)),
                    CheckboxList::make('installment_ids')
                        ->label('Vencimientos a cancelar (opcional)')
                        ->helperText('Si no elegís ninguno, se cancela primero el más viejo.')
                        ->live()
                        ->options(fn (Get $get): array => self::installmentOptions($get('customer_id')))
                        ->visible(fn (Get $get): bool => filled($get('customer_id')))
                        ->columnSpanFull(),
                    Textarea::make('notes')
                        ->label('Notas')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private static function searchCustomers(string $search): array
    {
        return Customer::query()
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('document_number', 'like', "%{$search}%"),
            ))
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->all();
    }

    private static function debtLabel(mixed $customerId): string
    {
        $customer = filled($customerId) ? Customer::query()->find($customerId) : null;

        if ($customer === null) {
            return 'Elegí un cliente para ver su deuda.';
        }

        $debt = $customer->accountBalance();
        $overdue = $customer->overdueBalance();

        if (Money::compare($debt, '0') === 0) {
            return 'El cliente no tiene deuda.';
        }

        return 'Deuda: '.Money::format($debt).(Money::compare($overdue, '0') > 0 ? ' · vencido: '.Money::format($overdue) : '');
    }

    /**
     * Lo que se debe de los vencimientos elegidos (o de todos si no se eligió ninguno).
     *
     * @param  array<int, int|string>  $ids
     */
    private static function selectedBalance(mixed $customerId, array $ids): string
    {
        if (blank($customerId)) {
            return '0.00';
        }

        return Money::sum(Installment::query()
            ->where('customer_id', $customerId)
            ->open()
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->get()
            ->map(fn (Installment $installment): string => $installment->balance())
            ->all());
    }

    /**
     * @return array<int, string>
     */
    private static function installmentOptions(mixed $customerId): array
    {
        if (blank($customerId)) {
            return [];
        }

        return Installment::query()
            ->where('customer_id', $customerId)
            ->open()
            ->oldestFirst()
            ->with('sale')
            ->get()
            ->mapWithKeys(fn (Installment $installment): array => [
                $installment->getKey() => $installment->sale->number().' · vence '.$installment->due_date->format('d/m/Y').' · saldo '.Money::format($installment->balance()).($installment->isOverdue() ? ' (vencido)' : ''),
            ])
            ->all();
    }

    private static function preview(Get $get): string
    {
        $base = $get('base_amount');
        $method = filled($get('payment_method_id')) ? PaymentMethod::query()->find($get('payment_method_id')) : null;

        if (! is_numeric($base) || $method === null) {
            return '';
        }

        $base = Money::from($base);
        $adjustment = Money::percentOf($base, (string) $method->adjustment_percent);

        return 'Ajuste del medio: '.Money::format($adjustment).' · El cliente paga: '.Money::format(Money::add($base, $adjustment));
    }
}
