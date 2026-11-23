<?php

namespace Database\Seeders;

use App\Enums\Module;
use App\Facades\Modules;
use App\Services\Appearance;
use Illuminate\Support\Facades\Storage;

/**
 * Demostración con los datos de «eat! viandas saludables» (Rosario).
 *
 * Toma de su Instagram, su Linktree y su sitio: nombre, contacto, zonas de
 * entrega, horarios de pedido y los precios del menú semanal (viandas
 * individuales, XL y packs). Los clientes y las ventas son inventados.
 * Los costos y el precio de las Energy Balls son estimados: ajustarlos.
 *
 * Reutiliza todo el armado de DemoSeeder; solo cambia los datos.
 * Uso: `php artisan db:seed --class=EatViandasSeeder` (con los módulos encendidos).
 */
class EatViandasSeeder extends DemoSeeder
{
    /** Carpeta (junto al seeder) con el logo y las fotos tomadas del Instagram de la marca. */
    private const IMAGES_DIR = __DIR__.'/images/eatviandas';

    /**
     * Foto de cada producto; los packs reutilizan la de su vianda. Sin foto: imagen de color.
     *
     * @var array<string, string>
     */
    private const PRODUCT_PHOTOS = [
        'eat-fit' => 'eat-fit.png',
        'pack-eat-fit' => 'eat-fit.png',
        'veggie-fit' => 'veggie-fit.png',
        'pack-veggie-fit' => 'veggie-fit.png',
        'vianda-xl' => 'vianda-xl.png',
        'pack-xl' => 'vianda-xl.png',
        'menu-empresas' => 'empresas.png',
    ];

    protected function seedSite(): void
    {
        parent::seedSite();

        $logo = $this->storeImage('logo.png', 'branding/eatviandas-logo.png');

        if ($logo === null) {
            return;
        }

        $appearance = app(Appearance::class);

        foreach (['logo_light', 'logo_dark', 'logo_login', 'favicon'] as $slot) {
            $appearance->setFile($slot, $logo);
        }
    }

    protected function seedCatalog(): void
    {
        if (! Modules::enabled(Module::Catalog)) {
            return;
        }

        foreach ($this->productDefinitions() as $key => $definition) {
            $product = $this->products[$key];
            $photo = isset(self::PRODUCT_PHOTOS[$key])
                ? $this->storeImage(self::PRODUCT_PHOTOS[$key], 'products/demo-'.$product->slug.'.png')
                : null;

            if ($photo === null && extension_loaded('gd')) {
                $photo = 'products/demo-'.$product->slug.'.png';
                Storage::disk('public')->put($photo, $this->placeholderImage($definition['color'], mb_strtoupper(mb_substr($definition['name'], 0, 1))));
            }

            if ($photo !== null) {
                $product->update(['images' => [$photo]]);
            }
        }
    }

    /**
     * Copia una imagen de la carpeta del seeder al disco público; null si no está.
     */
    private function storeImage(string $file, string $path): ?string
    {
        $source = self::IMAGES_DIR.'/'.$file;

        if (! is_file($source)) {
            return null;
        }

        Storage::disk('public')->put($path, (string) file_get_contents($source));

        return $path;
    }

    protected function siteDefinition(): array
    {
        return [
            'name' => 'eat! viandas saludables',
            'tagline' => 'Viandas diseñadas por una Lic. en Nutrición',
            'hero_title' => 'Comé rico y saludable, todos los días',
            'hero_subtitle' => 'Viandas individuales y empresariales con menú nuevo cada semana. Entregamos en Rosario: Centro, Alberdi y Echesortu.',
            'value_title' => 'Por qué elegirnos',
            'value_items' => [
                ['title' => 'Diseñadas por una nutricionista', 'text' => 'Cada plato lo piensa una Lic. en Nutrición: livianos, equilibrados y nutritivos.'],
                ['title' => 'Menú nuevo cada semana', 'text' => 'Opciones Eat Fit, Veggie Fit y XL que cambian todas las semanas.'],
                ['title' => 'Para vos o para tu equipo', 'text' => 'Viandas individuales y menú personalizado para empresas, con asesoramiento directo.'],
            ],
            'faq_items' => [
                ['question' => '¿Cómo hago un pedido?', 'answer' => 'Por WhatsApp o desde el sitio de pedidos, antes de las 11:30 hs.'],
                ['question' => '¿Hay descuento por pack semanal?', 'answer' => 'Sí: encargando las 5 viandas de la semana antes del lunes a las 11:15 hs y pagando en efectivo o transferencia.'],
                ['question' => '¿A qué zonas entregan?', 'answer' => 'Centro, Alberdi y Echesortu, en Rosario. Consultá por Fisherton y Zona Sur.'],
                ['question' => '¿Hacen menús para empresas?', 'answer' => 'Sí, armamos un menú personalizado para equipos de trabajo. Escribinos por WhatsApp.'],
            ],
            'phone' => '+54 341 347-4978',
            'whatsapp' => '+54 9 341 347-4978',
            'email' => 'contacto@eatviandas.com',
            'address' => 'Brown 2510, Rosario, Santa Fe',
            'instagram' => 'https://www.instagram.com/eatviandas',
        ];
    }

    protected function variantAttributes(): array
    {
        return [['name' => 'Opción', 'values' => ['Estándar']]];
    }

    protected function categoryNames(): array
    {
        return ['Eat Fit', 'Veggie Fit', 'Viandas XL', 'Packs semanales', 'Energy Balls', 'Empresas'];
    }

    protected function customerDefinitions(): array
    {
        return [
            ['Marcela Rojas', '27345678', '0341 555-0101', 'marcela@example.test', 'Centro'],
            ['Jorge Díaz', '30123456', '0341 555-0102', 'jorge@example.test', 'Alberdi'],
            ['Sofía Benítez', '33456789', '0341 555-0103', 'sofia@example.test', 'Echesortu'],
            ['Estudio Contable Aguirre', '30711223', '0341 555-0104', 'administracion@example.test', 'Centro'],
            ['Valeria Costa', '28765432', '0341 555-0105', 'valeria@example.test', 'Fisherton'],
            ['Taller Sosa SRL', '30655443', '0341 555-0106', 'compras@example.test', 'Zona Sur'],
        ];
    }

    protected function productDefinitions(): array
    {
        $base = ['variants' => [], 'variant_prices' => [], 'leftover' => [], 'published' => true, 'featured' => false, 'show_price' => true, 'brand' => 'eat!'];

        return [
            'eat-fit' => [...$base, 'name' => 'Vianda Eat Fit', 'category' => 'Eat Fit',
                'description' => "Plato del día equilibrado, con proteínas magras y guarnición.\nMenú semanal: consultá las opciones. Precio efectivo/transferencia.",
                'price' => '7800.00', 'cost' => '4200.00', 'leftover' => [20], 'featured' => true, 'color' => [74, 103, 65]],
            'veggie-fit' => [...$base, 'name' => 'Vianda Veggie Fit', 'category' => 'Veggie Fit',
                'description' => "Opción vegetariana liviana y nutritiva, por ejemplo albóndigas vegetales con salsa y batatas rústicas.\nPrecio efectivo/transferencia.",
                'price' => '7800.00', 'cost' => '4000.00', 'leftover' => [20], 'featured' => true, 'color' => [129, 140, 70]],
            'vianda-xl' => [...$base, 'name' => 'Vianda XL', 'category' => 'Viandas XL',
                'description' => "Porción grande para quienes necesitan más energía.\nPrecio efectivo/transferencia.",
                'price' => '9100.00', 'cost' => '5000.00', 'leftover' => [15], 'featured' => true, 'color' => [38, 70, 52]],
            'pack-eat-fit' => [...$base, 'name' => 'Pack semanal Eat Fit (5 viandas)', 'category' => 'Packs semanales',
                'description' => "5 viandas Eat Fit de lunes a viernes: $7.200 c/u.\nPedir antes del lunes a las 11:15 hs. Pago en efectivo o transferencia.",
                'price' => '36000.00', 'cost' => '21000.00', 'leftover' => [10], 'color' => [96, 125, 80]],
            'pack-veggie-fit' => [...$base, 'name' => 'Pack semanal Veggie Fit (5 viandas)', 'category' => 'Packs semanales',
                'description' => "5 viandas Veggie Fit de lunes a viernes: $7.200 c/u.\nPedir antes del lunes a las 11:15 hs. Pago en efectivo o transferencia.",
                'price' => '36000.00', 'cost' => '20000.00', 'leftover' => [10], 'color' => [150, 160, 90]],
            'pack-xl' => [...$base, 'name' => 'Pack semanal XL (5 viandas)', 'category' => 'Packs semanales',
                'description' => "5 viandas XL de lunes a viernes: $8.800 c/u.\nPedir antes del lunes a las 11:15 hs. Pago en efectivo o transferencia.",
                'price' => '44000.00', 'cost' => '25000.00', 'leftover' => [8], 'featured' => true, 'color' => [52, 84, 64]],
            'energy-balls' => [...$base, 'name' => 'Energy Balls', 'category' => 'Energy Balls',
                'description' => 'Bolitas energéticas para el snack de media tarde. Precio de ejemplo: confirmar con el local.',
                'price' => '3500.00', 'cost' => '1500.00', 'leftover' => [12], 'color' => [140, 98, 66]],
            'menu-empresas' => [...$base, 'name' => 'Menú personalizado para empresas', 'category' => 'Empresas',
                'description' => 'Armamos el menú para tu equipo de trabajo con asesoramiento directo. Consultá por WhatsApp.',
                'price' => '7800.00', 'cost' => '4000.00', 'leftover' => [30], 'show_price' => false, 'color' => [60, 72, 90]],
        ];
    }

    protected function salePlan(): array
    {
        return [
            ['month' => 0, 'day' => 0, 'by' => 'admin', 'customer' => 0, 'pay' => ['Efectivo'], 'items' => [['eat-fit', 0, 1]]],
            ['month' => 0, 'day' => 0, 'by' => 'ana', 'customer' => 1, 'pay' => ['Transferencia'], 'items' => [['pack-eat-fit', 0, 1]]],
            ['month' => 0, 'day' => 1, 'by' => 'luis', 'pay' => ['Efectivo'], 'items' => [['veggie-fit', 0, 2]]],
            ['month' => 0, 'day' => 1, 'by' => 'ana', 'customer' => 3, 'pay' => ['Transferencia'], 'items' => [['menu-empresas', 0, 12]]],
            ['month' => 0, 'day' => 2, 'by' => 'admin', 'pay' => ['Efectivo'], 'items' => [['vianda-xl', 0, 1], ['energy-balls', 0, 2]]],
            ['month' => 0, 'day' => 3, 'by' => 'luis', 'customer' => 2, 'pay' => ['Transferencia'], 'items' => [['pack-veggie-fit', 0, 1]]],
            ['month' => 0, 'day' => 4, 'by' => 'ana', 'pay' => ['Débito'], 'items' => [['eat-fit', 0, 2]]],
            ['month' => 0, 'day' => 5, 'by' => 'admin', 'customer' => 5, 'pay' => ['Transferencia'], 'items' => [['menu-empresas', 0, 8]]],
            ['month' => 0, 'day' => 6, 'by' => 'luis', 'pay' => ['Efectivo'], 'items' => [['veggie-fit', 0, 1]], 'cancel' => 'El cliente canceló el pedido'],
            ['month' => 0, 'day' => 7, 'by' => 'ana', 'customer' => 4, 'pay' => ['Efectivo', 'Transferencia'], 'items' => [['pack-xl', 0, 1], ['energy-balls', 0, 1]]],
            ['month' => 0, 'day' => 8, 'by' => 'admin', 'pay' => ['Débito'], 'items' => [['vianda-xl', 0, 2]]],
            ['month' => 0, 'day' => 9, 'by' => 'luis', 'customer' => 0, 'pay' => ['Efectivo'], 'items' => [['eat-fit', 0, 3]]],
            ['month' => 1, 'day' => 1, 'by' => 'ana', 'customer' => 3, 'pay' => ['Transferencia'], 'items' => [['menu-empresas', 0, 10]]],
            ['month' => 1, 'day' => 3, 'by' => 'admin', 'pay' => ['Efectivo'], 'items' => [['pack-eat-fit', 0, 2]]],
            ['month' => 1, 'day' => 6, 'by' => 'luis', 'customer' => 1, 'pay' => ['Transferencia'], 'items' => [['pack-veggie-fit', 0, 1]]],
            ['month' => 1, 'day' => 9, 'by' => 'ana', 'pay' => ['Débito'], 'items' => [['veggie-fit', 0, 2], ['energy-balls', 0, 2]]],
            ['month' => 1, 'day' => 13, 'by' => 'admin', 'pay' => ['Efectivo'], 'items' => [['eat-fit', 0, 2]], 'cancel' => 'Error de carga: se cobró de más'],
            ['month' => 1, 'day' => 16, 'by' => 'luis', 'customer' => 5, 'pay' => ['Transferencia'], 'items' => [['menu-empresas', 0, 8]]],
            ['month' => 1, 'day' => 20, 'by' => 'ana', 'pay' => ['Efectivo'], 'items' => [['pack-xl', 0, 1]]],
            ['month' => 1, 'day' => 25, 'by' => 'admin', 'customer' => 2, 'pay' => ['Transferencia'], 'items' => [['vianda-xl', 0, 3]]],
            ['month' => 2, 'day' => 4, 'by' => 'luis', 'pay' => ['Efectivo'], 'items' => [['eat-fit', 0, 4]]],
            ['month' => 2, 'day' => 12, 'by' => 'ana', 'customer' => 3, 'pay' => ['Transferencia'], 'items' => [['menu-empresas', 0, 10]]],
            ['month' => 2, 'day' => 20, 'by' => 'admin', 'pay' => ['Débito'], 'items' => [['pack-eat-fit', 0, 1]]],
        ];
    }
}
