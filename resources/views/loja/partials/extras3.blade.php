{{-- Loja online — 4ª rodada (15/11/2026): favoritos (navegador + conta) e avise-me. --}}
<script>
(function () {
    var CHAVE = 'lj_fav_{{ $loja->id }}';
    var URL_SINC = @json($ctx->caminho('favoritos'));
    var LOGADO = @json((bool) ($contaLoja ?? null));
    function ler() { try { return (JSON.parse(localStorage.getItem(CHAVE)) || []).map(Number).filter(Boolean); } catch (e) { return []; } }
    function gravar(ids) { try { localStorage.setItem(CHAVE, JSON.stringify(ids.slice(0, 100))); } catch (e) {} }
    function pintar() {
        var ids = ler();
        document.querySelectorAll('[data-lj-fav]').forEach(function (b) {
            var tem = ids.indexOf(Number(b.dataset.ljFav)) >= 0;
            b.setAttribute('aria-pressed', tem ? 'true' : 'false');
            var i = b.querySelector('i'); if (i) i.className = 'bi bi-heart' + (tem ? '-fill' : '');
            var t = b.querySelector('span'); if (t) t.textContent = tem ? 'Nos favoritos' : 'Favoritar';
        });
        var c = document.querySelector('[data-lj-fav-conta]');
        if (c) { c.textContent = ids.length; c.hidden = ids.length < 1; }
    }
    function sincronizar(substituir) {
        if (!LOGADO || !window.LJ) return Promise.resolve();
        return LJ.post(URL_SINC, {ids: ler(), substituir: substituir ? 1 : 0}).then(function (j) { if (j && j.ids) { gravar(j.ids); pintar(); } }).catch(function () {});
    }
    window.LJ = window.LJ || {};
    LJ.favoritos = {ler: ler, pintar: pintar};

    document.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-lj-fav]');
        if (!b) return;
        ev.preventDefault();
        var id = Number(b.dataset.ljFav), ids = ler(), pos = ids.indexOf(id);
        if (pos >= 0) ids.splice(pos, 1); else ids.unshift(id);
        gravar(ids); pintar(); sincronizar(true);
        document.dispatchEvent(new CustomEvent('lj:favoritos'));
    });
    pintar();
    // conta logada: junta o que está no navegador com o que está na conta (uma vez por aba)
    try { if (LOGADO && !sessionStorage.getItem(CHAVE + '_s')) { sessionStorage.setItem(CHAVE + '_s', '1'); sincronizar(false); } } catch (e) {}

    // avise-me quando chegar
    var av = document.querySelector('[data-lj-avise]');
    if (av) av.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var saida = av.querySelector('[data-lj-avise-saida]'), bt = av.querySelector('button[type=submit]');
        bt.disabled = true; saida.removeAttribute('data-erro'); saida.textContent = '';
        LJ.post(av.getAttribute('action'), {produto_id: av.elements.produto_id.value, email: av.elements.email.value, aceite: av.elements.aceite.checked ? 1 : 0})
            .then(function (j) {
                if (j.ok) { saida.textContent = j.mensagem; av.querySelector('.lj-avise__linha').hidden = true; av.querySelector('.lj-aceite').hidden = true; }
                else { saida.setAttribute('data-erro', ''); saida.textContent = j.erro || 'Confira o e-mail.'; }
            }).catch(function () { saida.setAttribute('data-erro', ''); saida.textContent = 'Sem conexão. Tente de novo.'; })
            .finally(function () { bt.disabled = false; });
    });
})();
</script>
