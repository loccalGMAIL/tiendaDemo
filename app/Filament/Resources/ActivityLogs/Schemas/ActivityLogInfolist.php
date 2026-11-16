<?php

namespace App\Filament\Resources\ActivityLogs\Schemas;

use App\Models\ActivityLog;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ActivityLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i:s'),
                TextEntry::make('user.name')
                    ->label('Usuario')
                    ->placeholder('—'),
                TextEntry::make('event')
                    ->label('Evento')
                    ->badge(),
                TextEntry::make('description')
                    ->label('Descripción'),
                TextEntry::make('ip_address')
                    ->label('IP')
                    ->placeholder('—'),
                TextEntry::make('user_agent')
                    ->label('Navegador')
                    ->placeholder('—'),
                KeyValueEntry::make('before')
                    ->label('Antes')
                    ->state(fn (ActivityLog $record): array => self::flatten($record->properties['old'] ?? []))
                    ->visible(fn (ActivityLog $record): bool => filled($record->properties['old'] ?? null)),
                KeyValueEntry::make('after')
                    ->label('Después')
                    ->state(fn (ActivityLog $record): array => self::flatten($record->properties['new'] ?? []))
                    ->visible(fn (ActivityLog $record): bool => filled($record->properties['new'] ?? null)),
            ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private static function flatten(array $values): array
    {
        return array_map(
            fn (mixed $value): string => is_scalar($value) || $value === null
                ? (string) $value
                : (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            $values,
        );
    }
}
