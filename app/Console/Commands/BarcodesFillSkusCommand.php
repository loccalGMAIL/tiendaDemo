<?php

namespace App\Console\Commands;

use App\Actions\Variants\AssignMissingVariantSkus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('barcodes:fill-skus')]
#[Description('Asigna SKU a las variantes que no lo tienen, para poder imprimir su código de barras')]
class BarcodesFillSkusCommand extends Command
{
    public function handle(AssignMissingVariantSkus $action): int
    {
        $this->info('Variantes completadas: '.$action->handle());

        return self::SUCCESS;
    }
}
