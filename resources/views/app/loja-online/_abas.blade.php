{{-- Navegação do painel da loja online. 4ª rodada (15/11/2026): 13 abas em dois grupos, compactas (cabem em até duas linhas). --}}
<ul class="nav nav-pills mb-4 gap-1 flex-wrap small" id="abasLojaOnline">
    @foreach([
        ['app.loja-online.index', 'speedometer2', 'Visão geral', null],
        ['app.loja-online.pedidos', 'receipt', 'Pedidos', 'Separar, informar o envio, cancelar e devolver'],
        ['app.loja-online.carrinhos', 'cart-x', 'Carrinhos', 'Carrinhos abandonados'],
        ['app.loja-online.avaliacoes', 'star', 'Avaliações', null],
        ['app.loja-online.resultados', 'bar-chart', 'Resultados', 'Visitas, pedidos e o que mais vende'],
        ['app.loja-online.produtos', 'box-seam', 'Produtos', 'Quais produtos aparecem'],
        ['app.loja-online.grade', 'grid-3x3', 'Grade', 'Tamanho e cor'],
        ['app.loja-online.editor', 'palette', 'Aparência', null],
        ['app.loja-online.promocoes', 'tags', 'Promoções', null],
        ['app.loja-online.avisos', 'envelope', 'E-mails', 'Avisos por e-mail'],
        ['app.loja-online.politicas', 'shield-check', 'Políticas', 'Políticas e cookies'],
        ['app.loja-online.rastreamento', 'graph-up-arrow', 'Anúncios', 'Anúncios e busca'],
        ['app.loja-online.configuracoes', 'sliders', 'Configurações', null],
    ] as [$rota, $icone, $rotulo, $dica])
        <li class="nav-item">
            <a class="nav-link py-1 px-2 {{ request()->routeIs($rota) ? 'active' : '' }}" href="{{ route($rota) }}" @if($dica) title="{{ $dica }}" @endif><i class="bi bi-{{ $icone }} me-1"></i>{{ $rotulo }}</a>
        </li>
    @endforeach
</ul>
