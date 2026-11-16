<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $siteContent->name() }}@if (trim($site['tagline']) !== '') — {{ $site['tagline'] }}@endif</title>
<meta name="description" content="{{ $description }}">
@if ($favicon = $appearance->faviconUrl())
    <link rel="icon" href="{{ $favicon }}">
@endif
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $siteContent->name() }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ url()->current() }}">
@if ($share = $appearance->shareImageUrl())
    <meta property="og:image" content="{{ $share }}">
    <meta name="twitter:card" content="summary_large_image">
@endif
{{-- Combinación de colores y tipografía del tema: se aplican sin recompilar. --}}
<style>{!! $appearance->cssVariables() !!}</style>
@vite([$appearance->theme()['css']])
