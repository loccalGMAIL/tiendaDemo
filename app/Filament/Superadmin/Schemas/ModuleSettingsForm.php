<?php

namespace App\Filament\Superadmin\Schemas;

use App\Enums\Module;
use App\Models\ProductVariant;
use App\Services\Barcodes\LabelLayout;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Campos de los ajustes propios de cada módulo (panel superadmin). Los nombres
 * coinciden con las claves de Module::defaultSettings().
 */
class ModuleSettingsForm
{
    /**
     * @return array<int, Component>
     */
    public static function fields(Module $module): array
    {
        return match ($module) {
            Module::Stock => [
                Select::make('on_no_stock')
                    ->label('Al vender sin stock')
                    ->options([
                        'warn' => 'Avisar y dejar vender (el stock queda en negativo)',
                        'block' => 'Bloquear la venta',
                    ])
                    ->required()
                    ->native(false)
                    ->live()
                    ->helperText(function (Get $get): string {
                        $negative = ProductVariant::query()->where('stock', '<', 0)->count();

                        if ($get('on_no_stock') === 'block' && $negative > 0) {
                            return "Atención: hay {$negative} variantes con stock negativo. Conviene corregirlas con la carga de stock antes de bloquear las ventas.";
                        }

                        return 'Conviene dejarlo en «Avisar» hasta terminar el primer recuento de stock.';
                    }),
            ],
            Module::Variants => [self::variantAttributesRepeater()],
            Module::Installments => [
                Select::make('due_rule')
                    ->label('Vencimiento de las cuotas')
                    ->options([
                        'fixed_day' => 'Día fijo del mes',
                        'same_day' => 'El mismo día de la venta',
                    ])
                    ->required()
                    ->native(false)
                    ->live(),
                TextInput::make('fixed_day')
                    ->label('Día del mes')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(28)
                    ->required()
                    ->visible(fn (Get $get): bool => $get('due_rule') === 'fixed_day')
                    ->helperText('En los meses más cortos vence el último día.'),
            ],
            Module::Catalog => [
                Toggle::make('show_sold_out')
                    ->label('Mostrar los productos agotados')
                    ->helperText('Con Stock encendido, las variantes sin existencia aparecen como agotadas.'),
            ],
            Module::Barcodes => self::barcodeFields(),
            default => [],
        };
    }

    /**
     * Atributos de las variantes (talle, color…) con sus valores posibles. Lo usan el panel
     * de la plataforma y la página Catálogo → Atributos del administrador.
     */
    public static function variantAttributesRepeater(): Repeater
    {
        return Repeater::make('attributes')
            ->label('Atributos que usa el negocio')
            ->addActionLabel('Agregar atributo')
            ->defaultItems(0)
            ->reorderable()
            ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null) ? $state['name'] : null)
            ->schema([
                TextInput::make('name')
                    ->label('Atributo')
                    ->placeholder('Talle')
                    ->required()
                    ->distinct()
                    ->maxLength(50),
                TagsInput::make('values')
                    ->label('Valores posibles')
                    ->placeholder('S, M, L')
                    ->reorderable()
                    ->required(),
            ])
            ->columns(2);
    }

    /**
     * Formato de las etiquetas: una plantilla completa las medidas y se puede ajustar a mano.
     *
     * @return array<int, Component>
     */
    private static function barcodeFields(): array
    {
        $measure = fn (string $key, string $label, float $min, float $max): TextInput => TextInput::make($key)
            ->label($label)
            ->suffix('mm')
            ->numeric()
            ->minValue($min)
            ->maxValue($max)
            ->required()
            ->live(onBlur: true)
            ->afterStateUpdated(fn (Set $set) => $set('preset', LabelLayout::CUSTOM));

        $isSheet = fn (Get $get): bool => $get('format') === LabelLayout::SHEET;

        return [
            Select::make('preset')
                ->label('Plantilla')
                ->options(array_map(fn (array $preset): string => $preset['label'], LabelLayout::presets()))
                ->native(false)
                ->live()
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    foreach (LabelLayout::presets()[$state]['values'] ?? [] as $key => $value) {
                        $set($key, $value);
                    }
                })
                ->helperText('Elegí la medida de tus etiquetas. Después podés ajustar cada valor.'),
            Select::make('format')
                ->label('Papel')
                ->options([
                    LabelLayout::ROLL => 'Rollo de etiquetas (una por página)',
                    LabelLayout::SHEET => 'Hoja con varias etiquetas',
                ])
                ->required()
                ->native(false)
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('preset', LabelLayout::CUSTOM)),
            Select::make('paper')
                ->label('Tamaño de hoja')
                ->options(['A4' => 'A4', 'letter' => 'Carta'])
                ->native(false)
                ->visible($isSheet),
            $measure('label_width', 'Ancho de la etiqueta', 10, 300),
            $measure('label_height', 'Alto de la etiqueta', 10, 300),
            $measure('columns', 'Columnas por hoja', 1, 10)->suffix(null)->integer()->visible($isSheet),
            $measure('rows', 'Filas por hoja', 1, 30)->suffix(null)->integer()->visible($isSheet),
            $measure('margin_top', 'Margen superior', 0, 100)->visible($isSheet),
            $measure('margin_left', 'Margen izquierdo', 0, 100)->visible($isSheet),
            $measure('gap_x', 'Separación horizontal', 0, 50)->visible($isSheet),
            $measure('gap_y', 'Separación vertical', 0, 50)->visible($isSheet),
            Toggle::make('show_price')->label('Mostrar el precio'),
            Toggle::make('show_business')->label('Mostrar el nombre del negocio'),
        ];
    }
}
