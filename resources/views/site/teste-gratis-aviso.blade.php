@extends('site._teste-base')
@section('titulo', $titulo)
@section('css')
.aviso{max-width:34rem;margin:clamp(1rem,6vh,4rem) auto 0;background:#fff;border-radius:22px;box-shadow:var(--shadow);padding:clamp(1.6rem,4vw,2.6rem)}
.aviso h1{font-size:clamp(1.5rem,3.4vw,2rem);font-weight:700;line-height:1.15;margin-bottom:.8rem}
.aviso p{color:var(--ink2);margin-bottom:1.4rem;text-wrap:pretty}
@endsection
@section('conteudo')
<div class="wrap">
  <div class="aviso" role="status">
    <h1>{{ $titulo }}</h1>
    <p>{{ $texto }}</p>
    @if($botao)<a class="pill" href="{{ $botao[1] }}">{{ $botao[0] }}</a>@endif
  </div>
</div>
@endsection
