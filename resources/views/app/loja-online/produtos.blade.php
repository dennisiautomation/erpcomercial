@extends('layouts.app')

@section('title', 'Loja online — Produtos')

@section('content')
<x-erp.page-header title="Loja online" icon="globe2" subtitle="Produtos que aparecem na vitrine">
    <span class="badge bg-primary bg-opacity-10 text-primary fs-6 fw-semibold">{{ $noCatalogo }} de {{ $totalAtivos }} na loja</span>
</x-erp.page-header>

@include('app.loja-online._abas')

<x-erp.filter-bar :action="route('app.loja-online.produtos')">
    <div class="col-md-4">
        <label class="form-label small text-muted mb-1" for="q">Buscar</label>
        <input type="text" name="q" id="q" class="form-control" value="{{ request('q') }}" placeholder="Nome ou código">
    </div>
    <div class="col-md-3">
        <label class="form-label small text-muted mb-1" for="categoria">Categoria</label>
        <select name="categoria" id="categoria" class="form-select">
            <option value="">Todas</option>
            @foreach($categorias as $c)<option value="{{ $c->id }}" @selected((int) request('categoria') === $c->id)>{{ $c->nome }}</option>@endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label small text-muted mb-1" for="situacao">Mostrar</label>
        <select name="situacao" id="situacao" class="form-select">
            <option value="">Todos os produtos ativos</option>
            <option value="na_loja" @selected(request('situacao') === 'na_loja')>Só os que estão na loja</option>
            <option value="fora" @selected(request('situacao') === 'fora')>Só os que estão fora</option>
            <option value="destaque" @selected(request('situacao') === 'destaque')>Só os destaques</option>
            <option value="sem_foto" @selected(request('situacao') === 'sem_foto')>Na loja e sem foto</option>
        </select>
    </div>
</x-erp.filter-bar>

<form method="POST" action="{{ route('app.loja-online.produtos.marcar') }}" id="formMarcar">
    @csrf
    <input type="hidden" name="categoria_id" value="{{ (int) request('categoria') ?: '' }}">
    <div class="card shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-2">
            <span class="text-muted small me-1" id="contagemMarcados">Nenhum marcado</span>
            <select name="alcance" class="form-select form-select-sm w-auto" aria-label="Aplicar a">
                <option value="marcados">Aplicar aos marcados</option>
                @if(request('categoria'))<option value="categoria">Aplicar a toda a categoria filtrada</option>@endif
                <option value="todos">Aplicar a TODOS os produtos ativos</option>
            </select>
            <button type="submit" name="acao" value="entrar" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i> Pôr na loja</button>
            <button type="submit" name="acao" value="sair" class="btn btn-sm btn-outline-danger"><i class="bi bi-dash-circle me-1"></i> Tirar da loja</button>
            <button type="submit" name="acao" value="destacar" class="btn btn-sm btn-outline-primary"><i class="bi bi-star me-1"></i> Destacar</button>
            <button type="submit" name="acao" value="tirar_destaque" class="btn btn-sm btn-outline-secondary">Tirar destaque</button>
        </div>
    </div>

    <x-erp.data-table>
        <thead>
            <tr>
                <th style="width:36px"><input type="checkbox" class="form-check-input" id="marcarTodos" aria-label="Marcar todos desta página"></th>
                <th style="width:56px"></th>
                <th>Produto</th>
                <th>Código</th>
                <th class="text-end">Preço</th>
                <th class="text-end">Saldo na loja online</th>
                <th>Na loja</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($produtos as $produto)
                <tr>
                    <td><input type="checkbox" class="form-check-input marcar" name="ids[]" value="{{ $produto->id }}" aria-label="Marcar {{ $produto->descricao }}"></td>
                    <td>
                        @if($produto->foto)<img src="{{ Storage::url($produto->foto) }}" class="rounded" style="width:44px;height:44px;object-fit:cover" alt="">
                        @else<span class="d-inline-flex align-items-center justify-content-center rounded bg-light text-muted" style="width:44px;height:44px" title="Sem foto"><i class="bi bi-image"></i></span>@endif
                    </td>
                    <td>{{ $produto->descricao }}</td>
                    <td class="text-muted">{{ $produto->codigo_interno }}</td>
                    <td class="text-end">R$ {{ number_format((float) $produto->preco_venda, 2, ',', '.') }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($saldos[$produto->id] ?? 0, 3, ',', '.'), '0'), ',') }}</td>
                    <td>
                        @if($produto->loja_online)
                            <span class="badge bg-success">Na loja</span>
                            @if($produto->loja_online_destaque)<span class="badge bg-warning text-dark"><i class="bi bi-star-fill"></i> Destaque</span>@endif
                        @else
                            <span class="badge bg-secondary bg-opacity-25 text-secondary">Fora</span>
                        @endif
                    </td>
                    <td class="text-end"><a href="{{ route('app.produtos.edit', $produto) }}" class="btn btn-sm btn-outline-secondary" title="Editar o produto (fotos, descrição)"><i class="bi bi-pencil"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-erp.empty-state title="Nenhum produto com este filtro" icon="box-seam" description="Mude a busca ou o filtro acima." /></td></tr>
            @endforelse
        </tbody>
        <x-slot:pagination>{{ $produtos->links() }}</x-slot:pagination>
    </x-erp.data-table>
</form>
@endsection

@push('scripts')
<script>
(function () {
    var todos = document.getElementById('marcarTodos'), caixas = document.querySelectorAll('.marcar'), texto = document.getElementById('contagemMarcados');
    var form = document.getElementById('formMarcar');
    function contar() {
        var n = document.querySelectorAll('.marcar:checked').length;
        texto.textContent = n === 0 ? 'Nenhum marcado' : (n === 1 ? '1 marcado' : n + ' marcados');
    }
    todos.addEventListener('change', function () { caixas.forEach(function (c) { c.checked = todos.checked; }); contar(); });
    caixas.forEach(function (c) { c.addEventListener('change', contar); });
    form.addEventListener('submit', function (ev) {
        var alcance = form.alcance.value, acao = ev.submitter ? ev.submitter.value : '';
        if (alcance === 'todos' && !form.dataset.ok) {
            ev.preventDefault();
            var frase = {entrar: 'pôr na loja', sair: 'tirar da loja', destacar: 'destacar', tirar_destaque: 'tirar o destaque de'}[acao] || 'alterar';
            ERP.confirm({title: 'Aplicar a todos os produtos?', confirmText: 'Aplicar a todos',
                message: 'Isto vai ' + frase + ' TODOS os {{ $totalAtivos }} produtos ativos da empresa, não só os desta página.'}).then(function (sim) {
                if (!sim) return;
                form.dataset.ok = '1';
                var h = document.createElement('input'); h.type = 'hidden'; h.name = 'acao'; h.value = acao; form.appendChild(h);
                form.submit();
            });
        }
    });
})();
</script>
@endpush
