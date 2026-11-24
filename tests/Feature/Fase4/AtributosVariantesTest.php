<?php

use App\Actions\Variants\GenerateVariants;
use App\Enums\Module;
use App\Facades\Modules;
use App\Filament\Pages\VariantAttributes;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    resetModules();

    Modules::enable(Module::Variants);
    Modules::updateSettings(Module::Variants, ['attributes' => [
        ['name' => 'Talle', 'values' => ['S', 'M']],
    ]]);

    $this->admin = User::factory()->admin()->create();
    $this->seller = User::factory()->seller()->create();
});

it('solo el administrador accede, y solo con el módulo Variantes encendido', function () {
    $this->actingAs($this->seller);
    expect(VariantAttributes::canAccess())->toBeFalse();

    $this->actingAs($this->admin);
    expect(VariantAttributes::canAccess())->toBeTrue()
        ->and(VariantAttributes::shouldRegisterNavigation())->toBeTrue();

    Modules::disable(Module::Variants);

    expect(VariantAttributes::canAccess())->toBeFalse()
        ->and(VariantAttributes::shouldRegisterNavigation())->toBeFalse();
});

it('muestra los atributos guardados', function () {
    $this->actingAs($this->admin);

    $attributes = Livewire::test(VariantAttributes::class)->get('data.attributes');

    expect(array_values($attributes))->toBe([['name' => 'Talle', 'values' => ['S', 'M']]]);
});

it('guarda atributos nuevos, renombrados y reordenados, y registra el cambio', function () {
    $this->actingAs($this->admin);

    Livewire::test(VariantAttributes::class)
        ->fillForm(['attributes' => [
            ['name' => 'Color', 'values' => ['Negro', 'Blanco']],
            ['name' => 'Medida', 'values' => ['34', '36', '38']],
        ]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Modules::setting(Module::Variants, 'attributes'))->toBe([
        ['name' => 'Color', 'values' => ['Negro', 'Blanco']],
        ['name' => 'Medida', 'values' => ['34', '36', '38']],
    ])->and(ActivityLog::query()->where('description', 'Cambio en los atributos de las variantes')->count())->toBe(1);
});

it('exige nombre y valores, y no admite atributos repetidos', function () {
    $this->actingAs($this->admin);

    Livewire::test(VariantAttributes::class)
        ->fillForm(['attributes' => [['name' => '', 'values' => []]]])
        ->call('save')
        ->assertHasFormErrors(['attributes.0.name', 'attributes.0.values']);

    Livewire::test(VariantAttributes::class)
        ->fillForm(['attributes' => [
            ['name' => 'Talle', 'values' => ['S']],
            ['name' => 'Talle', 'values' => ['M']],
        ]])
        ->call('save')
        ->assertHasFormErrors();

    expect(Modules::setting(Module::Variants, 'attributes'))->toBe([['name' => 'Talle', 'values' => ['S', 'M']]]);
});

it('limpia espacios y valores repetidos al guardar', function () {
    $this->actingAs($this->admin);

    Livewire::test(VariantAttributes::class)
        ->fillForm(['attributes' => [['name' => '  Talle  ', 'values' => [' S ', 'S', 'M']]]])
        ->call('save');

    expect(Modules::setting(Module::Variants, 'attributes'))->toBe([['name' => 'Talle', 'values' => ['S', 'M']]]);
});

it('no modifica las variantes ya creadas y las nuevas usan los valores nuevos', function () {
    $product = Product::factory()->create();
    app(GenerateVariants::class)->handle($product, ['Talle' => ['S', 'M']], $this->admin);

    $this->actingAs($this->admin);

    Livewire::test(VariantAttributes::class)
        ->fillForm(['attributes' => [['name' => 'Talle', 'values' => ['M', 'L']]]])
        ->call('save');

    expect($product->variants()->where('name', '!=', '')->pluck('name')->all())->toBe(['S', 'M']);

    app(GenerateVariants::class)->handle($product, ['Talle' => ['M', 'L']], $this->admin);

    expect($product->variants()->where('name', '!=', '')->pluck('name')->all())->toBe(['S', 'M', 'L']);
});
