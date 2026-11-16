<section id="hero" class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 sm:px-6 md:grid-cols-2 md:py-24">
    <div>
        <h1 class="text-4xl font-bold leading-tight sm:text-5xl">{{ $siteContent->heroTitle() }}</h1>

        @if ($subtitle = trim($site['hero_subtitle']) ?: trim($site['tagline']))
            <p class="mt-5 text-lg opacity-80">{{ $subtitle }}</p>
        @endif

        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ $panelUrl }}" class="rounded-full bg-brand px-6 py-3 font-medium text-on-brand hover:opacity-90">
                Ingresar al sistema
            </a>

            @if ($whatsapp = $siteContent->whatsappUrl('Hola, quería hacer una consulta'))
                <a href="{{ $whatsapp }}" class="rounded-full border border-ink/20 px-6 py-3 font-medium hover:border-brand hover:text-brand" rel="noopener">
                    Escribinos por WhatsApp
                </a>
            @endif
        </div>
    </div>

    <div>
        @if ($image = $appearance->assetUrl('hero'))
            <img src="{{ $image }}" alt="" class="aspect-[3/2] w-full rounded-2xl object-cover shadow-lg">
        @else
            <div class="aspect-[3/2] w-full rounded-2xl bg-accent/30" aria-hidden="true"></div>
        @endif
    </div>
</section>
