{{-- $dark = true cuando la tarjeta va sobre un fondo oscuro (sección del catálogo en la landing). --}}
@php($image = $product->imageUrls()[0] ?? null)
@php($dark = $dark ?? false)
<a href="{{ url('/producto/'.$product->slug) }}" class="group block {{ $dark ? 'text-night-ink' : 'text-ink' }}">
    <div class="relative aspect-[4/5] overflow-hidden {{ $dark ? 'bg-night-ink/10' : 'bg-accent/20' }}">
        @if ($image)
            <img src="{{ $image }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <div class="flex h-full w-full items-center justify-center font-display text-6xl text-brand/60" aria-hidden="true">{{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}</div>
        @endif

        @if ($product->isSoldOut())
            <span class="absolute left-0 top-4 bg-brand px-3 py-1 text-xs uppercase tracking-widest text-on-brand">Agotado</span>
        @endif
    </div>

    <div class="pt-4 text-center">
        <h3 class="font-display text-lg">{{ $product->name }}</h3>
        <p class="mt-1 text-sm uppercase tracking-widest {{ $dark ? 'text-brand' : 'opacity-70' }}">{{ $product->catalogPriceLabel() }}</p>
    </div>
</a>
