@extends('landings.boutique.layout')

@section('content')
    <article class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <nav class="text-xs uppercase tracking-widest opacity-60" aria-label="Ruta de navegación">
            <a href="{{ url('/') }}" class="hover:text-brand">Inicio</a>
            <span aria-hidden="true">/</span>
            <a href="{{ url('/catalogo') }}" class="hover:text-brand">Catálogo</a>
            @if ($product->category)
                <span aria-hidden="true">/</span>
                <a href="{{ url('/catalogo/'.$product->category->slug) }}" class="hover:text-brand">{{ $product->category->name }}</a>
            @endif
        </nav>

        <div class="mt-8 grid gap-12 md:grid-cols-2">
            <div>
                @if ($images !== [])
                    <img src="{{ $images[0] }}" alt="{{ $product->name }}" class="aspect-[4/5] w-full object-cover">

                    @if (count($images) > 1)
                        <ul class="mt-3 grid grid-cols-5 gap-2">
                            @foreach ($images as $thumb)
                                <li><a href="{{ $thumb }}" target="_blank" rel="noopener"><img src="{{ $thumb }}" alt="" loading="lazy" class="aspect-square w-full object-cover"></a></li>
                            @endforeach
                        </ul>
                    @endif
                @else
                    <div class="flex aspect-[4/5] w-full items-center justify-center bg-accent/20 font-display text-8xl text-brand/60" aria-hidden="true">{{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}</div>
                @endif
            </div>

            <div>
                @if (trim((string) $product->brand) !== '')
                    <p class="text-xs uppercase tracking-widest opacity-60">{{ $product->brand }}</p>
                @endif

                <h1 class="mt-2 font-display text-4xl sm:text-5xl">{{ $product->name }}</h1>
                <div class="mt-4 h-px w-16 bg-brand"></div>

                <p class="mt-6 text-xl uppercase tracking-widest">{{ $product->catalogPriceLabel() }}</p>

                @if ($product->isSoldOut())
                    <p class="mt-3 inline-block bg-night px-4 py-1 text-xs uppercase tracking-widest text-night-ink">Agotado</p>
                @endif

                @if (trim((string) $product->description) !== '')
                    <div class="mt-8 opacity-90">{!! nl2br(e($product->description)) !!}</div>
                @endif

                @if ($catalogVariants !== [])
                    <div class="mt-8">
                        <h2 class="text-xs uppercase tracking-widest opacity-70">Opciones</h2>
                        <ul class="mt-3 flex flex-wrap gap-2">
                            @foreach ($catalogVariants as $variant)
                                <li class="border px-4 py-1.5 text-sm {{ $variant['sold_out'] ? 'border-ink/15 opacity-60' : 'border-ink/40' }}">
                                    <span class="{{ $variant['sold_out'] ? 'line-through' : '' }}">{{ $variant['name'] }}</span>@if ($variant['sold_out']) <span class="sr-only">(agotado)</span><span aria-hidden="true" class="ml-1 text-xs">Agotado</span>@endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($whatsappUrl)
                    <a href="{{ $whatsappUrl }}" rel="noopener" class="mt-10 inline-block bg-night px-8 py-3 text-xs uppercase tracking-widest text-night-ink hover:opacity-90">
                        Consultar por WhatsApp
                    </a>
                @endif
            </div>
        </div>

        @if ($related->isNotEmpty())
            <section class="mt-20">
                <h2 class="text-center font-display text-3xl">También te puede interesar</h2>
                <div class="mx-auto mt-4 h-px w-16 bg-brand"></div>
                <div class="mt-10 grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($related as $product)
                        @include('landings.boutique.product-card')
                    @endforeach
                </div>
            </section>
        @endif
    </article>
@endsection
