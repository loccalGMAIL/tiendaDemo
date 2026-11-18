<?php

namespace App\Actions\Sales;

use App\Enums\ActivityEvent;
use App\Enums\Module;
use App\Enums\SaleStatus;
use App\Events\SaleCreated;
use App\Facades\Modules;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Money;
use App\Services\SaleCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Confirma una venta: ítems con precio y costo copiados, cobros con el recargo o
 * descuento de su medio de pago, y la venta nace pagada. Con el módulo Cuenta
 * corriente, lo que no se cobra en el acto queda a cuenta del cliente (la venta nace
 * con saldo y el módulo arma su vencimiento al escuchar el evento).
 */
class CreateSale
{
    public function __construct(
        private SaleCalculator $calculator,
        private ActivityLogger $logger,
    ) {}

    /**
     * @param  array{
     *     seller_id?: int|string|null,
     *     customer_id?: int|string|null,
     *     date?: string|null,
     *     due_date?: string|null,
     *     notes?: string|null,
     *     items?: array<int, array{variant_id?: int|string|null, quantity?: int|string|null, unit_price?: string|int|float|null}>,
     *     payments?: array<int, array{payment_method_id?: int|string|null, base_amount?: string|int|float|null}>
     * }  $data
     *
     * @throws ValidationException
     */
    public function handle(User $actor, array $data): Sale
    {
        return DB::transaction(fn (): Sale => $this->create($actor, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function create(User $actor, array $data): Sale
    {
        $seller = $this->resolveSeller($actor, $data);
        $customer = $this->resolveCustomer($data);
        $items = $this->resolveItems($actor, $data);
        $payments = $this->resolvePayments($data);

        $calculation = $this->calculator->calculate(
            array_map(fn (array $item): array => ['quantity' => $item['quantity'], 'unit_price' => $item['unit_price']], $items),
            array_map(fn (array $payment): array => [
                'adjustment_percent' => $payment['method']->adjustment_percent,
                'base_amount' => $payment['base_amount'],
            ], $payments),
        );

        $date = isset($data['date']) && filled($data['date'])
            ? Carbon::parse($data['date'])->toDateString()
            : now()->toDateString();

        // Con Cuenta corriente lo que no se cobra en el acto queda a cuenta del cliente;
        // sin el módulo toda venta es de contado y los cobros suman exactamente el subtotal.
        $financed = $this->resolveFinancedAmount($calculation, $customer, $data, $date);

        $sale = new Sale([
            'customer_id' => $customer?->getKey(),
            'seller_id' => $seller->getKey(),
            'date' => $date,
            'subtotal' => $calculation['subtotal'],
            'adjustment' => $calculation['adjustment'],
            'total' => $calculation['total'],
            'total_cost' => Money::sum(array_map(
                fn (array $item): string => Money::mul($item['unit_cost'], $item['quantity']),
                $items,
            )),
            // Lo cobrado en el acto (base + ajuste de cada medio) y lo que queda a cuenta.
            'down_payment' => Money::sub($calculation['total'], $financed),
            'financed_amount' => $financed,
            'first_due_date' => Money::compare($financed, '0') > 0 ? Carbon::parse($data['due_date'])->toDateString() : null,
            'status' => Money::compare($financed, '0') > 0 ? SaleStatus::Active : SaleStatus::Paid,
            'notes' => $data['notes'] ?? null,
        ]);
        $sale->save();

        foreach ($items as $item) {
            $sale->items()->create([
                'product_id' => $item['product_id'],
                'product_variant_id' => $item['variant_id'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $this->calculator->lineSubtotal($item['quantity'], $item['unit_price']),
            ]);
        }

        foreach ($payments as $index => $payment) {
            $line = $calculation['payments'][$index];

            $sale->payments()->create([
                'customer_id' => $customer?->getKey(),
                'date' => $date,
                'payment_method_id' => $payment['method']->getKey(),
                // Se copian del medio: cambiarlo después no afecta lo ya cobrado.
                'installments' => $payment['method']->installments,
                'adjustment_percent' => $payment['method']->adjustment_percent,
                'base_amount' => $line['base_amount'],
                'adjustment_amount' => $line['adjustment_amount'],
                'amount' => $line['amount'],
                'collected_by' => $actor->getKey(),
            ]);
        }

        $this->logger->log(
            ActivityEvent::Created,
            "Alta de venta {$sale->number()}: total {$sale->total}",
            $sale,
            ['total' => $sale->total, 'items' => count($items), 'payments' => count($payments)],
            $actor,
        );

        $sale->load(['items', 'payments']);

        // Dentro de la transacción: si un módulo rechaza la venta (p. ej. Stock en modo
        // bloquear), se revierte todo.
        SaleCreated::dispatch($sale, $actor);

        return $sale;
    }

    /**
     * Lo que queda a cuenta del cliente (cero en una venta de contado). Con Cuenta corriente
     * los cobros pueden cubrir menos que el subtotal; el saldo exige cliente y vencimiento.
     *
     * @param  array{subtotal: string, balance: string, balanced: bool, overpaid: bool, payments: list<array{base_amount: string}>}  $calculation
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function resolveFinancedAmount(array $calculation, ?Customer $customer, array $data, string $date): string
    {
        $paid = Money::sum(array_column($calculation['payments'], 'base_amount'));

        if (! Modules::enabled(Module::CurrentAccount)) {
            if (! $calculation['balanced']) {
                throw ValidationException::withMessages([
                    'payments' => "La suma de los cobros ({$paid}) no coincide con el subtotal ({$calculation['subtotal']}).",
                ]);
            }

            return '0.00';
        }

        if ($calculation['overpaid']) {
            throw ValidationException::withMessages([
                'payments' => "La suma de los cobros ({$paid}) supera el subtotal ({$calculation['subtotal']}).",
            ]);
        }

        if (Money::compare($calculation['balance'], '0') === 0) {
            return '0.00';
        }

        if ($customer === null) {
            throw ValidationException::withMessages(['customer_id' => 'Una venta con saldo a cuenta necesita un cliente.']);
        }

        if (blank($data['due_date'] ?? null)) {
            throw ValidationException::withMessages(['due_date' => 'Indicá el vencimiento del saldo.']);
        }

        try {
            $due = Carbon::parse($data['due_date'])->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['due_date' => 'El vencimiento no es una fecha válida.']);
        }

        if ($due < $date) {
            throw ValidationException::withMessages(['due_date' => 'El vencimiento no puede ser anterior a la fecha de la venta.']);
        }

        return $calculation['balance'];
    }

    /**
     * Un vendedor siempre carga a su nombre; un administrador elige (por defecto, él mismo).
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveSeller(User $actor, array $data): User
    {
        if ($actor->isSeller()) {
            return $actor;
        }

        $sellerId = filled($data['seller_id'] ?? null) ? (int) $data['seller_id'] : $actor->getKey();

        $seller = User::query()
            ->visibleToClient()
            ->where('active', true)
            ->find($sellerId);

        if ($seller === null) {
            throw ValidationException::withMessages(['seller_id' => 'El vendedor indicado no es válido.']);
        }

        return $seller;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomer(array $data): ?Customer
    {
        if (blank($data['customer_id'] ?? null)) {
            return null;
        }

        $customer = Customer::query()->where('active', true)->find((int) $data['customer_id']);

        if ($customer === null) {
            throw ValidationException::withMessages(['customer_id' => 'El cliente indicado no es válido.']);
        }

        return $customer;
    }

    /**
     * Valida los ítems y copia precio, costo y descripción del catálogo.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{product_id: int, variant_id: int, description: string, quantity: int, unit_cost: string, unit_price: string}>
     */
    private function resolveItems(User $actor, array $data): array
    {
        $rawItems = array_values($data['items'] ?? []);

        if ($rawItems === []) {
            throw ValidationException::withMessages(['items' => 'La venta necesita al menos un ítem.']);
        }

        $variantIds = collect($rawItems)
            ->pluck('variant_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();

        $variants = ProductVariant::query()->whereIn('id', $variantIds)->get()->keyBy('id');

        // Se bloquean los productos siempre en el mismo orden (por id).
        $products = Product::query()
            ->whereIn('id', $variants->pluck('product_id')->unique()->sort()->values())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $errors = [];
        $items = [];

        foreach ($rawItems as $index => $raw) {
            $variant = $variants->get((int) ($raw['variant_id'] ?? 0));
            $product = $variant === null ? null : $products->get($variant->product_id);

            if ($variant === null || $product === null) {
                $errors["items.$index.variant_id"] = 'El producto elegido no existe.';

                continue;
            }

            if (! $variant->active || ! $product->active) {
                $errors["items.$index.variant_id"] = "El producto «{$product->name}» está inactivo.";

                continue;
            }

            $quantity = filter_var($raw['quantity'] ?? null, FILTER_VALIDATE_INT);

            if ($quantity === false || $quantity < 1) {
                $errors["items.$index.quantity"] = 'La cantidad tiene que ser 1 o más.';

                continue;
            }

            $catalogPrice = $product->effectivePrice($variant);
            $unitPrice = $catalogPrice;

            if (filled($raw['unit_price'] ?? null)) {
                if (! is_numeric($raw['unit_price']) || Money::isNegative(Money::from($raw['unit_price']))) {
                    $errors["items.$index.unit_price"] = 'El precio no puede ser negativo.';

                    continue;
                }

                $unitPrice = Money::from($raw['unit_price']);

                if (! $actor->isAdmin() && Money::compare($unitPrice, $catalogPrice) !== 0) {
                    $errors["items.$index.unit_price"] = 'Solo un administrador puede cambiar el precio.';

                    continue;
                }
            }

            $items[] = [
                'product_id' => $product->getKey(),
                'variant_id' => $variant->getKey(),
                'description' => $variant->isImplicit() ? $product->name : "{$product->name} — {$variant->name}",
                'quantity' => $quantity,
                'unit_cost' => $product->cost,
                'unit_price' => $unitPrice,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{method: PaymentMethod, base_amount: string|null}>
     */
    private function resolvePayments(array $data): array
    {
        $rawPayments = array_values($data['payments'] ?? []);

        // Con Cuenta corriente la venta puede quedar entera a cuenta (sin cobros).
        if ($rawPayments === []) {
            if (Modules::enabled(Module::CurrentAccount)) {
                return [];
            }

            throw ValidationException::withMessages(['payments' => 'Indicá al menos un medio de pago.']);
        }

        $methods = PaymentMethod::query()
            ->whereIn('id', collect($rawPayments)->pluck('payment_method_id')->filter()->map(fn (mixed $id): int => (int) $id))
            ->get()
            ->keyBy('id');

        $errors = [];
        $payments = [];

        foreach ($rawPayments as $index => $raw) {
            $method = $methods->get((int) ($raw['payment_method_id'] ?? 0));

            if ($method === null || ! $method->active) {
                $errors["payments.$index.payment_method_id"] = 'El medio de pago elegido no es válido.';

                continue;
            }

            $base = $raw['base_amount'] ?? null;

            if (blank($base)) {
                // Con un solo cobro la base es el subtotal; con varios hay que indicarla.
                if (count($rawPayments) > 1) {
                    $errors["payments.$index.base_amount"] = 'Indicá el monto de este cobro.';

                    continue;
                }

                $payments[] = ['method' => $method, 'base_amount' => null];

                continue;
            }

            if (! is_numeric($base) || Money::isNegative(Money::from($base))) {
                $errors["payments.$index.base_amount"] = 'El monto no puede ser negativo.';

                continue;
            }

            $payments[] = ['method' => $method, 'base_amount' => Money::from($base)];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $payments;
    }
}
