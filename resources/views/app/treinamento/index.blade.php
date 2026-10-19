@extends('layouts.app')

@section('title', 'Treinamento')

@section('content')
<x-erp.page-header title="Treinamento" icon="play-btn">
    @if(\App\Http\Middleware\CheckPermission::modoPdv(auth()->user()))
        {{-- Vendedor "só PDV": chegou pelo link do topo do PDV --}}
        <a href="{{ route('app.pdv.index') }}" class="btn btn-primary">
            <i class="bi bi-upc-scan me-1"></i> Voltar ao PDV
        </a>
    @endif
</x-erp.page-header>

@if($treinamentos->isEmpty())
    <x-erp.empty-state title="Nenhum vídeo disponível" icon="play-btn"
                       description="Os vídeos de treinamento do ERP aparecem aqui assim que forem publicados." />
@else
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <video id="treinoPlayer" controls preload="metadata" class="w-100 rounded bg-dark" style="max-height: 70vh;"></video>
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mt-3">
                    <div>
                        <h5 class="mb-1" id="treinoTitulo"></h5>
                        <p class="text-muted mb-0 small" id="treinoDescricao" style="white-space: pre-line;"></p>
                    </div>
                    <button type="button" class="btn btn-outline-success" id="treinoMarcar">
                        <i class="bi bi-check2-circle me-1"></i> Marcar como assistido
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0">Vídeos</h6></div>
            <div class="list-group list-group-flush" id="treinoLista">
                @foreach($treinamentos as $treinamento)
                    <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2"
                            data-id="{{ $treinamento->id }}"
                            data-titulo="{{ $treinamento->titulo }}"
                            data-descricao="{{ $treinamento->descricao }}"
                            data-video="{{ route('app.treinamento.video', $treinamento) }}"
                            data-concluir="{{ route('app.treinamento.concluir', $treinamento) }}">
                        <span class="text-start">{{ $treinamento->titulo }}</span>
                        <span class="treino-selo">
                            @if(isset($concluidos[$treinamento->id]))
                                <span class="badge bg-success" title="Assistido em {{ $concluidos[$treinamento->id] }}"><i class="bi bi-check-lg"></i> Assistido</span>
                            @else
                                <span class="badge bg-secondary">Pendente</span>
                            @endif
                        </span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
/*
 * Conta como assistido o que acontecer primeiro: o vídeo chegar ao fim
 * (evento `ended`) ou o clique em "Marcar como assistido".
 */
(function () {
    const player = document.getElementById('treinoPlayer');
    if (!player) return;
    const itens = [...document.querySelectorAll('#treinoLista [data-id]')];
    const btnMarcar = document.getElementById('treinoMarcar');
    let atual = null;

    function selo(item) {
        return item.querySelector('.treino-selo');
    }

    function assistido(item) {
        return !!selo(item).querySelector('.bg-success');
    }

    function atualizarBotao() {
        const feito = atual && assistido(atual);
        btnMarcar.disabled = feito;
        btnMarcar.innerHTML = feito
            ? '<i class="bi bi-check2-circle me-1"></i> Assistido'
            : '<i class="bi bi-check2-circle me-1"></i> Marcar como assistido';
    }

    function abrir(item) {
        itens.forEach(i => i.classList.toggle('active', i === item));
        atual = item;
        player.src = item.dataset.video;
        document.getElementById('treinoTitulo').textContent = item.dataset.titulo;
        document.getElementById('treinoDescricao').textContent = item.dataset.descricao || '';
        atualizarBotao();
    }

    async function concluir() {
        const item = atual;
        if (!item || assistido(item)) return;
        btnMarcar.disabled = true;
        try {
            const resp = await fetch(item.dataset.concluir, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });
            const json = await resp.json();
            if (!resp.ok) throw new Error();
            selo(item).innerHTML = `<span class="badge bg-success" title="Assistido em ${json.concluido_em}"><i class="bi bi-check-lg"></i> Assistido</span>`;
            ERP.toast('Treinamento marcado como assistido.', 'success');
        } catch (e) {
            ERP.toast('Não foi possível marcar como assistido.', 'danger');
        }
        atualizarBotao();
    }

    itens.forEach(item => item.addEventListener('click', () => abrir(item)));
    btnMarcar.addEventListener('click', concluir);
    player.addEventListener('ended', concluir);

    abrir(itens.find(i => !assistido(i)) || itens[0]);
})();
</script>
@endpush
