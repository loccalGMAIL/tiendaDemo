<?php

use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

// Cualquier host que no sea uno de los tres de config/domains.php responde 404.

Route::domain(config('domains.landing'))->group(function () {
    Route::get('/', LandingController::class)->name('landing');
});

Route::domain(config('domains.panel'))->get('/up', fn () => response('OK'))->name('health');
