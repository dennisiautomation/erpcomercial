{{--
    PWA: registro do service worker + convite de instalação.

    Aparece uma vez por navegador (login ou primeira tela depois dele) e some
    de vez quando o app é instalado. "Agora não" adia por 7 dias. Nunca aparece
    dentro do app já instalado nem no PDV (o PDV não usa este layout).
--}}
@if(config('pwa.ativo'))
<div id="pwaInstalarBackdrop" class="pwa-backdrop" hidden>
    <div class="pwa-caixa" role="dialog" aria-modal="true" aria-labelledby="pwaInstalarTitulo">
        <div class="pwa-marca"><i class="bi bi-grid-3x3-gap-fill"></i></div>

        <h2 class="pwa-titulo" id="pwaInstalarTitulo">Instalar o ERP no seu computador</h2>

        <p class="pwa-texto" id="pwaInstalarTexto">
            Instale o ERP como aplicativo: ele cria o atalho na
            <strong>área de trabalho</strong>, pode ser fixado na
            <strong>barra de tarefas</strong> e abre em janela própria, sem a barra do navegador.
            É o mesmo sistema — nada muda no seu dia a dia.
        </p>

        <div class="pwa-passos" id="pwaInstalarPassos" hidden>
            <div class="pwa-passo"><span>1</span> Clique no ícone <i class="bi bi-download"></i> no fim da barra de endereço do navegador.</div>
            <div class="pwa-passo"><span>2</span> Ou abra o menu <i class="bi bi-three-dots-vertical"></i> do navegador e escolha <strong>Instalar ERP Comercial</strong>.</div>
        </div>

        <div class="pwa-botoes">
            <button type="button" class="pwa-btn-ghost" id="pwaInstalarDepois">Agora não</button>
            <button type="button" class="pwa-btn" id="pwaInstalarAgora">
                <i class="bi bi-download me-1"></i>Instalar
            </button>
        </div>
    </div>
</div>

<style>
    .pwa-backdrop {
        position: fixed; inset: 0; z-index: 2000;
        background: rgba(15, 23, 42, .45);
        backdrop-filter: blur(3px);
        display: flex; align-items: center; justify-content: center;
        padding: 20px;
        animation: pwaFade .2s ease;
    }
    .pwa-backdrop[hidden] { display: none; }
    @keyframes pwaFade { from { opacity: 0 } to { opacity: 1 } }
    @keyframes pwaSobe { from { opacity: 0; transform: translateY(12px) scale(.98) } to { opacity: 1; transform: none } }

    .pwa-caixa {
        width: 100%; max-width: 430px;
        background: #fff; border-radius: 18px;
        padding: 32px 28px 24px;
        box-shadow: 0 24px 60px rgba(15, 23, 42, .22);
        text-align: center;
        animation: pwaSobe .25s cubic-bezier(.4, 0, .2, 1);
    }
    .pwa-marca {
        width: 60px; height: 60px; border-radius: 15px;
        margin: 0 auto 18px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 1.6rem;
    }
    .pwa-titulo { font-size: 1.15rem; font-weight: 700; color: #1e293b; margin: 0 0 10px; }
    .pwa-texto  { font-size: .9rem; color: #64748b; line-height: 1.55; margin: 0 0 22px; }
    .pwa-texto strong { color: #475569; }

    .pwa-passos { text-align: left; margin: 0 0 22px; }
    .pwa-passo {
        display: flex; gap: 10px; align-items: flex-start;
        font-size: .85rem; color: #475569; line-height: 1.5;
        padding: 9px 12px; background: #f8fafc; border-radius: 10px; margin-bottom: 8px;
    }
    .pwa-passo span {
        flex: 0 0 20px; height: 20px; border-radius: 50%;
        background: #6366f1; color: #fff;
        font-size: .72rem; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
    }

    .pwa-botoes { display: flex; gap: 10px; justify-content: center; }
    .pwa-btn, .pwa-btn-ghost {
        border: 0; border-radius: 10px; cursor: pointer;
        padding: 11px 22px; font-size: .92rem; font-weight: 600;
        transition: background .15s, color .15s;
    }
    .pwa-btn { background: #6366f1; color: #fff; }
    .pwa-btn:hover { background: #4f46e5; }
    .pwa-btn-ghost { background: transparent; color: #64748b; }
    .pwa-btn-ghost:hover { background: #f1f5f9; color: #475569; }

    @media (max-width: 420px) {
        .pwa-caixa { padding: 26px 20px 20px; }
        .pwa-botoes { flex-direction: column-reverse; }
        .pwa-btn, .pwa-btn-ghost { width: 100%; }
    }
</style>

<script>
(function () {
    'use strict';

    var ADIAR_DIAS   = 7;
    var CHAVE_ADIADO = 'erp_pwa_adiado';
    var CHAVE_INST   = 'erp_pwa_instalado';
    var CHAVE_MANUAL = 'erp_pwa_manual_visto';

    var backdrop = document.getElementById('pwaInstalarBackdrop');
    var passos   = document.getElementById('pwaInstalarPassos');
    var texto    = document.getElementById('pwaInstalarTexto');
    var btnSim   = document.getElementById('pwaInstalarAgora');
    var btnNao   = document.getElementById('pwaInstalarDepois');
    var evento   = null;   // beforeinstallprompt guardado
    var aberto   = false;

    function ls(acao, chave, valor) {
        try {
            if (acao === 'get') return localStorage.getItem(chave);
            if (acao === 'set') localStorage.setItem(chave, valor);
        } catch (e) { /* modo restrito: segue sem memória */ }
        return null;
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

    /* ---- diálogo ---- */

    function abrir(modo) {                    // modo: 'prompt' | 'manual'
        if (aberto || jaInstalado()) return;

        if (celular()) {
            document.getElementById('pwaInstalarTitulo').textContent = 'Instalar o ERP no seu celular';
            texto.innerHTML = 'Instale o ERP como aplicativo: ele ganha ícone na <strong>tela inicial</strong> '
                + 'e abre em tela cheia, sem a barra do navegador. É o mesmo sistema — nada muda no seu dia a dia.';
        }

        if (modo === 'manual') {
            passos.hidden = false;
            btnSim.hidden = true;
            btnNao.textContent = 'Entendi';
            ls('set', CHAVE_MANUAL, '1');
        }

        backdrop.hidden = false;
        aberto = true;
        (modo === 'manual' ? btnNao : btnSim).focus();
    }

    function fechar(lembrar) {
        backdrop.hidden = true;
        aberto = false;
        if (lembrar) ls('set', CHAVE_ADIADO, String(Date.now()));
    }

    btnNao.addEventListener('click', function () { fechar(true); });

    btnSim.addEventListener('click', function () {
        if (!evento) { fechar(true); return; }
        backdrop.hidden = true;
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

    backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) fechar(true);
    });
    document.addEventListener('keydown', function (e) {
        if (aberto && e.key === 'Escape') fechar(true);
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
        if (evento) { abrir('prompt'); } else { abrir('manual'); }
    });

    /* ---- ciclo de vida ---- */

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        evento = e;
        mostrarAtalhoMenu();
        if (!jaInstalado() && !adiado()) setTimeout(function () { abrir('prompt'); }, 900);
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
