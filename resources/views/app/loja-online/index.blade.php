@extends('layouts.app')

@section('title', 'Loja online')

@section('content')
<x-erp.page-header title="Loja online" icon="globe2" subtitle="Sua vitrine na internet, vendendo o estoque do ERP">
    <a href="{{ $loja->enderecoInterno() }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-up-right me-1"></i> {{ $loja->noAr() ? 'Abrir a loja' : 'Ver prévia' }}</a>
    <form method="POST" action="{{ route('app.loja-online.publicar') }}" class="d-inline"
          @if($loja->publicada) data-confirm="Tirar a loja online do ar? O público deixa de ver a vitrine; nada é apagado." @endif>
        @csrf
        <input type="hidden" name="publicar" value="{{ $loja->publicada ? 0 : 1 }}">
        <button type="submit" class="btn {{ $loja->publicada ? 'btn-outline-danger' : 'btn-primary' }}">
            <i class="bi bi-{{ $loja->publicada ? 'pause-circle' : 'rocket-takeoff' }} me-1"></i> {{ $loja->publicada ? 'Tirar do ar' : 'Pôr no ar' }}
        </button>
    </form>
</x-erp.page-header>

@include('app.loja-online._abas')

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <x-erp.card title="Situação" icon="broadcast">
            <div class="d-flex align-items-center gap-2 mb-3">
                @if($loja->noAr())
                    <span class="badge bg-success">No ar</span><span>O público já pode comprar.</span>
                @elseif($loja->publicada)
                    <span class="badge bg-warning text-dark">Falta configurar</span><span>Escolha a loja de origem em Configurações.</span>
                @else
                    <span class="badge bg-secondary">Fora do ar</span><span>Só você, logado no ERP, vê a prévia.</span>
                @endif
            </div>
            <label class="form-label small text-muted mb-1">Endereço da loja</label>
            <div class="input-group mb-2">
                <input type="text" class="form-control" readonly value="{{ $loja->enderecoPublico() }}" id="enderecoLoja">
                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('enderecoLoja').value).then(function(){ERP.toast('Endereço copiado.','success')})"><i class="bi bi-clipboard"></i> Copiar</button>
            </div>
            @if($loja->dominio || \App\Models\LojaOnline::dominioBase() !== '')
                <div class="form-text">Também abre por <a href="{{ $loja->enderecoInterno() }}" target="_blank" rel="noopener">{{ rtrim(config('app.url'), '/') . $loja->enderecoInterno() }}</a>.</div>
            @endif
            @unless($loja->dominio)
                <div class="form-text">Quer usar um domínio seu (ex.: loja.suaempresa.com.br)? Fale com o suporte da IA365.</div>
            @endunless
        </x-erp.card>
    </div>
    <div class="col-lg-5">
        <x-erp.card title="Para vender" icon="list-check">
            <ul class="list-unstyled mb-0">
                @foreach($passos as $passo)
                    <li class="d-flex align-items-start gap-2 py-1">
                        <i class="bi {{ $passo['feito'] ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted' }} mt-1"></i>
                        @if($passo['rota'] && ! $passo['feito'])<a href="{{ $passo['rota'] }}">{{ $passo['titulo'] }}</a>@else<span class="{{ $passo['feito'] ? 'text-muted' : '' }}">{{ $passo['titulo'] }}</span>@endif
                    </li>
                @endforeach
            </ul>
        </x-erp.card>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><x-erp.stat-card icon="box-seam" color="primary" :value="$noCatalogo" label="Produtos na loja" /></div>
    <div class="col-6 col-lg-3"><x-erp.stat-card icon="image" color="{{ $semFoto ? 'warning' : 'success' }}" :value="$semFoto" label="Sem foto de capa" /></div>
    <div class="col-6 col-lg-3"><x-erp.stat-card icon="receipt" color="info" :value="$totalPedidos" label="Pedidos pela loja" /></div>
    <div class="col-6 col-lg-3"><x-erp.stat-card icon="shop" color="secondary" :value="$loja->unidade?->nome ?? '—'" label="Vende o estoque de" /></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <x-erp.card title="Pagamento na loja" icon="credit-card">
            <p class="text-muted small">A loja online usa o que já está ligado em <strong>Integrações</strong> — os mesmos do Vendedor IA.</p>
            <ul class="list-unstyled mb-0">
                <li class="py-1"><i class="bi {{ $meios['pix'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted' }} me-1"></i>
                    PIX @if($meios['pix'])<span class="text-muted">— {{ ['sicredi_pix' => 'Sicredi, QR Code na tela', 'asaas' => 'Asaas, QR Code na tela', 'infinitepay' => 'pelo link da InfinitePay'][$meios['pix']] ?? $meios['pix'] }}</span>@else<span class="text-muted">— nenhum provedor ligado</span>@endif</li>
                <li class="py-1"><i class="bi {{ $meios['cartao'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted' }} me-1"></i>
                    Cartão @if($meios['cartao'])<span class="text-muted">— link {{ \App\Services\Pagamento\CartaoPedidoService::rotulo($meios['cartao']) }}</span>@else<span class="text-muted">— nenhum provedor ligado</span>@endif</li>
                <li class="py-1"><i class="bi {{ $meios['combinar'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted' }} me-1"></i> Combinar com a loja</li>
            </ul>
        </x-erp.card>
    </div>
    <div class="col-lg-6">
        <x-erp.card title="Entrega na loja" icon="truck">
            <ul class="list-unstyled mb-0">
                <li class="py-1"><i class="bi {{ $loja->opcao('retirada') ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted' }} me-1"></i> Retirada na loja</li>
                <li class="py-1"><i class="bi {{ $loja->opcao('entrega') ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted' }} me-1"></i>
                    Entrega no endereço
                    @if($loja->opcao('entrega'))
                        <span class="text-muted">— {{ $entregaLigada ? 'frete calculado na hora (Uber Direct / Melhor Envio)' : 'sem cotação automática: a loja combina o frete com o cliente' }}</span>
                    @endif
                </li>
            </ul>
        </x-erp.card>
    </div>
</div>

<x-erp.card title="Últimos pedidos pela loja online" icon="receipt">
    @if($pedidos->isEmpty())
        <x-erp.empty-state title="Nenhum pedido ainda" icon="receipt" description="Os pedidos feitos na loja online aparecem aqui e em Comercial → Pedidos, com o canal Online." />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Nº</th><th>Data</th><th>Cliente</th><th>Recebimento</th><th class="text-end">Total</th><th>Situação</th><th></th></tr></thead>
                <tbody>
                @foreach($pedidos as $pedido)
                    @php($sit = \App\Services\LojaOnline\PagamentoLoja::situacao($pedido))
                    <tr>
                        <td class="fw-semibold">#{{ $pedido->numero }}</td>
                        <td>{{ $pedido->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $pedido->cliente?->nome_razao_social ?? '—' }}</td>
                        <td>{{ $pedido->metodo_entrega === 'retirada' ? 'Retirada' : 'Entrega' }}</td>
                        <td class="text-end">R$ {{ number_format((float) $pedido->total, 2, ',', '.') }}</td>
                        <td><span class="badge {{ ['aguardando' => 'bg-warning text-dark', 'cancelado' => 'bg-danger'][$sit['situacao']] ?? 'bg-success' }}">{{ $sit['rotulo'] }}</span></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('app.pedidos.show', $pedido) }}">Abrir</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-erp.card>
@endsection
