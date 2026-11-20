<?php

namespace Database\Seeders;

use App\Actions\Products\CreateProduct;
use App\Actions\Stock\CountStock;
use App\Actions\Variants\GenerateVariants;
use App\Enums\Module;
use App\Enums\Role;
use App\Facades\Modules;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Settings;
use App\Services\SiteContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Carga inicial de Muy Chulas (indumentaria, Concepción del Uruguay).
 *
 * Los datos del negocio, los productos, los precios, los talles y las fotos salen de
 * su Instagram público (@muy_chulas_ok). El costo no se conoce y queda en cero; no se
 * cargan ventas ni clientes. Usa las acciones reales y solo arma lo de los módulos
 * encendidos, así que corre después de `modules:enable`.
 *
 * No corre si la instalación ya tiene productos.
 */
class MuyChulasSeeder extends Seeder
{
    /** Existencia inicial de cada variante si el producto no indica otra. */
    private const DEFAULT_STOCK = 6;

    /** Stock mínimo de todas las variantes (con el módulo Stock). */
    private const MIN_STOCK = 2;

    /** Carpeta de las fotos, una por producto: `<clave>.jpg`. */
    private const ASSETS = 'seeders/assets/muy-chulas';

    /** @var array<string, Product> */
    private array $products = [];

    public function run(): void
    {
        if (Product::query()->exists()) {
            $this->command?->info('Ya hay productos cargados: no se agrega la carga inicial de Muy Chulas para no pisar datos en uso.');

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

        $this->seedSite();
        $this->seedVariantAttributes();
        $this->seedProducts();
        $this->seedVariants($admin);
        $this->seedStock($admin);
        $this->seedCatalog();

        $this->command?->info('Muy Chulas cargada: '.Product::query()->count().' productos.');
    }

    // --- Sitio ------------------------------------------------------------------

    private function seedSite(): void
    {
        if (app(Settings::class)->get('site') !== null) {
            return;
        }

        $site = app(SiteContent::class);

        $site->save([
            ...$site->defaults(),
            'name' => 'Muy Chulas',
            'tagline' => 'Moda para mujeres que saben lo que quieren',
            'hero_title' => 'Moda para mujeres que saben lo que quieren',
            'hero_subtitle' => 'Ingresos nuevos todas las semanas, con envíos a todo el país.',
            'value_title' => 'Por qué elegirnos',
            'value_items' => [
                ['title' => 'Talles para todas', 'text' => 'Trabajamos del S al XXL y del 1 al 5, con modelos amplios.'],
                ['title' => 'Envíos a todo el país', 'text' => 'Elegí por la web o por WhatsApp y te lo mandamos.'],
                ['title' => 'Vouchers de regalo', 'text' => 'Impresos o digitales: vos elegís el monto y ella la ropa.'],
            ],
            'faq_items' => [
                ['question' => '¿Hacen envíos?', 'answer' => 'Sí, a todo el país.'],
                ['question' => '¿Dónde está el local?', 'answer' => 'En 9 de Julio 1084, Concepción del Uruguay.'],
                ['question' => '¿Tienen vouchers de regalo?', 'answer' => 'Sí, impresos y digitales, por el monto que elijas.'],
            ],
            'whatsapp' => '+54 9 3442 66-0818',
            'address' => '9 de Julio 1084, Concepción del Uruguay, Entre Ríos',
            'instagram' => 'https://www.instagram.com/muy_chulas_ok/',
        ]);
    }

    // --- Productos --------------------------------------------------------------

    /**
     * Suma a los atributos del módulo los talles y colores que usa Muy Chulas, sin
     * quitar ni reordenar los que ya estén configurados.
     */
    private function seedVariantAttributes(): void
    {
        if (! Modules::enabled(Module::Variants)) {
            return;
        }

        $attributes = [];

        foreach ((array) Modules::setting(Module::Variants, 'attributes', []) as $attribute) {
            $attributes[(string) ($attribute['name'] ?? '')] = array_map('strval', (array) ($attribute['values'] ?? []));
        }

        $needed = [
            'Talle' => ['S', 'M', 'L', 'XL', 'XXL', '1', '2', '3', '4', '5', '34', '36', '38', '40', '42', '44', '38/40', '42/44'],
            'Color' => ['Blanco', 'Negro', 'Beige', 'Chocolate', 'Borravino', 'Natural', 'Camel'],
        ];

        foreach ($needed as $name => $values) {
            $attributes[$name] = array_values(array_unique([...($attributes[$name] ?? []), ...$values]));
        }

        Modules::updateSettings(Module::Variants, ['attributes' => array_map(
            fn (string $name, array $values): array => ['name' => $name, 'values' => $values],
            array_keys($attributes),
            array_values($attributes),
        )]);
    }

    private function seedProducts(): void
    {
        $categories = [];
        $names = array_values(array_unique(array_column($this->productDefinitions(), 'category')));

        foreach ($names as $position => $name) {
            $categories[$name] = Category::query()->create(['name' => $name, 'position' => $position, 'active' => true]);
        }

        $withCatalog = Modules::enabled(Module::Catalog);

        foreach ($this->productDefinitions() as $key => $definition) {
            $data = [
                'name' => $definition['name'],
                'description' => $definition['description'],
                'category_id' => $categories[$definition['category']]->getKey(),
                'price' => $definition['price'],
                'active' => true,
            ];

            if ($withCatalog) {
                $data += ['is_published' => true, 'is_featured' => $definition['featured'], 'show_price' => true];
            }

            $this->products[$key] = app(CreateProduct::class)->handle($data);
        }
    }

    private function seedVariants(User $admin): void
    {
        if (! Modules::enabled(Module::Variants)) {
            return;
        }

        foreach ($this->productDefinitions() as $key => $definition) {
            if ($definition['variants'] !== []) {
                app(GenerateVariants::class)->handle($this->products[$key], $definition['variants'], $admin);
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

    private function seedStock(User $admin): void
    {
        if (! Modules::enabled(Module::Stock)) {
            return;
        }

        $counts = [];
        $minimums = [];

        foreach ($this->productDefinitions() as $key => $definition) {
            foreach ($this->variantsOf($this->products[$key]) as $variant) {
                $counts[$variant->getKey()] = $definition['stock'];
                $minimums[$variant->getKey()] = self::MIN_STOCK;
            }
        }

        app(CountStock::class)->handle($counts, 'Stock inicial', $admin, $minimums);
    }

    // --- Catálogo web -------------------------------------------------------------

    /**
     * Copia la foto de cada producto al disco público. Un producto sin foto queda sin imagen.
     */
    private function seedCatalog(): void
    {
        if (! Modules::enabled(Module::Catalog)) {
            return;
        }

        $disk = Storage::disk('public');

        foreach ($this->products as $key => $product) {
            $source = database_path(self::ASSETS.'/'.$key.'.jpg');

            if (! is_file($source)) {
                continue;
            }

            $path = 'products/muy-chulas-'.$key.'.jpg';

            $disk->put($path, (string) file_get_contents($source));
            $product->update(['images' => [$path]]);
        }
    }

    // --- Datos fijos --------------------------------------------------------------

    /**
     * Productos publicados con precio en el Instagram del negocio. La clave es también
     * el nombre del archivo de la foto.
     *
     * @return array<string, array{
     *     name: string, category: string, description: string, price: string,
     *     variants: array<string, list<string>>, stock: int, featured: bool
     * }>
     */
    private function productDefinitions(): array
    {
        $base = ['variants' => [], 'stock' => self::DEFAULT_STOCK, 'featured' => false];

        $letters = ['S', 'M', 'L', 'XL'];
        $numbers = ['1', '2', '3', '4', '5'];
        $waist = ['36', '38', '40', '42', '44'];

        return [
            'remera-basica' => [...$base, 'name' => 'Remera básica', 'category' => 'Remeras', 'price' => '15000.00',
                'description' => 'Básica de todos los días, del S al XL.',
                'variants' => ['Talle' => $letters, 'Color' => ['Blanco', 'Negro', 'Beige', 'Chocolate']]],
            'remera-estampada' => [...$base, 'name' => 'Remera estampada', 'category' => 'Remeras', 'price' => '16000.00',
                'description' => 'Remeritas estampadas de nueva temporada.'],
            'remera-talles-grandes' => [...$base, 'name' => 'Remera talles grandes', 'category' => 'Remeras', 'price' => '18000.00',
                'description' => 'Talles 1 al 5 (M al XXL).', 'variants' => ['Talle' => $numbers]],
            'remera-rayada' => [...$base, 'name' => 'Remera rayada', 'category' => 'Remeras', 'price' => '20000.00',
                'description' => 'Full color, del S al XL.', 'variants' => ['Talle' => $letters], 'featured' => true],
            'remera-brillitos' => [...$base, 'name' => 'Remera con brillitos', 'category' => 'Remeras', 'price' => '21000.00',
                'description' => 'Modelo amplio, del S al XL.', 'variants' => ['Talle' => $letters]],
            'remera-transparencias' => [...$base, 'name' => 'Remera estampada amplia', 'category' => 'Remeras', 'price' => '22000.00',
                'description' => 'Ideal para usar con transparencias.'],
            'remera-termica' => [...$base, 'name' => 'Remera térmica', 'category' => 'Remeras', 'price' => '15000.00',
                'description' => 'Las térmicas más lindas y cómodas. Talles 1 al 5.',
                'variants' => ['Talle' => $numbers, 'Color' => ['Chocolate', 'Negro', 'Blanco']]],

            'musculosa-algodon' => [...$base, 'name' => 'Musculosa de algodón', 'category' => 'Musculosas', 'price' => '12000.00',
                'description' => 'Musculosa de algodón en los colores de la temporada.'],
            'musculosa-amplia' => [...$base, 'name' => 'Musculosa amplia', 'category' => 'Musculosas', 'price' => '14000.00',
                'description' => 'Modelos amplios y larguitos. Talles 1 al 5.', 'variants' => ['Talle' => $numbers]],

            'blusa' => [...$base, 'name' => 'Blusa', 'category' => 'Blusas', 'price' => '18000.00',
                'description' => 'Blusas livianas, últimos colores.'],
            'blusa-amplia' => [...$base, 'name' => 'Blusa amplia', 'category' => 'Blusas', 'price' => '22000.00',
                'description' => 'Tela súper fresca. Talles 1 al 5.', 'variants' => ['Talle' => $numbers]],
            'blusa-brillos' => [...$base, 'name' => 'Blusa con brillos', 'category' => 'Blusas', 'price' => '29000.00',
                'description' => 'Para los eventos de la temporada. Del S al XL.', 'variants' => ['Talle' => $letters]],

            'buzo-darlon' => [...$base, 'name' => 'Buzo de darlon', 'category' => 'Buzos', 'price' => '20000.00',
                'description' => 'Súper abrigado y suave, ideal para los días fríos.'],
            'buzo-argentina' => [...$base, 'name' => 'Buzo Argentina', 'category' => 'Buzos', 'price' => '48000.00',
                'description' => 'Darlon premium. Talle único.'],

            'jean' => [...$base, 'name' => 'Jean', 'category' => 'Pantalones', 'price' => '27000.00',
                'description' => 'Talles 34 al 42.', 'variants' => ['Talle' => ['34', '36', '38', '40', '42']]],
            'pantalon-darlon' => [...$base, 'name' => 'Pantalón de darlon', 'category' => 'Pantalones', 'price' => '32000.00',
                'description' => 'Súper cómodo y calentito.', 'variants' => ['Talle' => ['38/40', '42/44']]],
            'pantalon-sastrero' => [...$base, 'name' => 'Pantalón sastrero', 'category' => 'Pantalones', 'price' => '32000.00',
                'description' => 'Talles 1 al 4, en blanco y negro.',
                'variants' => ['Talle' => ['1', '2', '3', '4'], 'Color' => ['Blanco', 'Negro']]],
            'pantalon-rosa' => [...$base, 'name' => 'Pantalón rayado rosa', 'category' => 'Pantalones', 'price' => '55000.00',
                'description' => 'El rosa es el color de la temporada. Talles 36 al 44.', 'variants' => ['Talle' => $waist], 'featured' => true],

            'short' => [...$base, 'name' => 'Short de jean', 'category' => 'Shorts y polleras', 'price' => '35000.00',
                'description' => 'Talles 36 al 44.', 'variants' => ['Talle' => $waist]],
            'pollera-short' => [...$base, 'name' => 'Pollera short', 'category' => 'Shorts y polleras', 'price' => '21000.00',
                'description' => 'Talles 2, 3 y 4.', 'variants' => ['Talle' => ['2', '3', '4']]],

            'conjunto-darlon' => [...$base, 'name' => 'Conjunto de darlon', 'category' => 'Conjuntos y vestidos', 'price' => '60000.00',
                'description' => 'Talles 1 al 5, amplios. En negro y borravino.',
                'variants' => ['Talle' => $numbers, 'Color' => ['Negro', 'Borravino']], 'featured' => true],
            'vestido-lentejuelas' => [...$base, 'name' => 'Vestido de lentejuelas', 'category' => 'Conjuntos y vestidos', 'price' => '35000.00',
                'description' => 'Para brillar en las fiestas.', 'featured' => true],

            'chaleco' => [...$base, 'name' => 'Chaleco', 'category' => 'Abrigos', 'price' => '29000.00',
                'description' => 'Chaleco sastrero para combinar con todo.'],
            'chaleco-gamulan' => [...$base, 'name' => 'Chaleco gamulán', 'category' => 'Abrigos', 'price' => '35000.00',
                'description' => 'Con corderito en el interior.'],
            'blazer' => [...$base, 'name' => 'Blazer', 'category' => 'Abrigos', 'price' => '39000.00',
                'description' => 'Últimos disponibles.', 'stock' => 2],
            'saco-pano' => [...$base, 'name' => 'Saco de paño', 'category' => 'Abrigos', 'price' => '45000.00',
                'description' => 'Talles amplios, 1 al 5.', 'variants' => ['Talle' => $numbers]],
            'bomber-pano' => [...$base, 'name' => 'Bomber de paño', 'category' => 'Abrigos', 'price' => '48000.00',
                'description' => 'Bomber de paño con broches.'],
            'chaqueta-pano' => [...$base, 'name' => 'Chaqueta de paño corta', 'category' => 'Abrigos', 'price' => '50000.00',
                'description' => 'Animal print. Talle M.', 'variants' => ['Talle' => ['M']], 'stock' => 2],
            'bomber-gamuzada' => [...$base, 'name' => 'Bomber gamuzada', 'category' => 'Abrigos', 'price' => '56000.00',
                'description' => 'Talles M y L.', 'variants' => ['Talle' => ['M', 'L']]],
            'puffer' => [...$base, 'name' => 'Campera puffer importada', 'category' => 'Abrigos', 'price' => '58000.00',
                'description' => 'Con piel en el interior. Del M al XXL.',
                'variants' => ['Talle' => ['M', 'L', 'XL', 'XXL'], 'Color' => ['Natural', 'Negro', 'Camel']], 'featured' => true],

            'bikini' => [...$base, 'name' => 'Bikini', 'category' => 'Bikinis', 'price' => '30000.00',
                'description' => 'Varios modelos. Talles 1 al 5 (85 al 105).', 'variants' => ['Talle' => $numbers], 'featured' => true],
        ];
    }
}
