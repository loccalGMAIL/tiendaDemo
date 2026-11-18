<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ReportPrintController;
use Illuminate\Support\Facades\Route;

// Cualquier host que no sea uno de los tres de config/domains.php responde 404.

Route::domain(config('domains.landing'))->group(function () {
    Route::get('/', LandingController::class)->name('landing');

    // Catálogo web: con el módulo apagado estas rutas responden 404.
    Route::middleware('module:catalog')->group(function () {
        Route::get('/catalogo', [CatalogController::class, 'index'])->name('catalog.index');
        Route::get('/catalogo/{slug}', [CatalogController::class, 'category'])->where('slug', '[a-z0-9\-]+')->name('catalog.category');
        Route::get('/producto/{slug}', [CatalogController::class, 'show'])->where('slug', '[a-z0-9\-]+')->name('catalog.product');
    });
});

Route::domain(config('domains.panel'))->get('/up', fn () => response('OK'))->name('health');

// Informes imprimibles: con el módulo apagado responden 404; solo para administradores.
Route::domain(config('domains.panel'))
    ->middleware('module:reports')
    ->get('/informes/{report}/imprimir', ReportPrintController::class)
    ->where('report', '[a-z\-]+')
    ->name('reports.print');
