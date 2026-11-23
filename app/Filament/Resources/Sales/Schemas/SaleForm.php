<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Actions\Products\CreateProduct;
use App\Actions\Sales\FindVariantByCode;
use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\InstallmentSchedule;
use App\Services\Money;
use App\Services\SaleCalculator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Datos de la venta')
                ->columns(2)
                ->schema([
                    Select::make('customer_id')
                        ->label('Cliente')
                        ->placeholder('Consumidor final')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => self::searchCustomers($search))
                        ->options(fn (): array => self::searchCustomers(''))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => Customer::query()->find($value)?->name)
                        // Con saldo a cuenta (módulo Cuenta corriente) la venta necesita un cliente.
                        ->required(fn (Get $get): bool => self::leavesBalance($get))
                        ->helperText(fn (Get $get): ?string => self::leavesBalance($get) ? 'Obligatorio: la venta deja saldo a cuenta del cliente.' : null)
                        // Alta rápida de cliente sin salir de la venta.
                        ->createOptionForm(CustomerForm::fields())
                        ->createOptionUsing(fn (array $data): int => Customer::create($data)->getKey()),
                    Select::make('seller_id')
                        ->label('Vendedor')
                        ->options(fn (): array => User::query()
                            ->visibleToClient()
                            ->where('active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->default(fn (): ?int => self::defaultSellerId())
                        ->required()
                        ->native(false)
                        // Un vendedor siempre carga a su nombre.
                        ->disabled(fn (): bool => self::currentUser()?->isSeller() ?? false),
                    DatePicker::make('date')
                        ->label('Fecha')
                        ->default(fn (): string => now()->toDateString())
                        ->required(),
                    Textarea::make('notes')
                        ->label('Notas')
                        ->rows(2),
                ]),
            Section::make('Productos')
                ->schema([
                    // Módulo Códigos de barras: el lector escribe el código y Enter.
                    TextInput::make('scan')
                        ->label('Escanear código')
                        ->placeholder('Pasá el lector por la etiqueta')
                        ->prefixIcon('heroicon-o-qr-code')
                        ->dehydrated(false)
                        ->autofocus()
                        ->autocomplete(false)
                        ->visible(fn (): bool => Modules::enabled(Module::Barcodes))
                        ->extraInputAttributes([
                            'x-on:keydown.enter.prevent' => "\$wire.set('data.scan', \$el.value).then(() => { \$el.value = ''; \$el.focus() })",
                        ])
                        ->afterStateUpdated(fn (mixed $state, Get $get, Set $set) => self::addScannedCode($state, $get, $set)),
                    Repeater::make('items')
                        ->hiddenLabel()
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addActionLabel('Agregar producto')
                        ->live()
                        ->columns(12)
                        ->schema([
                            Select::make('variant_id')
                                ->label('Producto')
                                ->required()
                                ->searchable()
                                ->live()
                                ->getSearchResultsUsing(fn (string $search): array => self::searchVariants($search))
                                ->options(fn (): array => self::searchVariants(''))
                                ->getOptionLabelUsing(fn (mixed $value): ?string => ProductVariant::query()->with('product')->find($value)?->fullName())
                                // Alta rápida de producto: solo el administrador.
                                ->createOptionForm(fn (): ?array => self::currentUser()?->isAdmin() ? ProductForm::fields() : null)
                                ->createOptionUsing(fn (array $data): int => (int) app(CreateProduct::class)
                                    ->handle($data)
                                    ->variants()
                                    ->orderBy('id')
                                    ->value('id'))
                                ->afterStateUpdated(function (mixed $state, Set $set): void {
                                    $variant = ProductVariant::query()->with('product')->find($state);

                                    $set('unit_price', $variant?->effectivePrice());
                                })
                                ->columnSpan(5),
                            TextInput::make('quantity')
                                ->label('Cantidad')
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->default(1)
                                ->required()
                                ->live(onBlur: true)
                                ->columnSpan(2),
                            // El vendedor no cambia el precio: sale del catálogo.
                            TextInput::make('unit_price')
                                ->label('Precio')
                                ->numeric()
                                ->minValue(0)
                                ->step('0.01')
                                ->prefix('$')
                                ->required()
                                ->live(onBlur: true)
                                ->readOnly(fn (): bool => ! (self::currentUser()?->isAdmin() ?? false))
                                ->columnSpan(3),
                            Text::make(fn (Get $get): string => 'Subtotal: '.Money::format(
                                (new SaleCalculator)->lineSubtotal(
                                    max(0, (int) $get('quantity')),
                                    Money::from(is_numeric($get('unit_price')) ? $get('unit_price') : null),
                                ),
                            ))->columnSpan(2),
                            Text::make(fn (Get $get): string => self::stockHint($get) ?? '')
                                ->color(fn (Get $get): string => str_starts_with(self::stockHint($get) ?? '', 'Pediste') ? 'warning' : 'gray')
                                ->visible(fn (Get $get): bool => self::stockHint($get) !== null)
                                ->columnSpanFull(),
                        ]),
                ]),
            Section::make('Cobro')
                ->description(fn (): string => self::onAccount()
                    ? 'Lo que se cobra ahora. Lo que falte queda a cuenta del cliente; sin ningún cobro, la venta queda entera a cuenta.'
                    : 'Con varios medios de pago, la suma de los montos tiene que dar el subtotal.')
                ->schema([
                    Repeater::make('payments')
                        ->hiddenLabel()
                        // Con Cuenta corriente se puede no cobrar nada en el acto.
                        ->minItems(fn (): int => self::onAccount() ? 0 : 1)
                        ->defaultItems(1)
                        ->addActionLabel('Agregar otro medio de pago')
                        ->live()
                        ->columns(12)
                        ->schema([
                            Select::make('payment_method_id')
                                ->label('Medio de pago')
                                ->options(fn (): array => PaymentMethod::query()
                                    ->where('active', true)
                                    ->orderBy('position')
                                    ->get()
                                    ->mapWithKeys(fn (PaymentMethod $method): array => [
                                        $method->getKey() => $method->label().($method->installments > 1 ? " · {$method->installments} cuotas" : ''),
                                    ])
                                    ->all())
                                ->required()
                                ->live()
                                ->native(false)
                                ->columnSpan(6),
                            TextInput::make('base_amount')
                                ->label('Monto a cubrir')
                                ->numeric()
                                ->minValue(0)
                                ->step('0.01')
                                ->prefix('$')
                                ->live(onBlur: true)
                                // Con un solo medio de pago cubre todo el subtotal (o lo que se indique, con Cuenta corriente).
                                ->placeholder(fn (): ?string => self::onAccount() ? 'Todo el subtotal' : null)
                                ->visible(fn (Get $get): bool => self::onAccount() || count($get('../../payments') ?? []) > 1)
                                ->required(fn (Get $get): bool => count($get('../../payments') ?? []) > 1)
                                ->columnSpan(6),
                        ]),
                ]),
            Section::make('Resumen')
                ->schema([
                    Text::make(fn (Get $get): string => 'Subtotal: '.Money::format(self::calculation($get)['subtotal'])),
                    Text::make(fn (Get $get): string => 'Recargos y descuentos: '.Money::format(self::calculation($get)['adjustment'])),
                    Text::make(fn (Get $get): string => (self::onAccount() ? 'Total de la venta: ' : 'Total a cobrar: ').Money::format(self::calculation($get)['total']))
                        ->size('lg'),
                    Text::make(fn (Get $get): string => 'Se cobra ahora: '.Money::format(Money::sub(self::calculation($get)['total'], self::calculation($get)['balance'])))
                        ->visible(fn (Get $get): bool => self::leavesBalance($get)),
                    Text::make(fn (Get $get): string => 'A cuenta del cliente: '.Money::format(self::calculation($get)['balance']))
                        ->size('lg')
                        ->color('warning')
                        ->visible(fn (Get $get): bool => self::leavesBalance($get)),
                    // Cuotas propias: un plan reparte el saldo en cuotas con su recargo.
                    Select::make('financing_plan_id')
                        ->label('Plan de pago')
                        ->placeholder('Un solo vencimiento')
                        ->options(fn (): array => FinancingPlan::query()
                            ->where('active', true)
                            ->orderBy('position')
                            ->orderBy('id')
                            ->get()
                            ->mapWithKeys(fn (FinancingPlan $plan): array => [$plan->getKey() => $plan->label()])
                            ->all())
                        ->native(false)
                        ->live()
                        ->visible(fn (Get $get): bool => self::leavesBalance($get) && self::onInstallments()),
                    Select::make('first_month')
                        ->label('Mes de la primera cuota')
                        ->options(fn (Get $get): array => self::monthOptions($get))
                        ->default(fn (): string => now()->addMonthNoOverflow()->format('Y-m'))
                        ->native(false)
                        ->live()
                        ->visible(fn (Get $get): bool => self::usesPlan($get) && self::dueRule() === InstallmentSchedule::RULE_FIXED_DAY)
                        ->helperText(fn (): string => 'Vencen el día '.self::fixedDay().' de cada mes.'),
                    Text::make(fn (Get $get): string => self::planPreview($get))
                        ->extraAttributes(['style' => 'white-space: pre-line;'])
                        ->visible(fn (Get $get): bool => self::usesPlan($get)),
                    DatePicker::make('due_date')
                        ->label('Vencimiento del saldo')
                        ->default(fn (): string => now()->addDays(30)->toDateString())
                        ->minDate(fn (Get $get): mixed => $get('date'))
                        ->required(fn (Get $get): bool => self::leavesBalance($get) && ! self::usesPlan($get))
                        ->visible(fn (Get $get): bool => self::leavesBalance($get) && ! self::usesPlan($get)),
                    Text::make(fn (Get $get): string => self::balanceWarning($get) ?? '')
                        ->color('danger')
                        ->visible(fn (Get $get): bool => self::balanceWarning($get) !== null),
                ]),
        ]);
    }

    private static function onAccount(): bool
    {
        return Modules::enabled(Module::CurrentAccount);
    }

    private static function onInstallments(): bool
    {
        return Modules::enabled(Module::Installments);
    }

    /**
     * Hay saldo y se eligió un plan de financiación (módulo Cuotas propias).
     */
    private static function usesPlan(Get $get): bool
    {
        return self::onInstallments() && self::leavesBalance($get) && filled($get('financing_plan_id'));
    }

    private static function dueRule(): string
    {
        return (string) Modules::setting(Module::Installments, 'due_rule', InstallmentSchedule::RULE_FIXED_DAY);
    }

    private static function fixedDay(): int
    {
        return (int) Modules::setting(Module::Installments, 'fixed_day', 10);
    }

    private static function saleDate(Get $get): Carbon
    {
        $date = $get('date');

        try {
            return filled($date) ? Carbon::parse($date)->startOfDay() : now()->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }

    /**
     * Meses para la primera cuota: el de la venta y los 11 siguientes.
     *
     * @return array<string, string>
     */
    private static function monthOptions(Get $get): array
    {
        $start = self::saleDate($get)->startOfMonth();
        $options = [];

        foreach (range(0, 11) as $offset) {
            $month = $start->copy()->addMonthsNoOverflow($offset);
            $options[$month->format('Y-m')] = ucfirst($month->locale('es')->translatedFormat('F Y'));
        }

        return $options;
    }

    /**
     * Vista previa en vivo con el mismo servicio que arma las cuotas de la venta real.
     */
    private static function planPreview(Get $get): string
    {
        $plan = FinancingPlan::query()->find($get('financing_plan_id'));

        if ($plan === null) {
            return '';
        }

        $schedule = new InstallmentSchedule;
        $balance = self::calculation($get)['balance'];
        $surcharge = $schedule->surcharge($balance, (string) $plan->surcharge_percent);
        $financed = Money::add($balance, $surcharge);

        $saleDate = self::saleDate($get);
        $rule = self::dueRule();
        $month = filled($get('first_month')) ? (string) $get('first_month') : null;
        $first = $schedule->firstDueDate($saleDate, $rule, self::fixedDay(), $month);
        $rows = $schedule->build($financed, $plan->installments_count, $first, $schedule->anchorDay($saleDate, $rule, self::fixedDay()), '0.00');

        $lines = [
            'A financiar: '.Money::format($balance).(Money::compare($surcharge, '0') > 0 ? ' + recargo '.Money::format($surcharge).' = '.Money::format($financed) : ''),
            "{$plan->installments_count} cuotas:",
        ];

        foreach ($rows as $row) {
            $lines[] = $row['number'].'. '.$row['due_date']->format('d/m/Y').' — '.Money::format($row['amount']);
        }

        if ($first->lt($saleDate)) {
            $lines[] = 'Atención: la primera cuota vence antes de la venta; elegí otro mes.';
        }

        return implode("\n", $lines);
    }

    /**
     * Con Cuenta corriente: los cobros dejan algo sin cubrir y hay con qué armar el saldo.
     */
    private static function leavesBalance(Get $get): bool
    {
        if (! self::onAccount()) {
            return false;
        }

        $calculation = self::calculation($get);

        return ! $calculation['overpaid'] && Money::compare($calculation['balance'], '0') > 0;
    }

    /**
     * Cálculo en vivo con el mismo servicio que usa la venta real.
     *
     * @return array{subtotal: string, adjustment: string, total: string, payments: list<array{base_amount: string, adjustment_amount: string, amount: string}>, balanced: bool, balance: string, overpaid: bool}
     */
    private static function calculation(Get $get): array
    {
        $items = [];

        foreach ($get('items') ?? [] as $item) {
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);

            if ($quantity === false || $quantity < 1 || ! is_numeric($item['unit_price'] ?? null)) {
                continue;
            }

            $items[] = ['quantity' => $quantity, 'unit_price' => Money::from($item['unit_price'])];
        }

        $rawPayments = array_values($get('payments') ?? []);
        $methodIds = collect($rawPayments)->pluck('payment_method_id')->filter();

        // Sin medio de pago elegido no hay nada que consultar (el formulario recalcula varias veces por pantalla).
        $methods = $methodIds->isEmpty()
            ? collect()
            : PaymentMethod::query()->whereIn('id', $methodIds)->get()->keyBy('id');

        $payments = [];

        foreach ($rawPayments as $payment) {
            $method = $methods->get($payment['payment_method_id'] ?? 0);

            if ($method === null) {
                continue;
            }

            $payments[] = [
                'adjustment_percent' => $method->adjustment_percent,
                'base_amount' => is_numeric($payment['base_amount'] ?? null) ? Money::from($payment['base_amount']) : null,
            ];
        }

        return (new SaleCalculator)->calculate($items, $payments);
    }

    private static function balanceWarning(Get $get): ?string
    {
        $calculation = self::calculation($get);

        if ($calculation['payments'] === [] || $calculation['balanced'] || Money::compare($calculation['subtotal'], '0') === 0) {
            return null;
        }

        // Con Cuenta corriente cobrar de menos es válido (queda a cuenta); solo se avisa si se pasa.
        if (self::onAccount() && ! $calculation['overpaid']) {
            return null;
        }

        $paid = Money::sum(array_column($calculation['payments'], 'base_amount'));

        return 'La suma de los montos ('.Money::format($paid).') no coincide con el subtotal ('.Money::format($calculation['subtotal']).').';
    }

    /**
     * @return array<int, string>
     */
    private static function searchCustomers(string $search): array
    {
        return Customer::query()
            ->where('active', true)
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('document_number', 'like', "%{$search}%"),
            ))
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Variantes vendibles (ver ProductVariant::sellable()). Con el módulo Stock se
     * muestra la existencia de cada una.
     *
     * @return array<int, string>
     */
    public static function searchVariants(string $search): array
    {
        $withStock = Modules::enabled(Module::Stock);

        return ProductVariant::query()
            ->with('product')
            ->sellable()
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($product) => $product
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")),
            ))
            ->orderBy('product_id')
            ->orderBy('position')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (ProductVariant $variant): array => [
                $variant->getKey() => $variant->fullName().' · '.($variant->sku ?? $variant->product->sku).($withStock ? ' · stock '.$variant->stock : ''),
            ])
            ->all();
    }

    /**
     * Agrega a la venta la variante del código leído: si ya está, suma una unidad; si no,
     * la agrega con su precio (reemplazando la fila vacía que trae el formulario).
     */
    public static function addScannedCode(mixed $state, Get $get, Set $set): void
    {
        $set('scan', null);

        $code = trim((string) $state);

        if ($code === '') {
            return;
        }

        try {
            $variant = app(FindVariantByCode::class)->handle($code);
        } catch (ValidationException $e) {
            Notification::make()->title($e->validator->errors()->first())->danger()->send();

            return;
        }

        $items = (array) ($get('items') ?? []);

        foreach ($items as $key => $item) {
            if ((int) ($item['variant_id'] ?? 0) === $variant->getKey()) {
                $items[$key]['quantity'] = max(0, (int) ($item['quantity'] ?? 0)) + 1;
                $set('items', $items);

                return;
            }
        }

        $items = array_filter($items, fn (mixed $item): bool => filled($item['variant_id'] ?? null));
        $items[(string) Str::uuid()] = [
            'variant_id' => $variant->getKey(),
            'quantity' => 1,
            'unit_price' => $variant->effectivePrice(),
        ];

        $set('items', $items);
    }

    /**
     * Aviso de existencia bajo la fila de un ítem (módulo Stock). En modo "bloquear" la
     * venta se rechaza si no alcanza; en "avisar" solo se advierte.
     */
    private static function stockHint(Get $get): ?string
    {
        if (! Modules::enabled(Module::Stock) || blank($get('variant_id'))) {
            return null;
        }

        $variant = ProductVariant::query()->find($get('variant_id'));

        if ($variant === null) {
            return null;
        }

        $quantity = max(0, (int) $get('quantity'));

        if ($quantity <= $variant->stock) {
            return 'Stock disponible: '.$variant->stock;
        }

        $mode = Modules::setting(Module::Stock, 'on_no_stock', 'warn') === 'block'
            ? 'La venta se rechazará si no alcanza.'
            : 'Se vende igual y el stock queda en negativo.';

        return "Pediste {$quantity} y hay {$variant->stock}. {$mode}";
    }

    private static function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private static function defaultSellerId(): ?int
    {
        $user = self::currentUser();

        if ($user?->isSeller()) {
            return $user->getKey();
        }

        // El administrador arranca con el vendedor por defecto, si hay uno activo.
        return User::query()->visibleToClient()->where('active', true)->where('is_default_seller', true)->value('id')
            ?? $user?->getKey();
    }
}
