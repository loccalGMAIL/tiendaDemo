<?php

use App\Filament\Superadmin\Pages\ManageAppearance;
use App\Models\User;
use App\Services\Appearance;
use App\Services\ImageTrimmer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

/**
 * Imagen con un rectángulo negro de 300 × 100 centrado en un lienzo blanco (o transparente).
 */
function logoWithMargins(string $format = 'png', bool $transparent = false, int $size = 1000): string
{
    $image = imagecreatetruecolor($size, $size);

    if ($transparent) {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 255, 255, 255, 127));
    } else {
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 255, 255));
    }

    $x = intdiv($size - 300, 2);
    $y = intdiv($size - 100, 2);
    imagefilledrectangle($image, $x, $y, $x + 299, $y + 99, (int) imagecolorallocate($image, 0, 0, 0));

    ob_start();
    $format === 'jpg' ? imagejpeg($image, null, 95) : imagepng($image);

    return (string) ob_get_clean();
}

function storedSize(string $path): array
{
    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));

    return [$width, $height];
}

it('recorta el margen blanco de un logo y deja el aire pedido', function () {
    Storage::disk('public')->put('branding/logo.png', logoWithMargins());

    expect(app(ImageTrimmer::class)->trim('branding/logo.png', padding: 20))->toBeTrue()
        ->and(storedSize('branding/logo.png'))->toBe([340, 140]);
})->skip(! extension_loaded('gd'), 'Requiere la extensión GD');

it('recorta también un JPG y un PNG con fondo transparente', function () {
    Storage::disk('public')->put('branding/a.jpg', logoWithMargins('jpg'));
    Storage::disk('public')->put('branding/b.png', logoWithMargins('png', transparent: true));

    expect(app(ImageTrimmer::class)->trim('branding/a.jpg', padding: 20))->toBeTrue()
        ->and(app(ImageTrimmer::class)->trim('branding/b.png', padding: 20))->toBeTrue();

    [$width, $height] = storedSize('branding/a.jpg');
    expect($width)->toBeBetween(338, 342)->and($height)->toBeBetween(138, 142)
        ->and(storedSize('branding/b.png'))->toBe([340, 140]);

    $alpha = imagecreatefromstring(Storage::disk('public')->get('branding/b.png'));
    expect((imagecolorat($alpha, 0, 0) >> 24) & 127)->toBe(127);
})->skip(! extension_loaded('gd'), 'Requiere la extensión GD');

it('con una imagen grande detecta el dibujo igual, saltando píxeles', function () {
    Storage::disk('public')->put('branding/grande.png', logoWithMargins(size: 2400));

    app(ImageTrimmer::class)->trim('branding/grande.png', padding: 20);

    [$width, $height] = storedSize('branding/grande.png');
    expect($width)->toBeBetween(338, 346)->and($height)->toBeBetween(138, 146);
})->skip(! extension_loaded('gd'), 'Requiere la extensión GD');

it('es idempotente: recortar de nuevo no cambia el archivo', function () {
    Storage::disk('public')->put('branding/logo.png', logoWithMargins());
    $trimmer = app(ImageTrimmer::class);

    $trimmer->trim('branding/logo.png');
    $once = Storage::disk('public')->get('branding/logo.png');

    expect($trimmer->trim('branding/logo.png'))->toBeFalse()
        ->and(Storage::disk('public')->get('branding/logo.png'))->toBe($once);
})->skip(! extension_loaded('gd'), 'Requiere la extensión GD');

it('no toca una imagen sin margen, vacía, inexistente ni un archivo que no es imagen', function () {
    $full = imagecreatetruecolor(100, 100);
    imagefill($full, 0, 0, (int) imagecolorallocate($full, 255, 255, 255));
    imagefilledrectangle($full, 2, 2, 97, 97, (int) imagecolorallocate($full, 0, 0, 0));
    ob_start();
    imagepng($full);
    Storage::disk('public')->put('branding/lleno.png', (string) ob_get_clean());

    $blank = imagecreatetruecolor(100, 100);
    imagefill($blank, 0, 0, (int) imagecolorallocate($blank, 255, 255, 255));
    ob_start();
    imagepng($blank);
    Storage::disk('public')->put('branding/blanco.png', (string) ob_get_clean());

    Storage::disk('public')->put('branding/texto.png', 'no soy una imagen');

    $trimmer = app(ImageTrimmer::class);

    expect($trimmer->trim('branding/lleno.png'))->toBeFalse()
        ->and($trimmer->trim('branding/blanco.png'))->toBeFalse()
        ->and($trimmer->trim('branding/texto.png'))->toBeFalse()
        ->and($trimmer->trim('branding/fantasma.png'))->toBeFalse()
        ->and(Storage::disk('public')->get('branding/texto.png'))->toBe('no soy una imagen');
})->skip(! extension_loaded('gd'), 'Requiere la extensión GD');

it('al guardar la apariencia recorta los logos pero no el favicon ni la imagen para compartir', function () {
    $this->withoutVite();
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('superadmin'));

    $upload = fn (string $name) => UploadedFile::fake()->createWithContent($name, logoWithMargins());

    Livewire\Livewire::actingAs(User::factory()->superadmin()->create())
        ->test(ManageAppearance::class)
        ->fillForm(['logo_light' => $upload('claro.png'), 'logo_dark' => $upload('oscuro.png'), 'share_image' => $upload('compartir.png')])
        ->call('save')
        ->assertHasNoFormErrors();

    $files = app(Appearance::class)->files();

    expect(storedSize($files['logo_light'])[0])->toBeLessThan(400)
        ->and(storedSize($files['logo_dark'])[0])->toBeLessThan(400)
        ->and(storedSize($files['share_image']))->toBe([1000, 1000]);
})->skip(! extension_loaded('gd'), 'Requiere la extensión GD');
