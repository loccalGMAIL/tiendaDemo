<?php

namespace App\Filament\Superadmin\Schemas;

use App\Enums\Module;
use App\Models\ProductVariant;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;

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
            Module::Variants => [
                Repeater::make('attributes')
                    ->label('Atributos que usa el negocio')
                    ->addActionLabel('Agregar atributo')
                    ->defaultItems(0)
                    ->schema([
                        TextInput::make('name')
                            ->label('Atributo')
                            ->placeholder('Talle')
                            ->required()
                            ->maxLength(50),
                        TagsInput::make('values')
                            ->label('Valores posibles')
                            ->placeholder('S, M, L')
                            ->required(),
                    ])
                    ->columns(2),
            ],
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
            default => [],
        };
    }
}
