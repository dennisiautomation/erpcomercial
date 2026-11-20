{{-- Base das páginas do teste grátis (20/11/2026): mesmo sistema visual do site novo (site/redes). --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>@yield('titulo', 'Teste grátis') · ERP Comercial IA365</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%231d1d1f'/%3E%3Ctext x='16' y='22' font-family='Arial' font-size='16' font-weight='bold' fill='white' text-anchor='middle'%3EI%3C/text%3E%3C/svg%3E">
<style>
:root{
  --bg:#ffffff; --bg2:#f5f5f7; --ink:#1d1d1f; --ink2:#6e6e73; --ink3:#86868b;
  --sep:rgba(0,0,0,.09); --line:#d2d2d7;
  --blue:#0071e3; --blue-h:#0077ed; --link:#06c;
  --navy:#0e1533; --navy2:#1a2350; --onNavy:#eef0fb; --onNavy2:#b3b9dc;
  --badD:#d70015; --r:18px;
  --shadow:0 1px 2px rgba(0,0,0,.04),0 8px 32px rgba(0,0,0,.07);
  --ease:cubic-bezier(.16,1,.3,1);
}
*{margin:0;padding:0;box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{font-family:-apple-system,BlinkMacSystemFont,'SF Pro Display','SF Pro Text','Segoe UI',Roboto,Helvetica,Arial,sans-serif;
  background:var(--bg2);color:var(--ink);font-size:17px;line-height:1.55;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility;min-height:100vh;display:flex;flex-direction:column}
a{color:var(--link);text-decoration:none} a:hover{text-decoration:underline}
:focus-visible{outline:2px solid var(--blue);outline-offset:3px;border-radius:4px}
h1,h2,h3{letter-spacing:-.015em;text-wrap:balance}
.wrap{max-width:1080px;margin-inline:auto;padding-inline:22px;width:100%}
.nav{background:rgba(255,255,255,.9);border-bottom:1px solid var(--sep)}
.nav__in{display:flex;align-items:center;gap:1rem;height:52px}
.brand{display:inline-flex;align-items:center;gap:.55rem;font-weight:600;color:var(--ink);font-size:1rem;white-space:nowrap}
.brand:hover{text-decoration:none}
.brand__mark{width:26px;height:26px;border-radius:7px;background:var(--ink);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700}
.nav__cta{margin-left:auto;font-size:.9rem}
.pill{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;background:var(--blue);color:#fff;border:none;cursor:pointer;
  font:inherit;font-size:1.05rem;font-weight:600;padding:.8rem 1.5rem;border-radius:980px;min-height:48px;transition:background .18s var(--ease),transform .18s var(--ease)}
.pill:hover{background:var(--blue-h);text-decoration:none;color:#fff}
.pill:active{transform:scale(.985)}
.pill[disabled]{opacity:.6;cursor:progress}
.pill--block{width:100%}
main{flex:1;padding-block:clamp(1.4rem,4vw,3rem)}
.foot{padding:1.4rem 0 2rem;font-size:.82rem;color:var(--ink3)}
.foot .wrap{display:flex;gap:.5rem 1.4rem;flex-wrap:wrap;justify-content:space-between}
@yield('css')
@media(prefers-reduced-motion:reduce){.pill{transition:none}}
</style>
</head>
<body>
@php $wa = 'https://wa.me/5511917120940?text=' . rawurlencode('Quero ajuda com o teste grátis do ERP Comercial'); @endphp
<header class="nav">
  <div class="wrap nav__in">
    <a class="brand" href="{{ route('site.home') }}"><span class="brand__mark">IA</span> ERP Comercial</a>
    <div class="nav__cta">Já tem conta? <a href="{{ route('login') }}">Entrar</a></div>
  </div>
</header>
<main id="main">@yield('conteudo')</main>
<footer class="foot">
  <div class="wrap">
    <span>© {{ date('Y') }} IA365 · ERP Comercial</span>
    <span>Dúvida no cadastro? <a href="{{ $wa }}" target="_blank" rel="noopener">Chamar no WhatsApp</a></span>
  </div>
</footer>
@yield('js')
</body>
</html>
