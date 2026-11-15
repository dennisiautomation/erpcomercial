@extends('loja.layout')

@section('conteudo')
<div class="lj-caixa">
    <div class="lj-pagina__topo"><div><h1>Favoritos</h1>
        <p style="color:var(--suave);margin-top:.3rem">@if($contaLoja ?? null)Guardados na sua conta.@else Guardados neste aparelho.@if($loja->opcao('conta_cliente')) <a href="{{ $ctx->url('entrar') }}">Entre</a> para levar para qualquer aparelho.@endif @endif</p></div></div>
    <div class="lj-grade lj-sem-cat" style="--colunas:4" data-lj-fav-lista data-url="{{ $ctx->caminho('vistos') }}"></div>
    <div class="lj-vazio-quadro" data-lj-fav-vazio hidden><i class="bi bi-heart" aria-hidden="true"></i>Nenhum favorito ainda. Toque no coração de um produto para guardar.<br><a class="lj-btn" style="margin-top:1rem" href="{{ $ctx->url('produtos') }}">Ver os produtos</a></div>
    <div style="height:3rem"></div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var lista = document.querySelector('[data-lj-fav-lista]'), vazio = document.querySelector('[data-lj-fav-vazio]');
    function carregar() {
        var ids = LJ.favoritos.ler().slice(0, 8 * 6);
        if (!ids.length) { lista.innerHTML = ''; vazio.hidden = false; return; }
        vazio.hidden = true;
        // o endereço dos cartões aceita 8 por vez
        var lotes = []; for (var i = 0; i < ids.length; i += 8) lotes.push(ids.slice(i, i + 8));
        Promise.all(lotes.map(function (l) { return fetch(lista.dataset.url + '?ids=' + l.join(','), {credentials: 'same-origin'}).then(function (r) { return r.text(); }); }))
            .then(function (partes) { lista.innerHTML = partes.join(''); LJ.favoritos.pintar(); if (!lista.querySelector('.lj-card')) vazio.hidden = false; });
    }
    document.addEventListener('lj:favoritos', function () { setTimeout(carregar, 50); });
    setTimeout(carregar, 300);
})();
</script>
@endpush
