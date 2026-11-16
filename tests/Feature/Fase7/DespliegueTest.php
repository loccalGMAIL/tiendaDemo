<?php

/**
 * Scripts de composer y documentación de instalación: lo que un técnico sigue
 * paso a paso al dar de alta o actualizar un cliente.
 */
function composerScripts(): array
{
    return json_decode(file_get_contents(base_path('composer.json')), true)['scripts'];
}

it('composer demo enciende los módulos antes de cargar la demostración', function () {
    $demo = composerScripts()['demo'];

    $fresh = array_search('@php artisan migrate:fresh --seed --force', $demo, true);
    $enable = array_search('@php artisan modules:enable --all', $demo, true);
    $seed = array_search('@php artisan db:seed --class=DemoSeeder --force', $demo, true);

    expect($fresh)->toBe(0)->and($enable)->toBe(1)->and($seed)->toBe(2);
});

it('composer deploy actualiza sin compilar assets', function () {
    $deploy = implode("\n", composerScripts()['deploy']);

    expect($deploy)->toContain('composer install --no-dev --optimize-autoloader')
        ->toContain('migrate --force')
        ->toContain('storage:link')
        ->toContain('optimize')
        ->not->toContain('npm');
});

it('los assets compilados viajan con el repositorio', function () {
    expect(file_get_contents(base_path('.gitignore')))->not->toMatch('#^/public/build$#m')
        ->and(file_exists(public_path('build/manifest.json')))->toBeTrue();
});

it('el README documenta cada variable propia de la plataforma', function () {
    $readme = file_get_contents(base_path('README.md'));

    $variables = ['LANDING_DOMAIN', 'PANEL_DOMAIN', 'SUPERADMIN_DOMAIN', 'TRUSTED_PROXIES', 'ADMIN_EMAIL', 'ADMIN_PASSWORD',
        'SUPERADMIN_EMAIL', 'SUPERADMIN_PASSWORD', 'SELLER_SEES_ALL_SALES', 'DEMO_PASSWORD', 'GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET'];

    foreach ($variables as $variable) {
        expect($readme)->toContain($variable);
        expect(file_get_contents(base_path('.env.example')))->toContain($variable.'=');
    }
});

it('el README cubre instalación, alta de cliente, despliegue y actualización', function () {
    $readme = file_get_contents(base_path('README.md'));

    foreach (['composer setup', 'php artisan db:seed', 'composer demo', 'modules:enable', 'storage:link', 'APP_ENV=production',
        'composer deploy', '## Alta de un cliente nuevo', '## Despliegue en producción', '## Actualizar una instalación', 'docs/decisiones.md'] as $text) {
        expect($readme)->toContain($text);
    }

    foreach (['docs/decisiones.md', 'docs/modulos.md', 'docs/temas.md', 'CHANGELOG.md', 'PLAN-BASE.md'] as $file) {
        expect(file_exists(base_path($file)))->toBeTrue("falta {$file}");
    }
});

it('la versión de la plataforma coincide con el CHANGELOG', function () {
    $version = config('platform.version');

    expect(file_get_contents(base_path('CHANGELOG.md')))->toContain("## {$version} ");
});
