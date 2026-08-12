@extends('layouts.app')

@section('title', $estoque->exists ? 'Editar Estoque' : 'Novo Estoque')

@section('content')
<x-erp.page-header :title="$estoque->exists ? 'Editar Estoque' : 'Novo Estoque'"
                   subtitle="Um lugar de guarda dentro desta loja"
                   icon="boxes">
    <a href="{{ route('app.estoques.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

<div class="row">
    <div class="col-lg-8">
        <form method="POST"
              action="{{ $estoque->exists ? route('app.estoques.update', $estoque) : route('app.estoques.store') }}">
            @csrf
            @if($estoque->exists) @method('PUT') @endif

            <x-erp.form-section title="Identificação" icon="tag">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="nome" class="form-label fw-semibold">
                            Nome <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="nome" id="nome" maxlength="80" required
                               class="form-control @error('nome') is-invalid @enderror"
                               value="{{ old('nome', $estoque->nome) }}"
                               placeholder="Depósito, Vitrine, Avaria...">
                        @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="codigo" class="form-label fw-semibold">Código</label>
                        <input type="text" name="codigo" id="codigo" maxlength="20"
                               class="form-control @error('codigo') is-invalid @enderror"
                               value="{{ old('codigo', $estoque->codigo) }}" placeholder="Opcional">
                        @error('codigo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </x-erp.form-section>

            <x-erp.form-section title="Comportamento" icon="sliders">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" value="1"
                           name="permite_venda" id="permite_venda"
                           {{ old('permite_venda', $estoque->permite_venda) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="permite_venda">
                        É o estoque de venda desta loja
                    </label>
                    <div class="form-text">
                        O PDV e as vendas baixam daqui. <strong>Só um por loja</strong> — marcar este
                        desmarca o atual.
                    </div>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" value="1"
                           name="is_padrao" id="is_padrao"
                           {{ old('is_padrao', $estoque->is_padrao) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="is_padrao">
                        Vem pré-selecionado nos formulários
                    </label>
                    <div class="form-text">Também é único por loja.</div>
                </div>

                <div class="mb-3">
                    <label for="status" class="form-label fw-semibold">Situação</label>
                    <select name="status" id="status" class="form-select">
                        <option value="ativo" {{ old('status', $estoque->status ?? 'ativo') === 'ativo' ? 'selected' : '' }}>Ativo</option>
                        <option value="inativo" {{ old('status', $estoque->status) === 'inativo' ? 'selected' : '' }}>Inativo</option>
                    </select>
                </div>

                <div class="mb-0">
                    <label for="observacoes" class="form-label fw-semibold">Observações</label>
                    <textarea name="observacoes" id="observacoes" rows="2" class="form-control"
                              placeholder="Para que serve este estoque">{{ old('observacoes', $estoque->observacoes) }}</textarea>
                </div>
            </x-erp.form-section>

            <div class="d-flex gap-2 mb-4">
                <button type="submit" class="btn btn-erp-primary btn-lg px-4">
                    <i class="bi bi-check-lg me-1"></i> Salvar
                </button>
                <a href="{{ route('app.estoques.index') }}" class="btn btn-erp-outline btn-lg">Cancelar</a>
            </div>
        </form>
    </div>

    <div class="col-lg-4">
        <x-erp.card title="Como funciona" icon="question-circle">
            <p class="small text-muted mb-2">
                Cada estoque tem <strong>saldo próprio</strong>. O saldo da loja é a soma de todos.
            </p>
            <p class="small text-muted mb-2">
                Para mover peça de um para outro, use <strong>Transferência</strong> — ela agora
                aceita origem e destino na mesma loja.
            </p>
            <p class="small text-muted mb-0">
                Inativar não apaga nada: o histórico continua no extrato de movimentações.
            </p>
        </x-erp.card>
    </div>
</div>
@endsection
