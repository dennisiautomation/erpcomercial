@extends('loja.layout')

@section('pagina', 'compra')

@section('conteudo')
<div class="lj-caixa">
    <div class="lj-pagina__topo"><div><h1>Senha nova</h1></div></div>
    <div class="lj-entrar">
        <section class="lj-entrar__quadro">
            <h2>Crie a sua senha nova</h2>
            <p>Depois de gravar você já entra na conta.</p>
            <form class="lj-form" method="post" action="{{ $ctx->url('redefinir/' . $token) }}">
                @csrf
                <div><label for="r-senha">Senha nova <small style="font-weight:400;color:var(--suave)">(8 caracteres ou mais)</small></label><input class="lj-campo" id="r-senha" type="password" name="senha" minlength="8" autocomplete="new-password" required></div>
                @error('senha')<p class="lj-erro">{{ $message }}</p>@enderror
                <button class="lj-btn lj-btn--cheio" type="submit">Gravar a senha</button>
            </form>
        </section>
    </div>
</div>
@endsection
