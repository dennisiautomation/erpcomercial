{{-- Navegação do painel da loja online --}}
<ul class="nav nav-pills mb-4 gap-1 flex-wrap">
    @foreach([
        ['app.loja-online.index', 'speedometer2', 'Visão geral'],
        ['app.loja-online.produtos', 'box-seam', 'Produtos'],
        ['app.loja-online.editor', 'palette', 'Aparência'],
        ['app.loja-online.configuracoes', 'sliders', 'Configurações'],
    ] as [$rota, $icone, $rotulo])
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs($rota) ? 'active' : '' }}" href="{{ route($rota) }}"><i class="bi bi-{{ $icone }} me-1"></i>{{ $rotulo }}</a>
        </li>
    @endforeach
</ul>
