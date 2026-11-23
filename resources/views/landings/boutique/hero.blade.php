<section id="hero" class="relative isolate overflow-hidden bg-night text-night-ink">
    @if ($image = $siteContent->imageUrl($site['hero_image']) ?? $appearance->assetUrl('hero'))
        <img src="{{ $image }}" alt="{{ $site['hero_image_alt'] }}" fetchpriority="high" class="absolute inset-0 -z-10 h-full w-full object-cover opacity-40">
    @endif

    <div class="mx-auto max-w-3xl px-4 py-24 text-center sm:px-6 md:py-36">
        <h1 class="font-display text-4xl leading-tight sm:text-6xl">{{ $siteContent->heroTitle() }}</h1>

        @if ($subtitle = trim($site['hero_subtitle']) ?: trim($site['tagline']))
            <p class="mx-auto mt-6 max-w-xl text-lg opacity-80">{{ $subtitle }}</p>
        @endif

        @include('landings.partials.hero-actions')
    </div>
</section>
