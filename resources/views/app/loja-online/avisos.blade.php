@extends('layouts.app')

@section('title', 'Loja online — Avisos por e-mail')

@section('content')
<x-erp.page-header title="Loja online" icon="globe2" subtitle="Avisos por e-mail do pedido" />

@include('app.loja-online._abas')

<form method="POST" action="{{ route('app.loja-online.secao.salvar', 'avisos') }}">
    @csrf @method('PUT')
    <x-erp.card title="Para a loja" icon="shop" class="mb-3">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="email_pedidos" class="form-label">E-mail que recebe os pedidos</label>
                <input type="email" name="email_pedidos" id="email_pedidos" class="form-control @error('email_pedidos') is-invalid @enderror" value="{{ old('email_pedidos', $loja->opcao('email_pedidos')) }}" placeholder="pedidos@sualoja.com.br">
                @error('email_pedidos') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">É também para onde vai a resposta do cliente quando ele responde um aviso.</div>
            </div>
            <div class="col-md-6 d-flex align-items-center">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="email_loja_novo" id="email_loja_novo" value="1" @checked(old('email_loja_novo', $loja->opcao('email_loja_novo')))>
                    <label class="form-check-label" for="email_loja_novo">Avisar a loja a cada pedido novo</label>
                </div>
            </div>
        </div>
    </x-erp.card>

    <x-erp.card title="Para o cliente do pedido" icon="person" class="mb-3">
        <p class="text-muted small">Vão para o e-mail que o cliente informou ao fechar a compra. Quem não informou e-mail acompanha pelo endereço do pedido.</p>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" name="email_cliente_recebido" id="email_cliente_recebido" value="1" @checked(old('email_cliente_recebido', $loja->opcao('email_cliente_recebido')))>
                    <label class="form-check-label" for="email_cliente_recebido"><strong>Recebemos o seu pedido</strong> <span class="text-muted">— na hora em que ele fecha a compra, com os itens e o botão para acompanhar e pagar</span></label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" name="email_cliente_confirmado" id="email_cliente_confirmado" value="1" @checked(old('email_cliente_confirmado', $loja->opcao('email_cliente_confirmado')))>
                    <label class="form-check-label" for="email_cliente_confirmado"><strong>Pedido confirmado</strong> <span class="text-muted">— quando o PIX ou o cartão é confirmado, ou quando a loja confirma o pedido no ERP</span></label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="email_cliente_andamento" id="email_cliente_andamento" value="1" @checked(old('email_cliente_andamento', $loja->opcao('email_cliente_andamento')))>
                    <label class="form-check-label" for="email_cliente_andamento"><strong>Andamento</strong> <span class="text-muted">— pronto para retirada / a caminho (ao faturar), entregue e cancelado</span></label>
                </div>
            </div>
            <div class="col-md-6">
                <label for="email_remetente" class="form-label">Nome que aparece como remetente</label>
                <input type="text" name="email_remetente" id="email_remetente" maxlength="60" class="form-control" value="{{ old('email_remetente', $loja->opcao('email_remetente')) }}" placeholder="{{ $loja->nome }}">
                <div class="form-text">Em branco, usa o nome da loja. O endereço de envio é o da plataforma ({{ config('mail.from.address') }}); a resposta do cliente vai para o e-mail da loja.</div>
            </div>
        </div>
    </x-erp.card>
    <div class="d-flex justify-content-end mb-4"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Salvar avisos</button></div>
</form>

<x-erp.card title="Ver como chega" icon="send">
    <form method="POST" action="{{ route('app.loja-online.avisos.testar') }}" class="d-flex flex-wrap align-items-end gap-2">
        @csrf
        <div>
            <label for="tipo_teste" class="form-label small fw-semibold">Aviso</label>
            <select name="tipo" id="tipo_teste" class="form-select">
                <option value="recebido">Recebemos o seu pedido</option>
                <option value="confirmado">Pedido confirmado</option>
                <option value="faturado">Pronto / a caminho</option>
                <option value="loja_novo">Pedido novo (para a loja)</option>
            </select>
        </div>
        <button type="submit" class="btn btn-outline-primary"><i class="bi bi-send me-1"></i> Enviar teste para {{ auth()->user()->email }}</button>
    </form>
    <div class="form-text mt-2">Usa o último pedido da loja online como exemplo. Nada é enviado ao cliente.</div>
</x-erp.card>
@endsection
