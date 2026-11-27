@extends('layouts.app')

@section('title', 'Loja online — Categorias')

@section('content')
<x-erp.page-header title="Loja online" icon="globe2" subtitle="Categorias: o que a loja mostra e o que vai em cada uma" />

@include('app.loja-online._abas')

@php
    $marcadasCat = collect(old('categorias_erp', $editar?->idsCategorias() ?? []))->map(fn ($i) => (int) $i)->all();
    $marcadosProd = collect(old('produtos', $editar?->idsProdutos() ?? []))->map(fn ($i) => (int) $i)->all();
    $resumo = function ($c) use ($nomesCadastro) {
        $partes = [];
        if ($n = count($c->idsCategorias())) {
            $nomes = collect($c->idsCategorias())->map(fn ($i) => $nomesCadastro[$i] ?? null)->filter()->take(3)->implode(', ');
            $partes[] = ($n === 1 ? '1 categoria do cadastro' : $n . ' categorias do cadastro') . ($nomes ? ' (' . $nomes . ($n > 3 ? '…' : '') . ')' : '');
        }
        if ($n = count($c->idsProdutos())) {
            $partes[] = $n === 1 ? '1 produto avulso' : $n . ' produtos avulsos';
        }

        return implode(' + ', $partes) ?: 'vazia';
    };
@endphp

<div class="alert alert-info">
    <strong>Como funciona:</strong> aqui você monta as categorias que aparecem na loja online — o nome, a ordem, a foto e o que vai dentro de cada uma.
    Dentro pode ir uma <strong>categoria inteira do cadastro</strong> (várias, se quiser juntar) e também <strong>produtos escolhidos um a um</strong>.
    O cadastro de categorias do ERP não muda.
    @if($emUso)
        <br>A loja está mostrando <strong>só as categorias desta tela</strong>, nesta ordem.
    @else
        <br>Enquanto não houver nenhuma categoria ligada aqui, a loja mostra as categorias do cadastro, em ordem alfabética.
    @endif
</div>

@if($emUso && $semCategoria > 0)
    <div class="alert alert-warning">
        <strong>{{ $semCategoria }} {{ $semCategoria === 1 ? 'produto da loja não está' : 'produtos da loja não estão' }} em nenhuma categoria desta tela.</strong>
        {{ $semCategoria === 1 ? 'Ele continua' : 'Eles continuam' }} aparecendo em "Todos os produtos" e na busca, mas não em uma categoria.
    </div>
@endif

<x-erp.card title="Categorias da loja" icon="collection" class="mb-3">
    @if($lista->isEmpty())
        <x-erp.empty-state title="Nenhuma categoria montada" icon="collection" description="A loja está usando as categorias do cadastro. Monte a primeira no quadro abaixo." />
    @else
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th style="width:6.5rem">Ordem</th><th style="width:4rem"></th><th>Nome na loja</th><th>O que vai dentro</th><th class="text-end">Produtos na loja</th><th>Situação</th><th></th></tr></thead>
                <tbody>
                @foreach($lista as $c)
                    <tr @class(['table-active' => $editar && $editar->id === $c->id])>
                        <td>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Mudar a ordem de {{ $c->nome }}">
                                @foreach(['subir' => ['arrow-up', 'Subir'], 'descer' => ['arrow-down', 'Descer']] as $dir => [$ic, $rot])
                                    <form method="POST" action="{{ route('app.loja-online.categorias.mover', $c->id) }}" class="d-inline">@csrf
                                        <input type="hidden" name="direcao" value="{{ $dir }}">
                                        <button class="btn btn-outline-secondary" @disabled(($dir === 'subir' && $loop->parent->first) || ($dir === 'descer' && $loop->parent->last)) aria-label="{{ $rot }} {{ $c->nome }}" title="{{ $rot }}"><i class="bi bi-{{ $ic }}" aria-hidden="true"></i></button>
                                    </form>
                                @endforeach
                            </div>
                        </td>
                        <td>@if($c->foto)<img src="{{ \App\Services\LojaOnline\LojaContexto::arquivo($c->foto) }}" alt="" width="44" height="44" class="rounded border" style="object-fit:cover">@else<span class="text-muted small" title="A foto vem de um produto da categoria">auto</span>@endif</td>
                        <td class="fw-semibold">{{ $c->nome }}</td>
                        <td class="small text-muted">{{ $resumo($c) }}</td>
                        <td class="text-end">{{ $totais[$c->id] ?? 0 }}@if(($totais[$c->id] ?? 0) === 0) <i class="bi bi-exclamation-triangle text-warning" title="Sem produto na loja: não aparece para o público"></i>@endif</td>
                        <td>
                            <form method="POST" action="{{ route('app.loja-online.categorias.alternar', $c->id) }}">@csrf @method('PATCH')
                                <button class="btn btn-sm {{ $c->ativa ? 'btn-outline-success' : 'btn-outline-secondary' }}" title="{{ $c->ativa ? 'Desligar: some da loja' : 'Ligar: volta a aparecer' }}">{{ $c->ativa ? 'Ligada' : 'Desligada' }}</button>
                            </form>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('app.loja-online.categorias', ['editar' => $c->id]) }}#formCategoria">Editar</a>
                            <form method="POST" action="{{ route('app.loja-online.categorias.excluir', $c->id) }}" class="d-inline">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" data-confirm="Excluir a categoria “{{ $c->nome }}” da loja? Os produtos continuam na loja e no cadastro." aria-label="Excluir {{ $c->nome }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-erp.card>

<div id="formCategoria"></div>
<x-erp.card :title="$editar ? 'Editar: ' . $editar->nome : 'Nova categoria da loja'" :icon="$editar ? 'pencil' : 'plus-circle'">
    <form method="POST" enctype="multipart/form-data" action="{{ $editar ? route('app.loja-online.categorias.salvar', $editar->id) : route('app.loja-online.categorias.criar') }}">
        @csrf
        @if($editar) @method('PUT') @endif

        <div class="row g-3 mb-3">
            <div class="col-md-5">
                <label for="nome" class="form-label">Nome que aparece na loja</label>
                <input type="text" class="form-control @error('nome') is-invalid @enderror" id="nome" name="nome" maxlength="60" value="{{ old('nome', $editar->nome ?? '') }}" placeholder="Ex.: Perfumes árabes" required>
                @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-5">
                <label for="foto" class="form-label">Foto da categoria <span class="text-muted fw-normal">(opcional)</span></label>
                <input type="file" class="form-control @error('foto') is-invalid @enderror" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
                @error('foto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">Sem foto, a loja usa a de um produto da categoria. Quadrada, 800 × 800 px ou maior.</div>
            </div>
            @if($editar?->foto)
                <div class="col-md-2 d-flex align-items-center gap-2">
                    <img src="{{ \App\Services\LojaOnline\LojaContexto::arquivo($editar->foto) }}" alt="Foto atual" width="56" height="56" class="rounded border" style="object-fit:cover">
                    <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="tirar_foto" value="1" id="tirar_foto"><label class="form-check-label small" for="tirar_foto">Tirar a foto</label></div>
                </div>
            @endif
        </div>

        @error('conteudo') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

        <div class="row g-3">
            <div class="col-lg-5">
                <fieldset class="border rounded p-3 h-100" data-escolha>
                    <legend class="fs-6 fw-semibold float-none w-auto px-1 mb-1">Categorias do cadastro que entram inteiras</legend>
                    <p class="small text-muted mb-2">Todo produto da categoria marcada entra, inclusive os que você cadastrar depois. Marque várias para juntar. As que já têm produto na loja vêm primeiro.</p>
                    <label class="visually-hidden" for="filtroCat">Procurar categoria do cadastro</label>
                    <input type="search" class="form-control form-control-sm mb-2" id="filtroCat" placeholder="Procurar categoria…" data-escolha-filtro autocomplete="off">
                    <div class="lj-escolha" data-escolha-lista>
                        @forelse($cadastro as $c)
                            <label class="lj-escolha__item" data-texto="{{ mb_strtolower($c['nome']) }}">
                                <input class="form-check-input" type="checkbox" name="categorias_erp[]" value="{{ $c['id'] }}" @checked(in_array($c['id'], $marcadasCat, true))>
                                <span>{{ $c['nome'] }}</span>
                                <small class="text-muted">{{ $c['total'] }} na loja</small>
                            </label>
                        @empty
                            <p class="text-muted small mb-0">O cadastro ainda não tem categorias.</p>
                        @endforelse
                    </div>
                    <div class="small text-muted mt-2" data-escolha-conta aria-live="polite"></div>
                </fieldset>
            </div>
            <div class="col-lg-7">
                <fieldset class="border rounded p-3 h-100" data-escolha>
                    <legend class="fs-6 fw-semibold float-none w-auto px-1 mb-1">Produtos escolhidos um a um</legend>
                    <p class="small text-muted mb-2">Para pôr um produto nesta categoria sem levar a categoria inteira dele. Só aparecem os produtos marcados para a loja online.</p>
                    @if($produtos->isEmpty())
                        <p class="text-muted small mb-0">Nenhum produto está marcado para a loja online. Marque em <a href="{{ route('app.loja-online.produtos') }}">Produtos</a>.</p>
                    @else
                        <label class="visually-hidden" for="filtroProd">Procurar produto</label>
                        <input type="search" class="form-control form-control-sm mb-2" id="filtroProd" placeholder="Procurar pelo nome ou código…" data-escolha-filtro autocomplete="off">
                        <div class="lj-escolha" data-escolha-lista>
                            @foreach($produtos as $p)
                                <label class="lj-escolha__item" data-texto="{{ mb_strtolower($p->descricao . ' ' . $p->codigo_interno) }}">
                                    <input class="form-check-input" type="checkbox" name="produtos[]" value="{{ $p->id }}" @checked(in_array((int) $p->id, $marcadosProd, true))>
                                    <span>{{ $p->descricao }}</span>
                                    <small class="text-muted">{{ $nomesCadastro[$p->categoria_id] ?? 'sem categoria' }}</small>
                                </label>
                            @endforeach
                        </div>
                        <div class="small text-muted mt-2" data-escolha-conta aria-live="polite"></div>
                    @endif
                </fieldset>
            </div>
        </div>

        <div class="erp-acoes mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> {{ $editar ? 'Salvar a categoria' : 'Criar a categoria' }}</button>
            @if($editar)<a class="btn btn-outline-secondary" href="{{ route('app.loja-online.categorias') }}">Cancelar</a>@endif
        </div>
    </form>
</x-erp.card>

@push('styles')
<style>
.lj-escolha{max-height:19rem;overflow-y:auto;border:1px solid var(--border,#e2e8f0);border-radius:.5rem}
.lj-escolha__item{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:.6rem;align-items:center;padding:.5rem .75rem;margin:0;cursor:pointer;border-bottom:1px solid var(--border-light,#f1f5f9)}
.lj-escolha__item:last-child{border-bottom:0}
.lj-escolha__item:hover{background:var(--bg-table-head,#f8fafc)}
.lj-escolha__item:has(input:checked){background:var(--primary-light,#eef2ff)}
.lj-escolha__item span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.lj-escolha__item small{white-space:nowrap;max-width:14rem;overflow:hidden;text-overflow:ellipsis}
.lj-escolha__item[hidden]{display:none}
</style>
@endpush

@push('scripts')
<script>
document.querySelectorAll('[data-escolha]').forEach(function (quadro) {
    var filtro = quadro.querySelector('[data-escolha-filtro]'), conta = quadro.querySelector('[data-escolha-conta]');
    var itens = [].slice.call(quadro.querySelectorAll('.lj-escolha__item'));
    var contar = function () {
        if (!conta) return;
        var n = itens.filter(function (i) { return i.querySelector('input').checked; }).length;
        conta.textContent = n === 0 ? 'Nada marcado.' : (n === 1 ? '1 marcado.' : n + ' marcados.');
    };
    if (filtro) filtro.addEventListener('input', function () {
        var termo = filtro.value.trim().toLowerCase();
        itens.forEach(function (i) { i.hidden = termo !== '' && i.dataset.texto.indexOf(termo) === -1; });
    });
    quadro.addEventListener('change', contar);
    contar();
});
</script>
@endpush
@endsection
