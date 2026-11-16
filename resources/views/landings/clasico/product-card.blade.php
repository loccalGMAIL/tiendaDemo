@php($image = $product->imageUrls()[0] ?? null)
<a href="{{ url('/producto/'.$product->slug) }}" class="group block overflow-hidden rounded-2xl border border-ink/10 bg-surface transition hover:shadow-md">
    <div class="relative aspect-square bg-accent/20">
        @if ($image)
            <img src="{{ $image }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-cover transition group-hover:scale-105">
        @else
            <div class="flex h-full w-full items-center justify-center text-5xl font-bold text-brand/40" aria-hidden="true">{{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}</div>
        @endif

        @if ($product->isSoldOut())
            <span class="absolute left-3 top-3 rounded-full bg-ink px-3 py-1 text-xs font-medium text-surface">Agotado</span>
        @endif
    </div>

    <div class="p-4">
        @if ($product->category)
            <p class="text-xs uppercase tracking-wide opacity-60">{{ $product->category->name }}</p>
        @endif
        <h3 class="mt-1 font-semibold">{{ $product->name }}</h3>
        <p class="mt-2 font-medium text-brand">{{ $product->catalogPriceLabel() }}</p>
    </div>
</a>
