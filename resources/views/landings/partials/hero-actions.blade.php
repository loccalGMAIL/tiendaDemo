<div class="mt-8 flex flex-wrap gap-3 {{ $themeKey === 'boutique' ? 'justify-center' : '' }}">
    @if ($hasCatalog)
        <a href="{{ route('catalog.index') }}" class="inline-flex min-h-12 items-center justify-center rounded-full bg-brand px-7 py-3 font-semibold text-on-brand transition hover:opacity-90">
            Ver catálogo
        </a>
    @endif
    @if ($whatsapp = $siteContent->whatsappUrl('Hola, quería hacer una consulta'))
        <a href="{{ $whatsapp }}" class="inline-flex min-h-12 items-center justify-center rounded-full border border-current px-7 py-3 font-medium transition hover:opacity-75" rel="noopener">
            {{ $hasCatalog ? 'Consultar por WhatsApp' : 'Escribinos por WhatsApp' }}
        </a>
    @elseif (in_array('contact', $sections, true))
        <a href="{{ route('landing') }}#contact" class="inline-flex min-h-12 items-center justify-center rounded-full border border-current px-7 py-3 font-medium transition hover:opacity-75">
            Contactanos
        </a>
    @elseif (in_array('services', $sections, true))
        <a href="{{ route('landing') }}#services" class="inline-flex min-h-12 items-center justify-center rounded-full border border-current px-7 py-3 font-medium transition hover:opacity-75">
            Conocé nuestros servicios
        </a>
    @endif
</div>
