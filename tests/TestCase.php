<?php

namespace Tests;

use App\Services\Modules;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // La suite tiene que pasar con solo el núcleo (TEST_MODULES=none, por
        // defecto) y con todos los módulos encendidos (TEST_MODULES=all).
        // Ver el script "test" de composer.json.
        if (env('TEST_MODULES') === 'all') {
            app(Modules::class)->enableAll();
        }
    }
}
