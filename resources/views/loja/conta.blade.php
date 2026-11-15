@extends('loja.layout')

@section('pagina', 'compra')

@section('conteudo')
<div class="lj-caixa" data-lj-minha-conta>
    <div class="lj-pagina__topo">
        <div><h1>Olá, {{ \Illuminate\Support\Str::before($conta->nome, ' ') }}</h1><p style="color:var(--suave);margin-top:.3rem">{{ $conta->email }}</p></div>
        <form method="post" action="{{ $ctx->url('sair') }}">@csrf<button class="lj-btn lj-btn--p lj-btn--linha" type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Sair</button></form>
    </div>

    @if(session('loja_ok'))<div class="lj-aviso lj-aviso--ok"><i class="bi bi-check-circle"></i><span>{{ session('loja_ok') }}</span></div>@endif
    @if(session('loja_erro'))<div class="lj-aviso lj-aviso--erro"><i class="bi bi-exclamation-circle"></i><span>{{ session('loja_erro') }}</span></div>@endif

    <div class="lj-duas" style="padding-top:0">
        <div>
            <h2 style="font-size:1.2rem;letter-spacing:0;margin-bottom:1rem">Meus pedidos</h2>
            @if($pedidos->isEmpty())
                <div class="lj-vazio-quadro"><i class="bi bi-bag" aria-hidden="true"></i>Você ainda não fez pedido com esta conta.<br><a class="lj-btn" style="margin-top:1rem" href="{{ $ctx->url('produtos') }}">Ver os produtos</a></div>
            @else
                <div class="lj-pedidos-lista">
                    @foreach($pedidos as $linha)
                        @php($pd = $linha['pedido'])
                        @php($st = $linha['situacao'])
                        <a class="lj-pedido-linha" href="{{ $ctx->url('pedido/' . $pd->loja_token) }}">
                            <span><strong>Pedido #{{ $pd->numero }}</strong><small>{{ $pd->created_at->format('d/m/Y') }} · {{ $linha['itens'] }} {{ $linha['itens'] === 1 ? 'item' : 'itens' }}</small></span>
                            <span class="lj-etiqueta @if($st['situacao'] === 'cancelado') lj-etiqueta--fim @elseif($st['pago']) lj-etiqueta--ok @endif">{{ $st['rotulo'] }}</span>
                            <strong>R$ {{ number_format((float) $pd->total, 2, ',', '.') }}</strong>
                        </a>
                    @endforeach
                </div>
            @endif
            <p style="margin-top:1.4rem"><a class="lj-link" href="{{ $ctx->url('favoritos') }}"><i class="bi bi-heart" aria-hidden="true"></i> Meus favoritos</a></p>
        </div>

        <aside class="lj-resumo" id="dados">
            <h2>Meus dados</h2>
            <form class="lj-form" method="post" action="{{ $ctx->url('conta') }}" style="display:grid;gap:.8rem;margin-top:.8rem">
                @csrf
                <div><label for="m-nome" style="display:block;font-size:.88rem;font-weight:600;margin-bottom:.3rem">Nome</label><input class="lj-campo" id="m-nome" name="nome" value="{{ old('nome', $conta->nome) }}" required></div>
                <div><label for="m-tel" style="display:block;font-size:.88rem;font-weight:600;margin-bottom:.3rem">Telefone com DDD</label><input class="lj-campo" id="m-tel" name="telefone" value="{{ old('telefone', $conta->telefone) }}" inputmode="tel"></div>
                <div><label for="m-atual" style="display:block;font-size:.88rem;font-weight:600;margin-bottom:.3rem">Trocar a senha <small style="font-weight:400;color:var(--suave)">(opcional)</small></label>
                    <input class="lj-campo" id="m-atual" type="password" name="senha_atual" placeholder="Senha atual" autocomplete="current-password">
                    <label class="lj-so-leitor" for="m-nova">Senha nova</label>
                    <input class="lj-campo" id="m-nova" type="password" name="senha_nova" placeholder="Senha nova (8 ou mais)" minlength="8" autocomplete="new-password" style="margin-top:.4rem"></div>
                @foreach($errors->all() as $e)<p class="lj-erro">{{ $e }}</p>@endforeach
                <button class="lj-btn lj-btn--linha" type="submit">Salvar</button>
            </form>
            @if($cliente && filled($cliente->logradouro))
                <div class="lj-bloco"><h2>Último endereço de entrega</h2>
                    <p style="color:var(--suave);font-size:.92rem">{{ collect([trim(($cliente->logradouro ?? '') . ', ' . ($cliente->numero ?? ''), ', '), $cliente->complemento, $cliente->bairro, trim(($cliente->cidade ?? '') . '/' . ($cliente->uf ?? ''), '/'), $cliente->cep])->filter()->implode(' · ') }}</p>
                    <p style="color:var(--suave);font-size:.85rem">Ele já vem preenchido na próxima compra; dá para trocar no fechamento.</p></div>
            @endif
        </aside>
    </div>
</div>
@endsection
