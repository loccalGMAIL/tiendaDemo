<header class="sticky top-0 z-10 border-b border-ink/10 bg-surface/90 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <a href="{{ url('/') }}" class="flex min-w-0 items-center gap-2 text-lg font-semibold">
            @php
                $lightLogo = $appearance->logoUrl('light');
                $darkLogo = $appearance->logoUrl('dark');
                // Con el modo fijo hay un solo logo; en automático van los dos y el dispositivo elige.
                $swapLogos = $appearance->modeKey() === 'auto' && $lightLogo && $darkLogo;
                $logo = $appearance->modeKey() === 'dark' ? ($darkLogo ?? $lightLogo) : $lightLogo;
            @endphp
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $siteContent->name() }}" class="h-9 w-auto sm:h-12 @if ($swapLogos) dark:hidden @endif">
                @if ($swapLogos)
                    <img src="{{ $darkLogo }}" alt="{{ $siteContent->name() }}" class="hidden h-9 w-auto sm:h-12 dark:block">
                @endif
            @else
                <span class="wrap-anywhere">{{ $siteContent->name() }}</span>
            @endif
        </a>

        <nav class="hidden min-w-0 flex-1 flex-wrap items-center justify-center gap-x-5 gap-y-2 text-sm md:flex" aria-label="Secciones">
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

            @include('landings.partials.mobile-navigation')
        </div>
    </div>
</header>
