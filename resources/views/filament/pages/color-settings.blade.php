<x-filament-panels::page>
    @php($selected = $this->getPalettes()[$this->palette] ?? null)

    <p class="text-sm text-gray-500 dark:text-gray-400">
        Elegí la combinación de colores del tema «{{ $this->getThemeName() }}». Se aplica a tu sitio y al panel.
    </p>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" role="radiogroup" aria-label="Combinaciones de colores">
        @foreach ($this->getPalettes() as $key => $palette)
            <label
                wire:key="palette-{{ $key }}"
                class="flex cursor-pointer flex-col gap-3 rounded-xl border p-4 transition {{ $this->palette === $key ? 'border-primary-600 ring-2 ring-primary-600' : 'border-gray-200 hover:border-gray-400 dark:border-white/10' }}"
            >
                <span class="flex items-center gap-2">
                    <input type="radio" class="sr-only" name="palette" value="{{ $key }}" wire:model.live="palette">
                    <span class="font-medium">{{ $palette['name'] }}</span>
                </span>

                <span class="flex gap-1.5" aria-hidden="true">
                    @foreach (['primary', 'accent', 'surface', 'text'] as $token)
                        <span class="h-7 w-7 rounded-full border border-black/10" style="background-color: {{ $palette[$token] }}"></span>
                    @endforeach
                </span>
            </label>
        @endforeach
    </div>

    @if ($selected)
        <x-filament::section heading="Vista previa" description="Así se ve la combinación elegida.">
            <div
                class="rounded-xl p-6"
                style="background-color: {{ $selected['surface'] }}; color: {{ $selected['text'] }};"
                data-preview="{{ $this->palette }}"
            >
                <p class="text-xl font-semibold">{{ app(\App\Services\SiteContent::class)->name() }}</p>
                <p class="mt-1 text-sm opacity-80">Un texto de ejemplo sobre el fondo de la combinación.</p>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <span class="rounded-full px-4 py-2 text-sm font-medium" style="background-color: {{ $selected['primary'] }}; color: {{ $selected['on_primary'] }};">Ingresar al sistema</span>
                    <span class="rounded-full px-4 py-2 text-sm font-medium" style="background-color: {{ $selected['accent'] }}; color: {{ $selected['text'] }};">Detalle</span>
                </div>
            </div>
        </x-filament::section>
    @endif

    <div>
        <x-filament::button wire:click="save" icon="heroicon-m-check">
            Guardar colores
        </x-filament::button>
    </div>
</x-filament-panels::page>
