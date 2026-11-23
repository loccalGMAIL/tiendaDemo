@php
    $heroImage = $siteContent->imageUrl($site['hero_image']) ?? $appearance->assetUrl('hero');
    $slides = $featured->filter(fn ($product) => $product->imageUrls() !== [])->values();
    $hasHeroImage = $heroImage || $slides->isNotEmpty();
@endphp
<section id="hero" class="mx-auto grid items-center gap-10 px-4 py-20 sm:px-6 md:py-28 {{ $hasHeroImage ? 'max-w-6xl md:grid-cols-2' : 'max-w-3xl' }}">
    <div>
        @if (trim($site['tagline']) !== '' && trim($site['hero_subtitle']) !== '')
            <p class="mb-5 text-sm font-semibold uppercase tracking-widest text-brand">{{ $site['tagline'] }}</p>
        @endif
        <h1 class="text-4xl font-bold leading-tight tracking-tight text-balance sm:text-6xl">{{ $siteContent->heroTitle() }}</h1>

        @if ($subtitle = trim($site['hero_subtitle']) ?: trim($site['tagline']))
            <p class="mt-5 text-lg opacity-80">{{ $subtitle }}</p>
        @endif

        @include('landings.partials.hero-actions')
    </div>

    @if ($hasHeroImage)
    <div>
        @if ($image = $heroImage)
            <img src="{{ $image }}" alt="{{ $site['hero_image_alt'] }}" width="1600" height="1067" fetchpriority="high" class="aspect-[3/2] w-full rounded-2xl object-cover shadow-lg">
        @elseif ($slides->isNotEmpty())
            {{-- Pase de los productos destacados: fundido con un acercamiento suave, solo con CSS.
                 Los porcentajes dependen de la cantidad de fotos, por eso los fotogramas van acá. --}}
            @php([$seconds, $fade, $total] = [4, 1, 4 * $slides->count()])

            @if ($slides->count() > 1)
                <style>
                    @keyframes hero-slide {
                        0% { opacity: 0; visibility: visible; transform: scale(1.08); }
                        {{ round($fade / $total * 100, 2) }}%, {{ round($seconds / $total * 100, 2) }}% { opacity: 1; }
                        {{ round(($seconds + $fade) / $total * 100, 2) }}% { opacity: 0; visibility: hidden; transform: scale(1); }
                        100% { opacity: 0; visibility: hidden; transform: scale(1); }
                    }
                </style>
            @endif

            <div class="hero-slides relative mx-auto aspect-[4/5] w-full max-w-xs overflow-hidden rounded-2xl bg-accent/20 shadow-lg">
                @foreach ($slides as $index => $product)
                    <a href="{{ url('/producto/'.$product->slug) }}" class="hero-slide absolute inset-0 block"
                        @if ($slides->count() > 1) style="animation-duration: {{ $total }}s; animation-delay: {{ $index * $seconds }}s;" @endif>
                        <img src="{{ $product->imageUrls()[0] }}" alt="{{ $product->name }}" @if ($index > 0) loading="lazy" @endif class="h-full w-full object-cover">

                        <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-5 pb-4 pt-16 text-lg font-semibold text-white">{{ $product->name }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
    @endif
</section>
