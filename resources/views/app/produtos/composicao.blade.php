@extends('layouts.app')

@section('title', 'Ficha técnica e opções')

@section('content')
@php
    $tipo = $produto->tipoEnum();
    $temFicha = in_array($tipo, [\App\Enums\TipoProduto::Produzido, \App\Enums\TipoProduto::Adicional], true);
    $temGrupos = $tipo->vendeNoPdv();
    $linhas = old('ficha', $produto->fichaItens->map(fn ($l) => ['insumo_id' => $l->insumo_id, 'quantidade' => (float) $l->quantidade])->all());
    if ($linhas === []) {
        $linhas = [['insumo_id' => '', 'quantidade' => '']];
    }
    $gruposMarcados = collect(old('grupos', $produto->gruposOpcoes->pluck('id')->all()))->map(fn ($v) => (int) $v)->all();
    $unidades = ['UN' => 'un', 'KG' => 'kg', 'LT' => 'L', 'CX' => 'cx', 'PCT' => 'pct', 'MT' => 'm', 'M2' => 'm²', 'M3' => 'm³', 'PAR' => 'par', 'JG' => 'jg'];
@endphp

<x-erp.page-header :title="'Ficha técnica e opções: ' . $produto->descricao" icon="cup-hot" :subtitle="$tipo->label() . ' · ' . $tipo->descricao()">
    <a href="{{ route('app.produtos.edit', $produto) }}" class="btn btn-erp-outline"><i class="bi bi-pencil me-1"></i> Editar produto</a>
    <a href="{{ route('app.produtos.show', $produto) }}" class="btn btn-erp-outline"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
</x-erp.page-header>

@if(! $temFicha && ! $temGrupos)
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-1"></i>
        Produto do tipo <strong>Insumo</strong> não tem receita nem opções: ele entra nas receitas dos outros.
        Mude o tipo em <a href="{{ route('app.produtos.edit', $produto) }}">Editar produto</a> se ele for vendido no PDV.
    </div>
@else
<form method="POST" action="{{ route('app.produtos.composicao.update', $produto) }}">
    @csrf
    @method('PUT')

    @if($temFicha)
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <strong><i class="bi bi-journal-text me-1"></i> Ficha técnica (receita de 1 {{ $unidades[$produto->unidade_medida] ?? 'un' }})</strong>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddInsumo"><i class="bi bi-plus-lg me-1"></i> Adicionar insumo</button>
        </div>
        <div class="card-body">
            @error('ficha') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
            @foreach($errors->get('ficha.*') as $msgs)
                <div class="alert alert-danger py-2">{{ $msgs[0] }}</div>
            @endforeach
            <p class="small text-muted mb-2">
                Quantidade na unidade do insumo: café em <strong>kg</strong> → 18 g = <code>0,018</code>; leite em <strong>L</strong> → 150 ml = <code>0,150</code>; copo em <strong>un</strong> → <code>1</code>.
                @if($tipo === \App\Enums\TipoProduto::Adicional)
                    Adicional sem receita não baixa nada do estoque (ex.: tamanho P).
                @else
                    Cada venda baixa estes insumos; o próprio produto não tem saldo.
                @endif
            </p>
            @if($insumos->isEmpty())
                <div class="alert alert-warning py-2">
                    Nenhum insumo cadastrado ainda. Cadastre café, leite, copo… como produto do tipo <strong>Insumo</strong> em
                    <a href="{{ route('app.produtos.create') }}">Novo produto</a>.
                </div>
            @endif
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-2">
                    <thead class="table-light">
                        <tr>
                            <th>Insumo</th>
                            <th style="width:170px;">Quantidade</th>
                            <th class="text-end" style="width:130px;">Custo</th>
                            <th style="width:50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="fichaLista">
                        @foreach($linhas as $i => $l)
                            <tr class="ficha-linha">
                                <td>
                                    <select name="ficha[{{ $i }}][insumo_id]" class="form-select form-select-sm ficha-insumo" aria-label="Insumo">
                                        <option value="">— escolha —</option>
                                        @foreach($insumos as $ins)
                                            <option value="{{ $ins->id }}" data-un="{{ $unidades[$ins->unidade_medida] ?? $ins->unidade_medida }}" data-custo="{{ (float) $ins->preco_custo }}"
                                                @selected((string) ($l['insumo_id'] ?? '') === (string) $ins->id)>
                                                {{ $ins->descricao }}{{ $ins->tipo === 'revenda' ? ' (revenda)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.0001" min="0" name="ficha[{{ $i }}][quantidade]" class="form-control ficha-qtd" value="{{ $l['quantidade'] ?? '' }}" aria-label="Quantidade">
                                        <span class="input-group-text ficha-un">—</span>
                                    </div>
                                </td>
                                <td class="text-end small ficha-custo">—</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remover" title="Remover insumo"><i class="bi bi-x-lg"></i></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2" class="text-end">Custo da receita</th>
                            <th class="text-end" id="custoTotal">R$ {{ number_format($custoFicha, 2, ',', '.') }}</th>
                            <th></th>
                        </tr>
                        <tr>
                            <td colspan="2" class="text-end small text-muted">Preço de venda R$ {{ number_format($produto->preco_venda, 2, ',', '.') }} · margem</td>
                            <td class="text-end small" id="margem">—</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="form-text">Ao salvar, o custo do produto passa a ser o custo da receita (usa o custo cadastrado em cada insumo).</div>
        </div>
    </div>
    @endif

    @if($temGrupos)
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <strong><i class="bi bi-ui-checks-grid me-1"></i> Opções no PDV</strong>
            <a href="{{ route('app.grupos-opcoes.create') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i> Novo grupo</a>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-2">Marque os grupos que o caixa escolhe ao lançar este produto. A ordem da tela do PDV segue a ordem desta lista.</p>
            @forelse($grupos as $g)
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="grupos[]" value="{{ $g->id }}" id="grupo_{{ $g->id }}" @checked(in_array($g->id, $gruposMarcados, true))>
                    <label class="form-check-label" for="grupo_{{ $g->id }}">
                        <strong>{{ $g->nome }}</strong> <span class="text-muted small">— {{ $g->regraTexto() }}</span>
                        <span class="d-block small text-muted">
                            {{ $g->itens->map(fn ($i) => ($i->adicional?->descricao ?? '—') . ($i->precoEfetivo() > 0 ? ' +R$ ' . number_format($i->precoEfetivo(), 2, ',', '.') : ''))->join(' · ') ?: 'sem opções' }}
                        </span>
                    </label>
                </div>
            @empty
                <div class="text-muted small">Nenhum grupo ainda. Crie "Tamanho", "Leite" ou "Extras" em <a href="{{ route('app.grupos-opcoes.create') }}">Novo grupo</a>.</div>
            @endforelse
        </div>
    </div>
    @endif

    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('app.produtos.show', $produto) }}" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Salvar</button>
    </div>
</form>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const lista = document.getElementById('fichaLista');
    if (!lista) return;
    const precoVenda = {{ (float) $produto->preco_venda }};
    const brl = v => 'R$ ' + v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    function recalcular() {
        let total = 0;
        lista.querySelectorAll('.ficha-linha').forEach(tr => {
            const opt = tr.querySelector('.ficha-insumo').selectedOptions[0];
            const qtd = parseFloat(tr.querySelector('.ficha-qtd').value) || 0;
            const un = opt && opt.value ? opt.dataset.un : '—';
            const custo = opt && opt.value ? (parseFloat(opt.dataset.custo) || 0) * qtd : 0;
            tr.querySelector('.ficha-un').textContent = un;
            tr.querySelector('.ficha-custo').textContent = opt && opt.value ? brl(custo) : '—';
            total += custo;
        });
        document.getElementById('custoTotal').textContent = brl(total);
        const m = document.getElementById('margem');
        m.textContent = total > 0 && precoVenda > 0 ? (((precoVenda - total) / precoVenda) * 100).toFixed(0) + '%' : '—';
    }

    let seq = lista.querySelectorAll('.ficha-linha').length;
    document.getElementById('btnAddInsumo').addEventListener('click', () => {
        const nova = lista.querySelector('.ficha-linha').cloneNode(true);
        nova.querySelectorAll('select, input').forEach(el => {
            el.name = el.name.replace(/ficha\[\d+\]/, `ficha[${seq}]`);
            if (el.tagName === 'SELECT') el.selectedIndex = 0; else el.value = '';
        });
        seq++;
        lista.appendChild(nova);
        recalcular();
    });
    lista.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-remover');
        if (!btn) return;
        const linhas = lista.querySelectorAll('.ficha-linha');
        const linha = btn.closest('.ficha-linha');
        if (linhas.length === 1) {
            linha.querySelectorAll('select, input').forEach(el => { if (el.tagName === 'SELECT') el.selectedIndex = 0; else el.value = ''; });
        } else {
            linha.remove();
        }
        recalcular();
    });
    lista.addEventListener('input', recalcular);
    lista.addEventListener('change', recalcular);
    recalcular();
})();
</script>
@endpush
