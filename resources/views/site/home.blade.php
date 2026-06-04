<!DOCTYPE html>
<html lang="pt-BR" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ia365 ERP: PDV, estoque e nota fiscal num sistema só</title>
    <meta name="description" content="ERP comercial para pequenas e médias empresas: PDV, estoque, financeiro e emissão de NFC-e, NF-e e NFS-e num sistema só. 14 dias grátis.">
    <meta name="theme-color" content="#221c17">

    <meta property="og:type" content="website">
    <meta property="og:title" content="ia365 ERP: venda no balcão, a nota sai sozinha">
    <meta property="og:description" content="PDV, estoque, financeiro e emissão fiscal (NFC-e, NF-e, NFS-e) num sistema só, para pequenas e médias empresas.">

    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=Archivo+Expanded:wght@700;800&family=Spline+Sans+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
</head>
<body>

{{-- ===================== NAV ===================== --}}
<header class="nav">
    <div class="wrap nav__inner">
        <a href="#topo" class="brand" aria-label="ia365 ERP, início">
            <span class="brand__mark" aria-hidden="true"></span>
            ia365 <span>ERP</span>
        </a>
        <nav class="nav__links" aria-label="Seções">
            <a href="#funcionalidades">Funcionalidades</a>
            <a href="#fiscal">Fiscal</a>
            <a href="#planos">Planos</a>
        </nav>
        <div class="nav__actions">
            <a href="{{ route('login') }}" class="nav__login">Entrar</a>
            <a href="{{ route('login') }}" class="btn btn--primary">Testar grátis</a>
        </div>
    </div>
</header>

{{-- ===================== HERO ===================== --}}
<section class="hero" id="topo">
    <div class="wrap hero__grid">
        <div class="hero__copy">
            <h1 data-reveal>Venda no balcão. A <span class="hot">nota</span> sai sozinha.</h1>
            <p class="hero__sub" data-reveal data-reveal-delay="80">
                O ia365 junta PDV, estoque, financeiro e emissão fiscal num sistema só.
                A NFC-e sai no fechamento da venda; NF-e e NFS-e quando você precisa.
                Sem planilha, sem susto com a SEFAZ.
            </p>
            <div class="hero__cta" data-reveal data-reveal-delay="160">
                <a href="{{ route('login') }}" class="btn btn--primary btn--lg">
                    Começar teste grátis
                    <svg class="arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
                <a href="#planos" class="btn btn--ghost-dark btn--lg">Ver planos e preços</a>
            </div>
            <ul class="hero__trust" data-reveal data-reveal-delay="220" aria-label="Condições do teste">
                <li><span class="dot" aria-hidden="true"></span>14 dias grátis</li>
                <li><span class="dot" aria-hidden="true"></span>Sem cartão</li>
                <li><span class="dot" aria-hidden="true"></span>Configuração fiscal assistida</li>
            </ul>
        </div>

        {{-- Crafted product artifact: NFC-e cupom + SEFAZ chip --}}
        <div class="hero__art" data-reveal data-reveal-delay="120">
            <figure class="receipt" role="img" aria-label="Cupom fiscal eletrônico NFC-e autorizado pela SEFAZ, total de R$ 248,90, com chave de acesso de 44 dígitos.">
                <span class="stamp">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    AUTORIZADA
                </span>
                <div class="receipt__head">
                    <div class="receipt__store">Mercearia da Praça</div>
                    <div class="receipt__meta">NFC-e 000.482 · Série 1 · 04/06/2026 14:32</div>
                </div>
                <div class="receipt__rows">
                    <div class="receipt__row"><span>2× Café torrado 500g</span><span>R$ 39,80</span></div>
                    <div class="receipt__row"><span>1× Açúcar cristal 5kg</span><span>R$ 24,90</span></div>
                    <div class="receipt__row"><span>3× Refrigerante 2L</span><span>R$ 26,70</span></div>
                    <div class="receipt__row"><span>1× Detergente kit</span><span>R$ 18,50</span></div>
                    <div class="receipt__row"><span>1× Arroz tipo 1 5kg</span><span>R$ 28,90</span></div>
                </div>
                <div class="receipt__total"><span>Total</span><b>R$ 248,90</b></div>
                <div class="receipt__key">Chave: 3526 0412 3456 7800 0182 5500 1000 0482 1100 0482 1903</div>
            </figure>
            <div class="sefaz-chip" aria-hidden="true">
                <span class="pulse"></span> SEFAZ-SP online · 0,9s
            </div>
        </div>
    </div>
</section>

{{-- ===================== RELIEF ===================== --}}
<section class="relief">
    <div class="wrap relief__grid">
        <div>
            <h2 data-reveal>Nota fiscal não devia dar medo.</h2>
            <p class="relief__lead" data-reveal data-reveal-delay="80">
                A maioria dos sistemas trata o fiscal como anexo. Aqui ele é o centro:
                a venda e a nota acontecem no mesmo gesto, e o erro da SEFAZ chega
                explicado, não em código.
            </p>
        </div>
        <ul class="relief__list">
            <li class="relief__item" data-reveal>
                <span class="ic" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 8h10M7 12h6"/><circle cx="17" cy="16" r="3"/></svg>
                </span>
                <div>
                    <h3>Emite direto do caixa</h3>
                    <p>Fechou a venda no PDV, a NFC-e é autorizada e o cupom imprime. Sem trocar de tela.</p>
                </div>
            </li>
            <li class="relief__item" data-reveal data-reveal-delay="80">
                <span class="ic" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 3 7v6c0 5 3.5 8 9 9 5.5-1 9-4 9-9V7l-9-5Z"/><path d="m9 12 2 2 4-4"/></svg>
                </span>
                <div>
                    <h3>Erro da SEFAZ em português</h3>
                    <p>Prazo, duplicidade, certificado, instabilidade: a mensagem diz o que houve e o que fazer.</p>
                </div>
            </li>
            <li class="relief__item" data-reveal data-reveal-delay="160">
                <span class="ic" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </span>
                <div>
                    <h3>Certificado A1 sob guarda</h3>
                    <p>Você envia o .pfx uma vez; ele não fica solto no seu computador. Avisamos antes de vencer.</p>
                </div>
            </li>
        </ul>
    </div>
</section>

{{-- ===================== FEATURES ===================== --}}
<section class="section" id="funcionalidades">
    <div class="wrap">
        <div class="features__head">
            <h2 data-reveal>Um sistema, a loja inteira.</h2>
            <p data-reveal data-reveal-delay="80">Do balcão ao DRE. Cada parte conversa com a outra, então o estoque baixa, o caixa registra e a nota emite a partir da mesma venda.</p>
        </div>

        <div class="feat-grid">
            {{-- PDV spotlight (dark, wide) --}}
            <article class="feat feat--spotlight" data-reveal>
                <span class="feat__ic" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M2 9h20M6 18v3M18 18v3"/></svg>
                </span>
                <h3>PDV de tela cheia</h3>
                <p>Atalhos F1–F12, leitura por código de barras, pagamento dividido e verificação de estoque na hora. Funciona rápido até em balcão movimentado.</p>
                <div class="pdv-mini" aria-hidden="true">
                    <div class="pdv-mini__bar">PDV · Caixa 1 <span class="k">F2 buscar</span></div>
                    <div class="pdv-mini__rows">
                        <div class="pdv-mini__row"><span>Pão francês kg</span><span>12,90</span></div>
                        <div class="pdv-mini__row"><span>Presto kg</span><span>54,00</span></div>
                        <div class="pdv-mini__row"><span>Mussarela kg</span><span>48,80</span></div>
                    </div>
                    <div class="pdv-mini__total"><span>Total</span><b>R$ 115,70</b></div>
                </div>
                <div class="feat__tags"><span class="tag">split de pagamento</span><span class="tag">NFC-e no fechamento</span></div>
            </article>

            {{-- Fiscal (tall) --}}
            <article class="feat feat--tall" data-reveal data-reveal-delay="80">
                <span class="feat__ic" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M9 13h6M9 17h4"/></svg>
                </span>
                <h3>Emissão fiscal completa</h3>
                <p>NFC-e, NF-e e NFS-e, com Carta de Correção e manifestação de notas recebidas. Integração Focus NFe.</p>
                <div class="feat__tags"><span class="tag">NFC-e</span><span class="tag">NF-e</span><span class="tag">NFS-e</span><span class="tag">CC-e</span></div>
            </article>

            {{-- Financeiro (half) --}}
            <article class="feat feat--half" data-reveal>
                <span class="feat__ic" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 14 3-3 3 3 5-6"/></svg>
                </span>
                <h3>Financeiro que fecha o mês</h3>
                <p>Contas a pagar e receber com parcelas, fluxo de caixa, DRE por unidade e conciliação por arquivo OFX.</p>
                <div class="feat__tags"><span class="tag">fluxo de caixa</span><span class="tag">DRE</span><span class="tag">conciliação OFX</span></div>
            </article>

            {{-- Estoque + multiloja (half) --}}
            <article class="feat feat--half" data-reveal data-reveal-delay="80">
                <span class="feat__ic" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/></svg>
                </span>
                <h3>Estoque e multilojas</h3>
                <p>Cada unidade com estoque, caixa e fiscal próprios. Transferência entre lojas com pedido e aprovação.</p>
                <div class="feat__tags"><span class="tag">por unidade</span><span class="tag">transferências</span></div>
            </article>

            {{-- Cadastros (third) --}}
            <article class="feat feat--third" data-reveal>
                <span class="feat__ic" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                </span>
                <h3>Cadastros que se preenchem</h3>
                <p>Digite o CNPJ, o resto vem. Importe clientes e produtos por CSV com modelo pronto.</p>
            </article>

            {{-- ST inteligente (third) --}}
            <article class="feat feat--third" data-reveal data-reveal-delay="80">
                <span class="feat__ic" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M5 7h14M5 7l-2 5a4 4 0 0 0 8 0L5 7Zm14 0-2 5a4 4 0 0 0 8 0l-6-5Z"/></svg>
                </span>
                <h3>Imposto calculado certo</h3>
                <p>Substituição tributária interestadual, FCP e presets de CST/CSOSN por regime, no item da nota.</p>
            </article>

            {{-- Auditoria (third) --}}
            <article class="feat feat--third" data-reveal data-reveal-delay="160">
                <span class="feat__ic" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3M11 8v3l2 1"/></svg>
                </span>
                <h3>Histórico de tudo</h3>
                <p>Quem mudou o quê, e quando. Registro de auditoria com o antes e o depois, por empresa.</p>
            </article>
        </div>
    </div>
</section>

{{-- ===================== FISCAL ===================== --}}
<section class="section fiscal" id="fiscal">
    <div class="wrap fiscal__grid">
        <div>
            <p class="eyebrow" data-reveal>O motivo de existir</p>
            <h2 data-reveal data-reveal-delay="60">A parte fiscal, resolvida.</h2>
            <p class="fiscal__lead" data-reveal data-reveal-delay="120">
                Da venda à autorização da SEFAZ em segundos, com acompanhamento
                automático quando a nota é assíncrona. O que costuma travar a operação
                aqui já vem pronto.
            </p>
            <ul class="fiscal__list">
                <li data-reveal><span class="chk" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span><div><b>NFC-e síncrona no PDV.</b> <span>Autoriza no fechamento; se falhar, cai no recibo sem travar a venda.</span></div></li>
                <li data-reveal data-reveal-delay="60"><span class="chk" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span><div><b>NF-e e NFS-e com DANFE.</b> <span>Emissão assíncrona com consulta automática até autorizar.</span></div></li>
                <li data-reveal data-reveal-delay="120"><span class="chk" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span><div><b>Manifestação do destinatário.</b> <span>Ciência, confirmação e recusa das notas que entram, sincronizadas a cada 4h.</span></div></li>
                <li data-reveal data-reveal-delay="180"><span class="chk" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span><div><b>Status da SEFAZ por UF.</b> <span>Sabe se a SEFAZ está online antes de tentar emitir.</span></div></li>
            </ul>
        </div>

        {{-- Crafted flow --}}
        <div class="flow" data-reveal data-reveal-delay="120" aria-hidden="true">
            <div class="flow__step">
                <span class="flow__num">01</span>
                <div><h4>Venda fechada no caixa</h4><p>R$ 248,90 · 5 itens · dinheiro + Pix</p></div>
            </div>
            <svg class="flow__arrow" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M6 13l6 6 6-6"/></svg>
            <div class="flow__step">
                <span class="flow__num">02</span>
                <div><h4>NFC-e enviada à SEFAZ</h4><p>Cálculo de ICMS, ST e tributos no item</p></div>
                <span class="badge badge--ember">processando</span>
            </div>
            <svg class="flow__arrow" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M6 13l6 6 6-6"/></svg>
            <div class="flow__step">
                <span class="flow__num">03</span>
                <div><h4>Autorizada e cupom impresso</h4><p>Chave de acesso e DANFE prontos</p></div>
                <span class="badge badge--ok"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> autorizada</span>
            </div>
        </div>
    </div>
</section>

{{-- ===================== PRICING ===================== --}}
<section class="section" id="planos">
    <div class="wrap">
        <div class="pricing__head">
            <h2 data-reveal>Preço na cara. Escolha e comece.</h2>
            <p data-reveal data-reveal-delay="80">Sem proposta para descobrir o valor. Todos os planos têm teste grátis.</p>
        </div>

        <div class="plans">
            {{-- Básico --}}
            <article class="plan" data-reveal>
                <div class="plan__name">Básico</div>
                <p class="plan__desc">Para quem está começando e quer sair da planilha.</p>
                <div class="plan__price"><span class="cur">R$</span><span class="amt">97</span><span class="per">/mês</span></div>
                <div class="plan__year">ou R$ 970/ano · 14 dias grátis</div>
                <a href="{{ route('login') }}" class="btn btn--ghost">Começar</a>
                <ul>
                    <li><span class="tick" aria-hidden="true">✓</span> PDV completo</li>
                    <li><span class="tick" aria-hidden="true">✓</span> 1 unidade · 5 usuários</li>
                    <li><span class="tick" aria-hidden="true">✓</span> Até 500 produtos</li>
                    <li><span class="tick" aria-hidden="true">✓</span> Estoque e financeiro básico</li>
                    <li class="off"><span class="tick" aria-hidden="true">✕</span> Emissão fiscal</li>
                    <li class="off"><span class="tick" aria-hidden="true">✕</span> Multilojas</li>
                </ul>
            </article>

            {{-- Profissional (featured) --}}
            <article class="plan plan--featured" data-reveal data-reveal-delay="80">
                <span class="plan__flag">Mais escolhido</span>
                <div class="plan__name">Profissional</div>
                <p class="plan__desc">Para a empresa que cresceu e precisa emitir nota.</p>
                <div class="plan__price"><span class="cur">R$</span><span class="amt">197</span><span class="per">/mês</span></div>
                <div class="plan__year">ou R$ 1.970/ano · 14 dias grátis</div>
                <a href="{{ route('login') }}" class="btn btn--primary">Começar</a>
                <ul>
                    <li><span class="tick" aria-hidden="true">✓</span> Tudo do Básico</li>
                    <li><span class="tick" aria-hidden="true">✓</span> Emissão fiscal (NFC-e, NF-e, NFS-e)</li>
                    <li><span class="tick" aria-hidden="true">✓</span> Multilojas: até 3 unidades</li>
                    <li><span class="tick" aria-hidden="true">✓</span> 15 usuários · até 5.000 produtos</li>
                    <li><span class="tick" aria-hidden="true">✓</span> Ordens de serviço e contratos</li>
                    <li><span class="tick" aria-hidden="true">✓</span> 500 notas por mês</li>
                </ul>
            </article>

            {{-- Enterprise --}}
            <article class="plan" data-reveal data-reveal-delay="160">
                <div class="plan__name">Enterprise</div>
                <p class="plan__desc">Operação sem limites, com tudo ligado.</p>
                <div class="plan__price"><span class="cur">R$</span><span class="amt">397</span><span class="per">/mês</span></div>
                <div class="plan__year">ou R$ 3.970/ano · 30 dias grátis</div>
                <a href="{{ route('login') }}" class="btn btn--ghost">Começar</a>
                <ul>
                    <li><span class="tick" aria-hidden="true">✓</span> Tudo do Profissional</li>
                    <li><span class="tick" aria-hidden="true">✓</span> Unidades e usuários ilimitados</li>
                    <li><span class="tick" aria-hidden="true">✓</span> Conciliação bancária e DRE</li>
                    <li><span class="tick" aria-hidden="true">✓</span> Boletos e carnês</li>
                    <li><span class="tick" aria-hidden="true">✓</span> Acesso por API</li>
                    <li><span class="tick" aria-hidden="true">✓</span> Notas sem limite mensal</li>
                </ul>
            </article>
        </div>
        <p class="pricing__note">Os limites (unidades, usuários, produtos e notas) seguem o plano escolhido e podem ser ampliados a qualquer momento.</p>
    </div>
</section>

{{-- ===================== TRUST ===================== --}}
<section class="trust">
    <div class="wrap trust__grid">
        <div class="trust__item" data-reveal>
            <span class="ic" aria-hidden="true"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
            <h3>Cada empresa, isolada</h3>
            <p>Dados separados por empresa e por unidade. Uma loja nunca enxerga a outra.</p>
        </div>
        <div class="trust__item" data-reveal data-reveal-delay="80">
            <span class="ic" aria-hidden="true"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 3 7v6c0 5 3.5 8 9 9 5.5-1 9-4 9-9V7l-9-5Z"/></svg></span>
            <h3>Seu certificado seguro</h3>
            <p>O .pfx vai direto para a emissora e não fica armazenado no sistema. Guardamos só os metadados.</p>
        </div>
        <div class="trust__item" data-reveal data-reveal-delay="160">
            <span class="ic" aria-hidden="true"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v6H4zM4 14h16v6H4z"/><path d="M8 7h.01M8 17h.01"/></svg></span>
            <h3>Perfis por função</h3>
            <p>Dono, gerente, financeiro, vendedor, caixa. Cada um vê e faz só o que precisa.</p>
        </div>
    </div>
</section>

{{-- ===================== FINAL CTA ===================== --}}
<section class="section cta-final">
    <div class="wrap cta-final__inner">
        <h2 data-reveal>Comece a emitir nota hoje.</h2>
        <p data-reveal data-reveal-delay="80">Crie sua conta, configure a parte fiscal com a gente e faça a primeira venda com NFC-e no mesmo dia.</p>
        <div class="hero__cta" data-reveal data-reveal-delay="140">
            <a href="{{ route('login') }}" class="btn btn--primary btn--lg">
                Começar teste grátis
                <svg class="arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
            <a href="mailto:contato@ia365.com.br?subject=Quero%20uma%20demonstração%20do%20ia365%20ERP" class="btn btn--ghost-dark btn--lg">Agendar demonstração</a>
        </div>
    </div>
</section>

{{-- ===================== FOOTER ===================== --}}
<footer class="footer">
    <div class="wrap">
        <div class="footer__grid">
            <div class="footer__brand">
                <a href="#topo" class="brand" style="color:var(--ink-on-dark)">
                    <span class="brand__mark" aria-hidden="true"></span> ia365 <span>ERP</span>
                </a>
                <p>O ERP comercial que junta venda, estoque, financeiro e nota fiscal para a pequena e média empresa brasileira.</p>
            </div>
            <div>
                <h4>Produto</h4>
                <ul>
                    <li><a href="#funcionalidades">Funcionalidades</a></li>
                    <li><a href="#fiscal">Fiscal</a></li>
                    <li><a href="#planos">Planos e preços</a></li>
                </ul>
            </div>
            <div>
                <h4>Acesso</h4>
                <ul>
                    <li><a href="{{ route('login') }}">Entrar no sistema</a></li>
                    <li><a href="{{ route('password.request') }}">Esqueci a senha</a></li>
                </ul>
            </div>
            <div>
                <h4>Contato</h4>
                <ul>
                    <li><a href="mailto:contato@ia365.com.br">contato@ia365.com.br</a></li>
                    <li><a href="mailto:contato@ia365.com.br?subject=Demonstração%20ia365%20ERP">Agendar demonstração</a></li>
                </ul>
            </div>
        </div>
        <div class="footer__bottom">
            <span>© {{ date('Y') }} ia365 · ERP Comercial</span>
            <span>Emissão fiscal via Focus NFe</span>
        </div>
    </div>
</footer>

<script src="{{ asset('js/site.js') }}" defer></script>
</body>
</html>
