@extends('layouts.app')

@section('title', 'Loja online — Avaliações')

@section('content')
<x-erp.page-header title="Loja online" icon="globe2" subtitle="Avaliações dos produtos, feitas por quem comprou" />

@include('app.loja-online._abas')

<ul class="nav nav-tabs mb-3">
    @foreach(['pendente' => 'Para conferir', 'aprovada' => 'Publicadas', 'oculta' => 'Fora da loja'] as $k => $rotulo)
        <li class="nav-item"><a class="nav-link {{ $ver === $k ? 'active' : '' }}" href="{{ route('app.loja-online.avaliacoes', ['ver' => $k]) }}">{{ $rotulo }} <span class="badge text-bg-secondary ms-1">{{ $totais[$k] ?? 0 }}</span></a></li>
    @endforeach
</ul>

<x-erp.card>
    @forelse($avaliacoes as $a)
        <div class="border rounded p-3 mb-2">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <span class="text-warning" aria-label="{{ $a->nota }} de 5 estrelas">@for($i = 1; $i <= 5; $i++)<i class="bi bi-star{{ $i <= $a->nota ? '-fill' : '' }}"></i>@endfor</span>
                    <strong class="ms-1">{{ $a->nome }}</strong> <span class="text-muted small">· {{ \Illuminate\Support\Carbon::parse($a->created_at)->format('d/m/Y') }} · compra verificada</span>
                    <div class="small text-muted">{{ $a->produto }} (cód. {{ $a->codigo_interno }})</div>
                    @if($a->texto)<p class="mb-0 mt-1">{{ $a->texto }}</p>@else<p class="mb-0 mt-1 text-muted fst-italic">Sem comentário.</p>@endif
                </div>
            </div>
            <form method="POST" action="{{ route('app.loja-online.avaliacoes.moderar', $a->id) }}" class="row g-2 align-items-end mt-1">
                @csrf
                <div class="col-md-7"><label class="form-label small mb-1" for="rs-{{ $a->id }}">Resposta da loja (opcional, aparece embaixo da avaliação)</label><input class="form-control form-control-sm" id="rs-{{ $a->id }}" name="resposta" maxlength="600" value="{{ $a->resposta }}"></div>
                <div class="col-auto">
                    @if($a->status !== 'aprovada')<button class="btn btn-sm btn-success" name="status" value="aprovada">Publicar</button>@else<button class="btn btn-sm btn-outline-primary" name="status" value="aprovada">Salvar resposta</button>@endif
                    @if($a->status !== 'oculta')<button class="btn btn-sm btn-outline-secondary" name="status" value="oculta">Deixar fora da loja</button>@endif
                </div>
            </form>
        </div>
    @empty
        <p class="text-muted mb-0">Nada aqui. O cliente avalia pela página do pedido, depois que o pagamento é confirmado.</p>
    @endforelse
    {{ $avaliacoes->links() }}
</x-erp.card>
@endsection
