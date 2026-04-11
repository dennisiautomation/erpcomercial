@extends('layouts.app')

@section('title', 'Notificacoes')

@section('content')
<x-erp.page-header title="Notificacoes">
    @if($notificacoes->where('lida', false)->count() > 0)
        <form method="POST" action="{{ route('app.notificacoes.todas-lidas') }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-check-all me-1"></i> Marcar todas como lidas
            </button>
        </form>
    @endif
</x-erp.page-header>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        @forelse($notificacoes as $notificacao)
            <div class="d-flex align-items-start gap-3 px-4 py-3 border-bottom {{ $notificacao->lida ? 'opacity-75' : 'bg-light' }}" style="transition: background 0.15s;">
                <div class="rounded-3 p-2 bg-{{ $notificacao->cor }} bg-opacity-10 flex-shrink-0" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-{{ $notificacao->icone }} text-{{ $notificacao->cor }}"></i>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-0 fw-semibold {{ $notificacao->lida ? '' : 'text-dark' }}">
                                {{ $notificacao->titulo }}
                            </h6>
                            @if($notificacao->mensagem)
                                <small class="text-muted">{{ $notificacao->mensagem }}</small>
                            @endif
                        </div>
                        <small class="text-muted flex-shrink-0 ms-2">{{ $notificacao->created_at->diffForHumans() }}</small>
                    </div>
                </div>
                <div class="flex-shrink-0 d-flex align-items-center gap-2">
                    @if($notificacao->url && !$notificacao->lida)
                        <form method="POST" action="{{ route('app.notificacoes.lida', $notificacao) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-arrow-right"></i> Ver
                            </button>
                        </form>
                    @elseif($notificacao->url && $notificacao->lida)
                        <a href="{{ $notificacao->url }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    @endif
                    @if(!$notificacao->lida)
                        <form method="POST" action="{{ route('app.notificacoes.lida', $notificacao) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Marcar como lida">
                                <i class="bi bi-check"></i>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <x-erp.empty-state
                icon="bell-slash"
                title="Nenhuma notificacao"
                description="Voce nao tem notificacoes no momento."
            />
        @endforelse
    </div>
</div>

<div class="mt-3">
    {{ $notificacoes->links() }}
</div>
@endsection
