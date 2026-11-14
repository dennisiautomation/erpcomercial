@extends('layouts.app')

@section('title', 'Loja online — Configurações')

@section('content')
<x-erp.page-header title="Loja online" icon="globe2" subtitle="Configurações">
    <a href="{{ $loja->enderecoInterno() }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-up-right me-1"></i> {{ $loja->noAr() ? 'Abrir a loja' : 'Ver prévia' }}</a>
</x-erp.page-header>

@include('app.loja-online._abas')

<form method="POST" action="{{ route('app.loja-online.configuracoes.salvar') }}">
    @csrf @method('PUT')

    <x-erp.card title="De onde a loja online vende" icon="shop" class="mb-3">
        <p class="text-muted small">A loja online não tem estoque nem pedido próprios: ela mostra o saldo de uma loja do ERP e cada compra vira um <strong>pedido</strong> nessa loja, com o canal <strong>Online</strong>.</p>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="nome" class="form-label">Nome da loja online <span class="text-danger">*</span></label>
                <input type="text" name="nome" id="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome', $loja->nome) }}" maxlength="120" required>
                @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="unidade_id" class="form-label">Loja de origem <span class="text-danger">*</span></label>
                <select name="unidade_id" id="unidade_id" class="form-select @error('unidade_id') is-invalid @enderror" required>
                    <option value="">Escolha…</option>
                    @foreach($unidades as $u)
                        <option value="{{ $u->id }}" @selected((int) old('unidade_id', $loja->unidade_id) === $u->id)>{{ $u->nome }}{{ $u->cidade ? ' — ' . $u->cidade . '/' . $u->uf : '' }}</option>
                    @endforeach
                </select>
                @error('unidade_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">Os pedidos caem nesta loja; é dela o endereço de retirada e de onde sai a entrega. Para uma operação separada, crie uma loja só do e-commerce em Minhas Lojas e escolha-a aqui.</div>
            </div>
            <div class="col-md-4">
                <label for="estoque_id" class="form-label">Estoque</label>
                <select name="estoque_id" id="estoque_id" class="form-select @error('estoque_id') is-invalid @enderror">
                    <option value="">O mesmo do balcão (estoque de venda da loja)</option>
                    @foreach($estoques as $e)
                        <option value="{{ $e->id }}" data-unidade="{{ $e->unidade_id }}" @selected((int) old('estoque_id', $loja->estoque_id) === $e->id)>Só o estoque “{{ $e->nome }}”</option>
                    @endforeach
                </select>
                @error('estoque_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">Para separar o que é da internet, crie um estoque (ex.: “E-commerce”) em Configurações → Estoques da Loja e escolha-o aqui.</div>
            </div>
            <div class="col-md-4">
                <label for="vendedor_id" class="form-label">Vendedor dos pedidos</label>
                <select name="vendedor_id" id="vendedor_id" class="form-select">
                    <option value="">Sem vendedor</option>
                    @foreach($vendedores as $v)<option value="{{ $v->id }}" @selected((int) old('vendedor_id', $loja->vendedor_id) === $v->id)>{{ $v->name }}</option>@endforeach
                </select>
                <div class="form-text">Quem aparece como vendedor nos pedidos da loja online (relatórios e comissão).</div>
            </div>
            <div class="col-md-4">
                <label for="pedido_minimo" class="form-label">Pedido mínimo (R$)</label>
                <input type="text" inputmode="decimal" name="pedido_minimo" id="pedido_minimo" class="form-control @error('pedido_minimo') is-invalid @enderror" value="{{ old('pedido_minimo', number_format((float) $loja->opcao('pedido_minimo'), 2, '.', '')) }}">
                @error('pedido_minimo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">0 = sem mínimo.</div>
            </div>
            <div class="col-md-4 d-flex flex-column justify-content-center">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="vender_sem_estoque" id="vender_sem_estoque" value="1" @checked(old('vender_sem_estoque', $loja->opcao('vender_sem_estoque')))>
                    <label class="form-check-label" for="vender_sem_estoque">Vender mesmo sem saldo</label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="mostrar_estoque" id="mostrar_estoque" value="1" @checked(old('mostrar_estoque', $loja->opcao('mostrar_estoque')))>
                    <label class="form-check-label" for="mostrar_estoque">Mostrar a quantidade em estoque</label>
                </div>
            </div>
        </div>
    </x-erp.card>

    <x-erp.card title="Como o cliente recebe e paga" icon="truck" class="mb-3">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="fw-semibold mb-2">Recebimento</div>
                <div class="form-check form-switch">
                    <input class="form-check-input @error('retirada') is-invalid @enderror" type="checkbox" role="switch" name="retirada" id="retirada" value="1" @checked(old('retirada', $loja->opcao('retirada')))>
                    <label class="form-check-label" for="retirada">Retirada na loja <span class="text-muted">(sem frete)</span></label>
                    @error('retirada') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="entrega" id="entrega" value="1" @checked(old('entrega', $loja->opcao('entrega')))>
                    <label class="form-check-label" for="entrega">Entrega no endereço <span class="text-muted">(frete pelo que estiver ligado em Integrações)</span></label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="fw-semibold mb-2">Pagamento</div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="pix" id="pix" value="1" @checked(old('pix', $loja->opcao('pix')))>
                    <label class="form-check-label" for="pix">PIX <span class="text-muted">{{ $meios['pix'] ? '(ligado em Integrações)' : '(nenhum provedor ligado em Integrações)' }}</span></label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="cartao" id="cartao" value="1" @checked(old('cartao', $loja->opcao('cartao')))>
                    <label class="form-check-label" for="cartao">Cartão por link <span class="text-muted">{{ \App\Services\Pagamento\CartaoPedidoService::provedorEmUso($loja->empresa_id) ? '(ligado em Integrações)' : '(nenhum provedor ligado em Integrações)' }}</span></label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="combinar" id="combinar" value="1" @checked(old('combinar', $loja->opcao('combinar')))>
                    <label class="form-check-label" for="combinar">Combinar com a loja <span class="text-muted">(pedido fica aguardando a equipe)</span></label>
                </div>
                <div class="form-text">Sem PIX nem cartão ligados, “combinar com a loja” vale sempre — a loja nunca fica sem forma de fechar o pedido.</div>
            </div>
        </div>
    </x-erp.card>

    <x-erp.card title="Contato, redes sociais e textos" icon="chat-text" class="mb-3">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="whatsapp" class="form-label">WhatsApp da loja</label>
                <input type="text" name="whatsapp" id="whatsapp" data-mask="telefone" class="form-control" value="{{ old('whatsapp', $loja->opcao('whatsapp')) }}" placeholder="(00) 00000-0000">
            </div>
            <div class="col-md-4">
                <label for="instagram" class="form-label">Instagram</label>
                <input type="text" name="instagram" id="instagram" class="form-control @error('instagram') is-invalid @enderror" value="{{ old('instagram', $loja->opcao('instagram')) }}" placeholder="@minhaloja">
                @error('instagram') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="email" class="form-label">E-mail de contato</label>
                <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $loja->opcao('email')) }}">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="facebook" class="form-label">Facebook</label>
                <input type="text" name="facebook" id="facebook" class="form-control @error('facebook') is-invalid @enderror" value="{{ old('facebook', $loja->opcao('facebook')) }}" placeholder="minhaloja">
                @error('facebook') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="tiktok" class="form-label">TikTok</label>
                <input type="text" name="tiktok" id="tiktok" class="form-control @error('tiktok') is-invalid @enderror" value="{{ old('tiktok', $loja->opcao('tiktok')) }}" placeholder="@minhaloja">
                @error('tiktok') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="youtube" class="form-label">YouTube</label>
                <input type="text" name="youtube" id="youtube" class="form-control @error('youtube') is-invalid @enderror" value="{{ old('youtube', $loja->opcao('youtube')) }}" placeholder="@minhaloja">
                @error('youtube') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" name="whatsapp_flutuante" id="whatsapp_flutuante" value="1" @checked(old('whatsapp_flutuante', $loja->opcao('whatsapp_flutuante')))>
                    <label class="form-check-label" for="whatsapp_flutuante">Botão do WhatsApp flutuando no canto da loja</label>
                </div>
                <label for="whatsapp_mensagem" class="form-label">Mensagem que já vem escrita</label>
                <input type="text" name="whatsapp_mensagem" id="whatsapp_mensagem" class="form-control" maxlength="160" value="{{ old('whatsapp_mensagem', $loja->opcao('whatsapp_mensagem')) }}">
                <div class="form-text">Precisa do WhatsApp da loja preenchido acima. As redes preenchidas aparecem no rodapé e no bloco "Redes sociais".</div>
            </div>
            <div class="col-md-6">
                <label for="horario_atendimento" class="form-label">Horário de atendimento <span class="text-muted fw-normal">(opcional)</span></label>
                <input type="text" name="horario_atendimento" id="horario_atendimento" class="form-control" maxlength="120" value="{{ old('horario_atendimento', $loja->opcao('horario_atendimento')) }}" placeholder="Seg a sex, 9h às 18h · Sáb, 9h às 13h">
            </div>
            <div class="col-md-6">
                <label for="aviso_topo" class="form-label">Aviso no topo <span class="text-muted fw-normal">(opcional)</span></label>
                <input type="text" name="aviso_topo" id="aviso_topo" class="form-control" maxlength="120" value="{{ old('aviso_topo', $loja->opcao('aviso_topo')) }}" placeholder="Ex.: Frete grátis acima de R$ 200 nesta semana">
                <div class="form-text">Faixa fina acima do cabeçalho, em todas as páginas. Para ela correr como letreiro: Aparência → Cores e letras → Faixa de aviso.</div>
            </div>
            <div class="col-md-6">
                <label for="texto_rodape" class="form-label">Frase do rodapé <span class="text-muted fw-normal">(opcional)</span></label>
                <textarea name="texto_rodape" id="texto_rodape" class="form-control" rows="2" maxlength="300">{{ old('texto_rodape', $loja->opcao('texto_rodape')) }}</textarea>
            </div>
        </div>
    </x-erp.card>

    <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Salvar configurações</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    var loja = document.getElementById('unidade_id'), estoque = document.getElementById('estoque_id');
    function filtrar() {
        Array.prototype.forEach.call(estoque.options, function (o) {
            if (!o.dataset.unidade) return;
            var some = o.dataset.unidade !== loja.value;
            o.hidden = some; o.disabled = some;
            if (some && o.selected) estoque.value = '';
        });
    }
    loja.addEventListener('change', filtrar); filtrar();
})();
</script>
@endpush
