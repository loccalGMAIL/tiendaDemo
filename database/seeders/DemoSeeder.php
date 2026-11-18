<?php

namespace Database\Seeders;

use App\Actions\Accounts\CancelPayment;
use App\Actions\Accounts\CollectPayment;
use App\Actions\Products\CreateProduct;
use App\Actions\Sales\CancelSale;
use App\Actions\Sales\CreateSale;
use App\Actions\Stock\CountStock;
use App\Actions\Users\CreateUser;
use App\Actions\Variants\GenerateVariants;
use App\Enums\Module;
use App\Enums\Role;
use App\Facades\Modules;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use App\Services\Money;
use App\Services\Settings;
use App\Services\SiteContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Datos de demostración (rubro indumentaria) para mostrar el sistema.
 *
 * Usa las acciones reales (CreateProduct, GenerateVariants, CountStock, CreateSale,
 * CancelSale): los totales, el stock, los movimientos y la actividad son los del
 * sistema. Solo arma lo de los módulos encendidos, así que corre después de
 * `modules:enable`. No hay azar: dos bases limpias quedan iguales.
 *
 * No corre en producción ni si la instalación ya tiene ventas.
 */
class DemoSeeder extends Seeder
{
    /** Cantidad de stock que queda por variante si el producto no indica otra. */
    private const DEFAULT_LEFTOVER = 8;

    /** Stock mínimo de todas las variantes (con el módulo Stock). */
    private const MIN_STOCK = 3;

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, Product> */
    protected array $products = [];

    /** @var list<Customer> */
    private array $customers = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('La demostración no se carga en producción: crea usuarios de prueba.');

            return;
        }

        if (Sale::query()->exists()) {
            $this->command?->info('Ya hay ventas cargadas: no se agrega la demostración para no pisar datos en uso.');

            return;
        }

        $this->seedUsers();
        $this->seedSite();
        $this->seedVariantAttributes();
        $this->seedProducts();
        $this->seedVariants();
        $this->seedStock();
        $this->seedCustomers();
        $this->seedSales();
        $this->seedCollections();
        $this->seedCatalog();

        $this->command?->info('Demostración cargada: '.Product::query()->count().' productos y '.Sale::query()->count().' ventas.');
    }

    // --- Usuarios y sitio -----------------------------------------------------

    private function seedUsers(): void
    {
        $password = (string) config('platform.demo_password');

        $admin = User::query()
            ->where('role', Role::Admin->value)
            ->where('active', true)
            ->orderByDesc('is_default_seller')
            ->orderBy('id')
            ->first();

        $this->users['admin'] = $admin ?? app(CreateUser::class)->handle([
            'name' => 'Administrador',
            'email' => 'admin@demo.test',
            'password' => $password,
            'role' => Role::Admin->value,
            'active' => true,
            'is_default_seller' => true,
        ]);

        foreach (['ana' => ['Ana Pérez', 'ana@demo.test'], 'luis' => ['Luis Gómez', 'luis@demo.test']] as $key => [$name, $email]) {
            $this->users[$key] = User::query()->where('email', $email)->first() ?? app(CreateUser::class)->handle([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => Role::Seller->value,
                'active' => true,
            ]);
        }
    }

    protected function seedSite(): void
    {
        if (app(Settings::class)->get('site') !== null) {
            return;
        }

        $site = app(SiteContent::class);

        $site->save([...$site->defaults(), ...$this->siteDefinition()]);
    }

    /**
     * Contenido del sitio público de la demostración.
     *
     * @return array<string, mixed>
     */
    protected function siteDefinition(): array
    {
        return [
            'name' => 'Casa Lila',
            'tagline' => 'Ropa con historia',
            'hero_title' => 'Vestite como te sentís',
            'hero_subtitle' => 'Colecciones nuevas todas las semanas, con envíos a todo el país.',
            'value_title' => 'Por qué elegirnos',
            'value_items' => [
                ['title' => 'Calidad que dura', 'text' => 'Telas elegidas una por una y terminaciones cuidadas.'],
                ['title' => 'Cambios sin vueltas', 'text' => 'Si no te queda bien, lo cambiás por otro talle o color.'],
                ['title' => 'Atención personal', 'text' => 'Te asesoramos por WhatsApp o en el local.'],
            ],
            'faq_items' => [
                ['question' => '¿Hacen envíos?', 'answer' => 'Sí, a todo el país. El costo se calcula al coordinar el pedido.'],
                ['question' => '¿Qué medios de pago aceptan?', 'answer' => 'Efectivo con descuento, transferencia, débito y tarjetas en cuotas.'],
                ['question' => '¿Puedo cambiar una prenda?', 'answer' => 'Sí, dentro de los 30 días con la etiqueta puesta.'],
            ],
            'phone' => '011 4000-1234',
            'whatsapp' => '+54 9 11 5555-1234',
            'email' => 'hola@casalila.example',
            'address' => 'Av. Corrientes 1234, CABA',
            'instagram' => 'https://instagram.com/casalila.example',
        ];
    }

    // --- Productos --------------------------------------------------------------

    protected function seedVariantAttributes(): void
    {
        if (! Modules::enabled(Module::Variants) || Modules::setting(Module::Variants, 'attributes', []) !== []) {
            return;
        }

        Modules::updateSettings(Module::Variants, ['attributes' => $this->variantAttributes()]);
    }

    /**
     * @return list<array{name: string, values: list<string>}>
     */
    protected function variantAttributes(): array
    {
        return [
            ['name' => 'Talle', 'values' => ['S', 'M', 'L', 'XL']],
            ['name' => 'Color', 'values' => ['Negro', 'Blanco', 'Azul', 'Gris']],
        ];
    }

    /**
     * @return list<string>
     */
    protected function categoryNames(): array
    {
        return ['Remeras', 'Camisas', 'Pantalones', 'Abrigos', 'Accesorios'];
    }

    protected function seedProducts(): void
    {
        $categories = [];

        foreach ($this->categoryNames() as $position => $name) {
            $categories[$name] = Category::query()->create(['name' => $name, 'position' => $position, 'active' => true]);
        }

        $withCatalog = Modules::enabled(Module::Catalog);

        foreach ($this->productDefinitions() as $key => $definition) {
            $data = [
                'name' => $definition['name'],
                'brand' => $definition['brand'],
                'description' => $definition['description'],
                'category_id' => $categories[$definition['category']]->getKey(),
                'price' => $definition['price'],
                'cost' => $definition['cost'],
                'active' => true,
            ];

            if ($withCatalog) {
                $data += [
                    'is_published' => $definition['published'],
                    'is_featured' => $definition['featured'],
                    'show_price' => $definition['show_price'],
                ];
            }

            $this->products[$key] = app(CreateProduct::class)->handle($data);
        }
    }

    private function seedVariants(): void
    {
        if (! Modules::enabled(Module::Variants)) {
            return;
        }

        foreach ($this->productDefinitions() as $key => $definition) {
            if ($definition['variants'] === []) {
                continue;
            }

            $product = $this->products[$key];

            app(GenerateVariants::class)->handle($product, $definition['variants'], $this->users['admin']);

            foreach ($definition['variant_prices'] as $index => $price) {
                $this->variantsOf($product)->get($index)?->update(['price' => $price]);
            }
        }
    }

    /**
     * Variantes vendibles del producto, en orden (la implícita si no hay variantes con nombre).
     *
     * @return Collection<int, ProductVariant>
     */
    private function variantsOf(Product $product): Collection
    {
        return $product->unsetRelation('variants')->load('variants')->sellableVariants()->sortBy('position')->values();
    }

    // --- Stock ------------------------------------------------------------------

    /**
     * Carga el stock inicial: lo que se va a vender más lo que debe quedar, así
     * las ventas de la demostración nunca dejan existencias negativas.
     */
    private function seedStock(): void
    {
        if (! Modules::enabled(Module::Stock)) {
            return;
        }

        $demand = [];

        foreach ($this->salePlan() as $sale) {
            foreach ($sale['items'] as [$productKey, $variantIndex, $quantity]) {
                $variant = $this->variantFor($productKey, $variantIndex);
                $demand[$variant->getKey()] = ($demand[$variant->getKey()] ?? 0) + $quantity;
            }
        }

        $counts = [];
        $minimums = [];

        foreach ($this->productDefinitions() as $key => $definition) {
            foreach ($this->variantsOf($this->products[$key]) as $index => $variant) {
                $leftover = $definition['leftover'][$index] ?? self::DEFAULT_LEFTOVER;

                $counts[$variant->getKey()] = ($demand[$variant->getKey()] ?? 0) + $leftover;
                $minimums[$variant->getKey()] = self::MIN_STOCK;
            }
        }

        app(CountStock::class)->handle($counts, 'Stock inicial', $this->users['admin'], $minimums);
    }

    // --- Clientes y ventas --------------------------------------------------------

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    protected function customerDefinitions(): array
    {
        return [
            ['Marcela Rojas', '27345678', '011 5555-0101', 'marcela@example.test', 'Palermo'],
            ['Jorge Díaz', '30123456', '011 5555-0102', 'jorge@example.test', 'Caballito'],
            ['Sofía Benítez', '33456789', '011 5555-0103', 'sofia@example.test', 'Belgrano'],
            ['Tomás Aguirre', '35987654', '011 5555-0104', 'tomas@example.test', 'Almagro'],
            ['Valeria Costa', '28765432', '011 5555-0105', 'valeria@example.test', 'Villa Urquiza'],
            ['Nicolás Sosa', '36111222', '011 5555-0106', 'nicolas@example.test', 'Flores'],
        ];
    }

    protected function seedCustomers(): void
    {
        foreach ($this->customerDefinitions() as [$name, $document, $phone, $email, $city]) {
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

    private function seedSales(): void
    {
        $methods = PaymentMethod::query()->pluck('id', 'name');

        foreach ($this->salePlan() as $plan) {
            $items = [];
            $subtotal = '0.00';

            foreach ($plan['items'] as [$productKey, $variantIndex, $quantity]) {
                $variant = $this->variantFor($productKey, $variantIndex);

                $items[] = ['variant_id' => $variant->getKey(), 'quantity' => $quantity];
                $subtotal = Money::add($subtotal, Money::mul($this->products[$productKey]->effectivePrice($variant), $quantity));
            }

            $actor = $this->users[$plan['by']];
            $date = $this->dateFor($plan['month'], $plan['day']);

            $data = [
                'customer_id' => isset($plan['customer']) ? $this->customers[$plan['customer']]->getKey() : null,
                'date' => $date,
                'items' => $items,
                'payments' => $this->paymentsFor($plan['pay'], $methods->all(), $subtotal),
            ];

            // Con Cuenta corriente, algunas ventas dejan saldo a cuenta (algunas ya vencidas).
            if (isset($plan['on_account'], $plan['customer']) && Modules::enabled(Module::CurrentAccount)) {
                $data['payments'] = $plan['on_account']['paid'] > 0
                    ? [['payment_method_id' => $methods[$plan['pay'][0]], 'base_amount' => Money::percentOf($subtotal, (string) $plan['on_account']['paid'])]]
                    : [];
                $data['due_date'] = Carbon::parse($date)->addDays($plan['on_account']['due_days'])->toDateString();
            }

            $sale = app(CreateSale::class)->handle($actor, $data);

            if (isset($plan['cancel'])) {
                app(CancelSale::class)->handle($sale, $this->users['admin'], $plan['cancel']);
            }
        }
    }

    /**
     * Con Cuenta corriente: un cobro parcial, un cliente que paga todo (su venta se salda) y un
     * cobro anulado por error de carga. Cada cobro usa lo que el cliente debe en ese momento;
     * un cliente sin deuda (otra demostración sin ventas a cuenta) se saltea.
     */
    private function seedCollections(): void
    {
        if (! Modules::enabled(Module::CurrentAccount)) {
            return;
        }

        $methods = PaymentMethod::query()->pluck('id', 'name');

        $this->collectFrom(0, '40', $methods['Transferencia'], $this->users['ana']);
        $this->collectFrom(4, '100', $methods['Efectivo'], $this->users['luis']);

        $mistaken = $this->collectFrom(3, '25', $methods['Débito'], $this->users['admin']);

        if ($mistaken !== null) {
            app(CancelPayment::class)->handle($mistaken, $this->users['admin'], 'Error de carga: era de otro cliente');
        }
    }

    /**
     * Cobra un porcentaje de la deuda del cliente (el índice es el de seedCustomers()).
     */
    private function collectFrom(int $customer, string $percent, int $methodId, User $actor): ?Payment
    {
        $debt = $this->customers[$customer]->accountBalance();
        $base = Money::percentOf($debt, $percent);

        if (Money::compare($base, '0') <= 0) {
            return null;
        }

        return app(CollectPayment::class)->handle($actor, [
            'customer_id' => $this->customers[$customer]->getKey(),
            'payment_method_id' => $methodId,
            'base_amount' => $base,
            'date' => now()->toDateString(),
        ]);
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

    private function variantFor(string $productKey, int $index): ProductVariant
    {
        $variants = $this->variantsOf($this->products[$productKey]);

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

    // --- Catálogo web -------------------------------------------------------------

    protected function seedCatalog(): void
    {
        if (! Modules::enabled(Module::Catalog) || ! extension_loaded('gd')) {
            return;
        }

        $disk = Storage::disk('public');
        $disk->makeDirectory('products');

        foreach ($this->productDefinitions() as $key => $definition) {
            $product = $this->products[$key];
            $path = 'products/demo-'.$product->slug.'.png';

            $disk->put($path, $this->placeholderImage($definition['color'], mb_strtoupper(mb_substr($definition['name'], 0, 1))));
            $product->update(['images' => [$path]]);
        }
    }

    /**
     * PNG de color liso con un círculo claro, como imagen de ejemplo.
     *
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    protected function placeholderImage(array $rgb, string $initial): string
    {
        $image = imagecreatetruecolor(800, 1000);

        imagefill($image, 0, 0, (int) imagecolorallocate($image, ...$rgb));
        imagefilledellipse($image, 400, 460, 420, 420, (int) imagecolorallocatealpha($image, 255, 255, 255, 90));
        imagestring($image, 5, 396, 452, $initial, (int) imagecolorallocate($image, 255, 255, 255));

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    // --- Datos fijos --------------------------------------------------------------

    /**
     * @return array<string, array{
     *     name: string, brand: string, category: string, description: string, price: string, cost: string,
     *     variants: array<string, list<string>>, variant_prices: array<int, string>, leftover: array<int, int>,
     *     published: bool, featured: bool, show_price: bool, color: array{0: int, 1: int, 2: int}
     * }>
     */
    protected function productDefinitions(): array
    {
        $base = ['variants' => [], 'variant_prices' => [], 'leftover' => [], 'published' => true, 'featured' => false, 'show_price' => true];

        return [
            'remera-basica' => [...$base, 'name' => 'Remera básica', 'brand' => 'Nube', 'category' => 'Remeras',
                'description' => "Algodón peinado, corte recto.\nLavar a mano con agua fría.", 'price' => '12000.00', 'cost' => '6000.00',
                'variants' => ['Talle' => ['S', 'M', 'L'], 'Color' => ['Negro', 'Blanco']], 'featured' => true, 'color' => [180, 83, 9]],
            'remera-estampada' => [...$base, 'name' => 'Remera estampada', 'brand' => 'Nube', 'category' => 'Remeras',
                'description' => 'Estampa serigrafiada sobre algodón.', 'price' => '15000.00', 'cost' => '7500.00',
                'variants' => ['Talle' => ['M', 'L'], 'Color' => ['Negro', 'Blanco']], 'variant_prices' => [3 => '16500.00'], 'color' => [124, 58, 237]],
            'camisa-lino' => [...$base, 'name' => 'Camisa de lino', 'brand' => 'Aurora', 'category' => 'Camisas',
                'description' => 'Lino lavado, liviana y fresca para el verano.', 'price' => '28000.00', 'cost' => '14000.00',
                'variants' => ['Talle' => ['S', 'M', 'L']], 'featured' => true, 'color' => [87, 120, 99]],
            'jean-recto' => [...$base, 'name' => 'Jean recto', 'brand' => 'Denim Co', 'category' => 'Pantalones',
                'description' => 'Denim rígido de tiro medio.', 'price' => '32000.00', 'cost' => '16000.00',
                'variants' => ['Talle' => ['S', 'M', 'L', 'XL']], 'color' => [30, 64, 120]],
            'pantalon-sastrero' => [...$base, 'name' => 'Pantalón sastrero', 'brand' => 'Aurora', 'category' => 'Pantalones',
                'description' => 'Corte clásico, ideal para la oficina.', 'price' => '35000.00', 'cost' => '17500.00',
                'variants' => ['Talle' => ['S', 'M', 'L']], 'color' => [70, 70, 80]],
            'campera-puffer' => [...$base, 'name' => 'Campera puffer', 'brand' => 'Aurora', 'category' => 'Abrigos',
                'description' => 'Relleno térmico liviano. Resiste el viento.', 'price' => '65000.00', 'cost' => '32000.00',
                'variants' => ['Talle' => ['M', 'L'], 'Color' => ['Negro', 'Azul']], 'leftover' => [0, 2, 1, 4], 'featured' => true, 'color' => [30, 30, 30]],
            'buzo-canguro' => [...$base, 'name' => 'Buzo canguro', 'brand' => 'Nube', 'category' => 'Abrigos',
                'description' => 'Friza peinada con capucha y bolsillo.', 'price' => '30000.00', 'cost' => '15000.00',
                'variants' => ['Talle' => ['S', 'M', 'L'], 'Color' => ['Gris']], 'color' => [120, 120, 125]],
            'gorra' => [...$base, 'name' => 'Gorra', 'brand' => 'Nube', 'category' => 'Accesorios',
                'description' => 'Visera curva, regulable.', 'price' => '8000.00', 'cost' => '3000.00',
                'leftover' => [2], 'featured' => true, 'color' => [20, 83, 45]],
            'cinturon' => [...$base, 'name' => 'Cinturón de cuero', 'brand' => 'Aurora', 'category' => 'Accesorios',
                'description' => 'Cuero vacuno con hebilla metálica.', 'price' => '14000.00', 'cost' => '6000.00', 'color' => [92, 64, 51]],
            'medias' => [...$base, 'name' => 'Pack de medias', 'brand' => 'Nube', 'category' => 'Accesorios',
                'description' => 'Pack x3 de algodón.', 'price' => '6000.00', 'cost' => '2500.00', 'leftover' => [0], 'color' => [190, 150, 90]],
            'mochila' => [...$base, 'name' => 'Mochila urbana', 'brand' => 'Aurora', 'category' => 'Accesorios',
                'description' => 'Resistente al agua, con compartimento para notebook.', 'price' => '45000.00', 'cost' => '22000.00',
                'show_price' => false, 'color' => [50, 50, 90]],
            'bufanda' => [...$base, 'name' => 'Bufanda de lana', 'brand' => 'Aurora', 'category' => 'Accesorios',
                'description' => 'Lana tejida, próxima temporada.', 'price' => '18000.00', 'cost' => '8000.00', 'published' => false, 'color' => [150, 40, 60]],
        ];
    }

    /**
     * Ventas de los últimos tres meses: [producto, índice de variante, cantidad] por ítem.
     * El día es 0-based dentro del mes; dos ventas se anulan.
     *
     * `on_account` (solo con Cuenta corriente y cliente): qué porcentaje se cobra en el acto y a
     * cuántos días vence el saldo; sin el módulo la venta es de contado como el resto.
     *
     * @return list<array{
     *     month: int, day: int, by: string, customer?: int, pay: list<string>,
     *     items: list<array{0: string, 1: int, 2: int}>, cancel?: string,
     *     on_account?: array{paid: int, due_days: int}
     * }>
     */
    protected function salePlan(): array
    {
        return [
            ['month' => 0, 'day' => 0, 'by' => 'admin', 'customer' => 0, 'pay' => ['Efectivo'], 'items' => [['remera-basica', 0, 2]]],
            ['month' => 0, 'day' => 1, 'by' => 'ana', 'pay' => ['Tarjeta 3 cuotas'], 'items' => [['jean-recto', 1, 1]]],
            ['month' => 0, 'day' => 1, 'by' => 'luis', 'pay' => ['Efectivo'], 'items' => [['gorra', 0, 2]]],
            ['month' => 0, 'day' => 2, 'by' => 'ana', 'customer' => 1, 'pay' => ['Tarjeta 6 cuotas'], 'items' => [['campera-puffer', 0, 1]]],
            ['month' => 0, 'day' => 3, 'by' => 'admin', 'pay' => ['Transferencia', 'Efectivo'], 'items' => [['buzo-canguro', 2, 1], ['medias', 0, 3]]],
            ['month' => 0, 'day' => 4, 'by' => 'luis', 'pay' => ['Débito'], 'items' => [['remera-estampada', 1, 2]]],
            ['month' => 0, 'day' => 5, 'by' => 'ana', 'customer' => 2, 'pay' => ['Tarjeta 1 cuota'], 'items' => [['camisa-lino', 1, 1], ['cinturon', 0, 1]], 'on_account' => ['paid' => 0, 'due_days' => 20]],
            ['month' => 0, 'day' => 6, 'by' => 'admin', 'pay' => ['Efectivo'], 'items' => [['pantalon-sastrero', 0, 1]], 'cancel' => 'El cliente devolvió el pedido'],
            ['month' => 0, 'day' => 7, 'by' => 'luis', 'pay' => ['Transferencia'], 'items' => [['mochila', 0, 1]]],
            ['month' => 0, 'day' => 8, 'by' => 'ana', 'pay' => ['Efectivo', 'Débito'], 'items' => [['remera-basica', 3, 1], ['gorra', 0, 1]]],
            ['month' => 0, 'day' => 9, 'by' => 'admin', 'customer' => 3, 'pay' => ['Tarjeta 3 cuotas'], 'items' => [['jean-recto', 2, 2]], 'on_account' => ['paid' => 30, 'due_days' => 30]],
            ['month' => 0, 'day' => 10, 'by' => 'luis', 'pay' => ['Efectivo'], 'items' => [['remera-basica', 1, 3]]],
            ['month' => 1, 'day' => 2, 'by' => 'ana', 'pay' => ['Tarjeta 3 cuotas'], 'items' => [['campera-puffer', 1, 1]]],
            ['month' => 1, 'day' => 4, 'by' => 'admin', 'customer' => 0, 'pay' => ['Transferencia'], 'items' => [['remera-basica', 2, 2], ['cinturon', 0, 1]], 'on_account' => ['paid' => 50, 'due_days' => 20]],
            ['month' => 1, 'day' => 5, 'by' => 'luis', 'pay' => ['Débito'], 'items' => [['buzo-canguro', 0, 2]]],
            ['month' => 1, 'day' => 8, 'by' => 'ana', 'pay' => ['Efectivo'], 'items' => [['camisa-lino', 0, 1]]],
            ['month' => 1, 'day' => 10, 'by' => 'admin', 'pay' => ['Efectivo'], 'items' => [['gorra', 0, 3]]],
            ['month' => 1, 'day' => 12, 'by' => 'luis', 'customer' => 4, 'pay' => ['Tarjeta 6 cuotas'], 'items' => [['jean-recto', 0, 1], ['remera-estampada', 0, 1]], 'on_account' => ['paid' => 0, 'due_days' => 45]],
            ['month' => 1, 'day' => 15, 'by' => 'ana', 'pay' => ['Tarjeta 1 cuota'], 'items' => [['pantalon-sastrero', 2, 1]], 'cancel' => 'Error de carga: se cobró de más'],
            ['month' => 1, 'day' => 18, 'by' => 'admin', 'pay' => ['Transferencia'], 'items' => [['mochila', 0, 1]]],
            ['month' => 1, 'day' => 20, 'by' => 'luis', 'pay' => ['Efectivo', 'Transferencia'], 'items' => [['remera-basica', 4, 2]]],
            ['month' => 1, 'day' => 24, 'by' => 'ana', 'customer' => 5, 'pay' => ['Tarjeta 3 cuotas'], 'items' => [['campera-puffer', 2, 1]], 'on_account' => ['paid' => 40, 'due_days' => 10]],
            ['month' => 2, 'day' => 5, 'by' => 'admin', 'pay' => ['Efectivo'], 'items' => [['remera-estampada', 2, 1]]],
            ['month' => 2, 'day' => 14, 'by' => 'luis', 'pay' => ['Débito'], 'items' => [['buzo-canguro', 1, 1]]],
            ['month' => 2, 'day' => 22, 'by' => 'ana', 'pay' => ['Efectivo'], 'items' => [['jean-recto', 3, 1], ['medias', 0, 2]]],
        ];
    }
}
