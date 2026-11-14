@extends('layouts.app')

@section('title', 'Loja online — Promoções')

@section('content')
<x-erp.page-header title="Loja online" icon="globe2" subtitle="Promoções: preço, cupom, frete grátis e popup">
    <a href="{{ $loja->enderecoInterno('produtos?promocao=1') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-up-right me-1"></i> Ver a página de ofertas</a>
</x-erp.page-header>

@include('app.loja-online._abas')

<x-erp.card title="Promoção de preço" icon="percent" class="mb-3">
    <div class="d-flex flex-wrap align-items-center gap-3">
        <p class="mb-0 flex-grow-1">
            O preço promocional é o mesmo do ERP: uma promoção criada em <strong>Cadastros → Promoções</strong> para a loja de origem aparece na loja online
            com o preço antigo riscado, o selo de desconto, o link <strong>Ofertas</strong> no menu e a fileira "Em promoção".
            <span class="d-block text-muted small mt-1">Hoje: {{ count($promocoesErp) }} produto(s) em promoção na loja de origem.</span>
        </p>
        @if($podePromocaoErp)<a href="{{ route('app.promocoes.index') }}" class="btn btn-outline-primary"><i class="bi bi-percent me-1"></i> Abrir Promoções</a>@endif
    </div>
</x-erp.card>

<form method="POST" action="{{ route('app.loja-online.secao.salvar', 'promocoes') }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    <x-erp.card title="Frete grátis" icon="truck" class="mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="frete_gratis_acima" class="form-label">Frete grátis em compras a partir de (R$)</label>
                <input type="text" inputmode="decimal" name="frete_gratis_acima" id="frete_gratis_acima" class="form-control @error('frete_gratis_acima') is-invalid @enderror" value="{{ old('frete_gratis_acima', number_format((float) $loja->opcao('frete_gratis_acima'), 2, '.', '')) }}">
                @error('frete_gratis_acima') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-8"><div class="form-text mb-2">0 = desligado. O cliente vê no carrinho quanto falta. O frete continua sendo cotado e contratado normalmente — <strong>o custo fica por conta da loja</strong> e aparece nas observações do pedido.</div></div>
        </div>
    </x-erp.card>

    <x-erp.card title="Popup" icon="window-stack" class="mb-3">
        <div class="row g-3">
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="popup_ativo" id="popup_ativo" value="1" @checked(old('popup_ativo', $loja->opcao('popup_ativo')))>
                    <label class="form-check-label fw-semibold" for="popup_ativo">Mostrar um popup para quem visita a loja</label>
                </div>
            </div>
            <div class="col-md-6">
                <label for="popup_titulo" class="form-label">Título</label>
                <input type="text" name="popup_titulo" id="popup_titulo" maxlength="80" class="form-control @error('popup_titulo') is-invalid @enderror" value="{{ old('popup_titulo', $loja->opcao('popup_titulo')) }}" placeholder="Ex.: 10% na primeira compra">
                @error('popup_titulo') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label for="popup_texto" class="form-label">Texto <span class="text-muted fw-normal">(opcional)</span></label>
                <input type="text" name="popup_texto" id="popup_texto" maxlength="240" class="form-control" value="{{ old('popup_texto', $loja->opcao('popup_texto')) }}">
            </div>
            <div class="col-md-4">
                <label for="popup_cupom" class="form-label">Cupom que o popup entrega <span class="text-muted fw-normal">(opcional)</span></label>
                <input type="text" name="popup_cupom" id="popup_cupom" maxlength="30" class="form-control text-uppercase @error('popup_cupom') is-invalid @enderror" value="{{ old('popup_cupom', $loja->opcao('popup_cupom')) }}">
                @error('popup_cupom') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="popup_botao" class="form-label">Texto do botão</label>
                <input type="text" name="popup_botao" id="popup_botao" maxlength="40" class="form-control" value="{{ old('popup_botao', $loja->opcao('popup_botao')) }}" placeholder="Ver ofertas">
            </div>
            <div class="col-md-4">
                <label for="popup_link" class="form-label">O botão leva para</label>
                <select name="popup_link" id="popup_link" class="form-select">
                    @foreach(['produtos' => 'Todos os produtos', 'produtos?promocao=1' => 'Ofertas', 'carrinho' => 'Carrinho'] as $v => $r)
                        <option value="{{ $v }}" @selected(old('popup_link', $loja->opcao('popup_link')) === $v)>{{ $r }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="popup_capturar" id="popup_capturar" value="1" @checked(old('popup_capturar', $loja->opcao('popup_capturar')))>
                    <label class="form-check-label" for="popup_capturar">Pedir o e-mail do visitante <span class="text-muted">(o cupom só aparece depois que ele se cadastra; o contato entra na lista abaixo, com o aceite)</span></label>
                </div>
            </div>
            <div class="col-md-4">
                <label for="popup_gatilho" class="form-label">Quando aparece</label>
                <select name="popup_gatilho" id="popup_gatilho" class="form-select">
                    @foreach(['tempo' => 'Depois de alguns segundos', 'entrar' => 'Logo que a pessoa entra', 'saida' => 'Quando a pessoa vai sair (no computador)'] as $v => $r)
                        <option value="{{ $v }}" @selected(old('popup_gatilho', $loja->opcao('popup_gatilho')) === $v)>{{ $r }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label for="popup_segundos" class="form-label">Segundos de espera</label>
                <input type="number" name="popup_segundos" id="popup_segundos" min="1" max="120" class="form-control" value="{{ old('popup_segundos', $loja->opcao('popup_segundos')) }}">
            </div>
            <div class="col-md-4">
                <label for="popup_dias" class="form-label">Mostrar de novo depois de (dias)</label>
                <input type="number" name="popup_dias" id="popup_dias" min="1" max="90" class="form-control" value="{{ old('popup_dias', $loja->opcao('popup_dias')) }}">
                <div class="form-text">Quem já viu não vê de novo antes disso.</div>
            </div>
            <div class="col-md-8">
                <label for="popup_imagem" class="form-label">Imagem <span class="text-muted fw-normal">(opcional)</span></label>
                <input type="file" name="popup_imagem" id="popup_imagem" accept="image/png,image/jpeg,image/webp" class="form-control @error('popup_imagem') is-invalid @enderror">
                @error('popup_imagem') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @if(filled($loja->opcao('popup_imagem')))
                    <div class="d-flex align-items-center gap-3 mt-2">
                        <img src="{{ Storage::url($loja->opcao('popup_imagem')) }}" class="rounded border" style="height:64px" alt="Imagem atual do popup">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="remover_popup_imagem" id="remover_popup_imagem" value="1"><label class="form-check-label" for="remover_popup_imagem">Tirar a imagem</label></div>
                    </div>
                @endif
            </div>
        </div>
        <div class="form-text mt-2">O popup não aparece nas telas de fechamento e de pagamento, nem por cima do aviso de cookies.</div>
    </x-erp.card>
    <div class="d-flex justify-content-end mb-4"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Salvar frete grátis e popup</button></div>
</form>

<x-erp.card title="Cupons de desconto" icon="ticket-perforated" class="mb-3">
    <form method="POST" action="{{ route('app.loja-online.cupons.criar') }}" class="row g-2 align-items-end mb-3" id="formCupom">
        @csrf
        <div class="col-md-2">
            <label for="cupom_codigo" class="form-label small fw-semibold">Código</label>
            <input type="text" name="codigo" id="cupom_codigo" maxlength="30" class="form-control text-uppercase @error('codigo') is-invalid @enderror" value="{{ old('codigo') }}" placeholder="BEMVINDO10" required>
            @error('codigo') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-2">
            <label for="cupom_tipo" class="form-label small fw-semibold">Dá</label>
            <select name="tipo" id="cupom_tipo" class="form-select">
                @foreach(\App\Models\LojaCupom::TIPOS as $v => $r)<option value="{{ $v }}" @selected(old('tipo') === $v)>{{ $r }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label for="cupom_valor" class="form-label small fw-semibold">Valor</label>
            <input type="text" inputmode="decimal" name="valor" id="cupom_valor" class="form-control @error('valor') is-invalid @enderror" value="{{ old('valor') }}" placeholder="10">
            @error('valor') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-2">
            <label for="cupom_minimo" class="form-label small fw-semibold">Compra mínima (R$)</label>
            <input type="text" inputmode="decimal" name="minimo" id="cupom_minimo" class="form-control" value="{{ old('minimo') }}" placeholder="0">
        </div>
        <div class="col-md-2">
            <label for="cupom_fim" class="form-label small fw-semibold">Vale até</label>
            <input type="date" name="fim" id="cupom_fim" class="form-control @error('fim') is-invalid @enderror" value="{{ old('fim') }}">
            @error('fim') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-1">
            <label for="cupom_limite" class="form-label small fw-semibold">Usos</label>
            <input type="number" name="limite_usos" id="cupom_limite" min="1" class="form-control" value="{{ old('limite_usos') }}" placeholder="∞">
        </div>
        <div class="col-md-1"><button type="submit" class="btn btn-primary w-100" title="Criar cupom"><i class="bi bi-plus-lg"></i><span class="visually-hidden">Criar cupom</span></button></div>
    </form>
    @if($cupons->isEmpty())
        <x-erp.empty-state title="Nenhum cupom ainda" icon="ticket-perforated" description="Crie um código acima. O cliente digita no carrinho." />
    @else
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Código</th><th>Dá</th><th>Compra mínima</th><th>Vale até</th><th>Usos</th><th>Situação</th><th></th></tr></thead>
                <tbody>
                @foreach($cupons as $c)
                    <tr>
                        <td class="fw-semibold">{{ $c->codigo }}</td>
                        <td>{{ $c->descricao() }}</td>
                        <td>{{ (float) $c->minimo > 0 ? 'R$ ' . number_format((float) $c->minimo, 2, ',', '.') : '—' }}</td>
                        <td>{{ $c->fim?->format('d/m/Y') ?? 'sem prazo' }}</td>
                        <td>{{ $c->usos }}{{ $c->limite_usos ? ' de ' . $c->limite_usos : '' }}</td>
                        <td><span class="badge {{ $c->situacao() === 'Valendo' ? 'bg-success' : 'bg-secondary' }}">{{ $c->situacao() }}</span></td>
                        <td class="text-end text-nowrap">
                            <form method="POST" action="{{ route('app.loja-online.cupons.alternar', $c->id) }}" class="d-inline">@csrf @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ $c->ativo ? 'Desligar' : 'Ligar' }}</button></form>
                            <form method="POST" action="{{ route('app.loja-online.cupons.excluir', $c->id) }}" class="d-inline" data-confirm="Excluir o cupom {{ $c->codigo }}? Os pedidos que já usaram continuam com o desconto.">@csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i><span class="visually-hidden">Excluir</span></button></form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-erp.card>

<x-erp.card title="Contatos que pediram novidades" icon="people">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <span class="text-muted small">{{ $totalContatos }} contato(s), vindos do popup, do bloco "Receber novidades" e do fechamento do pedido — todos com a data do aceite. O ERP <strong>não envia</strong> campanha: baixe a planilha e use na sua ferramenta de e-mail.</span>
        @if($totalContatos)<a href="{{ route('app.loja-online.contatos.exportar') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-excel me-1"></i> Baixar planilha</a>@endif
    </div>
    @if($contatos->isEmpty())
        <x-erp.empty-state title="Nenhum contato ainda" icon="people" description="Ligue o popup com 'pedir o e-mail' ou ponha o bloco 'Receber novidades' na página inicial." />
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>E-mail</th><th>Nome</th><th>WhatsApp</th><th>De onde veio</th><th>Aceitou em</th></tr></thead>
                <tbody>@foreach($contatos as $c)<tr><td>{{ $c->email ?? '—' }}</td><td>{{ $c->nome ?? '—' }}</td><td>{{ $c->whatsapp ?? '—' }}</td>
                    <td>{{ ['popup' => 'Popup', 'newsletter' => 'Bloco de novidades', 'checkout' => 'Fechamento do pedido'][$c->origem] ?? $c->origem }}</td><td>{{ $c->aceitou_em?->format('d/m/Y H:i') }}</td></tr>@endforeach</tbody>
            </table>
        </div>
    @endif
</x-erp.card>
@endsection

@push('scripts')
<script>
(function () {
    var tipo = document.getElementById('cupom_tipo'), valor = document.getElementById('cupom_valor');
    function sinc() { var frete = tipo.value === 'frete_gratis'; valor.disabled = frete; valor.placeholder = frete ? '—' : (tipo.value === 'percentual' ? '10 (%)' : '20,00'); if (frete) valor.value = ''; }
    tipo.addEventListener('change', sinc); sinc();
})();
</script>
@endpush
