<?php

namespace App\Filament\Resources\Products\Pages;

use App\Actions\Products\CreateProduct as CreateProductAction;
use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Pages\BarcodeLabels;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateProductAction::class)->handle($data);
    }

    /**
     * Con el módulo Códigos de barras, según su ajuste, el alta termina en Etiquetas
     * con las variantes del producto nuevo ya cargadas.
     */
    protected function getRedirectUrl(): string
    {
        $product = $this->getRecord();

        if ($product instanceof Product && $this->shouldPrintLabels()) {
            $selection = BarcodeLabels::selectionForNewProduct($product);

            if ($selection !== []) {
                return BarcodeLabels::urlFor($selection);
            }
        }

        return parent::getRedirectUrl();
    }

    private function shouldPrintLabels(): bool
    {
        if (! Modules::enabled(Module::Barcodes) || ! BarcodeLabels::canAccess()) {
            return false;
        }

        return match (Modules::setting(Module::Barcodes, 'labels_on_create', 'ask')) {
            'always' => true,
            'ask' => (bool) ($this->data['print_labels'] ?? false),
            default => false,
        };
    }
}
