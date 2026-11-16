<x-filament-panels::page>
    @php
        $variants = $this->getVariants();
        $total = $this->getTotal();
    @endphp

    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <label class="fi-fo-field-label-content mb-1 block text-sm font-medium" for="stock-search">Buscar producto</label>
            <x-filament::input.wrapper>
                <x-filament::input id="stock-search" type="search" placeholder="Nombre, SKU o variante" wire:model.live.debounce.300ms="search" />
            </x-filament::input.wrapper>
        </div>

        <div>
            <label class="fi-fo-field-label-content mb-1 block text-sm font-medium" for="stock-category">Categoría</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="stock-category" wire:model.live="categoryId">
                    <option value="">Todas</option>
                    @foreach ($this->getCategories() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        <div>
            <label class="fi-fo-field-label-content mb-1 block text-sm font-medium" for="stock-reason">Motivo</label>
            <x-filament::input.wrapper>
                <x-filament::input id="stock-reason" type="text" list="stock-reasons" wire:model="reason" />
            </x-filament::input.wrapper>
            <datalist id="stock-reasons">
                <option value="Stock inicial"></option>
                <option value="Recuento"></option>
            </datalist>
        </div>
    </div>

    <p class="text-sm text-gray-500 dark:text-gray-400">
        Escribí la cantidad contada de cada variante. Lo que dejes vacío no cambia. Al guardar se registra un ajuste por cada diferencia con el stock actual.
    </p>

    <x-filament::section>
        <div class="overflow-x-auto">
            <table class="w-full text-start text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="px-3 py-2 text-start font-semibold">Producto</th>
                        <th class="px-3 py-2 text-start font-semibold">SKU</th>
                        <th class="px-3 py-2 text-end font-semibold">Stock actual</th>
                        <th class="w-40 px-3 py-2 text-start font-semibold">Cantidad contada</th>
                        <th class="w-40 px-3 py-2 text-start font-semibold">Stock mínimo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($variants as $variant)
                        <tr class="border-b border-gray-100 dark:border-white/5" wire:key="variant-{{ $variant->id }}">
                            <td class="px-3 py-1.5">{{ $variant->fullName() }}</td>
                            <td class="px-3 py-1.5 text-gray-500">{{ $variant->sku ?? $variant->product->sku }}</td>
                            <td class="px-3 py-1.5 text-end {{ $variant->stock < 0 ? 'text-danger-600 font-semibold' : '' }}">{{ $variant->stock }}</td>
                            <td class="px-3 py-1.5">
                                <x-filament::input.wrapper>
                                    <x-filament::input type="number" min="0" step="1" inputmode="numeric" placeholder="{{ $variant->stock }}" wire:model="counts.{{ $variant->id }}" />
                                </x-filament::input.wrapper>
                            </td>
                            <td class="px-3 py-1.5">
                                <x-filament::input.wrapper>
                                    <x-filament::input type="number" min="0" step="1" inputmode="numeric" placeholder="{{ $variant->min_stock }}" wire:model="minimums.{{ $variant->id }}" />
                                </x-filament::input.wrapper>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-gray-500">No hay productos para mostrar con esos filtros.</td>
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
        <x-filament::button wire:click="save" wire:loading.attr="disabled" icon="heroicon-m-check">
            Guardar recuento
        </x-filament::button>
    </div>
</x-filament-panels::page>
