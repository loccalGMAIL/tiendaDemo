<?php

use Illuminate\Support\Facades\Route;

// Cualquier host que no sea uno de los tres de config/domains.php responde 404.

Route::domain(config('domains.landing'))->group(function () {
    Route::get('/', function () {
        $scheme = request()->getScheme();
        $port = request()->getPort();
        $panelUrl = $scheme.'://'.config('domains.panel')
            .(in_array($port, [80, 443], true) ? '' : ':'.$port);

        return view('landings.base.index', ['panelUrl' => $panelUrl]);
    })->name('landing');
});

Route::domain(config('domains.panel'))->get('/up', fn () => response('OK'))->name('health');
