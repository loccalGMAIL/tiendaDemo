<?php

namespace App\Services;

/**
 * Contenido del sitio (landing y marca): solo texto, editable por el administrador
 * del cliente en Configuración → Sitio. Sin configurar, el nombre sale de APP_NAME.
 */
class SiteContent
{
    /** Secciones de la landing, en orden. */
    public const SECTIONS = [
        'hero' => 'Portada',
        'value' => 'Por qué elegirnos',
        'catalog' => 'Catálogo',
        'faq' => 'Preguntas frecuentes',
        'contact' => 'Contacto',
    ];

    public function __construct(private Settings $settings) {}

    /**
     * Contenido guardado sobre los valores por defecto.
     *
     * @return array{
     *     name: string, tagline: string, hero_title: string, hero_subtitle: string,
     *     value_title: string, value_items: list<array{title: string, text: string}>,
     *     faq_items: list<array{question: string, answer: string}>,
     *     phone: string, whatsapp: string, email: string, address: string,
     *     instagram: string, facebook: string, tiktok: string,
     *     sections: array<string, bool>
     * }
     */
    public function all(): array
    {
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
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'name' => (string) config('app.name'),
            'tagline' => '',
            'hero_title' => '',
            'hero_subtitle' => '',
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
        return $this->all()['sections'][$section] ?? false;
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

        return trim($all['phone'].$all['whatsapp'].$all['email'].$all['address']) !== '' || $this->socials() !== [];
    }
}
