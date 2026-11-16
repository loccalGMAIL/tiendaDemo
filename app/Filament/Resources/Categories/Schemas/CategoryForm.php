<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),
            TextInput::make('slug')
                ->label('Slug')
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->helperText('Se arma solo a partir del nombre si lo dejás vacío.'),
            TextInput::make('position')
                ->label('Orden')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->default(0),
            Toggle::make('active')
                ->label('Activa')
                ->default(true),
        ]);
    }
}
