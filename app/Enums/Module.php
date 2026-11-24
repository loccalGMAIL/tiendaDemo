<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Lista cerrada de módulos habilitables. El orden de los casos es el orden
 * de dependencia: un módulo siempre aparece después de los que requiere.
 */
enum Module: string implements HasLabel
{
    case CurrentAccount = 'current-account';
    case Installments = 'installments';
    case Variants = 'variants';
    case Purchases = 'purchases';
    case Stock = 'stock';
    case Reports = 'reports';
    case Catalog = 'catalog';
    case GoogleLogin = 'google-login';
    case CustomColors = 'custom-colors';
    case Barcodes = 'barcodes';

    public function getLabel(): string
    {
        return match ($this) {
            self::CurrentAccount => 'Cuenta corriente',
            self::Installments => 'Cuotas propias',
            self::Variants => 'Variantes',
            self::Purchases => 'Compras',
            self::Stock => 'Stock',
            self::Reports => 'Informes',
            self::Catalog => 'Catálogo web',
            self::GoogleLogin => 'Login con Google',
            self::CustomColors => 'Colores personalizables',
            self::Barcodes => 'Códigos de barras',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::CurrentAccount => 'Ventas a cuenta, cobros imputados, saldo y ficha financiera del cliente.',
            self::Installments => 'Financiación del propio comercio: planes, ventas en varias cuotas, vencimientos y cobranza.',
            self::Variants => 'Talles, colores u otras presentaciones por producto, con precio y stock propios.',
            self::Purchases => 'Proveedores, órdenes de compra y facturas de compra.',
            self::Stock => 'Existencias, movimientos y stock mínimo.',
            self::Reports => 'Grupo Informes. Cada informe exige además su propio módulo.',
            self::Catalog => 'Catálogo público en la landing con los productos del panel.',
            self::GoogleLogin => 'Botón «Ingresar con Google» en el login del panel.',
            self::CustomColors => 'El administrador del cliente elige la combinación de colores.',
            self::Barcodes => 'Etiquetas con código de barras (el SKU) para imprimir y venta con lector de códigos.',
        };
    }

    /**
     * Módulos que tienen que estar encendidos para encender este.
     *
     * @return list<self>
     */
    public function requires(): array
    {
        return match ($this) {
            self::Installments => [self::CurrentAccount],
            default => [],
        };
    }

    /**
     * Módulos que dependen de este (inverso de requires()).
     *
     * @return list<self>
     */
    public function dependents(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $module): bool => in_array($this, $module->requires(), true),
        ));
    }

    /**
     * Mensaje si falta configuración del servidor para poder encenderlo; null si está todo.
     */
    public function missingConfiguration(): ?string
    {
        return match ($this) {
            self::GoogleLogin => blank(config('services.google.client_id')) || blank(config('services.google.client_secret'))
                ? 'Faltan GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET en el .env.'
                : null,
            default => null,
        };
    }

    /**
     * Ajustes propios del módulo y sus valores por defecto.
     *
     * @return array<string, mixed>
     */
    public function defaultSettings(): array
    {
        return match ($this) {
            self::Stock => ['on_no_stock' => 'warn'],
            self::Variants => ['attributes' => []],
            self::Installments => ['due_rule' => 'fixed_day', 'fixed_day' => 10],
            self::Catalog => ['show_sold_out' => true],
            self::Barcodes => [
                'preset' => 'roll_50x25',
                'format' => 'roll',
                'paper' => 'A4',
                'label_width' => 50,
                'label_height' => 25,
                'columns' => 1,
                'rows' => 1,
                'margin_top' => 0,
                'margin_left' => 0,
                'gap_x' => 0,
                'gap_y' => 0,
                'show_price' => true,
                'show_business' => false,
            ],
            default => [],
        };
    }
}
