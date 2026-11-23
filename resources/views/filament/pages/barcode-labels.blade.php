<x-filament-panels::page>
    @php
        $variants = $this->getVariants();
        $total = $this->getTotal();
        $layout = $this->labelLayout();
        $labels = $this->getTotalLabels();
        $withoutCode = $this->getWithoutCodeCount();
        $withStock = \App\Facades\Modules::enabled(\App\Enums\Module::Stock);
    @endphp

    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <label class="fi-fo-field-label-content mb-1 block text-sm font-medium" for="labels-search">Buscar producto</label>
            <x-filament::input.wrapper>
                <x-filament::input id="labels-search" type="search" placeholder="Nombre, SKU o variante" wire:model.live.debounce.300ms="search" />
            </x-filament::input.wrapper>
        </div>

        <div>
            <label class="fi-fo-field-label-content mb-1 block text-sm font-medium" for="labels-category">Categoría</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="labels-category" wire:model.live="categoryId">
                    <option value="">Todas</option>
                    @foreach ($this->getCategories() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        @if ($layout->isSheet())
            <div>
                <label class="fi-fo-field-label-content mb-1 block text-sm font-medium" for="labels-start">Empezar en la etiqueta N.º</label>
                <x-filament::input.wrapper>
                    <x-filament::input id="labels-start" type="number" min="1" max="{{ $layout->perPage() }}" step="1" wire:model.live.debounce.400ms="startAt" />
                </x-filament::input.wrapper>
            </div>
        @endif
    </div>

    <p class="text-sm text-gray-500 dark:text-gray-400">
        Escribí cuántas etiquetas imprimir de cada variante. El código de barras es el SKU.
        Formato actual: {{ $layout->isSheet() ? "hoja de {$layout->perPage()} etiquetas de {$layout->width} × {$layout->height} mm" : "rollo de {$layout->width} × {$layout->height} mm" }} (se cambia desde el panel de la plataforma).
    </p>

    @if ($withoutCode > 0)
        <x-filament::section>
            <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                <span>{{ $withoutCode }} {{ $withoutCode === 1 ? 'variante no tiene' : 'variantes no tienen' }} SKU y no se pueden etiquetar.</span>
                <x-filament::button size="sm" color="gray" wire:click="fillMissingSkus" wire:loading.attr="disabled">
                    Completar los SKU que faltan
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    <div class="flex flex-wrap gap-2">
        <x-filament::button size="sm" color="gray" wire:click="oneOfEach">1 de cada una</x-filament::button>
        @if ($withStock)
            <x-filament::button size="sm" color="gray" wire:click="fromStock">Cantidad = stock</x-filament::button>
        @endif
        <x-filament::button size="sm" color="gray" wire:click="clearQuantities">Limpiar</x-filament::button>
    </div>

    <x-filament::section>
        <div class="overflow-x-auto">
            <table class="w-full text-start text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="px-3 py-2 text-start font-semibold">Producto</th>
                        <th class="px-3 py-2 text-start font-semibold">Código (SKU)</th>
                        @if ($withStock)
                            <th class="px-3 py-2 text-end font-semibold">Stock</th>
                        @endif
                        <th class="w-40 px-3 py-2 text-start font-semibold">Etiquetas</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($variants as $variant)
                        @php($code = $variant->barcode())
                        <tr class="border-b border-gray-100 dark:border-white/5" wire:key="variant-{{ $variant->id }}">
                            <td class="px-3 py-1.5">{{ $variant->fullName() }}</td>
                            <td class="px-3 py-1.5 {{ $code === null ? 'text-danger-600' : 'text-gray-500' }}">{{ $code ?? 'Sin SKU' }}</td>
                            @if ($withStock)
                                <td class="px-3 py-1.5 text-end">{{ $variant->stock }}</td>
                            @endif
                            <td class="px-3 py-1.5">
                                <x-filament::input.wrapper :disabled="$code === null">
                                    <x-filament::input type="number" min="0" max="{{ \App\Http\Controllers\BarcodeLabelsPrintController::MAX_PER_VARIANT }}" step="1" inputmode="numeric" :disabled="$code === null" wire:model.live.debounce.400ms="quantities.{{ $variant->id }}" />
                                </x-filament::input.wrapper>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $withStock ? 4 : 3 }}" class="px-3 py-6 text-center text-gray-500">No hay productos para mostrar con esos filtros.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($total > $variants->count())
            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                Mostrando {{ $variants->count() }} de {{ $total }} variantes. Filtrá por categoría o buscá para ver el resto.
            </p>
        @endif
    </x-filament::section>

    <div>
        @if ($labels > 0)
            <x-filament::button tag="a" :href="$this->getPrintUrl()" target="_blank" icon="heroicon-m-printer">
                Imprimir {{ $labels }} {{ $labels === 1 ? 'etiqueta' : 'etiquetas' }}
            </x-filament::button>
        @else
            <x-filament::button disabled icon="heroicon-m-printer">Imprimir etiquetas</x-filament::button>
        @endif
    </div>
</x-filament-panels::page>
