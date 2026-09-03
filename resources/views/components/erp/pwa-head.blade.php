{{-- PWA: manifest + ícones. Vai no <head> de todo documento que pode virar app. --}}
@if(config('pwa.ativo'))
<link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
@endif
<meta name="theme-color" content="#1e293b">
<meta name="application-name" content="ERP Comercial">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="ERP Comercial">
<link rel="icon" type="image/png" sizes="192x192" href="{{ url('/pwa/icone-192.png') }}">
<link rel="apple-touch-icon" href="{{ url('/pwa/icone-apple-180.png') }}">
