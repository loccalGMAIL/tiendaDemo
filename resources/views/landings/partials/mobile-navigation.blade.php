@if (count(array_diff($sections, ['hero', 'catalog'])) > 0)
    <details class="group relative md:hidden">
        <summary aria-label="Menú de secciones" class="flex size-10 cursor-pointer list-none items-center justify-center rounded-full border border-current/20 hover:bg-accent/10 focus-visible:outline-2 focus-visible:outline-offset-2 [&::-webkit-details-marker]:hidden">
            <svg class="size-5 group-open:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <path d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <svg class="hidden size-5 group-open:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <path d="M6 6l12 12M18 6L6 18" />
            </svg>
        </summary>
        <nav aria-label="Secciones del sitio" class="absolute right-0 z-30 mt-3 grid w-64 gap-1 rounded-2xl border border-ink/10 bg-surface p-3 text-sm normal-case tracking-normal text-ink shadow-xl">
            @foreach ($sections as $section)
                @if (! in_array($section, ['hero', 'catalog'], true))
                    <a href="{{ route('landing') }}#{{ $section }}" class="rounded-lg px-4 py-3 hover:bg-accent/10">{{ \App\Services\SiteContent::SECTIONS[$section] }}</a>
                @endif
            @endforeach
        </nav>
    </details>
@endif
