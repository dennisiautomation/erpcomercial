{{-- Transação na maquininha Stone (venda e conta a receber mostram o mesmo bloco). Recebe $pg (item de pagamento_detalhes). --}}
@php $tx = \App\Services\Pos\PosStone::transacao($pg); @endphp
@if($tx)
<div class="border rounded p-3 mt-2">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <span class="badge bg-dark"><i class="bi bi-credit-card-2-front me-1"></i>Maquininha Stone</span>
        @if($tx['conciliado'])
            <span class="badge bg-success"><i class="bi bi-check2-circle me-1"></i>Conciliado</span>
            <small class="text-muted">valor aprovado na maquininha = valor da venda</small>
        @elseif($tx['aprovado'] !== null)
            <span class="badge bg-danger">Divergente</span>
            <small class="text-muted">aprovado R$ {{ number_format($tx['aprovado'], 2, ',', '.') }} × registrado R$ {{ number_format($tx['valor'], 2, ',', '.') }}</small>
        @else
            <span class="badge bg-secondary">Sem valor aprovado no retorno</span>
        @endif
    </div>
    <div class="row g-2 small">
        <div class="col-6"><span class="text-muted d-block">Valor aprovado</span>{{ $tx['aprovado'] !== null ? 'R$ ' . number_format($tx['aprovado'], 2, ',', '.') : '-' }}</div>
        <div class="col-6"><span class="text-muted d-block">Tipo</span>{{ $tx['tipo'] ?? '-' }} {{ $tx['bandeira'] && in_array($tx['forma'], ['cartao_credito', 'cartao_debito'], true) ? '· ' . $tx['bandeira'] : '' }} {{ $tx['parcelas'] > 1 ? '· ' . $tx['parcelas'] . 'x' : '' }}</div>
        <div class="col-6"><span class="text-muted d-block">Autorização</span>{{ $tx['autorizacao'] ?? '-' }}</div>
        <div class="col-6"><span class="text-muted d-block">ATK (código da transação)</span><code>{{ $tx['atk'] ?? '-' }}</code></div>
        <div class="col-6"><span class="text-muted d-block">Maquininha (nº de série)</span>{{ $tx['terminal'] ?? '-' }}</div>
        <div class="col-6"><span class="text-muted d-block">Data e hora na Stone</span>{{ $tx['hora'] ?? '-' }}</div>
        <div class="col-12"><span class="text-muted d-block">Credenciadora</span>Stone Instituição de Pagamento S.A. {{ $tx['cnpj'] ? '· CNPJ ' . \App\Support\Cnpj::formatar($tx['cnpj']) : '' }}</div>
    </div>
</div>
@endif
