<?php

use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\BarcodeLabelsPrintController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PurchaseOrderPrintController;
use App\Http\Controllers\ReportPrintController;
use Illuminate\Support\Facades\Route;

// Cualquier host que no sea uno de los tres de config/domains.php responde 404.

Route::domain(config('domains.landing'))->group(function () {
    Route::get('/', LandingController::class)->name('landing');

    // Catálogo web: con el módulo apagado estas rutas responden 404. Público, así que con tope por IP
    // (120 por minuto, de sobra para navegar) para que un robot no pueda hacer búsquedas sin parar.
    Route::middleware(['module:catalog', 'throttle:120,1'])->group(function () {
        Route::get('/catalogo', [CatalogController::class, 'index'])->name('catalog.index');
        Route::get('/catalogo/{slug}', [CatalogController::class, 'category'])->where('slug', '[a-z0-9\-]+')->name('catalog.category');
        Route::get('/producto/{slug}', [CatalogController::class, 'show'])->where('slug', '[a-z0-9\-]+')->name('catalog.product');
    });
});

Route::domain(config('domains.panel'))->get('/up', fn () => response('OK'))->name('health');

// Login con Google (módulo Login con Google, panel admin): con el módulo apagado responden 404.
Route::domain(config('domains.panel'))
    ->middleware(['module:google-login', 'throttle:10,1'])
    ->prefix('auth/google')
    ->group(function () {
        Route::get('redirect', [GoogleLoginController::class, 'redirect'])->name('auth.google.redirect');
        Route::get('callback', [GoogleLoginController::class, 'callback'])->name('auth.google.callback');
    });

// Orden de compra imprimible (módulo Compras): con el módulo apagado responde 404; solo administradores.
Route::domain(config('domains.panel'))
    ->middleware('module:purchases')
    ->get('/compras/ordenes/{order}/imprimir', PurchaseOrderPrintController::class)
    ->whereNumber('order')
    ->name('purchase-orders.print');

// Etiquetas con código de barras (módulo Códigos de barras): con el módulo apagado responde 404; solo administradores.
Route::domain(config('domains.panel'))
    ->middleware('module:barcodes')
    ->get('/etiquetas/imprimir', BarcodeLabelsPrintController::class)
    ->name('barcode-labels.print');

// Informes imprimibles: con el módulo apagado responden 404; solo para administradores.
Route::domain(config('domains.panel'))
    ->middleware('module:reports')
    ->get('/informes/{report}/imprimir', ReportPrintController::class)
    ->where('report', '[a-z\-]+')
    ->name('reports.print');
