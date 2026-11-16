<?php

namespace App\Filament\Resources\Products\Pages;

use App\Actions\Products\UpdateProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Product) {
            throw new LogicException('El registro a editar no es un producto.');
        }

        return app(UpdateProduct::class)->handle($record, $data);
    }
}
