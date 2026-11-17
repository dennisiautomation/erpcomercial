{{-- Navegação do painel da loja online. 4ª rodada (15/11/2026): 14 abas (16/11: Domínio).
     17/11/2026: agrupadas em Vender / Catálogo / Aparência / Ajustes, numa linha só que rola de lado no celular. --}}
@php
    $gruposAbas = [
        'Vender' => [
            ['app.loja-online.index', 'speedometer2', 'Visão geral', null],
            ['app.loja-online.pedidos', 'receipt', 'Pedidos', 'Separar, informar o envio, cancelar e devolver'],
            ['app.loja-online.carrinhos', 'cart-x', 'Carrinhos', 'Carrinhos abandonados'],
            ['app.loja-online.avaliacoes', 'star', 'Avaliações', null],
            ['app.loja-online.resultados', 'bar-chart', 'Resultados', 'Visitas, pedidos e o que mais vende'],
        ],
        'Catálogo' => [
            ['app.loja-online.produtos', 'box-seam', 'Produtos', 'Quais produtos aparecem'],
            ['app.loja-online.grade', 'grid-3x3', 'Grade', 'Tamanho e cor'],
            ['app.loja-online.promocoes', 'tags', 'Promoções', null],
        ],
        'Aparência' => [
            ['app.loja-online.editor', 'palette', 'Editor visual', 'Logo, cores, modelo e página inicial'],
            ['app.loja-online.dominio', 'globe', 'Domínio', 'Usar o seu domínio (www.sualoja.com.br)'],
        ],
        'Ajustes' => [
            ['app.loja-online.avisos', 'envelope', 'E-mails', 'Avisos por e-mail'],
            ['app.loja-online.politicas', 'shield-check', 'Políticas', 'Políticas e cookies'],
            ['app.loja-online.rastreamento', 'graph-up-arrow', 'Anúncios', 'Anúncios e busca'],
            ['app.loja-online.configuracoes', 'sliders', 'Configurações', null],
        ],
    ];
@endphp
<nav class="lj-abas mb-4" id="abasLojaOnline" aria-label="Painel da loja online">
    @foreach($gruposAbas as $grupo => $abas)
        <div class="lj-abas-grupo" role="group" aria-label="{{ $grupo }}">
            <span class="lj-abas-rotulo">{{ $grupo }}</span>
            <ul class="lj-abas-lista">
                @foreach($abas as [$rota, $icone, $rotulo, $dica])
                    @php $ativa = request()->routeIs($rota); @endphp
                    <li>
                        <a class="lj-aba {{ $ativa ? 'active' : '' }}" href="{{ route($rota) }}" @if($ativa) aria-current="page" @endif @if($dica) title="{{ $dica }}" @endif>
                            <i class="bi bi-{{ $icone }}" aria-hidden="true"></i>{{ $rotulo }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>

@once
@push('styles')
<style>
    .lj-abas {
        display: flex; gap: 0.5rem 2rem; align-items: flex-end; flex-wrap: wrap;
        padding: 0.5rem 0.75rem 0.625rem; background: var(--bg-card, #fff);
        border: 1px solid var(--border, #e2e8f0); border-radius: 0.75rem;
    }
    .lj-abas-grupo { display: flex; flex-direction: column; gap: 0.125rem; min-width: 0; }
    .lj-abas-rotulo {
        font-size: 0.6875rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase;
        color: var(--text-muted, #64748b); padding-left: 0.5rem;
    }
    .lj-abas-lista { display: flex; gap: 0.125rem; list-style: none; margin: 0; padding: 0; }
    .lj-aba {
        display: inline-flex; align-items: center; gap: 0.375rem; white-space: nowrap;
        padding: 0.375rem 0.625rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500;
        color: var(--text-secondary, #475569); text-decoration: none;
        transition: background-color .15s ease, color .15s ease;
    }
    .lj-aba:hover { background: var(--primary-light, #eef2ff); color: var(--primary, #4f46e5); }
    .lj-aba.active { background: var(--primary, #4f46e5); color: #fff; }
    .lj-aba:focus-visible { outline: 2px solid var(--primary, #4f46e5); outline-offset: 2px; }
    /* Botão de salvar das telas de ajuste: fica à vista no pé da tela enquanto rola */
    .lj-salvar {
        position: sticky; bottom: 0; z-index: 10; padding: 0.75rem 0; margin-top: 0.5rem;
        background: rgba(241, 245, 249, .94); backdrop-filter: blur(6px);
        border-top: 1px solid var(--border, #e2e8f0);
    }
    /* Celular: uma linha só, rolando de lado, sem barra de rolagem aparente */
    @media (max-width: 767.98px) {
        .lj-abas {
            flex-wrap: nowrap; overflow-x: auto; scroll-snap-type: x proximity; scrollbar-width: none;
            margin-left: -0.25rem; margin-right: -0.25rem;
            gap: 1.25rem;
        }
        /* esmaece a borda direita enquanto houver mais abas para o lado */
        .lj-abas.tem-mais {
            -webkit-mask-image: linear-gradient(to right, #000 85%, transparent);
                    mask-image: linear-gradient(to right, #000 85%, transparent);
        }
        .lj-abas::-webkit-scrollbar { display: none; }
        .lj-abas-grupo { flex: none; scroll-snap-align: start; }
    }
</style>
@endpush
@push('scripts')
<script>
// No celular as abas rolam de lado: traz a aba aberta para a vista ao carregar.
(function () {
    var nav = document.getElementById('abasLojaOnline');
    var ativa = nav && nav.querySelector('.lj-aba.active');
    if (!nav) return;
    if (ativa && nav.scrollWidth > nav.clientWidth) {
        nav.scrollLeft = Math.max(0, ativa.offsetLeft - nav.offsetLeft - 24);
    }
    function marca() { nav.classList.toggle('tem-mais', nav.scrollLeft + nav.clientWidth < nav.scrollWidth - 4); }
    nav.addEventListener('scroll', marca, { passive: true });
    window.addEventListener('resize', marca);
    marca();
})();
</script>
@endpush
@endonce
