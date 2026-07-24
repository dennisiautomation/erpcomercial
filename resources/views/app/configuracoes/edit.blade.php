@extends('layouts.app')

@section('title', 'Configurações da Loja')

@section('content')
<x-erp.page-header title="Configurações da Loja" icon="sliders"
    subtitle="Como o PDV, o caixa e os preços se comportam nesta unidade" />

<div class="alert alert-info border-0 bg-info bg-opacity-10 mb-4">
    <div class="d-flex">
        <i class="bi bi-info-circle fs-5 me-2 text-info"></i>
        <div>
            <strong>Como funciona esta tela:</strong> cada opção abaixo muda um comportamento do sistema
            <u>somente nesta unidade</u>. Enquanto você não salvar nada, a loja continua operando
            exatamente como sempre operou. Pode ativar um recurso de cada vez e testar com calma.
        </div>
    </div>
</div>

<form method="POST" action="{{ route('app.configuracoes.update') }}">
    @csrf
    @method('PUT')

    {{-- ============ CAIXA E VENDAS ============ --}}
    <x-erp.form-section title="Caixa e Vendas" icon="person-badge"
        description="Quem responde pela venda e pelo dinheiro que entra no caixa">
        <div class="form-check form-switch mb-1">
            <input class="form-check-input" type="checkbox" role="switch" value="1"
                   id="vendedor_responsavel_caixa" name="vendedor_responsavel_caixa"
                   @checked(old('vendedor_responsavel_caixa', $config->vendedor_responsavel_caixa))>
            <label class="form-check-label" for="vendedor_responsavel_caixa">
                <strong>Vendedor selecionado é o responsável pela venda e pela entrada no caixa</strong>
            </label>
        </div>
        <div class="text-muted small ps-4 mb-2">
            <div class="mb-1"><i class="bi bi-toggle-off me-1"></i><strong>Desligado (padrão):</strong>
                a entrada no caixa fica registrada no nome do <em>operador logado</em> (quem está no computador do caixa).</div>
            <div class="mb-1"><i class="bi bi-toggle-on me-1"></i><strong>Ligado:</strong>
                a entrada fica registrada no nome do <em>vendedor selecionado</em> no PDV: o extrato do caixa e a
                conferência mostram quem de fato fez cada venda.</div>
            <div><i class="bi bi-lightbulb me-1"></i><em>Use quando vários vendedores atendem e um único caixa registra;
                assim cada venda "pertence" ao vendedor certo, não a quem digitou.</em></div>
        </div>
    </x-erp.form-section>

    {{-- ============ TABELAS DE PREÇO ============ --}}
    <x-erp.form-section title="Preços por Forma de Pagamento" icon="tags"
        description="Um preço para Dinheiro/PIX, outro para Débito, outro para Crédito">

        <div class="alert alert-light border mb-3">
            <strong><i class="bi bi-book me-1"></i>Entenda as 3 tabelas:</strong>
            o <strong>preço de venda cadastrado no produto</strong> é o preço à vista
            (<span class="badge bg-success">Dinheiro / PIX</span>).
            Os acréscimos abaixo calculam automaticamente os preços no
            <span class="badge bg-primary">Débito</span> e no
            <span class="badge bg-warning text-dark">Crédito</span>.
            <div class="mt-2 small text-muted">
                Exemplo: produto de <strong>R$ 300,00</strong> com acréscimo de 8% no crédito →
                PIX R$ 300,00 · Crédito R$ 324,00 · e a etiqueta sai
                "<strong>6x R$ 54,00</strong> ou <strong>R$ 300,00 no PIX</strong>".
            </div>
            <div class="mt-1 small text-muted">
                <i class="bi bi-arrow-return-right me-1"></i>Precisa de um preço diferente num produto específico?
                No cadastro do produto há os campos "Preço no Débito/Crédito", que têm prioridade sobre a regra geral.
            </div>
        </div>

        <div class="row g-3 mb-2">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Acréscimo no Débito (%)</label>
                <div class="input-group">
                    <input type="number" step="0.01" min="0" max="100" class="form-control"
                           name="percentual_debito"
                           value="{{ old('percentual_debito', $config->percentual_debito ?? 0) }}">
                    <span class="input-group-text">%</span>
                </div>
                <div class="form-text">Quanto o preço sobe quando o cliente paga no débito.
                    <strong>0 = mesmo preço do PIX.</strong></div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Acréscimo no Crédito (%)</label>
                <div class="input-group">
                    <input type="number" step="0.01" min="0" max="100" class="form-control"
                           name="percentual_credito"
                           value="{{ old('percentual_credito', $config->percentual_credito ?? 0) }}">
                    <span class="input-group-text">%</span>
                </div>
                <div class="form-text">Quanto o preço sobe no crédito (à vista ou parcelado).
                    <strong>0 = mesmo preço do PIX.</strong></div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Máximo de parcelas</label>
                <input type="number" min="1" max="24" class="form-control"
                       name="max_parcelas"
                       value="{{ old('max_parcelas', $config->max_parcelas ?? 6) }}">
                <div class="form-text">Até quantas vezes a loja parcela no crédito.
                    Aparece na etiqueta ("6x R$ ...") e na lista de parcelas do PDV.</div>
            </div>
        </div>

        <hr>

        <label class="form-label fw-semibold mb-1">
            <i class="bi bi-signpost-split me-1"></i>Pagamento dividido (split): qual tabela vale?
        </label>
        <div class="text-muted small mb-2">
            Quando o cliente paga metade no PIX e metade no cartão (por exemplo), o sistema precisa decidir
            que preço cobrar. Escolha a regra da sua loja:
        </div>
        @php $regra = old('regra_preco_split', $config->regra_preco_split ?? 'cartao_maior'); @endphp
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="regra_preco_split"
                   id="regra_cartao_maior" value="cartao_maior" @checked($regra === 'cartao_maior')>
            <label class="form-check-label" for="regra_cartao_maior">
                <strong>Vale a maior tabela entre as formas usadas</strong> <span class="badge bg-secondary">recomendado</span>
                <div class="text-muted small">
                    PIX + Crédito → cobra a tabela <strong>Crédito</strong> ·
                    PIX + Débito → cobra a tabela <strong>Débito</strong> ·
                    só PIX + Dinheiro → cobra a tabela <strong>PIX</strong> (menor).
                </div>
            </label>
        </div>
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="regra_preco_split"
                   id="regra_sempre_menor" value="sempre_menor" @checked($regra === 'sempre_menor')>
            <label class="form-check-label" for="regra_sempre_menor">
                <strong>Sempre a tabela menor (Dinheiro/PIX)</strong>
                <div class="text-muted small">Mesmo com cartão no meio, cobra o preço à vista. Bom para não complicar,
                    mas a taxa da máquina sai do seu bolso.</div>
            </label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="regra_preco_split"
                   id="regra_sempre_maior" value="sempre_maior" @checked($regra === 'sempre_maior')>
            <label class="form-check-label" for="regra_sempre_maior">
                <strong>Sempre a tabela maior (Crédito)</strong>
                <div class="text-muted small">Qualquer pagamento dividido cobra o preço do crédito. É a regra mais
                    conservadora para a loja.</div>
            </label>
        </div>
        <div class="form-text mt-2">
            <i class="bi bi-eye me-1"></i>No PDV o operador vê a mudança na hora: ao escolher a forma de pagamento,
            os preços dos itens se ajustam e aparece o aviso "Tabela: Crédito/Débito" ao lado do total.
        </div>
    </x-erp.form-section>

    {{-- ============ EMISSÃO ============ --}}
    <x-erp.form-section title="Recibo ou Cupom Fiscal na Finalização da Venda" icon="receipt"
        description="O que imprimir/emitir quando a venda fecha no PDV">

        <div class="alert alert-light border mb-3 small">
            <strong><i class="bi bi-book me-1"></i>Como o PDV decide o documento:</strong>
            <ol class="mb-1 ps-3">
                <li><strong>Escolha manual:</strong> os botões <em>Auto / Recibo / Cupom Fiscal</em> ficam sempre
                    visíveis acima do FINALIZAR. Se o operador escolher, vale a escolha dele.</li>
                <li><strong>No modo Auto</strong>, valem as regras abaixo (cartão e CPF primeiro, depois o padrão).</li>
            </ol>
            <span class="text-muted"><i class="bi bi-shield-check me-1"></i>Segurança: se a emissão da NFC-e falhar
            (SEFAZ fora do ar etc.), a venda <u>nunca trava</u>: sai o recibo e o cupom pode ser emitido depois.</span>
        </div>

        <div class="form-check form-switch mb-1">
            <input class="form-check-input" type="checkbox" role="switch" value="1"
                   id="cupom_automatico_cartao" name="cupom_automatico_cartao"
                   @checked(old('cupom_automatico_cartao', $config->cupom_automatico_cartao))>
            <label class="form-check-label" for="cupom_automatico_cartao">
                <strong>Venda no cartão emite cupom fiscal automaticamente</strong>
            </label>
        </div>
        <div class="text-muted small ps-4 mb-3">
            Qualquer venda com cartão (débito ou crédito, inclusive dividida) já sai com NFC-e emitida,
            sem o operador precisar escolher nada. <em>Recomendado: venda no cartão deixa rastro na
            operadora, e o cupom fiscal mantém tudo casado.</em>
        </div>

        <div class="form-check form-switch mb-1">
            <input class="form-check-input" type="checkbox" role="switch" value="1"
                   id="cpf_emite_fiscal" name="cpf_emite_fiscal"
                   @checked(old('cpf_emite_fiscal', $config->cpf_emite_fiscal))>
            <label class="form-check-label" for="cpf_emite_fiscal">
                <strong>Cliente pediu CPF na nota → emite cupom fiscal na hora</strong>
            </label>
        </div>
        <div class="text-muted small ps-4 mb-3">
            Quando o operador informa o cliente na venda (o famoso "CPF na nota?"), a NFC-e sai
            imediatamente com o CPF do cliente, sem depender do padrão abaixo.
        </div>

        <label class="form-label fw-semibold mb-1">Nas demais vendas (dinheiro/PIX, sem CPF), o padrão é:</label>
        @php $padrao = old('padrao_impressao', $config->padrao_impressao ?? 'recibo'); @endphp
        <div class="form-check">
            <input class="form-check-input" type="radio" name="padrao_impressao"
                   id="padrao_recibo" value="recibo" @checked($padrao === 'recibo')>
            <label class="form-check-label" for="padrao_recibo">
                <strong>Recibo</strong> <span class="text-muted small">(comprovante simples, não fiscal).
                O cupom fiscal só sai se o operador pedir.</span>
            </label>
        </div>
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="padrao_impressao"
                   id="padrao_cupom" value="cupom_fiscal" @checked($padrao === 'cupom_fiscal')>
            <label class="form-check-label" for="padrao_cupom">
                <strong>Cupom Fiscal (NFC-e)</strong> <span class="text-muted small">(toda venda sai com
                cupom fiscal emitido na SEFAZ).</span>
            </label>
        </div>

        <div class="alert alert-warning border-0 bg-warning bg-opacity-10 small mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>Pré-requisito:</strong> a emissão de NFC-e depende da
            <a href="{{ route('app.configuracao-fiscal.edit') }}">Configuração Fiscal</a> desta unidade estar
            ativa com NFC-e habilitada (certificado A1 + CSC). Sem isso, sai sempre o recibo,
            independentemente do que estiver marcado aqui.
        </div>
    </x-erp.form-section>

    {{-- ============ ONDE MAIS ISSO APARECE ============ --}}
    <x-erp.card title="Onde essas configurações aparecem no dia a dia" icon="map" class="mb-4">
        <div class="row g-3 small text-muted">
            <div class="col-md-3">
                <strong class="text-body"><i class="bi bi-upc-scan me-1"></i>PDV</strong><br>
                Preço muda conforme a forma de pagamento; botões Auto/Recibo/Cupom; parcelas do crédito.
            </div>
            <div class="col-md-3">
                <strong class="text-body"><i class="bi bi-tag me-1"></i>Etiquetas</strong><br>
                "6x R$ 54,00 ou R$ 300,00 no PIX" quando houver acréscimo no crédito.
            </div>
            <div class="col-md-3">
                <strong class="text-body"><i class="bi bi-cash-register me-1"></i>Fechamento de caixa</strong><br>
                Conferência por forma de pagamento e responsável por cada entrada.
            </div>
            <div class="col-md-3">
                <strong class="text-body"><i class="bi bi-credit-card-2-front me-1"></i>Financeiro</strong><br>
                Junto com <a href="{{ route('app.adquirentes.index') }}">Máquinas de Cartão</a>, gera a previsão
                de recebimento com taxa e valor líquido.
            </div>
        </div>
    </x-erp.card>

    <div class="d-flex justify-content-end gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-lg px-4">
            <i class="bi bi-check-lg me-1"></i> Salvar Configurações
        </button>
    </div>
</form>
@endsection
