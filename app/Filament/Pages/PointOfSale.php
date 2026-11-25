<?php

namespace App\Filament\Pages;

use App\Actions\Sales\CreateSale;
use App\Actions\Sales\FindVariantByCode;
use App\Enums\Module;
use App\Enums\SaleChannel;
use App\Facades\Modules;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Customer;
use App\Models\FinancingPlan;
use App\Models\PaymentMethod;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\InstallmentSchedule;
use App\Services\Money;
use App\Services\SaleCalculator;
use App\Services\SaleTicket;
use App\Services\Tender;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Caja: la pantalla de venta del mostrador, pensada para teclado y lector de códigos. Un solo
 * campo recibe códigos, SKU o texto («3*CODIGO» suma tres unidades); el cobro se hace en una
 * ventana aparte con el vuelto a la vista. La venta se confirma con la misma acción que
 * «Nueva venta» (`CreateSale`), siempre con canal Mostrador, fecha de hoy y el usuario como
 * vendedor. El precio sale siempre del catálogo: la pantalla no lo manda.
 */
class PointOfSale extends Page
{
    public const MAX_QUANTITY = 9999;

    public const MAX_RESULTS = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $navigationLabel = 'Caja';

    protected static ?string $title = 'Caja';

    protected static string|\UnitEnum|null $navigationGroup = 'Ventas';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'caja';

    protected string $view = 'filament.pages.point-of-sale';

    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * Ítems del ticket. Solo variante y cantidad: descripción y precio se leen del catálogo
     * en cada pantalla, así no se pueden alterar desde el navegador.
     *
     * @var list<array{variant_id: int, quantity: int}>
     */
    public array $lines = [];

    /** Línea sobre la que actúan +, − y Supr. */
    public ?int $selectedLine = null;

    /**
     * Resultados de la última búsqueda por texto, para elegir con ↑/↓ y Enter.
     *
     * @var list<array{id: int, label: string, code: string, price: string, stock: int|null}>
     */
    public array $results = [];

    /** Cantidad pedida con «N*» mientras se elige entre los resultados. */
    public ?int $resultsQuantity = null;

    public ?int $customerId = null;

    public bool $checkingOut = false;

    /**
     * Cobros de la venta. `base` es la parte del subtotal que cubre cada medio; con un solo
     * medio (o en el último, sin saldo a cuenta) cubre lo que falta.
     *
     * @var list<array{method_id: int|string|null, base: string|null}>
     */
    public array $payments = [];

    /** «Paga con»: lo que entrega el cliente para el último medio de pago. */
    public string $tendered = '';

    /** Módulo Cuenta corriente: lo que no se cobra ahora queda a cuenta del cliente. */
    public bool $onAccount = false;

    public ?string $dueDate = null;

    public int|string|null $financingPlanId = null;

    public ?string $firstMonth = null;

    /**
     * La venta recién confirmada: su vuelto y el ticket para imprimir o reimprimir (F9).
     *
     * @var array{id: int, number: string, total: string, change: string|null, url: string, ticket_url: string, ask_print: bool}|null
     */
    public ?array $lastSale = null;

    /** @var list<array{index: int, variant_id: int, description: string, code: string, quantity: int, unit_price: string, subtotal: string, stock: int|null, available: bool}>|null */
    private ?array $ticketMemo = null;

    /** @var Collection<int, PaymentMethod>|null */
    private ?Collection $methodsMemo = null;

    public static function canAccess(): bool
    {
        return SaleResource::canCreate();
    }

    /**
     * Sin encabezado: el lugar es para el ticket (el título sigue en la pestaña).
     */
    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * Lo que se escribe o escanea en el campo único. Primero se busca el código exacto (el SKU
     * de la etiqueta); si no, se busca por texto: con un solo resultado se agrega directo y con
     * varios se muestran para elegir.
     */
    public function submitEntry(string $entry): void
    {
        $this->results = [];
        $this->resultsQuantity = null;

        [$quantity, $term] = $this->parseEntry($entry);

        if ($term === '') {
            return;
        }

        try {
            $variant = app(FindVariantByCode::class)->handle($term);

            $this->addVariant($variant->getKey(), $quantity);

            return;
        } catch (ValidationException) {
            // No es un código vendible: se busca por texto (un código de producto con
            // variantes, por ejemplo, muestra sus variantes).
        }

        $results = $this->search($term);

        if (count($results) === 1) {
            $this->addVariant($results[0]['id'], $quantity);

            return;
        }

        if ($results === []) {
            Notification::make()->title("No se encontró ningún producto para «{$term}».")->warning()->send();

            return;
        }

        $this->results = $results;
        $this->resultsQuantity = $quantity;
    }

    public function addVariant(int $variantId, ?int $quantity = null): void
    {
        $quantity = $this->clampQuantity($quantity ?? 1);

        $this->results = [];
        $this->resultsQuantity = null;

        if (! ProductVariant::query()->sellable()->whereKey($variantId)->exists()) {
            Notification::make()->title('Ese producto no está disponible para la venta.')->danger()->send();

            return;
        }

        foreach ($this->lines as $index => $line) {
            if ($line['variant_id'] === $variantId) {
                $this->lines[$index]['quantity'] = $this->clampQuantity($line['quantity'] + $quantity);
                $this->selectedLine = $index;
                $this->forgetTicket();

                return;
            }
        }

        $this->lines[] = ['variant_id' => $variantId, 'quantity' => $quantity];
        $this->selectedLine = array_key_last($this->lines);
        $this->forgetTicket();
    }

    public function changeQuantity(?int $index, int $delta): void
    {
        if ($index === null || ! isset($this->lines[$index])) {
            return;
        }

        $quantity = $this->lines[$index]['quantity'] + $delta;

        if ($quantity < 1) {
            $this->removeLine($index);

            return;
        }

        $this->setQuantity($index, $quantity);
    }

    public function setQuantity(int $index, mixed $quantity): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }

        $value = filter_var($quantity, FILTER_VALIDATE_INT);

        $this->lines[$index]['quantity'] = $this->clampQuantity($value === false ? 1 : $value);
        $this->selectedLine = $index;
        $this->forgetTicket();
    }

    public function removeLine(?int $index): void
    {
        if ($index === null || ! isset($this->lines[$index])) {
            return;
        }

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        $this->selectedLine = $this->lines === [] ? null : min($index, count($this->lines) - 1);
        $this->forgetTicket();
    }

    public function selectLine(int $index): void
    {
        $this->selectedLine = isset($this->lines[$index]) ? $index : null;
    }

    public function clearResults(): void
    {
        $this->results = [];
        $this->resultsQuantity = null;
    }

    /**
     * Vacía el ticket sin vender (el cliente se arrepintió o se cargó mal).
     */
    public function clearTicket(): void
    {
        $this->lines = [];
        $this->selectedLine = null;
        $this->customerId = null;
        $this->clearResults();
        $this->resetCheckout();
        $this->forgetTicket();
        $this->dispatch('pos-focus-entry');
    }

    public function openCheckout(): void
    {
        if ($this->lines === []) {
            Notification::make()->title('Agregá al menos un producto para cobrar.')->warning()->send();

            return;
        }

        $this->resetCheckout();
        $this->payments = [['method_id' => $this->paymentMethods()->first()?->getKey(), 'base' => null]];
        $this->checkingOut = true;
    }

    public function closeCheckout(): void
    {
        $this->checkingOut = false;
    }

    /**
     * Elige el medio de pago del último cobro (con uno solo, el de toda la venta).
     */
    public function choosePaymentMethod(int $methodId): void
    {
        if (! $this->paymentMethods()->has($methodId)) {
            return;
        }

        if ($this->payments === []) {
            $this->payments[] = ['method_id' => $methodId, 'base' => null];

            return;
        }

        $this->payments[array_key_last($this->payments)]['method_id'] = $methodId;
    }

    /**
     * Divide el pago: agrega otro medio, que cubre lo que falta.
     */
    public function addPaymentLine(): void
    {
        $this->payments[] = ['method_id' => null, 'base' => null];
    }

    public function removePaymentLine(int $index): void
    {
        unset($this->payments[$index]);
        $this->payments = array_values($this->payments);
    }

    /**
     * A cuenta (módulo Cuenta corriente), lo que se cobra ahora se escribe a mano en cada
     * medio; vacío es nada. Sin el módulo no se puede activar.
     */
    public function updatedOnAccount(): void
    {
        if (! $this->canLeaveBalance()) {
            $this->onAccount = false;
        }
    }

    /**
     * Confirma la venta con la misma acción que «Nueva venta». Si algo no cierra, avisa y el
     * ticket queda como estaba para corregirlo.
     */
    public function confirm(): void
    {
        $actor = auth()->user();

        if (! $actor instanceof User || ! $this->checkingOut) {
            return;
        }

        $summary = $this->checkoutSummary();

        if ($summary['error'] !== null) {
            Notification::make()->title('No se pudo confirmar la venta')->body($summary['error'])->danger()->send();

            return;
        }

        if ($summary['change'] !== null && Money::isNegative($summary['change'])) {
            Notification::make()
                ->title('Lo que entrega no alcanza')
                ->body('Faltan '.Money::format(Money::sub('0', $summary['change'])).'. Corregí «Paga con» o dejalo vacío.')
                ->danger()
                ->send();

            return;
        }

        try {
            $sale = app(CreateSale::class)->handle($actor, $this->salePayload($summary));
        } catch (ValidationException $e) {
            Notification::make()->title('No se pudo confirmar la venta')->body($e->validator->errors()->first())->danger()->send();

            return;
        }

        $printMode = app(SaleTicket::class)->printMode();

        $this->lastSale = [
            'id' => $sale->getKey(),
            'number' => $sale->number(),
            'total' => (string) $sale->total,
            'change' => $summary['change'],
            'url' => SaleResource::getUrl('view', ['record' => $sale]),
            // «Paga con» viaja en el enlace para que el ticket muestre el vuelto (no se guarda).
            'ticket_url' => route('sales.ticket', ['sale' => $sale, 'pago' => $summary['tendered']]),
            'ask_print' => $printMode === 'ask',
        ];

        $this->clearTicket();

        // Siempre: se imprime solo. Preguntar: el foco queda en «Imprimir ticket» (Enter imprime;
        // seguir escaneando pasa al próximo cliente sin imprimir).
        match ($printMode) {
            'always' => $this->dispatch('pos-print-ticket', url: $this->lastSale['ticket_url']),
            'ask' => $this->dispatch('pos-ask-ticket'),
            default => null,
        };

        Notification::make()
            ->title("Venta {$sale->number()} confirmada")
            ->body($summary['change'] !== null ? 'Vuelto: '.Money::format($summary['change']) : null)
            ->success()
            ->send();
    }

    /**
     * Cliente de la venta (F4): búsqueda con alta rápida, como en «Nueva venta».
     */
    public function selectCustomerAction(): Action
    {
        return Action::make('selectCustomer')
            ->label('Cambiar (F4)')
            ->color('gray')
            ->size(Size::Small)
            ->modalHeading('Cliente de la venta')
            ->modalSubmitActionLabel('Elegir')
            ->modalWidth(Width::Large)
            ->fillForm(fn (): array => ['customer_id' => $this->customerId])
            ->schema([
                Select::make('customer_id')
                    ->label('Cliente')
                    ->placeholder('Consumidor final')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => $this->searchCustomers($search))
                    ->options(fn (): array => $this->searchCustomers(''))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => Customer::query()->find($value)?->name)
                    ->createOptionForm(CustomerForm::fields())
                    ->createOptionUsing(fn (array $data): int => Customer::create($data)->getKey()),
            ])
            ->action(function (array $data): void {
                $this->customerId = filled($data['customer_id'] ?? null) ? (int) $data['customer_id'] : null;
                $this->dispatch('pos-focus-entry');
            });
    }

    public function forgetCustomer(): void
    {
        $this->customerId = null;
        $this->dispatch('pos-focus-entry');
    }

    /**
     * El ticket con descripción, precio y existencia leídos del catálogo.
     *
     * @return list<array{index: int, variant_id: int, description: string, code: string, quantity: int, unit_price: string, subtotal: string, stock: int|null, available: bool}>
     */
    public function ticket(): array
    {
        if ($this->ticketMemo !== null) {
            return $this->ticketMemo;
        }

        $variants = ProductVariant::query()
            ->with('product')
            ->whereKey(array_column($this->lines, 'variant_id'))
            ->get()
            ->keyBy('id');

        $withStock = Modules::enabled(Module::Stock);
        $calculator = new SaleCalculator;
        $ticket = [];

        foreach ($this->lines as $index => $line) {
            $line = ['variant_id' => (int) $line['variant_id'], 'quantity' => $this->clampQuantity((int) $line['quantity'])];
            $variant = $variants->get($line['variant_id']);
            $price = $variant === null ? '0.00' : Money::from($variant->effectivePrice());

            $ticket[] = [
                'index' => $index,
                'variant_id' => $line['variant_id'],
                'description' => $variant?->fullName() ?? 'Producto inexistente',
                'code' => (string) ($variant->sku ?? $variant->product->sku ?? ''),
                'quantity' => $line['quantity'],
                'unit_price' => $price,
                'subtotal' => $calculator->lineSubtotal($line['quantity'], $price),
                'stock' => $withStock && $variant !== null ? $variant->stock : null,
                'available' => $variant !== null && $variant->active && $variant->product->active,
            ];
        }

        return $this->ticketMemo = $ticket;
    }

    public function ticketTotal(): string
    {
        return Money::sum(array_column($this->ticket(), 'subtotal'));
    }

    public function ticketUnits(): int
    {
        return array_sum(array_column($this->lines, 'quantity'));
    }

    public function customerName(): ?string
    {
        return $this->customerId === null ? null : Customer::query()->find($this->customerId)?->name;
    }

    /**
     * @return Collection<int, PaymentMethod>
     */
    public function paymentMethods(): Collection
    {
        return $this->methodsMemo ??= PaymentMethod::query()
            ->where('active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->keyBy('id');
    }

    /**
     * @return Collection<int, FinancingPlan>
     */
    public function financingPlans(): Collection
    {
        if (! Modules::enabled(Module::Installments)) {
            return collect();
        }

        return FinancingPlan::query()->where('active', true)->orderBy('position')->orderBy('id')->get();
    }

    public function canLeaveBalance(): bool
    {
        return Modules::enabled(Module::CurrentAccount);
    }

    /**
     * La regla de vencimiento de Cuotas propias pide elegir el mes de la primera cuota.
     */
    public function asksFirstMonth(): bool
    {
        return Modules::setting(Module::Installments, 'due_rule', InstallmentSchedule::RULE_FIXED_DAY) === InstallmentSchedule::RULE_FIXED_DAY;
    }

    /**
     * Meses para la primera cuota: el actual y los 11 siguientes.
     *
     * @return array<string, string>
     */
    public function monthOptions(): array
    {
        $options = [];

        foreach (range(0, 11) as $offset) {
            $month = now()->startOfMonth()->addMonthsNoOverflow($offset);
            $options[$month->format('Y-m')] = ucfirst($month->locale('es')->translatedFormat('F Y'));
        }

        return $options;
    }

    /**
     * Números del cobro con el mismo cálculo que la venta real.
     *
     * @return array{
     *     subtotal: string,
     *     adjustment: string,
     *     total: string,
     *     now: string,
     *     balance: string,
     *     rows: list<array{index: int, method: PaymentMethod|null, base: string, amount: string, covers_rest: bool}>,
     *     last_amount: string,
     *     tendered: string|null,
     *     change: string|null,
     *     suggestions: list<string>,
     *     error: string|null
     * }
     */
    public function checkoutSummary(): array
    {
        $subtotal = $this->ticketTotal();
        $error = null;
        $rows = [];
        $lastIndex = array_key_last($this->payments);
        $tender = app(Tender::class);

        foreach ($this->payments as $index => $payment) {
            $method = $this->paymentMethods()->get((int) ($payment['method_id'] ?? 0));
            $coversRest = ! $this->onAccount && $index === $lastIndex;
            $base = $coversRest ? null : $tender->parse($payment['base'] ?? null);

            if ($method === null && ($coversRest || $base !== null)) {
                $error ??= 'Elegí el medio de pago.';
            }

            if (! $coversRest && $base === null && ! $this->onAccount) {
                $error ??= 'Indicá cuánto cubre cada medio de pago, salvo el último.';
            }

            $rows[] = ['index' => $index, 'method' => $method, 'base' => $base ?? '0.00', 'covers_rest' => $coversRest];
        }

        // Sin saldo a cuenta, el último medio cubre lo que falta.
        if ($lastIndex !== null && $rows[$lastIndex]['covers_rest']) {
            $others = Money::sum(array_column(array_slice($rows, 0, -1), 'base'));
            $rest = Money::sub($subtotal, $others);

            if (Money::isNegative($rest)) {
                $error ??= 'Los montos de los medios de pago superan el total.';
                $rest = '0.00';
            }

            $rows[$lastIndex]['base'] = $rest;
        }

        if ($rows === [] && ! $this->onAccount) {
            $error ??= 'Elegí el medio de pago.';
        }

        $calculation = (new SaleCalculator)->calculate(
            array_map(fn (array $line): array => ['quantity' => $line['quantity'], 'unit_price' => $line['unit_price']], $this->ticket()),
            array_map(fn (array $row): array => ['adjustment_percent' => (string) ($row['method']->adjustment_percent ?? '0'), 'base_amount' => $row['base']], $rows),
        );

        foreach ($rows as $index => $row) {
            $rows[$index]['amount'] = $calculation['payments'][$index]['amount'] ?? '0.00';
        }

        if ($this->onAccount && $calculation['overpaid']) {
            $error ??= 'Lo que se cobra ahora supera el total.';
        }

        $lastAmount = $lastIndex === null ? '0.00' : $rows[$lastIndex]['amount'];
        $tendered = $tender->parse($this->tendered);

        return [
            'subtotal' => $calculation['subtotal'],
            'adjustment' => $calculation['adjustment'],
            'total' => $calculation['total'],
            'now' => Money::sub($calculation['total'], $calculation['balance']),
            'balance' => $calculation['balance'],
            'rows' => $rows,
            'last_amount' => $lastAmount,
            'tendered' => $tendered,
            'change' => $tendered === null ? null : $tender->change($tendered, $lastAmount),
            'suggestions' => $tender->suggestions($lastAmount),
            'error' => $error,
        ];
    }

    /**
     * @param  array{rows: list<array{method: PaymentMethod|null, base: string}>}  $summary
     * @return array<string, mixed>
     */
    private function salePayload(array $summary): array
    {
        $payments = [];

        foreach ($summary['rows'] as $row) {
            // A cuenta, un medio sin monto no cobra nada: no se registra.
            if ($this->onAccount && ($row['method'] === null || Money::compare($row['base'], '0') === 0)) {
                continue;
            }

            $payments[] = ['payment_method_id' => $row['method']?->getKey(), 'base_amount' => $row['base']];
        }

        return [
            'channel' => SaleChannel::Counter,
            'customer_id' => $this->customerId,
            'date' => now()->toDateString(),
            'due_date' => $this->onAccount ? $this->dueDate : null,
            'financing_plan_id' => $this->onAccount ? $this->financingPlanId : null,
            'first_month' => $this->onAccount ? $this->firstMonth : null,
            'items' => array_map(fn (array $line): array => [
                'variant_id' => $line['variant_id'],
                'quantity' => $line['quantity'],
            ], $this->lines),
            'payments' => $payments,
        ];
    }

    /**
     * «3*REM-1» → [3, 'REM-1']; sin multiplicador, una unidad.
     *
     * @return array{0: int, 1: string}
     */
    private function parseEntry(string $entry): array
    {
        $entry = trim($entry);

        if (preg_match('/^(\d{1,4})\s*\*\s*(.*)$/u', $entry, $matches) === 1) {
            return [$this->clampQuantity((int) $matches[1]), trim($matches[2])];
        }

        return [1, $entry];
    }

    /**
     * @return list<array{id: int, label: string, code: string, price: string, stock: int|null}>
     */
    private function search(string $term): array
    {
        $withStock = Modules::enabled(Module::Stock);
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return ProductVariant::query()
            ->with('product')
            ->sellable()
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhereHas('product', fn (Builder $product) => $product
                    ->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)))
            ->orderBy('product_id')
            ->orderBy('position')
            ->orderBy('id')
            ->limit(self::MAX_RESULTS)
            ->get()
            ->map(fn (ProductVariant $variant): array => [
                'id' => $variant->getKey(),
                'label' => $variant->fullName(),
                'code' => (string) ($variant->sku ?? $variant->product->sku ?? ''),
                'price' => Money::from($variant->effectivePrice()),
                'stock' => $withStock ? $variant->stock : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function searchCustomers(string $search): array
    {
        return Customer::query()
            ->where('active', true)
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('document_number', 'like', "%{$search}%"),
            ))
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->all();
    }

    private function resetCheckout(): void
    {
        $this->checkingOut = false;
        $this->payments = [];
        $this->tendered = '';
        $this->onAccount = false;
        $this->dueDate = now()->addDays(30)->toDateString();
        $this->financingPlanId = null;
        $this->firstMonth = now()->addMonthNoOverflow()->format('Y-m');
    }

    private function clampQuantity(int $quantity): int
    {
        return max(1, min(self::MAX_QUANTITY, $quantity));
    }

    private function forgetTicket(): void
    {
        $this->ticketMemo = null;
    }
}
