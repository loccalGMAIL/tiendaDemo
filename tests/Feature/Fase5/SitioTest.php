<?php

use App\Enums\ActivityEvent;
use App\Filament\Pages\SiteSettings;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\SiteContent;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();
    $this->url = 'http://'.config('domains.panel').'/site-settings';
});

// --- Valores por defecto --------------------------------------------------

it('sin configurar usa el nombre de la aplicación y no tiene contacto ni contenido', function () {
    $site = app(SiteContent::class);

    expect($site->name())->toBe(config('app.name'))
        ->and($site->heroTitle())->toBe(config('app.name'))
        ->and($site->hasContact())->toBeFalse()
        ->and($site->socials())->toBe([])
        ->and($site->whatsappUrl())->toBeNull()
        ->and($site->all()['value_items'])->toBe([])
        ->and($site->all()['sections'])->toBe(['hero' => true, 'value' => true, 'catalog' => true, 'faq' => true, 'contact' => true]);
});

it('arma el enlace de WhatsApp con solo los dígitos y el mensaje codificado', function () {
    $site = app(SiteContent::class);
    $site->save(array_merge($site->all(), ['whatsapp' => '+54 9 11 1234-5678']));

    expect($site->whatsappUrl())->toBe('https://wa.me/5491112345678')
        ->and($site->whatsappUrl('Hola, ¿cómo están?'))->toBe('https://wa.me/5491112345678?text=Hola%2C%20%C2%BFc%C3%B3mo%20est%C3%A1n%3F');
});

it('lista solo las redes cargadas', function () {
    $site = app(SiteContent::class);
    $site->save(array_merge($site->all(), ['instagram' => ' https://instagram.com/a ', 'tiktok' => 'https://tiktok.com/@a']));

    expect($site->socials())->toBe(['Instagram' => 'https://instagram.com/a', 'TikTok' => 'https://tiktok.com/@a'])
        ->and($site->hasContact())->toBeTrue();
});

it('solo guarda las claves conocidas', function () {
    $site = app(SiteContent::class);
    $site->save(['name' => 'Casa Lila', 'inventada' => 'x']);

    expect($site->name())->toBe('Casa Lila')
        ->and($site->all())->not->toHaveKey('inventada');
});

// --- Pantalla -------------------------------------------------------------

it('solo el administrador edita el sitio', function () {
    $this->actingAs($this->admin)->get($this->url)->assertOk()->assertSee('Sitio');
    $this->actingAs($this->seller)->get($this->url)->assertForbidden();
});

it('muestra el contenido actual y los valores por defecto', function () {
    $this->actingAs($this->admin);

    Livewire::test(SiteSettings::class)
        ->assertSet('data.name', config('app.name'))
        ->assertSet('data.sections.hero', true)
        ->assertSet('data.sections.faq', true);
});

it('guarda el contenido y se ve en la landing', function () {
    $this->actingAs($this->admin);

    Livewire::test(SiteSettings::class)
        ->fillForm([
            'name' => 'Casa Lila',
            'tagline' => 'Ropa con historia',
            'hero_title' => 'Vestite como te sentís',
            'value_items' => [['title' => 'Envíos rápidos', 'text' => 'En 48 horas']],
            'faq_items' => [['question' => '¿Cambios?', 'answer' => 'Sí']],
            'phone' => '011 4444-5555',
            'email' => 'hola@casalila.test',
            'instagram' => 'https://instagram.com/casalila',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Sitio actualizado');

    $site = app(SiteContent::class)->all();

    expect($site['name'])->toBe('Casa Lila')
        ->and($site['value_items'][0]['title'])->toBe('Envíos rápidos')
        ->and($site['faq_items'][0]['question'])->toBe('¿Cambios?');

    $this->withoutVite()
        ->get('http://'.config('domains.landing').'/')
        ->assertSee('Vestite como te sentís')
        ->assertSee('Envíos rápidos')
        ->assertSee('¿Cambios?');
});

it('guarda qué secciones se muestran', function () {
    $this->actingAs($this->admin);

    Livewire::test(SiteSettings::class)
        ->fillForm(['sections' => ['hero' => true, 'value' => false, 'catalog' => false, 'faq' => true, 'contact' => false]])
        ->call('save')
        ->assertHasNoFormErrors();

    $site = app(SiteContent::class);

    expect($site->sectionEnabled('value'))->toBeFalse()
        ->and($site->sectionEnabled('hero'))->toBeTrue()
        ->and($site->sectionEnabled('contact'))->toBeFalse();
});

it('registra en la actividad solo lo que cambió, con antes y después', function () {
    $this->actingAs($this->admin);

    Livewire::test(SiteSettings::class)
        ->fillForm(['tagline' => 'Ropa con historia', 'phone' => '123'])
        ->call('save');

    $log = ActivityLog::where('event', ActivityEvent::Updated)->where('description', 'Cambio en el contenido del sitio')->firstOrFail();

    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->properties['old'])->toBe(['tagline' => '', 'phone' => ''])
        ->and($log->properties['new'])->toBe(['tagline' => 'Ropa con historia', 'phone' => '123']);
});

it('no registra nada si no hubo cambios', function () {
    $this->actingAs($this->admin);

    Livewire::test(SiteSettings::class)->call('save');

    expect(ActivityLog::where('description', 'Cambio en el contenido del sitio')->count())->toBe(0);
});

it('valida los datos', function (array $values, string $field) {
    $this->actingAs($this->admin);

    Livewire::test(SiteSettings::class)
        ->fillForm($values)
        ->call('save')
        ->assertHasFormErrors([$field]);
})->with([
    'nombre obligatorio' => [['name' => ''], 'name'],
    'nombre largo' => [['name' => str_repeat('a', 121)], 'name'],
    'correo inválido' => [['email' => 'no-es-un-correo'], 'email'],
    'whatsapp con letras' => [['whatsapp' => 'llamar al local'], 'whatsapp'],
    'instagram sin url' => [['instagram' => 'casalila'], 'instagram'],
    'facebook sin url' => [['facebook' => 'facebook'], 'facebook'],
    'teléfono muy largo' => [['phone' => str_repeat('1', 41)], 'phone'],
    'lema muy largo' => [['tagline' => str_repeat('a', 161)], 'tagline'],
]);

it('la pregunta frecuente necesita pregunta y respuesta', function () {
    $this->actingAs($this->admin);

    Livewire::test(SiteSettings::class)
        ->fillForm(['faq_items' => [['question' => '¿Hacen envíos?', 'answer' => '']]])
        ->call('save')
        ->assertHasFormErrors();

    expect(app(SiteContent::class)->all()['faq_items'])->toBe([]);
});

it('no admite más de seis puntos en Por qué elegirnos', function () {
    $this->actingAs($this->admin);

    $items = array_map(fn (int $i) => ['title' => "Punto {$i}", 'text' => ''], range(1, 7));

    Livewire::test(SiteSettings::class)
        ->fillForm(['value_items' => $items])
        ->call('save')
        ->assertHasFormErrors(['value_items']);
});

it('el administrador no tiene en esta pantalla nada de tema, logos ni colores libres', function () {
    $this->actingAs($this->admin);

    $html = $this->get($this->url)->assertOk()->getContent();

    expect($html)->not->toContain('Logo para fondo claro')
        ->not->toContain('Favicon')
        ->not->toContain('Combinación de colores');
});
