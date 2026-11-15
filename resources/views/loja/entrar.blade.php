@extends('loja.layout')

@section('pagina', 'compra')

@section('conteudo')
@php($volta = request('volta') === 'finalizar' ? 'finalizar' : '')
@php($errosCriar = $errors->getBag('criar'))
<div class="lj-caixa">
    <div class="lj-pagina__topo"><div><h1>Entrar</h1><p style="color:var(--suave);margin-top:.3rem">Acompanhe os pedidos e compre mais rápido. Sem conta também dá para comprar.</p></div></div>

    @if(session('loja_ok'))<div class="lj-aviso lj-aviso--ok"><i class="bi bi-check-circle"></i><span>{{ session('loja_ok') }}</span></div>@endif
    @if(session('loja_erro'))<div class="lj-aviso lj-aviso--erro"><i class="bi bi-exclamation-circle"></i><span>{{ session('loja_erro') }}</span></div>@endif

    <div class="lj-entrar">
        <section class="lj-entrar__quadro" aria-labelledby="t-entrar">
            <h2 id="t-entrar">Já tenho conta</h2>
            <p>Entre com o e-mail e a senha.</p>
            <form class="lj-form" method="post" action="{{ $ctx->url('entrar') }}" data-lj-entrar>
                @csrf
                <input type="hidden" name="volta" value="{{ $volta }}">
                <div><label for="e-email">E-mail</label><input class="lj-campo" id="e-email" type="email" name="email" value="{{ $errosCriar->any() ? '' : old('email') }}" autocomplete="email" required></div>
                <div><label for="e-senha">Senha</label><input class="lj-campo" id="e-senha" type="password" name="senha" autocomplete="current-password" required></div>
                @error('email')<p class="lj-erro">{{ $message }}</p>@enderror
                @error('senha')<p class="lj-erro">{{ $message }}</p>@enderror
                <button class="lj-btn lj-btn--cheio" type="submit">Entrar</button>
            </form>
            <details>
                <summary>Esqueci a senha</summary>
                <form class="lj-form" method="post" action="{{ $ctx->url('esqueci') }}" style="margin-top:.8rem" data-lj-esqueci>
                    @csrf
                    <div><label for="q-email">Seu e-mail</label><input class="lj-campo" id="q-email" type="email" name="email" autocomplete="email" required></div>
                    <button class="lj-btn lj-btn--linha" type="submit">Enviar o link</button>
                </form>
            </details>
        </section>

        <section class="lj-entrar__quadro" id="criar" aria-labelledby="t-criar">
            <h2 id="t-criar">Criar conta</h2>
            <p>Leva um minuto.</p>
            <form class="lj-form" method="post" action="{{ $ctx->url('conta/criar') }}" data-lj-criar>
                @csrf
                <input type="hidden" name="volta" value="{{ $volta }}">
                <div><label for="c-nome">Nome completo</label><input class="lj-campo" id="c-nome" name="nome" value="{{ $errosCriar->any() ? old('nome') : '' }}" autocomplete="name" required>@if($errosCriar->has('nome'))<p class="lj-erro">{{ $errosCriar->first('nome') }}</p>@endif</div>
                <div><label for="c-email">E-mail</label><input class="lj-campo" id="c-email" type="email" name="email" value="{{ $errosCriar->any() ? old('email') : '' }}" autocomplete="email" required>@if($errosCriar->has('email'))<p class="lj-erro">{{ $errosCriar->first('email') }}</p>@endif</div>
                <div><label for="c-tel">Telefone com DDD <small style="font-weight:400;color:var(--suave)">(opcional)</small></label><input class="lj-campo" id="c-tel" name="telefone" value="{{ $errosCriar->any() ? old('telefone') : '' }}" inputmode="tel" autocomplete="tel">@if($errosCriar->has('telefone'))<p class="lj-erro">{{ $errosCriar->first('telefone') }}</p>@endif</div>
                <div><label for="c-senha">Crie uma senha <small style="font-weight:400;color:var(--suave)">(8 caracteres ou mais)</small></label><input class="lj-campo" id="c-senha" type="password" name="senha" minlength="8" autocomplete="new-password" required>@if($errosCriar->has('senha'))<p class="lj-erro">{{ $errosCriar->first('senha') }}</p>@endif</div>
                <label class="lj-aceite"><input type="checkbox" name="aceite" value="1" required> <span>Li e aceito a <a href="{{ $ctx->url('politica/privacidade') }}" target="_blank" rel="noopener">política de privacidade</a>.</span></label>
                @if($errosCriar->has('aceite'))<p class="lj-erro">{{ $errosCriar->first('aceite') }}</p>@endif
                <button class="lj-btn lj-btn--cheio" type="submit">Criar conta</button>
            </form>
        </section>
    </div>
</div>
@endsection
