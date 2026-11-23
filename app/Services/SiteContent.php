<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Textos y fotografías editables por el cliente en Configuración → Sitio.
 * La estructura se guarda aparte y solo se configura desde el panel SuperAdmin.
 * Sin configurar, el nombre sale de APP_NAME.
 */
class SiteContent
{
    /** @var array<string, mixed>|null */
    private ?array $previewContent = null;

    /** @var array<string, string> */
    private array $previewImageUrls = [];

    /** Secciones de la landing, en orden. */
    public const SECTIONS = [
        'hero' => 'Portada',
        'value' => 'Por qué elegirnos',
        'catalog' => 'Catálogo',
        'about' => 'Nuestro negocio',
        'services' => 'Servicios',
        'gallery' => 'Galería',
        'banner' => 'Novedades',
        'faq' => 'Preguntas frecuentes',
        'contact' => 'Contacto',
    ];

    public function __construct(private Settings $settings) {}

    /**
     * Contenido guardado sobre los valores por defecto.
     *
     * @return array{
     *     name: string, tagline: string, hero_title: string, hero_subtitle: string,
     *     hero_image: string|null, hero_image_alt: string,
     *     about_title: string, about_text: string, about_image: string|null,
     *     services_title: string, service_items: list<array{title: string, text: string}>,
     *     gallery_title: string, gallery_items: list<array{image: string, alt: string}>,
     *     banner_title: string, banner_text: string, hours: string,
     *     value_title: string, value_items: list<array{title: string, text: string}>,
     *     faq_items: list<array{question: string, answer: string}>,
     *     phone: string, whatsapp: string, email: string, address: string,
     *     instagram: string, facebook: string, tiktok: string,
     *     sections: array<string, bool>
     * }
     */
    public function all(): array
    {
        if ($this->previewContent !== null) {
            return $this->previewContent;
        }

        $stored = $this->settings->get('site', []);
        $stored = is_array($stored) ? $stored : [];

        $content = array_merge($this->defaults(), array_filter($stored, fn (mixed $value, string $key): bool => $key !== 'sections' && $value !== null, ARRAY_FILTER_USE_BOTH));

        $content['sections'] = array_merge(
            $this->defaults()['sections'],
            array_map('boolval', is_array($stored['sections'] ?? null) ? $stored['sections'] : []),
        );

        return $content;
    }

    /**
     * Copia aislada para renderizar un borrador sin persistirlo ni alterar el sitio público.
     *
     * @param  array<string, mixed>  $content
     * @param  array<string, string>  $imageUrls
     */
    public function withPreviewContent(array $content, array $imageUrls = []): self
    {
        $preview = clone $this;
        $preview->previewContent = array_replace($this->all(), array_intersect_key($content, $this->defaults()));
        $preview->previewImageUrls = $imageUrls;

        return $preview;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'name' => (string) config('app.name'),
            'tagline' => '',
            'hero_title' => '',
            'hero_subtitle' => '',
            'hero_image' => null,
            'hero_image_alt' => '',
            'about_title' => 'Conocenos',
            'about_text' => '',
            'about_image' => null,
            'services_title' => 'Lo que ofrecemos',
            'service_items' => [],
            'gallery_title' => 'Nuestro espacio',
            'gallery_items' => [],
            'banner_title' => '',
            'banner_text' => '',
            'hours' => '',
            'value_title' => 'Por qué elegirnos',
            'value_items' => [],
            'faq_items' => [],
            'phone' => '',
            'whatsapp' => '',
            'email' => '',
            'address' => '',
            'instagram' => '',
            'facebook' => '',
            'tiktok' => '',
            'sections' => array_fill_keys(array_keys(self::SECTIONS), true),
        ];
    }

    /**
     * Guarda el contenido (solo las claves conocidas).
     *
     * @param  array<string, mixed>  $content
     */
    public function save(array $content): void
    {
        $this->settings->set('site', array_intersect_key($content, $this->defaults()));
    }

    public function name(): string
    {
        $name = trim($this->all()['name']);

        return $name !== '' ? $name : (string) config('app.name');
    }

    /**
     * Título de la portada: el indicado o, si no, el nombre del negocio.
     */
    public function heroTitle(): string
    {
        $title = trim($this->all()['hero_title']);

        return $title !== '' ? $title : $this->name();
    }

    public function sectionEnabled(string $section): bool
    {
        return in_array($section, $this->sectionOrder(), true);
    }

    /**
     * Sin estructura explícita se conserva la visibilidad de las instalaciones anteriores.
     *
     * @return list<string>
     */
    public function sectionOrder(): array
    {
        $structure = $this->settings->get('site_structure');

        if (! is_array($structure)) {
            $structure = array_keys(array_filter($this->all()['sections']));
        }

        return array_values(array_unique(array_filter(
            $structure,
            fn (mixed $section): bool => is_string($section) && array_key_exists($section, self::SECTIONS),
        )));
    }

    /** @param list<string> $sections */
    public function saveStructure(array $sections): void
    {
        $this->settings->set('site_structure', array_values(array_unique(array_filter(
            $sections,
            fn (string $section): bool => array_key_exists($section, self::SECTIONS),
        ))));
    }

    public function imageUrl(?string $path): ?string
    {
        if ($path !== null && isset($this->previewImageUrls[$path])) {
            return $this->previewImageUrls[$path];
        }

        if (! is_string($path) || ! str_starts_with($path, 'site/') || str_contains($path, '..')) {
            return null;
        }

        return Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Enlace de WhatsApp (wa.me) con solo los dígitos del número, o null.
     */
    public function whatsappUrl(?string $message = null): ?string
    {
        $digits = preg_replace('/\D/', '', $this->all()['whatsapp']);

        if ($digits === '' || $digits === null) {
            return null;
        }

        return 'https://wa.me/'.$digits.($message !== null ? '?text='.rawurlencode($message) : '');
    }

    /**
     * Redes sociales cargadas: nombre => URL.
     *
     * @return array<string, string>
     */
    public function socials(): array
    {
        $all = $this->all();
        $networks = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok'];

        $socials = [];

        foreach ($networks as $key => $label) {
            if (trim($all[$key]) !== '') {
                $socials[$label] = trim($all[$key]);
            }
        }

        return $socials;
    }

    /**
     * Hay algún dato de contacto para mostrar.
     */
    public function hasContact(): bool
    {
        $all = $this->all();

        return trim($all['phone'].$all['whatsapp'].$all['email'].$all['address'].$all['hours']) !== '' || $this->socials() !== [];
    }
}
