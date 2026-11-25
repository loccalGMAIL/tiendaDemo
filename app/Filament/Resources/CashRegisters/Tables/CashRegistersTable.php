<?php

namespace App\Filament\Resources\CashRegisters\Tables;

use App\Models\CashRegister;
use App\Services\SaleTicket;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CashRegistersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // La policy de borrar mira si tiene turnos: se precarga para no consultar por fila.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withExists('sessions')->with('openSession.openedBy'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('state')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (CashRegister $record): string => $record->openSession === null ? 'Cerrada' : 'Abierta')
                    ->color(fn (string $state): string => $state === 'Abierta' ? 'success' : 'gray')
                    ->description(fn (CashRegister $record): ?string => $record->openSession === null
                        ? null
                        : 'Por '.$record->openSession->openedBy->name.' desde las '.$record->openSession->opened_at->format('H:i')),
                TextColumn::make('paper_width')
                    ->label('Papel')
                    ->formatStateUsing(fn (?int $state): string => SaleTicket::PAPER_WIDTHS[$state] ?? 'El general')
                    ->placeholder('El general'),
                TextColumn::make('position')
                    ->label('Orden')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->defaultSort('position')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
