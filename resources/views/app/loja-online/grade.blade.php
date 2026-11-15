@extends('layouts.app')

@section('title', 'Loja online — Grade')

@section('content')
<x-erp.page-header title="Loja online" icon="globe2" subtitle="Grade: o mesmo produto em vários tamanhos e cores" />

@include('app.loja-online._abas')

<div class="alert alert-info">
    <strong>Como funciona:</strong> cada tamanho/cor é um <strong>produto comum do ERP</strong>, com código, código de barras, preço e estoque próprios — o PDV, a nota e o estoque não mudam.
    A grade só diz à loja online que eles são o mesmo produto: a vitrine mostra <strong>um cartão</strong> e o cliente escolhe o tamanho e a cor na página.
</div>

<x-erp.card title="Criar uma grade" icon="plus-circle" class="mb-3">
    <form method="GET" action="{{ route('app.loja-online.grade') }}" class="row g-2 mb-2">
        <div class="col-md-6"><label class="visually-hidden" for="q">Buscar produto</label><input class="form-control" id="q" name="q" value="{{ $busca }}" placeholder="Busque o produto pelo nome ou código"></div>
        <div class="col-auto"><button class="btn btn-outline-primary">Buscar</button></div>
    </form>
    @if($busca !== '' && $achados->isEmpty())<p class="text-muted mb-0">Nenhum produto fora de grade com "{{ $busca }}".</p>@endif
    @foreach($achados as $a)
        <form method="POST" action="{{ route('app.loja-online.grade.gerar') }}" class="border rounded p-3 mb-2">
            @csrf
            <input type="hidden" name="produto_id" value="{{ $a->id }}">
            <div class="mb-2"><strong>{{ $a->descricao }}</strong> <span class="text-muted">· cód. {{ $a->codigo_interno }} · R$ {{ number_format((float) $a->preco_venda, 2, ',', '.') }}</span>@unless($a->loja_online) <span class="badge text-bg-secondary">fora da loja</span>@endunless</div>
            <div class="row g-2">
                @foreach([['Tamanho', 'P, M, G, GG'], ['Cor', 'Preto, Branco'], ['', '']] as $n => [$nome, $exemplo])
                    <div class="col-md-2"><label class="form-label small mb-1" for="at-{{ $a->id }}-{{ $n }}">{{ $n === 2 ? 'Outra opção' : 'Opção ' . ($n + 1) }}</label><input class="form-control form-control-sm" id="at-{{ $a->id }}-{{ $n }}" name="atributo[{{ $n }}]" maxlength="20" value="{{ $n === 0 ? 'Tamanho' : '' }}" placeholder="{{ $nome ?: 'ex.: Voltagem' }}"></div>
                    <div class="col-md-{{ $n === 2 ? 3 : 2 }}"><label class="form-label small mb-1" for="vl-{{ $a->id }}-{{ $n }}">Valores (separe com vírgula)</label><input class="form-control form-control-sm" id="vl-{{ $a->id }}-{{ $n }}" name="valores[{{ $n }}]" maxlength="400" placeholder="{{ $exemplo ?: '110V, 220V' }}"></div>
                @endforeach
                <div class="col-md-1 d-flex align-items-end"><button class="btn btn-sm btn-primary w-100" data-confirm="Criar a grade? Este produto vira a primeira combinação e o sistema cadastra um produto novo para cada uma das outras (estoque zero).">Criar</button></div>
            </div>
            <div class="form-text">Ex.: Tamanho "P, M, G" e Cor "Preto, Branco" = 6 produtos (este + 5 novos, cópias com o mesmo preço, fiscal e fotos). Limite: {{ \App\Services\LojaOnline\GradeLoja::MAXIMO_VARIACOES }} combinações.</div>
        </form>
    @endforeach
</x-erp.card>

@forelse($principais as $pr)
    @php $vs = $variacoes[$pr->id] ?? collect(); $nomes = \App\Services\LojaOnline\GradeLoja::nomes($pr); @endphp
    <x-erp.card :title="\App\Services\LojaOnline\GradeLoja::nomeComum($pr)" icon="grid-3x3" class="mb-3">
        <div class="table-responsive"><table class="table table-sm align-middle">
            <thead><tr><th>Código</th>@foreach($nomes as $n)<th>{{ $n }}</th>@endforeach<th class="text-end">Preço</th><th class="text-end">Saldo na loja online</th><th>Na loja</th><th></th></tr></thead>
            <tbody>
            @foreach($vs as $v)
                @php $op = \App\Services\LojaOnline\GradeLoja::opcoes($v); @endphp
                <tr>
                    <td><a href="{{ route('app.produtos.edit', $v->id) }}">{{ $v->codigo_interno }}</a>@if($v->id === $pr->id) <span class="badge text-bg-primary">principal</span>@endif</td>
                    @foreach($nomes as $n)<td>{{ $op[$n] ?? '—' }}</td>@endforeach
                    <td class="text-end">R$ {{ number_format((float) $v->preco_venda, 2, ',', '.') }}</td>
                    <td class="text-end">{{ isset($saldos[$v->id]) ? rtrim(rtrim(number_format($saldos[$v->id], 3, ',', '.'), '0'), ',') : '—' }}</td>
                    <td>@if($v->loja_online && $v->status === 'ativo')<i class="bi bi-check-circle-fill text-success" title="Aparece"></i>@else<span class="text-muted small">não aparece</span>@endif</td>
                    <td class="text-end">
                        <form method="POST" action="{{ route('app.loja-online.grade.tirar') }}" class="d-inline">@csrf<input type="hidden" name="produto_id" value="{{ $v->id }}">
                            <button class="btn btn-sm btn-outline-secondary" data-confirm="{{ $v->id === $pr->id ? 'Desfazer a grade inteira? Os produtos continuam cadastrados, cada um por si.' : 'Tirar este produto da grade? Ele continua cadastrado.' }}">{{ $v->id === $pr->id ? 'Desfazer grade' : 'Tirar' }}</button></form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        @unless($pr->loja_online)<div class="alert alert-warning py-2">O produto principal não está marcado para aparecer na loja — a grade inteira fica fora da vitrine. Marque em <a href="{{ route('app.loja-online.produtos') }}">Produtos</a>.</div>@endunless
        <form method="POST" action="{{ route('app.loja-online.grade.juntar') }}" class="row g-2 align-items-end">
            @csrf <input type="hidden" name="principal_id" value="{{ $pr->id }}">
            <div class="col-md-3"><label class="form-label small mb-1" for="jc-{{ $pr->id }}">Juntar produto que já existe (código)</label><input class="form-control form-control-sm" id="jc-{{ $pr->id }}" name="codigo" required maxlength="50"></div>
            @foreach($nomes as $n)<div class="col-md-2"><label class="form-label small mb-1" for="jo-{{ $pr->id }}-{{ $loop->index }}">{{ $n }}</label><input class="form-control form-control-sm" id="jo-{{ $pr->id }}-{{ $loop->index }}" name="opcoes[{{ $n }}]" required maxlength="30"></div>@endforeach
            <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Juntar</button></div>
        </form>
    </x-erp.card>
@empty
    <x-erp.card><p class="text-muted mb-0">Nenhuma grade ainda. Busque um produto acima para criar a primeira.</p></x-erp.card>
@endforelse
@endsection
