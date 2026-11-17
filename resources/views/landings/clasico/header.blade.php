<header class="sticky top-0 z-10 border-b border-ink/10 bg-surface/90 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <a href="{{ url('/') }}" class="flex items-center gap-2 text-lg font-semibold">
            @if ($logo = $appearance->logoUrl('light'))
                <img src="{{ $logo }}" alt="{{ $siteContent->name() }}" class="h-9 w-auto sm:h-12 @if ($appearance->logoUrl('dark')) dark:hidden @endif">
                @if ($logoDark = $appearance->logoUrl('dark'))
                    <img src="{{ $logoDark }}" alt="{{ $siteContent->name() }}" class="hidden h-9 w-auto sm:h-12 dark:block">
                @endif
            @else
                <span>{{ $siteContent->name() }}</span>
            @endif
        </a>

        <nav class="hidden items-center gap-6 text-sm md:flex" aria-label="Secciones">
            @foreach ($sections as $section)
                @if (! in_array($section, ['hero', 'catalog'], true))
                    <a href="{{ url('/') }}#{{ $section }}" class="hover:text-brand">{{ \App\Services\SiteContent::SECTIONS[$section] }}</a>
                @endif
            @endforeach
        </nav>

        {{-- Siempre a la vista, también en el celular: el catálogo es el botón principal. --}}
        <div class="flex shrink-0 items-center gap-2 text-sm font-medium">
            @if ($hasCatalog)
                <a href="{{ url('/catalogo') }}" class="rounded-full bg-brand px-4 py-2 text-on-brand shadow-sm hover:opacity-90">Catálogo</a>
            @endif

            <a href="{{ $panelUrl }}" class="rounded-full border border-ink/20 px-4 py-2 hover:border-brand hover:text-brand">
                <span class="sm:hidden">Ingresar</span>
                <span class="hidden sm:inline">Ingresar al sistema</span>
            </a>
        </div>
    </div>
</header>
