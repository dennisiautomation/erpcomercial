@extends('layouts.app')

@section('title', 'Loja online — Carrinhos abandonados')

@section('content')
<x-erp.page-header title="Loja online" icon="globe2" subtitle="Carrinhos abandonados: quem chegou ao fechamento e não comprou" />

@include('app.loja-online._abas')

<div class="alert alert-info">
    Entra aqui quem <strong>digitou e-mail ou telefone no fechamento</strong> e saiu sem concluir (parado há mais de 30 minutos, últimos 30 dias).
    O link devolve o carrinho pronto para o cliente. Quem mandar a mensagem é <strong>você</strong> — o botão do WhatsApp só abre a conversa com o texto.
    @if($recuperados) <strong>{{ $recuperados }}</strong> carrinho(s) recuperado(s) viraram pedido nos últimos 30 dias.@endif
</div>

<x-erp.card>
    @forelse($linhas as $l)
        @php
            $itens = (array) json_decode($l->itens, true);
            $link = $base . '/carrinho/recuperar/' . $l->token;
            $texto = 'Olá' . ($l->nome ? ', ' . \Illuminate\Support\Str::before($l->nome, ' ') : '') . '! Vi que você deixou produtos no carrinho da ' . $loja->nome . '. Guardei para você: ' . $link;
        @endphp
        <div class="border rounded p-3 mb-2">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <strong>{{ $l->nome ?: 'Sem nome' }}</strong>
                    <span class="text-muted small">· parado desde {{ \Illuminate\Support\Carbon::parse($l->updated_at)->format('d/m H:i') }}</span>
                    @if($l->recuperado_em)<span class="badge text-bg-info ms-1">abriu o link</span>@endif
                    @if($l->lembrete_em)<span class="badge text-bg-secondary ms-1">lembrete enviado</span>@endif
                    <div class="small text-muted">{{ $l->email }}@if($l->email && $l->telefone) · @endif{{ $l->telefone }}</div>
                    <div class="small mt-1">@foreach($itens as $i){{ (int) ($i['quantidade'] ?? 1) }}× {{ $i['nome'] ?? '' }}@if(! $loop->last); @endif @endforeach</div>
                </div>
                <div class="text-end">
                    <div class="fs-5 fw-bold">R$ {{ number_format((float) $l->total, 2, ',', '.') }}</div>
                    <div class="d-flex gap-1 justify-content-end mt-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-copiar="{{ $link }}">Copiar o link</button>
                        @if($l->telefone)<a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="https://wa.me/55{{ $l->telefone }}?text={{ rawurlencode($texto) }}"><i class="bi bi-whatsapp me-1"></i>Abrir conversa</a>@endif
                    </div>
                </div>
            </div>
        </div>
    @empty
        <p class="text-muted mb-0">Nenhum carrinho abandonado agora.</p>
    @endforelse
    {{ $linhas->links() }}
</x-erp.card>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-copiar]').forEach(function (b) {
    b.addEventListener('click', function () {
        navigator.clipboard.writeText(b.dataset.copiar).then(function () { b.textContent = 'Copiado'; setTimeout(function () { b.textContent = 'Copiar o link'; }, 1600); });
    });
});
</script>
@endpush
