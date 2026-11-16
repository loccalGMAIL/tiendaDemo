<header class="bg-night text-night-ink">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-5 sm:px-6">
        <a href="{{ url('/') }}" class="font-display text-xl tracking-wide">
            @if ($logo = $appearance->logoUrl('dark') ?? $appearance->logoUrl('light'))
                <img src="{{ $logo }}" alt="{{ $siteContent->name() }}" class="h-9 w-auto">
            @else
                <span>{{ $siteContent->name() }}</span>
            @endif
        </a>

        <nav class="hidden items-center gap-8 text-sm uppercase tracking-widest md:flex" aria-label="Secciones">
            @foreach ($sections as $section)
                @if (! in_array($section, ['hero', 'catalog'], true))
                    <a href="{{ url('/') }}#{{ $section }}" class="opacity-80 hover:text-brand hover:opacity-100">{{ \App\Services\SiteContent::SECTIONS[$section] }}</a>
                @endif
            @endforeach

            @if ($hasCatalog)
                <a href="{{ url('/catalogo') }}" class="opacity-80 hover:text-brand hover:opacity-100">Catálogo</a>
            @endif
        </nav>

        <a href="{{ $panelUrl }}" class="border border-brand px-5 py-2 text-xs uppercase tracking-widest text-brand hover:bg-brand hover:text-on-brand">
            Ingresar al sistema
        </a>
    </div>
</header>
