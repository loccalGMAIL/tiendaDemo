<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Sales\SaleResource;
use App\Models\Sale;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Últimas ventas (un vendedor ve solo las suyas).
 */
class LatestSales extends TableWidget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Últimas ventas';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Sale::query()
                ->scopes(['visibleTo' => [auth()->user()]])
                ->with(['customer', 'seller'])
                ->latest('id')
                ->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('id')
                    ->label('N°')
                    ->formatStateUsing(fn (mixed $state, Sale $record): string => $record->number()),
                TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y'),
                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->placeholder('Consumidor final'),
                TextColumn::make('seller.name')
                    ->label('Vendedor'),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('ARS', locale: 'es_AR'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
            ])
            ->recordUrl(fn (Sale $record): string => SaleResource::getUrl('view', ['record' => $record]));
    }
}
