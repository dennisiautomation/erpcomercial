@extends('layouts.app')

@section('title', 'Loja online — Políticas e cookies')

@section('content')
<x-erp.page-header title="Loja online" icon="globe2" subtitle="Políticas e cookies" />

@include('app.loja-online._abas')

<div class="alert alert-info d-flex gap-2">
    <i class="bi bi-info-circle mt-1"></i>
    <div>Cada página já nasce com um <strong>texto de partida</strong>, montado com os dados da sua empresa e com o que a loja tem ligado (entrega, retirada, PIX, cartão, rastreadores). É um modelo geral: <strong>revise com o seu contador ou advogado</strong> e, se quiser, escreva o seu no lugar. Campo em branco = usa o texto de partida.</div>
</div>

<form method="POST" action="{{ route('app.loja-online.secao.salvar', 'politicas') }}">
    @csrf @method('PUT')
    @foreach($modelos as $chave => $m)
        <x-erp.card :title="$m['titulo']" icon="file-text" class="mb-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <span class="badge {{ filled($loja->opcao($m['campo'])) ? 'bg-primary' : 'bg-secondary' }}">{{ filled($loja->opcao($m['campo'])) ? 'Usando o seu texto' : 'Usando o texto de partida' }}</span>
                <span class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-usar-modelo="{{ $m['campo'] }}">Copiar o texto de partida para editar</button>
                    <a class="btn btn-sm btn-link" href="{{ $loja->enderecoInterno('politica/' . $chave) }}" target="_blank" rel="noopener">Ver na loja <i class="bi bi-box-arrow-up-right"></i></a>
                </span>
            </div>
            <label class="visually-hidden" for="{{ $m['campo'] }}">{{ $m['titulo'] }}</label>
            <textarea name="{{ $m['campo'] }}" id="{{ $m['campo'] }}" rows="7" maxlength="20000" class="form-control @error($m['campo']) is-invalid @enderror" placeholder="Em branco: a loja mostra o texto de partida.">{{ old($m['campo'], $loja->opcao($m['campo'])) }}</textarea>
            @error($m['campo']) <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text">Separe os parágrafos com uma linha em branco. Uma linha curta terminada em dois-pontos vira subtítulo.</div>
            <template data-modelo="{{ $m['campo'] }}">{{ $m['modelo'] }}</template>
        </x-erp.card>
    @endforeach

    <x-erp.card title="Aviso de cookies" icon="cookie" class="mb-3">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" name="cookies_aviso" id="cookies_aviso" value="1" @checked(old('cookies_aviso', $loja->opcao('cookies_aviso')))>
            <label class="form-check-label" for="cookies_aviso">Mostrar o aviso de cookies na primeira visita</label>
        </div>
        <div class="form-text">
            @if($loja->temRastreamento())
                Sua loja tem rastreador configurado em "Anúncios e busca": o aviso aparece <strong>sempre</strong>, com as opções Aceitar todos, Só os necessários e Escolher. Nenhum rastreador carrega antes de a pessoa aceitar.
            @else
                Sua loja não tem rastreador: o aviso é só informativo ("Entendi"). Ao configurar Meta, Google ou TikTok, ele passa a pedir a escolha e aparece sempre.
            @endif
        </div>
    </x-erp.card>
    <div class="lj-salvar d-flex justify-content-end"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Salvar políticas</button></div>
</form>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-usar-modelo]').forEach(function (b) {
    b.addEventListener('click', function () {
        var campo = document.getElementById(b.dataset.usarModelo), modelo = document.querySelector('template[data-modelo="' + b.dataset.usarModelo + '"]').content.textContent;
        var por = function () { campo.value = modelo; campo.focus(); };
        if (campo.value.trim() === '') por();
        else ERP.confirm({title: 'Trocar pelo texto de partida?', message: 'O que está escrito neste campo será substituído. Só vale depois de salvar.', confirmText: 'Trocar'}).then(function (sim) { if (sim) por(); });
    });
});
</script>
@endpush
