@extends('layouts.app')

@section('title', 'Configurações da Loja')

@section('content')
<x-erp.page-header title="Configurações da Loja" icon="sliders"
    subtitle="Como o PDV, o caixa e os preços se comportam nesta unidade">
    <a href="{{ route('app.estoques.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-boxes me-1"></i> Estoques da Loja
    </a>
</x-erp.page-header>

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

    {{-- ============ ACESSO DO VENDEDOR (empresa inteira) ============ --}}
    @if($empresa)
    <x-erp.form-section title="Acesso do vendedor" icon="person-lock"
        description="O que o perfil Vendedor enxerga ao entrar no sistema">

        <div class="alert alert-warning border-0 bg-warning bg-opacity-10 mb-3">
            <i class="bi bi-building me-1"></i>
            <strong>Atenção: esta opção vale para a empresa inteira</strong> — todas as
            {{ $empresa->unidades()->where('status', 'ativa')->count() }} lojas de
            <strong>{{ $empresa->nome_fantasia ?: $empresa->razao_social }}</strong>, não só esta.
            É a única desta tela que sai do escopo da loja.

            @if(auth()->user()->isGerente())
                <div class="mt-2 pt-2 border-top border-warning border-opacity-25">
                    <i class="bi bi-person-badge me-1"></i>
                    Você é <strong>gerente</strong>: o que ligar aqui vale também para as lojas
                    que não são suas — inclusive as que não aparecem em Multilojas para você.
                </div>
            @endif
        </div>

        <div class="form-check form-switch mb-1">
            <input class="form-check-input" type="checkbox" role="switch" value="1"
                   id="vendedor_apenas_pdv" name="vendedor_apenas_pdv"
                   @checked(old('vendedor_apenas_pdv', $empresa->vendedor_apenas_pdv))
                   @disabled(! $podeMudarAcessoVendedor)>
            <label class="form-check-label" for="vendedor_apenas_pdv">
                <strong>Vendedor opera somente o PDV</strong>
            </label>
        </div>

        <div class="text-muted small ps-4 mb-2">
            <div class="mb-1"><i class="bi bi-toggle-off me-1"></i><strong>Desligado (padrão):</strong>
                o vendedor entra no Dashboard e navega pelo menu como sempre.</div>
            <div class="mb-1"><i class="bi bi-toggle-on me-1"></i><strong>Ligado:</strong>
                ao entrar, o vendedor cai <em>direto no PDV</em>. Continua vendendo, fazendo
                troca pelo F6, usando vale e abrindo/fechando o caixa — e deixa de alcançar
                Dashboard, relatórios, cadastros, estoque, financeiro, fiscal e comissões,
                inclusive digitando o endereço no navegador.</div>
            <div><i class="bi bi-lightbulb me-1"></i><em>Use quando o vendedor é balcão:
                hoje ele enxerga faturamento do mês, ticket médio, o relatório financeiro da
                loja e o preço de custo dos produtos.</em></div>
        </div>

        <div class="small ps-4">
            @if($vendedoresAtivos > 0)
                <span class="badge bg-secondary-subtle text-secondary-emphasis">
                    <i class="bi bi-people me-1"></i>
                    Alcança {{ $vendedoresAtivos }}
                    {{ $vendedoresAtivos === 1 ? 'vendedor ativo' : 'vendedores ativos' }} desta empresa
                </span>
            @else
                <span class="badge bg-secondary-subtle text-secondary-emphasis">
                    <i class="bi bi-people me-1"></i>
                    Nenhum vendedor ativo hoje — ligar agora não muda nada até cadastrar um
                </span>
            @endif
            <div class="text-muted mt-1">
                Gerente, caixa, financeiro, consulta e dono <strong>não</strong> são afetados.
            </div>
        </div>

        <hr class="my-3">

        {{-- Filtro do select de vendedor do PDV (F3) --}}
        <div class="form-check form-switch mb-1">
            <input class="form-check-input" type="checkbox" role="switch" value="1"
                   id="pdv_vendedores_da_loja" name="pdv_vendedores_da_loja"
                   @checked(old('pdv_vendedores_da_loja', $empresa->pdv_vendedores_da_loja))
                   @disabled(! $podeMudarAcessoVendedor)>
            <label class="form-check-label" for="pdv_vendedores_da_loja">
                <strong>No PDV, mostrar só os vendedores desta loja</strong>
            </label>
        </div>

        <div class="text-muted small ps-4 mb-2">
            <div class="mb-1"><i class="bi bi-toggle-off me-1"></i><strong>Desligado (padrão):</strong>
                o <strong>F3</strong> lista todos os vendedores, caixas, gerentes e o dono da
                empresa — inclusive quem trabalha em outra loja.</div>
            <div class="mb-1"><i class="bi bi-toggle-on me-1"></i><strong>Ligado:</strong>
                o F3 lista só quem está <em>vinculado à loja em que o caixa está operando</em>.
                O <strong>dono e a IA365 continuam aparecendo em qualquer loja</strong>, e quem
                não tem nenhuma loja vinculada aparece em todas (não há como saber onde está).</div>
            <div><i class="bi bi-lightbulb me-1"></i><em>Use quando cada vendedor atende numa loja
                fixa e o caixa se perde numa lista com a rede inteira.</em></div>
        </div>

        <div class="small ps-4">
            <span class="badge bg-secondary-subtle text-secondary-emphasis">
                <i class="bi bi-shop me-1"></i>
                Nesta loja: {{ $vendedoresDestaLoja }} de {{ $vendedoresAtivos }}
                {{ $vendedoresAtivos === 1 ? 'vendedor está vinculado' : 'vendedores estão vinculados' }}
            </span>
            @if($vendedoresSemVinculo > 0)
                <span class="badge bg-warning-subtle text-warning-emphasis">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    {{ $vendedoresSemVinculo }} sem loja vinculada — aparecem em todas
                </span>
            @endif
            <div class="text-muted mt-1">
                Quem some do F3 é quem não está vinculado. O vínculo se ajusta em
                <a href="{{ route('app.funcionarios.index') }}">Funcionários</a>.
            </div>
        </div>

        @unless($podeMudarAcessoVendedor)
            <div class="alert alert-light border mt-3 mb-0 small">
                <i class="bi bi-lock me-1"></i>
                Só o <strong>dono</strong>, o <strong>gerente</strong> ou a IA365 alteram estas
                duas opções — elas mudam o acesso de outro usuário e valem para todas as lojas.
                Salvar esta tela <strong>não</strong> mexe nelas.
            </div>
        @endunless
    </x-erp.form-section>
    @endif

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
            <i class="bi bi-percent me-1"></i>Juros do parcelamento no cartão de crédito
        </label>
        <div class="text-muted small mb-2">
            Quanto a venda <strong>encarece</strong> em cada quantidade de parcelas. É o mesmo formato da
            tabela que a adquirente (Stone, Cielo, PagSeguro...) manda — dá para copiar o número dela e
            somar a sua margem. <strong>Em branco ou 0 = aquela parcela não tem juros.</strong>
            <div class="mt-1">
                <i class="bi bi-arrow-return-right me-1"></i>Exemplo: numa venda de <strong>R$ 1.000,00</strong>,
                digitar <strong>8</strong> na linha do 6x faz o cliente pagar R$ 1.080,00 — 6x de R$ 180,00.
            </div>
        </div>
        <div class="text-muted small mb-3">
            <i class="bi bi-info-circle me-1"></i>Vale <strong>só para cartão de crédito parcelado</strong>.
            Dinheiro, PIX e débito nunca levam acréscimo. O <strong>crédito à vista (1x)</strong> não entra
            aqui porque quem encarece ele é o <strong>"Acréscimo no Crédito"</strong> ali em cima — é por
            isso que a tabela começa no 2x.
        </div>

        @php
            $jurosTabela = old('juros_por_parcela', $config->juros_por_parcela ?? []);
            $maxParcelas = (int) old('max_parcelas', $config->max_parcelas ?? 6);
            $acrescimoCredito = (float) old('percentual_credito', $config->percentual_credito ?? 0);
        @endphp

        {{-- Simulador: o exemplo deixa de ser texto fixo e passa a acompanhar o que a
             loja digita. Sem isso o lojista preenche "8" sem saber quanto o cliente paga. --}}
        <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
            <span class="small text-muted">Simular com uma venda de</span>
            <div class="input-group input-group-sm" style="max-width:11rem;">
                <span class="input-group-text">R$</span>
                <input type="number" step="0.01" min="0" class="form-control" id="jurosBase" value="1000">
            </div>
            <span class="small text-muted">— os valores ao lado acompanham.</span>
        </div>

        {{-- 1x somente-leitura: a linha existe para a tabela nao ter buraco, mas o numero
             vem do "Acrescimo no Credito" e nao e editavel aqui (dois donos para o mesmo
             campo seria pior do que a ausencia dele). --}}
        <div class="d-flex align-items-center flex-wrap gap-2 mb-2 px-2 py-2 rounded"
             style="background:rgba(108,117,125,.08);">
            <div class="input-group input-group-sm" style="max-width:11rem;">
                <span class="input-group-text" style="min-width:3.2rem;">1x</span>
                <input type="number" class="form-control" id="jurosCreditoAvista"
                       value="{{ number_format($acrescimoCredito, 2, '.', '') }}" disabled>
                <span class="input-group-text">%</span>
            </div>
            <span class="small text-muted flex-grow-1">
                <i class="bi bi-lock me-1"></i>Crédito à vista — vem do <strong>"Acréscimo no Crédito"</strong>
                lá em cima. Para mudar, altere aquele campo.
                <span id="previa1x" class="d-block"></span>
            </span>
        </div>

        <div class="row g-2 mb-2" id="jurosParcelasGrid">
            @for($n = 2; $n <= 24; $n++)
                @php $valorLinha = $jurosTabela[$n] ?? $jurosTabela[(string) $n] ?? ''; @endphp
                <div class="col-12 col-lg-6 juros-linha" data-parcelas="{{ $n }}"
                     @if($n > $maxParcelas) style="display:none;" @endif>
                    <div class="d-flex align-items-center gap-2 px-2 py-1 rounded juros-caixa">
                        <div class="input-group input-group-sm" style="max-width:11rem;">
                            <span class="input-group-text" style="min-width:3.2rem;">{{ $n }}x</span>
                            <input type="number" step="0.01" min="0" max="100" class="form-control juros-campo"
                                   name="juros_por_parcela[{{ $n }}]"
                                   placeholder="sem juros"
                                   value="{{ $valorLinha }}">
                            <span class="input-group-text">%</span>
                        </div>
                        <span class="small text-muted juros-previa flex-grow-1"></span>
                    </div>
                </div>
            @endfor
        </div>
        <div class="form-text mb-2">
            Campo em branco = <strong>parcela sem juros</strong>. Só aparecem as parcelas que a loja usa;
            para cadastrar mais, aumente o <strong>"Máximo de parcelas"</strong> ali em cima.
        </div>

        {{-- Aviso da SOMA, com numero real. E a resposta do "devo aplicar isso?": quem ja
             cobra acrescimo no credito nao paga so o juros da parcela. --}}
        <div id="jurosAvisoSoma" class="small mb-3 px-3 py-2 rounded" style="display:none;
             background:rgba(255,193,7,.12); border:1px solid rgba(255,193,7,.35);"></div>

        @php
            $temJuros = collect($jurosTabela)->contains(fn ($v) => (float) $v > 0);
            $mostrarParcelas = (bool) old('pdv_mostrar_valor_parcelas', $config->pdv_mostrar_valor_parcelas ?? false);
        @endphp
        <div class="form-check form-switch mb-1">
            {{-- Campo `disabled` NAO e enviado no POST: com a tabela de juros travando o
                 switch, o hidden precisa devolver o valor REAL salvo, senao salvar a tela
                 zeraria a flag sem ninguem pedir. --}}
            <input type="hidden" name="pdv_mostrar_valor_parcelas"
                   value="{{ $temJuros ? (int) $mostrarParcelas : 0 }}">
            <input class="form-check-input" type="checkbox" role="switch" value="1"
                   id="pdv_mostrar_valor_parcelas" name="pdv_mostrar_valor_parcelas"
                   @checked($mostrarParcelas || $temJuros) @disabled($temJuros)>
            <label class="form-check-label" for="pdv_mostrar_valor_parcelas">
                Mostrar o valor de cada parcela na lista do PDV
            </label>
        </div>
        <div class="form-text mb-2">
            @if($temJuros)
                <i class="bi bi-lock me-1"></i>Ligado e travado porque esta loja cobra juros: o caixa
                precisa ver <strong>"6x de R$ 180,00 · total R$ 1.080,00"</strong> para falar o valor
                certo ao cliente. Zere a tabela acima para poder desligar.
            @else
                <strong>Não muda preço nenhum</strong> — é só o texto da lista de parcelas do PDV.
                Desligado, mostra <strong>"2x", "3x"</strong>, como sempre foi. Ligado, mostra quanto dá
                cada parcela ("3x de R$ 333,33 sem juros"), mesmo sem cobrar juros.
            @endif
        </div>

        <script>
            // Espelha JurosParcelamentoService no que o lojista precisa VER enquanto
            // preenche: quanto o cliente paga. Quem grava o valor da venda continua
            // sendo o servidor — isto aqui e so a previa da tela de configuracao.
            (function () {
                const campoMax     = document.querySelector('input[name="max_parcelas"]');
                const campoCredito = document.querySelector('input[name="percentual_credito"]');
                const campoBase    = document.getElementById('jurosBase');
                const espelho1x    = document.getElementById('jurosCreditoAvista');
                const previa1x     = document.getElementById('previa1x');
                const aviso        = document.getElementById('jurosAvisoSoma');
                const linhas       = document.querySelectorAll('#jurosParcelasGrid .juros-linha');
                if (!linhas.length) return;

                const dinheiro = v => 'R$ ' + (v || 0).toLocaleString('pt-BR',
                    { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const num = el => Math.max(0, parseFloat((el && el.value) || 0) || 0);

                const render = () => {
                    const max     = parseInt((campoMax && campoMax.value) || '6', 10) || 6;
                    const base    = num(campoBase);
                    const credito = num(campoCredito);

                    // O 1x espelha o "Acrescimo no Credito": um campo, um dono.
                    if (espelho1x) espelho1x.value = credito.toFixed(2);
                    if (previa1x) {
                        previa1x.textContent = base > 0
                            ? (credito > 0
                                ? 'Hoje: ' + dinheiro(base) + ' no crédito à vista sai por '
                                  + dinheiro(base * (1 + credito / 100)) + '.'
                                : 'Hoje o crédito à vista sai pelo mesmo preço do PIX.')
                            : '';
                    }

                    let algumJuros = false;

                    linhas.forEach(linha => {
                        const n = parseInt(linha.dataset.parcelas, 10);
                        linha.style.display = n > max ? 'none' : '';

                        const campo  = linha.querySelector('.juros-campo');
                        const previa = linha.querySelector('.juros-previa');
                        const caixa  = linha.querySelector('.juros-caixa');
                        const pct    = num(campo);
                        const temJuros = pct > 0;

                        if (temJuros && n <= max) algumJuros = true;

                        // Destaque so em quem tem juros: a tabela cheia de zeros nao
                        // deixava enxergar o que estava configurado de verdade.
                        if (caixa) caixa.style.background = temJuros ? 'rgba(255,193,7,.12)' : '';

                        if (!previa) return;
                        if (base <= 0) { previa.textContent = ''; return; }

                        // A conta que o cliente ve: o acrescimo do credito ja esta no
                        // preco da tabela, e o juros da parcela vem POR CIMA dele.
                        const comCredito = base * (1 + credito / 100);
                        const total      = comCredito * (1 + pct / 100);

                        previa.innerHTML = temJuros
                            ? '<strong>' + n + 'x de ' + dinheiro(total / n) + '</strong> · total '
                              + dinheiro(total)
                            : n + 'x de ' + dinheiro(total / n) + ' · sem juros';
                    });

                    // O aviso da soma so faz sentido quando os dois estao ligados.
                    if (aviso) {
                        if (algumJuros && credito > 0 && base > 0) {
                            const exemplo = [...linhas].find(l =>
                                num(l.querySelector('.juros-campo')) > 0 &&
                                parseInt(l.dataset.parcelas, 10) <= max);
                            const n   = parseInt(exemplo.dataset.parcelas, 10);
                            const pct = num(exemplo.querySelector('.juros-campo'));
                            const soJuros = base * (1 + pct / 100);
                            const real    = base * (1 + credito / 100) * (1 + pct / 100);

                            aviso.style.display = 'block';
                            aviso.innerHTML =
                                '<i class="bi bi-exclamation-triangle me-1"></i><strong>Os dois acréscimos somam.</strong> '
                                + 'Esta loja já cobra <strong>' + credito.toFixed(2).replace('.', ',') + '%</strong> no crédito. '
                                + 'Com <strong>' + pct.toFixed(2).replace('.', ',') + '%</strong> no ' + n + 'x, uma venda de '
                                + dinheiro(base) + ' sai por <strong>' + dinheiro(real) + '</strong> — não por '
                                + dinheiro(soJuros) + '. Se a intenção era cobrar só o juros da parcela, '
                                + 'zere o "Acréscimo no Crédito" lá em cima.';
                        } else {
                            aviso.style.display = 'none';
                        }
                    }
                };

                [campoMax, campoCredito, campoBase].forEach(c => c && c.addEventListener('input', render));
                document.querySelectorAll('.juros-campo').forEach(c => c.addEventListener('input', render));
                render();
            })();
        </script>

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
        <div class="form-check mt-2">
            <input class="form-check-input" type="radio" name="regra_preco_split"
                   id="regra_por_parte" value="por_parte" @checked($regra === 'por_parte')>
            <label class="form-check-label" for="regra_por_parte">
                <strong>Cada forma paga a sua própria tabela</strong>
                <div class="text-muted small">
                    O acréscimo do cartão incide <strong>só sobre a parte paga no cartão</strong>, não sobre a
                    venda inteira.
                    @php
                        $pctCred = (float) old('percentual_credito', $config->percentual_credito ?? 0);
                        $exBase = 300; $exPix = 100; $exCartao = $exBase - $exPix;
                        $exAcrescimo = round($exCartao * $pctCred / 100, 2);
                    @endphp
                    @if($pctCred > 0)
                        <div class="mt-1">
                            Na sua loja: venda de R$ {{ number_format($exBase, 2, ',', '.') }} com
                            R$ {{ number_format($exPix, 2, ',', '.') }} no PIX e o resto no crédito
                            ({{ rtrim(rtrim(number_format($pctCred, 2, ',', '.'), '0'), ',') }}%) →
                            as outras regras cobram
                            <strong>R$ {{ number_format($exBase * (1 + $pctCred/100), 2, ',', '.') }}</strong>;
                            esta cobra <strong>R$ {{ number_format($exBase + $exAcrescimo, 2, ',', '.') }}</strong>
                            (os {{ rtrim(rtrim(number_format($pctCred, 2, ',', '.'), '0'), ',') }}% só sobre os
                            R$ {{ number_format($exCartao, 2, ',', '.') }} do cartão).
                        </div>
                    @endif
                    <div class="mt-1">
                        Aqui o preço dos itens <strong>não muda</strong> com a forma de pagamento: o cupom e a
                        nota saem com o preço da etiqueta e uma linha à parte de "Acréscimo cartão".
                        Quem devolve a peça recebe o acréscimo de volta.
                    </div>
                </div>
            </label>
        </div>

        @if(($produtosComPrecoProprio ?? 0) > 0)
            <div class="alert alert-warning border-0 bg-warning bg-opacity-10 mt-2 mb-0 small">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <strong>{{ $produtosComPrecoProprio }}</strong>
                {{ $produtosComPrecoProprio === 1 ? 'produto tem preço próprio' : 'produtos têm preço próprio' }}
                no débito/crédito (campo no cadastro do produto). Na regra <em>"cada forma paga a sua própria
                tabela"</em> esse preço fechado <strong>não é usado</strong> — vale o percentual geral acima,
                aplicado sobre o valor pago no cartão.
            </div>
        @endif

        <div class="form-text mt-2">
            <i class="bi bi-eye me-1"></i>No PDV o operador vê a mudança na hora: nas três primeiras regras os
            preços dos itens se ajustam e aparece o aviso "Tabela: Crédito/Débito" ao lado do total; na quarta,
            os preços ficam parados e o acréscimo aparece como linha própria no resumo da venda.
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

    {{-- ============ IMPRESSÃO DA ORDEM DE SERVIÇO ============ --}}
    {{-- ============ TROCAS E DEVOLUÇÕES (03/09/2026) ============ --}}
    <x-erp.form-section title="Trocas e Devoluções" icon="arrow-repeat"
        description="O que a loja faz quando o cliente traz uma peça de volta">

        <div class="text-muted small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            A troca é feita no <strong>PDV (tecla F6)</strong>: o caixa localiza a venda, marca o que volta,
            bipa o que o cliente leva e a conta fecha na hora. Aqui você define a política — o que acontece
            quando o cliente devolve <em>mais</em> do que compra, até quando a loja aceita a troca e quem
            precisa autorizar o que sai da regra.
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <label for="troca_prazo_dias" class="form-label fw-semibold">Prazo para troca (dias)</label>
                <input type="number" min="0" max="3650" step="1" name="troca_prazo_dias" id="troca_prazo_dias"
                       class="form-control @error('troca_prazo_dias') is-invalid @enderror"
                       value="{{ old('troca_prazo_dias', $config->troca_prazo_dias ?? 30) }}">
                <div class="form-text">Contado da data da venda. <strong>0</strong> = sem prazo. Passou do prazo, a troca
                    só sai com autorização do gerente (abaixo).</div>
                @error('troca_prazo_dias') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="troca_vale_validade_dias" class="form-label fw-semibold">Validade do vale (dias)</label>
                <input type="number" min="0" max="3650" step="1" name="troca_vale_validade_dias" id="troca_vale_validade_dias"
                       class="form-control @error('troca_vale_validade_dias') is-invalid @enderror"
                       value="{{ old('troca_vale_validade_dias', $config->troca_vale_validade_dias ?? 90) }}">
                <div class="form-text">Quanto tempo o crédito da troca vale para o cliente usar. <strong>0</strong> = não vence.</div>
                @error('troca_vale_validade_dias') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <hr class="my-3">

        @php $sobraAtual = old('troca_sobra', $config->troca_sobra ?? 'vale'); @endphp
        <div class="fw-semibold mb-2">Quando o cliente devolve mais do que leva, a sobra…</div>
        <div class="text-muted small mb-2">
            Exemplo: devolveu uma peça de R$ 150 e levou outra de R$ 100 — sobram <strong>R$ 50</strong> a favor dele.
        </div>
        <div class="row g-2 mb-2">
            <div class="col-md-6">
                <label class="form-check card-radio h-100 p-3 border rounded @if($sobraAtual === 'vale') border-primary bg-primary bg-opacity-10 @endif" for="troca_sobra_vale">
                    <input class="form-check-input ms-0 me-2" type="radio" name="troca_sobra" id="troca_sobra_vale" value="vale" @checked($sobraAtual === 'vale')>
                    <strong>Vira crédito na loja (vale)</strong> <span class="badge bg-secondary ms-1">padrão</span>
                    <div class="small text-muted mt-1">A loja não devolve dinheiro. Os R$ 50 viram um vale com código impresso
                        e validade, que o cliente usa numa próxima compra. É o que a maioria das lojas de roupa e semijoia faz.</div>
                </label>
            </div>
            <div class="col-md-6">
                <label class="form-check card-radio h-100 p-3 border rounded @if($sobraAtual === 'dinheiro') border-primary bg-primary bg-opacity-10 @endif" for="troca_sobra_dinheiro">
                    <input class="form-check-input ms-0 me-2" type="radio" name="troca_sobra" id="troca_sobra_dinheiro" value="dinheiro" @checked($sobraAtual === 'dinheiro')>
                    <strong>Pode ser devolvida em dinheiro</strong>
                    <div class="small text-muted mt-1">O caixa pode devolver os R$ 50 na hora pela gaveta. A saída fica registrada no
                        caixa e entra na conferência do fechamento. O caixa ainda pode escolher o vale, se o cliente preferir.</div>
                </label>
            </div>
        </div>
        @error('troca_sobra') <div class="text-danger small">{{ $message }}</div> @enderror

        <hr class="my-3">

        <div class="form-check form-switch mb-1">
            <input class="form-check-input" type="checkbox" role="switch" value="1"
                   id="troca_senha_gerente" name="troca_senha_gerente"
                   @checked(old('troca_senha_gerente', $config->troca_senha_gerente ?? true))>
            <label class="form-check-label" for="troca_senha_gerente">
                <strong>Fora da política, pedir e-mail e senha de um gerente</strong>
            </label>
        </div>
        <div class="text-muted small ps-4">
            <div class="mb-1"><i class="bi bi-toggle-on me-1"></i><strong>Ligado (padrão):</strong> troca fora do prazo e
                devolução em dinheiro só saem se um gerente ou o dono digitar e-mail e senha na tela. Quem já está logado
                como gerente/dono não precisa digitar de novo.</div>
            <div><i class="bi bi-toggle-off me-1"></i><strong>Desligado:</strong> vendedor e caixa fazem qualquer troca sem autorização.</div>
        </div>
    </x-erp.form-section>

    <x-erp.form-section title="Impressão da Ordem de Serviço" icon="wrench-adjustable"
        description="O que sai no papel quando você imprime uma OS — textos seus, desta loja">

        <div class="text-muted small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Tudo aqui é <strong>opcional</strong>. Campo em branco simplesmente não aparece na OS impressa,
            que continua saindo como sempre saiu. O que você escrever vale para
            <u>todas as OS desta loja</u>, sem precisar redigitar em cada uma.
        </div>

        <div class="mb-3">
            <label for="os_cabecalho" class="form-label fw-semibold">Texto do cabeçalho</label>
            <textarea name="os_cabecalho" id="os_cabecalho" rows="4" data-contador="5000"
                      class="form-control @error('os_cabecalho') is-invalid @enderror"
                      placeholder="Ex.: Assistência Técnica Autorizada — Atendimento de segunda a sexta, 8h às 18h">{{ old('os_cabecalho', $config->os_cabecalho) }}</textarea>
            <div class="form-text d-flex justify-content-between gap-2 flex-wrap"><span>Sai logo abaixo do nome e endereço da loja, no topo da folha.</span><span class="text-muted" data-contador-de="os_cabecalho"></span></div>
            @error('os_cabecalho') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="os_termos_garantia" class="form-label fw-semibold">Termos de garantia</label>
            <textarea name="os_termos_garantia" id="os_termos_garantia" rows="12" data-contador="15000"
                      class="form-control @error('os_termos_garantia') is-invalid @enderror"
                      placeholder="Ex.: Garantia de 90 dias sobre o serviço executado e as peças aplicadas, contados da data de entrega. A garantia não cobre mau uso, quedas, oxidação ou violação por terceiros.">{{ old('os_termos_garantia', $config->os_termos_garantia) }}</textarea>
            <div class="form-text d-flex justify-content-between gap-2 flex-wrap"><span>Sai em bloco destacado antes das assinaturas — é o que o cliente assina junto.</span><span class="text-muted" data-contador-de="os_termos_garantia"></span></div>
            @error('os_termos_garantia') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="os_texto_legal" class="form-label fw-semibold">Texto legal / observações fixas</label>
            <textarea name="os_texto_legal" id="os_texto_legal" rows="8" data-contador="15000"
                      class="form-control @error('os_texto_legal') is-invalid @enderror"
                      placeholder="Ex.: Equipamentos não retirados em até 90 dias após o aviso de conclusão poderão ser vendidos para custeio do serviço (art. 1.275 CC).">{{ old('os_texto_legal', $config->os_texto_legal) }}</textarea>
            <div class="form-text d-flex justify-content-between gap-2 flex-wrap"><span>Para prazo de retirada, LGPD, ou qualquer aviso que precise constar sempre.</span><span class="text-muted" data-contador-de="os_texto_legal"></span></div>
            @error('os_texto_legal') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-4">
            <label for="os_rodape" class="form-label fw-semibold">Rodapé</label>
            <textarea name="os_rodape" id="os_rodape" rows="4" data-contador="5000"
                      class="form-control @error('os_rodape') is-invalid @enderror"
                      placeholder="Ex.: Dúvidas? (11) 4002-8922 — contato@minhaloja.com.br">{{ old('os_rodape', $config->os_rodape) }}</textarea>
            <div class="form-text d-flex justify-content-between gap-2 flex-wrap"><span>Última linha da folha. Em branco, sai a data de emissão de sempre.</span><span class="text-muted" data-contador-de="os_rodape"></span></div>
            @error('os_rodape') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="fw-semibold mb-2"><i class="bi bi-eye me-1"></i> Blocos que aparecem na OS impressa</div>
        <div class="text-muted small mb-2">Todos ligados por padrão. Desligue o que a sua loja não usa.</div>

        <div class="form-check form-switch mb-1">
            <input class="form-check-input" type="checkbox" role="switch" value="1"
                   id="os_mostrar_assinatura" name="os_mostrar_assinatura"
                   @checked(old('os_mostrar_assinatura', $config->os_mostrar_assinatura))>
            <label class="form-check-label" for="os_mostrar_assinatura">
                Linhas de <strong>assinatura</strong> (cliente e responsável técnico)
            </label>
        </div>
        <div class="form-check form-switch mb-1">
            <input class="form-check-input" type="checkbox" role="switch" value="1"
                   id="os_mostrar_laudo" name="os_mostrar_laudo"
                   @checked(old('os_mostrar_laudo', $config->os_mostrar_laudo))>
            <label class="form-check-label" for="os_mostrar_laudo">
                <strong>Laudo técnico</strong>
            </label>
        </div>
        <div class="form-check form-switch mb-1">
            <input class="form-check-input" type="checkbox" role="switch" value="1"
                   id="os_mostrar_valores" name="os_mostrar_valores"
                   @checked(old('os_mostrar_valores', $config->os_mostrar_valores))>
            <label class="form-check-label" for="os_mostrar_valores">
                <strong>Valores</strong> (itens, desconto e total)
            </label>
        </div>
        <div class="text-muted small ps-4 mt-1">
            <i class="bi bi-lightbulb me-1"></i>
            <em>Desligar "Valores" é útil na via que fica com o técnico, ou quando a OS de entrada
            é impressa antes de o orçamento estar fechado.</em>
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

        <script>
            // Contador de caracteres dos textos da OS. Existe porque a Realiza Phone
            // reportou "está limitado a 500" numa tela que aceitava 5.000 e não dizia
            // nada: sem número na tela, o lojista escreve, o texto rola pra fora do
            // campo e ele conclui que bateu no teto. O limite real é o do servidor
            // (data-contador), então os dois nunca divergem.
            (function () {
                document.querySelectorAll('textarea[data-contador]').forEach(function (campo) {
                    const limite = parseInt(campo.dataset.contador, 10);
                    const saida  = document.querySelector('[data-contador-de="' + campo.name + '"]');
                    if (!saida) return;

                    const fmt = n => n.toLocaleString('pt-BR');

                    const render = function () {
                        const usado = campo.value.length;
                        saida.textContent = fmt(usado) + ' de ' + fmt(limite) + ' caracteres';
                        // Só muda de cor quando encosta de verdade — contador vermelho
                        // em campo vazio assusta sem motivo.
                        saida.classList.toggle('text-danger', usado > limite);
                        saida.classList.toggle('text-warning', usado <= limite && usado > limite * 0.9);
                        saida.classList.toggle('text-muted',   usado <= limite * 0.9);
                    };

                    campo.addEventListener('input', render);
                    render();
                });
            })();
        </script>

        <button type="submit" class="btn btn-primary btn-lg px-4">
            <i class="bi bi-check-lg me-1"></i> Salvar Configurações
        </button>
    </div>
</form>
@endsection
