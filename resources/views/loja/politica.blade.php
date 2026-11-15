@extends('loja.layout')

@section('conteudo')
<div class="lj-caixa">
    <nav class="lj-trilha" aria-label="Você está em"><a href="{{ $ctx->url() }}">Início</a> <span>/</span> <span>{{ $tituloPolitica }}</span></nav>
    <div class="lj-pagina__topo"><h1>{{ $tituloPolitica }}</h1></div>
    <div class="lj-politica">
        <div class="lj-politica__texto">
            @foreach(preg_split('/\n\s*\n/', trim($texto)) as $paragrafo)
                @php($p = trim($paragrafo))
                @if(mb_strlen($p) <= 70 && str_ends_with($p, ':'))<h2>{{ rtrim($p, ':') }}</h2>@else<p>{{ $p }}</p>@endif
            @endforeach
        </div>
        <nav class="lj-politica__menu" aria-label="Outras páginas">
            @foreach(\App\Services\LojaOnline\PoliticasLoja::TIPOS as $chave => [$nome])
                <a href="{{ $ctx->url('politica/' . $chave) }}" @if($chave === $tipo) aria-current="page" @endif>{{ $nome }}</a>
            @endforeach
            <button type="button" data-lj-cookies-abrir>Preferências de cookies</button>
        </nav>
    </div>
</div>
@endsection
