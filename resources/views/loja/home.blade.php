@extends('loja.layout')

@section('conteudo')
@php($vinculo = fn (?string $l) => blank($l) ? null : (str_starts_with($l, 'https://') ? $l : $ctx->url($l)))
@php($primeiroTitulo = true)
@forelse($blocos as $bloco)
    <div data-lj-bloco="{{ $bloco['id'] ?? '' }}">
        @include('loja.blocos.' . $bloco['tipo'], ['bloco' => $bloco, 'vinculo' => $vinculo, 'h1' => $primeiroTitulo && $bloco['tipo'] === 'banner'])
    </div>
    @if($bloco['tipo'] === 'banner') @php($primeiroTitulo = false) @endif
@empty
    <div class="lj-caixa lj-vazio-grande">
        <i class="bi bi-shop"></i>
        <h1>{{ $loja->nome }}</h1>
        <p>A loja está sendo montada. Volte em breve.</p>
    </div>
@endforelse

@if($totalProdutos === 0 && ($ctx->previa || $ctx->rascunho))
    <div class="lj-caixa lj-secao">
        <div class="lj-vazio">Nenhum produto está marcado para aparecer na loja online. No ERP, abra <strong>Loja online → Produtos</strong> e escolha o que entra na vitrine.</div>
    </div>
@endif
@endsection
