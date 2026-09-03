@extends('layouts.app')

@section('title', 'Registrar devolução')

@section('content')
<x-erp.page-header title="Registrar devolução" icon="box-arrow-in-left"
    subtitle="O cliente devolve e não leva nada agora — a sobra vira vale ou dinheiro">
    <a href="{{ route('app.trocas.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
</x-erp.page-header>

<div class="alert alert-warning border-0 bg-warning bg-opacity-10 mb-4">
    <i class="bi bi-upc-scan me-1"></i> Para <strong>trocar por outro produto na hora</strong>, use o <strong>PDV (tecla F6)</strong>: lá o que o cliente leva é bipado e a diferença fecha na mesma tela.
</div>

@if(! $venda)
    <x-erp.card title="1. Qual venda?" icon="search">
        <form method="GET" action="{{ route('app.trocas.create') }}" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Número da venda, código do cupom (V123) ou nome do cliente</label>
                <input type="text" name="busca" class="form-control" value="{{ request('busca') }}" autofocus placeholder="Ex.: 158, V158 ou Maria">
            </div>
            <div class="col-md-2"><button class="btn btn-erp-primary w-100"><i class="bi bi-search me-1"></i> Buscar</button></div>
        </form>

        @if(request()->filled('busca'))
        <hr>
        @forelse($resultados as $r)
            <a href="{{ route('app.trocas.create', ['venda' => $r->id]) }}" class="d-flex justify-content-between align-items-center border rounded p-2 mb-2 text-decoration-none text-body">
                <div><strong>Venda #{{ $r->numero }}</strong> <small class="text-muted">{{ $r->created_at->format('d/m/Y H:i') }} · {{ $r->unidade->nome ?? '' }}</small>
                    <div class="small text-muted">{{ $r->cliente->nome_razao_social ?? 'Consumidor' }}</div></div>
                <div class="fw-semibold">R$ {{ number_format($r->total, 2, ',', '.') }}</div>
            </a>
        @empty
            <p class="text-muted mb-0">Nenhuma venda concluída encontrada para "{{ request('busca') }}".</p>
        @endforelse
        @endif
    </x-erp.card>
@else
    @php $pol = $situacao['politica']; @endphp
    <form method="POST" action="{{ route('app.trocas.store') }}" id="formDevolucao">
        @csrf
        <input type="hidden" name="venda_id" value="{{ $venda->id }}">

        <x-erp.card title="Venda #{{ $venda->numero }}" icon="bag-check" class="mb-4">
            <div class="d-flex justify-content-between flex-wrap gap-2">
                <div>
                    <div>{{ $situacao['venda']['data'] }} · {{ $situacao['venda']['loja'] }} · <strong>R$ {{ number_format($situacao['venda']['total'], 2, ',', '.') }}</strong></div>
                    <div class="text-muted small">{{ $situacao['venda']['cliente'] ?? 'Consumidor (sem cadastro)' }}</div>
                </div>
                <a href="{{ route('app.trocas.create') }}" class="btn btn-sm btn-outline-secondary">Outra venda</a>
            </div>
            @if(! $situacao['pode_trocar'])
                <div class="alert alert-danger mt-3 mb-0"><i class="bi bi-x-circle me-1"></i> Esta venda não tem itens disponíveis para devolução.</div>
            @elseif($pol['fora_prazo'])
                <div class="alert alert-warning mt-3 mb-0"><i class="bi bi-exclamation-triangle me-1"></i> Venda de <strong>{{ $pol['dias_desde_venda'] }} dias</strong> — fora do prazo de troca da loja ({{ $pol['prazo_dias'] }} dias).
                    {{ $pol['exige_gerente_fora_prazo'] ? 'Precisa da autorização de um gerente (abaixo).' : ($pol['usuario_e_gerente'] ? 'Você é gerente: pode autorizar.' : '') }}</div>
            @else
                <div class="alert alert-success mt-3 mb-0"><i class="bi bi-check-circle me-1"></i> Dentro do prazo de troca ({{ $pol['dias_desde_venda'] }} de {{ $pol['prazo_dias'] > 0 ? $pol['prazo_dias'] : '∞' }} dias).</div>
            @endif
        </x-erp.card>

        @if($situacao['pode_trocar'])
        <x-erp.card title="2. O que volta" icon="box-arrow-in-left" class="mb-4">
            <div class="table-responsive">
                <table class="table align-middle mb-2" id="tabelaItens">
                    <thead class="table-light"><tr><th>Item</th><th class="text-end">Vendido</th><th class="text-end" style="width:130px;">Devolver</th><th class="text-end">Valor unit.</th><th class="text-center">Volta ao estoque?</th></tr></thead>
                    <tbody>
                    @foreach($situacao['itens'] as $i => $item)
                        <tr data-unit="{{ $item['valor_unitario'] }}" @if($item['disponivel'] <= 0) class="text-muted" @endif>
                            <td>{{ $item['descricao'] }} @if($item['devolvida'] > 0)<small class="text-warning">({{ $item['devolvida'] }} já devolvido)</small>@endif
                                <input type="hidden" name="itens[{{ $i }}][venda_item_id]" value="{{ $item['venda_item_id'] }}"></td>
                            <td class="text-end">{{ $item['quantidade'] }}</td>
                            <td class="text-end"><input type="number" name="itens[{{ $i }}][quantidade]" class="form-control form-control-sm text-end qtd-dev" min="0" max="{{ $item['disponivel'] }}" step="1" value="{{ old("itens.$i.quantidade", $item['disponivel']) }}" @disabled($item['disponivel'] <= 0)></td>
                            <td class="text-end">R$ {{ number_format($item['valor_unitario'], 2, ',', '.') }}</td>
                            <td class="text-center">
                                <input type="hidden" name="itens[{{ $i }}][retorna_estoque]" value="0">
                                <input type="checkbox" class="form-check-input" name="itens[{{ $i }}][retorna_estoque]" value="1" @checked(! $item['e_servico']) @disabled($item['e_servico']) title="Desmarque se a peça está avariada e não volta à prateleira">
                                @if($estoques->count() > 1)
                                <select name="itens[{{ $i }}][estoque_id]" class="form-select form-select-sm mt-1">
                                    @foreach($estoques as $e)<option value="{{ $e->id }}" @selected($e->permite_venda)>{{ $e->nome }}</option>@endforeach
                                </select>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr class="table-light"><th colspan="3" class="text-end">Valor a devolver</th><th class="text-end" id="totalDev">R$ 0,00</th><th></th></tr></tfoot>
                </table>
            </div>
            @if($situacao['parcelas_abertas'] > 0)
                <div class="text-warning small"><i class="bi bi-info-circle me-1"></i> Esta venda tem R$ {{ number_format($situacao['parcelas_abertas'], 2, ',', '.') }} em parcelas abertas (crediário/boleto): o valor devolvido abate essas parcelas antes de virar crédito.</div>
            @endif

            <div class="row g-3 mt-1">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Motivo</label>
                    <select name="motivo" class="form-select">
                        @foreach($situacao['motivos'] as $k => $v)<option value="{{ $k }}" @selected(old('motivo') === $k)>{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Detalhe <small class="text-muted">(opcional)</small></label>
                    <input type="text" name="motivo_texto" class="form-control" maxlength="500" value="{{ old('motivo_texto') }}">
                </div>
            </div>
        </x-erp.card>

        <x-erp.card title="3. A sobra a favor do cliente…" icon="cash-coin" class="mb-4">
            @php $sobraSel = old('sobra_destino', 'vale'); @endphp
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-check p-3 border rounded h-100" for="sobra_vale">
                        <input class="form-check-input ms-0 me-2" type="radio" name="sobra_destino" id="sobra_vale" value="vale" @checked($sobraSel === 'vale')>
                        <strong>vira crédito na loja (vale)</strong>
                        <div class="small text-muted">Código impresso, {{ $pol['vale_validade_dias'] > 0 ? 'válido por ' . $pol['vale_validade_dias'] . ' dias' : 'sem validade' }}. O cliente usa no PDV.</div>
                    </label>
                </div>
                <div class="col-md-6">
                    <label class="form-check p-3 border rounded h-100 {{ $pol['permite_dinheiro'] ? '' : 'opacity-50' }}" for="sobra_dinheiro">
                        <input class="form-check-input ms-0 me-2" type="radio" name="sobra_destino" id="sobra_dinheiro" value="dinheiro" @checked($sobraSel === 'dinheiro') @disabled(! $pol['permite_dinheiro'])>
                        <strong>é devolvida em dinheiro pela gaveta</strong>
                        <div class="small text-muted">{{ $pol['permite_dinheiro'] ? 'Exige caixa aberto nesta loja; a saída entra no fechamento.' : 'Desligado nesta loja (Configurações da Loja → Trocas).' }}</div>
                    </label>
                </div>
            </div>

            <div id="gerenteWrap" class="mt-3 p-3 border border-warning rounded" style="display:none;">
                <div class="text-warning small mb-2"><i class="bi bi-shield-lock me-1"></i> <span id="gerenteMotivo"></span> — autorização de um gerente:</div>
                <div class="row g-2">
                    <div class="col-md-6"><input type="email" name="gerente_email" class="form-control" placeholder="E-mail do gerente" autocomplete="off"></div>
                    <div class="col-md-6"><input type="password" name="gerente_senha" class="form-control" placeholder="Senha do gerente" autocomplete="new-password"></div>
                </div>
            </div>

            <div class="mt-3">
                <label class="form-label">Observações <small class="text-muted">(opcional)</small></label>
                <input type="text" name="observacoes" class="form-control" maxlength="1000" value="{{ old('observacoes') }}">
            </div>
        </x-erp.card>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('app.trocas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button class="btn btn-erp-primary" data-confirm="Confirmar a devolução? O estoque será atualizado e a sobra gerada conforme a opção escolhida."><i class="bi bi-check-lg me-1"></i> Confirmar devolução</button>
        </div>
        @endif
    </form>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const pol = @json($situacao['politica'] ?? null);
    if (!pol) return;
    const fmt = v => 'R$ ' + (v || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    function recalc() {
        let t = 0;
        document.querySelectorAll('#tabelaItens tbody tr').forEach(tr => {
            const inp = tr.querySelector('.qtd-dev'); if (!inp || inp.disabled) return;
            const max = parseFloat(inp.max) || 0; let q = parseFloat(inp.value) || 0;
            if (q > max) { q = max; inp.value = max; } if (q < 0) { q = 0; inp.value = 0; }
            t += q * (parseFloat(tr.dataset.unit) || 0);
        });
        document.getElementById('totalDev').textContent = fmt(Math.round(t * 100) / 100);
    }
    function gerente() {
        const motivos = [];
        if (pol.exige_gerente_fora_prazo) motivos.push('fora do prazo');
        const sobra = document.querySelector('input[name="sobra_destino"]:checked')?.value;
        if (sobra === 'dinheiro' && pol.exige_gerente_dinheiro) motivos.push('devolução em dinheiro');
        document.getElementById('gerenteWrap').style.display = motivos.length ? 'block' : 'none';
        document.getElementById('gerenteMotivo').textContent = motivos.join(' + ');
    }
    document.querySelectorAll('.qtd-dev').forEach(i => i.addEventListener('input', recalc));
    document.querySelectorAll('input[name="sobra_destino"]').forEach(r => r.addEventListener('change', gerente));
    recalc(); gerente();
})();
</script>
@endpush
