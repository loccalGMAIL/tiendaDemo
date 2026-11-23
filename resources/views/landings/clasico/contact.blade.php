<section id="contact" class="border-t border-ink/10 bg-accent/10">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <h2 class="text-3xl font-bold">Contacto</h2>

        <dl class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @if (trim($site['hours']) !== '')
                <div>
                    <dt class="text-sm font-medium opacity-60">Horarios de atención</dt>
                    <dd class="mt-1 whitespace-pre-line">{{ $site['hours'] }}</dd>
                </div>
            @endif
            @if (trim($site['address']) !== '')
                <div>
                    <dt class="text-sm font-medium opacity-60">Dirección</dt>
                    <dd class="mt-1">{{ $site['address'] }}</dd>
                </div>
            @endif

            @if (trim($site['phone']) !== '')
                <div>
                    <dt class="text-sm font-medium opacity-60">Teléfono</dt>
                    <dd class="mt-1"><a href="tel:{{ preg_replace('/[^\d+]/', '', $site['phone']) }}" class="hover:text-brand">{{ $site['phone'] }}</a></dd>
                </div>
            @endif

            @if ($whatsapp = $siteContent->whatsappUrl())
                <div>
                    <dt class="text-sm font-medium opacity-60">WhatsApp</dt>
                    <dd class="mt-1"><a href="{{ $whatsapp }}" class="hover:text-brand" rel="noopener">{{ $site['whatsapp'] }}</a></dd>
                </div>
            @endif

            @if (trim($site['email']) !== '')
                <div>
                    <dt class="text-sm font-medium opacity-60">Correo</dt>
                    <dd class="mt-1"><a href="mailto:{{ $site['email'] }}" class="hover:text-brand">{{ $site['email'] }}</a></dd>
                </div>
            @endif
        </dl>

        @if ($siteContent->socials() !== [])
            <p class="mt-8 flex flex-wrap gap-4">
                @foreach ($siteContent->socials() as $label => $url)
                    <a href="{{ $url }}" class="rounded-full border border-ink/20 px-4 py-1.5 text-sm hover:border-brand hover:text-brand" rel="noopener">{{ $label }}</a>
                @endforeach
            </p>
        @endif
    </div>
</section>
