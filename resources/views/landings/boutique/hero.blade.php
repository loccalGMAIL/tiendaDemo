<section id="hero" class="relative isolate overflow-hidden bg-night text-night-ink">
    @if ($image = $appearance->assetUrl('hero'))
        <img src="{{ $image }}" alt="" class="absolute inset-0 -z-10 h-full w-full object-cover opacity-40">
    @endif

    <div class="mx-auto max-w-3xl px-4 py-24 text-center sm:px-6 md:py-36">
        <h1 class="font-display text-4xl leading-tight sm:text-6xl">{{ $siteContent->heroTitle() }}</h1>

        @if ($subtitle = trim($site['hero_subtitle']) ?: trim($site['tagline']))
            <p class="mx-auto mt-6 max-w-xl text-lg opacity-80">{{ $subtitle }}</p>
        @endif

        <div class="mt-10 flex flex-wrap justify-center gap-4">
            <a href="{{ $panelUrl }}" class="bg-brand px-8 py-3 text-sm uppercase tracking-widest text-on-brand hover:opacity-90">
                Ingresar al sistema
            </a>

            @if ($whatsapp = $siteContent->whatsappUrl('Hola, quería hacer una consulta'))
                <a href="{{ $whatsapp }}" class="border border-night-ink/40 px-8 py-3 text-sm uppercase tracking-widest hover:border-brand hover:text-brand" rel="noopener">
                    WhatsApp
                </a>
            @endif
        </div>
    </div>
</section>
