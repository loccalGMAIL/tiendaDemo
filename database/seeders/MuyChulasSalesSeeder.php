<?php

namespace Database\Seeders;

use App\Actions\Sales\CancelSale;
use App\Actions\Sales\CreateSale;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use App\Services\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Ventas de prueba para Muy Chulas, sobre los productos de `MuyChulasSeeder`.
 *
 * Usa las acciones reales (CreateSale, CancelSale): los totales, el stock, los
 * movimientos y la actividad son los del sistema. No hay azar: dos bases limpias
 * quedan iguales. Reparte las ventas en los últimos tres meses, con medios de pago
 * variados, algunos clientes y una venta anulada. Las cantidades no pasan el stock
 * inicial de cada producto.
 *
 * No corre en producción, si la instalación ya tiene ventas ni si faltan los productos.
 */
class MuyChulasSalesSeeder extends Seeder
{
    /** @var array<string, Product> */
    private array $products = [];

    /** @var list<Customer> */
    private array $customers = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Las ventas de prueba de Muy Chulas no se cargan en producción.');

            return;
        }

        if (Sale::query()->exists()) {
            $this->command?->info('Ya hay ventas cargadas: no se agregan las de prueba para no pisar datos en uso.');

            return;
        }

        $admin = User::query()
            ->where('role', Role::Admin->value)
            ->where('active', true)
            ->orderByDesc('is_default_seller')
            ->orderBy('id')
            ->first();

        if ($admin === null) {
            $this->command?->error('No hay un administrador activo: corré primero los seeders iniciales.');

            return;
        }

        $methods = PaymentMethod::query()->where('active', true)->pluck('id', 'name');

        if ($methods->isEmpty()) {
            $this->command?->error('No hay medios de pago: corré primero los seeders iniciales.');

            return;
        }

        $missing = $this->missingProducts();

        if ($missing !== []) {
            $this->command?->error('Faltan productos de Muy Chulas ('.implode(', ', $missing).'): corré primero MuyChulasSeeder.');

            return;
        }

        $this->seedCustomers();
        $this->seedSales($admin, $methods);

        $this->command?->info('Ventas de Muy Chulas cargadas: '.Sale::query()->count().' ventas.');
    }

    /**
     * Carga los productos del plan por nombre y devuelve los que no se encontraron.
     *
     * @return list<string>
     */
    private function missingProducts(): array
    {
        $names = [];

        foreach ($this->salePlan() as $plan) {
            foreach ($plan['items'] as [$name]) {
                $names[$name] = $name;
            }
        }

        $found = Product::query()->whereIn('name', $names)->get()->keyBy('name');

        foreach ($names as $name) {
            if ($found->has($name)) {
                $this->products[$name] = $found->get($name);
            }
        }

        return array_values(array_diff($names, array_keys($this->products)));
    }

    private function seedCustomers(): void
    {
        $customers = [
            ['Camila Ledesma', '36211458', '3442 51-2201', 'camila.ledesma@example.test', 'Concepción del Uruguay'],
            ['Agustina Romero', '34987123', '3442 51-2202', 'agustina.romero@example.test', 'Concepción del Uruguay'],
            ['Lucía Fernández', '38456321', '3442 51-2203', 'lucia.fernandez@example.test', 'Colón'],
            ['Florencia Acosta', '33654987', '3442 51-2204', 'florencia.acosta@example.test', 'Concepción del Uruguay'],
            ['Daniela Benítez', '37123654', '3442 51-2205', 'daniela.benitez@example.test', 'Gualeguaychú'],
        ];

        foreach ($customers as [$name, $document, $phone, $email, $city]) {
            $this->customers[] = Customer::query()->create([
                'name' => $name,
                'document_number' => $document,
                'phone' => $phone,
                'email' => $email,
                'city' => $city,
                'active' => true,
            ]);
        }
    }

    /**
     * @param  Collection<string, int>  $methods
     */
    private function seedSales(User $admin, Collection $methods): void
    {
        foreach ($this->salePlan() as $plan) {
            $items = [];
            $subtotal = '0.00';

            foreach ($plan['items'] as [$name, $variantIndex, $quantity]) {
                $variant = $this->variantFor($name, $variantIndex);

                $items[] = ['variant_id' => $variant->getKey(), 'quantity' => $quantity];
                $subtotal = Money::add($subtotal, Money::mul($this->products[$name]->effectivePrice($variant), $quantity));
            }

            $sale = app(CreateSale::class)->handle($admin, [
                'customer_id' => isset($plan['customer']) ? $this->customers[$plan['customer']]->getKey() : null,
                'date' => $this->dateFor($plan['month'], $plan['day']),
                'items' => $items,
                'payments' => $this->paymentsFor($plan['pay'], $methods->all(), $subtotal),
            ]);

            if (isset($plan['cancel'])) {
                app(CancelSale::class)->handle($sale, $admin, $plan['cancel']);
            }
        }
    }

    /**
     * Un cobro lleva todo el subtotal; dos cobros se reparten mitad y mitad.
     *
     * @param  list<string>  $methodNames
     * @param  array<string, int>  $methodIds
     * @return list<array{payment_method_id: int, base_amount?: string}>
     */
    private function paymentsFor(array $methodNames, array $methodIds, string $subtotal): array
    {
        if (count($methodNames) === 1) {
            return [['payment_method_id' => $methodIds[$methodNames[0]]]];
        }

        $first = Money::percentOf($subtotal, '50');

        return [
            ['payment_method_id' => $methodIds[$methodNames[0]], 'base_amount' => $first],
            ['payment_method_id' => $methodIds[$methodNames[1]], 'base_amount' => Money::sub($subtotal, $first)],
        ];
    }

    /**
     * Variante vendible del producto en esa posición (la implícita si no hay variantes con nombre).
     */
    private function variantFor(string $name, int $index): ProductVariant
    {
        $variants = $this->products[$name]->unsetRelation('variants')->load('variants')->sellableVariants()->sortBy('position')->values();

        return $variants->get($index % $variants->count());
    }

    /**
     * Fecha dentro del mes (0 = el actual); el día se ajusta para no caer en el futuro.
     */
    private function dateFor(int $monthsBack, int $day): string
    {
        $first = now()->startOfMonth()->subMonthsNoOverflow($monthsBack);
        $last = $monthsBack === 0 ? now()->day - 1 : $first->daysInMonth - 1;

        return $first->copy()->addDays(min($day, $last))->toDateString();
    }

    /**
     * Cada ítem es [nombre del producto, posición de la variante, cantidad]. Por producto
     * se vende menos que su stock inicial (6, o 2 en Blazer y Chaqueta de paño corta).
     *
     * @return list<array{
     *     month: int, day: int, customer?: int, pay: list<string>,
     *     items: list<array{0: string, 1: int, 2: int}>, cancel?: string
     * }>
     */
    private function salePlan(): array
    {
        return [
            ['month' => 0, 'day' => 0, 'pay' => ['Efectivo'], 'items' => [['Remera básica', 0, 2]]],
            ['month' => 0, 'day' => 1, 'customer' => 0, 'pay' => ['Tarjeta 3 cuotas'], 'items' => [['Jean', 1, 1], ['Remera estampada', 0, 1]]],
            ['month' => 0, 'day' => 2, 'pay' => ['Transferencia'], 'items' => [['Musculosa de algodón', 0, 2]]],
            ['month' => 0, 'day' => 3, 'customer' => 1, 'pay' => ['Tarjeta 6 cuotas'], 'items' => [['Campera puffer importada', 0, 1]]],
            ['month' => 0, 'day' => 4, 'pay' => ['Transferencia', 'Efectivo'], 'items' => [['Buzo de darlon', 0, 1], ['Blusa', 0, 2]]],
            ['month' => 0, 'day' => 5, 'pay' => ['Débito'], 'items' => [['Remera térmica', 1, 2]]],
            ['month' => 0, 'day' => 6, 'customer' => 2, 'pay' => ['Tarjeta 1 cuota'], 'items' => [['Blazer', 0, 1]]],
            ['month' => 0, 'day' => 7, 'pay' => ['Efectivo'], 'items' => [['Pantalón sastrero', 0, 1]], 'cancel' => 'La clienta lo devolvió'],
            ['month' => 0, 'day' => 8, 'pay' => ['Efectivo', 'Débito'], 'items' => [['Remera básica', 3, 1], ['Short de jean', 0, 1]]],
            ['month' => 0, 'day' => 9, 'customer' => 3, 'pay' => ['Tarjeta 3 cuotas'], 'items' => [['Conjunto de darlon', 0, 1]]],
            ['month' => 1, 'day' => 2, 'pay' => ['Tarjeta 3 cuotas'], 'items' => [['Campera puffer importada', 1, 1]]],
            ['month' => 1, 'day' => 4, 'customer' => 0, 'pay' => ['Transferencia'], 'items' => [['Remera básica', 2, 2], ['Bikini', 0, 1]]],
            ['month' => 1, 'day' => 6, 'pay' => ['Débito'], 'items' => [['Pantalón de darlon', 0, 2]]],
            ['month' => 1, 'day' => 9, 'pay' => ['Efectivo'], 'items' => [['Remera rayada', 0, 1], ['Blusa amplia', 0, 1]]],
            ['month' => 1, 'day' => 12, 'customer' => 4, 'pay' => ['Tarjeta 6 cuotas'], 'items' => [['Saco de paño', 0, 1], ['Jean', 2, 1]]],
            ['month' => 1, 'day' => 15, 'pay' => ['Tarjeta 1 cuota'], 'items' => [['Pantalón de darlon', 1, 1]], 'cancel' => 'Error de carga: se cobró de más'],
            ['month' => 1, 'day' => 19, 'pay' => ['Transferencia'], 'items' => [['Chaqueta de paño corta', 0, 1]]],
            ['month' => 1, 'day' => 22, 'pay' => ['Efectivo', 'Transferencia'], 'items' => [['Vestido de lentejuelas', 0, 1], ['Musculosa amplia', 1, 1]]],
            ['month' => 2, 'day' => 5, 'pay' => ['Efectivo'], 'items' => [['Remera estampada', 0, 2]]],
            ['month' => 2, 'day' => 11, 'customer' => 1, 'pay' => ['Débito'], 'items' => [['Bomber gamuzada', 0, 1]]],
            ['month' => 2, 'day' => 17, 'pay' => ['Tarjeta 3 cuotas'], 'items' => [['Pantalón rayado rosa', 0, 1]]],
            ['month' => 2, 'day' => 24, 'customer' => 2, 'pay' => ['Efectivo'], 'items' => [['Chaleco gamulán', 0, 1], ['Pollera short', 1, 1]]],
        ];
    }
}
