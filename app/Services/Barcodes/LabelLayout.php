<?php

namespace App\Services\Barcodes;

/**
 * Formato de las etiquetas, a partir de los ajustes del módulo Códigos de barras.
 * Todas las medidas están en milímetros.
 */
final readonly class LabelLayout
{
    public const ROLL = 'roll';

    public const SHEET = 'sheet';

    public const CUSTOM = 'custom';

    public function __construct(
        public string $format,
        public string $paper,
        public float $width,
        public float $height,
        public int $columns,
        public int $rows,
        public float $marginTop,
        public float $marginLeft,
        public float $gapX,
        public float $gapY,
        public bool $showPrice,
        public bool $showBusiness,
    ) {}

    /**
     * Plantillas del formulario del superadmin: clave => nombre y valores que completan.
     *
     * @return array<string, array{label: string, values: array<string, mixed>}>
     */
    public static function presets(): array
    {
        $roll = fn (float $width, float $height): array => [
            'format' => self::ROLL, 'label_width' => $width, 'label_height' => $height,
            'columns' => 1, 'rows' => 1, 'margin_top' => 0, 'margin_left' => 0, 'gap_x' => 0, 'gap_y' => 0,
        ];

        return [
            'roll_50x25' => ['label' => 'Rollo 50 × 25 mm', 'values' => $roll(50, 25)],
            'roll_40x30' => ['label' => 'Rollo 40 × 30 mm', 'values' => $roll(40, 30)],
            'roll_60x40' => ['label' => 'Rollo 60 × 40 mm', 'values' => $roll(60, 40)],
            'a4_3x8' => ['label' => 'Hoja A4, 3 × 8 (70 × 37 mm)', 'values' => [
                'format' => self::SHEET, 'paper' => 'A4', 'label_width' => 70, 'label_height' => 37,
                'columns' => 3, 'rows' => 8, 'margin_top' => 0, 'margin_left' => 0, 'gap_x' => 0, 'gap_y' => 0,
            ]],
            'a4_4x10' => ['label' => 'Hoja A4, 4 × 10 (48,5 × 25,4 mm)', 'values' => [
                'format' => self::SHEET, 'paper' => 'A4', 'label_width' => 48.5, 'label_height' => 25.4,
                'columns' => 4, 'rows' => 10, 'margin_top' => 21.5, 'margin_left' => 8, 'gap_x' => 0, 'gap_y' => 0,
            ]],
            self::CUSTOM => ['label' => 'Personalizado', 'values' => []],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function fromSettings(array $settings): self
    {
        $number = fn (string $key, float $default, float $min, float $max): float => max($min, min($max, is_numeric($settings[$key] ?? null) ? (float) $settings[$key] : $default));

        $format = ($settings['format'] ?? self::ROLL) === self::SHEET ? self::SHEET : self::ROLL;
        $isSheet = $format === self::SHEET;

        return new self(
            format: $format,
            paper: ($settings['paper'] ?? 'A4') === 'letter' ? 'letter' : 'A4',
            width: $number('label_width', 50, 10, 300),
            height: $number('label_height', 25, 10, 300),
            columns: $isSheet ? (int) $number('columns', 1, 1, 10) : 1,
            rows: $isSheet ? (int) $number('rows', 1, 1, 30) : 1,
            marginTop: $isSheet ? $number('margin_top', 0, 0, 100) : 0,
            marginLeft: $isSheet ? $number('margin_left', 0, 0, 100) : 0,
            gapX: $isSheet ? $number('gap_x', 0, 0, 50) : 0,
            gapY: $isSheet ? $number('gap_y', 0, 0, 50) : 0,
            showPrice: (bool) ($settings['show_price'] ?? true),
            showBusiness: (bool) ($settings['show_business'] ?? false),
        );
    }

    public function isSheet(): bool
    {
        return $this->format === self::SHEET;
    }

    /**
     * Etiquetas por hoja (1 en rollo).
     */
    public function perPage(): int
    {
        return $this->columns * $this->rows;
    }

    /**
     * Valor de `@page { size }`.
     */
    public function pageSize(): string
    {
        if (! $this->isSheet()) {
            return "{$this->width}mm {$this->height}mm";
        }

        return $this->paper === 'letter' ? 'letter' : 'A4';
    }
}
