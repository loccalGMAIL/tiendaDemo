<section id="value">
    <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
        <h2 class="font-display text-center text-3xl sm:text-4xl">{{ $site['value_title'] }}</h2>
        <div class="mx-auto mt-4 h-px w-16 bg-brand"></div>

        <div class="mt-12 grid items-center gap-10 md:grid-cols-2">
            @if ($image = $appearance->assetUrl('about'))
                <img src="{{ $image }}" alt="" class="aspect-[4/5] w-full object-cover">
            @endif

            <ul class="grid gap-8 {{ $image ?? false ? '' : 'md:col-span-2 md:grid-cols-3' }}">
                @foreach ($site['value_items'] as $item)
                    <li>
                        {{-- Sobre fondo claro el color de marca puede no leerse (p. ej. el dorado): se usa el texto. --}}
                        <h3 class="font-display text-xl">{{ $item['title'] }}</h3>
                        <div class="mt-2 h-px w-10 bg-brand"></div>
                        @if (trim($item['text']) !== '')
                            <p class="mt-3 opacity-80">{{ $item['text'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>
