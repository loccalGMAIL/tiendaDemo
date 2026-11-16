<?php

use App\Enums\Module;
use App\Facades\Modules;

it('respeta TEST_MODULES: all enciende los módulos posibles y none deja solo el núcleo', function () {
    config()->set('services.google.client_id', null);

    $expected = env('TEST_MODULES') === 'all';

    foreach (Module::cases() as $module) {
        if ($module === Module::GoogleLogin) {
            expect(Modules::enabled($module))->toBeFalse();

            continue;
        }

        expect(Modules::enabled($module))->toBe($expected);
    }
});
