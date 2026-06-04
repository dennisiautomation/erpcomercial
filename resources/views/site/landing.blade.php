<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>ERP Comercial IA365 · Sua loja vende, o sistema emite a nota</title>
<meta name="description" content="ERP para micro, pequenas e médias empresas: emite NF-e, NFC-e e NFS-e automaticamente, controla PDV, estoque, financeiro e várias lojas em um só lugar. Integração oficial Focus NFe.">
<meta property="og:title" content="ERP Comercial IA365">
<meta property="og:description" content="Venda no PDV, controle estoque e financeiro, e deixe a emissão fiscal no automático. Feito para PMEs brasileiras.">
<meta property="og:type" content="website">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%234f46e5'/%3E%3Ctext x='16' y='22' font-family='Arial' font-size='16' font-weight='bold' fill='white' text-anchor='middle'%3EI%3C/text%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Hanken+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('site/styles.css') }}?v={{ @filemtime(public_path('site/styles.css')) ?: '1' }}">
</head>
<body>
<a class="skip" href="#main">Pular para o conteúdo</a>

<!-- NAV -->
<header class="nav" id="nav">
  <div class="wrap nav__in">
    <a class="brand" href="#" aria-label="ERP Comercial IA365, ir para o início">
      <span class="brand__mark"><span>IA</span></span>
      ERP Comercial <small>· IA365</small>
    </a>
    <nav class="nav__links" aria-label="Seções">
      <a href="#recursos">Recursos</a>
      <a href="#fiscal">Nota fiscal</a>
      <a href="#modulos">Módulos</a>
      <a href="#planos">Planos</a>
    </nav>
    <div class="nav__cta">
      <a class="btn btn-ghost" href="#demo">Falar com a gente</a>
      <a class="btn btn-primary" href="#demo">Agendar demonstração</a>
    </div>
  </div>
</header>

<main id="main">

<!-- HERO -->
<section class="hero">
  <div class="wrap hero__grid">
    <div class="hero__copy">
      <span class="eyebrow reveal" style="margin-bottom:1.25rem">Integração oficial Focus NFe</span>
      <h1 class="reveal d1">Sua loja vende. O sistema <span class="hl">emite a nota</span> sozinho.</h1>
      <p class="hero__sub reveal d2">Um ERP para micro, pequenas e médias empresas que cuida da parte chata: nota fiscal, estoque, caixa e financeiro de todas as suas lojas, em um lugar só.</p>
      <div class="hero__actions reveal d2">
        <a class="btn btn-primary" href="#demo">
          Agendar demonstração
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
        <a class="btn btn-ghost" href="#recursos">Ver o que faz</a>
      </div>
      <p class="hero__note reveal d3">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        NF-e, NFC-e e NFS-e no automático, sem você decorar CFOP nem CST.
      </p>
    </div>

    <!-- Imagery: cena de UI real do produto -->
    <div class="hero__media reveal d2">
      <div class="mock-stage">
        <div class="app" role="img" aria-label="Painel do ERP mostrando faturamento do dia, vendas e gráfico, com uma nota fiscal recém autorizada.">
          <div class="app__bar">
            <span class="app__dot"></span><span class="app__dot"></span><span class="app__dot"></span>
            <span class="app__url">erp.ia365.com.br/app</span>
          </div>
          <div class="app__body">
            <aside class="app__side">
              <div class="brand"><span class="brand__mark"><span>IA</span></span> ERP</div>
              <nav class="app__nav">
                <span class="on"><i></i> Painel</span>
                <span><i></i> PDV</span>
                <span><i></i> Vendas</span>
                <span><i></i> Estoque</span>
                <span><i></i> Financeiro</span>
                <span><i></i> Fiscal</span>
              </nav>
            </aside>
            <div class="app__main">
              <div class="app__h"><b>Loja Centro · Hoje</b><span class="mono" style="font-size:.7rem;color:var(--ink-muted)">04/06</span></div>
              <div class="kpis">
                <div class="kpi"><small>Faturamento</small><b>R$ 8.430</b><span class="up">▲ 12%</span></div>
                <div class="kpi"><small>Vendas</small><b>47</b><span class="up">▲ 6</span></div>
                <div class="kpi"><small>Notas</small><b>47/47</b><span class="up">autorizadas</span></div>
              </div>
              <div class="chart" aria-hidden="true">
                <i style="height:38%"></i><i style="height:55%"></i><i style="height:42%"></i><i style="height:70%"></i><i class="hi" style="height:88%"></i><i style="height:62%"></i><i style="height:78%"></i><i style="height:50%"></i><i style="height:66%"></i>
              </div>
            </div>
          </div>
        </div>
        <div class="toast-fiscal" role="status">
          <span class="ic">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
          </span>
          <div>
            <b>NFC-e autorizada</b>
            <small>Venda #2041 · R$ 189,90</small>
            <div class="chave">3526 0412 3456 7890 ··· 5501</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- TRUST -->
<section class="trust">
  <div class="wrap trust__in">
    <span class="trust__label">Tudo que o fisco pede, resolvido:</span>
    <div class="trust__items">
      <span class="pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg> NF-e</span>
      <span class="pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 10h8M8 14h5"/></svg> NFC-e (cupom)</span>
      <span class="pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg> NFS-e (serviços)</span>
      <span class="pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Certificado A1</span>
      <span class="pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-6l-2-2H5a2 2 0 0 0-2 2z"/></svg> Backup de XML</span>
    </div>
  </div>
</section>

<!-- RECURSOS / FEATURES -->
<section class="section" id="recursos">
  <div class="wrap">
    <div class="head">
      <h2>O dia da loja, do balcão ao fechamento do caixa</h2>
      <p class="lede">Cada parte conversa com a outra. Vendeu no PDV, o estoque baixa, a nota sai e o financeiro registra. Sem digitar a mesma coisa três vezes.</p>
    </div>

    <!-- Feature 1: PDV -->
    <div class="feature reveal">
      <div class="feature__copy">
        <h3>PDV de tela cheia, com atalhos de teclado</h3>
        <p>O caixa trabalha rápido: leitura de código de barras, busca por produto, desconto, pagamento dividido e troco. Funciona do jeito que o operador espera.</p>
        <ul class="checks">
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>Atalhos <b>F1 a F12</b> e pagamento dividido em várias formas.</span></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>Confere o estoque na hora e avisa se o produto está zerado.</span></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>Fechou a venda, o cupom imprime e a <b>NFC-e</b> sai junto.</span></li>
        </ul>
      </div>
      <div class="feature__media">
        <div class="pdv" role="img" aria-label="Tela do PDV com dois itens, total de R$ 189,90 e teclas de pagamento.">
          <div class="pdv__top"><span>PDV · Loja Centro</span><span class="badge">F8 finalizar</span></div>
          <div class="pdv__items">
            <div class="pdv__row"><span>Camiseta básica <small>2 un · R$ 49,90</small></span><b>99,80</b></div>
            <div class="pdv__row"><span>Tênis runner <small>1 un · R$ 90,10</small></span><b>90,10</b></div>
          </div>
          <div class="pdv__total"><span>Total a pagar</span><b>R$ 189,90</b></div>
          <div class="pdv__pay">
            <span class="pdv__key">Dinheiro</span>
            <span class="pdv__key">Pix</span>
            <span class="pdv__key">Cartão</span>
            <span class="pdv__key">Desconto</span>
            <span class="pdv__key">Cliente</span>
            <span class="pdv__key go">Finalizar</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Feature 2: Financeiro -->
    <div class="feature flip reveal">
      <div class="feature__copy">
        <h3>Financeiro que mostra para onde o dinheiro vai</h3>
        <p>Contas a pagar e a receber com parcelas, fluxo de caixa em gráfico, conciliação do extrato do banco e DRE por loja ou consolidado. O que você precisa para decidir, na tela.</p>
        <ul class="checks">
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>Contas a pagar e receber, com parcelas e baixa.</span></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>Conciliação bancária por arquivo <b>OFX</b> e contratos recorrentes.</span></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>DRE e fluxo de caixa por unidade ou da empresa toda.</span></li>
        </ul>
      </div>
      <div class="feature__media">
        <div class="panel" role="img" aria-label="Painel financeiro com fluxo de caixa por categoria.">
          <div class="panel__head"><b>Fluxo de caixa · Junho</b><span class="tag">consolidado</span></div>
          <div class="rows">
            <div class="rows__r"><span>Vendas no balcão <span class="u">entradas</span></span><span class="bar"><i class="ok" style="width:86%"></i></span><span style="font-variant-numeric:tabular-nums">R$ 62.140</span></div>
            <div class="rows__r"><span>Serviços <span class="u">entradas</span></span><span class="bar"><i class="ok" style="width:48%"></i></span><span style="font-variant-numeric:tabular-nums">R$ 18.900</span></div>
            <div class="rows__r"><span>Fornecedores <span class="u">saídas</span></span><span class="bar"><i style="width:60%"></i></span><span style="font-variant-numeric:tabular-nums">R$ 27.430</span></div>
            <div class="rows__r"><span>Folha e despesas <span class="u">saídas</span></span><span class="bar"><i style="width:33%"></i></span><span style="font-variant-numeric:tabular-nums">R$ 14.220</span></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Feature 3: Multi-loja / estoque -->
    <div class="feature reveal">
      <div class="feature__copy">
        <h3>Várias lojas, um estoque sob controle</h3>
        <p>Cada unidade tem caixa, estoque e fiscal próprios. Quando o produto acaba em uma loja, o PDV pode vender do estoque da outra e o sistema registra a transferência. Você escolhe a regra.</p>
        <ul class="checks">
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>Veja o saldo de cada loja e transfira com aprovação.</span></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>Movimentações de entrada, saída, ajuste e transferência.</span></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>Etiquetas com código de barras, prontas para imprimir.</span></li>
        </ul>
      </div>
      <div class="feature__media">
        <div class="panel" role="img" aria-label="Estoque do mesmo produto em três lojas, uma delas com saldo baixo.">
          <div class="panel__head"><b>Tênis runner 42</b><span class="tag">estoque por loja</span></div>
          <div class="rows">
            <div class="rows__r"><span>Loja Centro <span class="u">unidade atual</span></span><span class="bar"><i class="ok" style="width:80%"></i></span><span class="tagk ok">24 un</span></div>
            <div class="rows__r"><span>Loja Shopping</span><span class="bar"><i style="width:50%"></i></span><span class="tagk ok">12 un</span></div>
            <div class="rows__r"><span>Loja Bairro</span><span class="bar"><i class="low" style="width:12%"></i></span><span class="tagk low">2 un · baixo</span></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FISCAL (banda escura, o herói) -->
<section class="section fiscal" id="fiscal">
  <div class="wrap">
    <div class="head">
      <span class="eyebrow ok reveal" style="margin-bottom:1.25rem">A parte que tira o sono, resolvida</span>
      <h2 class="reveal">A nota fiscal sai certa, sem você ser contador</h2>
      <p class="lede reveal d1">Integração completa com a Focus NFe. O sistema preenche imposto, calcula o que precisa e fala direto com a SEFAZ. Você só vende.</p>
    </div>

    <div class="fcards">
      <div class="fcard green reveal">
        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></div>
        <h3>Emite tudo, no automático</h3>
        <p>Cupom no balcão, nota de produto para entrega e nota de serviço. O imposto é calculado por trás, conforme o seu regime tributário.</p>
        <div class="types"><span class="ftag">NF-e</span><span class="ftag">NFC-e</span><span class="ftag">NFS-e</span></div>
      </div>
      <div class="fcard reveal d1">
        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></div>
        <h3>Errou uma palavra? Corrige</h3>
        <p>Carta de correção com histórico, cancelamento e inutilização de numeração. O passo a passo é guiado, com erro da SEFAZ traduzido em português claro.</p>
        <div class="types"><span class="ftag">CC-e</span><span class="ftag">Cancelamento</span></div>
      </div>
      <div class="fcard reveal d2">
        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-6l-2-2H5a2 2 0 0 0-2 2z"/></svg></div>
        <h3>Notas que chegam para você</h3>
        <p>Manifestação do destinatário: dê ciência, confirme ou recuse as notas que seus fornecedores emitem contra o seu CNPJ. Sincroniza sozinho de tempos em tempos.</p>
        <div class="types"><span class="ftag">Ciência</span><span class="ftag">Confirmação</span><span class="ftag">Recusa</span></div>
      </div>
      <div class="fcard reveal">
        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg></div>
        <h3>Certificado e CSC sem mistério</h3>
        <p>Suba seu certificado A1 e o sistema avisa quando estiver perto de vencer. O arquivo não fica guardado no servidor: vai direto para a emissora.</p>
        <div class="types"><span class="ftag">Certificado A1</span><span class="ftag">Aviso de validade</span></div>
      </div>
      <div class="fcard reveal d1">
        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9c2.5 0 4.8 1 6.4 2.6L21 8"/><path d="M21 3v5h-5"/></svg></div>
        <h3>Guarda os XML por 5 anos</h3>
        <p>Backup mensal dos arquivos fiscais feito todo dia, sem você lembrar. Status da SEFAZ por estado e painel com erros e emissões dos últimos dias.</p>
        <div class="types"><span class="ftag">Backup automático</span><span class="ftag">Painel fiscal</span></div>
      </div>
      <div class="fcard reveal d2">
        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 4-6"/></svg></div>
        <h3>Já pronto para a Reforma Tributária</h3>
        <p>Cálculo de IBS, CBS e IS por item, além de substituição tributária interestadual com as alíquotas reais. Quando virar obrigatório, você já está em dia.</p>
        <div class="types"><span class="ftag">IBS / CBS / IS</span><span class="ftag">ST interestadual</span></div>
      </div>
    </div>

    <div class="fiscal__foot reveal">
      <p>Sem colar token, sem decorar tabela de CFOP. Ao cadastrar a empresa, o sistema já provisiona a emissão fiscal e configura os avisos automáticos.</p>
      <a class="btn btn-light" href="#demo">Quero ver isso funcionando</a>
    </div>
  </div>
</section>

<!-- MÓDULOS -->
<section class="section" id="modulos">
  <div class="wrap">
    <div class="head">
      <h2>Tudo que a empresa precisa, no mesmo sistema</h2>
      <p class="lede">Do cadastro do cliente ao relatório do contador. Sem juntar três programas que não se falam.</p>
    </div>
    <div class="mods">

      <article class="mod reveal">
        <div class="mod__h"><span class="mod__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><h3>Cadastros</h3></div>
        <p>Clientes, produtos, fornecedores, serviços e funcionários com passo a passo. CPF e CNPJ preenchem o resto sozinhos.</p>
        <ul><li>Busca de CNPJ</li><li>Importação CSV</li><li>Categorias</li><li>Etiquetas</li></ul>
      </article>

      <article class="mod reveal d1">
        <div class="mod__h"><span class="mod__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/></svg></span><h3>Vendas</h3></div>
        <p>Do orçamento ao pedido e à venda. PDV, venda balcão, devoluções e comissão por vendedor.</p>
        <ul><li>Orçamentos</li><li>PDV</li><li>Devoluções</li><li>Comissões</li></ul>
      </article>

      <article class="mod reveal d2">
        <div class="mod__h"><span class="mod__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/></svg></span><h3>Estoque</h3></div>
        <p>Saldo por loja, movimentações e transferências entre unidades com solicitação e aprovação.</p>
        <ul><li>Multi-loja</li><li>Transferências</li><li>Ajustes</li><li>Etiquetas</li></ul>
      </article>

      <article class="mod reveal">
        <div class="mod__h"><span class="mod__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span><h3>Financeiro</h3></div>
        <p>Contas a pagar e receber, fluxo de caixa, conciliação bancária, DRE e plano de contas.</p>
        <ul><li>Fluxo de caixa</li><li>DRE</li><li>Conciliação OFX</li><li>Recorrências</li></ul>
      </article>

      <article class="mod reveal d1">
        <div class="mod__h"><span class="mod__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg></span><h3>Fiscal</h3></div>
        <p>Emissão de NF-e, NFC-e e NFS-e, carta de correção, manifestação e painel fiscal. Tudo via Focus NFe.</p>
        <ul><li>Emissão</li><li>CC-e</li><li>Backup XML</li><li>Reforma</li></ul>
      </article>

      <article class="mod reveal d2">
        <div class="mod__h"><span class="mod__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span><h3>Controle e auditoria</h3></div>
        <p>Sete perfis de acesso com permissão por módulo. Registro de quem mudou o quê, antes e depois.</p>
        <ul><li>Perfis e permissões</li><li>Histórico</li><li>Notificações</li><li>Busca global</li></ul>
      </article>

    </div>
  </div>
</section>

<!-- MULTI-EMPRESA -->
<section class="section" style="background:var(--surface);border-block:1px solid var(--line)">
  <div class="wrap split">
    <div class="reveal">
      <h2 style="margin-bottom:1rem">Uma empresa, ou uma rede inteira</h2>
      <p class="lede" style="margin-bottom:1.5rem">Cada empresa tem suas lojas; cada loja tem CNPJ, estoque, caixa e fiscal independentes. O dono enxerga tudo, o vendedor vê só a loja dele. Os dados de uma empresa nunca se misturam com os de outra.</p>
      <ul class="checks">
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span><b>Multi-empresa e multi-loja</b> com isolamento total dos dados.</span></li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>Cada usuário vê só o que o perfil dele permite.</span></li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span>Relatórios por loja ou da empresa toda, num clique.</span></li>
      </ul>
    </div>
    <div class="reveal d1">
      <div class="panel" role="img" aria-label="Resumo de três lojas de uma mesma empresa com faturamento e notas do dia.">
        <div class="panel__head"><b>Minha Empresa Ltda</b><span class="tag">3 lojas ativas</span></div>
        <div class="rows">
          <div class="rows__r"><span>Loja Centro <span class="u">CNPJ ····/0001</span></span><span style="font-variant-numeric:tabular-nums">R$ 8.430</span><span class="tagk ok">47 notas</span></div>
          <div class="rows__r"><span>Loja Shopping <span class="u">CNPJ ····/0002</span></span><span style="font-variant-numeric:tabular-nums">R$ 12.190</span><span class="tagk ok">63 notas</span></div>
          <div class="rows__r"><span>Loja Bairro <span class="u">CNPJ ····/0003</span></span><span style="font-variant-numeric:tabular-nums">R$ 4.870</span><span class="tagk ok">29 notas</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- PLANOS -->
<section class="section" id="planos">
  <div class="wrap">
    <div class="head center">
      <h2>Planos que cabem no caixa da PME</h2>
      <p class="lede" style="margin-inline:auto">Comece testando. A demonstração mostra qual plano faz sentido para o número de lojas e usuários que você tem.</p>
    </div>
    <div class="plans">

      <article class="plan reveal">
        <h3>Básico</h3>
        <p class="plan__desc">Para quem está começando com uma loja.</p>
        <div class="plan__price"><b class="quote">Sob consulta</b></div>
        <ul>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> PDV, vendas e estoque</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Emissão de NFC-e e NF-e</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Financeiro essencial</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> 1 loja, até 3 usuários</li>
        </ul>
        <a class="btn btn-ghost btn-block" href="#demo">Ver na demonstração</a>
      </article>

      <article class="plan feat reveal d1">
        <h3>Profissional</h3>
        <p class="plan__desc">Para quem cresceu e tem mais de uma loja.</p>
        <div class="plan__price"><b class="quote">Sob consulta</b></div>
        <ul>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Tudo do Básico, com NFS-e</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Multi-loja com transferências</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> DRE, conciliação e recorrências</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Auditoria e comissões</li>
        </ul>
        <a class="btn btn-primary btn-block" href="#demo">Agendar demonstração</a>
      </article>

      <article class="plan reveal d2">
        <h3>Enterprise</h3>
        <p class="plan__desc">Para redes com muitas lojas e operação fiscal pesada.</p>
        <div class="plan__price"><b class="quote">Sob consulta</b></div>
        <ul>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Tudo do Profissional</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Lojas e usuários sem limite prático</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Reforma Tributária e ST avançada</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Suporte prioritário</li>
        </ul>
        <a class="btn btn-ghost btn-block" href="#demo">Falar com vendas</a>
      </article>

    </div>
  </div>
</section>

<!-- SEGURANÇA -->
<section class="section" style="background:var(--surface);border-top:1px solid var(--line)">
  <div class="wrap">
    <div class="head">
      <h2>Seus dados, separados e auditados</h2>
      <p class="lede">Cada empresa em seu próprio espaço, com registro de tudo que acontece dentro do sistema.</p>
    </div>
    <div class="sec">
      <div class="sec__item reveal">
        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
        <div><b>Isolamento por empresa</b><p>Os dados de um cliente nunca aparecem para outro. Cada empresa vive no seu próprio espaço.</p></div>
      </div>
      <div class="sec__item reveal d1">
        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg></span>
        <div><b>Perfis de acesso</b><p>Dono, gerente, financeiro, vendedor, caixa e mais. Cada um enxerga só o que precisa.</p></div>
      </div>
      <div class="sec__item reveal d2">
        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="9"/></svg></span>
        <div><b>Histórico de alterações</b><p>Quem mudou, quando e o que mudou. O antes e o depois ficam registrados.</p></div>
      </div>
      <div class="sec__item reveal">
        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg></span>
        <div><b>Certificado fora do servidor</b><p>O arquivo .pfx vai direto para a emissora. No banco ficam só os dados de validade.</p></div>
      </div>
    </div>
  </div>
</section>

<!-- CTA / DEMO FORM -->
<section class="section cta" id="demo">
  <div class="wrap cta__grid">
    <div class="reveal">
      <span class="cta__eyebrow">Demonstração gratuita</span>
      <h2>Veja o ERP rodando na sua operação</h2>
      <p class="cta__lede">Em cerca de 30 minutos mostramos o sistema com o cenário da sua empresa — número de lojas, tipo de nota fiscal e o que mais pesa no seu dia a dia. Sem compromisso e sem instalar nada.</p>
      <ul class="cta__list">
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Demonstração guiada e no seu cenário real</li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Dúvidas de NF-e, NFC-e e NFS-e tiradas na hora</li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Nada para instalar — é só entrar na chamada</li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Proposta sob medida para o seu número de lojas</li>
      </ul>
      <div class="cta__map reveal">
        <iframe
          title="Localização IA365 — Alameda Santos, 200, Bela Vista, São Paulo/SP"
          src="https://www.google.com/maps?q=Alameda+Santos+200,+Bela+Vista,+S%C3%A3o+Paulo+-+SP,+01418-000&output=embed"
          loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        <div class="cta__map-bar">
          <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg> <strong>IA365</strong> · Alameda Santos, 200 — 9º andar, Bela Vista, São Paulo/SP</span>
          <a href="https://www.google.com/maps/search/?api=1&query=Alameda+Santos+200+9%C2%BA+andar+Bela+Vista+S%C3%A3o+Paulo+SP+01418-000" target="_blank" rel="noopener">Como chegar →</a>
        </div>
      </div>
    </div>

    <form class="form" id="demoForm" action="{{ route('site.demo.store') }}" method="POST" novalidate>
      @csrf
      <input type="text" name="site" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
      <div class="form__head">
        <span class="form__badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7v5l3 2"/><circle cx="12" cy="12" r="9"/></svg> Resposta em até 1 dia útil</span>
        <h3>Agendar demonstração</h3>
        <p>Preencha e a gente entra em contato para marcar o melhor horário.</p>
      </div>
      <div class="field">
        <label for="nome">Seu nome</label>
        <input id="nome" name="nome" type="text" autocomplete="name" placeholder="Como podemos te chamar" required>
      </div>
      <div class="field">
        <label for="empresa">Empresa</label>
        <input id="empresa" name="empresa" type="text" autocomplete="organization" placeholder="Nome da sua empresa" required>
      </div>
      <div class="field field--row">
        <div>
          <label for="email">E-mail</label>
          <input id="email" name="email" type="email" autocomplete="email" placeholder="voce@empresa.com" required>
        </div>
        <div>
          <label for="whats">WhatsApp</label>
          <input id="whats" name="whatsapp" type="tel" inputmode="tel" autocomplete="tel" placeholder="(11) 90000-0000" required>
        </div>
      </div>
      <div class="field">
        <label for="lojas">Quantas lojas?</label>
        <select id="lojas" name="lojas">
          <option value="1 loja">1 loja</option>
          <option value="2 a 3 lojas">2 a 3 lojas</option>
          <option value="4 a 10 lojas">4 a 10 lojas</option>
          <option value="mais de 10 lojas">Mais de 10 lojas</option>
        </select>
      </div>
      <button class="btn btn-primary btn-block" type="submit">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        Quero agendar a demonstração
      </button>
      <p class="form__fine"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg> Seus dados são usados só para este contato. Sem spam.</p>
      <p id="formMsg" class="form__feedback" hidden role="status"></p>
      <p class="form__alt">Prefere agora? <a href="https://wa.me/5511917120940?text=Quero%20uma%20demonstra%C3%A7%C3%A3o%20do%20ERP%20Comercial" target="_blank" rel="noopener">Chamar no WhatsApp</a></p>
    </form>
  </div>
</section>

</main>

<!-- FOOTER -->
<footer class="foot">
  <div class="wrap">
    <div class="foot__grid">
      <div class="foot__brand">
        <a class="brand" href="#"><span class="brand__mark"><span>IA</span></span> ERP Comercial <small>· IA365</small></a>
        <p>O ERP que cuida da nota fiscal pela sua PME, do PDV ao relatório do contador.</p>
      </div>
      <div>
        <h4>Produto</h4>
        <ul>
          <li><a href="#recursos">Recursos</a></li>
          <li><a href="#fiscal">Nota fiscal</a></li>
          <li><a href="#modulos">Módulos</a></li>
          <li><a href="#planos">Planos</a></li>
        </ul>
      </div>
      <div>
        <h4>Fiscal</h4>
        <ul>
          <li><a href="#fiscal">NF-e, NFC-e, NFS-e</a></li>
          <li><a href="#fiscal">Carta de correção</a></li>
          <li><a href="#fiscal">Manifestação</a></li>
          <li><a href="#fiscal">Reforma Tributária</a></li>
        </ul>
      </div>
      <div>
        <h4>Contato</h4>
        <ul>
          <li><a href="#demo">Agendar demonstração</a></li>
          <li><a href="https://wa.me/5511917120940" target="_blank" rel="noopener">WhatsApp</a></li>
          <li><a href="mailto:contato@ia365.com.br">contato@ia365.com.br</a></li>
          <li><a href="https://www.google.com/maps/search/?api=1&query=Alameda+Santos+200+9%C2%BA+andar+Bela+Vista+S%C3%A3o+Paulo+SP+01418-000" target="_blank" rel="noopener">Alameda Santos, 200 — 9º andar<br>Bela Vista, São Paulo/SP · 01418-000</a></li>
        </ul>
      </div>
    </div>
    <div class="foot__bar">
      <span>© <span id="ano">2026</span> IA365 · ERP Comercial. Todos os direitos reservados.</span>
      <span>Emissão fiscal via Focus NFe. NF-e, NFC-e e NFS-e.</span>
    </div>
  </div>
</footer>

<script src="{{ asset('site/landing.js') }}?v={{ @filemtime(public_path('site/landing.js')) ?: '1' }}" defer></script>
</body>
</html>
