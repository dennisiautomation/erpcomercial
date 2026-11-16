@extends('layouts.app')

@section('title', 'Loja online — Resultados')

@section('content')
<x-erp.page-header title="Loja online" icon="globe2" subtitle="Resultados: visitas, pedidos e o que mais vende">
    <div class="btn-group">
        @foreach([7, 30, 90] as $d)<a class="btn btn-outline-secondary {{ $r['dias'] === $d ? 'active' : '' }}" href="{{ route('app.loja-online.resultados', ['dias' => $d]) }}">{{ $d }} dias</a>@endforeach
    </div>
</x-erp.page-header>

@include('app.loja-online._abas')

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <span class="badge rounded-pill text-bg-success fs-6 fw-semibold" id="ljAgora" data-url="{{ route('app.loja-online.resultados.agora') }}"><i class="bi bi-broadcast me-1"></i><span data-agora>{{ $r['agora'] }}</span> na loja agora</span>
    <span class="small text-muted">Pessoas com a loja aberta nos últimos 5 minutos. Atualiza sozinho.</span>
</div>

@php $moeda = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.'); $maior = max(1, collect($r['porDia'])->max('visitas')); $maiorValor = max(1, collect($r['porDia'])->max('valor')); @endphp

<div class="row g-3 mb-3">
    @foreach([
        ['Visitas', number_format($r['visitas'], 0, ',', '.'), 'pessoas que abriram a loja'],
        ['Puseram no carrinho', number_format($r['carrinhos'], 0, ',', '.'), $r['visitas'] ? min(100, round($r['carrinhos'] / $r['visitas'] * 100)) . '% das visitas' : '—'],
        ['Chegaram ao fechamento', number_format($r['fechamentos'], 0, ',', '.'), $r['visitas'] ? min(100, round($r['fechamentos'] / $r['visitas'] * 100)) . '% das visitas' : '—'],
        ['Pedidos pagos', $r['pagos'] . ' de ' . $r['pedidos'], $r['conversao'] !== null ? 'conversão de ' . str_replace('.', ',', (string) $r['conversao']) . '%' : 'sem visitas contadas'],
        ['Faturamento', $moeda($r['faturamento']), 'pedidos pagos no período'],
        ['Ticket médio', $moeda($r['ticket']), 'por pedido pago'],
    ] as [$rotulo, $valor, $nota])
        <div class="col-6 col-lg-2"><div class="border rounded p-3 h-100 bg-body"><div class="text-muted small">{{ $rotulo }}</div><div class="fs-4 fw-bold" data-indicador="{{ $rotulo }}">{{ $valor }}</div><div class="small text-muted">{{ $nota }}</div></div></div>
    @endforeach
</div>

<x-erp.card title="Onde as pessoas desistem" icon="funnel" class="mb-3">
    @if($r['funil'][0]['n'] === 0)
        <p class="text-muted mb-0">Ainda sem visitas contadas neste período. Assim que alguém abrir a loja, o caminho dela aparece aqui: entrou, viu produto, pôs no carrinho, começou a fechar, pediu e pagou.</p>
    @else
        <div data-funil>
        @foreach($r['funil'] as $e)
            <div class="row g-2 align-items-center py-1 {{ $loop->last ? '' : 'border-bottom' }}" data-etapa="{{ $e['nome'] }}">
                <div class="col-12 col-md-3 fw-semibold">{{ $e['nome'] }}</div>
                <div class="col-8 col-md-5"><div class="bg-body-secondary rounded" style="height:14px"><div class="bg-primary rounded" style="height:14px;width:{{ max($e['n'] > 0 ? 2 : 0, $e['do_total']) }}%"></div></div></div>
                <div class="col-4 col-md-1 text-end"><strong data-etapa-n>{{ $e['n'] }}</strong> <span class="small text-muted">{{ $e['do_total'] }}%</span></div>
                <div class="col-12 col-md-3 small {{ ($e['perda_pct'] ?? 0) >= 60 ? 'text-danger' : 'text-muted' }}">@if($e['perda'] !== null && $e['perda'] > 0){{ $e['perda'] }} desistiram antes daqui ({{ $e['perda_pct'] }}%)@elseif($e['perda'] === 0)ninguém desistiu nesta etapa @endif</div>
            </div>
        @endforeach
        </div>
        @php $pior = collect($r['funil'])->filter(fn ($e) => ($e['perda'] ?? 0) > 0)->sortByDesc('perda_pct')->first(); @endphp
        @if($pior)
            <div class="alert alert-light border small mt-3 mb-0"><i class="bi bi-lightbulb me-1"></i><strong>Maior perda: antes de "{{ $pior['nome'] }}".</strong>
                {{ ['Viram um produto' => 'A página inicial não está levando as pessoas aos produtos: ponha vitrines de destaque logo no topo e banners com botão.',
                    'Puseram no carrinho' => 'As pessoas olham e não escolhem: confira fotos, descrição, preço e se há estoque dos tamanhos mais pedidos.',
                    'Começaram a fechar' => 'Enchem o carrinho e param: deixe o frete e o pedido mínimo claros, e ligue o lembrete de carrinho em Promoções.',
                    'Fizeram o pedido' => 'Desistem no formulário: veja se o frete está caro ou se faltam formas de entrega (retirada na loja ajuda).',
                    'Pagaram' => 'Fazem o pedido e não pagam: confira se o PIX está ligado e fale com quem ficou em Pedidos aguardando pagamento.'][$pior['nome']] ?? '' }}</div>
        @endif
    @endif
</x-erp.card>

<div class="row g-3 mb-3">
    <div class="col-lg-7"><x-erp.card title="De onde vêm as visitas" icon="signpost-split">
        @if($r['origens'])
            <div class="table-responsive"><table class="table table-sm align-middle mb-0" data-origens>
                <thead><tr><th>Origem</th><th class="text-end">Visitas</th><th class="text-end">Pedidos pagos</th><th class="text-end">Vendido</th><th class="text-end">Conversão</th></tr></thead>
                <tbody>
                @foreach($r['origens'] as $o)
                    <tr data-origem="{{ $o['origem'] }}"><td>{{ $o['rotulo'] }} @if($o['pagas'] > 0)<span class="badge text-bg-warning ms-1" title="Visitas que vieram de anúncio pago">{{ $o['pagas'] }} de anúncio</span>@endif</td>
                        <td class="text-end">{{ $o['visitas'] }}</td><td class="text-end">{{ $o['pedidos'] }}</td>
                        <td class="text-end text-nowrap">{{ $o['valor'] > 0 ? $moeda($o['valor']) : '—' }}</td>
                        <td class="text-end">{{ str_replace('.', ',', (string) $o['conversao']) }}%</td></tr>
                @endforeach
                </tbody>
            </table></div>
            @if($r['campanhas'])
                <div class="fw-semibold small mt-3 mb-1">Campanhas</div>
                @foreach($r['campanhas'] as $c)<div class="d-flex justify-content-between border-bottom py-1 small" data-campanha="{{ $c['campanha'] }}"><span>{{ $c['campanha'] }} <span class="text-muted">· {{ $c['rotulo'] }}</span></span><span class="text-nowrap ms-2">{{ $c['visitas'] }} visita(s) · {{ $c['pedidos'] }} pedido(s){{ $c['valor'] > 0 ? ' · ' . $moeda($c['valor']) : '' }}</span></div>@endforeach
            @endif
        @else
            <p class="text-muted mb-0">Ainda sem visitas contadas neste período.</p>
        @endif
        <p class="small text-muted mt-3 mb-0">A venda fica na origem da visita em que a pessoa fechou o pedido. Quem viu o anúncio hoje e voltou outro dia para comprar entra como "Direto".</p>
    </x-erp.card></div>
    <div class="col-lg-5">
        <x-erp.card title="Celular ou computador" icon="phone" class="mb-3">
            @forelse($r['aparelhos'] as $a)
                <div class="d-flex justify-content-between border-bottom py-1" data-aparelho="{{ $a['rotulo'] }}"><span>{{ $a['rotulo'] }} <span class="text-muted small">· {{ $a['parte'] }}% das visitas</span></span><span class="text-nowrap ms-2">{{ $a['visitas'] }} visita(s) · {{ $a['pedidos'] }} pedido(s)</span></div>
            @empty<p class="text-muted mb-0">Ainda sem visitas contadas neste período.</p>@endforelse
        </x-erp.card>
        <x-erp.card title="Clientes novos e que voltaram" icon="people">
            <div class="row text-center g-2">
                <div class="col-6"><div class="fs-3 fw-bold" data-clientes="novos">{{ $r['clientesNovos'] }}</div><div class="small text-muted">compraram pela primeira vez</div></div>
                <div class="col-6"><div class="fs-3 fw-bold" data-clientes="voltaram">{{ $r['clientesVoltaram'] }}</div><div class="small text-muted">já tinham comprado na loja online</div></div>
            </div>
        </x-erp.card>
    </div>
</div>

<x-erp.card title="Link rastreado para divulgar" icon="link-45deg" class="mb-3">
    <p class="small text-muted">Use um link destes na bio do Instagram, no status do WhatsApp, no panfleto ou no anúncio. Quem entrar por ele aparece acima na origem certa, com o nome da campanha.</p>
    <div class="row g-2 align-items-end" id="ljLink" data-base="{{ rtrim($loja->enderecoPublico(), '/') }}">
        <div class="col-6 col-md-3"><label class="form-label small mb-1" for="ljLinkOrigem">Onde vai divulgar</label>
            <select class="form-select" id="ljLinkOrigem">@foreach(['instagram' => 'Instagram', 'whatsapp' => 'WhatsApp', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'google' => 'Google', 'email' => 'E-mail', 'panfleto' => 'Panfleto ou QR Code', 'parceiro' => 'Parceiro ou influenciador'] as $v => $t)<option value="{{ $v }}">{{ $t }}</option>@endforeach</select></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1" for="ljLinkCampanha">Nome da campanha (opcional)</label><input type="text" class="form-control" id="ljLinkCampanha" maxlength="40" placeholder="ex.: natal"></div>
        <div class="col-6 col-md-2"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="ljLinkPago"><label class="form-check-label small" for="ljLinkPago">É anúncio pago</label></div></div>
        <div class="col-12 col-md-4"><div class="input-group"><input type="text" class="form-control" id="ljLinkSaida" readonly aria-label="Link rastreado"><button class="btn btn-outline-secondary" type="button" id="ljLinkCopiar"><i class="bi bi-clipboard me-1"></i>Copiar</button></div></div>
    </div>
</x-erp.card>

<x-erp.card title="Dia a dia" icon="calendar3" class="mb-3">
    <div class="table-responsive" style="max-height:420px">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Dia</th><th style="width:38%">Visitas</th><th class="text-end">Pedidos pagos</th><th style="width:30%">Vendido</th></tr></thead>
            <tbody>
            @foreach(array_reverse($r['porDia']) as $d)
                <tr>
                    <td>{{ $d['dia'] }}</td>
                    <td><div class="d-flex align-items-center gap-2"><div class="bg-primary rounded" style="height:8px;width:{{ max(0, round($d['visitas'] / $maior * 100)) }}%"></div><span class="small">{{ $d['visitas'] }}</span></div></td>
                    <td class="text-end">{{ $d['pedidos'] }}</td>
                    <td><div class="d-flex align-items-center gap-2"><div class="bg-success rounded" style="height:8px;width:{{ max(0, round($d['valor'] / $maiorValor * 100)) }}%"></div><span class="small text-nowrap">{{ $d['valor'] > 0 ? $moeda($d['valor']) : '—' }}</span></div></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-erp.card>

<div class="row g-3">
    <div class="col-lg-6"><x-erp.card title="Mais vendidos" icon="trophy">
        @forelse($r['maisVendidos'] as $m)<div class="d-flex justify-content-between border-bottom py-1"><span>{{ $m->descricao }}</span><span class="text-nowrap ms-2"><strong>{{ $moeda($m->total) }}</strong> <span class="text-muted small">· {{ rtrim(rtrim(number_format((float) $m->qtd, 3, ',', '.'), '0'), ',') }} un.</span></span></div>
        @empty<p class="text-muted mb-0">Nenhum pedido pago no período.</p>@endforelse
    </x-erp.card></div>
    <div class="col-lg-6"><x-erp.card title="Mais vistos" icon="eye">
        @forelse($r['maisVistos'] as $m)<div class="d-flex justify-content-between border-bottom py-1"><span>{{ $m->descricao }}</span><span class="text-nowrap ms-2">{{ $m->vistas }} visita(s)</span></div>
        @empty<p class="text-muted mb-0">Ainda sem visitas contadas.</p>@endforelse
    </x-erp.card></div>
</div>
<div class="row g-3 mt-0">
    <div class="col-lg-6"><x-erp.card title="Mais abandonados no carrinho" icon="cart-x">
        @forelse($r['abandonados'] as $m)<div class="d-flex justify-content-between border-bottom py-1" data-abandonado><span>{{ $m['descricao'] }}</span><span class="text-nowrap ms-2"><strong>{{ $m['abandonos'] }}</strong> <span class="text-muted small">de {{ $m['carrinhos'] }} carrinho(s) não viraram compra</span></span></div>
        @empty<p class="text-muted mb-0">Nada por enquanto. Aqui aparecem os produtos que as pessoas põem no carrinho e não compram — bons candidatos a cupom ou a revisar preço e frete.</p>@endforelse
    </x-erp.card></div>
    <div class="col-lg-6"><x-erp.card title="O que as pessoas procuram" icon="search">
        @if($r['buscasSemResultado']->isNotEmpty())
            <div class="fw-semibold small text-danger mb-1">Procuraram e não acharam</div>
            @foreach($r['buscasSemResultado'] as $b)<div class="d-flex justify-content-between border-bottom py-1" data-busca-vazia="{{ $b->termo }}"><span>"{{ $b->termo }}"</span><span class="text-nowrap ms-2">{{ $b->sem }} vez(es)</span></div>@endforeach
            <p class="small text-muted mt-1">Vale pôr esses produtos na loja ou usar essas palavras no nome dos que já existem.</p>
        @endif
        @if($r['buscas']->isNotEmpty())
            <div class="fw-semibold small mb-1 {{ $r['buscasSemResultado']->isNotEmpty() ? 'mt-3' : '' }}">Mais procurados</div>
            @foreach($r['buscas'] as $b)<div class="d-flex justify-content-between border-bottom py-1" data-busca="{{ $b->termo }}"><span>"{{ $b->termo }}"</span><span class="text-nowrap ms-2">{{ $b->vezes }} vez(es)</span></div>@endforeach
        @else
            <p class="text-muted mb-0">Ninguém usou a busca da loja neste período.</p>
        @endif
    </x-erp.card></div>
</div>

<x-erp.card title="Onde moram os compradores" icon="geo-alt" class="mt-3">
    @forelse($r['cidades'] as $c)<div class="d-flex justify-content-between border-bottom py-1" data-cidade><span>{{ $c->cidade }}{{ $c->uf ? ' / ' . $c->uf : '' }}</span><span class="text-nowrap ms-2"><strong>{{ $moeda($c->valor) }}</strong> <span class="text-muted small">· {{ $c->pedidos }} pedido(s)</span></span></div>
    @empty<p class="text-muted mb-0">Nenhum pedido pago com endereço no período.</p>@endforelse
</x-erp.card>

<p class="small text-muted mt-3">Origem, funil, aparelho, buscas e abandonados começaram a ser contados em 16/11/2026. Nada disso guarda nome nem endereço de internet de quem visita: são somas. As visitas começaram a ser contadas em 15/11/2026 — uma por pessoa por dia, sem contar a equipe logada no ERP nem robôs de busca. Pedidos e valores vêm dos pedidos do ERP.</p>
@endsection

@push('scripts')
<script>
(function () {
    var agora = document.getElementById('ljAgora');
    if (agora) setInterval(function () {
        if (document.hidden) return;
        fetch(agora.dataset.url, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'}).then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) { if (d) agora.querySelector('[data-agora]').textContent = d.agora; }).catch(function () {});
    }, 30000);

    var caixa = document.getElementById('ljLink');
    if (!caixa) return;
    var origem = document.getElementById('ljLinkOrigem'), campanha = document.getElementById('ljLinkCampanha'),
        pago = document.getElementById('ljLinkPago'), saida = document.getElementById('ljLinkSaida');
    function montar() {
        var p = ['utm_source=' + encodeURIComponent(origem.value)];
        if (pago.checked) p.push('utm_medium=cpc');
        var c = campanha.value.trim().toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9\-_]/g, '');
        if (c) p.push('utm_campaign=' + encodeURIComponent(c));
        saida.value = caixa.dataset.base + '/?' + p.join('&');
    }
    [origem, campanha, pago].forEach(function (e) { e.addEventListener('input', montar); e.addEventListener('change', montar); });
    montar();
    document.getElementById('ljLinkCopiar').addEventListener('click', function () {
        navigator.clipboard.writeText(saida.value).then(function () { if (window.ERP && ERP.toast) ERP.toast('Link copiado.', 'success'); });
    });
})();
</script>
@endpush
