@extends('layouts.app')

@section('title', 'Loja online — Pedidos')

@section('content')
<x-erp.page-header title="Loja online" icon="globe2" subtitle="Pedidos: separar, informar o envio, cancelar e devolver">
    <a href="{{ route('app.pedidos.index') }}" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-up-right me-1"></i> Faturar em Comercial → Pedidos</a>
</x-erp.page-header>

@include('app.loja-online._abas')

<ul class="nav nav-tabs mb-3">
    @foreach(['separar' => 'Pagos para separar/enviar', 'aguardando' => 'Aguardando pagamento', 'enviados' => 'Enviados', 'cancelados' => 'Cancelados', 'todos' => 'Todos'] as $k => $rotulo)
        <li class="nav-item"><a class="nav-link {{ $filtro === $k ? 'active' : '' }}" href="{{ route('app.loja-online.pedidos', ['ver' => $k]) }}">{{ $rotulo }}@isset($totais[$k]) <span class="badge text-bg-{{ $k === 'separar' && $totais[$k] ? 'primary' : 'secondary' }} ms-1">{{ $totais[$k] }}</span>@endisset</a></li>
    @endforeach
</ul>

<x-erp.card class="mb-3">
    @forelse($pedidos as $p)
        @php
            $s = $p->situacaoLoja; $cancelado = $p->status === \App\Enums\StatusPedido::Cancelado;
            $podeEnviar = in_array($p->status, [\App\Enums\StatusPedido::Confirmado, \App\Enums\StatusPedido::Faturado], true);
            $podeCancelar = in_array($p->status, [\App\Enums\StatusPedido::Rascunho, \App\Enums\StatusPedido::Confirmado], true);
            $fone = preg_replace('/\D/', '', (string) ($p->cliente->whatsapp ?? $p->cliente->telefone ?? ''));
        @endphp
        <div class="border rounded p-3 mb-2" data-pedido="{{ $p->id }}">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <strong>Pedido #{{ $p->numero }}</strong>
                    <span class="badge text-bg-{{ $cancelado ? 'danger' : ($s['pago'] ? 'success' : 'secondary') }} ms-1">{{ $s['rotulo'] }}</span>
                    @if($p->loja_estornado_em)<span class="badge text-bg-warning ms-1">Dinheiro devolvido em {{ $p->loja_estornado_em->format('d/m H:i') }}</span>
                    @elseif($cancelado && $s['pago'])<span class="badge text-bg-warning ms-1">PAGO — falta devolver</span>@endif
                    <div class="text-muted small">{{ $p->created_at->format('d/m/Y H:i') }} · {{ $p->cliente->nome_razao_social ?? 'Cliente' }}@if($p->cliente?->cidade) · {{ $p->cliente->cidade }}/{{ $p->cliente->uf }}@endif
                        · {{ $p->metodo_entrega === 'retirada' ? 'Retirada na loja' : 'Entrega' . ($p->frete_servico_nome ? ' (' . $p->frete_servico_nome . ')' : '') }}</div>
                    <div class="small mt-1">@foreach($p->itensResumo as $i){{ rtrim(rtrim(number_format((float) $i->quantidade, 3, ',', '.'), '0'), ',') }}× {{ $i->descricao }}@if(! $loop->last); @endif @endforeach</div>
                    @if($p->loja_enviado_em)<div class="small mt-1 text-success"><i class="bi bi-truck me-1"></i>Enviado em {{ $p->loja_enviado_em->format('d/m/Y H:i') }}@if($p->loja_transportadora) por {{ $p->loja_transportadora }}@endif @if($p->loja_rastreio_codigo) · rastreio <strong>{{ $p->loja_rastreio_codigo }}</strong>@endif</div>@endif
                </div>
                <div class="text-end">
                    <div class="fs-5 fw-bold">R$ {{ number_format((float) $p->total, 2, ',', '.') }}</div>
                    <div class="d-flex flex-wrap gap-1 justify-content-end mt-1">
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('app.pedidos.show', $p->id) }}">Abrir no ERP</a>
                        <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="{{ $loja->enderecoInterno('pedido/' . $p->loja_token) }}">Página do cliente</a>
                        @if($fone)<a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="https://wa.me/55{{ $fone }}"><i class="bi bi-whatsapp"></i></a>@endif
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-2">
                @if($podeEnviar && $p->metodo_entrega !== 'retirada')
                    <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#envio-{{ $p->id }}"><i class="bi bi-truck me-1"></i>{{ $p->loja_enviado_em ? 'Corrigir o envio' : 'Informar o envio' }}</button>
                @endif
                @if($podeCancelar)<button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#cancelar-{{ $p->id }}">Cancelar o pedido</button>@endif
                @if($cancelado && $s['pago'] && ! $p->loja_estornado_em && $podeDevolver)<button class="btn btn-sm btn-warning" type="button" data-bs-toggle="collapse" data-bs-target="#devolver-{{ $p->id }}">Devolver o dinheiro</button>@endif
            </div>

            @if($podeEnviar && $p->metodo_entrega !== 'retirada')
            <form class="collapse mt-2" id="envio-{{ $p->id }}" method="POST" action="{{ route('app.loja-online.pedidos.envio', $p->id) }}">
                @csrf
                <div class="row g-2 align-items-end">
                    <div class="col-md-3"><label class="form-label small mb-1" for="tr-{{ $p->id }}">Transportadora</label><input class="form-control form-control-sm" id="tr-{{ $p->id }}" name="transportadora" maxlength="60" value="{{ $p->loja_transportadora ?? $p->frete_servico_nome }}" placeholder="Correios, Jadlog, motoboy…"></div>
                    <div class="col-md-3"><label class="form-label small mb-1" for="cod-{{ $p->id }}">Código de rastreio</label><input class="form-control form-control-sm" id="cod-{{ $p->id }}" name="codigo" maxlength="60" value="{{ $p->loja_rastreio_codigo }}" placeholder="AA123456789BR"></div>
                    <div class="col-md-4"><label class="form-label small mb-1" for="lk-{{ $p->id }}">Link de rastreio (opcional)</label><input class="form-control form-control-sm" id="lk-{{ $p->id }}" name="link" type="url" maxlength="255" value="{{ $p->loja_rastreio_url }}" placeholder="https://…"></div>
                    <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Gravar e avisar</button></div>
                </div>
                <div class="form-text">Código dos Correios ganha o link sozinho. O cliente vê na página do pedido e recebe o e-mail "pedido enviado" (se o aviso de andamento estiver ligado).</div>
            </form>
            @endif
            @if($podeCancelar)
            <form class="collapse mt-2" id="cancelar-{{ $p->id }}" method="POST" action="{{ route('app.loja-online.pedidos.cancelar', $p->id) }}">
                @csrf
                <div class="input-group input-group-sm"><input class="form-control" name="motivo" required minlength="3" maxlength="200" placeholder="Motivo do cancelamento (fica no pedido)" aria-label="Motivo do cancelamento"><button class="btn btn-danger">Confirmar o cancelamento</button></div>
                <div class="form-text">Libera o estoque reservado e avisa o cliente por e-mail. @if($s['pago'])<strong>Este pedido está pago:</strong> depois de cancelar, falta devolver o dinheiro.@endif</div>
            </form>
            @endif
            @if($cancelado && $s['pago'] && ! $p->loja_estornado_em && $podeDevolver)
            <form class="collapse mt-2" id="devolver-{{ $p->id }}" method="POST" action="{{ route('app.loja-online.pedidos.devolver', $p->id) }}">
                @csrf
                <div class="row g-2 align-items-center">
                    <div class="col-md-5"><input class="form-control form-control-sm" name="motivo" required minlength="3" maxlength="150" placeholder="Motivo da devolução" aria-label="Motivo da devolução"></div>
                    <div class="col-md-7">
                        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="ja_devolvi" value="1" id="jd-{{ $p->id }}"><label class="form-check-label small" for="jd-{{ $p->id }}">Já devolvi por fora (só registrar)</label></div>
                        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="confirmo" value="1" id="cf-{{ $p->id }}" required><label class="form-check-label small" for="cf-{{ $p->id }}">Confirmo devolver R$ {{ number_format((float) $p->total, 2, ',', '.') }}</label></div>
                        <button class="btn btn-sm btn-warning">Devolver</button>
                    </div>
                </div>
                <div class="form-text">Pagamento feito pelo Asaas é devolvido automaticamente pelo mesmo meio. Nos outros (Sicredi, InfinitePay), devolva no banco/provedor e marque "já devolvi".</div>
            </form>
            @endif
        </div>
    @empty
        <p class="text-muted mb-0">Nenhum pedido aqui.</p>
    @endforelse
    {{ $pedidos->links() }}
</x-erp.card>

<x-erp.card title="Estoque reservado pelos pedidos online" icon="lock" class="mb-3">
    <p class="small text-muted">O estoque só baixa quando o pedido é <strong>faturado</strong>. Até lá, a quantidade abaixo já não aparece na vitrine — mas o PDV da loja física ainda enxerga a peça. Separe estes itens.</p>
    @if($reservado->isEmpty())<p class="mb-0 text-muted">Nada reservado agora.</p>
    @else
        <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Código</th><th>Produto</th><th class="text-end">Reservado</th></tr></thead><tbody>
        @foreach($reservado as $r)<tr><td>{{ $r['codigo'] }}</td><td>{{ $r['nome'] }}</td><td class="text-end">{{ rtrim(rtrim(number_format($r['qtd'], 3, ',', '.'), '0'), ',') }}</td></tr>@endforeach
        </tbody></table></div>
    @endif
</x-erp.card>

<form method="POST" action="{{ route('app.loja-online.secao.salvar', 'vendas') }}">
    @csrf @method('PUT')
    <x-erp.card title="Regras de venda" icon="sliders" class="mb-3">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="reservar_minutos" class="form-label">Segurar a peça enquanto aguarda o pagamento (minutos)</label>
                <input type="number" min="0" max="1440" class="form-control" name="reservar_minutos" id="reservar_minutos" value="{{ old('reservar_minutos', (int) $loja->opcao('reservar_minutos')) }}">
                <div class="form-text">0 = não segura. Pedido pago fica reservado até faturar ou cancelar, sempre.</div>
            </div>
            <div class="col-md-4">
                <label for="carrinho_lembrete_horas" class="form-label">Lembrar o carrinho parado depois de (horas)</label>
                <input type="number" min="1" max="72" class="form-control" name="carrinho_lembrete_horas" id="carrinho_lembrete_horas" value="{{ old('carrinho_lembrete_horas', (int) $loja->opcao('carrinho_lembrete_horas')) }}">
                <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" role="switch" name="carrinho_lembrete" id="carrinho_lembrete" value="1" @checked($loja->opcao('carrinho_lembrete'))><label class="form-check-label" for="carrinho_lembrete">Enviar 1 e-mail de lembrete do carrinho</label></div>
            </div>
            <div class="col-md-4">
                @foreach(['boleto' => 'Aceitar boleto' . ($temAsaas ? '' : ' (precisa do Asaas ligado)'), 'conta_cliente' => 'Conta do cliente (entrar / meus pedidos)', 'avise_me' => '"Avise-me quando chegar" no produto esgotado', 'avaliacoes' => 'Avaliações de produto', 'avaliacoes_moderar' => 'Conferir a avaliação antes de publicar'] as $k => $rotulo)
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="{{ $k }}" id="{{ $k }}" value="1" @checked($loja->opcao($k)) @disabled($k === 'boleto' && ! $temAsaas)><label class="form-check-label" for="{{ $k }}">{{ $rotulo }}</label></div>
                @endforeach
            </div>
        </div>
        <div class="mt-3"><button class="btn btn-primary">Salvar regras</button></div>
    </x-erp.card>
</form>
@endsection
