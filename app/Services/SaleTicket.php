<?php

namespace App\Services;

/**
 * Formato del ticket de venta (Configuración → Ticket): ancho del papel, encabezado, pie, qué
 * datos se muestran y qué hace la Caja al confirmar. Se guarda normalizado en los ajustes de
 * la instalación; sin configurar, valen los valores por defecto.
 *
 * @phpstan-type TicketSettings array{
 *     paper_width: int,
 *     show_site_contact: bool,
 *     header: string,
 *     footer: string,
 *     show_seller: bool,
 *     show_customer: bool,
 *     show_codes: bool,
 *     not_invoice_legend: bool,
 *     print_on_confirm: string,
 *     copies: int
 * }
 */
class SaleTicket
{
    private const SETTINGS_KEY = 'ticket';

    /** Anchos de papel de las impresoras térmicas, en milímetros. */
    public const PAPER_WIDTHS = [58 => 'Rollo de 58 mm', 80 => 'Rollo de 80 mm'];

    /** Qué hace la Caja al confirmar una venta. */
    public const PRINT_MODES = [
        'never' => 'No imprimir (se puede imprimir después)',
        'ask' => 'Preguntar (Enter imprime, seguir escaneando no)',
        'always' => 'Imprimir siempre',
    ];

    public const MAX_COPIES = 3;

    public const MAX_TEXT = 500;

    public function __construct(private Settings $settings, private SiteContent $site) {}

    /**
     * @return TicketSettings
     */
    public function defaults(): array
    {
        return [
            'paper_width' => 80,
            'show_site_contact' => true,
            'header' => '',
            'footer' => 'Gracias por su compra',
            'show_seller' => true,
            'show_customer' => true,
            'show_codes' => false,
            'not_invoice_legend' => true,
            'print_on_confirm' => 'ask',
            'copies' => 1,
        ];
    }

    /**
     * @return TicketSettings
     */
    public function settings(): array
    {
        $stored = $this->settings->get(self::SETTINGS_KEY, []);

        return $this->normalize(is_array($stored) ? $stored : []);
    }

    /**
     * Guarda lo indicado sobre lo que ya había y devuelve cómo quedó.
     *
     * @param  array<string, mixed>  $values
     * @return TicketSettings
     */
    public function save(array $values): array
    {
        $settings = $this->normalize([...$this->settings(), ...$values]);

        $this->settings->set(self::SETTINGS_KEY, $settings);

        return $settings;
    }

    public function printMode(): string
    {
        return $this->settings()['print_on_confirm'];
    }

    /**
     * Líneas del encabezado debajo del nombre del negocio: dirección y teléfono del sitio (si se
     * pidió) y las líneas propias del ticket (CUIT, condición frente al IVA…).
     *
     * @return list<string>
     */
    public function headerLines(): array
    {
        $settings = $this->settings();
        $lines = [];

        if ($settings['show_site_contact']) {
            $site = $this->site->all();
            $lines[] = trim($site['address']);
            $lines[] = trim($site['phone']) !== '' ? 'Tel. '.trim($site['phone']) : '';
        }

        return array_values(array_filter(
            [...$lines, ...$this->lines($settings['header'])],
            fn (string $line): bool => $line !== '',
        ));
    }

    /**
     * @return list<string>
     */
    public function footerLines(): array
    {
        return $this->lines($this->settings()['footer']);
    }

    public function businessName(): string
    {
        return $this->site->name();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return TicketSettings
     */
    private function normalize(array $values): array
    {
        $defaults = $this->defaults();
        $width = (int) ($values['paper_width'] ?? $defaults['paper_width']);
        $mode = (string) ($values['print_on_confirm'] ?? $defaults['print_on_confirm']);

        return [
            'paper_width' => array_key_exists($width, self::PAPER_WIDTHS) ? $width : $defaults['paper_width'],
            'show_site_contact' => (bool) ($values['show_site_contact'] ?? $defaults['show_site_contact']),
            'header' => $this->text($values['header'] ?? $defaults['header']),
            'footer' => $this->text($values['footer'] ?? $defaults['footer']),
            'show_seller' => (bool) ($values['show_seller'] ?? $defaults['show_seller']),
            'show_customer' => (bool) ($values['show_customer'] ?? $defaults['show_customer']),
            'show_codes' => (bool) ($values['show_codes'] ?? $defaults['show_codes']),
            'not_invoice_legend' => (bool) ($values['not_invoice_legend'] ?? $defaults['not_invoice_legend']),
            'print_on_confirm' => array_key_exists($mode, self::PRINT_MODES) ? $mode : $defaults['print_on_confirm'],
            'copies' => max(1, min(self::MAX_COPIES, (int) ($values['copies'] ?? $defaults['copies']))),
        ];
    }

    private function text(mixed $value): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim((string) $value));

        return mb_substr($text, 0, self::MAX_TEXT);
    }

    /**
     * @return list<string>
     */
    private function lines(string $text): array
    {
        return array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            fn (string $line): bool => $line !== '',
        ));
    }
}
