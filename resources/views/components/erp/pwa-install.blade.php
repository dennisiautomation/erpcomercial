{{--
    PWA: registro do service worker + convite de instalação.

    Convite discreto no canto da tela (não bloqueia a página nem rouba o foco).
    Só aparece a partir da 3ª tela aberta na sessão, para nunca ser a primeira
    coisa que o usuário vê. Some de vez quando o app é instalado; "Agora não"
    adia por 7 dias. Nunca aparece dentro do app já instalado nem no PDV
    (o PDV não usa este layout).
--}}
@if(config('pwa.ativo'))
<div id="pwaInstalarCaixa" class="pwa-caixa" role="region" aria-labelledby="pwaInstalarTitulo" hidden>
    <button type="button" class="pwa-fechar" id="pwaInstalarFechar" aria-label="Fechar convite de instalação">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
    </button>
    <div class="pwa-corpo">
        <div class="pwa-marca" aria-hidden="true"><i class="bi bi-grid-3x3-gap-fill"></i></div>
        <div class="pwa-conteudo">
            <h2 class="pwa-titulo" id="pwaInstalarTitulo">Instalar o ERP no computador</h2>
            <p class="pwa-texto" id="pwaInstalarTexto">
                Abre em janela própria, com atalho na área de trabalho. Nada muda no seu dia a dia.
            </p>
            <div class="pwa-passos" id="pwaInstalarPassos" hidden>
                <div class="pwa-passo"><span>1</span> Clique no ícone <i class="bi bi-download" aria-hidden="true"></i> no fim da barra de endereço.</div>
                <div class="pwa-passo"><span>2</span> Ou abra o menu <i class="bi bi-three-dots-vertical" aria-hidden="true"></i> do navegador e escolha <strong>Instalar ERP Comercial</strong>.</div>
            </div>
            <div class="pwa-botoes">
                <button type="button" class="pwa-btn" id="pwaInstalarAgora">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>Instalar
                </button>
                <button type="button" class="pwa-btn-ghost" id="pwaInstalarDepois">Agora não</button>
            </div>
        </div>
    </div>
</div>

<style>
    .pwa-caixa {
        position: fixed; right: 16px; bottom: 16px; z-index: 1050;
        width: calc(100% - 32px); max-width: 400px;
        background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
        padding: 16px 16px 14px;
        box-shadow: 0 12px 32px rgba(15, 23, 42, .16);
        animation: pwaSobe .25s cubic-bezier(.2, .7, .2, 1);
    }
    .pwa-caixa[hidden] { display: none; }
    @keyframes pwaSobe { from { opacity: 0; transform: translateY(10px) } to { opacity: 1; transform: none } }
    @media (prefers-reduced-motion: reduce) { .pwa-caixa { animation: none; } }

    .pwa-fechar {
        position: absolute; top: 8px; right: 8px;
        width: 32px; height: 32px; border: 0; border-radius: 8px;
        background: transparent; color: #64748b; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
    }
    .pwa-fechar:hover { background: #f1f5f9; color: #1e293b; }

    .pwa-corpo { display: flex; gap: 12px; align-items: flex-start; }
    .pwa-marca {
        flex: 0 0 40px; width: 40px; height: 40px; border-radius: 10px;
        background: #4f46e5; color: #fff; font-size: 1.1rem;
        display: flex; align-items: center; justify-content: center;
    }
    .pwa-conteudo { flex: 1; min-width: 0; padding-right: 24px; }
    .pwa-titulo { font-size: .95rem; font-weight: 700; color: #1e293b; margin: 2px 0 4px; }
    .pwa-texto  { font-size: .84rem; color: #475569; line-height: 1.5; margin: 0 0 12px; }

    .pwa-passos { margin: 0 0 12px; }
    .pwa-passo {
        display: flex; gap: 8px; align-items: flex-start;
        font-size: .82rem; color: #475569; line-height: 1.5;
        padding: 8px 10px; background: #f8fafc; border-radius: 8px; margin-bottom: 6px;
    }
    .pwa-passo span {
        flex: 0 0 18px; height: 18px; border-radius: 50%;
        background: #4f46e5; color: #fff; font-size: .7rem; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
    }

    .pwa-botoes { display: flex; gap: 8px; flex-wrap: wrap; }
    .pwa-btn, .pwa-btn-ghost {
        border: 0; border-radius: 8px; cursor: pointer;
        padding: 8px 14px; font-size: .86rem; font-weight: 600;
        transition: background-color .15s, color .15s;
    }
    .pwa-btn { background: #4f46e5; color: #fff; }
    .pwa-btn:hover { background: #4338ca; }
    .pwa-btn-ghost { background: transparent; color: #475569; }
    .pwa-btn-ghost:hover { background: #f1f5f9; color: #1e293b; }
    .pwa-btn:focus-visible, .pwa-btn-ghost:focus-visible, .pwa-fechar:focus-visible { outline: 2px solid #4f46e5; outline-offset: 2px; }
</style>

<script>
(function () {
    'use strict';

    var ADIAR_DIAS   = 7;
    var TELAS_MINIMAS = 3;              // só convida a partir da 3ª tela da sessão
    var CHAVE_ADIADO = 'erp_pwa_adiado';
    var CHAVE_INST   = 'erp_pwa_instalado';
    var CHAVE_MANUAL = 'erp_pwa_manual_visto';
    var CHAVE_TELAS  = 'erp_pwa_telas';

    var caixa    = document.getElementById('pwaInstalarCaixa');
    var passos   = document.getElementById('pwaInstalarPassos');
    var texto    = document.getElementById('pwaInstalarTexto');
    var btnSim   = document.getElementById('pwaInstalarAgora');
    var btnNao   = document.getElementById('pwaInstalarDepois');
    var btnX     = document.getElementById('pwaInstalarFechar');
    var evento   = null;   // beforeinstallprompt guardado
    var aberto   = false;

    function ls(acao, chave, valor) {
        try {
            if (acao === 'get') return localStorage.getItem(chave);
            if (acao === 'set') localStorage.setItem(chave, valor);
        } catch (e) { /* modo restrito: segue sem memória */ }
        return null;
    }
    function telasNaSessao() {
        try {
            var n = parseInt(sessionStorage.getItem(CHAVE_TELAS) || '0', 10) + 1;
            sessionStorage.setItem(CHAVE_TELAS, String(n));
            return n;
        } catch (e) { return TELAS_MINIMAS; }
    }

    function jaInstalado() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true
            || ls('get', CHAVE_INST) === '1';
    }

    function adiado() {
        var quando = parseInt(ls('get', CHAVE_ADIADO) || '0', 10);
        return quando > 0 && (Date.now() - quando) < ADIAR_DIAS * 86400000;
    }

    function celular() {
        return window.matchMedia('(max-width: 820px)').matches
            && window.matchMedia('(pointer: coarse)').matches;
    }

    var telas = telasNaSessao();

    /* ---- convite (não modal: a página continua usável e o foco fica onde estava) ---- */

    function abrir(modo, forcado) {           // modo: 'prompt' | 'manual'
        if (aberto || jaInstalado()) return;
        if (!forcado && telas < TELAS_MINIMAS) return;

        if (celular()) {
            document.getElementById('pwaInstalarTitulo').textContent = 'Instalar o ERP no celular';
            texto.textContent = 'Ganha ícone na tela inicial e abre em tela cheia. Nada muda no seu dia a dia.';
        }

        if (modo === 'manual') {
            passos.hidden = false;
            btnSim.hidden = true;
            btnNao.textContent = 'Entendi';
            ls('set', CHAVE_MANUAL, '1');
        }

        caixa.hidden = false;
        aberto = true;
        if (forcado) btnSim.hidden ? btnNao.focus() : btnSim.focus();   // só quando o usuário pediu pelo menu
    }

    function fechar(lembrar) {
        caixa.hidden = true;
        aberto = false;
        if (lembrar) ls('set', CHAVE_ADIADO, String(Date.now()));
    }

    btnNao.addEventListener('click', function () { fechar(true); });
    btnX.addEventListener('click', function () { fechar(true); });

    btnSim.addEventListener('click', function () {
        if (!evento) { fechar(true); return; }
        caixa.hidden = true;
        aberto = false;
        evento.prompt();
        evento.userChoice.then(function (escolha) {
            if (escolha && escolha.outcome === 'accepted') {
                ls('set', CHAVE_INST, '1');
            } else {
                ls('set', CHAVE_ADIADO, String(Date.now()));
            }
            evento = null;
            esconderAtalhoMenu();
        });
    });

    document.addEventListener('keydown', function (e) {
        if (aberto && e.key === 'Escape' && caixa.contains(document.activeElement)) fechar(true);
    });

    /* ---- item discreto "Instalar aplicativo" no menu do usuário ---- */

    function mostrarAtalhoMenu() {
        var itens = document.querySelectorAll('[data-pwa-menu]');
        for (var i = 0; i < itens.length; i++) itens[i].hidden = false;
    }
    function esconderAtalhoMenu() {
        var itens = document.querySelectorAll('[data-pwa-menu]');
        for (var i = 0; i < itens.length; i++) itens[i].hidden = true;
    }
    document.addEventListener('click', function (e) {
        var alvo = e.target.closest ? e.target.closest('[data-pwa-instalar]') : null;
        if (!alvo) return;
        e.preventDefault();
        if (evento) { abrir('prompt', true); } else { abrir('manual', true); }
    });

    /* ---- ciclo de vida ---- */

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        evento = e;
        mostrarAtalhoMenu();
        if (!jaInstalado() && !adiado()) setTimeout(function () { abrir('prompt'); }, 1500);
    });

    window.addEventListener('appinstalled', function () {
        ls('set', CHAVE_INST, '1');
        evento = null;
        fechar(false);
        esconderAtalhoMenu();
        if (window.ERP && ERP.toast) {
            ERP.toast('ERP instalado! O atalho já está na sua área de trabalho.', 'success');
        }
    });

    if (jaInstalado()) ls('set', CHAVE_INST, '1');

    // Chrome/Edge que não dispararam o evento (já instalado em outro perfil,
    // critério ainda não avaliado): mostra o caminho manual uma única vez.
    if ('onbeforeinstallprompt' in window && !jaInstalado() && !adiado()
        && ls('get', CHAVE_MANUAL) !== '1') {
        setTimeout(function () {
            if (!evento && !aberto) abrir('manual');
        }, 4500);
    }
})();
</script>
@endif

{{-- O registro fica FORA do @if: com PWA_ATIVO=false o /sw.js servido é o que
     se desregistra, e é preciso buscá-lo para desfazer instalações antigas. --}}
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {
                /* sem service worker o ERP continua funcionando normalmente */
            });
        });
    }
</script>
