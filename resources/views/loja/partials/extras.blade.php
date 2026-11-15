{{-- O que acompanha TODAS as páginas da vitrine (14/11/2026, 2ª rodada): botão do WhatsApp, popup, aviso de
     cookies, rastreamento (só depois do consentimento) e os scripts de movimento e dos blocos. --}}
@php
    $limpo = fn (string $chave, string $regex) => preg_match($regex, (string) $loja->opcao($chave)) ? (string) $loja->opcao($chave) : '';
    $rastro = [
        'meta' => $limpo('meta_pixel', '/^\d{6,20}$/'),
        'ga4' => $limpo('ga4', '/^G-[A-Z0-9]{4,15}$/'),
        'ads' => $limpo('google_ads', '/^AW-\d{6,15}$/'),
        'adsRotulo' => $limpo('google_ads_rotulo', '/^[A-Za-z0-9_-]{4,40}$/'),
        'gtm' => $limpo('gtm', '/^GTM-[A-Z0-9]{4,12}$/'),
        'tiktok' => $limpo('tiktok_pixel', '/^[A-Z0-9]{10,30}$/'),
    ];
    $temRastro = (bool) array_filter([$rastro['meta'], $rastro['ga4'], $rastro['ads'], $rastro['gtm'], $rastro['tiktok']]);
    $temAnalise = (bool) ($rastro['ga4'] || $rastro['gtm']);
    $temMarketing = (bool) ($rastro['meta'] || $rastro['ads'] || $rastro['tiktok']);
    $avisoCookies = $temRastro || $loja->opcao('cookies_aviso');
    $popup = $loja->opcao('popup_ativo') && filled($loja->opcao('popup_titulo')) && ! $ctx->rascunho && trim($__env->yieldContent('pagina')) !== 'compra';
    $zap = filled($loja->opcao('whatsapp')) && $loja->opcao('whatsapp_flutuante');
    $cfgJs = ['loja' => $loja->id, 'rastro' => $rastro, 'temRastro' => $temRastro, 'aviso' => (bool) $avisoCookies, 'moeda' => 'BRL'];
@endphp

@if($zap)
<a class="lj-zap" target="_blank" rel="noopener" aria-label="Falar com a loja no WhatsApp"
   href="https://wa.me/55{{ preg_replace('/\D/', '', $loja->opcao('whatsapp')) }}?text={{ rawurlencode($zapTexto ?? ($loja->opcao('whatsapp_mensagem') ?: 'Olá!')) }}">
    <i class="bi bi-whatsapp" aria-hidden="true"></i><span>Fale com a gente</span>
</a>
@endif

@if($popup)
<dialog class="lj-popup @if(filled($loja->opcao('popup_imagem'))) lj-popup--foto @endif" data-lj-popup aria-labelledby="lj-popup-titulo"
        data-gatilho="{{ $loja->opcao('popup_gatilho') }}" data-segundos="{{ (int) $loja->opcao('popup_segundos') }}" data-dias="{{ (int) $loja->opcao('popup_dias') }}">
    <button type="button" class="lj-popup__fechar" data-lj-popup-fechar aria-label="Fechar"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    <div class="lj-popup__dentro">
        @if(filled($loja->opcao('popup_imagem')))
            <div class="lj-popup__foto"><img src="{{ \App\Services\LojaOnline\LojaContexto::arquivo($loja->opcao('popup_imagem')) }}" alt=""></div>
        @endif
        <div class="lj-popup__corpo">
            <h2 id="lj-popup-titulo">{{ $loja->opcao('popup_titulo') }}</h2>
            @if(filled($loja->opcao('popup_texto')))<p>{{ $loja->opcao('popup_texto') }}</p>@endif
            @if($loja->opcao('popup_capturar'))
                <form data-lj-contato="popup" action="{{ $ctx->caminho('contato') }}" novalidate>
                    <label class="lj-so-leitor" for="lj-popup-email">Seu e-mail</label>
                    <input class="lj-campo" id="lj-popup-email" type="email" name="email" placeholder="seu@email.com" autocomplete="email" required>
                    <label class="lj-aceite" style="margin-top:0"><input type="checkbox" name="aceite" value="1" required> <span>Aceito receber novidades e ofertas de {{ $loja->nome }}. <a href="{{ $ctx->url('politica/privacidade') }}">Privacidade</a></span></label>
                    <button class="lj-btn" type="submit">{{ $loja->opcao('popup_botao') ?: 'Quero receber' }}</button>
                    <p class="lj-novidades__retorno" data-lj-contato-retorno role="status" aria-live="polite"></p>
                </form>
                <div class="lj-popup__cupom" data-lj-popup-cupom hidden>
                    <span>Seu cupom:</span>
                    <button type="button" class="lj-cupom" data-lj-copiar-texto=""><b></b> <i class="bi bi-copy" aria-hidden="true"></i></button>
                </div>
            @else
                @if(filled($loja->opcao('popup_cupom')))
                    <div class="lj-popup__cupom"><span>Use o cupom</span>
                        <button type="button" class="lj-cupom" data-lj-copiar-texto="{{ \App\Services\LojaOnline\CupomLoja::normalizar($loja->opcao('popup_cupom')) }}">{{ \App\Services\LojaOnline\CupomLoja::normalizar($loja->opcao('popup_cupom')) }} <i class="bi bi-copy" aria-hidden="true"></i></button>
                    </div>
                @endif
                @php($destinoPopup = \App\Services\LojaOnline\ModelosLoja::limparLink((string) $loja->opcao('popup_link')))
                @if(filled($loja->opcao('popup_botao')))
                    <a class="lj-btn" href="{{ $destinoPopup === '' ? $ctx->url('produtos') : (str_starts_with($destinoPopup, 'https://') ? $destinoPopup : $ctx->url($destinoPopup)) }}">{{ $loja->opcao('popup_botao') }}</a>
                @endif
            @endif
        </div>
    </div>
</dialog>
@endif

@if($avisoCookies)
<div class="lj-cookies" data-lj-cookies hidden role="region" aria-label="Aviso de cookies">
    <div class="lj-caixa lj-cookies__dentro">
        <div>
            <p>
                @if($temRastro)Usamos cookies para a loja funcionar e, se você permitir, para medir as visitas e mostrar anúncios mais relevantes.
                @else Usamos só os cookies necessários para a loja funcionar, como o que guarda o seu carrinho.@endif
                <a href="{{ $ctx->url('politica/privacidade') }}">Política de privacidade</a>
            </p>
            @if($temRastro)
            <div class="lj-cookies__escolhas" data-lj-cookies-escolhas hidden>
                <label><input type="checkbox" checked disabled> <span><strong>Necessários</strong><small>Carrinho e segurança. Sempre ligados.</small></span></label>
                @if($temAnalise)<label><input type="checkbox" data-lj-cookie="analise"> <span><strong>Medição</strong><small>Contam as visitas para a loja melhorar (Google Analytics).</small></span></label>@endif
                @if($temMarketing)<label><input type="checkbox" data-lj-cookie="marketing"> <span><strong>Publicidade</strong><small>Permitem anúncios da loja em outros sites e redes.</small></span></label>@endif
            </div>
            @endif
        </div>
        <div class="lj-cookies__botoes">
            @if($temRastro)
                <button type="button" class="lj-btn lj-btn--linha" data-lj-cookies-escolher>Escolher</button>
                <button type="button" class="lj-btn lj-btn--linha" data-lj-cookies-salvar hidden>Salvar escolha</button>
                <button type="button" class="lj-btn lj-btn--linha" data-lj-cookies-recusar>Só os necessários</button>
                <button type="button" class="lj-btn" data-lj-cookies-aceitar>Aceitar todos</button>
            @else
                <button type="button" class="lj-btn" data-lj-cookies-aceitar>Entendi</button>
            @endif
        </div>
    </div>
</div>
@endif

<script>
(function () {
    var CFG = {!! json_encode($cfgJs, JSON_HEX_TAG) !!};
    var chave = 'lj_cookies_' + CFG.loja;
    var guarda = { ler: function (k) { try { return JSON.parse(localStorage.getItem(k)); } catch (e) { return null; } },
                   gravar: function (k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} } };
    var movimento = document.body.dataset.movimento || 'completo';
    var semMovimento = movimento === 'nenhum' || matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ───────── rastreamento: nada carrega antes do consentimento ───────── */
    var R = LJ.rastro = { pronto: {}, fila: [], consentimento: guarda.ler(chave) || null };
    function script(src) { var s = document.createElement('script'); s.async = true; s.src = src; document.head.appendChild(s); }
    R.ativar = function () {
        var c = R.consentimento, r = CFG.rastro;
        if (!c) return;
        if (((r.ga4 && c.analise) || (r.ads && c.marketing)) && !R.pronto.gtag) {
            R.pronto.gtag = true;
            window.dataLayer = window.dataLayer || [];
            window.gtag = function () { dataLayer.push(arguments); };
            gtag('js', new Date());
            if (r.ga4 && c.analise) gtag('config', r.ga4);
            if (r.ads && c.marketing) gtag('config', r.ads);
            script('https://www.googletagmanager.com/gtag/js?id=' + ((r.ga4 && c.analise) ? r.ga4 : r.ads));
        }
        if (r.gtm && c.analise && !R.pronto.gtm) {
            R.pronto.gtm = true;
            window.dataLayer = window.dataLayer || [];
            dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
            script('https://www.googletagmanager.com/gtm.js?id=' + r.gtm);
        }
        if (r.meta && c.marketing && !R.pronto.meta) {
            R.pronto.meta = true;
            var n = window.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
            window._fbq = n; n.push = n; n.loaded = true; n.version = '2.0'; n.queue = [];
            script('https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', r.meta); fbq('track', 'PageView');
        }
        if (r.tiktok && c.marketing && !R.pronto.tiktok) {
            R.pronto.tiktok = true;
            var t = window.ttq = window.ttq || [];
            t.methods = ['page', 'track', 'identify'];
            t.methods.forEach(function (m) { t[m] = function () { t.push([m].concat([].slice.call(arguments))); }; });
            window.TiktokAnalyticsObject = 'ttq';
            script('https://analytics.tiktok.com/i18n/pixel/events.js?sdkid=' + r.tiktok + '&lib=ttq');
            ttq.page();
        }
        var fila = R.fila; R.fila = [];
        fila.forEach(function (e) { R.evento(e[0], e[1]); });
    };
    /* eventos da loja → nome de cada rede. d = {id, nome, valor, itens:[{id,nome,preco,quantidade}], pedido} */
    R.evento = function (nome, d) {
        d = d || {};
        if (!CFG.temRastro) return;
        if (!R.consentimento) { R.fila.push([nome, d]); return; }
        var itens = (d.itens || []).map(function (i) { return { item_id: String(i.id), item_name: i.nome, price: i.preco, quantity: i.quantidade || 1 }; });
        var ids = (d.itens || []).map(function (i) { return String(i.id); });
        var mapa = { ver_produto: ['view_item', 'ViewContent'], carrinho: ['add_to_cart', 'AddToCart'], fechamento: ['begin_checkout', 'InitiateCheckout'],
                     compra: ['purchase', 'Purchase'], busca: ['search', 'Search'], contato: ['generate_lead', 'Lead'] }[nome];
        if (!mapa) return;
        if (window.gtag && R.pronto.gtag) {
            var g = { currency: CFG.moeda, value: d.valor, items: itens };
            if (nome === 'compra') g.transaction_id = String(d.pedido);
            if (nome === 'busca') g = { search_term: d.termo };
            gtag('event', mapa[0], g);
            if (nome === 'compra' && CFG.rastro.ads && CFG.rastro.adsRotulo && R.consentimento.marketing)
                gtag('event', 'conversion', { send_to: CFG.rastro.ads + '/' + CFG.rastro.adsRotulo, value: d.valor, currency: CFG.moeda, transaction_id: String(d.pedido) });
        }
        if (R.pronto.gtm) dataLayer.push({ event: mapa[0], ecommerce: { currency: CFG.moeda, value: d.valor, transaction_id: d.pedido ? String(d.pedido) : undefined, items: itens } });
        var m = { content_ids: ids, content_type: 'product', value: d.valor, currency: CFG.moeda };
        if (nome === 'busca') m = { search_string: d.termo };
        if (window.fbq && R.pronto.meta) fbq('track', mapa[1], m, nome === 'compra' ? { eventID: 'pedido-' + d.pedido } : undefined);
        if (window.ttq && R.pronto.tiktok) ttq.track(nome === 'compra' ? 'CompletePayment' : mapa[1], { content_id: ids[0], value: d.valor, currency: CFG.moeda });
    };
    R.registrados = []; // usado pelos testes: tudo o que a loja tentou medir
    var eventoOriginal = R.evento;
    R.evento = function (nome, d) { R.registrados.push([nome, d]); eventoOriginal(nome, d); };

    /* ───────── aviso de cookies ───────── */
    var barra = document.querySelector('[data-lj-cookies]');
    function decidir(analise, marketing) {
        R.consentimento = { v: 1, analise: !!analise, marketing: !!marketing, em: Date.now() };
        guarda.gravar(chave, R.consentimento);
        if (barra) barra.hidden = true;
        document.documentElement.style.removeProperty('--alt-cookies');
        R.ativar();
        document.dispatchEvent(new CustomEvent('lj:cookies'));
    }
    function abrirBarra() {
        if (!barra) return;
        barra.hidden = false;
        document.documentElement.style.setProperty('--alt-cookies', barra.offsetHeight + 'px');
    }
    if (barra) {
        if (!R.consentimento) abrirBarra();
        barra.addEventListener('click', function (ev) {
            if (ev.target.closest('[data-lj-cookies-aceitar]')) decidir(true, true);
            else if (ev.target.closest('[data-lj-cookies-recusar]')) decidir(false, false);
            else if (ev.target.closest('[data-lj-cookies-escolher]')) {
                barra.querySelector('[data-lj-cookies-escolhas]').hidden = false;
                ev.target.closest('button').hidden = true;
                barra.querySelector('[data-lj-cookies-salvar]').hidden = false;
                document.documentElement.style.setProperty('--alt-cookies', barra.offsetHeight + 'px');
            } else if (ev.target.closest('[data-lj-cookies-salvar]')) {
                var a = barra.querySelector('[data-lj-cookie=analise]'), m = barra.querySelector('[data-lj-cookie=marketing]');
                decidir(a && a.checked, m && m.checked);
            }
        });
    }
    document.addEventListener('click', function (ev) {
        if (!ev.target.closest('[data-lj-cookies-abrir]')) return;
        if (!barra) { LJ.avisar('Esta loja usa só os cookies necessários para funcionar.'); return; }
        var c = R.consentimento || {};
        barra.querySelectorAll('[data-lj-cookie]').forEach(function (i) { i.checked = !!c[i.dataset.ljCookie]; });
        // quem volta aqui quer MUDAR a escolha: as categorias já vêm abertas
        var escolhas = barra.querySelector('[data-lj-cookies-escolhas]');
        if (escolhas) { escolhas.hidden = false; barra.querySelector('[data-lj-cookies-escolher]').hidden = true; barra.querySelector('[data-lj-cookies-salvar]').hidden = false; }
        abrirBarra(); (barra.querySelector('[data-lj-cookie]') || barra.querySelector('button:not([hidden])')).focus();
    });
    R.ativar();

    /* ───────── popup ───────── */
    var pop = document.querySelector('[data-lj-popup]');
    if (pop && typeof pop.showModal === 'function') {
        var kp = 'lj_popup_' + CFG.loja, visto = guarda.ler(kp);
        var dias = Number(pop.dataset.dias) || 7;
        var pode = !visto || (Date.now() - visto) > dias * 864e5;
        var abrir = function () {
            if (!pode || pop.open || !pop.isConnected) return;
            if (barra && !barra.hidden) { document.addEventListener('lj:cookies', function () { setTimeout(abrir, 900); }, { once: true }); return; }
            pode = false; guarda.gravar(kp, Date.now()); pop.showModal();
        };
        var seg = Math.max(1, Number(pop.dataset.segundos) || 6);
        if (pop.dataset.gatilho === 'entrar') setTimeout(abrir, 1200);
        else if (pop.dataset.gatilho === 'saida' && matchMedia('(hover:hover)').matches) {
            document.addEventListener('mouseout', function (ev) { if (!ev.relatedTarget && ev.clientY <= 0) abrir(); });
        } else setTimeout(abrir, (pop.dataset.gatilho === 'saida' ? 20 : seg) * 1000);
        pop.addEventListener('click', function (ev) { if (ev.target === pop || ev.target.closest('[data-lj-popup-fechar]')) pop.close(); });
        LJ.abrirPopup = function () { pode = true; abrir(); };
    }

    /* ───────── cadastro para novidades (popup e bloco) ───────── */
    document.addEventListener('submit', function (ev) {
        var f = ev.target.closest('form[data-lj-contato]');
        if (!f) return;
        ev.preventDefault();
        var saida = f.querySelector('[data-lj-contato-retorno]'), botao = f.querySelector('button[type=submit]');
        saida.removeAttribute('data-ok'); saida.removeAttribute('data-erro');
        botao.disabled = true;
        LJ.post(f.getAttribute('action'), { email: f.email.value, aceite: f.aceite.checked ? 1 : 0, origem: f.dataset.ljContato }).then(function (j) {
            if (j.ok) {
                saida.textContent = j.mensagem; saida.setAttribute('data-ok', '');
                f.email.value = ''; R.evento('contato', {});
                var caixa = f.parentElement.querySelector('[data-lj-popup-cupom]');
                if (j.cupom && caixa) { caixa.hidden = false; caixa.querySelector('b').textContent = j.cupom; caixa.querySelector('[data-lj-copiar-texto]').dataset.ljCopiarTexto = j.cupom; f.hidden = true; }
            } else {
                var e = j.errors || {};
                saida.textContent = (e.email && e.email[0]) || (e.aceite && e.aceite[0]) || (j._status === 429 ? 'Muitas tentativas. Espere um minuto.' : 'Não foi possível cadastrar agora.');
                saida.setAttribute('data-erro', '');
            }
        }).catch(function () { saida.textContent = 'Sem conexão. Tente de novo.'; saida.setAttribute('data-erro', ''); })
          .finally(function () { botao.disabled = false; });
    });

    /* ───────── copiar cupom ───────── */
    document.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-lj-copiar-texto]');
        if (!b || !b.dataset.ljCopiarTexto) return;
        var ok = function () { LJ.avisar('Cupom ' + b.dataset.ljCopiarTexto + ' copiado. Cole no carrinho.'); };
        if (navigator.clipboard) navigator.clipboard.writeText(b.dataset.ljCopiarTexto).then(ok, ok); else ok();
    });

    /* ───────── relógio regressivo ───────── */
    document.querySelectorAll('[data-lj-relogio]').forEach(function (rel) {
        var fim = new Date(rel.dataset.ljRelogio).getTime(), antes = {};
        function pintar() {
            var falta = Math.max(0, fim - Date.now());
            var v = { d: Math.floor(falta / 864e5), h: Math.floor(falta / 36e5) % 24, m: Math.floor(falta / 6e4) % 60, s: Math.floor(falta / 1e3) % 60 };
            Object.keys(v).forEach(function (u) {
                var b = rel.querySelector('[data-u=' + u + ']'), t = String(v[u]).padStart(2, '0');
                if (antes[u] !== t) { b.textContent = t; if (antes[u] !== undefined && !semMovimento) { b.classList.remove('lj-tique'); void b.offsetWidth; b.classList.add('lj-tique'); } antes[u] = t; }
            });
            if (falta <= 0) { clearInterval(laco); rel.setAttribute('aria-label', 'Oferta encerrada'); }
        }
        var laco = setInterval(function () { if (!document.hidden) pintar(); }, 1000);
        pintar();
    });

    /* ───────── vídeo: só carrega no clique ───────── */
    document.addEventListener('click', function (ev) {
        var v = ev.target.closest('[data-lj-video]');
        if (!v || !/^[A-Za-z0-9_-]{11}$/.test(v.dataset.ljVideo)) return;
        var q = document.createElement('iframe');
        q.src = 'https://www.youtube-nocookie.com/embed/' + v.dataset.ljVideo + '?autoplay=1&rel=0';
        q.allow = 'autoplay; encrypted-media; picture-in-picture'; q.allowFullscreen = true; q.title = 'Vídeo';
        v.appendChild(q); v.removeAttribute('data-lj-video');
    });

    /* ───────── movimento ───────── */
    if (!semMovimento) {
        // listas entram como listas, uma vez
        if (movimento === 'completo' && 'IntersectionObserver' in window) {
            var obs = new IntersectionObserver(function (es) {
                es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('lj-vista'); obs.unobserve(e.target); } });
            }, { rootMargin: '0px 0px -8% 0px' });
            document.querySelectorAll('.lj-grade, .lj-cats').forEach(function (g) {
                [].forEach.call(g.children, function (c, i) { c.style.setProperty('--i', i); });
                obs.observe(g);
            });
            // Rede de segurança: conteúdo nunca fica escondido. Se o navegador não avisar a
            // entrada na tela (rolagem muito rápida, aba em segundo plano, impressão), tudo
            // aparece sozinho depois de alguns segundos e antes de imprimir.
            var mostrarTudo = function () { document.querySelectorAll('.lj-grade, .lj-cats').forEach(function (g) { g.classList.add('lj-vista'); }); };
            setTimeout(mostrarTudo, 6000);
            window.addEventListener('beforeprint', mostrarTudo);
            document.addEventListener('visibilitychange', function () { if (document.hidden) mostrarTudo(); });
        } else document.querySelectorAll('.lj-grade, .lj-cats').forEach(function (g) { g.classList.add('lj-vista'); });
        // a foto do produto viaja do cartão para a página do produto
        document.addEventListener('pointerdown', function (ev) {
            var cartao = ev.target.closest('.lj-card');
            if (!cartao || ev.target.closest('form')) return;
            document.querySelectorAll('[style*="view-transition-name"]').forEach(function (e) { if (!e.hasAttribute('data-lj-foto-fixa')) e.style.viewTransitionName = ''; });
            var img = cartao.querySelector('.lj-card__foto img');
            if (img) img.style.viewTransitionName = 'lj-foto';
        });
        // …e da página para a sacola, ao adicionar
        LJ.voar = function (img) {
            var alvo = document.querySelector('.lj-icone[href$="carrinho"], .lj-icone[aria-label="Carrinho"]');
            if (!img || !alvo || !img.animate) return;
            var a = img.getBoundingClientRect(), b = alvo.getBoundingClientRect();
            var lado = Math.min(a.width, a.height, 160), clone = img.cloneNode();
            clone.className = 'lj-voo'; clone.removeAttribute('style'); clone.alt = '';
            clone.style.cssText = 'left:' + (a.left + a.width / 2 - lado / 2) + 'px;top:' + (a.top + a.height / 2 - lado / 2) + 'px;width:' + lado + 'px;height:' + lado + 'px';
            document.body.appendChild(clone);
            var dx = b.left + b.width / 2 - (a.left + a.width / 2), dy = b.top + b.height / 2 - (a.top + a.height / 2);
            clone.animate([{ transform: 'translate(0,0) scale(1)', opacity: 1 }, { transform: 'translate(' + dx * .55 + 'px,' + (dy * .2 - 40) + 'px) scale(.6)', opacity: 1, offset: .55 },
                           { transform: 'translate(' + dx + 'px,' + dy + 'px) scale(.12)', opacity: .2 }], { duration: 620, easing: 'cubic-bezier(.3,.1,.2,1)' })
                .onfinish = function () { clone.remove(); alvo.classList.remove('lj-recebe'); void alvo.offsetWidth; alvo.classList.add('lj-recebe'); };
        };
    } else document.querySelectorAll('.lj-grade, .lj-cats').forEach(function (g) { g.classList.add('lj-vista'); });
})();
</script>
