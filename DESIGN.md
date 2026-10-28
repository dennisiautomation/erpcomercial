---
name: ERP Comercial
description: Ferramenta de operação para lojas pequenas — um único azul, superfícies brancas sobre cinza-ardósia, status sempre legível.
colors:
  primary: "#4f46e5"
  primary-hover: "#4338ca"
  primary-light: "#eef2ff"
  primary-glow: "#818cf8"
  success: "#059669"
  success-light: "#ecfdf5"
  success-text: "#047857"
  warning: "#d97706"
  warning-light: "#fffbeb"
  warning-text: "#b45309"
  danger: "#dc2626"
  danger-light: "#fef2f2"
  danger-text: "#b91c1c"
  info: "#0891b2"
  info-light: "#ecfeff"
  info-text: "#0e7490"
  bg-page: "#f1f5f9"
  bg-card: "#ffffff"
  bg-table-head: "#f8fafc"
  bg-sidebar: "#1e293b"
  bg-sidebar-footer: "#0f172a"
  text-primary: "#0f172a"
  text-secondary: "#475569"
  text-muted: "#64748b"
  text-sidebar: "#cbd5e1"
  text-on-dark: "#ffffff"
  border: "#e2e8f0"
  border-light: "#f1f5f9"
  border-hover: "#cbd5e1"
  pdv-bg: "#0f172a"
  pdv-surface: "#1e293b"
  pdv-surface-raised: "#334155"
  pdv-border: "#475569"
  pdv-text: "#f1f5f9"
  pdv-text-secondary: "#94a3b8"
  pdv-text-muted: "#8290a6"
  pdv-accent: "#3b82f6"
  pdv-green: "#22c55e"
  pdv-red: "#ef4444"
  pdv-yellow: "#eab308"
typography:
  headline:
    fontFamily: "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif"
    fontSize: "1.5rem"
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: "-0.01em"
  title:
    fontFamily: "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif"
    fontSize: "0.95rem"
    fontWeight: 600
  stat-value:
    fontFamily: "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif"
    fontSize: "1.5rem"
    fontWeight: 800
    lineHeight: 1.2
  body:
    fontFamily: "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  body-dense:
    fontFamily: "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
  label:
    fontFamily: "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif"
    fontSize: "0.8rem"
    fontWeight: 600
    letterSpacing: "0.03em"
  table-head:
    fontFamily: "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif"
    fontSize: "0.75rem"
    fontWeight: 600
    letterSpacing: "0.04em"
  pdv-total:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"
    fontSize: "2.4rem"
    fontWeight: 800
    lineHeight: 1
rounded:
  xs: "4px"
  sm: "6px"
  md: "0.5rem"
  lg: "0.75rem"
  xl: "1rem"
  pill: "999px"
spacing:
  xs: "0.35rem"
  sm: "0.5rem"
  md: "0.75rem"
  lg: "1rem"
  xl: "1.25rem"
  2xl: "1.5rem"
  3xl: "3rem"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.text-on-dark}"
    typography: "{typography.body-dense}"
    rounded: "{rounded.md}"
    padding: "0.5rem 1rem"
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
    textColor: "{colors.text-on-dark}"
  button-outline:
    backgroundColor: "{colors.bg-card}"
    textColor: "{colors.text-secondary}"
    typography: "{typography.body-dense}"
    rounded: "{rounded.md}"
    padding: "0.5rem 1rem"
  button-outline-hover:
    backgroundColor: "{colors.bg-table-head}"
    textColor: "{colors.text-primary}"
  button-danger:
    backgroundColor: "{colors.danger}"
    textColor: "{colors.text-on-dark}"
    rounded: "{rounded.md}"
    padding: "0.5rem 1rem"
  button-success:
    backgroundColor: "{colors.success}"
    textColor: "{colors.text-on-dark}"
    rounded: "{rounded.md}"
    padding: "0.5rem 1rem"
  card:
    backgroundColor: "{colors.bg-card}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.lg}"
    padding: "1.25rem"
  card-header:
    backgroundColor: "{colors.bg-card}"
    textColor: "{colors.text-primary}"
    typography: "{typography.title}"
    padding: "1rem 1.25rem"
  stat-card:
    backgroundColor: "{colors.bg-card}"
    rounded: "{rounded.lg}"
    padding: "1.25rem"
  stat-icon:
    backgroundColor: "{colors.primary-light}"
    textColor: "{colors.primary}"
    rounded: "{rounded.md}"
    size: "48px"
  filter-bar:
    backgroundColor: "{colors.bg-card}"
    rounded: "{rounded.lg}"
    padding: "1rem 1.25rem"
  input:
    backgroundColor: "{colors.bg-card}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.md}"
    padding: "0.6rem 0.85rem"
  badge-status-success:
    backgroundColor: "{colors.success-light}"
    textColor: "{colors.success-text}"
    typography: "{typography.table-head}"
    rounded: "{rounded.pill}"
    padding: "0.3rem 0.65rem"
  badge-status-warning:
    backgroundColor: "{colors.warning-light}"
    textColor: "{colors.warning-text}"
    rounded: "{rounded.pill}"
    padding: "0.3rem 0.65rem"
  badge-status-danger:
    backgroundColor: "{colors.danger-light}"
    textColor: "{colors.danger-text}"
    rounded: "{rounded.pill}"
    padding: "0.3rem 0.65rem"
  badge-status-info:
    backgroundColor: "{colors.info-light}"
    textColor: "{colors.info-text}"
    rounded: "{rounded.pill}"
    padding: "0.3rem 0.65rem"
  badge-status-neutral:
    backgroundColor: "{colors.bg-page}"
    textColor: "{colors.text-secondary}"
    rounded: "{rounded.pill}"
    padding: "0.3rem 0.65rem"
  table-head:
    backgroundColor: "{colors.bg-table-head}"
    textColor: "{colors.text-secondary}"
    typography: "{typography.table-head}"
    padding: "0.75rem 1rem"
  sidebar:
    backgroundColor: "{colors.bg-sidebar}"
    textColor: "{colors.text-sidebar}"
    width: "250px"
  sidebar-link:
    textColor: "{colors.text-sidebar}"
    rounded: "{rounded.sm}"
    padding: "0.5rem 0.75rem"
  topbar:
    backgroundColor: "{colors.bg-card}"
    height: "56px"
    padding: "0 1.25rem"
  pdv-button-finalizar:
    backgroundColor: "{colors.pdv-green}"
    textColor: "#000000"
    rounded: "12px"
    padding: "16px"
---

# Design System: ERP Comercial

## Overview

**Creative North Star: "O Balcão da Loja"**

O ERP Comercial é uma ferramenta de operação: dono, gerente, vendedor e caixa de lojas pequenas usam no computador da loja e, às vezes, no celular. A interface foi construída como um balcão bem organizado: superfícies brancas sobre um fundo cinza-ardósia claro, uma barra lateral escura que fica quieta à esquerda, e um único azul (índigo) que aponta a ação principal de cada tela. Nada compete pela atenção além da tarefa em curso. A fonte é a do próprio sistema operacional (não há webfont), os ícones são Bootstrap Icons, e o Bootstrap 5.3 é a base — mas ele herda os tokens do projeto, nunca o contrário.

Existem dois mundos dentro do produto, e os dois são deliberados. O **ERP** (layout `app.blade.php` + `erp.css`) é claro, denso e tabular. O **PDV** (`app/pdv/index.blade.php`) é um mundo escuro próprio para o caixa: fundo ardósia profundo, azul `#3b82f6` como acento de foco e verde `#22c55e` como cor do dinheiro — o total da venda é o maior texto da tela. A tela de **login** fica entre os dois: escura como o PDV, mas com o mesmo índigo primário do ERP. Um agente que gera uma tela nova precisa saber em qual mundo está antes de escolher qualquer cor.

**Key Characteristics:**
- Um único azul primário (`primary`), herdado pelo Bootstrap via `--bs-primary`, `.btn-primary`, `.btn-outline-primary`, links, paginação, abas, switches e barra de progresso.
- Status sempre em par: fundo claro + texto escuro da mesma família (`success-light` + `success-text` etc.), todos ≥ 4.5:1.
- Cinza-ardósia (escala Tailwind slate) para tudo que é neutro: página, bordas, texto secundário e apagado, barra lateral.
- Densidade de operação: tabelas a 0.875rem, cabeçalhos de tabela e rótulos de campo em caixa alta pequena, números tabulares.
- Acessibilidade de teclado e toque embutida: anel de foco de 2px no primário, interruptores maiores em `pointer: coarse`, `prefers-reduced-motion` respeitado.

## Colors

Uma paleta de índigo único sobre cinza-ardósia, com quatro famílias de status que existem sempre em três tons (cor, fundo claro, texto escuro); o PDV troca a base para o lado escuro da mesma escala.

### Primary
- **Índigo de Ação** (`primary`): o único azul de marca do ERP. Botões primários, links, item ativo da barra lateral (via `rgba(79,70,229,.3)`), avatar do usuário, ícone da marca, caret do cursor, anel de foco e seleção de texto. Aparece também no login, no convite PWA e no gradiente do atalho PDV na barra lateral (`linear-gradient(135deg, #4f46e5, #7c3aed)`).
- **Índigo Pressionado** (`primary-hover`): hover e active de qualquer botão primário e de links.
- **Névoa de Índigo** (`primary-light`): fundo do ícone de stat-card primário, fundo da `::selection`, fundo do badge `entregue`.
- **Índigo Luminoso** (`primary-glow`): usado somente sobre fundo escuro — borda esquerda do item ativo da barra lateral e borda de foco dos campos do login. Nunca sobre branco (não atinge contraste).

### Status (Secondary roles)
- **Verde de Confirmação** (`success` / `success-light` / `success-text`): pago, ativo, concluído, autorizado. Botão `btn-erp-success`, tendência positiva de stat-card.
- **Âmbar de Pendência** (`warning` / `warning-light` / `warning-text`): pendente, em aberto, rascunho, em implantação.
- **Vermelho de Bloqueio** (`danger` / `danger-light` / `danger-text`): cancelado, rejeitado, bloqueado, vencido. Borda de campo inválido, asterisco de obrigatório, botão `btn-erp-danger`.
- **Ciano de Processo** (`info` / `info-light` / `info-text`): em andamento, confirmado, aprovado, faturado, convertido.

### Neutral
- **Ardósia de Página** (`bg-page`): fundo de toda a área de conteúdo do ERP; também fundo do badge de status neutro (inativo, fechado, suspenso).
- **Branco de Cartão** (`bg-card`): cartões, filter-bar, cabeçalhos de cartão, topbar, campos de formulário.
- **Branco Gelo** (`bg-table-head`): cabeçalho de tabela, hover de linha, hover de botão outline, chip da unidade na topbar.
- **Ardósia da Barra Lateral** (`bg-sidebar`): fundo da sidebar; **Ardósia Profunda** (`bg-sidebar-footer`): rodapé da sidebar com o cartão do usuário.
- **Tinta** (`text-primary`): títulos, valores, texto de corpo.
- **Grafite** (`text-secondary`): subtítulos, rótulos de campo, cabeçalhos de tabela, botões outline, texto do estado vazio. ≥ 4.5:1 sobre branco.
- **Cinza Apagado** (`text-muted`): rótulos de stat-card, texto de ajuda de campo, títulos de seção. ≥ 4.5:1 sobre branco.
- **Névoa Clara** (`text-sidebar`): texto da barra lateral em repouso (os links usam `rgba(255,255,255,.55)` em repouso e `.85` no hover; ativo é branco puro).
- **Linha** (`border`): toda borda de cartão, campo, filter-bar, divisor de cabeçalho. **Linha Suave** (`border-light`): divisores entre linhas de tabela e sob títulos de seção. **Linha Hover** (`border-hover`): borda de botão outline e chip da topbar em hover.

### PDV (mundo escuro)
- **Ardósia Profunda** (`pdv-bg`), **Ardósia** (`pdv-surface`), **Ardósia Erguida** (`pdv-surface-raised`): as três camadas do PDV, do fundo da página ao cabeçalho de tabela e botões de quantidade.
- **Linha Escura** (`pdv-border`): toda borda no PDV; campos usam 2px dela.
- **Gelo** (`pdv-text`), **Névoa** (`pdv-text-secondary`), **Cinza de Código** (`pdv-text-muted`): texto principal, secundário e códigos/sequências.
- **Azul de Foco** (`pdv-accent`): marca da topbar, borda de foco do campo de busca, linha selecionada, botão de pagamento ativo, anel de foco do teclado.
- **Verde de Dinheiro** (`pdv-green`): preço de item, coluna de total, o total da venda e o botão Finalizar (texto preto sobre verde). **Vermelho** (`pdv-red`): remover item, desconto, fiscal desligado. **Amarelo** (`pdv-yellow`): ícone de cartão de crédito.

### Named Rules
**The One Blue Rule.** O ERP tem um único azul de marca: `primary`. Toda classe Bootstrap que teria azul próprio (`.btn-primary`, `.text-primary`, `.bg-primary`, `.page-link`, `.nav-pills`, `.nav-tabs`, `.form-check-input:checked`, `.progress-bar`) foi redirecionada para ele em `erp.css`. Não introduza outro azul em telas do ERP; o `#3b82f6` pertence ao PDV.

**The Light-Plus-Dark Rule.** Um status nunca é cor sobre cor. É sempre fundo `*-light` com texto `*-text` (e, no badge, um ponto de 6px na cor `*` base). Texto na cor base (`#059669`, `#d97706`) só sobre branco em ícones de stat-card, nunca como texto corrido.

**The Two Worlds Rule.** O PDV é escuro e usa seus próprios tokens (`pdv-*`); o ERP é claro e usa `primary` + ardósia. Uma tela nova herda o mundo do layout que a envolve: `layouts/app` é ERP, `pdv/index` é PDV. Não misture.

## Typography

**Display Font:** nenhum (não há webfont)
**Body Font:** system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif
**Label/Mono Font:** nenhum distinto; números usam `font-variant-numeric: tabular-nums` na mesma família

**Character:** A fonte do sistema do usuário, sem download e sem personalidade de marca; a hierarquia vem só de peso, tamanho, caixa alta e espaçamento de letras. Resultado: o ERP se parece com um aplicativo nativo do computador da loja.

### Hierarchy
- **Headline** (700, 1.5rem, 1.25, `-0.01em`): o título de página, sempre um `h1` dentro de `page-header`, opcionalmente precedido de um ícone Bootstrap Icons. É o único texto grande do ERP; não há tamanho acima dele fora do PDV.
- **Stat Value** (800, 1.5rem, 1.2): o número do stat-card. Cai para 1.25rem abaixo de 768px.
- **Title** (600, 0.95rem): cabeçalho de cartão e de seção de formulário; título do convite PWA.
- **Body** (400, 1rem, 1.5): texto de corpo herdado do Bootstrap.
- **Body Dense** (400/600, 0.875rem): o tamanho de trabalho do ERP: tabelas, subtítulo de página, botões (`btn-erp` a 600), texto de breadcrumb. Campos de formulário ficam em 0.9rem.
- **Label** (600, 0.8rem, caixa alta, `0.03em`): rótulo de campo (`form-label`) e rótulo de stat-card; a 700 e `0.05em` vira `section-title` (divisor de seção com borda inferior de 2px).
- **Table Head** (600, 0.75rem, caixa alta, `0.04em`): cabeçalho de tabela em `text-secondary` sobre `bg-table-head`. O mesmo tamanho a 600, sem caixa alta, é o texto do badge de status e da tendência de stat-card.
- **Sidebar** (500, 0.8125rem): link de navegação; títulos de grupo da sidebar a 700, 0.6875rem, caixa alta, `0.08em`; submenu a 400, 0.775rem.
- **PDV Total** (800, 2.4rem, 1, tabular): o valor total da venda no PDV; cai para 1.9rem abaixo de 768px. Preço e total de item a 700.

### Named Rules
**The System Face Rule.** Nenhuma tela carrega fonte própria. Se precisar de ênfase, use peso (600/700/800), caixa alta pequena com espaçamento de 0.03–0.05em, ou `text-secondary`/`text-muted` — nunca uma família nova.

**The Tabular Numbers Rule.** Todo número em tabela, relógio e total recebe `font-variant-numeric: tabular-nums` para que colunas de valores alinhem.

## Layout

O ERP é um shell de duas colunas fixas: barra lateral de 250px à esquerda (fixa, rolável, `z-index` 1040) e `main-wrapper` com `margin-left: 250px`. No topo do conteúdo, uma topbar branca de 56px, sticky, com sombra leve (`0 1px 3px rgba(0,0,0,.06)`), contendo o botão de abrir menu (só no mobile), breadcrumb, chip da unidade ativa com ponto verde, sino de notificações e menu do usuário. O conteúdo tem padding de 1.5rem.

Dentro da página, a ordem é sempre: `page-header` (h1 + subtítulo + ações à direita, margem inferior de 1.5rem) → opcional linha de `stat-card`s → `filter-bar` (margem inferior de 1rem) → `data-table` ou `erp-card`s. Seções de formulário são `erp-card`s com `mb-4`. Grid e espaçamento entre colunas vêm do Bootstrap (`row g-2` na barra de filtros, `d-flex gap-2/gap-3`).

Ritmo de espaçamento observado: 0.35rem (gap de ícone/badge), 0.5rem (gap de botão, padding da nav), 0.75rem (célula de tabela), 1rem (padding de cabeçalho, margem de filtros), 1.25rem (corpo de cartão, stat-card, padding da topbar), 1.5rem (conteúdo, margem do page-header), 3rem (estado vazio).

Breakpoints do shell (Bootstrap):
- **≤ 991.98px**: a barra lateral sai da tela (`translateX(-100%)`) e volta como off-canvas com backdrop `rgba(15,23,42,.6)`; `main-wrapper` perde a margem; o botão de menu aparece na topbar.
- **≤ 768px** (`erp.css`): `page-header` empilha em coluna, cabeçalho de cartão quebra linha, stat-value encolhe para 1.25rem, botões de ação em tabela quebram linha.
- **≤ 575.98px**: conteúdo com padding de 1rem, topbar com padding de 0.75rem, nome do usuário some da topbar.

PDV: tela inteira sem rolagem (`html, body { overflow: hidden }`), topbar de 52px, barra inferior de atalhos de 36px, e o restante dividido em 65% (busca + itens) / 35% (cliente, resumo, pagamentos, Finalizar). Abaixo de **767.98px** os painéis empilham, a página volta a rolar, o botão Finalizar vira sticky no rodapé e a legenda de atalhos F1–F12 some.

## Elevation & Depth

Sistema de camadas tonais com sombras mínimas. No ERP, a profundidade vem da borda `border` de 1px e da diferença entre `bg-page` e `bg-card`; a sombra é um realce quase imperceptível, não um elemento estrutural. A barra lateral não tem sombra: ela é simplesmente mais escura. O PDV não usa sombra em superfícies de repouso; usa três tons de ardósia (`pdv-bg` → `pdv-surface` → `pdv-surface-raised`) para separar camadas, e uma sombra pesada apenas no dropdown de busca.

### Shadow Vocabulary
- **Repouso** (`box-shadow: 0 1px 2px rgba(0,0,0,0.05)`, token `--shadow-sm`): stat-card, erp-card, filter-bar. A sombra padrão de qualquer superfície branca.
- **Padrão** (`0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06)`, token `--shadow`): definido, reservado para elementos que precisam de um grau a mais que o repouso.
- **Erguido** (`0 4px 6px rgba(0,0,0,0.07), 0 2px 4px rgba(0,0,0,0.06)`, token `--shadow-md`): hover do stat-card, junto com `translateY(-1px)`.
- **Flutuante** (`0 10px 15px rgba(0,0,0,0.1), 0 4px 6px rgba(0,0,0,0.05)`, token `--shadow-lg`): o maior grau do sistema de tokens.
- **Dropdown** (`0 10px 40px rgba(0,0,0,0.1)`): menus do Bootstrap na topbar; versão escura `0 10px 40px rgba(0,0,0,0.5)` no dropdown de busca do PDV.
- **Convite PWA** (`0 12px 32px rgba(15,23,42,.16)`): a caixa fixa no canto inferior direito.
- **Anel de foco** (`0 0 0 3px rgba(79,70,229,0.25)`): campos de formulário e checkboxes em foco; inválido usa `rgba(220,38,38,0.1)`.

### Named Rules
**The Border-First Rule.** Uma superfície se separa do fundo primeiro pela borda de 1px em `border` e pelo branco sobre ardósia; a sombra `--shadow-sm` é complemento. Nunca use sombra sem borda em um cartão do ERP.

**The Hover-Lifts Rule.** O único movimento de profundidade em repouso → hover é o stat-card (`--shadow-sm` → `--shadow-md`, `translateY(-1px)`); com `prefers-reduced-motion`, o deslocamento some e só a sombra muda.

## Shapes

Cantos arredondados em três degraus de `rem` mais dois pequenos em pixel. Cartões, filter-bar e stat-card usam `lg` (0.75rem). Botões, campos, ícone de stat-card e botões de ação em tabela usam `md` (0.5rem). Links da barra lateral, chip de unidade e `topbar-toggler` usam `sm` (6px); avatares, botões de ícone da topbar e dropdowns usam 8px; itens de dropdown 5px; anel de foco em `:focus-visible` arredonda a 4px. `xl` (1rem) está definido e aparece no ícone de marca do login. Badges de status são pílulas (`999px`) com um ponto circular de 6px à esquerda. O item ativo da sidebar leva uma barra de 3px à esquerda com cantos `0 3px 3px 0`.

Bordas: sempre 1px sólido em `border`, exceto cabeçalho de tabela (2px inferior) e divisor de `section-title` (2px em `border-light`). No PDV, campos e botões de pagamento usam 2px em `pdv-border`, raio 10px; Finalizar usa 12px; quantidade 8px/6px. O login é o mais arredondado do produto: cartão a 1.25rem, campos e botão a 0.75rem.

## Components

### Buttons
Firmes, baixos e sem sombra: o botão é um retângulo de 0.5rem de raio que muda de cor, não de forma.
- **Shape:** cantos médios (`0.5rem`), `inline-flex` com gap de 0.4rem entre ícone e texto, 600, 0.875rem, padding `0.5rem 1rem`.
- **Primary** (`btn-erp-primary` e Bootstrap `btn-primary`): `primary` com texto branco, sem borda.
- **Hover / Focus:** fundo `primary-hover`; transição de 0.15s em cor, borda e sombra. Foco de teclado: outline 2px em `primary`, offset de 3px nos `.btn`.
- **Outline** (`btn-erp-outline`): branco, borda `border`, texto `text-secondary`; hover vai para `bg-table-head`, borda `border-hover`, texto `text-primary`. É o botão da barra de filtros ("Filtrar").
- **Danger / Success** (`btn-erp-danger`, `btn-erp-success`): `danger` / `success` com texto branco; sem hover próprio definido.
- **Ação em tabela** (`action-btns .btn`): padding `0.3rem 0.5rem`, 0.8rem, alinhados à direita com gap de 0.35rem.
- **PDV Finalizar**: largura total, 16px de padding, `pdv-green` com texto preto, 800, 1.2rem, raio 12px; hover `#16a34a` com `translateY(-1px)` e brilho verde; desabilitado vira `pdv-surface-raised`.
- **Login**: gradiente `primary → primary-hover`, raio 0.75rem, sombra índigo `0 4px 16px rgba(79,70,229,.3)`; é o único botão com gradiente no produto.

### Chips (status badges)
- **Style:** pílula `inline-flex` com ponto de 6px à esquerda, 600, 0.75rem, padding `0.3rem 0.65rem`, line-height 1.
- **State:** a classe é o próprio valor do status (`ativo`, `pendente`, `cancelado`, `em_andamento`…) e mapeia para uma das famílias: verde (ativo/paga/concluída/autorizada), âmbar (pendente/em aberto/rascunho/aberto/em implantação), vermelho (cancelado/rejeitada/bloqueado/vencida/vencido), ciano (em andamento/confirmado/aprovado/faturado/convertido), índigo (entregue), neutro (inativo/fechado/suspenso: `bg-page` + `text-secondary`, ponto `text-muted`). O componente `x-erp.status-badge` traduz o valor para o rótulo em português.

### Cards / Containers
- **Corner Style:** `0.75rem`.
- **Background:** `bg-card`; cabeçalho também branco, separado por borda inferior de 1px.
- **Shadow Strategy:** `--shadow-sm` em repouso (ver Elevation).
- **Border:** 1px `border`; `overflow: hidden` para a tabela respeitar o raio.
- **Internal Padding:** corpo 1.25rem; cabeçalho `1rem 1.25rem` com título a 600/0.95rem e ícone opcional.
- **Stat Card:** mesmo cartão com ícone de 48px (raio 0.5rem, fundo `*-light`, ícone na cor base), valor a 800/1.5rem, rótulo em caixa alta 0.8rem `text-muted`, tendência opcional a 0.75rem em `success-text`/`danger-text`. Hover ergue.
- **Filter Bar:** mesmo cartão com padding `1rem 1.25rem`, formulário GET em `row g-2 align-items-end`, botão outline "Filtrar" ao fim.
- **Data Table:** `erp-card` envolvendo `table-responsive > table.erp-table`; paginação opcional em `p-3 border-top`.
- **Empty State:** centrado, padding `3rem 1rem`, ícone a 3rem com opacidade 0.4, `h5` a 600 em `text-secondary`, botão primário opcional.

### Inputs / Fields
- **Style:** borda 1px `border`, raio 0.5rem, padding `0.6rem 0.85rem`, 0.9rem, fundo branco. Rótulo em caixa alta 600/0.8rem `text-secondary` com margem inferior de 0.35rem; obrigatório recebe ` *` em `danger` via `.required::after`. Texto de ajuda a 0.78rem `text-muted`.
- **Focus:** borda `primary` + anel `0 0 0 3px rgba(79,70,229,.25)`; o outline de `:focus-visible` é suprimido nos campos porque o anel já existe.
- **Error / Disabled:** `is-invalid` muda a borda para `danger` e o anel para `rgba(220,38,38,.1)`.
- **Toque:** em `pointer: coarse`, checkbox 1.25em e switch 2.75em × 1.5em.
- **PDV:** campo de busca a 1.2rem, borda 2px `pdv-border`, raio 10px, fundo `pdv-surface`; foco em `pdv-accent` com anel `rgba(59,130,246,.35)`.
- **Login:** campo translúcido (`rgba(255,255,255,.06)`), raio 0.75rem, ícone à esquerda; foco em `primary-glow` com anel `rgba(79,70,229,.35)`.

### Navigation
- **Sidebar:** 250px, `bg-sidebar`, marca de 56px com ícone índigo de 32px (raio 8px). Grupos com título em caixa alta 0.6875rem a 50% de branco. Link: 500, 0.8125rem, raio 6px, padding `0.5rem 0.75rem`, ícone de 1.05rem a 75% de opacidade; hover `rgba(255,255,255,.06)`; ativo `rgba(79,70,229,.3)` com borda `rgba(79,70,229,.2)` e barra esquerda de 3px em `primary-glow`. Submenu a 0.775rem/400, recuado 2.625rem. Atalho PDV em gradiente índigo→violeta, 600. Rodapé em `bg-sidebar-footer` com avatar índigo de 34px.
- **Topbar:** 56px branca, sticky, breadcrumb a 0.875rem em `text-muted` com página atual a 600 em `#1e293b`; chip de unidade `bg-table-head` com ponto verde `#22c55e`; sino de 36px com badge vermelho `#ef4444` de 8px; menu do usuário com avatar índigo de 32px.
- **Mobile:** abaixo de 991.98px a sidebar vira off-canvas com backdrop e o `topbar-toggler` aparece.
- **PDV topbar:** 52px em `pdv-surface`, marca em `pdv-accent`, relógio tabular 700/1.1rem, chip fiscal pílula verde/vermelho a 15% de opacidade.

### Page Header (signature)
`h1` a 700/1.5rem com ícone opcional, subtítulo a 0.875rem `text-secondary`, slot de breadcrumb, e ações em `d-flex gap-2 flex-wrap` à direita; abaixo de 768px empilha. Entra com `fade-in` (0.3s, 8px de subida), que some com `prefers-reduced-motion`.

### PWA Install Invite (signature)
Caixa fixa no canto inferior direito, 400px máx., raio 14px, sombra `0 12px 32px rgba(15,23,42,.16)`, marca índigo de 40px (raio 10px), botão índigo e botão fantasma `text-secondary`, passos numerados em círculos índigo de 18px sobre `bg-table-head`. Não é modal; só aparece a partir da 3ª tela da sessão.

## Do's and Don'ts

### Do:
- **Do** usar os componentes `x-erp.*` (page-header, stat-card, card, data-table, filter-bar, status-badge, form-section, empty-state) para qualquer tela nova do ERP; eles já carregam raio, borda, sombra e tipografia certos.
- **Do** colocar o título de página em `h1` dentro de `page-header` a 1.5rem; é o único headline do ERP.
- **Do** expressar status como par `*-light` + `*-text` (ou `badge-status` com a classe do valor), nunca a cor base como texto.
- **Do** usar `primary` (`#4f46e5`) e `primary-hover` (`#4338ca`) para toda ação principal, inclusive via classes Bootstrap `btn-primary` / `btn-outline-primary`.
- **Do** manter o foco visível: outline de 2px em `primary` com offset de 2px (3px em `.btn`); campos já têm o anel de 3px.
- **Do** respeitar `prefers-reduced-motion` em qualquer animação nova (as existentes: `fade-in`, `alertSlideIn`, `pwaSobe`, hover do stat-card) e aumentar alvos de toque em `pointer: coarse`.
- **Do** usar Bootstrap Icons (`bi bi-*`) para todo ícone; tamanhos observados: 1rem em botão, 1.05rem na sidebar, 1.25rem em stat-icon, 3rem em estado vazio.
- **Do** aplicar `tabular-nums` em colunas numéricas, totais e relógio.

### Don't:
- **Don't** carregar webfont ou declarar `font-family` nova; a pilha é a do sistema.
- **Don't** introduzir um segundo azul no ERP (o `#3b82f6` é token do PDV) nem usar `primary-glow` (`#818cf8`) sobre branco.
- **Don't** usar os tokens `pdv-*` fora de `app/pdv/index.blade.php`, nem os tokens claros do ERP dentro do PDV.
- **Don't** usar sombra sem borda em cartões, nem sombras acima de `--shadow-lg`; o produto é de camadas tonais com sombra mínima.
- **Don't** criar raios fora da escala (4/5/6/8px, 0.5/0.75/1rem, pílula); o PDV tem seus próprios 8/10/12px.
- **Don't** usar gradiente em botões do ERP; o único gradiente de botão é o do login, e o único gradiente de navegação é o atalho PDV na sidebar.
