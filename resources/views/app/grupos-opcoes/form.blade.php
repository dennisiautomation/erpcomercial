@extends('layouts.app')

@section('title', $grupo->exists ? 'Editar grupo de opções' : 'Novo grupo de opções')

@section('content')
<x-erp.page-header :title="$grupo->exists ? 'Editar grupo: ' . $grupo->nome : 'Novo grupo de opções'" icon="ui-checks-grid">
    <a href="{{ route('app.grupos-opcoes.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

@php
    $linhas = old('itens', $grupo->exists
        ? $grupo->itens->map(fn ($i) => ['adicional_id' => $i->adicional_id, 'novo_nome' => '', 'preco' => $i->preco])->all()
        : []);
    if ($linhas === []) {
        $linhas = [['adicional_id' => '', 'novo_nome' => '', 'preco' => '']];
    }
    $obrigatorio = (bool) old('obrigatorio', $grupo->minimo > 0);
@endphp

<form method="POST" action="{{ $grupo->exists ? route('app.grupos-opcoes.update', $grupo) : route('app.grupos-opcoes.store') }}">
    @csrf
    @if($grupo->exists) @method('PUT') @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label for="nome" class="form-label fw-semibold">Nome do grupo <span class="text-danger">*</span></label>
                    <input type="text" name="nome" id="nome" maxlength="100" required class="form-control @error('nome') is-invalid @enderror"
                           value="{{ old('nome', $grupo->nome) }}" placeholder="Ex: Tamanho, Leite, Extras">
                    @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="maximo" class="form-label fw-semibold">Quantas o cliente pode escolher</label>
                    <select name="maximo" id="maximo" class="form-select">
                        @foreach([1 => 'Só 1 (ex.: tamanho)', 2 => 'Até 2', 3 => 'Até 3', 5 => 'Até 5', 10 => 'Até 10'] as $v => $rot)
                            <option value="{{ $v }}" @selected((int) old('maximo', $grupo->maximo ?: 1) === $v)>{{ $rot }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label fw-semibold">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="ativo" @selected(old('status', $grupo->status) === 'ativo')>Ativo</option>
                        <option value="inativo" @selected(old('status', $grupo->status) === 'inativo')>Inativo</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <input type="hidden" name="obrigatorio" value="0">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="obrigatorio" id="obrigatorio" value="1" @checked($obrigatorio)>
                        <label class="form-check-label" for="obrigatorio">Obrigatório</label>
                    </div>
                </div>
            </div>
            <div class="form-text">Obrigatório: o PDV só lança o produto depois que o caixa escolher (ex.: tamanho P/M/G).</div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-list-check me-1"></i> Opções</strong>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddOpcao"><i class="bi bi-plus-lg me-1"></i> Adicionar opção</button>
        </div>
        <div class="card-body">
            @error('itens') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
            @foreach($errors->get('itens.*') as $msgs)
                <div class="alert alert-danger py-2">{{ $msgs[0] }}</div>
            @endforeach
            <p class="small text-muted mb-2">
                Escolha um adicional já cadastrado <strong>ou</strong> digite o nome de um novo (ele é criado como produto Adicional com o preço digitado).
                Preço em branco = o preço de venda do adicional. Use 0 para opção sem custo (ex.: tamanho P).
            </p>
            <div id="opcoesLista">
                @foreach($linhas as $i => $l)
                    <div class="row g-2 align-items-center mb-2 opcao-linha">
                        <div class="col-md-5">
                            <select name="itens[{{ $i }}][adicional_id]" class="form-select form-select-sm" aria-label="Adicional cadastrado">
                                <option value="">— novo adicional —</option>
                                @foreach($adicionais as $a)
                                    <option value="{{ $a->id }}" @selected((string) ($l['adicional_id'] ?? '') === (string) $a->id)>{{ $a->descricao }} (R$ {{ number_format($a->preco_venda, 2, ',', '.') }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="itens[{{ $i }}][novo_nome]" maxlength="255" class="form-control form-control-sm" placeholder="ou nome do novo (ex.: Leite de aveia)" value="{{ $l['novo_nome'] ?? '' }}" aria-label="Nome do novo adicional">
                        </div>
                        <div class="col-md-2">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">+R$</span>
                                <input type="number" step="0.01" min="0" name="itens[{{ $i }}][preco]" class="form-control" value="{{ $l['preco'] ?? '' }}" placeholder="do adicional" aria-label="Preço">
                            </div>
                        </div>
                        <div class="col-md-1 text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remover" title="Remover opção"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @if($grupo->exists && $grupo->produtos->isNotEmpty())
        <div class="alert alert-light border small">
            <i class="bi bi-box-seam me-1"></i> Usado em: {{ $grupo->produtos->pluck('descricao')->join(', ') }}
        </div>
    @endif

    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('app.grupos-opcoes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Salvar grupo</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const lista = document.getElementById('opcoesLista');
    let seq = lista.querySelectorAll('.opcao-linha').length;
    document.getElementById('btnAddOpcao').addEventListener('click', () => {
        const modelo = lista.querySelector('.opcao-linha');
        const nova = modelo.cloneNode(true);
        nova.querySelectorAll('select, input').forEach(el => {
            el.name = el.name.replace(/itens\[\d+\]/, `itens[${seq}]`);
            if (el.tagName === 'SELECT') el.selectedIndex = 0; else el.value = '';
        });
        seq++;
        lista.appendChild(nova);
        nova.querySelector('input[type="text"]').focus();
    });
    lista.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-remover');
        if (!btn) return;
        const linhas = lista.querySelectorAll('.opcao-linha');
        const linha = btn.closest('.opcao-linha');
        if (linhas.length === 1) {
            linha.querySelectorAll('select, input').forEach(el => { if (el.tagName === 'SELECT') el.selectedIndex = 0; else el.value = ''; });
        } else {
            linha.remove();
        }
    });
})();
</script>
@endpush
