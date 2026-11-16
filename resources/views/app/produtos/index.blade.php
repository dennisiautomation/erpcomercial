@extends('layouts.app')

@section('title', 'Produtos')

@section('content')
<x-erp.page-header title="Produtos" icon="box-seam" subtitle="Gerencie seu catálogo de produtos">
    <a href="{{ route('app.export.produtos') }}" class="btn btn-erp-outline">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Exportar
    </a>
    <a href="{{ route('app.etiquetas.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-upc me-1"></i> Gerar Etiquetas
    </a>
    <div class="btn-group">
        <button data-import="{{ route('app.import.produtos') }}" class="btn btn-erp-outline"
                title="Envie a planilha preenchida (.xlsx ou .csv)">
            <i class="bi bi-upload me-1"></i> Importar planilha
        </button>
        <a href="{{ route('app.import.template', 'produtos') }}" class="btn btn-erp-outline"
           title="Baixa o modelo em Excel (.xlsx) com as colunas certas e exemplos">
            <i class="bi bi-file-earmark-excel me-1"></i> Modelo Excel
        </a>
    </div>
    @if($usaFichaTecnica ?? false)
        <a href="{{ route('app.grupos-opcoes.index') }}" class="btn btn-erp-outline" title="Tamanho, leite, extras: os adicionais escolhidos no PDV">
            <i class="bi bi-ui-checks-grid me-1"></i> Grupos de opções
        </a>
    @endif
    <a href="{{ route('app.produtos.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Novo Produto
    </a>

</x-erp.page-header>

{{-- Filtros --}}
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('app.produtos.index') }}" class="row g-3 align-items-end">
            <div class="{{ ($usaFichaTecnica ?? false) ? 'col-md-3' : 'col-md-4' }}">
                <label for="campo-busca" class="form-label">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input id="campo-busca" type="text" name="busca" class="form-control" placeholder="Descrição, código de barras, SKU ou código interno..." value="{{ request('busca') }}">
                </div>
            </div>
            <div class="col-md-3">
                <label for="campo-categoria_id" class="form-label">Categoria</label>
                <select id="campo-categoria_id" name="categoria_id" class="form-select">
                    <option value="">Todas</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}" {{ request('categoria_id') == $cat->id ? 'selected' : '' }}>{{ $cat->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="campo-status" class="form-label">Status</label>
                <select id="campo-status" name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="ativo" {{ request('status') === 'ativo' ? 'selected' : '' }}>Ativo</option>
                    <option value="inativo" {{ request('status') === 'inativo' ? 'selected' : '' }}>Inativo</option>
                </select>
            </div>
            @if($usaFichaTecnica ?? false)
                <div class="col-md-2">
                    <label for="campo-tipo" class="form-label">Tipo</label>
                    <select id="campo-tipo" name="tipo" class="form-select">
                        <option value="">Todos</option>
                        @foreach(\App\Enums\TipoProduto::cases() as $tipo)
                            <option value="{{ $tipo->value }}" @selected(request('tipo') === $tipo->value)>{{ $tipo->label() }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
                <a href="{{ route('app.produtos.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i> Limpar
                </a>
            </div>
        </form>
    </div>
</div>

@php
    $podeEditarPreco = \App\Http\Middleware\CheckPermission::canUser(auth()->user(), 'produtos', 'editar');
@endphp

{{-- Tabela --}}
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 100px;">Código</th>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th class="text-center">Unidade</th>
                        <th class="text-end">{{ \App\Models\ConfiguracaoLoja::daUnidade()->precoBaseCartao() ? 'Preço (cartão)' : 'Preço Venda' }}</th>
                        <th class="text-end" title="Quantidade em estoque na loja selecionada no topo">
                            Estoque{{ $lojaAtual ? ' ' . $lojaAtual->nome : '' }}
                        </th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width: 150px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($produtos as $produto)
                        @php
                            // Grade como uma linha só (16/11/2026): o principal representa os tamanhos e cores
                            $variacoesGrade = $grades[$produto->id] ?? collect();
                            $ehGrade = $variacoesGrade->count() > 1;
                            $nomesGrade = $ehGrade ? \App\Services\LojaOnline\GradeLoja::nomes($produto) : [];
                        @endphp
                        <tr @if($ehGrade) data-grade-grupo="{{ $produto->id }}" @endif>
                            <td>
                                <code class="fw-bold">{{ $produto->codigo_interno }}</code>
                            </td>
                            <td>
                                @if($ehGrade)
                                    <strong>{{ \App\Services\LojaOnline\GradeLoja::nomeComum($produto) }}</strong>
                                    <br><button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" data-grade-abrir="{{ $produto->id }}" aria-expanded="false" aria-controls="gradeLinhas{{ $produto->id }}">
                                        <i class="bi bi-chevron-right" aria-hidden="true"></i> {{ $variacoesGrade->count() }} opções{{ $nomesGrade ? ' de ' . mb_strtolower(implode(' e ', array_slice($nomesGrade, 0, 2))) : '' }}</button>
                                @else
                                <strong>{{ $produto->descricao }}</strong>
                                @if(($usaFichaTecnica ?? false) && $produto->tipoEnum() !== \App\Enums\TipoProduto::Revenda)
                                    <span class="badge bg-info-subtle text-info-emphasis ms-1">{{ $produto->tipoEnum()->label() }}</span>
                                @endif
                                @if($produto->codigo_barras)
                                    <br><small class="text-muted"><i class="bi bi-upc me-1"></i>{{ $produto->codigo_barras }}</small>
                                @endif
                                @if($produto->sku)
                                    <small class="text-muted ms-2"><i class="bi bi-tag me-1"></i>{{ $produto->sku }}</small>
                                @endif
                                @endif
                            </td>
                            <td>{{ $produto->categoria->nome ?? '-' }}</td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark">{{ $produto->unidade_medida }}</span>
                            </td>
                            <td class="text-end fw-bold text-success text-nowrap">
                                @php
                                    // Preço REAL de cada forma na regra da loja (01/11/2026): na regra do
                                    // cartão o principal é o do cartão, mesmo no produto antigo com o cartão
                                    // fixo — antes a lista mostrava sempre preco_venda e só o rótulo mudava.
                                    $tabelaPrecosLista ??= app(\App\Services\TabelaPrecoService::class);
                                    $configLojaLista ??= \App\Models\ConfiguracaoLoja::daUnidade();
                                    $precoTela = $tabelaPrecosLista->precosParaTela($produto, $configLojaLista);
                                @endphp
                                @if($ehGrade)
                                    @php
                                        $precosGrade = $variacoesGrade->map(fn ($v) => $tabelaPrecosLista->precosParaTela($v, $configLojaLista)['principal']);
                                    @endphp
                                    @if($precosGrade->min() != $precosGrade->max())<small class="text-muted fw-normal">a partir de</small> @endif
                                    R$ {{ number_format($precosGrade->min(), 2, ',', '.') }}
                                @elseif($podeEditarPreco)
                                    <span class="preco-inline" data-url="{{ route('app.produtos.preco', $produto) }}" data-valor="{{ $precoTela['principal'] }}"
                                          role="button" title="Clique para alterar o preço">
                                        <span class="preco-texto">R$ {{ number_format($precoTela['principal'], 2, ',', '.') }}</span>
                                        <i class="bi bi-pencil small text-muted ms-1"></i>
                                    </span>
                                @else
                                    R$ {{ number_format($precoTela['principal'], 2, ',', '.') }}
                                @endif
                                @php
                                    // Preço fixo da outra forma: não acompanha o preço da lista — avisa
                                    // para ninguém se confundir. Débito ≠ crédito aparece separado.
                                    $fixos = $produto->precos->pluck('valor', 'modalidade');
                                    $avisoFixos = [];
                                    if (isset($fixos['debito'], $fixos['credito']) && (float) $fixos['debito'] !== (float) $fixos['credito']) {
                                        $avisoFixos['Débito'] = $fixos['debito'];
                                        $avisoFixos['Crédito'] = $fixos['credito'];
                                    } elseif ($precoTela['secundario'] !== null) {
                                        $avisoFixos[$precoTela['rotulo_secundario']] = $precoTela['secundario'];
                                    }
                                    if (isset($fixos['atacado'])) $avisoFixos['Atacado'] = $fixos['atacado'];
                                @endphp
                                @if($avisoFixos && ! $ehGrade)
                                    <div class="small fw-normal text-warning-emphasis"
                                         title="Preço fixo cadastrado no produto: não muda quando o preço de venda é alterado. Para mudar, use Editar.">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Fixo:
                                        @foreach($avisoFixos as $rotulo => $valor)
                                            {{ $rotulo }} R$ {{ number_format($valor, 2, ',', '.') }}@if(! $loop->last) · @endif
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($produto->ehProduzido())
                                    {{-- Produzido não tem saldo próprio: quem tem são os insumos --}}
                                    <span class="text-muted small" title="Feito na hora: a venda baixa os insumos da ficha técnica">pela receita</span>
                                @elseif($lojaAtual)
                                    @php
                                        $qtd = $ehGrade ? $variacoesGrade->sum(fn ($v) => $saldosLoja[$v->id] ?? 0) : ($saldosLoja[$produto->id] ?? 0);
                                    @endphp
                                    {{-- saldo REAL, com fração quando houver (armadilha 68) --}}
                                    <span class="fw-bold {{ $qtd <= 0 ? 'text-danger' : '' }}">{{ rtrim(rtrim(number_format($qtd, 3, ',', '.'), '0'), ',') }}</span>
                                    <small class="text-muted">{{ $produto->unidade_medida }}</small>
                                    @if($estoquesLoja->count() > 1 && ! $ehGrade)
                                        <div class="small text-muted text-nowrap">
                                            @foreach($estoquesLoja as $est)
                                                {{ $est->nome }} {{ rtrim(rtrim(number_format($saldosPorEstoque[$produto->id][$est->id] ?? 0, 3, ',', '.'), '0'), ',') }}@if(! $loop->last) · @endif
                                            @endforeach
                                        </div>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($ehGrade && $variacoesGrade->pluck('status')->unique()->count() > 1)
                                    <span class="badge bg-warning text-dark" title="Há tamanhos ou cores ativos e inativos">{{ $variacoesGrade->where('status', 'ativo')->count() }} de {{ $variacoesGrade->count() }} ativos</span>
                                @else
                                <span class="badge bg-{{ $produto->status === 'ativo' ? 'success' : 'secondary' }}">
                                    {{ ucfirst($produto->status) }}
                                </span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('app.produtos.show', $produto) }}" class="btn btn-outline-info" title="Visualizar">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('app.produtos.edit', $produto) }}" class="btn btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
@unless($ehGrade)
                                                                        <form method="POST" action="{{ route('app.produtos.destroy', $produto) }}" class="d-inline" data-confirm="Tem certeza que deseja excluir este produto?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Excluir">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                        @if($ehGrade)
                            @foreach($variacoesGrade as $v)
                                @php $qtdV = $saldosLoja[$v->id] ?? 0; @endphp
                                <tr class="table-light d-none" data-grade-de="{{ $produto->id }}" @if($loop->first) id="gradeLinhas{{ $produto->id }}" @endif>
                                    <td class="ps-4"><code>{{ $v->codigo_interno }}</code></td>
                                    <td>
                                        <span class="fw-semibold">{{ \App\Services\LojaOnline\GradeLoja::rotulo($v, $nomesGrade) ?: $v->descricao }}</span>
                                        @if($v->codigo_barras)<small class="text-muted ms-2"><i class="bi bi-upc me-1"></i>{{ $v->codigo_barras }}</small>@endif
                                    </td>
                                    <td></td>
                                    <td class="text-center"><span class="badge bg-light text-dark">{{ $v->unidade_medida }}</span></td>
                                    <td class="text-end text-nowrap">
                                        @php $precoV = $tabelaPrecosLista->precosParaTela($v, $configLojaLista)['principal']; @endphp
                                        @if($podeEditarPreco)
                                            <span class="preco-inline" data-url="{{ route('app.produtos.preco', $v) }}" data-valor="{{ $precoV }}" role="button" title="Clique para alterar o preço">
                                                <span class="preco-texto">R$ {{ number_format($precoV, 2, ',', '.') }}</span>
                                                <i class="bi bi-pencil small text-muted ms-1"></i>
                                            </span>
                                        @else
                                            R$ {{ number_format($precoV, 2, ',', '.') }}
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if($lojaAtual)
                                            <span class="fw-bold {{ $qtdV <= 0 ? 'text-danger' : '' }}">{{ rtrim(rtrim(number_format($qtdV, 3, ',', '.'), '0'), ',') }}</span>
                                            @if($estoquesLoja->count() > 1)
                                                <div class="small text-muted text-nowrap">
                                                    @foreach($estoquesLoja as $est)
                                                        {{ $est->nome }} {{ rtrim(rtrim(number_format($saldosPorEstoque[$v->id][$est->id] ?? 0, 3, ',', '.'), '0'), ',') }}@if(! $loop->last) · @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center"><span class="badge bg-{{ $v->status === 'ativo' ? 'success' : 'secondary' }}">{{ ucfirst($v->status) }}</span></td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('app.produtos.show', $v) }}" class="btn btn-outline-info" title="Visualizar"><i class="bi bi-eye"></i></a>
                                            <a href="{{ route('app.produtos.edit', $v) }}" class="btn btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                Nenhum produto encontrado
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($produtos->hasPages())
        <div class="card-footer bg-white">
            {{ $produtos->links() }}
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
// Grade na lista: o botão abre/fecha os tamanhos e cores do produto
document.querySelectorAll('[data-grade-abrir]').forEach(function (botao) {
    botao.addEventListener('click', function () {
        var aberto = botao.getAttribute('aria-expanded') === 'true';
        botao.setAttribute('aria-expanded', aberto ? 'false' : 'true');
        botao.querySelector('i').className = aberto ? 'bi bi-chevron-right' : 'bi bi-chevron-down';
        document.querySelectorAll('[data-grade-de="' + botao.dataset.gradeAbrir + '"]').forEach(function (tr) { tr.classList.toggle('d-none', aberto); });
    });
});
</script>
@endpush

@if($podeEditarPreco)
@push('scripts')
<script>
// Preço editável na lista: clique abre o campo, Enter/sair salva, Esc cancela
document.querySelectorAll('.preco-inline').forEach(el => {
    el.addEventListener('click', () => {
        if (el.querySelector('input')) return;
        const original = el.innerHTML;
        const input = document.createElement('input');
        input.type = 'text';
        input.inputMode = 'numeric';
        input.className = 'form-control form-control-sm text-end fw-bold';
        input.style.width = '110px';
        input.style.display = 'inline-block';
        input.value = ERP.masks.money(Number(el.dataset.valor).toFixed(2));
        input.addEventListener('input', () => { input.value = ERP.masks.money(input.value); });
        el.innerHTML = '';
        el.appendChild(input);
        input.focus();
        input.select();

        let fechado = false;
        const restaurar = () => { fechado = true; el.innerHTML = original; };

        const salvar = async () => {
            if (fechado) return;
            fechado = true;
            const valor = ERP.moneyToDecimal(input.value);
            if (input.value === '' || valor.toFixed(2) === Number(el.dataset.valor).toFixed(2)) {
                el.innerHTML = original;
                return;
            }
            input.disabled = true;
            try {
                const res = await fetch(el.dataset.url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ preco_venda: valor.toFixed(2) }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || 'Não foi possível salvar o preço.');
                el.dataset.valor = data.preco_venda;
                el.innerHTML = original;
                el.querySelector('.preco-texto').textContent = data.preco_formatado;
                ERP.toast('Preço atualizado', 'success');
            } catch (e) {
                el.innerHTML = original;
                ERP.toast(e.message, 'danger');
            }
        };

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); salvar(); }
            if (e.key === 'Escape') { restaurar(); }
        });
        input.addEventListener('blur', salvar);
    });
});
</script>
@endpush
@endif
