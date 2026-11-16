<section id="contact" class="bg-night text-night-ink">
    <div class="mx-auto max-w-4xl px-4 py-20 text-center sm:px-6">
        <h2 class="font-display text-3xl sm:text-4xl">Contacto</h2>
        <div class="mx-auto mt-4 h-px w-16 bg-brand"></div>

        <dl class="mt-12 grid gap-8 sm:grid-cols-2">
            @if (trim($site['address']) !== '')
                <div>
                    <dt class="text-xs uppercase tracking-widest opacity-60">Dirección</dt>
                    <dd class="mt-2">{{ $site['address'] }}</dd>
                </div>
            @endif

            @if (trim($site['phone']) !== '')
                <div>
                    <dt class="text-xs uppercase tracking-widest opacity-60">Teléfono</dt>
                    <dd class="mt-2"><a href="tel:{{ preg_replace('/[^\d+]/', '', $site['phone']) }}" class="hover:text-brand">{{ $site['phone'] }}</a></dd>
                </div>
            @endif

            @if ($whatsapp = $siteContent->whatsappUrl())
                <div>
                    <dt class="text-xs uppercase tracking-widest opacity-60">WhatsApp</dt>
                    <dd class="mt-2"><a href="{{ $whatsapp }}" class="hover:text-brand" rel="noopener">{{ $site['whatsapp'] }}</a></dd>
                </div>
            @endif

            @if (trim($site['email']) !== '')
                <div>
                    <dt class="text-xs uppercase tracking-widest opacity-60">Correo</dt>
                    <dd class="mt-2"><a href="mailto:{{ $site['email'] }}" class="hover:text-brand">{{ $site['email'] }}</a></dd>
                </div>
            @endif
        </dl>

        @if ($siteContent->socials() !== [])
            <p class="mt-10 flex flex-wrap justify-center gap-6 text-sm uppercase tracking-widest">
                @foreach ($siteContent->socials() as $label => $url)
                    <a href="{{ $url }}" class="hover:text-brand" rel="noopener">{{ $label }}</a>
                @endforeach
            </p>
        @endif
    </div>
</section>
