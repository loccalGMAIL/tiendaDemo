<header class="bg-night text-night-ink">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-5 sm:px-6">
        <a href="{{ url('/') }}" class="font-display text-xl tracking-wide">
            @if ($logo = $appearance->logoUrl('dark') ?? $appearance->logoUrl('light'))
                <img src="{{ $logo }}" alt="{{ $siteContent->name() }}" class="h-9 w-auto sm:h-12">
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
        </nav>

        {{-- Siempre a la vista, también en el celular: el catálogo es el botón principal. --}}
        <div class="flex shrink-0 items-center gap-2 text-xs uppercase tracking-widest">
            @if ($hasCatalog)
                <a href="{{ url('/catalogo') }}" class="border border-brand bg-brand px-4 py-2 text-on-brand hover:opacity-90 sm:px-5">Catálogo</a>
            @endif

            <a href="{{ $panelUrl }}" class="border border-brand px-4 py-2 text-brand hover:bg-brand hover:text-on-brand sm:px-5">
                <span class="sm:hidden">Ingresar</span>
                <span class="hidden sm:inline">Ingresar al sistema</span>
            </a>
        </div>
    </div>
</header>
