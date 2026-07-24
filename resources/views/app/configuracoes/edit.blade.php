@extends('layouts.app')

@section('title', 'Configurações da Loja')

@section('content')
<x-erp.page-header title="Configurações da Loja" icon="sliders"
    subtitle="Parametrização operacional do PDV, caixa e preços — por unidade" />

<form method="POST" action="{{ route('app.configuracoes.update') }}">
    @csrf
    @method('PUT')

    <x-erp.form-section title="Caixa e Vendas" icon="cash-register"
        description="Comportamento do PDV e do caixa">
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" value="1"
                   id="vendedor_responsavel_caixa" name="vendedor_responsavel_caixa"
                   @checked(old('vendedor_responsavel_caixa', $config->vendedor_responsavel_caixa))>
            <label class="form-check-label" for="vendedor_responsavel_caixa">
                <strong>Vendedor responsável pela venda e pelo caixa</strong>
            </label>
            <div class="text-muted small">
                O vendedor selecionado no PDV passa a ser o responsável pela venda e pela
                entrada do dinheiro no caixa (aparece no extrato do caixa e nos relatórios).
            </div>
        </div>
    </x-erp.form-section>

    <x-erp.form-section title="Tabelas de Preço por Forma de Pagamento" icon="tags"
        description="Regra geral — cada produto pode ter preços próprios no cadastro">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Acréscimo no Débito (%)</label>
                <input type="number" step="0.01" min="0" max="100" class="form-control"
                       name="percentual_debito"
                       value="{{ old('percentual_debito', $config->percentual_debito ?? 0) }}">
                <div class="form-text">Sobre o preço base (Dinheiro/PIX). 0 = mesmo preço.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Acréscimo no Crédito (%)</label>
                <input type="number" step="0.01" min="0" max="100" class="form-control"
                       name="percentual_credito"
                       value="{{ old('percentual_credito', $config->percentual_credito ?? 0) }}">
                <div class="form-text">Sobre o preço base (Dinheiro/PIX). 0 = mesmo preço.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Máximo de parcelas</label>
                <input type="number" min="1" max="24" class="form-control"
                       name="max_parcelas"
                       value="{{ old('max_parcelas', $config->max_parcelas ?? 6) }}">
                <div class="form-text">Usado na etiqueta ("6x R$ ...") e no PDV.</div>
            </div>
        </div>

        <hr>

        <label class="form-label"><strong>Preço em pagamento dividido (split)</strong></label>
        @php $regra = old('regra_preco_split', $config->regra_preco_split ?? 'cartao_maior'); @endphp
        <div class="form-check">
            <input class="form-check-input" type="radio" name="regra_preco_split"
                   id="regra_cartao_maior" value="cartao_maior" @checked($regra === 'cartao_maior')>
            <label class="form-check-label" for="regra_cartao_maior">
                <strong>Se envolver cartão, usa a tabela maior</strong> —
                só dinheiro/PIX usa a tabela menor <span class="badge bg-secondary">recomendado</span>
            </label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="regra_preco_split"
                   id="regra_sempre_menor" value="sempre_menor" @checked($regra === 'sempre_menor')>
            <label class="form-check-label" for="regra_sempre_menor">
                Sempre usa a tabela <strong>menor</strong> (Dinheiro/PIX), mesmo com cartão no split
            </label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="regra_preco_split"
                   id="regra_sempre_maior" value="sempre_maior" @checked($regra === 'sempre_maior')>
            <label class="form-check-label" for="regra_sempre_maior">
                Sempre usa a tabela <strong>maior</strong> (Crédito) quando houver split
            </label>
        </div>
    </x-erp.form-section>

    <x-erp.form-section title="Emissão na Finalização da Venda" icon="receipt"
        description="Recibo × cupom fiscal (NFC-e) no PDV — a troca manual continua sempre disponível">
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" value="1"
                   id="cupom_automatico_cartao" name="cupom_automatico_cartao"
                   @checked(old('cupom_automatico_cartao', $config->cupom_automatico_cartao))>
            <label class="form-check-label" for="cupom_automatico_cartao">
                <strong>Cartão emite cupom fiscal automaticamente</strong>
            </label>
            <div class="text-muted small">
                Venda com cartão (débito/crédito, inclusive split) sempre emite NFC-e,
                independentemente do padrão abaixo.
            </div>
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" value="1"
                   id="cpf_emite_fiscal" name="cpf_emite_fiscal"
                   @checked(old('cpf_emite_fiscal', $config->cpf_emite_fiscal))>
            <label class="form-check-label" for="cpf_emite_fiscal">
                <strong>CPF na nota emite cupom fiscal na hora</strong>
            </label>
            <div class="text-muted small">
                Quando o cliente informa o CPF na venda, a NFC-e é emitida imediatamente.
            </div>
        </div>

        <label class="form-label"><strong>Padrão de impressão nas demais vendas</strong></label>
        @php $padrao = old('padrao_impressao', $config->padrao_impressao ?? 'recibo'); @endphp
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="padrao_impressao"
                   id="padrao_recibo" value="recibo" @checked($padrao === 'recibo')>
            <label class="form-check-label" for="padrao_recibo">Recibo (não fiscal)</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="padrao_impressao"
                   id="padrao_cupom" value="cupom_fiscal" @checked($padrao === 'cupom_fiscal')>
            <label class="form-check-label" for="padrao_cupom">Cupom fiscal (NFC-e)</label>
        </div>
        <div class="form-text">
            A emissão de NFC-e ainda depende da <a href="{{ route('app.configuracao-fiscal.edit') }}">Configuração Fiscal</a>
            da unidade estar ativa com NFC-e habilitada. Sem fiscal ativo, sai sempre o recibo.
        </div>
    </x-erp.form-section>

    <div class="d-flex justify-content-end gap-2 mb-4">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> Salvar Configurações
        </button>
    </div>
</form>
@endsection
