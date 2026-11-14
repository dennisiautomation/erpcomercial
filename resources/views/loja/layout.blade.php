<!doctype html>
<html lang="pt-BR">
<head>
<!--
  LOJA ONLINE — contrato de direção (14/11/2026)
  THESIS: a vitrine é do LOJISTA, não da plataforma: uma estrutura, seis mundos (Vitrine, Ateliê, Circuito, Feira, Mimo, Balcão).
          Recusa o "tema recolorido": cada modelo muda forma, tipo, foto, preço e composição do topo, não só a cor.
  OWN-WORLD: variáveis de :root (marca, oferta, fundo, quadro, topo, rodapé, duas fontes, raio) + data-cartao/botao/titulos/preco/foto.
  STORY: o visitante entende o que a loja vende, confia (produto real, preço, estoque do ERP) e compra em três passos.
  FIRST VIEWPORT: nome da loja, busca e carrinho; banner com promessa + FOTOS DE PRODUTOS REAIS (mosaico) e um botão.
  FORM: loja de varejo jogada direto — produto na frente; um único momento de movimento (o mosaico se revela).
-->
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $titulo ?? $loja->nome }}</title>
<meta name="description" content="{{ ($descricaoPagina ?? '') !== '' ? $descricaoPagina : ($loja->opcao('descricao_busca') ?: $loja->nome . ' — compre online.') }}">
@if($ctx->previa || $ctx->rascunho)<meta name="robots" content="noindex,nofollow">@endif
<meta name="theme-color" content="{{ $tema['cor_cabecalho'] }}">
@if($temaFontes)
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="{{ $temaFontes }}">
@endif
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>{!! $temaEstilo !!}</style>
@include('loja.partials.estilo')
@stack('estilo')
</head>
<body {!! $temaAtributos !!} data-pagina="@yield('pagina', 'loja')">
<a class="lj-pular" href="#conteudo">Pular para o conteúdo</a>
@if($ctx->rascunho)
    <div class="lj-previa">Prévia do editor — o público ainda não vê estas mudanças.</div>
@elseif($ctx->previa)
    <div class="lj-previa">Esta loja ainda não está no ar: só você, logado no ERP, consegue ver.</div>
@endif
@if(filled($loja->opcao('aviso_topo')))
    <div class="lj-faixa">{{ $loja->opcao('aviso_topo') }}</div>
@endif

<header class="lj-topo">
    <div class="lj-caixa">
        <div class="lj-topo__linha">
            <a class="lj-marca" href="{{ $ctx->url() }}" aria-label="{{ $loja->nome }} — página inicial">
                @if(!empty($tema['logo']))
                    <img src="{{ \App\Services\LojaOnline\LojaContexto::arquivo($tema['logo']) }}" alt="{{ $loja->nome }}">
                @else
                    {{ $loja->nome }}
                @endif
            </a>
            <form class="lj-busca" action="{{ $ctx->url('produtos') }}" method="get" role="search">
                @if($ctx->rascunho)<input type="hidden" name="rascunho" value="1">@endif
                <label class="lj-so-leitor" for="lj-q">Buscar produtos</label>
                <input id="lj-q" type="search" name="q" value="{{ request('q') }}" placeholder="O que você procura?" autocomplete="off">
                <button type="submit" aria-label="Buscar"><i class="bi bi-search"></i></button>
            </form>
            <div class="lj-acoes">
                @if(filled($loja->opcao('whatsapp')))
                    <a class="lj-icone" href="https://wa.me/55{{ preg_replace('/\D/', '', $loja->opcao('whatsapp')) }}" target="_blank" rel="noopener" aria-label="Falar no WhatsApp"><i class="bi bi-whatsapp"></i></a>
                @endif
                <a class="lj-icone" href="{{ $ctx->url('carrinho') }}" aria-label="Carrinho">
                    <i class="bi bi-bag"></i>
                    <span class="lj-conta" data-lj-conta @if(($carrinhoTotal ?? 0) < 1) hidden @endif>{{ $carrinhoTotal ?? 0 }}</span>
                </a>
            </div>
        </div>
        <nav class="lj-nav" aria-label="Categorias">
            <a href="{{ $ctx->url('produtos') }}" @if(request()->routeIs('loja.produtos') && !request('categoria') && !request('promocao')) aria-current="page" @endif>Todos os produtos</a>
            @foreach(($categoriasMenu ?? collect())->take(12) as $c)
                <a href="{{ $ctx->url('produtos?categoria=' . $c['id']) }}" @if((int) request('categoria') === $c['id']) aria-current="page" @endif>{{ $c['nome'] }}</a>
            @endforeach
        </nav>
    </div>
</header>

<main id="conteudo">
    @yield('conteudo')
</main>

<footer class="lj-rodape">
    <div class="lj-caixa">
        <div class="lj-rodape__grade">
            <div>
                <strong class="lj-marca">{{ $loja->nome }}</strong>
                @if(filled($loja->opcao('texto_rodape')))<p style="max-width:42ch">{{ $loja->opcao('texto_rodape') }}</p>@endif
            </div>
            <div>
                <h2>Comprar</h2>
                <ul>
                    <li><a href="{{ $ctx->url('produtos') }}">Todos os produtos</a></li>
                    <li><a href="{{ $ctx->url('produtos?promocao=1') }}">Promoções</a></li>
                    <li><a href="{{ $ctx->url('carrinho') }}">Meu carrinho</a></li>
                </ul>
            </div>
            <div>
                <h2>Ajuda</h2>
                <ul>
                    <li><a href="{{ $ctx->url('informacoes') }}">Entrega, pagamento e trocas</a></li>
                    @if(filled($loja->opcao('whatsapp')))<li><a href="https://wa.me/55{{ preg_replace('/\D/', '', $loja->opcao('whatsapp')) }}" target="_blank" rel="noopener">WhatsApp {{ $loja->opcao('whatsapp') }}</a></li>@endif
                    @if(filled($loja->opcao('email')))<li><a href="mailto:{{ $loja->opcao('email') }}">{{ $loja->opcao('email') }}</a></li>@endif
                    @if(filled($loja->opcao('instagram')))<li><a href="https://instagram.com/{{ ltrim($loja->opcao('instagram'), '@') }}" target="_blank" rel="noopener">Instagram {{ '@' . ltrim($loja->opcao('instagram'), '@') }}</a></li>@endif
                </ul>
            </div>
            <div>
                @php($un = $loja->unidade)
                <h2>Onde estamos</h2>
                @if($un)
                    <p>{{ collect([trim(($un->logradouro ?? '') . ' ' . ($un->numero ?? '')), $un->bairro, trim(($un->cidade ?? '') . ($un->uf ? '/' . $un->uf : ''))])->filter()->implode(' · ') ?: $un->nome }}</p>
                @endif
            </div>
        </div>
        <div class="lj-rodape__fim">
            <span>© {{ date('Y') }} {{ $loja->empresa?->razao_social ?? $loja->nome }}@if($loja->empresa?->cnpj) · CNPJ {{ \App\Models\ConfiguracaoLoja::formatarDocumento($loja->empresa->cnpj) }}@endif</span>
            <span>Loja online IA365</span>
        </div>
    </div>
</footer>

<div class="lj-brinde" data-lj-brinde hidden role="status" aria-live="polite">
    <span data-lj-brinde-texto></span>
    <a href="{{ $ctx->url('carrinho') }}">Ver carrinho</a>
</div>

<script>
(function () {
    var csrf = document.querySelector('meta[name=csrf-token]').content;
    var conta = document.querySelector('[data-lj-conta]');
    var brinde = document.querySelector('[data-lj-brinde]');
    var relogio;
    window.LJ = {
        csrf: csrf,
        moeda: function (v) { return 'R$ ' + Number(v).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}); },
        post: function (url, dados) {
            return fetch(url, {method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify(dados || {})}).then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); });
        },
        avisar: function (texto) {
            brinde.querySelector('[data-lj-brinde-texto]').textContent = texto;
            brinde.hidden = false; clearTimeout(relogio);
            relogio = setTimeout(function () { brinde.hidden = true; }, 4500);
        },
        contar: function (n) {
            conta.textContent = n; conta.hidden = n < 1;
            conta.classList.remove('pulsa'); void conta.offsetWidth; conta.classList.add('pulsa');
        }
    };
    // "+" do cartão de produto: põe no carrinho sem sair da página
    document.addEventListener('submit', function (ev) {
        var f = ev.target.closest('form[data-lj-add]');
        if (!f) return;
        ev.preventDefault();
        var botao = f.querySelector('button[type=submit]');
        botao.disabled = true;
        LJ.post(f.action, {produto_id: Number(f.produto_id.value), quantidade: Number((f.quantidade && f.quantidade.value) || 1)})
            .then(function (j) {
                if (j.ok) { LJ.contar(j.total); LJ.avisar(j.aviso || 'Adicionado ao carrinho.'); if (f.dataset.ir) location.href = f.dataset.ir; }
                else { LJ.avisar(j.erro || 'Não foi possível adicionar.'); }
            })
            .catch(function () { f.removeAttribute('data-lj-add'); f.submit(); })
            .finally(function () { botao.disabled = false; });
    });
})();
</script>
@stack('scripts')
</body>
</html>
