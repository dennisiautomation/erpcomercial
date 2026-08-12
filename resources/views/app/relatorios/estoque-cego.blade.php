@extends('layouts.app')

@section('title', 'Contagem Cega de Estoque')

@section('content')
<div class="d-print-none">
    <x-erp.page-header title="Contagem Cega de Estoque"
                       subtitle="Folha para contar o físico sem ver o saldo do sistema"
                       icon="clipboard-check">
        <a href="{{ route('app.relatorios.estoque') }}" class="btn btn-erp-outline">
            <i class="bi bi-arrow-left me-1"></i> Relatório de Estoque
        </a>
        <a href="{{ route('app.relatorios.estoque-cego', array_merge(request()->query(), ['formato' => 'xlsx'])) }}"
           class="btn btn-erp-outline">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Baixar .xlsx
        </a>
        <button type="button" class="btn btn-erp-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Imprimir
        </button>
    </x-erp.page-header>

    <div class="alert alert-info d-flex align-items-start">
        <i class="bi bi-eye-slash me-2 mt-1"></i>
        <div>
            A folha sai <strong>sem a quantidade do sistema</strong> — de propósito. Quem conta
            anota o que achou na prateleira; a comparação vem depois, no seu conferido.
            <br>
            <strong>Uma coluna de quantidade por estoque da loja</strong> — esta tem
            {{ $colunas->count() }} ({{ $colunas->pluck('nome')->join(', ') }}). Se você criar
            outro estoque em <a href="{{ route('app.estoques.index') }}">Estoques da Loja</a>,
            ele entra aqui como coluna nova automaticamente.
        </div>
    </div>

    <x-erp.filter-bar :action="route('app.relatorios.estoque-cego')">
        <div class="col-md-3">
            <label class="form-label fw-semibold small text-muted">Loja</label>
            <select name="unidade_id" class="form-select">
                @foreach($lojas as $l)
                    <option value="{{ $l->id }}" {{ $lojaId === $l->id ? 'selected' : '' }}>{{ $l->nome }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold small text-muted">Categoria</label>
            <select name="categoria_id" class="form-select">
                <option value="">Todas</option>
                @foreach($categorias as $c)
                    <option value="{{ $c->id }}" {{ (int) request('categoria_id') === $c->id ? 'selected' : '' }}>{{ $c->nome }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold small text-muted">Buscar</label>
            <input type="text" name="busca" class="form-control" value="{{ request('busca') }}"
                   placeholder="SKU, código ou descrição">
        </div>
        @if($estoques->count() > 1)
        <div class="col-md-3">
            <label class="form-label fw-semibold small text-muted">Estoques na folha</label>
            <div class="border rounded p-2" style="max-height:110px;overflow:auto">
                @foreach($estoques as $e)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="estoques[]" value="{{ $e->id }}"
                           id="est-{{ $e->id }}" {{ $selecionados->isEmpty() || $selecionados->contains($e->id) ? 'checked' : '' }}>
                    <label class="form-check-label small" for="est-{{ $e->id }}">{{ $e->nome }}</label>
                </div>
                @endforeach
            </div>
        </div>
        @endif
        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="somente_com_saldo" value="1"
                       id="somente_com_saldo" {{ request()->boolean('somente_com_saldo') ? 'checked' : '' }}>
                <label class="form-check-label small" for="somente_com_saldo">
                    Só produtos com saldo (o saldo decide a linha entrar, mas não aparece na folha)
                </label>
            </div>
        </div>
        <div class="col-auto">
            <a href="{{ route('app.relatorios.estoque-cego') }}" class="btn btn-erp-outline">
                <i class="bi bi-x-lg me-1"></i> Limpar
            </a>
        </div>
    </x-erp.filter-bar>
</div>

{{-- Cabeçalho que só existe no papel --}}
<div class="d-none d-print-block mb-3">
    <h5 class="mb-1">Contagem de Estoque — {{ $loja->nome ?? '' }}</h5>
    <div class="small">
        Data ____/____/______ &nbsp;&nbsp; Conferente ______________________________
        &nbsp;&nbsp; Página <span class="pagina"></span>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0 tabela-contagem">
            <thead class="table-light">
                <tr>
                    <th style="width:110px">SKU</th>
                    <th style="width:90px">Código</th>
                    <th style="width:130px">Cód. barras</th>
                    <th>Produto</th>
                    <th style="width:120px">Categoria</th>
                    <th style="width:50px">Un</th>
                    @foreach($colunas as $coluna)
                        {{-- Cabeçalho diz o que escrever E de qual estoque --}}
                        <th style="width:120px" class="text-center align-middle coluna-contagem">
                            <div class="fw-bold">{{ $coluna->nome }}</div>
                            <div class="fw-normal text-muted" style="font-size:.72rem">Qtd. contada</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($produtos as $produto)
                <tr>
                    <td class="font-monospace small">{{ $produto->sku ?: '—' }}</td>
                    <td class="font-monospace small">{{ $produto->codigo_interno }}</td>
                    <td class="font-monospace small">{{ $produto->codigo_barras ?: '—' }}</td>
                    <td>{{ $produto->descricao }}</td>
                    <td class="small text-muted">{{ $produto->categoria->nome ?? '—' }}</td>
                    <td class="small text-center">{{ $produto->unidade_medida ?: 'UN' }}</td>
                    @foreach($colunas as $coluna)
                        {{-- Vazia de propósito: é onde o conferente escreve.
                             A linha pontilhada deixa claro que é campo de preencher. --}}
                        <td class="celula-contagem"><span class="linha-escrita"></span></td>
                    @endforeach
                </tr>
                @empty
                <tr>
                    <td colspan="{{ 6 + $colunas->count() }}">
                        <x-erp.empty-state icon="clipboard-check" title="Nenhum produto para contar"
                                           description="Ajuste os filtros acima." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="text-muted small mt-2 d-print-none">
    {{ $produtos->count() }} {{ $produtos->count() === 1 ? 'produto' : 'produtos' }} ·
    {{ $colunas->count() }} {{ $colunas->count() === 1 ? 'estoque' : 'estoques' }} na folha
</div>
@endsection

@push('styles')
<style>
    /* Altura para caber número escrito à mão */
    .celula-contagem { height: 30px; vertical-align: bottom; }

    /* Linha pontilhada dentro da célula: sinaliza "escreva aqui" */
    .linha-escrita {
        display: block;
        border-bottom: 1px dotted #adb5bd;
        margin: 0 4px 4px;
        height: 1px;
    }

    /* Coluna de contagem se destaca do cadastro do produto */
    .coluna-contagem { background: #f1f3f5; }

    @media print {
        /* Some com a moldura do sistema — só a folha vai para o papel */
        .sidebar, .topbar, .navbar, .app-sidebar, footer, .d-print-none { display: none !important; }
        .main-content, .content, main { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .card { border: none !important; box-shadow: none !important; }

        .tabela-contagem { font-size: 10pt; }
        .tabela-contagem th, .tabela-contagem td { padding: 3px 5px !important; }
        .celula-contagem { height: 28px; background: #fff !important; }
        /* Fundo cinza do cabeçalho tem que sair na impressora */
        .coluna-contagem { background: #eceef0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .linha-escrita { border-bottom: 1px dotted #6c757d !important; }

        /* Cabeçalho da tabela repete em toda página */
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        @page { margin: 12mm; }
    }
</style>
@endpush
