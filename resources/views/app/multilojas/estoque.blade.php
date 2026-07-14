@extends('layouts.app')

@section('title', 'Estoque por Loja')

@section('content')
@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
@endphp
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4 class="mb-0">
        <i class="bi bi-boxes me-2"></i>Estoque por Loja
        <small class="text-muted fs-6 d-block">Saldo de cada produto em todas as unidades — edite direto na tabela</small>
    </h4>
    <a href="{{ route('app.multilojas.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i> {{ $errors->first() }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('app.multilojas.estoque') }}" class="row g-2 align-items-center">
            <div class="col-sm-8 col-md-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" value="{{ $busca }}" class="form-control"
                           placeholder="Buscar por descrição, código ou SKU...">
                </div>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit">Buscar</button>
                @if($busca !== '')
                    <a href="{{ route('app.multilojas.estoque') }}" class="btn btn-outline-secondary">Limpar</a>
                @endif
            </div>
        </form>
    </div>
</div>

<form method="POST" action="{{ route('app.multilojas.estoque.ajustar') }}">
    @csrf
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="text-muted small">
                <i class="bi bi-pencil-square me-1"></i>Edite as quantidades e salve tudo de uma vez
            </span>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-save me-1"></i> Salvar Todos
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:220px; position:sticky; left:0; background:var(--bs-table-bg,#f8f9fa); z-index:2;">Produto</th>
                            @foreach($unidades as $unidade)
                                <th class="text-center" style="min-width:120px;">
                                    {{ $unidade->nome }}
                                </th>
                            @endforeach
                            <th class="text-center" style="min-width:90px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($matriz as $linha)
                            @php $produto = $linha['produto']; @endphp
                            <tr>
                                <td style="position:sticky; left:0; background:#fff; z-index:1;">
                                    <div class="fw-semibold">{{ $produto->descricao }}</div>
                                    <small class="text-muted">{{ $produto->codigo_interno ?? $produto->sku ?? '—' }}</small>
                                </td>
                                @foreach($unidades as $unidade)
                                    @php $saldo = $linha['saldos'][$unidade->id] ?? 0; @endphp
                                    <td class="text-center">
                                        <input type="number" step="0.001" min="0"
                                               name="saldos[{{ $produto->id }}][{{ $unidade->id }}]"
                                               value="{{ rtrim(rtrim(number_format($saldo, 3, '.', ''), '0'), '.') }}"
                                               class="form-control form-control-sm text-center
                                                      {{ $saldo <= 0 ? 'border-danger text-danger' : '' }}"
                                               style="max-width:100px; margin:0 auto;">
                                    </td>
                                @endforeach
                                <td class="text-center fw-bold">
                                    {{ rtrim(rtrim(number_format($linha['total'], 3, '.', ''), '0'), '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $unidades->count() + 2 }}" class="text-center text-muted py-4">
                                    Nenhum produto encontrado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-muted small">
                <i class="bi bi-info-circle me-1"></i>
                Só as quantidades alteradas geram movimentação de <strong>Ajuste</strong> no histórico.
                @if(count($matriz) >= 300)
                    <span class="text-warning d-block">Exibindo os primeiros 300 produtos — use a busca para refinar.</span>
                @endif
            </span>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-save me-1"></i> Salvar Todos
            </button>
        </div>
    </div>
</form>
@endsection
