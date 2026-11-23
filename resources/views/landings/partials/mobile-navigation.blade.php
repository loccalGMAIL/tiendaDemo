@if (count(array_diff($sections, ['hero', 'catalog'])) > 0)
    <details class="relative md:hidden">
        <summary class="cursor-pointer rounded-full border border-current/20 px-4 py-2 text-sm">Menú</summary>
        <nav aria-label="Secciones del sitio" class="absolute right-0 z-30 mt-3 grid w-64 gap-1 rounded-2xl border border-ink/10 bg-surface p-3 text-sm normal-case tracking-normal text-ink shadow-xl">
            @foreach ($sections as $section)
                @if (! in_array($section, ['hero', 'catalog'], true))
                    <a href="{{ route('landing') }}#{{ $section }}" class="rounded-lg px-4 py-3 hover:bg-accent/10">{{ \App\Services\SiteContent::SECTIONS[$section] }}</a>
                @endif
            @endforeach
        </nav>
    </details>
@endif
