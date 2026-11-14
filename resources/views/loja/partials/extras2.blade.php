{{-- Scripts do redesenho (14/11/2026, 3ª rodada): busca com sugestões, menu de categorias, menu lateral,
     sacola lateral, cabeçalho que encolhe, carrossel de banners e setas das vitrines deslizantes.
     Tudo progressivo: sem JavaScript os links, a busca e o carrinho continuam funcionando. --}}
<script>
(function () {
    var reduz = document.body.dataset.movimento === 'nenhum' || matchMedia('(prefers-reduced-motion: reduce)').matches;
    function el(tag, classe, texto) { var e = document.createElement(tag); if (classe) e.className = classe; if (texto !== undefined) e.textContent = texto; return e; }

    /* ── cabeçalho: sombra ao rolar ── */
    var topo = document.querySelector('[data-lj-topo]');
    if (topo) {
        var marcar = function () { topo.classList.toggle('lj-topo--rolou', scrollY > 90); };
        addEventListener('scroll', marcar, {passive: true}); marcar();
    }

    /* ── gavetas (<dialog>): fechar pelo X, pelo fundo e pelo Esc (nativo) ── */
    document.querySelectorAll('dialog.lj-gaveta').forEach(function (d) {
        d.addEventListener('click', function (ev) { if (ev.target === d || ev.target.closest('[data-lj-x]')) d.close(); });
    });
    var menu = document.querySelector('[data-lj-menu]');
    document.addEventListener('click', function (ev) {
        if (ev.target.closest('[data-lj-menu-abrir]') && menu && menu.showModal) menu.showModal();
    });

    /* ── menu "Todas as categorias" ── */
    var mega = document.querySelector('[data-lj-mega]');
    if (mega) {
        var botao = mega.querySelector('.lj-mega__botao'), painel = mega.querySelector('.lj-mega__painel'), relogio;
        var abrir = function (sim) { clearTimeout(relogio); painel.hidden = !sim; botao.setAttribute('aria-expanded', sim ? 'true' : 'false'); };
        botao.addEventListener('click', function () { abrir(painel.hidden); });
        if (matchMedia('(hover:hover)').matches) {
            mega.addEventListener('mouseenter', function () { abrir(true); });
            mega.addEventListener('mouseleave', function () { relogio = setTimeout(function () { abrir(false); }, 180); });
        }
        document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && !painel.hidden) { abrir(false); botao.focus(); } });
        document.addEventListener('click', function (ev) { if (!mega.contains(ev.target)) abrir(false); });
    }

    /* ── busca com sugestões ── */
    var busca = document.querySelector('[data-lj-busca]');
    if (busca) {
        var campo = busca.querySelector('input[type=search]'), caixa = busca.querySelector('[data-lj-sugestoes]'), espera, pedido = 0, ativo = -1;
        var fechar = function () { caixa.hidden = true; campo.setAttribute('aria-expanded', 'false'); ativo = -1; };
        var pintar = function (j, termo) {
            caixa.textContent = '';
            if (!j.produtos.length) { caixa.appendChild(el('p', '', 'Nada encontrado para “' + termo + '”.')); }
            j.produtos.forEach(function (p) {
                var a = el('a'); a.href = p.url;
                if (p.foto) { var im = el('img'); im.src = p.foto; im.alt = ''; a.appendChild(im); } else a.appendChild(el('span', 'lj-semfoto', p.nome.charAt(0)));
                a.appendChild(el('span', '', p.nome));
                var preco = el('b', '', LJ.moeda(p.preco));
                if (p.normal) { var s = el('s', '', LJ.moeda(p.normal)); preco.insertBefore(s, preco.firstChild); }
                a.appendChild(preco); caixa.appendChild(a);
            });
            if (j.produtos.length) { var t = el('a', 'lj-sugestoes__todos', 'Ver todos os resultados'); t.href = busca.getAttribute('action') + '?q=' + encodeURIComponent(termo); caixa.appendChild(t); }
            caixa.hidden = false; campo.setAttribute('aria-expanded', 'true');
        };
        campo.addEventListener('input', function () {
            clearTimeout(espera);
            var termo = campo.value.trim();
            if (termo.length < 2) { fechar(); return; }
            espera = setTimeout(function () {
                var meu = ++pedido;
                fetch(busca.dataset.sugestoes + '?q=' + encodeURIComponent(termo), {headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (j) { if (j && meu === pedido && campo.value.trim() === termo) pintar(j, termo); })
                    .catch(function () {});
            }, 220);
        });
        campo.addEventListener('keydown', function (ev) {
            var itens = caixa.querySelectorAll('a');
            if (caixa.hidden || !itens.length) return;
            if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
                ev.preventDefault();
                ativo = (ativo + (ev.key === 'ArrowDown' ? 1 : -1) + itens.length) % itens.length;
                itens.forEach(function (a, n) { a.setAttribute('aria-selected', n === ativo ? 'true' : 'false'); });
                itens[ativo].scrollIntoView({block: 'nearest'});
            } else if (ev.key === 'Enter' && ativo >= 0) { ev.preventDefault(); location.href = itens[ativo].href; }
            else if (ev.key === 'Escape') fechar();
        });
        document.addEventListener('click', function (ev) { if (!busca.contains(ev.target)) fechar(); });
    }

    /* ── sacola lateral ── */
    var gaveta = document.querySelector('[data-lj-gaveta]');
    if (gaveta && gaveta.showModal) {
        var lista = gaveta.querySelector('[data-lj-gaveta-itens]'), meta = gaveta.querySelector('[data-lj-gaveta-meta]');
        var desenhar = function (j) {
            lista.textContent = '';
            gaveta.querySelector('[data-lj-gaveta-qtd]').textContent = j.quantidade ? '(' + j.quantidade + ')' : '';
            gaveta.querySelector('[data-lj-gaveta-total]').textContent = LJ.moeda(j.subtotal);
            gaveta.querySelector('.lj-gaveta__pe').hidden = !j.itens.length;
            LJ.contar(j.quantidade);
            if (!j.itens.length) {
                var v = el('div', 'lj-gaveta__vazia'); v.appendChild(el('i', 'bi bi-bag')); v.appendChild(el('p', '', 'Sua sacola está vazia.')); lista.appendChild(v);
            }
            j.itens.forEach(function (i) {
                var linha = el('div', 'lj-item');
                var foto = el('a', 'lj-item__foto'); foto.href = i.url; foto.tabIndex = -1;
                if (i.foto) { var im = el('img'); im.src = i.foto; im.alt = ''; foto.appendChild(im); } else foto.appendChild(el('span', 'lj-semfoto', i.nome.charAt(0)));
                var meio = el('div'), nome = el('a', 'lj-item__nome', i.nome); nome.href = i.url;
                var acoes = el('div', 'lj-item__acoes'), qtd = el('div', 'lj-qtd');
                var menos = el('button', '', '−'), n = el('span', '', String(i.quantidade)), mais = el('button', '', '+');
                menos.type = mais.type = 'button'; menos.setAttribute('aria-label', 'Diminuir ' + i.nome); mais.setAttribute('aria-label', 'Aumentar ' + i.nome);
                menos.onclick = function () { mudar(i.id, i.quantidade - 1); }; mais.onclick = function () { mudar(i.id, i.quantidade + 1); };
                qtd.appendChild(menos); qtd.appendChild(n); qtd.appendChild(mais);
                var tirar = el('button', 'lj-tirar', 'Tirar'); tirar.type = 'button'; tirar.onclick = function () { mudar(i.id, 0); };
                acoes.appendChild(qtd); acoes.appendChild(tirar);
                meio.appendChild(nome); meio.appendChild(el('small', '', LJ.moeda(i.preco) + ' cada')); meio.appendChild(acoes);
                linha.appendChild(foto); linha.appendChild(meio); linha.appendChild(el('div', 'lj-item__total', LJ.moeda(i.total)));
                lista.appendChild(linha);
            });
            if (j.frete_gratis_acima > 0 && j.entrega && j.itens.length) {
                meta.hidden = false;
                meta.querySelector('p').textContent = j.falta_frete_gratis > 0 ? 'Faltam ' + LJ.moeda(j.falta_frete_gratis) + ' para o frete grátis.' : 'Você ganhou frete grátis.';
                meta.querySelector('i').style.setProperty('--p', Math.min(100, Math.round(j.subtotal / j.frete_gratis_acima * 100)) + '%');
            } else meta.hidden = true;
        };
        var carregar = function () {
            return fetch(gaveta.dataset.resumo, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'}).then(function (r) { return r.json(); }).then(desenhar);
        };
        var mudar = function (id, quantidade) {
            fetch(gaveta.dataset.atualizar, {method: 'POST', credentials: 'same-origin', redirect: 'manual',
                headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': LJ.csrf},
                body: 'produto_id=' + id + '&quantidade=' + quantidade}).then(carregar).catch(function () {});
        };
        LJ.abrirSacola = function () { carregar().then(function () { if (!gaveta.open) gaveta.showModal(); }).catch(function () { location.href = document.querySelector('[data-lj-sacola]').href; }); };
        // no computador o ícone abre a sacola lateral; no celular vai para a página do carrinho
        var icone = document.querySelector('[data-lj-sacola]');
        if (icone && document.body.dataset.pagina !== 'compra') icone.addEventListener('click', function (ev) {
            if (innerWidth < 700 || ev.metaKey || ev.ctrlKey || /\/carrinho$/.test(location.pathname)) return;
            ev.preventDefault(); LJ.abrirSacola();
        });
    }

    /* ── carrossel de banners ── */
    document.querySelectorAll('[data-lj-carrossel]').forEach(function (c) {
        var trilho = c.querySelector('[data-lj-trilho-banner]'), slides = trilho.children, pontos = c.querySelectorAll('[data-ir]'), atual = 0, rodando;
        if (slides.length < 2) return;
        var ir = function (i) { atual = (i + slides.length) % slides.length; trilho.scrollTo({left: trilho.clientWidth * atual, behavior: reduz ? 'auto' : 'smooth'}); };
        var marcar = function () {
            var i = Math.round(trilho.scrollLeft / trilho.clientWidth);
            atual = i; pontos.forEach(function (p, n) { n === i ? p.setAttribute('aria-current', 'true') : p.removeAttribute('aria-current'); });
        };
        trilho.addEventListener('scroll', marcar, {passive: true});
        c.querySelectorAll('.lj-carrossel__seta').forEach(function (s) { s.addEventListener('click', function () { ir(atual + Number(s.dataset.d)); parar(); }); });
        pontos.forEach(function (p) { p.addEventListener('click', function () { ir(Number(p.dataset.ir)); parar(); }); });
        var parar = function () { clearInterval(rodando); rodando = null; };
        var tocar = function () { if (!reduz && !rodando) rodando = setInterval(function () { if (!document.hidden) ir(atual + 1); }, 6500); };
        c.addEventListener('mouseenter', parar); c.addEventListener('mouseleave', tocar);
        c.addEventListener('focusin', parar); c.addEventListener('touchstart', parar, {passive: true});
        if ('IntersectionObserver' in window) new IntersectionObserver(function (es) { es[0].isIntersecting ? tocar() : parar(); }, {threshold: .4}).observe(c); else tocar();
    });

    /* ── setas das vitrines que deslizam ── */
    document.querySelectorAll('[data-lj-setas]').forEach(function (setas) {
        var trilho = setas.closest('section').querySelector('[data-lj-trilho]');
        if (!trilho) return;
        var botoes = setas.querySelectorAll('button');
        var conferir = function () {
            botoes[0].disabled = trilho.scrollLeft < 8;
            botoes[1].disabled = trilho.scrollLeft + trilho.clientWidth > trilho.scrollWidth - 8;
        };
        botoes.forEach(function (b) { b.addEventListener('click', function () { trilho.scrollBy({left: Number(b.dataset.d) * trilho.clientWidth * .82, behavior: reduz ? 'auto' : 'smooth'}); }); });
        trilho.addEventListener('scroll', conferir, {passive: true}); addEventListener('resize', conferir); conferir();
    });
})();
</script>
