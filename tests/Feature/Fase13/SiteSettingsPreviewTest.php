<?php

use App\Filament\Pages\SiteSettings;
use App\Models\User;
use App\Services\Appearance;
use App\Services\SiteContent;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('previsualiza el texto sin publicarlo hasta guardar', function () {
    $this->actingAs(User::factory()->admin()->create());

    $component = Livewire::test(SiteSettings::class)
        ->set('activeSection', 'hero')
        ->set('data.hero_title', 'Una portada en borrador')
        ->assertSet('hasUnsavedChanges', true);

    expect($component->get('previewHtml'))->toContain('Una portada en borrador');
    expect(app(SiteContent::class)->heroTitle())->not->toBe('Una portada en borrador');

    $component->call('save')->assertHasNoFormErrors()->assertSet('hasUnsavedChanges', false);

    expect(app(SiteContent::class)->heroTitle())->toBe('Una portada en borrador');
});

it('ordena las pestañas según la estructura y omite secciones deshabilitadas', function () {
    $this->actingAs(User::factory()->admin()->create());
    app(SiteContent::class)->saveStructure(['contact', 'catalog', 'hero', 'value']);

    Livewire::test(SiteSettings::class)
        ->assertSeeHtmlInOrder(['data-tab-key="business"', 'data-tab-key="contact"', 'data-tab-key="hero"', 'data-tab-key="value"'])
        ->assertDontSeeHtml('data-tab-key="catalog"')
        ->assertDontSeeHtml('data-tab-key="services"');
});

it('muestra el nombre y el lema del borrador en la pestaña Negocio', function () {
    $this->actingAs(User::factory()->admin()->create());

    $component = Livewire::test(SiteSettings::class)
        ->set('data.name', 'Mi nuevo negocio')
        ->set('data.tagline', 'Un lema nuevo');

    expect($component->get('previewHtml'))->toContain('Mi nuevo negocio', 'Un lema nuevo');
    expect(app(SiteContent::class)->name())->not->toBe('Mi nuevo negocio');
});

it('previsualiza únicamente la sección activa con el tema y colores elegidos', function (string $theme) {
    $this->actingAs(User::factory()->admin()->create());
    app(Appearance::class)->setTheme($theme);

    $component = Livewire::test(SiteSettings::class)
        ->set('activeSection', 'services')
        ->set('data.service_items', [['title' => 'Servicio en borrador', 'text' => 'Sin publicar']]);

    expect($component->get('previewHtml'))
        ->toContain('data-theme="'.$theme.'"', 'Servicio en borrador', app(Appearance::class)->cssVariables())
        ->not->toContain('id="hero"', 'id="contact"');

    expect(app(SiteContent::class)->all()['service_items'])->toBe([]);
})->with(['clasico', 'boutique']);

it('previsualiza las imágenes temporales sin almacenarlas como contenido publicado', function (string $field, string $section) {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());

    $component = Livewire::test(SiteSettings::class)
        ->set('activeSection', $section)
        ->set('data.'.$field, UploadedFile::fake()->image('nueva.jpg'));

    expect($component->get('previewHtml'))->toContain('preview-file');
    expect(app(SiteContent::class)->all()[$field])->toBeNull();
    Storage::disk('public')->assertDirectoryEmpty('site');
})->with([
    'portada' => ['hero_image', 'hero'],
    'negocio' => ['about_image', 'about'],
]);

it('permite quitar imágenes y previsualizar elementos de galería aún sin guardar', function () {
    Storage::fake('public');
    Storage::disk('public')->put('site/anterior.jpg', 'imagen');
    app(SiteContent::class)->save(['hero_image' => 'site/anterior.jpg']);
    $this->actingAs(User::factory()->admin()->create());

    $component = Livewire::test(SiteSettings::class)
        ->set('activeSection', 'hero')
        ->set('data.hero_image', []);

    expect($component->get('previewHtml'))->not->toContain('storage/site/anterior.jpg');
    expect(app(SiteContent::class)->all()['hero_image'])->toBe('site/anterior.jpg');

    $component
        ->set('activeSection', 'gallery')
        ->set('data.gallery_items', ['item-nuevo' => ['image' => null, 'alt' => 'Nueva foto']])
        ->set('data.gallery_items.item-nuevo.image', UploadedFile::fake()->image('galeria.jpg'));

    expect($component->get('previewHtml'))->toContain('preview-file', 'Nueva foto');
    expect(app(SiteContent::class)->all()['gallery_items'])->toBe([]);
});

it('mantiene los agregables plegados con etiquetas identificables y campos reactivos', function (string $field, string $labelField, string $section) {
    app(SiteContent::class)->save([$field => [[$labelField => 'Elemento reconocible']]]);
    $this->actingAs(User::factory()->admin()->create());

    $page = Livewire::test(SiteSettings::class)->set('activeSection', $section);
    $repeater = collect($page->instance()->form->getFlatFields())
        ->first(fn ($component): bool => $component instanceof Repeater && $component->getName() === $field);

    expect($repeater)->toBeInstanceOf(Repeater::class)
        ->and($repeater->isCollapsed())->toBeTrue()
        ->and($repeater->getItemLabel(array_key_first($repeater->getRawState())))->toBe('Elemento reconocible')
        ->and($repeater->isLive())->toBeTrue();
})->with([
    'servicios' => ['service_items', 'title', 'services'],
    'galería' => ['gallery_items', 'alt', 'gallery'],
    'puntos' => ['value_items', 'title', 'value'],
    'preguntas' => ['faq_items', 'question', 'faq'],
]);

it('escapa texto en la vista previa y no permite renderizar una sección arbitraria', function () {
    $this->actingAs(User::factory()->admin()->create());
    $unsafe = '<script>alert("preview")</script>';

    $component = Livewire::test(SiteSettings::class)
        ->set('activeSection', 'hero')
        ->set('data.hero_title', $unsafe);

    expect($component->get('previewHtml'))->toContain(e($unsafe))->not->toContain($unsafe);

    $component->set('activeSection', '../../secret');

    expect($component->get('previewHtml'))->toContain('Ingresar al sistema')->not->toContain('../../secret');
});

it('agrega un punto y actualiza la vista previa al editarlo y eliminarlo', function () {
    $this->actingAs(User::factory()->admin()->create());
    $page = Livewire::test(SiteSettings::class)->set('activeSection', 'value');
    $repeater = collect($page->instance()->form->getFlatFields())
        ->first(fn ($component): bool => $component instanceof Repeater && $component->getName() === 'value_items');
    $componentKey = str($repeater->getKey())->after('form.')->toString();

    $page->callAction(TestAction::make('add')->schemaComponent($componentKey));

    $items = $page->get('data.value_items');
    expect($items)->toHaveCount(1);

    $itemKey = array_key_first($items);
    $page->set('data.value_items.'.$itemKey.'.title', 'Atención personalizada');
    expect($page->get('previewHtml'))->toContain('Atención personalizada');

    $page->callAction(TestAction::make('delete')->schemaComponent($componentKey)->arguments(['item' => $itemKey]));
    expect($page->get('previewHtml'))->not->toContain('Atención personalizada');
    expect(app(SiteContent::class)->all()['value_items'])->toBe([]);
});

it('permite previsualizar contenido incompleto sin ejecutar la validación del guardado', function () {
    $this->actingAs(User::factory()->admin()->create());

    $component = Livewire::test(SiteSettings::class)
        ->set('activeSection', 'value')
        ->set('data.value_items', [['title' => null, 'text' => null]])
        ->assertHasNoFormErrors();

    expect($component->get('previewHtml'))->toContain('id="value"');

    $component->call('save')->assertHasFormErrors();
    expect(app(SiteContent::class)->all()['value_items'])->toBe([]);
});
