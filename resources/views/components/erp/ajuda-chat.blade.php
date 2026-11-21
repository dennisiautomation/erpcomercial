{{-- Agente de ajuda (21/11/2026): botão "Ajuda" + janela de conversa. Liga por AJUDA_ATIVO.
     :flutuante="false" (PDV) = sem o botão do canto; a tela abre por ERPAjuda.abrir(). --}}
@props(['flutuante' => true])
@if (config('ajuda.ativo') && auth()->check())
<style>
    .aj-botao {
        position: fixed; right: 20px; bottom: 20px; z-index: 1040;
        display: inline-flex; align-items: center; gap: .5rem;
        padding: .7rem 1.1rem; border: 0; border-radius: 999px;
        background: var(--primary, #4f46e5); color: #fff; font-weight: 600; font-size: .95rem;
        box-shadow: 0 8px 24px rgba(79, 70, 229, .35); cursor: pointer;
        transition: transform .15s ease, background .15s ease;
    }
    .aj-botao:hover { background: var(--primary-hover, #4338ca); transform: translateY(-1px); }
    .aj-botao i { font-size: 1.15rem; }
    .aj-botao[aria-expanded="true"] { display: none; }

    .aj-painel {
        position: fixed; right: 20px; bottom: 20px; z-index: 1045;
        width: min(420px, calc(100vw - 32px)); height: min(640px, calc(100vh - 96px));
        display: none; flex-direction: column; overflow: hidden;
        background: var(--bg-card, #fff); color: var(--text-primary, #0f172a);
        border: 1px solid var(--border, #e2e8f0); border-radius: var(--radius-xl, 1rem);
        box-shadow: 0 20px 50px rgba(15, 23, 42, .25);
    }
    .aj-painel.aberto { display: flex; animation: aj-entra .18s ease-out; }
    @keyframes aj-entra { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
    @media (prefers-reduced-motion: reduce) { .aj-painel.aberto { animation: none; } }

    .aj-topo {
        display: flex; align-items: center; gap: .6rem; padding: .85rem 1rem;
        background: var(--bg-sidebar, #0f172a); color: #fff;
    }
    .aj-topo .aj-avatar {
        width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center;
        background: var(--primary, #4f46e5); flex-shrink: 0;
    }
    .aj-topo h2 { font-size: 1rem; margin: 0; font-weight: 600; line-height: 1.2; }
    .aj-topo small { color: var(--text-sidebar, #cbd5e1); font-size: .78rem; }
    .aj-topo .aj-acoes { margin-left: auto; display: flex; gap: .25rem; }
    .aj-topo button {
        background: transparent; border: 0; color: #fff; width: 36px; height: 36px; border-radius: 8px;
        display: grid; place-items: center; cursor: pointer;
    }
    .aj-topo button:hover { background: rgba(255,255,255,.12); }

    .aj-lista { flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: .75rem; background: var(--bg-page, #f1f5f9); }
    .aj-msg { max-width: 88%; padding: .65rem .85rem; border-radius: 14px; font-size: .92rem; line-height: 1.45; word-wrap: break-word; }
    .aj-msg p { margin: 0 0 .45rem; } .aj-msg p:last-child { margin-bottom: 0; }
    .aj-msg ol, .aj-msg ul { margin: .25rem 0 .45rem; padding-left: 1.25rem; }
    .aj-msg li { margin-bottom: .2rem; }
    .aj-msg code { background: rgba(15,23,42,.07); padding: 0 .25rem; border-radius: 4px; font-size: .85em; }
    .aj-eu { align-self: flex-end; background: var(--primary, #4f46e5); color: #fff; border-bottom-right-radius: 4px; }
    .aj-eu a { color: #fff; }
    .aj-ele { align-self: flex-start; background: var(--bg-card, #fff); border: 1px solid var(--border, #e2e8f0); border-bottom-left-radius: 4px; }
    .aj-protocolo {
        align-self: stretch; display: flex; gap: .5rem; align-items: center; font-size: .88rem;
        background: var(--success-light, #ecfdf5); color: var(--success-text, #047857);
        border: 1px solid #a7f3d0; border-radius: 10px; padding: .55rem .75rem;
    }
    .aj-pensando { align-self: flex-start; display: inline-flex; gap: 4px; padding: .8rem .9rem; background: var(--bg-card,#fff); border: 1px solid var(--border,#e2e8f0); border-radius: 14px; }
    .aj-pensando span { width: 7px; height: 7px; border-radius: 50%; background: var(--text-muted, #64748b); animation: aj-pulo 1s infinite ease-in-out; }
    .aj-pensando span:nth-child(2) { animation-delay: .15s; } .aj-pensando span:nth-child(3) { animation-delay: .3s; }
    @keyframes aj-pulo { 0%, 80%, 100% { opacity: .3; transform: translateY(0); } 40% { opacity: 1; transform: translateY(-3px); } }

    .aj-sugestoes { display: flex; flex-wrap: wrap; gap: .4rem; }
    .aj-sugestoes button {
        border: 1px solid var(--border, #e2e8f0); background: var(--bg-card, #fff); color: var(--text-secondary, #475569);
        border-radius: 999px; padding: .4rem .75rem; font-size: .84rem; cursor: pointer; text-align: left;
    }
    .aj-sugestoes button:hover { border-color: var(--primary, #4f46e5); color: var(--primary, #4f46e5); }

    .aj-rodape { border-top: 1px solid var(--border, #e2e8f0); padding: .65rem; display: flex; gap: .5rem; align-items: flex-end; background: var(--bg-card, #fff); }
    .aj-rodape textarea {
        flex: 1; resize: none; border: 1px solid var(--border, #e2e8f0); border-radius: 10px;
        padding: .55rem .7rem; font-size: .92rem; max-height: 120px; min-height: 42px; line-height: 1.4;
    }
    .aj-rodape textarea:focus { outline: 2px solid var(--primary, #4f46e5); outline-offset: 0; border-color: transparent; }
    .aj-rodape button {
        width: 42px; height: 42px; border: 0; border-radius: 10px; flex-shrink: 0;
        background: var(--primary, #4f46e5); color: #fff; display: grid; place-items: center; cursor: pointer;
    }
    .aj-rodape button:disabled { opacity: .5; cursor: default; }
    .aj-aviso { font-size: .72rem; color: var(--text-muted, #64748b); text-align: center; padding: 0 .75rem .55rem; background: var(--bg-card,#fff); }

    @media (max-width: 576px) {
        .aj-painel { right: 0; bottom: 0; width: 100vw; height: 100dvh; border-radius: 0; }
        .aj-botao { right: 14px; bottom: 14px; padding: .65rem .95rem; }
    }
    @media print { .aj-botao, .aj-painel { display: none !important; } }
</style>

<button type="button" class="aj-botao" id="ajBotao" aria-controls="ajPainel" aria-expanded="false" @unless($flutuante) hidden style="display:none" @endunless>
    <i class="bi bi-chat-dots-fill" aria-hidden="true"></i> Ajuda
</button>

<section class="aj-painel" id="ajPainel" role="dialog" aria-modal="false" aria-labelledby="ajTitulo">
    <header class="aj-topo">
        <span class="aj-avatar" aria-hidden="true"><i class="bi bi-stars"></i></span>
        <div>
            <h2 id="ajTitulo">Ajuda do sistema</h2>
            <small>Tire dúvidas ou relate um problema</small>
        </div>
        <div class="aj-acoes">
            <button type="button" id="ajNova" title="Nova conversa" aria-label="Nova conversa"><i class="bi bi-plus-lg"></i></button>
            <button type="button" id="ajFechar" title="Fechar" aria-label="Fechar"><i class="bi bi-x-lg"></i></button>
        </div>
    </header>
    <div class="aj-lista" id="ajLista" aria-live="polite"></div>
    <form class="aj-rodape" id="ajForm" autocomplete="off">
        <label for="ajTexto" class="visually-hidden">Sua pergunta</label>
        <textarea id="ajTexto" rows="1" maxlength="2000" placeholder="Escreva sua dúvida…"></textarea>
        <button type="submit" id="ajEnviar" aria-label="Enviar"><i class="bi bi-send-fill"></i></button>
    </form>
    <div class="aj-aviso">Respostas geradas por IA. Ela só vê os dados da sua empresa.</div>
</section>

<script>
(function () {
    const botao = document.getElementById('ajBotao');
    const painel = document.getElementById('ajPainel');
    const lista = document.getElementById('ajLista');
    const form = document.getElementById('ajForm');
    const texto = document.getElementById('ajTexto');
    const enviar = document.getElementById('ajEnviar');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const URL_ATUAL = @json(route('app.ajuda.atual'));
    const URL_MSG = @json(route('app.ajuda.mensagem'));
    const nome = @json(\Illuminate\Support\Str::of(auth()->user()->name)->before(' '));
    let conversaId = null, carregou = false, ocupado = false, protocoloMostrado = null;

    const SUGESTOES = [
        'Estou com erro em uma nota fiscal',
        'Como cadastro uma promoção?',
        'Como abro e fecho o caixa?',
        'Quero falar com o suporte',
    ];

    // Teclas dentro da janela não vão para a tela de trás (PDV, atalhos, leitor de código)
    painel.addEventListener('keydown', e => {
        e.stopPropagation();
        if (e.key === 'Escape') fechar();
    });

    function esc(s) { return s.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    // Markdown pequeno: negrito, código, listas, links do próprio ERP
    function formatar(t) {
        const linhas = esc(t || '').split('\n');
        let html = '', lista = null;
        const inline = s => s
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/`([^`]+)`/g, '<code>$1</code>')
            .replace(/\[([^\]]+)\]\((\/app\/[^)\s]*|https:\/\/(?:homolog-)?erp\.ia365\.com\.br\/[^)\s]*)\)/g, '<a href="$2">$1</a>');
        for (const l of linhas) {
            const num = l.match(/^\s*\d+[.)]\s+(.*)/), bul = l.match(/^\s*[-•*]\s+(.*)/);
            const tipo = num ? 'ol' : (bul ? 'ul' : null);
            if (lista && tipo !== lista) { html += `</${lista}>`; lista = null; }
            if (tipo) { if (!lista) { html += `<${tipo}>`; lista = tipo; } html += `<li>${inline((num || bul)[1])}</li>`; }
            else if (l.trim()) html += `<p>${inline(l)}</p>`;
        }
        if (lista) html += `</${lista}>`;
        return html;
    }

    function rolar() { lista.scrollTop = lista.scrollHeight; }

    function bolha(papel, t) {
        const d = document.createElement('div');
        d.className = 'aj-msg ' + (papel === 'user' ? 'aj-eu' : 'aj-ele');
        d.innerHTML = formatar(t);
        lista.appendChild(d); rolar();
    }

    function mostrarProtocolo(p) {
        if (!p || p === protocoloMostrado) return;
        protocoloMostrado = p;
        const d = document.createElement('div');
        d.className = 'aj-protocolo';
        d.innerHTML = '<i class="bi bi-ticket-detailed" aria-hidden="true"></i><span>Chamado aberto para a equipe IA365 · protocolo <strong>' + esc(String(p)) + '</strong></span>';
        lista.appendChild(d); rolar();
    }

    function boasVindas() {
        lista.innerHTML = '';
        bolha('assistant', `Olá, ${nome}! Sou a ajuda do sistema. Posso explicar como fazer qualquer coisa no ERP, olhar o erro de uma nota ou venda da sua empresa e, se for problema de verdade, abrir um chamado para a equipe da IA365.\n\nComo posso ajudar?`);
        const s = document.createElement('div');
        s.className = 'aj-sugestoes';
        SUGESTOES.forEach(txt => {
            const b = document.createElement('button');
            b.type = 'button'; b.textContent = txt;
            b.addEventListener('click', () => { s.remove(); mandar(txt); });
            s.appendChild(b);
        });
        lista.appendChild(s);
    }

    async function carregar() {
        if (carregou) return;
        carregou = true;
        try {
            const r = await fetch(URL_ATUAL, { headers: { 'Accept': 'application/json' } });
            const j = await r.json();
            conversaId = j.conversa_id;
            if (j.mensagens && j.mensagens.length) {
                lista.innerHTML = '';
                j.mensagens.forEach(m => bolha(m.papel, m.texto));
                mostrarProtocolo(j.protocolo);
            } else boasVindas();
        } catch (e) { boasVindas(); }
    }

    function abrir() {
        painel.classList.add('aberto');
        botao.setAttribute('aria-expanded', 'true');
        carregar();
        setTimeout(() => texto.focus(), 50);
    }
    function fechar() {
        painel.classList.remove('aberto');
        botao.setAttribute('aria-expanded', 'false');
        botao.focus();
    }

    async function mandar(t) {
        t = (t || '').trim();
        if (!t || ocupado) return;
        ocupado = true; enviar.disabled = true;
        lista.querySelector('.aj-sugestoes')?.remove();
        bolha('user', t);
        texto.value = ''; texto.style.height = '';
        const p = document.createElement('div');
        p.className = 'aj-pensando'; p.setAttribute('aria-label', 'Pensando');
        p.innerHTML = '<span></span><span></span><span></span>';
        lista.appendChild(p); rolar();
        try {
            const r = await fetch(URL_MSG, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ texto: t, conversa_id: conversaId, pagina: document.title + ' — ' + location.pathname + location.search }),
            });
            const j = await r.json().catch(() => ({}));
            p.remove();
            if (r.status === 419) { bolha('assistant', 'Sua sessão expirou. Atualize a página (F5) e tente de novo.'); }
            else if (!r.ok) { bolha('assistant', j.erro || j.message || 'Não consegui responder agora. Tente de novo em instantes.'); }
            else {
                conversaId = j.conversa_id;
                bolha('assistant', j.resposta);
                mostrarProtocolo(j.protocolo);
            }
        } catch (e) {
            p.remove();
            bolha('assistant', 'Sem conexão com o sistema. Confira a internet e tente de novo.');
        } finally {
            ocupado = false; enviar.disabled = false; texto.focus();
        }
    }

    botao.addEventListener('click', abrir);
    document.getElementById('ajFechar').addEventListener('click', fechar);
    document.getElementById('ajNova').addEventListener('click', () => { conversaId = null; protocoloMostrado = null; carregou = true; boasVindas(); texto.focus(); });
    form.addEventListener('submit', e => { e.preventDefault(); mandar(texto.value); });
    texto.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); mandar(texto.value); } });
    texto.addEventListener('input', () => { texto.style.height = 'auto'; texto.style.height = Math.min(texto.scrollHeight, 120) + 'px'; });

    // Outras telas podem abrir a ajuda já com uma pergunta: ERPAjuda.perguntar('...')
    window.ERPAjuda = { abrir, perguntar: t => { abrir(); setTimeout(() => mandar(t), 300); } };
})();
</script>
@endif
