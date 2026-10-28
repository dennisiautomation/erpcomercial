@extends('layouts.app')

@section('title', 'Conferir NF-e ' . $entrada->numero)

@section('content')
@php
    $unidades = ['UN', 'KG', 'CX', 'PCT', 'LT', 'MT', 'M2', 'M3', 'PAR', 'JG'];
    $fmt = fn ($v, $d = 2) => number_format((float) $v, $d, ',', '.');
@endphp

<x-erp.page-header title="Conferir NF-e nº {{ $entrada->numero }}" icon="clipboard-check"
    subtitle="Ligue cada produto da nota a um produto seu e confirme — só então o estoque é lançado">
    <a href="{{ route('app.entradas-nfe.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

@if($avisoDestinatario)
    <div class="alert alert-warning d-flex align-items-start">
        <i class="bi bi-exclamation-triangle me-2 fs-5"></i>
        <div>{{ $avisoDestinatario }}</div>
    </div>
@endif

<div class="erp-card mb-3">
    <div class="card-body">
        <div class="row g-3 small">
            <div class="col-md-5">
                <span class="text-muted d-block">Fornecedor</span>
                <strong>{{ $entrada->nome_emitente }}</strong>
                <span class="text-muted d-block font-monospace">{{ \App\Support\Cnpj::formatar($entrada->cnpj_emitente) }}</span>
                @if($entrada->fornecedor_id)
                    <span class="badge bg-success-subtle text-success mt-1">Já cadastrado</span>
                @else
                    <span class="badge bg-info-subtle text-info mt-1">Será cadastrado ao confirmar</span>
                @endif
            </div>
            <div class="col-6 col-md-2">
                <span class="text-muted d-block">Nota</span>
                <strong>nº {{ $entrada->numero }}</strong> <span class="text-muted">série {{ $entrada->serie }}</span>
            </div>
            <div class="col-6 col-md-2">
                <span class="text-muted d-block">Emissão</span>
                <strong>{{ $entrada->data_emissao?->format('d/m/Y') }}</strong>
            </div>
            <div class="col-6 col-md-3 text-md-end">
                <span class="text-muted d-block">Total da nota</span>
                <strong class="fs-5">R$ {{ $fmt($entrada->valor_total) }}</strong>
                <span class="text-muted d-block">produtos R$ {{ $fmt($entrada->valor_produtos) }}</span>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('app.entradas-nfe.confirmar', $entrada) }}" id="form-entrada"
      data-confirm="Lançar esta nota no estoque? Depois de confirmada ela não pode ser lançada de novo.">
    @csrf

    <div class="erp-card mb-3">
        <div class="card-header"><i class="bi bi-box-seam me-1"></i> Produtos da nota ({{ $entrada->itens->count() }})</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="tabela-itens">
                <thead class="table-light small">
                    <tr>
                        <th style="width:32%">Na nota</th>
                        <th style="width:12%" class="text-end">Qtd · custo</th>
                        <th>No seu estoque</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($entrada->itens as $item)
                    @php
                        $acao = old("itens.{$item->id}.acao", $item->acao);
                        $produtoId = old("itens.{$item->id}.produto_id", $item->produto_id);
                        $fator = old("itens.{$item->id}.fator_conversao", rtrim(rtrim(number_format((float) $item->fator_conversao, 4, '.', ''), '0'), '.'));
                    @endphp
                    <tr class="linha-item" data-qtd="{{ (float) $item->quantidade }}" data-custo="{{ (float) $item->custo_total }}"
                        data-custo-atual="{{ (float) ($item->produto?->preco_custo ?? 0) }}" data-ucom="{{ $item->unidade_comercial }}">
                        <td class="small">
                            <strong class="d-block">{{ $item->descricao }}</strong>
                            <span class="text-muted">
                                cód. {{ $item->codigo_fornecedor }}
                                @if($item->ean) · EAN {{ $item->ean }} @endif
                                @if($item->ncm) · NCM {{ $item->ncm }} @endif
                            </span>
                        </td>
                        <td class="text-end small">
                            <strong>{{ $fmt($item->quantidade, ((float) $item->quantidade == floor((float) $item->quantidade)) ? 0 : 3) }} {{ $item->unidade_comercial }}</strong>
                            <span class="text-muted d-block">R$ {{ $fmt($item->custo_total) }}</span>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-2 align-items-start">
                                <select name="itens[{{ $item->id }}][acao]" class="form-select form-select-sm sel-acao" style="max-width:190px">
                                    <option value="vincular" @selected($acao === 'vincular')>Ligar a um produto meu</option>
                                    <option value="criar" @selected($acao === 'criar')>Criar produto novo</option>
                                    <option value="ignorar" @selected($acao === 'ignorar')>Não lançar</option>
                                </select>

                                <div class="bloco-vincular flex-grow-1" style="min-width:220px">
                                    <input type="text" class="form-control form-control-sm"
                                           placeholder="Buscar produto (nome, código, código de barras)…"
                                           value="{{ $item->produto?->descricao }}"
                                           data-autocomplete="{{ route('app.search.produtos') }}"
                                           data-autocomplete-target="produto-{{ $item->id }}"
                                           data-autocomplete-display="descricao">
                                    <input type="hidden" name="itens[{{ $item->id }}][produto_id]" id="produto-{{ $item->id }}" value="{{ $produtoId }}">
                                    @if($item->produto && $acao === 'vincular')
                                        <small class="text-success"><i class="bi bi-link-45deg"></i> ligado automaticamente</small>
                                    @endif
                                </div>

                                <div class="bloco-criar d-flex gap-2">
                                    <div class="input-group input-group-sm" style="width:170px">
                                        <span class="input-group-text">Venda R$</span>
                                        <input type="text" name="itens[{{ $item->id }}][preco_venda]" class="form-control"
                                               inputmode="decimal" value="{{ old("itens.{$item->id}.preco_venda") }}" placeholder="0,00">
                                    </div>
                                    <select name="itens[{{ $item->id }}][unidade_medida]" class="form-select form-select-sm" style="width:80px" title="Unidade do produto novo">
                                        <option value="">un.</option>
                                        @foreach($unidades as $u)
                                            <option value="{{ $u }}" @selected(old("itens.{$item->id}.unidade_medida") === $u)>{{ $u }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="bloco-fator input-group input-group-sm" style="width:200px" title="Ex.: a nota vende por caixa de 12 → 1 CX = 12">
                                    <span class="input-group-text">1 {{ $item->unidade_comercial ?: 'un' }} =</span>
                                    <input type="number" step="0.0001" min="0.0001" name="itens[{{ $item->id }}][fator_conversao]"
                                           class="form-control inp-fator" value="{{ $fator }}">
                                    <span class="input-group-text">un</span>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-1 resumo-item"></small>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="erp-card h-100">
                <div class="card-header"><i class="bi bi-boxes me-1"></i> Estoque e custo</div>
                <div class="card-body">
                    @if($estoques->count() > 1)
                        <label for="campo-estoque_id" class="form-label fw-semibold">Em qual estoque a mercadoria entra</label>
                        <select id="campo-estoque_id" name="estoque_id" class="form-select mb-3" required>
                            @foreach($estoques as $e)
                                <option value="{{ $e->id }}" @selected((int) old('estoque_id', $entrada->estoque_id) === $e->id)>{{ $e->nome }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="hidden" name="estoque_id" value="{{ $estoques->first()?->id }}">
                        <p class="small text-muted mb-3">Entra no estoque <strong>{{ $estoques->first()?->nome ?? '—' }}</strong> desta loja.</p>
                    @endif

                    <div class="form-check form-switch">
                        <input type="hidden" name="atualizar_custo" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="atualizar_custo" value="1" id="atualizar_custo"
                               @checked(old('atualizar_custo', '1') === '1')>
                        <label class="form-check-label" for="atualizar_custo">
                            Atualizar o <strong>preço de custo</strong> dos produtos com o custo desta nota
                        </label>
                        <div class="form-text">Custo = valor do item + frete, seguro, IPI e ST da nota − desconto, dividido pela quantidade que entra. O preço de venda não muda.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="erp-card h-100">
                <div class="card-header"><i class="bi bi-cash-coin me-1"></i> Contas a pagar</div>
                <div class="card-body">
                    @if($podeContaPagar)
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="gerar_conta_pagar" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" name="gerar_conta_pagar" value="1" id="gerar_conta_pagar"
                                   @checked(old('gerar_conta_pagar') === '1')>
                            <label class="form-check-label" for="gerar_conta_pagar">Lançar esta nota em <strong>Contas a pagar</strong></label>
                        </div>
                        <div id="bloco-contas" class="small">
                            @if(!empty($entrada->duplicatas))
                                <p class="text-muted mb-1">A nota traz {{ count($entrada->duplicatas) }} parcela(s):</p>
                                <ul class="list-unstyled mb-0">
                                    @foreach($entrada->duplicatas as $dup)
                                        <li>
                                            <i class="bi bi-calendar3 me-1"></i>
                                            {{ $dup['vencimento'] ? \Carbon\Carbon::parse($dup['vencimento'])->format('d/m/Y') : '—' }}
                                            — <strong>R$ {{ $fmt($dup['valor']) }}</strong>
                                            @if(!empty($dup['numero'])) <span class="text-muted">({{ $dup['numero'] }})</span> @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-muted mb-2">A nota não traz as parcelas. Será uma conta de <strong>R$ {{ $fmt($entrada->valor_total) }}</strong> com vencimento em:</p>
                                <input type="date" name="vencimento" class="form-control form-control-sm" style="max-width:180px"
                                       value="{{ old('vencimento', now()->addDays(30)->toDateString()) }}">
                            @endif
                        </div>
                    @else
                        <p class="small text-muted mb-0">Lançar contas a pagar é do perfil financeiro/dono. A entrada no estoque funciona normalmente; o financeiro lança a conta depois.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3">
        <button type="submit" form="form-descartar" class="btn btn-outline-danger">
            <i class="bi bi-trash me-1"></i> Descartar
        </button>
        <button type="submit" class="btn btn-success btn-lg">
            <i class="bi bi-box-arrow-in-down me-1"></i> Confirmar entrada no estoque
        </button>
    </div>
</form>

<form method="POST" action="{{ route('app.entradas-nfe.destroy', $entrada) }}" id="form-descartar"
      data-confirm="Descartar esta conferência? Nada foi lançado; você pode enviar o XML de novo depois.">
    @csrf
    @method('DELETE')
</form>
@endsection

@push('scripts')
<script>
(function () {
    const brl = v => v.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const num = v => v.toLocaleString('pt-BR', { maximumFractionDigits: 3 });

    document.querySelectorAll('.linha-item').forEach(tr => {
        const sel = tr.querySelector('.sel-acao');
        const fator = tr.querySelector('.inp-fator');
        const resumo = tr.querySelector('.resumo-item');
        const qtd = parseFloat(tr.dataset.qtd), custo = parseFloat(tr.dataset.custo);
        let custoAtual = parseFloat(tr.dataset.custoAtual);
        // Trocou o produto na busca: o custo atual mostrado era o do anterior
        tr.addEventListener('autocomplete-select', () => { custoAtual = 0; atualizar(); });

        function atualizar() {
            const acao = sel.value;
            // d-none (e não style.display): o d-flex do Bootstrap é !important e venceria
            tr.querySelector('.bloco-vincular').classList.toggle('d-none', acao !== 'vincular');
            tr.querySelector('.bloco-criar').classList.toggle('d-none', acao !== 'criar');
            tr.querySelector('.bloco-fator').classList.toggle('d-none', acao === 'ignorar');
            tr.classList.toggle('opacity-50', acao === 'ignorar');

            if (acao === 'ignorar') { resumo.textContent = 'Este item não entra no estoque.'; return; }
            const f = parseFloat(fator.value) || 0;
            const entra = qtd * f;
            let txt = f > 0 ? `Entram ${num(entra)} un · custo R$ ${brl(custo / entra)} cada` : 'Informe a conversão.';
            if (acao === 'vincular' && custoAtual > 0 && f > 0) txt += ` (custo atual R$ ${brl(custoAtual)})`;
            if (f === 1 && /^(CX|CAIXA|PCT|PT|FD|FARDO|DZ|DUZIA|KIT|JG|PAR)$/.test(tr.dataset.ucom)) {
                txt += ` — a nota vende por ${tr.dataset.ucom}: quantas unidades vêm em cada? Ajuste o "1 ${tr.dataset.ucom} =".`;
            }
            resumo.textContent = txt;
        }
        sel.addEventListener('change', atualizar);
        fator.addEventListener('input', atualizar);
        atualizar();
    });

    const chkConta = document.getElementById('gerar_conta_pagar');
    const blocoContas = document.getElementById('bloco-contas');
    if (chkConta && blocoContas) {
        const t = () => blocoContas.style.display = chkConta.checked ? '' : 'none';
        chkConta.addEventListener('change', t); t();
    }
})();
</script>
@endpush
