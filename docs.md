# ERP Comercial IA365 — Documentação Técnica

> SaaS ERP multi-tenant para PMEs. Admin (IA365) gerencia a plataforma; cada empresa-cliente tem múltiplas unidades com fiscal, estoque e caixa independentes. Integração 100% Focus NFe (NF-e, NFC-e, NFS-e, CC-e, manifestação do destinatário, backup XMLs).

**Última revisão:** 2026-08-12 (**API de Integração v1 — Gersen**: primeira API externa do ERP, somente leitura, token por empresa gerado no admin; seção própria) · 2026-08-12 madrugada (**backup mensal de XMLs virou pacote LOCAL** — o `/v2/backups` da Focus não existe, armadilha 49; **DONA DOURO em `producao`** com série 2 e CSC na Focus) · 2026-08-12 noite (**editor visual de layout de etiqueta** — arrasta-e-solta com imagens e formas, branch `layout-etiquetas` DEPLOYADA em produção; armadilha 48 + lição de deploy na 26b) · 2026-08-12 (vários estoques por loja + contagem cega + bonificação que deve voltar + estilo de etiqueta "nome no topo" + conferência de bobina; armadilhas 43-47; **imagem rebuildada** e main promovida) · 2026-08-11 (formato de etiqueta cadastrável pelo lojista + fix do CRUD de categorias — `status` feminino, armadilha 42) · 2026-08-05 (filtro por loja em vendas + imports de vendas/contas a receber + import robusto + lojas mesmo CNPJ compartilham empresa Focus) · **Estado:** integração fiscal Fase 1-4 + multi-loja + regime de cobrança + auto-sync Focus + UX config fiscal + caixa por forma de pagamento (14/07) + Configurações da Loja/tabelas de preço/emissão parametrizada/adquirentes (24/07) + **Reforma Tributária NT 2025.002 (obrigatório 03/08/2026) + CNPJ alfanumérico NT 2025.001 (25/07)** + **e-mails/no-reply + cobrança direta mensal/anual com bloqueio + pode_ver_financeiro (04/08)** + **doc de alterações do Dennis (05/08)** + **etiqueta cadastrável pelo lojista em cm + fix do CRUD de categorias + auditoria de produção (11/08)** — concluídos

---

## Sumário
1. [Stack e ambientes](#stack-e-ambientes)
2. [Multi-tenancy](#multi-tenancy)
3. [Integração fiscal Focus NFe](#integração-fiscal-focus-nfe)
4. [Multi-loja — política de estoque](#multi-loja--política-de-estoque)
5. [Regime de cobrança](#regime-de-cobrança--cortesiaparceiropós-pago-commit-a2121bf)
5b. [Configurações da Loja, tabelas de preço, emissão e caixa (Julho/2026)](#configurações-da-loja-tabelas-de-preço-emissão-e-caixa-julho2026)
6. [Schema essencial](#schema-essencial)
7. [Comandos artisan](#comandos-artisan)
8. [Crons agendados](#crons-agendados)
9. [Logins demo](#logins-demo)
9b. [Vários estoques por loja](#vários-estoques-por-loja-12082026)
9c. [Relatório de contagem cega](#relatório-de-contagem-cega-12082026)
9d. [Bonificação que deve voltar](#bonificação-que-deve-voltar--peças-em-poder-de-terceiros-12082026)
9e. [API de Integração — Gersen](#api-de-integração-gersen-12082026)
10. [Armadilhas conhecidas](#armadilhas-conhecidas)
11. [Próximos passos](#próximos-passos)

---

## Stack e ambientes

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 12, PHP 8.4 |
| Banco | MySQL 8.0 |
| Cache/Fila | Redis 7 |
| Frontend | Blade + Bootstrap 5.3 (CDN) + Chart.js + JsBarcode |
| Audit | spatie/laravel-activitylog |
| Fiscal | Focus NFe (REST v2) |
| Proxy | Caddy (host) |

### Containers em produção

Compose ativo: `docker-compose.prod.yml` (não o `.yml` padrão de dev).

| Container | Porta host | Porta interna |
|---|---|---|
| `erp-com-app` | 8091 | 80 |
| `erp-com-mysql` | 127.0.0.1:3310 | 3306 |
| `erp-com-redis` | 127.0.0.1:6381 | 6379 |

```bash
# .env não é bind-mounted em prod — usar docker cp
docker cp .env erp-com-app:/var/www/.env
docker exec -i erp-com-app php artisan config:clear

# Aplicar código novo + migrations
tar cf - app database resources routes config | docker exec -i erp-com-app tar xf - -C /var/www/
docker exec -i erp-com-app php artisan migrate --force

# Tarefas operacionais
docker exec -i erp-com-app php artisan schedule:list
docker exec -i erp-com-app php artisan tinker
```

### Variáveis sensíveis no .env

```env
APP_URL=https://erp.ia365.com.br
APP_PORT=8091

# Focus NFe — token master (modelo revenda)
FOCUS_MASTER_TOKEN=rriUTW5kJHPHmoNBmqyIxjDnae5raCn3
FOCUS_NFE_AMBIENTE=homologacao
FOCUS_WEBHOOK_BASE_URL=${APP_URL}
```

---

## Multi-tenancy

- **`empresa_id`** em TODAS as tabelas de dados. Trait `BelongsToEmpresa` + scope `EmpresaScope` (com flag anti-recursão `static $applying`).
- **`unidade_id`** em tabelas operacionais (vendas, estoque, caixa, notas, config fiscal). Trait `BelongsToUnidade` + scope `UnidadeScope`.
- Admin/Dono enxergam todas as unidades; outros perfis ficam scoped.
- `session('unidade_id')` define a unidade ativa do usuário.

### RBAC

7 perfis (`App\Enums\Perfil`): `admin` (100), `dono` (90), `gerente` (70), `financeiro` (60), `vendedor` (50), `caixa` (40), `consulta` (10).

Middleware: `permission:modulo,acao` (default `ver`) + `plano:feature` (gating por plano).

---

## Integração fiscal Focus NFe

### Arquitetura — modelo revenda

A plataforma IA365 opera como **revenda Focus**: 1 token master (`FOCUS_MASTER_TOKEN`) cria empresas-filhas via `POST /v2/empresas`. Cada empresa-cliente recebe seus próprios tokens (homologação + produção) que são usados nas emissões.

### Auto-provisionamento (Fase 1 — commit `305a950`)

Quando admin cria uma empresa ou unidade no CRUD comum, o sistema dispara `ProvisionarEmpresaFocusJob` que:

1. `POST /v2/empresas` na Focus (ou `PUT` se já existe — idempotente)
2. Persiste `focus_empresa_id`, `focus_token_producao`, `focus_token_homologacao` em `configuracoes_fiscais`
3. Sincroniza webhooks: **1 chamada `POST /v2/hooks` por evento** (Focus NÃO aceita array em `eventos`, é `event` singular)
4. Persiste mapa evento→hook_id em `configuracoes_fiscais.focus_webhook_ids` (JSON)
5. Notifica o dono no sino quando concluído ou falha

**Eventos suportados** (`FocusEmpresaService::EVENTOS_SUPORTADOS`):
- `nfe`, `nfse`, `nfce_contingencia`, `nfe_recebida`, `nfse_recebida`, `inutilizacao`, `cte`, `mdfe`

**Página de saúde**: `/admin/empresas/{id}/saude-focus` mostra status por unidade + botão Resincronizar / Provisionar N pendentes.

### Pipeline de emissão (Fase 2 + 3 — commit `5c1ded5`)

`FiscalPayloadBuilder` (`app/Services/FocusNFe/`) centraliza a montagem do payload. Estrutura comum: emitente, destinatário, responsável técnico, itens, totais, pagamentos.

**Componentes plugados:**

| Componente | Responsabilidade |
|---|---|
| `ICMSCalculator::calcular` | ICMS-ST interestadual quando CST de ST e UF origem ≠ destino |
| `ReformaTributariaCalculator::blocoPayload` | IBS/CBS/IS por item, gated por flags em `configuracoes_fiscais` |
| `FiscalAutoConfig::defaults` | Presets CST/CFOP/alíquotas por regime tributário |
| `VendaItem::fiscal()` | Lê snapshot fiscal com fallback no produto atual |

**Pre-flight validation** (rejeita antes de chamar Focus):
- NCM ≠ `00000000`
- CFOP definido
- Destinatário NF-e com endereço completo
- Responsável técnico configurado (NT 2018/003)

### Campos do payload por tipo de nota

| Campo | NF-e | NFC-e | NFS-e |
|---|---|---|---|
| `cnpj_responsavel_tecnico` + bloco | ✅ | ✅ opcional | — |
| `local_destino` | ✅ | ✅ | — |
| `indicador_intermediario` | ✅ | — | — |
| `valor_total_tributos` (IBPT) | ✅ | ✅ | — |
| `valor_troco` | — | ✅ | — |
| ICMS-ST + FCP-ST | ✅ | ✅ | — |
| IBS / CBS / IS (Reforma) | ✅ | ✅ | ✅ (Nacional) |
| Bloco DI (importação) | ✅ | ✅ | — |
| `numero_fatura` + `duplicatas[]` | ✅ se a prazo | — | — |
| Aninhamento `prestador/tomador/servico` | — | — | ✅ |
| `codigo_municipio` IBGE 7 dígitos | — | — | ✅ |

### Snapshot fiscal (Fase 3 — commit `5c1ded5`)

Para que re-emissões e auditoria reflitam o estado fiscal no **momento da venda** (não o estado atual do produto), `VendaItemObserver` copia automaticamente os campos fiscais do produto para o item ao criar:

```
snapshot_ncm, snapshot_cest, snapshot_cfop, snapshot_cst_csosn,
snapshot_cst_pis, snapshot_cst_cofins, snapshot_cst_ipi,
snapshot_origem, snapshot_icms_aliquota, snapshot_pis_aliquota,
snapshot_cofins_aliquota, snapshot_ipi_aliquota, snapshot_unidade_medida
```

O builder consulta via `$item->fiscal('ncm')` — com fallback no produto se snapshot vazio.

### Robustez operacional (Fase 4 — commit `56b1616`)

- **Backup XMLs** (`fiscal:backup-xmls`, diário 3h): monta LOCALMENTE o pacote mensal por
  CNPJ a partir das cópias por nota (`BaixarXmlNotaJob`/`fiscal:baixar-xmls-notas`) em
  `storage/app/private/fiscal/backups/{cnpj}/{YYYY-MM}.zip` (XMLs + `manifest.json`;
  sem `--mes`, remonta mês corrente + anterior; lojas do mesmo CNPJ no mesmo zip).
  Retenção 5 anos. Download do cliente em `/app/backups-xml` (rota `download/{mes}` serve
  do nosso disco). **Não fala mais com a Focus** — ver armadilha 49.
- **Saúde de webhooks** (`fiscal:saude-webhooks`, segunda 4h): compara `focus_webhook_ids` locais vs lista remota Focus; recadastra ausentes; notifica dono.
- **Alerta certificado** (`fiscal:alertar-certificado`, diário 8h): janelas 30/15/7/1 dias antes do vencimento.
- **Dashboard fiscal** `/app/fiscal/dashboard`: NF-es por status (30d), top 5 erros, série diária 14d (Chart.js), saúde por unidade, status SEFAZ por UF.

### Auto-sincronização total com Focus (commit `b5487bd`)

Quando há `FOCUS_MASTER_TOKEN`, toda mudança na configuração fiscal propaga automaticamente para a Focus — sem precisar colar token manualmente em nenhum momento.

| Ação no ERP | O que acontece na Focus |
|---|---|
| Admin cria nova empresa | `POST /v2/empresas` + 1 `POST /v2/hooks` por evento aplicável (idempotente) |
| Marca `emite_nfse=true` na config | `PUT /v2/empresas/{id}` com `habilita_nfse=true` + recadastra hooks `nfse`/`nfse_recebida` |
| Preenche CSC NFC-e | `PUT /v2/empresas/{id}` com `csc_nfce_producao` + `id_token_nfce_producao` |
| Edita endereço/IE/IM | `PUT /v2/empresas/{id}` via `ProvisionarEmpresaFocusJob` |
| Há empresas legadas com `focus_token` colado | `php artisan fiscal:migrar-empresas-legadas` migra todas |

**Bug fixado:** webhook ID da Focus é **string alfanumérica** (ex: `Y5P0na15`), não inteiro. `(int) $data['id']` virava 0 e quebrava sincronizações silenciosamente. Corrigido para `(string)`.

### UX da config fiscal (commits `2097213` + `de8bb16`)

- **Checklist de prontidão** no topo da tela: cards por tipo de nota (NF-e/NFC-e/NFS-e) com progresso `X/Y` + lista visual de itens prontos/faltando + "Pronto para emitir!" quando 100%.
- **Badges `★ obrigatório`** nos campos críticos (CSC, ID CSC, Série, Resp.Técnico) — usuário não confunde com opcionais.
- **Texto explicativo** no card Responsável Técnico ("geralmente o dono, contador, ou TI da empresa") + botão **"Usar meus dados"** auto-preenche CNPJ empresa + nome + email do user logado em 1 clique.
- **Mensagem SEFAZ status** mais amigável quando empresa ainda não tem token ("Aguardando provisionamento" em vez de erro técnico).

### Reforma Tributária — IBS/CBS obrigatórios 03/08/2026 (NT 2025.002 v1.40) — branch `feat/reforma-agosto2026-cnpj-alfanumerico` (25/07)

**Contexto legal:** a partir de **03/08/2026** a SEFAZ **rejeita** NF-e/NFC-e do regime
normal (CRT=3 — Lucro Presumido/Real) sem os grupos IBS/CBS (UB/W03). Simples Nacional
entra em **04/01/2027**. Desde 01/07/2026 os campos já são exigidos em homologação.

**O que mudou no ERP:**

- **Envio automático**: `ReformaTributariaCalculator::paraEmissao($config, $empresa)` liga o
  bloco IBS/CBS para toda empresa `lucro_presumido`/`lucro_real`, **independente das flags**
  `ibs_ativo`/`cbs_ativo` (que agora servem para antecipar o envio no Simples). IS continua
  só por flag. Vale para NF-e, NFC-e e NFS-e nacional.
- **Alíquotas-teste 2026 corrigidas** (estavam INVERTIDAS): **CBS 0,9% + IBS 0,1%**
  (LC 214/2025; IBS integral na esfera estadual em 2026, municipal = 0). Migration
  `2026_07_25_120000` anula o par invertido persistido em `configuracoes_fiscais`.
- **Payload no formato flat da Focus** (antes eram arrays aninhados `ibs`/`cbs` que a Focus
  ignorava): item ganha `ibs_cbs_situacao_tributaria` (CST, default `000`),
  `ibs_cbs_classificacao_tributaria` (cClassTrib, default `000001`), `ibs_cbs_base_calculo`,
  `ibs_uf_aliquota/valor`, `ibs_mun_aliquota/valor`, `ibs_valor_total`, `cbs_aliquota/valor`
  (+ `is_*` se IS ativo). Totais: `ibs_cbs_base_calculo`, `ibs_uf_valor_total`,
  `ibs_mun_valor_total`, `ibs_valor_total`, `cbs_valor_total`, `is_valor_total`
  (só entram quando os itens carregam o grupo). Na NFS-e nacional os campos flat vão dentro
  de `servico` (substituiu `servico.tributos_reforma`).
- **Overrides por produto** continuam: `produtos.ibs_aliquota/cbs_aliquota/is_aliquota`,
  `cst_ibs_cbs`, `classificacao_ibs` (formulário de produto já expõe).
- **UI**: tela de Configuração Fiscal explica o envio automático + alíquotas corretas;
  tooltips de IBS/CBS atualizados; defaults dos inputs de alíquota agora em branco
  (= usa valores legais).
- **Regime tributário configurável pelo cliente (25/07)**: card "Regime tributário da
  empresa" no topo da Configuração Fiscal — **só o perfil dono** altera (outros veem
  read-only), com `data-confirm` de impacto e flash orientando o contador. Rota
  `PUT app/configuracao-fiscal/regime` → `ConfiguracaoFiscalController::atualizarRegime`.
  Trocar para Presumido/Real liga o IBS/CBS automático na nota seguinte; voltar ao Simples
  desliga (obrigação 01/2027). Validado E2E via curl (dono muda → badge "Envio automático
  ativo" → volta). ⚠️ Blade: dois `@php(...)` inline consecutivos não compilam — usar
  bloco `@php ... @endphp` (quebrou a tela em produção por minutos até o fix).
- **Varredura E2E rodada 2 (telas de detalhe, 25/07)**: 46 rotas `GET` com parâmetro
  testadas com IDs reais → 10×500 + achado grave via scanner de reflexão (rota × assinatura
  do controller): **route model binding quebrado em 13 rotas** — parâmetro da URL não casava
  com o nome da variável (`{contas_pagar}` vs `$contaPagar` etc.), o Laravel injetava model
  VAZIO em silêncio. Consequência real: **baixar/excluir contas a pagar/receber nunca
  funcionou**, editar plano de contas e ordens de serviço (show/edit/update/destroy)
  operavam num registro vazio. Fix: `->parameters([...])` nos resources
  (movimentacoes→movimentacao, contas-receber→contaReceber, contas-pagar→contaPagar,
  plano-contas→planoContas, ordens-servico→ordemServico) + rotas custom `baixar` renomeadas.
  Rotas `resource` para métodos inexistentes removidas com `->except`/`->only`
  (vendas edit/update, contas edit/update, movimentações edit/update/destroy, planos show,
  transferências edit/update, plano-contas/centros-custo show); `admin/unidades/{id}` show →
  redirect ao edit (view nunca existiu). Scanner final: ZERO bindings quebrados/métodos
  ausentes; sweep: zero 5xx nas 2 personas. ⚠️ Armadilha: em `Route::resource` SEMPRE conferir
  se o parâmetro (singular do slug) casa com o nome da variável tipada do controller —
  binding que não casa NÃO dá erro, entrega model vazio (Laravel aceita snake_case do nome
  da variável; qualquer outra diferença falha).
- **Varredura E2E 25/07 (107 rotas GET × persona dono e admin)**: 3 bugs 500 corrigidos —
  (1) `ExportController::export` quebrava com enum (`StatusVenda`) → cast `BackedEnum->value`
  (afetava `/app/export/vendas` para clientes); (2) DRE (index/porUnidade/exportar) 500 para
  admin da plataforma → guard armadilha 21; (3) `/app/fiscal/calcular-st` sem parâmetros
  dava TypeError 500 → validação (422). Sweep final: dono 0×5xx, admin 0×5xx. Metodologia:
  usuário QA temporário (removido ao final) + seleção de unidade via `POST /selecionar-unidade`.
- **UI v2 (review 25/07 tarde)**: card da Reforma na config fiscal com **status dinâmico
  por regime** — regime normal: badge verde "Envio automático ativo", alerta verde, chaves
  IBS/CBS viram badges "automático" (hidden inputs preservam o valor persistido); Simples:
  badge "obrigatório em 01/2027" + chaves para antecipar. Tiles com alíquotas em uso
  (padrão da config ou teste legal) + "impacto no cliente R$ 0,00". Cadastro de produto:
  seção Reforma agora aparece **também no regime normal** (era gated só pelas flags) e
  ganhou o campo **cClassTrib (`classificacao_ibs`)** que o controller já validava mas o
  form nunca enviava; CST/cClassTrib primeiro, alíquotas depois, placeholders com os
  defaults. Cupom não-fiscal revisado (usa dados da venda — sem impacto do vProd);
  DANFE/DANFE-NFC-e vêm prontos da Focus com o detalhamento IBS/CBS.

### CNPJ alfanumérico (NT Conjunta 2025.001 — produção desde 06/07/2026)

CNPJs novos podem ter **letras nas 12 primeiras posições** (DV continua numérico,
módulo 11 sobre `ASCII−48`). O que mudou:

- **`App\Support\Cnpj`**: `limpar()` (preserva letras, caixa alta), `limparCpfCnpj()`,
  `pareceCpf()/pareceCnpj()`, `valido()` (DV alfanumérico — validado contra o exemplo
  oficial `12.ABC.345/01DE-35`), `dv()`, `formatar()`.
- **Sweep completo**: todos os `preg_replace('/\D/')` sobre CNPJ trocados por
  `Cnpj::limpar()` (payload builder, services Focus, webhook, commands, imports, filtros).
  Webhook `resolveConfig` compara com `UPPER(...)` no SQL.
- **Validação**: rule `App\Rules\CnpjValido` aplicada em Empresa (create/update) e Unidade.
- **Máscaras JS** (`erp-core.js`): `cnpj`/`cpfCnpj` aceitam letras (qualquer letra ⇒ trata
  como CNPJ); `buscaCNPJ` pula a BrasilAPI para CNPJ alfanumérico (API só numérico).
- **⚠️ Máscaras INLINE fora do erp-core (fix 25/07 madrugada)**: 9 telas tinham máscara
  própria com `replace(/\D/g)` que apagava as letras ao digitar — clientes create/edit,
  fornecedores create/edit, admin empresas create/edit (`maskCNPJ`), admin unidades
  create/edit, pedidos create (`maskCnpj` do cadastro rápido). Todas corrigidas (12 primeiras
  posições alfanuméricas + DV numérico, caixa alta) e lookup BrasilAPI/ReceitaWS pulado
  quando há letra. Validado E2E: cliente PJ criado via POST com `12.ABC.345/01DE-35`,
  exibido e editável. **Ao criar máscara nova de CNPJ, nunca usar `\D` — copiar o padrão
  dessas telas ou usar `data-mask="cnpj"` do erp-core.**

**Estado em produção (25/07):** as 2 empresas ativas são Simples Nacional — nada muda nas
notas de hoje; a plataforma fica pronta para clientes do regime normal e para a virada do
Simples em 2027.

### O que a Focus NÃO consegue gerenciar (limite do SEFAZ)

Mesmo com auto-sync, **estes 3 itens dependem do cliente final** (a Focus é só intermediária):

1. **Certificado A1 (.pfx)**: comprado em AC (Certisign, Serasa, AC SOLUTI — ~R$200/ano). Upload direto pra Focus, não fica no servidor.
2. **CSC NFC-e + ID**: gerado no portal SEFAZ do **estado da empresa-cliente** (e-CAC → menu NFC-e → "Gerar CSC"). Único por estabelecimento.
3. **Responsável Técnico** (NT 2018/003): cada empresa preenche o próprio (decisão arquitetural deste projeto — opção B).

### Arquivos críticos da integração

```
app/Services/FocusNFe/
├── FocusNFeClient.php           # HTTP client + rate limit + per-ambiente
├── FocusEmpresaService.php      # modelo revenda + webhooks (event singular!)
├── FiscalPayloadBuilder.php     # builder central NF-e/NFC-e
├── NFeService.php               # /v2/nfe (assíncrona + polling)
├── NFCeService.php              # /v2/nfce (síncrona)
├── NFSeService.php              # /v2/nfse aninhado prestador/tomador/servico
├── NFSeNacionalService.php      # padrão nacional RFB
├── CertificadoDigitalService.php # upload A1 multipart
├── ManifestacaoService.php      # MDe destinatário
├── ReformaTributariaCalculator.php
├── SefazStatusService.php
├── BackupXmlService.php
├── NFSesRecebidasService.php
└── NFesRecebidasService.php

app/Jobs/
├── ProvisionarEmpresaFocusJob.php
├── EmitirNFeJob.php
├── EmitirNFCeJob.php
├── EmitirNFSeJob.php
├── ConsultarNotaFiscalJob.php
├── SincronizarNFesRecebidasJob.php
└── SincronizarNFSesRecebidasJob.php

app/Console/Commands/
├── BackupXmlsFiscaisCommand.php
├── VerificarSaudeWebhooksCommand.php
├── AlertarCertificadoVencendoCommand.php
└── MigrarEmpresasLegadasFocusCommand.php

app/Observers/
└── VendaItemObserver.php        # snapshot fiscal automático

app/Http/Controllers/Admin/
└── SaudeFocusController.php     # /admin/empresas/{id}/saude-focus

app/Http/Controllers/App/
└── DashboardFiscalController.php # /app/fiscal/dashboard
```

---

## Multi-loja — política de estoque

### O problema

Empresas com múltiplas lojas (`Unidade`) precisavam decidir: filial vê estoque das outras? Pode vender? Como contabilizar?

### Solução (commit `af4d4d9`)

Campo `empresas.politica_estoque_inter_unidade` (enum) com 3 modos:

| Política | Comportamento |
|---|---|
| `silos` | Cada loja só vê o próprio estoque (legado) |
| `ver_apenas` | Visualiza saldo das outras lojas, mas precisa criar Transferência com aprovação |
| `ver_e_vender` *(default)* | PDV pode vender direto de outra unidade — sistema cria `TransferenciaEstoque` automática origem→destino |

Configuração em `/admin/empresas/{id}/edit` (radio cards visuais — só aparece se empresa tem >1 unidade).

### Fluxo no PDV (política `ver_e_vender`)

```mermaid
sequenceDiagram
    Vendedor->>PDV: lê código de barras
    PDV->>API: GET /app/pdv/estoque/{id}
    API-->>PDV: estoque_atual=0, outras_unidades=[Loja B: 5]
    PDV->>Vendedor: modal "Vender da Loja B?"
    Vendedor->>PDV: confirma Loja B
    PDV->>API: POST venda (item.unidade_origem_id=B)
    API->>DB: cria EstoqueMovimentacao saída em Loja B
    API->>DB: cria TransferenciaEstoque (B→A, concluida_venda_remota)
    API->>DB: grava unidade_origem_id no venda_item
```

### Componentes

- **`Empresa::permiteVerEstoqueOutrasUnidades()`** / **`permiteVenderEstoqueRemoto()`** — helpers booleanos
- **`EstoqueMultiUnidadeService`**:
  - `saldoPorUnidade(empresa_id, produto_id, unidade_atual)` → array ordenado (atual primeiro, depois saldo desc)
  - `outrasUnidadesComEstoque()` → filtro de outras unidades com saldo > 0
  - `registrarVendaRemota(venda, vendaItem, origem, userId)` → transaction: baixa estoque + cria transferência
- **`venda_itens.unidade_origem_id`** (FK `unidades`) — null quando venda local
- **`VendaItem::isVendaRemota()`** / **`unidadeOrigem()`** relation

### UI

- **Detalhe do produto** (`/app/produtos/{id}`): tabela "Estoque por Unidade" (só se >1 unidade) com saldo + status (OK/baixo/zerado) + indicador da unidade atual
- **Editar empresa**: 3 cards radio com descrição de cada política
- **PDV**: modal de seleção quando estoque local zerado + badge `↔ Loja X` no item da venda

### Fiscalmente

Cada `Unidade` continua sendo um CNPJ independente na Focus NFe. Empresa com 5 unidades = 5 empresas-filhas Focus = 5 certificados A1 = 5 conjuntos de webhooks. A venda remota é registrada como saída no CNPJ da origem e, fiscalmente, depende da operação:

- Modo simples (atual): venda emitida pela unidade que finalizou (CNPJ do destino) — adequado quando a empresa opera como rede com CNPJs sob mesmo grupo.
- Modo completo (futuro): emitir NF-e de transferência B→A (CFOP 5152/6152) + NF-e/NFC-e de venda A→cliente. Não implementado.

---

## Regime de cobrança — cortesia/parceiro/pós-pago (commit `a2121bf`)

Admin (IA365) pode liberar empresas-cliente do funil normal de trial+pago sem gateway de pagamento. Campo `empresas.regime_cobranca`:

| Regime | Cobra? | Limites do plano | Caso de uso |
|---|---|---|---|
| `padrao` | Sim (trial → pago) | Aplica | Cliente normal |
| `cortesia` | Não | Aplica | Amigo, beta tester, VIP |
| `parceiro` | Não | **Ignora** (tudo liberado) | Cliente-âncora, case |
| `pos_pago` | Manual fora | Aplica | Grandes contas faturadas externamente |

**Como funciona internamente:**
- `Empresa::isAssinaturaAtiva()` retorna `true` direto se `regime_cobranca` ≠ `padrao` → nunca cai em "plano expirado"
- `Empresa::limiteAtingido()` retorna `false` direto se `bypassaLimitesPlano()` (apenas parceiro)
- `CheckPlano` middleware pula feature gate quando parceiro
- `cortesia_concedida_por` registra qual admin aplicou (auditoria)

**UI:**
- `/admin/empresas/{id}/edit` — 4 radio cards visuais (só >1 unidade tem o card de multi-loja, regime de cobrança aparece sempre)
- Listagem `/admin/empresas` — badge colorido ao lado da razão social + botão `+30d` (trial extension)
- Dashboard admin — card "Cortesias / Parceiros" linkando para lista filtrada
- `Empresa::estenderTrial(N)` — extende `trial_fim` em N dias; se já tem assinatura paga, extende `assinatura_fim` em vez de regredir para trial; lança `DomainException` se empresa já é gratuita (não usa trial)

---

## Configurações da Loja, tabelas de preço, emissão e caixa (Julho/2026)

> Branch `feat/config-loja-precos-caixa` (24/07/2026). Origem: doc de melhorias do Dennis.
> **Todos os comportamentos novos nascem desligados/neutros** — loja sem registro em
> `configuracoes_loja` opera exatamente como antes.

### Central de Configurações da Loja (`/app/configuracoes`)

> Tela escrita para lojista leigo (24/07): aviso "nada muda até salvar", comportamento
> ligado×desligado por opção, exemplo numérico das 3 tabelas de preço, exemplos de split por
> combinação de formas, ordem de decisão do documento no PDV e mapa "onde isso aparece".

Tabela `configuracoes_loja` (unique `empresa_id+unidade_id`, mesma regra da config fiscal:
NUNCA `updateOrCreate`). Model `ConfiguracaoLoja::daUnidade()` devolve instância não persistida
com defaults quando a loja nunca salvou (checar `->exists` para distinguir). Parâmetros:

| Campo | Default | Efeito |
|---|---|---|
| `vendedor_responsavel_caixa` | off | vendedor do PDV vira `user_id` das MovimentacaoCaixa da venda |
| `regra_preco_split` | `cartao_maior` | split: `cartao_maior` = maior tabela entre as formas presentes; `sempre_menor`; `sempre_maior` |
| `percentual_debito` / `percentual_credito` | 0 | regra geral das tabelas de preço (acréscimo % sobre o base) |
| `max_parcelas` | 6 | parcelas do crédito no PDV + parcela exibida na etiqueta |
| `cupom_automatico_cartao` | off | venda com cartão emite NFC-e automaticamente |
| `cpf_emite_fiscal` | off | cliente informado na venda → NFC-e na hora |
| `padrao_impressao` | `recibo` | documento default nas demais vendas |

### Tabelas de preço por forma de pagamento

- Base (`produtos.preco_venda`) = tabela **Dinheiro/PIX**. Débito/crédito saem da regra geral
  (percentuais acima) com **override por produto** em `produto_precos`
  (produto_id+modalidade unique; modalidades `dinheiro_pix|debito|credito`).
- `TabelaPrecoService`: `precosDoProduto()` e `modalidadeDosPagamentos()` (venda simples segue
  a forma; split segue `regra_preco_split`). Boleto/crediário/transferência/vale usam a base.
- **PDV**: reprecifica os itens ao escolher a forma (hook em `openPagamento`, reverte se o modal
  fechar sem confirmar), badge "Tabela: Débito/Crédito" no resumo. O servidor recalcula o preço
  autoritativo em `registrarVenda` **somente quando o payload traz `tabela_precos: 1`** (abas do
  PDV abertas antes do deploy continuam funcionando com preço do cliente).
- **Cadastro de produto** (create/edit): campos "Preço no Débito/Crédito" (vazio = regra geral).
  Import/export CSV: colunas opcionais `preco_debito`/`preco_credito`.
- **Etiquetas**: quando tabela crédito > base, saem os valores secos "Cartão R$ X" +
  "PIX R$ Y" (sem parcelamento — pedido do Dennis 25/07); sem configuração a etiqueta
  fica como sempre foi (preço único).

### Emissão parametrizada

- **PDV**: seletor Auto/Recibo/Cupom Fiscal acima do FINALIZAR (escolha manual prevalece).
  Modo Auto (só quando a loja TEM registro de config): cartão+`cupom_automatico_cartao` → NFC-e;
  cliente+`cpf_emite_fiscal` → NFC-e; senão `padrao_impressao`. Sem registro → comportamento
  antigo (fiscal ativo = sempre NFC-e). Falha de NFC-e continua caindo no recibo.
- **Pedidos**: "Faturar Pedido" abre modal com **recibo / cupom fiscal (NFC-e) / nota fiscal
  (NF-e mod. 55) / nenhum**. Faturamento gera `Venda` (tipo `'pedido'`, `vendas.pedido_id`) que
  carrega o documento e aparece nos relatórios. NF-e autorizada (webhook OU polling — hook em
  `NotaFiscal::booted`) dispara `EnviarEmailNotaFiscalJob` → XML+DANFE por e-mail ao cliente
  via Focus `/v2/nfe/{ref}/email`. Rota `vendas/{venda}/recibo` imprime o cupom de qualquer venda.

### Caixa

- **Fechamento**: além do dinheiro (único que fecha gaveta), campos de conferência para
  PIX/débito/crédito e demais formas com movimento — resultado em `caixas.conferencia` JSON
  `{forma: {esperado, contado, diferenca}}`, exibido no extrato (`/app/caixa/{id}`).
- **Comprovantes**: uploads opcionais no fechamento (máquina/crédito/débito) em `caixa_anexos`
  (storage local `caixas/{id}/`, download em `/app/caixa/anexo/{anexo}` com a mesma
  visibilidade do caixa).

### Financeiro — máquinas de cartão (`/app/adquirentes`)

- `adquirente_taxas`: nome, forma (`cartao_debito|credito`), faixa de parcelas, taxa %, prazo D+N.
  `AdquirenteTaxa::paraPagamento()` escolhe a regra ativa mais específica.
- **PDV**: crédito pergunta parcelas (até `max_parcelas`). Cartão com regra cadastrada gera
  ContaReceber **pendente** por parcela (1ª em D+prazo, demais +30d cada) com `taxa_percentual`
  e `valor_liquido`; **sem regra → comportamento antigo** (conta paga à vista, valor cheio).
- Relatório `/app/adquirentes/recebiveis`: bruto × taxas × líquido por período.

### Miudezas

- Relatório de vendas: filtro **Origem** (Vendas PDV/balcão × Pedidos faturados).
- Movimentação de estoque: multi-itens no padrão visual da transferência (o `store` agora recebe
  `itens[]`; a movimentação continua 1 registro por produto).

### Ajustes do teste ao vivo do Dennis (24/07, tarde — commits `2926af0..7dc58b9`)

**PDV / emissão**
- Modal **"Qual documento imprimir?"** (Cupom Fiscal × Recibo) na finalização quando o modo Auto
  não tem regra automática decidindo; padrão da loja destacado, Enter confirma. Cartão/CPF com
  flags ligadas seguem emitindo direto, sem pergunta.
- Modal **Faturar Pedido** mostra a prontidão fiscal: opções NFC-e/NF-e desabilitadas com badge
  "indisponível" + lista do que falta (emissão ativa, certificado A1, resp. técnico, CSC,
  habilitar o tipo) + link para a Configuração Fiscal (`PedidoController::show` monta
  `$fiscalPedido`).
- Listagem `/app/notas-fiscais`: botão **Emitir NF-e (DANFE)** (→ vendas) + guia de onde cada
  tipo de nota é emitido (NF-e na venda/pedido, NFC-e no PDV, NFS-e ali).

**Produto**
- Campo único **"Preço no Cartão (Crédito e Débito)"** — grava o mesmo valor nas duas
  modalidades de `produto_precos`; preço base renomeado "Preço à vista (Dinheiro/PIX)".
  Controller ainda aceita `preco_debito`/`preco_credito` (import/integrações).
- **Máscara de dinheiro** nos preços (`data-mask="money"` em input text): formata 1.500,00 ao
  digitar e converte para decimal no submit (listener global em `initMasks`);
  `ERP.moneyToDecimal()` para cálculos (markup usa).
- **SKU sequencial automático** (`SKU-<codigo_interno>`) e **código de barras EAN-13 interno**
  (prefixo 2/in-store: `2 + empresa%1000 (3) + codigo_interno (8) + DV`) quando os campos vêm
  vazios no cadastro.
- **NCM com lista ao clicar**: novo atributo `data-autocomplete-focus` no erp-core lista
  sugestões ao focar sem digitar; endpoint NCM sem termo devolve os NCMs já usados pela empresa
  com a **nomenclatura oficial** (`FocusReferenciasService::ncmDescricao`, cache 30d, nível
  específico da hierarquia; fallback "usado em N produtos" sem cache). Digitando: busca oficial
  — Focus manda a nomenclatura em `descricao_completa` (não `descricao`).

**Pedidos**
- Autocomplete de produto/serviço lista os primeiros ao focar (search `?q=` vazio) e filtra ao
  digitar. Bugs corrigidos: URL era `app/pdv/buscar-produto` (**404 desde sempre**, engolido sem
  catch) — a rota real é `app/pdv/produto/{codigo}`; e o dropdown `position:fixed` perdia para
  as classes Bootstrap `position-absolute`/`w-100` (**!important**) — são removidas ao abrir.

**Infra/UX geral**
- `SearchController`: admin da plataforma (empresa_id null) busca pela **empresa da sessão** —
  antes clientes/produtos/fornecedores/vendedores/global voltavam vazios para o admin.
- **Paginação**: `Paginator::useBootstrapFive()` no AppServiceProvider (a view Tailwind padrão
  sem Tailwind renderizava seta SVG gigante) + traduções em `resources/lang/pt_BR/pagination.php`
  e `resources/lang/pt_BR.json` (langPath apontado para `resources/lang` — raiz é root-owned).
- Fechamento de caixa: **2 colunas no desktop** (cabe na tela) + responsivo mobile + inputs
  numéricos sem spinner.
- `erp-core.js` com **cache-busting** por `filemtime` no layout.
- Pedido **#3 é de teste** (Carla Menezes Souza, produto teste) — criado na validação, pode
  ser cancelado.

### PDV — CPF na nota + transparência da NFC-e (25/07 madrugada)

- **Campo "CPF/CNPJ na nota (opcional)"** no painel direito do PDV — o documento do
  consumidor vai no cupom fiscal **sem precisar cadastrar cliente** (`vendas.cpf_cnpj_nota`,
  migration 2026_07_25_140000). Aceita CNPJ alfanumérico; máscara própria no PDV (que não
  carrega erp-core). Vira `cpf/cnpj_destinatario` na NFC-e
  (`destinatarioNFCePayload($cliente, $cpfCnpjAvulso)` — cliente cadastrado tem prioridade),
  sai impresso no cupom não-fiscal, conta para a regra `cpf_emite_fiscal` da Config da Loja
  e é limpo a cada venda.
- **Falha de NFC-e deixou de ser silenciosa**: o modal "Venda Finalizada" mostra alerta
  amarelo com o motivo ("Cupom fiscal não emitido — saiu recibo: NCM inválido...").
  Backend devolve `nfce_erro` no JSON do PDV; `tipo_cupom` só é `fiscal` quando a nota
  realmente saiu. Diagnóstico que motivou (venda 28 do Dennis, cartão): pre-flight barrou
  por **NCM inválido no produto** e caiu no recibo sem avisar. Para o cupom fiscal sair de
  verdade ainda faltam **CSC + certificado A1** na config fiscal da unidade, e o modo
  automático por cartão exige **salvar a Configuração da Loja** (unidade Matriz não tem
  registro — modo legado: fiscal ativo → tenta NFC-e em toda venda).

### Armadilhas novas

1. `ConfiguracaoLoja::daUnidade()` retorna instância **não salva** quando a loja nunca configurou —
   usar `->exists` para diferenciar "sem config" (comportamento legado) de "configurado".
2. Repricing do PDV é gated pelo flag `tabela_precos` no payload — não remover, protege abas antigas.
3. Split no modo `cartao_maior` usa a **maior** tabela entre as formas presentes (débito+PIX →
   débito; crédito em qualquer split → crédito).
4. ContaReceber de cartão pendente tem `valor_pago = 0` e `pago_em = null` — relatórios que
   assumiam PDV = sempre pago precisam considerar isso quando houver regras de adquirente.
5. E-mail automático de NF-e só para vendas `tipo='pedido'` (hook no model NotaFiscal).
6. **Admin da plataforma não tem empresa** (`empresa_id` null): Produto e Plano ganharam guards
   (redirect com aviso) em 24/07 — commit `c160b9b`. Toda tela `/app/*` nova precisa tratar
   `auth()->user()->empresa` null, senão 500 para o admin.
7. **Classes utilitárias do Bootstrap têm `!important`** (`position-absolute`, `w-100`...) e
   vencem estilo inline via JS — remover a classe antes de posicionar por style.
8. **Dropdowns dentro de `.table-responsive` são clipados** pelo overflow — usar
   `position: fixed` ancorado no input (padrão dos pedidos) para escapar.
9. **Campos com `data-mask="money"` devem ser `type="text"`** — o submit converte
   "1.500,00" → "1500.00" automaticamente; não usar type=number com a máscara.
10. **Assets em `public/` exigem cache-busting** — o layout versiona `erp-core.js` por
    `filemtime`; JS/CSS novos sem isso ficam presos no cache do navegador do cliente.

---

## Schema essencial

### Tabelas-chave (campos relevantes)

```text
empresas
  cnpj, razao_social, nome_fantasia, regime_tributario, plano_id,
  em_trial, trial_inicio/fim, status,
  politica_estoque_inter_unidade ENUM('silos','ver_apenas','ver_e_vender'),
  regime_cobranca ENUM('padrao','cortesia','parceiro','pos_pago'),
  cortesia_motivo, cortesia_concedida_em, cortesia_revisar_em, cortesia_concedida_por,
  codigo_municipio (IBGE 7 dígitos)

unidades
  empresa_id, nome, cnpj, ie, im, endereço completo,
  codigo_municipio, status ('ativa','inativa','em_implantacao')

users
  empresa_id, perfil (enum Perfil), is_admin, comissao_percentual, status

produtos
  empresa_id, codigo_interno, descricao, preco_custo, markup, preco_venda,
  ncm, cest, cfop, cst_csosn, origem,
  icms_aliquota, icms_modalidade_bc, icms_modalidade_bc_st, mva_st,
  pis_aliquota, cst_pis,
  cofins_aliquota, cst_cofins,
  ipi_aliquota, cst_ipi,
  percentual_tributos_ibpt,
  ibs_aliquota, cbs_aliquota, is_aliquota, cst_ibs_cbs, classificacao_ibs,
  di_* (importação)

clientes
  empresa_id, tipo_pessoa, cpf_cnpj, nome_razao_social, ie, im,
  endereço completo (NULLABLE), codigo_municipio

vendas
  empresa_id, unidade_id, total, status ('concluida','cancelada','devolvida')

venda_itens
  venda_id, produto_id, servico_id, descricao, quantidade,
  preco_unitario, desconto_valor, total,
  unidade_origem_id (NULL = venda local),
  snapshot_ncm, snapshot_cest, snapshot_cfop, snapshot_cst_csosn,
  snapshot_cst_pis, snapshot_cst_cofins, snapshot_cst_ipi,
  snapshot_origem, snapshot_icms_aliquota, snapshot_pis_aliquota,
  snapshot_cofins_aliquota, snapshot_ipi_aliquota, snapshot_unidade_medida

configuracoes_fiscais
  empresa_id+unidade_id UNIQUE,
  ambiente, focus_token, focus_empresa_id,
  focus_token_producao, focus_token_homologacao,
  webhook_secret, focus_webhook_ids (JSON), webhooks_sincronizados_em,
  emissao_fiscal_ativa, emite_nfe, emite_nfce, emite_nfse,
  serie_nfe, serie_nfce, serie_nfse,
  csc_nfce, csc_id_nfce,
  nfse_item_lista_servico, nfse_codigo_tributacao, nfse_regime_especial,
  nfse_incentivador_cultural, nfse_padrao,
  certificado_validade, certificado_enviado_em, certificado_cnpj, certificado_nome,
  ibs_ativo, cbs_ativo, is_ativo, ibs_aliquota_padrao, cbs_aliquota_padrao,
  responsavel_tecnico_cnpj, responsavel_tecnico_nome,
  responsavel_tecnico_email, responsavel_tecnico_telefone

transferencias_estoque
  empresa_id, unidade_origem_id, unidade_destino_id,
  user_solicitante_id, user_aprovador_id,
  status ('pendente','aprovada','concluida','cancelada','concluida_venda_remota')

notas_fiscais
  tipo (nfe/nfce/nfse), status, focus_ref, chave_acesso, xml_url, danfe_url,
  cartas_correcao (HasMany)

cartas_correcao
  nota_fiscal_id, numero_sequencia (1-20), correcao, status, protocolo

nfes_recebidas
  chave_acesso UNIQUE, cnpj_emitente, tipo_ultima_manifestacao,
  protocolo_manifestacao
```

---

## Comandos artisan

```bash
# Migrations
docker exec -i erp-com-app php artisan migrate --force

# Limpar caches
docker exec -i erp-com-app php artisan optimize:clear

# Listar rotas
docker exec -i erp-com-app php artisan route:list | grep fiscal

# Comandos fiscais (rodar manualmente)
docker exec -i erp-com-app php artisan fiscal:backup-xmls
docker exec -i erp-com-app php artisan fiscal:backup-xmls --mes=2026-04 --apenas-download
docker exec -i erp-com-app php artisan fiscal:saude-webhooks
docker exec -i erp-com-app php artisan fiscal:alertar-certificado

# Migrar empresas legadas (que têm focus_token colado mas focus_empresa_id NULL)
docker exec -i erp-com-app php artisan fiscal:migrar-empresas-legadas --dry-run
docker exec -i erp-com-app php artisan fiscal:migrar-empresas-legadas --sync

# Filas (jobs assíncronos)
docker exec -i erp-com-app php artisan queue:work

# Scheduler (no host: cron * * * * * cd /root/erp-comercial && docker exec ...)
docker exec -i erp-com-app php artisan schedule:work
docker exec -i erp-com-app php artisan schedule:list
```

---

## Crons agendados

```text
0 */4 * * *  sincronizar-nfes-recebidas       (jobs por unidade fiscal-ativa)
0 */6 * * *  sincronizar-nfses-recebidas      (apenas unidades com NFS-e)
0 3   * * *  fiscal:backup-xmls               (backup mensal Focus)
0 4   * * 1  fiscal:saude-webhooks            (segunda — comparar local vs Focus)
0 8   * * *  fiscal:alertar-certificado       (alerta vencimento A1)
```

---

## Logins demo

| Perfil | Email | Senha |
|---|---|---|
| Admin | admin@ia365.com.br | admin123 |
| Dono | dono@demo.com | dono123 |
| Gerente | gerente@demo.com | gerente123 |
| Vendedor | vendedor@demo.com | vendedor123 |
| Caixa | caixa@demo.com | caixa123 |

> ⚠️ Em **produção** só o Admin funciona (verificado 24/07/2026) — os usuários `@demo.com`
> valem para o seed de desenvolvimento (`migrate:fresh --seed`).

---

### Planilhas (.xlsx), importação e uploads — 25/07 tarde

**Modelos de planilha agora são .xlsx de verdade** (`App\Support\Planilha`, gerador+leitor de
XLSX sem dependência externa — PhpSpreadsheet não existe no vendor e sumiria em rebuild da
imagem). O modelo antigo era um CSV com `;` que o Excel/Numbers abria em coluna única.

- `Planilha::gerar/download/ler/pareceXlsx` — escrita com strings inline, cabeçalho em negrito
  congelado, autofiltro e larguras; leitura resolve sharedStrings, inlineStr, rich text e
  células ausentes. Colunas "de código" (CPF/CNPJ, CEP, NCM, EAN, SKU...) saem **como texto** —
  se virassem número o Excel comeria o zero à esquerda e faria notação científica com o EAN.
- `/app/import/template/{tipo}` devolve .xlsx com as colunas aceitas + linhas de exemplo
  (`?formato=csv` mantém o texto puro). As linhas de exemplo são **descartadas na importação**
  (`ehLinhaDeExemplo`) — o lojista preenche embaixo e não apaga o exemplo.
- **Importação lê .xlsx nativo** (antes o arquivo era lido como texto e virava lixo, apesar de
  `mimes` já aceitar xlsx). `.xls` binário é recusado com instrução para salvar como .xlsx.
- Célula vazia vira `null` — string vazia em coluna decimal (`limite_credito`, preços) é
  erro MySQL 1366 e derrubava a linha inteira.
- Exportações (`/app/export/*`) também saem em .xlsx; `preco_debito`/`preco_credito` agora
  vêm da relação `produto_precos` (antes saíam sempre vazios).
- Upload do certificado virou **área de arrastar-e-soltar** com nome do arquivo escolhido e
  dica de usar o Buscar do seletor (o lojista não achava o .pfx na pasta cheia de downloads).
- Card NFC-e com paridade ao sistema antigo: Ambiente/Versão 4.00/Última NFC-e como campos
  informativos + mapa recolhido "onde ficou cada campo" (Token do Gestão = ID CSC).
- Front: botões viraram "Importar planilha" / "Modelo Excel"; o handler mostra o motivo real
  de 419/413/500 (antes: "Erro ao importar arquivo" para tudo).

**⚠️ Causa raiz do "não consigo importar" (500 em QUALQUER upload):** no container, o worker do
nginx roda como `www-data` mas `/var/lib/nginx` era do usuário `nginx` com 0700 — todo upload
maior que o buffer em memória morria em `open() "/var/lib/nginx/tmp/client_body/..." failed
(13: Permission denied)` **antes de chegar no PHP**. Afetava importação, certificado A1 e
anexos de caixa. Corrigido no container ativo, no `entrypoint.sh` e no `docker/prod/Dockerfile`.

### Fiscal — correções do teste ao vivo (25/07 tarde)

- **Certificado A1 nunca funcionou**: chamava `POST /v2/empresas/{cnpj}/certificado` com o token
  da empresa no host de homologação — rota inexistente, 404 → "Erro desconhecido" na tela.
  A Focus instala certificado pela **API de empresas, com token master em api.focusnfe.com.br**:
  `PUT /v2/empresas/{focus_empresa_id}` com `arquivo_certificado_base64` + `senha_certificado`.
  Agora o .pfx também é aberto localmente (openssl) antes de enviar: senha errada, arquivo
  inválido, certificado vencido e CNPJ de outro titular são barrados com mensagem clara, e a
  validade sai do próprio certificado. Erro da Focus é lido de `erros[0].mensagem`
  (a raiz traz só "Erro de validação").
- **Seletor de arquivo não mostrava o .pfx no macOS**: o `accept` com mimetype desconhecido
  esmaece o arquivo no Chrome/mac (só dava para arrastar). Removido — a validação é no servidor.
- **500 ao salvar a Configuração Fiscal**: a máscara manda `23.237.062/0001-17` (18 chars) e a
  coluna é `varchar(14)` → SQLSTATE 22001. O controller agora sanitiza resp. técnico
  (`Cnpj::limparCpfCnpj`) e telefone (dígitos) antes de gravar.
- **NFC-e rejeitada com "Erro na validação do Schema XML"** — duas causas reais:
  1. `VendaItem::fiscal()` usava `?? $fallback`, então **CFOP em branco no produto** (string
     vazia, não null) ia para o XML como `""`. Agora `''` cai no fallback (CFOP 5102 etc.).
  2. **Inscrição Estadual não cadastrada** ia como `""`. O emitente agora omite campos vazios e
     o pre-flight da NFC-e barra antes, dizendo onde cadastrar (IE e CSC/ID CSC).
- **Nota rejeitada travava a venda**: a tela só oferecia emissão quando não havia NENHUMA nota.
  Agora, se não existe nota viva (autorizada/pendente/contingência), aparecem "Emitir NFC-e
  novamente" e "Emitir NF-e" com o aviso do que houve.
- **`app(NFeService::class)` / `app(NFCeService::class)`** nos botões de emissão estouravam
  `BindingResolutionException` (armadilha 13) — trocados por `::forUnidade($config->unidade)`.
  `emitirNFCe` responde JSON para o PDV e redirect+flash para formulário.
- **Campo "Informações complementares"** (`configuracoes_fiscais.informacoes_complementares`,
  migration 2026_07_25_180000): mensagem fixa do rodapé que o lojista tinha no sistema antigo —
  entra em `informacoes_adicionais_contribuinte` na NF-e e na NFC-e, junto das observações da venda.
- **Numeração/"Última NFC-e"**: não existe campo equivalente porque **quem numera é a Focus**,
  não o ERP (o payload da NFC-e não aceita número nem série). Ao migrar de outro sistema, usar
  **série nova** ou pedir à Focus para iniciar a numeração — senão a SEFAZ rejeita por
  duplicidade. Aviso explícito no card da NFC-e.

### Cupom fiscal na térmica + cancelamento + arquivos da Focus (25/07 noite)

- **404 ao abrir cupom/XML**: a Focus devolve `caminho_danfe`/`caminho_xml` RELATIVOS ao host
  dela; o redirect jogava em erp.ia365.com.br/... Accessors `danfe_url_completa`/`xml_url_completa`
  no model resolvem para api.focusnfe.com.br (produção) ou homologacao.focusnfe.com.br pelo
  `ambiente` da NOTA. Usados nos downloads e no JSON do PDV.
- **Botão "Cupom" imprime na térmica**: NFC-e autorizada abre `vendas/{id}/recibo?print=1` —
  cupom 80mm do ERP com QR Code, chave e protocolo + `window.print()` automático. Colunas novas
  `notas_fiscais.qrcode_url` e `protocolo` (migration 2026_07_25_200000), gravadas na emissão,
  na consulta e no webhook; a view do cupom já as esperava (nunca existiam → cupom sem QR).
  Rótulos por tipo: NFC-e = "Cupom"/"Imprimir Cupom", NF-e = "DANFE", NFS-e = "PDF".
- **Tributos aproximados no cupom térmico** (Lei 12.741/2012): linha "Tributos Totais
  Incidentes" no cupom 80mm — mesmo cálculo do payload (IBPT do item via `fiscal()`,
  fallback 25%). Obrigatória no documento ao consumidor.
- **Cupom térmico 100% conforme o Manual DANFE NFC-e (NT 2020.006)**: nome completo do
  documento + "Não permite aproveitamento de crédito de ICMS", nº/série/data de emissão,
  "Via Consumidor", "Consulte pela chave de acesso em <url da SEFAZ>" (coluna nova
  `notas_fiscais.url_consulta`, vem de `url_consulta_nf` da Focus), chave em grupos de 4,
  bloco CONSUMIDOR (identificado ou "NÃO IDENTIFICADO"), QR Code, protocolo com data/hora,
  tarja "EMITIDA EM AMBIENTE DE HOMOLOGAÇÃO" quando aplicável e a linha da Lei 12.741.
  Bug junto: a view usava `numero_nota` (coluna é `numero`) — nº/série nunca saíam.
- **"Erro inesperado ao cancelar a nota"**: `resolveService()` usava `app(NF*Service)`
  (armadilha 13) — cancelar, consultar, inutilizar E carta de correção estavam quebrados
  desde sempre. Agora `::forUnidade` com a unidade da PRÓPRIA nota.
- **Cupom saía claro demais na térmica**: térmica só imprime preto puro — cinza vira chuviscado
  e Courier fino sai fraco. Corpo do cupom com `font-weight:700`, zero cinza (era #555/#888),
  fontes mínimas 9px (eram 8px), corpo 13px, separadores 2px e `color:#000 !important` no
  @media print. Vale para recibo E cupom fiscal (mesma view `cupom-nao-fiscal`).
- **Cancelar cupom**: número da nota na venda linka para a página da nota (botão Cancelar com
  justificativa). `VendaController::destroy` agora BLOQUEIA cancelar a venda com documento
  fiscal vivo e redireciona para a nota (NFC-e tem prazo curto de cancelamento na SEFAZ).
- **Etiquetas "validation.required" ao selecionar todos**: 515 produtos × 2 inputs = 1030 campos
  e o PHP corta em 1000 (`max_input_vars`) — o corte silencioso chegava como validação. Form
  agora manda 1 input por produto (`produtos[<id>]=qtd`, aceita o formato antigo),
  `max_input_vars=10000` no php.ini de produção.
- **`resources/lang/pt_BR/validation.php` criado** — o langPath repointado desligava o fallback
  do framework e TODA validação aparecia crua ("validation.required").

### Etiquetas

Novo formato **Tag Roupa 35 × 60 mm — 3 colunas** (`termica-tag-35x60`): bobina de 105 mm,
grid `repeat(3, 35mm)`, conteúdo deslocado 7 mm do topo para não cair no furo da tag,
código de barras de 13 mm e código do produto visível.

**Preços e logo (26/07):**
- Etiqueta dupla mostra valores secos — **"Cartão R$ X" em cima, "PIX R$ Y" embaixo**
  (sem "6x de"; ordem final definida pelo Dennis 26/07). Sem tabela de cartão, preço único
  como sempre. Linhas com `white-space: nowrap` (não quebram "Cartão R$ 22,00" no meio).
- **Logo da empresa no lugar do nome** quando `empresas.logo` preenchido (vale para todas
  as unidades; sem logo, cai no nome). Na impressão o logo sai em **preto sólido**
  (`filter: brightness(0)`) — dourado/colorido imprime apagado na térmica. Na tela do
  sistema o logo continua nas cores originais.
- Logo da J S COMERCIO (STILO VINTE): `storage/app/public/logos/empresa-3-v2.png`
  (volume `app_storage`, sobrevive a recreate). **Arquivo TRATADO antes de instalar** — o
  original tinha canvas 500×500 com o desenho numa faixa de 408×156 (172px de vazio em
  cima/baixo encolhiam o logo visível pra ~2mm na etiqueta) e cor rosé claro: recortado no
  conteúdo (424×172) + pintado de preto puro via PIL. ⚠️ Ao instalar logo de cliente,
  SEMPRE conferir margens vazias do canvas e cor clara — e salvar com nome novo (v2, v3...)
  para furar o cache do navegador.
- **Upload de logo no admin CORRIGIDO**: o form de empresa sempre teve o campo mas
  `store`/`update` ignoravam o arquivo — agora valida (image, 2MB), salva em `logos/` no
  disco público e apaga o anterior ao trocar.
- Zero cinza também nas etiquetas (preço PIX era #333, código #777) — térmica.

**Formato 36 × 20 mm — 2 colunas / Argox (05/08, DONA DOURO):** `termica-36x20-2col`,
página de **74 mm** (2 × 36 mm + 2 mm de espaço entre colunas), `repeat(2, 36mm)`.
Equivale ao layout **"27 – Etiqueta adesiva de produtos 2 col (com espaços) com preço"**
da Hiper Loja, sistema de origem da DONA DOURO. Conteúdo na ordem da Hiper —
descrição → código interno → código de barras (com número) → preço — obtida por
`order` no flex, já que no template padrão o código vem por último. Nome/logo da
empresa ficam ocultos (não cabem em 20 mm), barras de 7 mm.
⚠️ A Hiper escreve o tamanho como **altura × largura** ("20.00mm X 36.00mm"); aqui a
etiqueta é **36 mm de largura por 20 mm de altura** (deitada, como na prévia do sistema
antigo). Se a bobina do cliente tiver espaçamento diferente de 2 mm, só a largura da
página muda.
⚠️ **Barra de ponta a ponta (feedback do Dennis 05/08)**: o SVG do JsBarcode escala
mantendo a proporção — com o intrínseco padrão (~2,3:1) e altura CSS de 7 mm, a
ALTURA vira o limite e a barra encolhia para ~16 mm com margens dos dois lados,
dígitos ilegíveis. Fix definitivo (pós-processamento JS): `preserveAspectRatio='none'`
+ inline `width:100%` — o SVG estica para o box exato, independente da lib. Ao criar
formato térmico novo, conferir qual dimensão está limitando o SVG antes de mexer em fonte.
⚠️ **Dígitos em linha única (Hiper-style) — 36×20, 33×22 E Tag 35×60 (Dennis 05/08,
"número para fora")**: o layout EAN-13 clássico do JsBarcode (`displayValue`) desenha
o 1º dígito FORA das barras-guarda; com a barra esticada ele encostava na borda da
etiqueta. Nos três formatos o JsBarcode roda com `displayValue:false` (SVG só de
barras, `margin:10` no intrínseco ≈ 1,5 mm de quiet zone por lado após o stretch) e
os 13 dígitos saem numa **div `.barcode-digits`** própria — linha única centrada,
bold, monospace, grupos `X XXXXXX XXXXXX`. Vale também para o fallback CODE128
(mostra o código cru). Alturas de barra: 36×20 = 6 mm, 33×22 = 6,5 mm, Tag = 10 mm.
Os formatos de 1 coluna (40×25, 50×30, 60×40) seguem com o EAN-13 clássico —
mudança scoped por `in_array($formato, [...])`.
As melhorias do 33×22 e da Tag 35×60 valem para TODAS as empresas que os usam
(STILO VINTE inclusa) — OK do Dennis 05/08 ("deixa as duas prontas" / "33×22 arruma este").

### Formato de etiqueta cadastrado pelo lojista (11/08/2026)

> Branch `feat/etiqueta-formato-personalizado`. Origem: bobina **~32 × 25 mm, 3 colunas**
> (impressora **Elgin**, MISS MERLINDA) que não existia no sistema — o Dennis tentou o
> `termica-tag-35x60` (único de 3 colunas) e saiu cortado nas laterais e fora da etiqueta.

**Por que virou motor e não mais um formato fixo:** já eram 4 one-offs hardcoded em 3 semanas
(33×22, 36×20 Argox, Tag 35×60 e agora 32×25). Cliente novo com bobina diferente = código +
deploy. Agora a medida é **dado**, não CSS.

- Tabela **`etiqueta_formatos`** (`empresa_id` + `nome` unique): `largura_mm`, `altura_mm`,
  `colunas`, `espaco_mm`, `mostrar_empresa`, `ativo`. Model `EtiquetaFormato`.
- **O lojista digita em CENTÍMETROS** (é o que ele mede com a régua) e aceita vírgula —
  o controller converte para mm, que é a unidade do banco e do CSS. `espaco_cm` é a folga
  entre uma etiqueta e a vizinha; a **largura da página** = `colunas × largura + (colunas−1) × espaço`.
- **Tudo o mais é DERIVADO** em `EtiquetaFormato::layout()`: tamanhos de fonte, altura das
  barras, se cabe nome/logo (≥ 22 mm de altura) e código interno (≥ 18 mm). As fórmulas foram
  calibradas nos formatos fixos que já funcionam, então cadastrar "3,6 × 2,0 cm, 2 colunas,
  0,2 cm" sai praticamente igual ao `termica-36x20-2col`. Tudo clampado — medida absurda não
  gera etiqueta ilegível.
- ⚠️ **O preço duplo (Cartão + PIX) é limitado pela LARGURA, não pela altura**: são 2 linhas de
  ~16 caracteres ("Cartão R$ 110,00"). `fonte_preco_duplo` usa `(largura − 2) / 3,3` — foi o que
  cortou o texto na tentativa do Dennis.
- Formato personalizado entra **sempre** no tratamento de barra esticada
  (`preserveAspectRatio="none"`) + dígitos em linha única embaixo — é o que torna etiqueta
  pequena legível (mesmo padrão do 36×20 e do Tag).
- **UI**: os formatos fixos continuam exatamente como estavam. "Meus formatos" (radios) só
  aparece se a empresa cadastrou algum, e o cadastro fica num `collapse` **recolhido**
  ("Cadastrar o formato da minha bobina"). Erro de validação **reabre o painel** — senão a
  mensagem ficaria escondida dentro do bloco fechado.
- ⚠️⚠️ **A chave do formato PRECISA começar com `termica-`** (`termica-custom-<id>`, constante
  `EtiquetaFormato::PREFIXO_CHAVE`). O `print.blade` zera `min-height`, `margin`, `gap` e a
  **borda tracejada** dos formatos de bobina pelo seletor **`[class*="formato-termica"]`** —
  casamento por SUBSTRING da classe. Na 1ª versão a chave era `custom-<id>` (classe
  `formato-custom-1`), que não casava: a etiqueta herdava o `min-height: 297mm` do A4 e
  **uma linha da bobina virava uma página A4 espalhada por ~12 páginas de 25 mm**, quase todas
  em branco, com o conteúdo de UMA etiqueta partido em duas (nome+barras numa, Cartão/PIX na
  outra) e as bordas tracejadas impressas. Pego pelo Dennis em 11/08 imprimindo em PDF antes de
  gastar bobina. A regra do `.page.formato-*` custom repete `min-height: 0; margin: 0;
  overflow: hidden` de propósito, para não depender só do substring.
- ⚠️ **HTML não aceita form aninhado**: o cadastro e as exclusões são `<form>` separados,
  fora do `#formEtiquetas`, e os campos apontam para eles por `form="..."`. O `data-confirm`
  do erp-core já resolve isso porque usa `button.form` (respeita o atributo), não `closest('form')`.
- Rota `DELETE /app/etiquetas/formatos/{etiquetaFormato}` — o parâmetro casa com a variável
  tipada do controller (armadilha do route model binding).

---

## E-mails transacionais + Financeiro da plataforma + Equipe IA365 (04/08/2026)

> Branch `feat/financeiro-plataforma-emails`. Pedido do Dennis: botão de login na home,
> e-mails de cadastro/recuperação, cobrança direta (cliente paga a IA365 sem gateway,
> mensal ou anual, com bloqueio) e admins IA365 com/sem acesso a valores.

### E-mails (remetente `no-reply@iautomation.com.br`)

- SMTP: `smtp.hostinger.com:465` SSL (mesmo host de antes; remetente trocado de
  dennis.canteli@ para no-reply@ no `.env` — lembrar `docker cp` + restart).
- **`App\Mail\BoasVindasUsuario`** (fila): disparado em TODO cadastro de usuário —
  contexto `dono` (onboarding step3), `funcionario` (FuncionarioController e
  Admin\UsuarioController p/ usuário de empresa) e `equipe` (admin IA365).
  **A senha digitada no cadastro VAI no e-mail** (decisão do Dennis 04/08, revisando
  a escolha da manhã: "tem que ter a senha, senão como ele acessa") — 3º parâmetro
  do Mailable; sem senha o template mostra "definida por quem realizou o cadastro".
- **Reenviar dados de acesso**: card "Acessos do cliente" no fim de
  `/admin/empresas/{id}/edit` lista os usuários da empresa com botão
  **Reenviar acesso** (`POST empresas/{id}/reenviar-acesso`): gera **senha nova**
  (Str::random(10) — a original é hash, irrecuperável), salva e envia o
  boas-vindas com a senha. `data-confirm` avisa que a senha atual deixa de valer.
- **`App\Mail\RedefinirSenha`** (fila): link com token válido por **60 min**
  (`PasswordResetController`). ⚠️ **Brecha fechada**: antes o "reset" NÃO enviava
  e-mail — mostrava o link de troca NA TELA para qualquer um que digitasse o e-mail
  (tomada de conta trivial). Agora resposta neutra ("se o e-mail existir...").
- Templates em `resources/views/emails/` (`layout` + `boas-vindas` + `redefinir-senha`),
  inline CSS, tabela — compatível Gmail/Outlook.

### Home / landing

- **"Entrar"** no nav da landing → `/login`: **link discreto** (`.nav__login`), não botão —
  3 botões estouravam o nav em duas linhas ("ficou grotesco", Dennis 04/08). Visível no
  mobile; botões do nav com `white-space: nowrap`.
- `GET /app` redireciona para `/app/dashboard` (dava 404 seco).

### Cobrança direta (financeiro da plataforma — sem gateway)

- **Campos na `empresas`**: `cobranca_periodicidade` (mensal|anual, null = desligada),
  `cobranca_valor`, `cobranca_dia_vencimento` (mensal, 1-28),
  `cobranca_proxima_renovacao` (anual), `cobranca_geracao` (automatica|manual),
  `cobranca_bloqueio_automatico`, `cobranca_tolerancia_dias` (default 5),
  `cobranca_suspensa_em`.
- **`plataforma_faturas`**: competência (`YYYY-MM` mensal / `YYYY` anual), valor,
  vencimento, status (pendente|paga|cancelada), pago_em, forma, marcada_por.
  NÃO confundir com contas_receber (financeiro interno das empresas).
- **`plataforma:processar-cobrancas`** (diário 06h): gera fatura do ciclo (mensal:
  competência do mês; anual: 30 dias antes da renovação — só geração `automatica`),
  avisa o dono no sino (vence ≤3 dias / em atraso, dedup por não-lida) e **SUSPENDE**
  a empresa quando fatura pendente passa de vencimento+tolerância com bloqueio ligado.
- **Bloqueio TOTAL** (decisão do Dennis): middleware `suspensao` no grupo `/app` —
  todos os usuários da empresa caem em `/acesso-suspenso` (tela dark com pendências
  visíveis só para o perfil dono). Admin IA365 nunca bloqueia. Marcar a fatura como
  paga reativa na hora (anual também avança `cobranca_proxima_renovacao` +1 ano).
- **Prioridade em `isAssinaturaAtiva()`**: cobrança direta configurada > regime
  gratuito (cortesia/parceiro/pos_pago) > trial/assinatura. Ou seja: com cobrança
  direta ativa o trial deixa de contar.
- **Trial encerra ao contratar**: salvar cobrança direta zera `em_trial` (update do
  EmpresaController) e o banner "Período de avaliação" também checa
  `temCobrancaDireta()`/`ehGratuita()` — cliente com licença anual via banner de
  "4 dias restantes" (caso STILO VINTE, 04/08).
- **UI**: card "Cobrança direta" em `/admin/empresas/{id}/edit` (só admins com
  `pode_ver_financeiro`) + tela `/admin/financeiro` (cards a receber/em atraso/
  recebido/MRR, contratos, faturas com filtros, gerar fatura manual, marcar paga,
  cancelar, suspender/reativar manual).

### Equipe IA365 — `users.pode_ver_financeiro`

- Flag booleana (só faz sentido com `is_admin`); migration deu a flag aos admins
  existentes. `User::podeVerFinanceiro()` = is_admin && flag.
- Sem a flag: menu Financeiro some, card Cobrança direta some, rotas do financeiro
  dão 403 e o update da empresa ignora os campos `cobranca_*`.
- Só quem TEM a flag concede/revoga a flag de outro admin.
- **Fix de segurança no deploy**: `comercial@ebgestaoevendas.com.br` (user 3) tinha
  `is_admin=1` + `empresa_id NULL` — enxergava TODAS as empresas (o EmpresaScope só
  filtra quando `empresa_id` não é null). Rebaixado a dono da empresa 2 (EB Gestão).

### Bugfix junto

- **`fiscal:backup-xmls` falhava TODA noite desde 29/05/2026** (68 execuções, zero
  backups): `handle(BackupXmlService $service)` fazia o container resolver
  `FocusNFeClient` sem token → `BindingResolutionException` antes do handle
  (armadilha 13 na assinatura de um Command). Parâmetro removido — o método já
  instanciava o service por unidade.

### Cópia local dos XMLs por nota (04/08 tarde)

> Contexto: o XML mora na Focus (ela monta/assina/transmite; o ERP guarda chave +
> link). O pacote mensal (`fiscal:backup-xmls`) é assíncrono no ritmo da Focus.
> Pedido do Dennis: o cliente precisa de acesso fácil e cópia nossa.

- **Hook `saved` no model NotaFiscal**: nota com chave+XML (autorizada, cancelada,
  mudança de status) dispara `BaixarXmlNotaJob` (delay 15s, 4 tentativas com
  backoff 30s/2min/10min) → salva em `storage/app/private/fiscal/xmls/{empresa_id}/{chave}.xml`
  (volume `app_storage`, sobrevive a recreate).
- **`fiscal:baixar-xmls-notas`** (diário 03h30): varredura que garante a cópia de
  toda nota com chave — backfill + rede de segurança se worker/Focus falharem.
- **Download do cliente (`/app/notas-fiscais/{id}/xml`) é local-first**: serve a
  cópia do nosso disco (`Storage::download`); sem cópia, tenta baixar na hora;
  só em último caso redireciona para a Focus (comportamento antigo).
- Cópia avulsa fora do app: `/home/ubuntu/erp-backups/xmls-focus/` (3 XMLs de
  produção baixados à mão em 04/08) + dump diário do MySQL às 02h30
  (`/home/ubuntu/erp-backups/diario/`, cron do ubuntu, retenção 30 dias).

---

## Alterações do doc do Dennis (05/08/2026) — branch `fix/vendas-filtro-imports-multiloja-cnpj`

> Origem: Google Doc "AlteraesERP" (5 itens com screenshots). Contexto real: migração
> da STILO VINTE (6+ lojas em 2 CNPJs) do sistema antigo para o ERP.

### Lojas com o MESMO CNPJ compartilham a empresa Focus

**Problema:** a Focus recusa `POST /v2/empresas` para um CNPJ repetido quando
`habilita_manifestacao=true` (422 — "Não é permitido habilitar manifestação para uma
segunda empresa com o mesmo CNPJ/CPF", com typo "manisfetação" no texto real). O
provisionamento mandava a flag sempre ligada → as filiais 02 JS/03 JS/OUTLET da STILO
VINTE ficaram sem `focus_empresa_id`/tokens (não emitiam e o upload de certificado
pedia token). As PRIME 05-07 tinham o focus_id copiado na mão, mas com
`ambiente=homologacao` divergindo da matriz (producao).

**Solução (`FocusEmpresaService::criar`):**
1. `configIrmaMesmoCnpj()` — se OUTRA unidade da mesma empresa com o mesmo CNPJ efetivo
   (`unidade.cnpj ?: empresa.cnpj`, comparado via `Cnpj::limpar`) já está provisionada,
   `reutilizarEmpresaFocus()` copia focus_empresa_id + tokens + ambiente + CSC +
   metadados do certificado SEM chamar a Focus. Certificado A1, CSC e numeração são
   POR CNPJ — configura uma vez, vale para todas as lojas do CNPJ.
2. Sem irmã local: POST normal; se vier o 422 da manifestação
   (`erroManifestacaoDuplicada` — campo `habilita_manifestacao` ou "mesmo cnpj" na
   mensagem), retry único com `habilita_manifestacao=false`.
3. Tela de Configuração Fiscal mostra alerta "CNPJ compartilhado entre lojas" listando
   as irmãs (`$lojasMesmoCnpj` no `ConfiguracaoFiscalController::edit`; relação
   `unidade()` adicionada ao model ConfiguracaoFiscal).

**Data-fix aplicado em produção (empresa 3):** configs das unidades 13/14/18 receberam
focus_id 235712 + tokens/certificado da Matriz (config unidade 9); configs 15/16/17
(PRIME) alinhadas ao ambiente `producao` + metadados de certificado da 04 PRIME MATRIZ.

### Alinhamento fiscal por CNPJ + numeração contínua (05/08, 2ª rodada)

Dennis trouxe os dados do sistema antigo e confirmou mistura entre os CNPJs:

- **CSC**: as 8 configs (e a empresa Focus 235712 da JS!) estavam com o CSC da PRIME
  (`...292326`). Corrigido: JS (unidades 9/13/14/18 + Focus 235712) usa
  `23237062...443143`; PRIME mantém o dela. CSC/ID Token são por CNPJ.
- **Responsável técnico**: todas as configs apontavam o CNPJ da JS. Configs PRIME
  (10/15/16/17) agora com `responsavel_tecnico_cnpj=25105231000190`.
- **Informações complementares** da PRIME: "EMPRESA OPTANTE PELO SIMPLES NACIONAL,
  NÃO GERA DIREITO A CREDITO" (igual ao sistema antigo).
- **Numeração — COMO FUNCIONA**: quem numera NFC-e/NF-e é a Focus, por
  empresa-filha (CNPJ) + série, via `serie_*_producao`/`proximo_numero_*_producao`
  no `PUT /v2/empresas/{id}` (a UI da Focus chama de "Última NFC-e"). Na migração
  de sistema é ajuste ÚNICO: aponta o próximo número e daí a Focus incrementa
  sozinha; o ERP só registra o número que a Focus devolve. Aplicado:
  - JS 235712: série NFC-e produção 1→**2**, próximo **4564** (antigo parou em 4563).
  - PRIME 235729: série NFC-e produção 1→**2**, próximo **4892** (antigo 4891);
    NF-e próximo **8** (antigo 7, série 1).
- ⚠️ A PRIME emitiu NFC-e 1–3 na série 1 pelo ERP (25/07) antes do alinhamento:
  nº 2 e 3 canceladas; **nº 1 segue AUTORIZADA na série 1** — cancelamento já foi
  recusado pela SEFAZ em 25/07 (janela de ~30 min da NFC-e). Não é irregular manter
  série paralela; se quiser tentar de novo: página da nota → Cancelar (vai recusar
  por prazo de novo, quase certo).

### Filtro por loja em /app/vendas

- Select "Loja" (Todas + unidades ativas) **só para admin/dono** — os demais perfis já
  são travados pelo UnidadeScope. Padrão continua a loja da sessão (tela não muda).
- Cards de resumo e Exportar respeitam o filtro (`?loja=todas|<id>`; o botão Exportar
  agora propaga a query string). Badge da loja em cada linha quando "Todas".
- `VendaController::lojasParaFiltro()` valida o id contra as lojas visíveis.

### Import de vendas históricas (/app/vendas)

- `POST /app/import/vendas` (`permission:vendas,criar`) + Modelo Excel `vendas`.
- Colunas: `numero_antigo, data, cliente, cliente_cpf_cnpj, vendedor, forma_pagamento,
  valor_total, status, observacoes`. 1 linha = 1 venda com item único "Venda importada
  — sistema anterior" (produto_id null — VendaItemObserver ignora snapshot).
- Entram como **`tipo='importada'`**: SEM estoque, SEM caixa, SEM fiscal, SEM contas a
  receber. `created_at` = data da planilha (relatórios históricos funcionam); numeração
  segue a sequência da unidade (nº original vai nas observações).
- Migration `2026_08_05_100001`: enum `vendas.tipo` += `pedido` e `importada` —
  **o faturamento de pedidos gravava `tipo='pedido'` que NÃO existia no enum e
  estourava com sql_mode STRICT** (bug latente desde 24/07, zero vendas de pedido
  no banco confirmavam).

### Import de contas a receber (/app/financeiro/contas-receber)

- `POST /app/import/contas-receber` (`permission:financeiro,criar`) + modelo
  `contas_receber`: `cliente, cliente_cpf_cnpj, descricao, valor, vencimento, parcela
  (2/10), status (pendente|paga), pago_em, forma_pagamento`.
- Cliente resolvido por CPF/CNPJ e depois por nome exato; sem match a conta fica sem
  vínculo (a tela já aceita). `paga` preenche valor_pago/pago_em (default = vencimento).

### /app/plano só para o dono (05/08, 2ª rodada)

Pedido do Dennis: plano/assinatura é assunto do proprietário. `PlanoController::index`
e `comparar` redirecionam não-donos ao dashboard com aviso; menu "Meu Plano", link
"Ver planos" do banner de trial e botão "Assinar" do dashboard só renderizam para
`isDono()`. A tela `/app/plano/expirado` continua aberta a todos (é a de bloqueio).
Admin da plataforma segue com o redirect próprio (armadilha 25).

### Minhas Lojas + Minha Empresa no /app (05/08, 4ª rodada)

Pedido do Dennis ("tudo no admin fica ruim") — o cadastro sai do monopólio do admin:

- **`/app/lojas` (Minhas Lojas)** — dono cria/edita todas as lojas da empresa;
  **gerente também cadastra lojas** (fica vinculado automaticamente à que criou)
  e **edita as lojas às quais está vinculado** (pivot `unidade_user`; sem
  vínculo, vale a loja da sessão). Criação respeita `max_unidades` do plano
  (badge "limite atingido" no lugar do botão; admin continua sem limite).
  Reusa `Admin\UnidadeController::validationRules()` e dispara o MESMO
  `ProvisionarEmpresaFocusJob` — loja nova com CNPJ de irmã herda a empresa
  Focus automaticamente. Badge fiscal por loja (Pronta/Provisionando/Sem emissão).
  Excluir loja continua só no admin (aqui, inativar).
- **`/app/empresa` (Minha Empresa)** — só o dono: nome fantasia, endereço,
  contato, código IBGE e **logo** (o mesmo que sai nas etiquetas). CNPJ e razão
  social read-only (ato societário — suporte). Regime tributário segue na
  Config Fiscal.
- **Permissões**: `unidades.gerente` ganhou `editar` na matriz. Menu Gestão
  agora abre para gerente (só com Minhas Lojas; demais itens continuam
  dono/admin) — Minha Empresa e Minhas Lojas no topo do grupo.
- ⚠️ Blade: a armadilha do `@php(...)` inline mordeu DE NOVO no layout
  (500 em todo o /app até o fix) — SEMPRE bloco `@php ... @endphp`.
- Nota: STILO VINTE tem 8 lojas num plano Profissional (max 3) — criadas pelo
  admin, que ignora limite; o dono dela só cria loja nova se o plano subir.

### Vendedor no PDV sempre visível + F3 (05/08, 3ª rodada)

Pedido do Dennis: "onde eu seleciono o vendedor?". O select `vendedorSelect` JÁ
existia no PDV (venda, comissão e — com `vendedor_responsavel_caixa` ligado na
Config da Loja — o caixa vão para o selecionado), mas **só renderizava se a
empresa tivesse usuários com perfil caixa/vendedor** — a STILO VINTE só tinha o
Pedro (dono) e o campo sumia. Agora:

- Lista inclui **vendedor, caixa, gerente e dono** ativos (menos o operador
  logado, que é a opção padrão) e o select **renderiza sempre**.
- Atalho **F3** foca o select (rodapé de atalhos atualizado).
- Ao finalizar a venda o select **volta ao operador logado** — comissão não
  vaza para a venda seguinte.

### Import robusto (bug "0 de 70 linhas — 11 com erro")

Causas: linha sem CPF/CNPJ era pulada EM SILÊNCIO; cabeçalho "CPF/CNPJ" não virava
`cpf_cnpj` (Str::snake preserva `/` — coluna inteira ignorada); loop abortava na 11ª
falha; log INFO filtrado em produção. Correções em `processImport`:

- `normalizarCabecalho()` — Str::ascii + tudo que não é `[a-z0-9]` vira `_`.
- Arquivo inteiro processado (sem break); contadores `imported`/`puladas`/`erros_total`
  + até 50 mensagens linha a linha; log em `warning`.
- Front (`erp-core.js`): `ERP.importResumoModal(data)` — modal com chips
  importadas/puladas/com erro/total e a lista de ocorrências (reload ao fechar).
- **Cliente sem CPF/CNPJ agora importa** (base migrada é assim): migration
  `2026_08_05_100000` torna `clientes.cpf_cnpj` NULLABLE (unique empresa+cpf_cnpj
  aceita múltiplos NULL) e a dedup desses é por nome exato. Cadastro manual continua
  exigindo documento.
- Datas aceitam dd/mm/aaaa, aaaa-mm-dd e **serial do Excel** (célula formatada como
  data); `normalizarFormaPagamento()` mapeia "Cartão de Crédito"→`cartao_credito` etc.
- **Round 2 (import real da STILO VINTE, 05/08 tarde)**: os "11 com erro" eram
  registros SOFT-DELETADOS com o mesmo CPF (Dennis importou 04/08 e excluiu — o
  updateOrCreate não enxerga a lixeira, tentava INSERT e estourava o unique).
  `upsertComLixeira()` (clientes/produtos/fornecedores): acha inclusive trashed,
  restaura e atualiza. E a regra "mínimo 2 células" virou "linha não vazia" —
  cliente só com nome é válido. **Resultado: 70/70 clientes da planilha do Dennis
  importados (11 com CPF, 59 sem documento), zero erros.**

---

## Migração DONA DOURO (empresa 5) — Hiper Loja → ERP (05/08/2026)

> Branch `feat/etiqueta-36x20-2col-donadouro`. Cliente novo (M & R LTDA, CNPJ
> 64.169.650/0001-48, Teresina/PI, Simples Nacional): joalheria/semi-joias/bolsas
> vinda do **Hiper Loja** (`donadouro26.hiper.com.br`). Base de origem: export
> `Cadastros - produtos.xlsx` — **1.913 produtos ativos**, 2.842 unidades em estoque.

### Import de saldo de estoque (`POST /app/import/estoque`)

Não existia forma de trazer saldo na migração: o import de produtos só grava
cadastro (`estoque_minimo`), e o saldo do ERP é **derivado** da última
`estoque_movimentacoes.quantidade_posterior` da unidade — não há coluna de saldo.

- Colunas: `codigo, descricao, quantidade, custo_unitario, observacao` + Modelo Excel.
- Permissão `estoque,criar`; botão discreto (outline) na tela de Movimentações,
  ao lado do "Nova Movimentação" — a tela não muda para quem já usa.
- **Semântica é "completar até o saldo da planilha", não "somar"**: grava ENTRADA do
  delta (`planilha − saldo atual da unidade`). Rodar o mesmo arquivo duas vezes NÃO
  duplica estoque — na 2ª vez o delta é 0 e a linha entra como *pulada* no modal.
- Saldo é **por unidade** (`session('unidade_id')`) — sem loja selecionada devolve 422.
- `origem_tipo = 'importacao_saldo_inicial'` identifica a carga no histórico.
- Produto inexistente vira **erro** da linha (não silêncio): importar produtos antes.

### Conversão da base do Hiper

O export do Hiper não bate com o template `produtos`; a conversão gera 3 planilhas:

| Arquivo | Conteúdo |
|---|---|
| `1_produtos.xlsx` | 1.913 linhas no template `produtos` |
| `2_estoque.xlsx` | 1.725 produtos / 2.842 un para o import de saldo |
| `3_ncm_revisar.xlsx` | 202 NCMs corrigidos, com confiança e motivo (para o contador) |

Pontos que mordem:

- **O Hiper não exporta código de barras.** O EAN-13 interno é gerado na conversão
  com o MESMO algoritmo do `ProdutoController::store` (`2` + `empresa%1000` +
  `codigo`(8) + DV) — ⚠️ **o `ImportController` NÃO gera EAN**, só o cadastro manual;
  sem essa coluna os 1.913 produtos ficariam sem barras e a etiqueta cairia em
  CODE128 do código interno.
- **Decimais precisam sair com vírgula.** `parseNumber` remove `.` e troca `,` por `.`
  — mandar `178.00` vira **17800**.
- **202 NCMs eram lixo do Hiper** (196 com `01012900` = cavalos vivos, mais
  `0102/0105`, e 1 com `00000000` que **barra a NFC-e** no pre-flight). Corrigidos por
  regra derivada da própria base (distribuição de NCM dos 1.711 válidos): categoria
  PRATA 925 → `71131100` (93%), SEMI JOIA → `71131900` (88%), zircônia → `71179000`
  (89%), clutch → `42021220` (86%), relógio/echarpe/carteira → 100%. **86 alta / 79
  média / 37 baixa confiança** — as 116 de média/baixa saem na planilha de revisão
  (bolsa de couro × têxtil muda o NCM e a base não decide).
- Origem: `"0 - Nacional"` → `0`, `"2 - Estrangeira…"` → `2`.
- O import de produtos **não cria** categoria, marca nem fornecedor (16/11/13 na base
  de origem) — ficam de fora.
- Achados para o lojista: 3 produtos com preço de venda < custo (códigos 3759, 3430,
  3365) e 2 com estoque negativo (5146, 5029 — ficam de fora da carga).

---

### Estilo "nome no topo" (12/08/2026)

`etiqueta_formatos.estilo` = `padrao` | `nome_topo`. Como o formato é por
empresa, o estilo **nunca vaza para outro cliente**.

No `nome_topo` (pedido da MISS MERLINDA, replicando o BarTender que ela já
usava): nome da loja em destaque no topo, **preços pequenos à direita**, código
de barras **grande no rodapé**. A descrição do produto e o código interno saem —
não cabem, e o layout de referência também não os tem. As fontes saem de
`EtiquetaFormato::layout()`, num ramo próprio: numa etiqueta 33 × 26 mm dá nome
9,2pt (era 5,6), preço 5,6pt (era 9,4) e barras 9,4 mm (eram 6,8).

⚠️ **`EstoqueMovimentacao` sem `estoque_id` estoura** desde a migration de
12/08. Foi assim que o cancelamento de venda quebrou (`VendaController::cancelar`
ficou de fora da varredura inicial) e o `EmpresaDemoSeeder` parou de rodar.
Toda gravação deve passar por `SaldoEstoque::registrar()`. Loja nova ganha o
estoque "Principal" por `Unidade::booted()` — cobre admin, Minhas Lojas e seeder
de uma vez.

### Bobina x formato: a conta que precisa fechar (12/08/2026)

**A página que o navegador manda TEM que ter a largura do papel.** Se for maior,
a impressora encolhe, corta ou gira — e o lojista vê etiqueta torta sem
mensagem de erro nenhuma.

```
colunas × largura + (colunas − 1) × espaço  =  largura da bobina
```

**Caso MISS MERLINDA (Elgin L42PRO FULL):** o formato foi cadastrado como
3 colunas × 3,3 cm + 0,2 = **página de 10,3 cm**, mas o driver da Elgin estava
em `USER (70,0 mm × 40,0 mm)` — **bobina de 7 cm**. 3,3 cm cabem só **2 vezes**
em 7 cm (2 × 3,3 + 0,4 = 7,0). Daí a impressão saiu girada e fora de registro.

Por isso o cadastro de formato ganhou o campo **"Largura da bobina (cm)"**
(só validação, não persiste): a tela mostra a conta ao vivo enquanto se digita
— verde quando cabe, vermelho dizendo **quantas colunas cabem** quando não cabe —
e o `formatoStore` recusa o formato mais largo que a bobina.

⚠️ **Três medidas têm que bater**, não duas: a etiqueta física (régua), o tamanho
de página no **driver** da impressora (`USER` em Preferências → Configuração de
página) e o formato no ERP. O driver da MISS MERLINDA também declara
`Altura do intervalo: 3,1 mm` — esse é o vão entre as fileiras e **não** entra na
conta da largura.


## Editor visual de layout de etiqueta (12/08/2026, noite)

> Branch `layout-etiquetas`. O lojista desenha a etiqueta: arrasta nome, preços,
> barras, imagens da galeria e formas (linha/retângulo) para onde quiser.

- **`etiqueta_formatos.layout_json`** (nullable): NULL = layout automático de sempre
  (`layout()`/`layoutInicial()`); preenchido = modo livre. "Voltar ao automático" é
  UPDATE para NULL. **Nenhum formato existente muda de comportamento** — os 3 da
  MISS MERLINDA seguiram idênticos no deploy.
- **Formatos FIXOS também são editáveis** sem mexer na constante: `formato_base`
  (unique `empresa_id+formato_base`) guarda só o desenho daquele fixo para aquela
  empresa (`editorFixo` cria via `firstOrCreate` e cai no mesmo editor). Esses
  registros NÃO aparecem em "Meus formatos" (filtro `whereNull('formato_base')`)
  e NÃO são imprimíveis por si (o `gerar()` também os exclui do resolve de
  `termica-custom-N` — imprimir por eles sairia na página errada).
- **Galeria `etiqueta_imagens`** (por empresa, máx. 30, sem SVG — é XML executável):
  o item do layout guarda só o `imagem_id`; quem resolve o arquivo é o servidor,
  conferindo a empresa dona. Upload em `storage/app/public/etiquetas/{empresa_id}/`
  (disco public — exige o symlink `public/storage`, já presente no container).
  Apagar imagem da galeria remove o arquivo; item de layout que apontava para ela
  é pulado em silêncio na impressão.
- **Sanitização no `layoutUpdate`** (o JSON vem do navegador e cai num `style=`):
  whitelist de tipos (`EtiquetaFormato::CAMPOS` + `DESENHOS`), fontes e alinhamentos
  fixos, cor só `#RRGGBB`, posição/tamanho clampados na etiqueta, máx. 40 itens,
  campos do ERP não repetem (linha/moldura/imagem repetem à vontade).
- **`print.blade`**: etiqueta com layout livre vira `position:relative` + itens
  absolutos em mm (`_elemento.blade.php`); barras com `preserveAspectRatio=none`
  no box exato e dígitos como item separado (`digitos_barras`). Sem layout salvo,
  o HTML é o mesmo de antes. `print-color-adjust: exact` global — retângulo
  preenchido some no papel sem isso ("Gráficos em segundo plano" do Chrome).
- `MEDIDAS_FIXOS` documenta a medida real de cada formato fixo (nas folhas A4 a
  medida cai do grid: 2x5 = 98,5×55 mm etc.) — é o tamanho da tela de desenho.

## Bonificação que deve voltar — peças em poder de terceiros (12/08/2026)

Pedido do documento do Dennis: na movimentação de estoque, quando a saída é para
influencer, marcar que **a peça deve retornar**, com quem está e a data prevista de
volta — e enxergar isso nos relatórios.

### Como funciona

A **baixa de estoque continua sendo a bonificação normal** — nada mudou no saldo.
A tabela `estoque_comodatos` é só o controle de responsabilidade: quem está com a
peça, desde quando e até quando.

- No formulário de movimentação, o bloco **"A peça volta?"** só aparece quando o tipo
  escolhido é **Bonificação**; dentro dele, os campos só abrem ao ligar o switch.
  Nas outras movimentações a tela é idêntica à de antes.
- Campos: **com quem fica** (obrigatório), contato (@ ou telefone) e **volta em**
  (obrigatório, não aceita data no passado). Valem para todos os itens da movimentação.
- Trocar o tipo para algo que não é bonificação **desliga o switch** — não existe
  comodato órfão. O `store` também guarda contra POST torto.

### Tela `/app/estoque/comodatos` — "Em poder de terceiros"

Entrou como 3º item **dentro do grupo Estoque** do menu, que segue recolhido por
padrão (tela operacional não muda).

- Cards: aguardando retorno · atrasadas · peças fora · já voltaram.
- Filtro padrão é **"ainda fora"** — o que interessa. Linha de atrasada fica vermelha
  com "N dias de atraso".
- **Registrar retorno**: aceita devolução **parcial** (status vira `parcial`) e recusa
  quantidade maior do que falta. A entrada volta para a **unidade de onde a peça saiu**,
  não a da sessão — senão a peça reaparece na loja errada.
- **Não voltou**: encerra como `perdido` e **não devolve estoque** (a baixa da
  bonificação já refletiu a perda). Pede confirmação.
- Comodato encerrado não aceita novo lançamento.

### Aviso no sino

`NotificacaoService::gerarAlertas` ganhou o alerta `comodato_atrasado`, que leva
direto para `/app/estoque/comodatos?situacao=atrasado`.

### Schema

```
estoque_comodatos: empresa_id, unidade_id, estoque_movimentacao_id, produto_id,
  quantidade, quantidade_devolvida, responsavel, contato,
  data_saida, data_prevista_retorno, data_retorno,
  status enum('pendente','parcial','devolvido','perdido'),  -- palavras neutras de gênero
  observacoes, user_id
```

Sem `softDeletes`: é trilha de responsabilidade sobre a peça.

### Bug pré-existente corrigido junto (armadilha 43)

Ao ligar o alerta do sino descobriu-se que `gerarAlertas` consultava
`produtos.estoque` — **coluna que nunca existiu** (o saldo é derivado das
movimentações). A consulta estourava, e como o `DashboardController` chama o método
dentro de um `try/catch` que engole tudo, o alerta de **estoque baixo** e o de
**trial expirando** (que vem depois no método) **nunca dispararam em produção**.
Corrigido com a mesma derivação de saldo do `RelatorioController`; os 3 alertas
foram validados juntos.

---

## Vários estoques por loja (12/08/2026)

Pedido do documento do Dennis: **criar mais de um estoque por loja, nomear como
quiser, transferir entre eles e saber em qual estoque o produto está.**

### O que mudou por baixo

Não existe tabela de saldo: o saldo é o `quantidade_posterior` da última
movimentação. **A chave dessa cadeia passou de `(unidade, produto)` para
`(estoque, produto)`** — e o saldo da LOJA virou a soma dos estoques dela.

Toda leitura/gravação passa a ir por `App\Services\SaldoEstoque`:

| Método | Para que serve |
|---|---|
| `noEstoque($estoqueId, $produtoId)` | saldo de um estoque |
| `naUnidade($unidadeId, $produtoId)` | saldo da loja (soma dos estoques) |
| `porEstoqueDaUnidade(...)` | quebra por estoque (inclui os zerados) |
| `porProdutoDaEmpresa($empresaId)` | saldo consolidado — usado no relatório |
| `estoqueDeVenda($unidadeId)` / `estoqueDeVendaId(...)` | de onde a venda baixa |
| `registrar(...)` | grava mantendo a cadeia anterior→posterior |

Foram 10 pontos que derivavam saldo na mão (PDV, venda balcão, pedido faturado,
movimentação, transferência, multiloja, import, venda remota, relatório,
comodato). Todos passaram a chamar o serviço.

### Migration — por que o saldo não mexeu

Três migrations em sequência (`2026_08_12_110000/110100/110200`):

1. Cria `estoques` e **um "Principal" por unidade** (`is_padrao` + `permite_venda`).
2. Adiciona `estoque_movimentacoes.estoque_id` **nullable**, faz o backfill de todo
   o histórico para o Principal da unidade, **aborta se sobrar órfã** e só então
   torna a coluna obrigatória. Como todo o histórico cai no mesmo estoque, a última
   movimentação de cada par continua sendo a mesma → **nenhum saldo muda**.
3. `transferencias_estoque` ganha `estoque_origem_id`/`estoque_destino_id`, com
   backfill para o Principal de cada ponta.

Validado em base com movimentação: saldos idênticos antes e depois, zero órfãs,
e a cadeia `anterior→posterior` conferida movimentação a movimentação.

### O que o lojista vê

- **Configurações da Loja → Estoques da Loja** (`/app/configuracoes/estoques`):
  CRUD com nome livre, código, situação. **Não virou item de menu** — loja com um
  estoque só não precisa saber que isso existe.
- **Um estoque de venda por loja** (`permite_venda`): é dele que o PDV e as vendas
  baixam, e o **PDV não ganhou seletor**. Marcar outro desmarca o anterior, e o
  sistema impede deixar a loja sem estoque de venda.
- **Movimentação**: o seletor "Em qual estoque" só aparece se a loja tiver mais de
  um. Com um só, a tela é idêntica à de antes.
- **Transferência**: a loja atual passou a aparecer na lista de destino, então
  transferir salão → depósito **dentro da mesma loja** é caso legítimo. O select de
  estoque de destino é preenchido por JS conforme a loja escolhida, e só aparece se
  aquela loja tiver mais de um estoque.
- **Estoque não se exclui, inativa** — o histórico continua no extrato.

### Onde ficou o saldo de cada tela

| Tela | Mostra |
|---|---|
| PDV | saldo da **loja** (soma) — o vendedor pensa em loja; a baixa é que sai do estoque de venda |
| Relatório de estoque | saldo consolidado da empresa |
| Multilojas → Estoque por Loja | saldo por loja; o ajuste da célula cai no estoque de venda |
| Movimentações | saldo do estoque escolhido |

---

## Relatório de contagem cega (12/08/2026)

Pedido do documento do Dennis: **folha de conferência sem a quantidade, com os
campos de cada estoque e o SKU para identificar o produto.**

`/app/relatorios/estoque-cego`, ao lado dos outros relatórios no menu.

- **Colunas**: SKU · código interno · código de barras · produto · categoria ·
  unidade · **uma coluna em branco por estoque** da loja. O cabeçalho traz o
  **nome do estoque + "Qtd. contada"**, a célula tem linha pontilhada e a coluna
  ganha fundo cinza (com `print-color-adjust: exact`, senão a impressora ignora)
  — sem isso a coluna era lida como espaço sobrando, não como campo de preencher.
  Criar um estoque novo em Configurações da Loja faz a coluna aparecer sozinha.
- **Sem saldo, de propósito** — quem conta não pode ser induzido pelo número que
  o sistema espera. Verificado no teste: as células do corpo saem vazias e
  nenhum dos saldos reais aparece no HTML.
- **Filtros**: loja, categoria, busca (SKU/código/descrição) e quais estoques
  entram na folha. Há um "só produtos com saldo" para contagem cíclica — o saldo
  decide a linha entrar, mas continua fora do papel.
- **Impressão** via `@media print`: some com sidebar/topbar, `thead` repete em
  toda página (`display: table-header-group`), linha não quebra no meio, margem
  de 12 mm. O cabeçalho com data e nome do conferente só existe no papel.
- **Exportação .xlsx** por `App\Support\Planilha` (armadilha 26 — nunca CSV, o
  Excel destrói zero à esquerda de SKU/EAN). As colunas de contagem saem vazias;
  como o helper grava referência de célula explícita (`r="B2"`), célula vazia
  **não desloca coluna**.

**Não implementado (decisão a tomar):** a folha só sai. O sistema ainda **não
recebe a contagem de volta** para gerar os ajustes e o relatório de divergência
— era a pergunta em aberto do plano. Enquanto isso, a conferência é manual.

---

## API de Integração (Gersen) (12/08/2026)

Primeira API máquina-a-máquina do ERP: **somente leitura**, versionada em `/api/integracao/v1`, criada para o **Gersen** (app.gersen.com.br) importar vendas/lojas/vendedores — mesmo papel que a API do Gestão Click cumpre para outros clientes do Gersen. Branch `integracao-gersen`.

### Endpoints (todos GET, JSON)

| Rota | Devolve |
|---|---|
| `/api/integracao/v1/ping` | `{ok, versao, empresa}` — teste de credencial |
| `/api/integracao/v1/lojas` | unidades da empresa: `id, nome, cnpj, cidade, uf, ativo` (`ativo` = `status === 'ativa'`, feminino — armadilha 5) |
| `/api/integracao/v1/vendedores` | users da empresa com perfil dono/gerente/vendedor/caixa: `id, nome, email, ativo` |
| `/api/integracao/v1/situacoes` | enum `StatusVenda` com `conta_como_venda` (só `concluida`) |
| `/api/integracao/v1/vendas?loja_id&inicio&fim&pagina` | vendas da unidade na janela (datas `Y-m-d`, inclusivas, máx 366 dias): `id, numero, data, total, vendedor_id/nome, cliente_nome, forma_pagamento, qtde_itens (soma de quantidades), situacao, tipo`; 100/página + `tem_mais` |

Semântica: **a data da venda é `created_at`** (como no resto do sistema — vendas `tipo=importada` têm `created_at` retroativo de propósito). Cancelamento aparece como `situacao=cancelada` na mesma listagem — o consumidor decide o que fazer (o Gersen exclui via filtro de situações). `qtde_itens` de venda importada = 1 (item genérico).

### Autenticação e segurança

- Token **Bearer por empresa**, gerado em DOIS lugares: `/admin/empresas/{id}` → aba **Integração** (`Admin\IntegracaoTokenController`, plataforma) e **`/app/configuracoes/integracao`** (`App\IntegracaoTokenController` — item "Integrações" no menu Gestão, dono/admin via `permission:configuracoes`; pedido do Dennis 13/08). Exibido **uma única vez**; persistimos só o **sha256** (`integracao_tokens.token_hash`, model `IntegracaoToken` — **sem** `BelongsToEmpresa` de propósito, acesso máquina-a-máquina não tem sessão). Revogar = `ativo=false`, efeito imediato.
- Middleware `App\Http\Middleware\IntegracaoApiToken` registrado **por classe na rota** (alias novo exigiria `bootstrap/app.php` = rebuild da imagem, armadilha 46) + `throttle:300,1`.
- **Escopo de tenant é sempre a empresa do token**: os controllers removem `EmpresaScope`/`UnidadeScope` UM A UM (`withoutGlobalScope(X::class)`) e filtram `empresa_id` na mão — os scopes globais dependem de sessão web e uma sessão de navegador logada NÃO pode vazar para a API. O `SoftDeletingScope` fica (armadilha 38).
- `loja_id` de outra empresa responde **404 idêntico** ao inexistente (não enumera unidades alheias).

### Logs

Canal dedicado `integracao` (`config/logging.php`, daily, 30 dias → `storage/logs/integracao-AAAA-MM-DD.log`): uma linha por request (empresa, token, path, query, status, ms) e uma por tentativa com token inválido (warning, com IP). O token guarda `last_used_at`/`last_used_ip` (visíveis na aba Integração).

### Migration

`2026_08_12_190000_create_integracao_tokens_table` — cria `integracao_tokens` e o índice `vendas_unidade_created_idx` em `vendas(unidade_id, created_at)` (a API consulta por loja+período; não havia índice por data).

### Consumidor (lado Gersen)

Provider `ERPIA365` no Gersen (repo `gersen-work`, `src/lib/integrations/providers/erpia365.ts`). Tráfego não sai do servidor: o container do Gersen resolve `erp.ia365.com.br` para o gateway Docker via `extra_hosts` (hairpin NAT não funciona de dentro da LAN; o nginx do host escuta em 0.0.0.0:443 e o certificado valida normalmente). Cancelamento propagado ao Gersen só dentro da janela de re-varredura dele (`rescanDays`, padrão 7 dias) — mesma limitação do Gestão Click; extensão futura: cursor por `updated_at`.

## Armadilhas conhecidas

1. **EmpresaScope recursão**: `auth()->user()` dentro do scope chama User model que tem o scope → loop infinito. Scopes têm flag `static $applying`. Não remover.
2. **Fornecedor NÃO tem `nome_razao_social`** — usa `razao_social`. Cliente SIM tem `nome_razao_social`.
3. **Fornecedor NÃO tem `status`** — não filtrar por status.
4. **Servico usa `codigo_lc116`** — NÃO `codigo` nem `codigo_servico_municipal`.
5. **Unidade.status é `ativa/inativa`** — NÃO `ativo/inativo`. **`categorias.status` também é feminino**
   (`enum('ativa','inativa')`) — ver armadilha 42. As demais (`produtos`, `clientes`, `servicos`,
   `users`, `empresas`, `contratos`) são masculinas. Confira o `SHOW COLUMNS` antes de escrever o literal.
6. **Venda.status é `concluida`** — NÃO `finalizada`.
7. **VendaItem.total é `total`** — NÃO `subtotal`.
8. **ConfiguracaoFiscal unique (empresa_id, unidade_id)** — NÃO usar `updateOrCreate` direto. Usar `where()->first()` + `update()` ou `create()` em fallback.
9. **`$user->perfil` é enum Perfil** — converter com `->value` antes de usar como string/array key.
10. **`$errors` pode ser null em views standalone** — `$errors = $errors ?? new ViewErrorBag()`.
11. **OrdemServico table = `ordens_servico`** — definir `$table` no model.
12. **Porta nginx é 8091** (não 8080, que estava ocupada na primeira versão).
13. **Services Focus NFe exigem FocusNFeClient com token** — usar `FocusNFeClient::forUnidade($unidade)` ou `fromConfig($config)`, nunca `app(NFeService::class)`.
14. **NFSeService::emitir aceita aliases** (`descricao` → `discriminacao`, `valor_servico` → `valor_servicos`). Validação de obrigatórios é ANTES da chamada à Focus.
15. **Certificado .pfx NÃO é persistido local** — upload direto à Focus via multipart. Só metadados ficam no banco.
16. **Activity log** automaticamente preserva `empresa_id` nas properties via listener no `AppServiceProvider`.
17. **`.env` em produção NÃO é bind-mounted** — usar `docker cp` para atualizar. Build da imagem usa Dockerfile próprio.
18. **Focus webhook é 1 evento por chamada** — payload `{event: "nfe", ...}` singular. Mandar `{eventos: [...]}` falha silenciosamente (corrigido em `cadastrarWebhook`).
19. **Migration de campos NFS-e (`codigo_municipio`)** assume tabelas `empresas/unidades/clientes` — usuário precisa preencher manualmente ou via autocomplete `/app/focus-autocomplete/municipio`.
20. **Snapshot fiscal só é aplicado em `creating`** do VendaItem — itens criados antes da migration 2026-05-28 não têm snapshot (caem no fallback `produto->*`).
21. **NUNCA sanitizar CNPJ com `preg_replace('/\D/')`** — destrói as letras do CNPJ
    alfanumérico (NT 2025.001). Usar `App\Support\Cnpj::limpar()` /
    `limparCpfCnpj()`. Para decidir CPF×CNPJ usar `Cnpj::pareceCpf()`, não `strlen == 11`.
22. **Alíquotas-teste 2026 são CBS 0,9% + IBS 0,1%** (LC 214/2025) — cuidado com material
    antigo do projeto que trazia o par invertido. Defaults corretos no
    `ReformaTributariaCalculator`; não preencher `*_aliquota_padrao` na config sem motivo.
23. **`ReformaTributariaCalculator::blocoPayload` retorna campos FLAT da Focus**
    (`ibs_cbs_*`, `ibs_uf_*`, `cbs_*`) para mesclar direto no item — não aninhar em
    `ibs`/`cbs`/`tributos_reforma` (formato antigo, ignorado pela Focus).
24. **IBS/CBS é automático para lucro_presumido/lucro_real** via `paraEmissao()` — não
    condicionar emissão só nas flags `ibs_ativo`/`cbs_ativo` (rejeição SEFAZ 03/08/2026).
24b. **`VendaItem.total` é LÍQUIDO do desconto do item** — no payload fiscal, `valor_bruto`
    (vProd) = `preco_unitario × quantidade` e o desconto vai em `valor_desconto`;
    `valor_desconto` do documento = Σ descontos dos itens + desconto global da venda
    (vNF = vProd − vDesc). Base dos tributos (ICMS/PIS/COFINS/IBS/CBS) = total líquido.
    Corrigido no review de 25/07 — antes `valor_bruto` recebia o líquido e desconto de
    item causaria rejeição SEFAZ (qtd×unitário ≠ vProd) + desconto em dobro.
25. **Admin da plataforma tem `empresa_id` NULL** — `auth()->user()->empresa` retorna null e qualquer deref direto (`->regime_tributario`, `->getPlanoAtivo()`) dá 500. O `EnsureUnidadeSelected` e o `CheckPlano` dão bypass para admin, então telas `/app/*` PRECISAM de guard próprio. Padrão adotado (fix 24/07/2026): telas de criação redirecionam com aviso (`ProdutoController::create/store`), telas de item existente usam a empresa do próprio registro (`ProdutoController::edit/show` → `?? $produto->empresa`), telas de plano redirecionam para `admin.dashboard` (`PlanoController`). Ao criar tela nova em `/app/*`, nunca derefar `->empresa->` sem guard — usar `?->` ou redirect. 25/07: `ConfiguracaoFiscalController::edit/update` ganharam o mesmo guard (dava 500 pro admin da plataforma).
    **12/08: mordeu de novo em 3 telas novas de uma vez** — `EstoqueController::store` e
    `EstoqueMovimentacaoController::store` gravavam `empresa_id => auth()->user()->empresa_id`
    (NULL → `Column 'empresa_id' cannot be null`, 500 na cara do usuário), `ComodatoController`
    dava 403 comparando com a empresa do usuário, e `RelatorioController::estoqueCego` montava a
    folha **sem coluna nenhuma** porque a lista de lojas vinha filtrada por NULL. Regra prática:
    em tela `/app/*`, **`empresa_id` de gravação vem da LOJA** (`Unidade::value('empresa_id')`),
    de leitura vem de `auth()->user()->empresa_id ?? session('empresa_id')`, e comparação de
    posse aceita `$user->is_admin`. Sempre teste a tela nova logado como admin da plataforma —
    é o caminho que ninguém exercita e que o cliente nunca reproduz.
26. **NUNCA gerar CSV como "modelo de planilha"** — usar `App\Support\Planilha` (.xlsx).
    Colunas de código precisam sair como texto, senão o Excel destrói zero à esquerda e EAN.
26b. **DEPLOY NÃO APARECE = faltou `docker restart erp-com-app`** — o php.ini de produção tem
    `opcache.validate_timestamps=0`: o FPM congela o bytecode quando o container sobe e IGNORA
    arquivos novos no disco (tinker/CLI enxergam o código novo, o site não — armadilha dupla:
    a validação por CLI passa e o navegador segue no antigo). Todo deploy termina com restart.
    **Sem poder reiniciar o container, a ordem importa (12/08 noite):** o boot roda `optimize`,
    então existe **cache de rotas/config** (`bootstrap/cache/routes-v7.php`) preso no opcache.
    Deploy com ROTA NOVA via `kill -USR2` no php-fpm exige: (1) tar do código, (2) `php artisan
    optimize` (recacheia rotas), (3) chown www-data dos caches (o exec roda como root —
    armadilha 34), (4) **USR2 DEPOIS do optimize** — reload antes do optimize serve a view nova
    com rotas velhas e `route()` estoura 500 ("Route not defined") na tela inteira.
27. **Upload 500 sem log no Laravel = permissão do nginx no container** — conferir
    `docker logs erp-com-app | grep client_body`. `/var/lib/nginx` tem que ser de `www-data`.
28. **Campo em branco no produto é `''`, não `null`** — `?? ` não pega. Em dado fiscal isso vira
    `""` no XML e a SEFAZ rejeita a nota inteira com "Erro na validação do Schema XML".
29. **Certificado A1 vai pela API de EMPRESAS com token master** (`PUT /v2/empresas/{id}`,
    `arquivo_certificado_base64` + `senha_certificado`). Não existe endpoint de certificado.
29a. **HTTP 200 da Focus NÃO significa evento aceito** — o DELETE de cancelamento devolve
    200 com `status` de erro quando a SEFAZ recusa (ex.: prazo de 30 min vencido). SEMPRE
    conferir `status == cancelado` no corpo antes de marcar cancelada local (nota 3 ficou
    "cancelada" no banco e AUTORIZADA na SEFAZ até o fix de 25/07 noite).
29b. **Caminhos de arquivo da Focus são RELATIVOS** — sempre usar `danfe_url_completa`/
    `xml_url_completa` do model (resolvem o host pelo ambiente), nunca redirect direto.
30. **A Focus controla número e série da NFC-e/NF-e** — os campos `serie_*` do ERP são registro
    local. Migração de sistema exige série nova ou reinício de numeração pedido à Focus.
31. **NUNCA injetar service Focus na assinatura de `handle()` de Command** — o container tenta
    resolver `FocusNFeClient` sem token e explode ANTES do handle (o backup de XMLs ficou 68
    noites quebrado por isso). Instanciar dentro do método com `FocusNFeClient::fromConfig()`.
32. **Todo cadastro de usuário dispara `BoasVindasUsuario` (fila)** — ao criar novo fluxo de
    criação de User, disparar o Mailable com o contexto certo (dono/funcionario/equipe)
    **passando a senha plana como 3º argumento** (decisão do Dennis 04/08: a senha vai no
    e-mail). Reenvio (tela da empresa) SEMPRE gera senha nova — nunca tentar "recuperar".
33. **Valores da plataforma só com `podeVerFinanceiro()`** — telas/menus/rotas que exibem
    faturas, contratos ou receita da IA365 precisam do guard (não basta `is_admin`).
    A ordem dos middlewares do grupo `/app` é `auth → suspensao → unidade` — não mover o
    `suspensao` para depois de rotas que consultam a empresa.
34. **`docker exec php artisan` roda como ROOT** — comando que escreve em
    `storage/` (ex.: `fiscal:baixar-xmls-notas`) cria arquivos que o site
    (www-data) não lê/escreve. Rodar com `-u www-data` ou chown depois.
    O scheduler/workers já rodam como www-data — só o exec manual morde.
35. **A Focus aceita 1 CNPJ com manifestação; lojas do mesmo CNPJ COMPARTILHAM a
    empresa Focus** — nunca criar 2ª empresa-filha para CNPJ repetido: o certificado,
    CSC, numeração e webhooks são do CNPJ. `FocusEmpresaService::criar` já reutiliza
    a config da irmã (05/08); se mexer no provisionamento, preservar esse caminho.
36. **`vendas.tipo` é ENUM no MySQL** (`pdv,balcao,online,pedido,importada`) — valor
    novo exige migration ALTER (sql_mode STRICT rejeita o INSERT; foi assim que o
    faturamento de pedidos ficou quebrado sem ninguém ver).
37. **Import: linha pulada ≠ erro** — processor devolve null = "pulada" (campo
    obrigatório ausente) e entra no contador/modal. Ao criar novo import, validar e
    devolver null em vez de deixar exceção genérica estourar.
38. **`withoutGlobalScopes()` remove TAMBÉM o SoftDeletingScope** — contagem/busca
    passa a incluir registros soft-deletados (Venda/Cliente/ContaReceber têm
    deleted_at). Para excluir de verdade dado de teste, `forceDelete()`; para contar
    só vivos sem os scopes de tenant, adicionar `whereNull('deleted_at')`.
39. **O `ImportController` NÃO gera código de barras nem SKU automáticos** — o EAN-13
    interno (`2` + empresa + código + DV) e o `SKU-<codigo>` só nascem no
    `ProdutoController::store`, no cadastro manual. Produto importado sem a coluna
    `codigo_barras` fica sem barras e a etiqueta cai em CODE128 do código interno.
    Em migração, gerar o EAN na planilha (mesmo algoritmo) ou o cliente fica sem etiqueta.
40. **`parseNumber` do import remove o ponto e troca vírgula por ponto** — planilha com
    `178.00` vira **17800**. Todo decimal gerado para importação sai em padrão BR
    (`178,00`). Vale para preços, markup e custo.
41. **Import de saldo de estoque COMPLETA, não soma** (`import/estoque`): grava entrada
    do delta `planilha − saldo atual`. É o que torna o reprocessamento seguro — mas
    significa que rodar depois de vendas **recompleta** o saldo até o valor do arquivo.
    Carga de migração, não ajuste de rotina.
42. **`categorias.status` é `enum('ativa','inativa')` — o CRUD inteiro usava `'ativo'`** e nunca
    funcionou (fix 11/08/2026). Com `sql_mode` STRICT o MySQL trunca o valor inválido e o
    `INSERT` estoura: **criar categoria dava 500** (`SQLSTATE[01000] 1265 Data truncated for
    column 'status'`, flagrado ao vivo na MISS MERLINDA em 11/08 16:14). Os `where('status','ativo')`
    não davam erro — só **nunca casavam**, deixando o select de categoria-pai (`create`/`edit`) e o
    **filtro de categoria em `/app/produtos`** permanentemente vazios; e as `<option>` do form de
    edição nunca marcavam o valor real. 6 pontos corrigidos: `CategoriaController` (`create`,
    `store`, `edit`, `update`), `categorias/edit.blade.php` e `ProdutoController::index`.
    As 4 categorias que existiam no banco eram do seed da empresa 1 — **nenhum cliente jamais
    conseguiu criar categoria pela tela**. O `<x-erp.status-badge>` e o CSS já tratavam os dois
    gêneros, então a exibição nunca denunciou o problema.
43. **NÃO existe coluna `produtos.estoque`** — o saldo é sempre derivado do
    `quantidade_posterior` da ÚLTIMA movimentação de cada par (produto, unidade).
    `NotificacaoService::gerarAlertas` consultava essa coluna fantasma e estourava;
    o `DashboardController` chama o método dentro de `try/catch` **silencioso**, então
    o alerta de estoque baixo e o de trial expirando morreram sem deixar rastro
    (fix 12/08/2026). Duas lições: (a) para saldo, copie a derivação do
    `RelatorioController`; (b) `try/catch` que engole exceção esconde bug por meses —
    ao mexer em `gerarAlertas`, teste o método direto no tinker, não pelo dashboard.
44. **A chave do saldo é `(estoque, produto)`, não `(unidade, produto)`** (desde
    12/08/2026). Derivar saldo na mão com `GROUP BY unidade_id` volta a dar errado
    numa loja com depósito — passa a devolver o saldo de um estoque só. **Use
    `App\Services\SaldoEstoque`**, nunca escreva a derivação de novo. Para gravar,
    `SaldoEstoque::registrar()` mantém a cadeia `anterior→posterior` do estoque certo.
45. **`->latest()` em `estoque_movimentacoes` ordena por `created_at`, não por `id`** —
    duas movimentações no mesmo segundo saem em ordem indefinida e a cadeia de saldo
    pode ler a errada. O código antigo misturava `latest()` e `orderByDesc('id')`;
    o `SaldoEstoque` usa **sempre `id`**. Se precisar consultar direto, use `id`.
46. **A imagem `erp-com-app:latest` envelhece calada.** O deploy padrão só
    empacota `app database resources routes config` — `bootstrap/`, `public/`,
    `vendor/` e `composer.json` ficam congelados no que a imagem trouxe. Em 12/08 a
    imagem ainda era anterior ao middleware `suspensao`, então **um
    `--force-recreate` derrubaria o `/app` inteiro** com `Target class [suspensao]
    does not exist` (flagrado ao montar o ambiente de teste). **Resolvido em 12/08
    com rebuild da imagem a partir de `/root/erp`.** Ao mexer em `bootstrap/`,
    `public/` ou `composer.json`, rebuild — tar não resolve. E o `.env` **não está
    na imagem**: todo recreate exige `docker cp /root/erp/.env erp-com-app:/var/www/.env`
    depois (o do host está sincronizado desde 04/08).
47. **`estoque_movimentacoes.estoque_id` é OBRIGATÓRIO** desde 12/08 — gravar
    movimentação sem ele estoura `SQLSTATE[HY000] 1364`. Foi assim que o
    **cancelamento de venda** quebrou por 2h em produção (`VendaController::cancelar`
    escapou da varredura dos 10 pontos) e o `EmpresaDemoSeeder` parou de rodar.
    Sempre gravar por `SaldoEstoque::registrar()`. Loja nova ganha o estoque
    "Principal" por `Unidade::booted()` — cobre admin, Minhas Lojas e seeder de uma
    vez; sem isso a loja nasceria sem lugar de onde o PDV baixar.
48. **Registro de `etiqueta_formatos` com `formato_base` preenchido NÃO é formato
    imprimível** — é só o desenho de um formato fixo para uma empresa. Toda listagem
    de "formatos da empresa" filtra `whereNull('formato_base')`, e o `gerar()` exclui
    esses registros ao resolver `termica-custom-N`. Esquecer o filtro duplica o
    formato na tela ou imprime na página errada. E em `produtoExemplo()`/previews:
    empresa pode ter ZERO produtos ativos — deref de produto só com `?->`/`??`.
49. **O endpoint `/v2/backups` da Focus NÃO existe** — 404 em toda chamada (7 noites de
    05→12/08/2026; antes disso a armadilha 31 escondia o problema porque o command nem
    rodava). O pacote mensal auditável é montado LOCALMENTE pelo `BackupXmlService`
    a partir das cópias por nota. Se a Focus um dia lançar backup de verdade, avaliar —
    até lá, nenhum código deve chamar `/v2/backups`.

---

## Próximos passos

> Estado do banco conferido em **11/08/2026** (auditoria de produção). Onde a realidade
> divergia do que estava escrito aqui, o texto foi corrigido — vale a auditoria, não a
> memória do que se pretendia fazer.

- ✅ **`fiscal:backup-xmls` RESOLVIDO (12/08 madrugada)**: o 404 era o endpoint `/v2/backups`
  que **não existe** na Focus (armadilha 49). O command foi reescrito para montar o pacote
  localmente das cópias por nota — 1ª rodada gerou os zips da JS (2026-08, 2 XMLs) e da
  PRIME (2026-07, 3 XMLs) com `manifest.json`. Tela `/app/backups-xml` agora gera/baixa
  do nosso disco.
- **DONA DOURO (empresa 5, migrada 05/08)**: ✅ **12/08 madrugada: `ambiente=producao`** —
  config 15 virada; na Focus (empresa 239437) foram gravados o **CSC + ID Token** (estavam
  SÓ no banco local — NFC-e falharia no QR Code) e **série 2 / próximo 1** para NFC-e e
  NF-e (série nova porque não temos o último número do Hiper — série 2 é imune à
  duplicidade, armadilha 30). Token de produção validado (404 autenticado em consulta de
  ref). Registro local: `serie_nfce=2`, `serie_nfe=2`. Pendências que seguem: contador
  validar os 116 NCMs (`DONA_DOURO_3_ncm_revisar.xlsx`); etiquetas — **validar impressão
  física na Argox + bipar com o leitor**; avisar o lojista dos 3 produtos com venda < custo
  (3759, 3430, 3365) e 2 estoques negativos fora da carga (5146, 5029); **fatura anual de
  R$ 6.264 continua sem gerar** (geração manual — `/admin/financeiro`).
- **`focus_webhook_ids` NULL migrou de dono**: não é mais a empresa 5 — hoje a única config
  assim é a **21 (empresa 3 / unidade 18, STILO VINTE OUTLET)**. Resincronizar em
  `/admin/empresas/3/saude-focus`. Como a unidade 18 compartilha o CNPJ da Matriz JS
  (armadilha 35), os hooks são do CNPJ e provavelmente já existem na Focus — conferir antes
  de recadastrar.
- **STILO VINTE multi-CNPJ (05/08)**: emitir 1 NFC-e de teste numa filial JS (02/03/OUTLET)
  para confirmar o compartilhamento da empresa Focus; conferir CSC das PRIME (herdado da
  04 MATRIZ). Dennis reimportar a planilha de clientes que deu "0 de 70" (agora entra
  cliente sem CPF e o modal mostra o motivo de cada linha).
- **Financeiro da plataforma (04/08)** — estado real em 11/08:
  - **MISS MERLINDA**: a fatura 2026-08 (R$ 710, vence **20/08**) segue **`pendente`** —
    NÃO foi marcada como paga. `cobranca_bloqueio_automatico = 0`, então no vencimento
    ela só avisa, não suspende. Decidir se liga o bloqueio.
  - ⚠️ **DONA DOURO nunca vai gerar a fatura do 1º ano**: cobrança **anual de R$ 6.264**
    com `cobranca_geracao = manual` e `cobranca_proxima_renovacao = 2027-08-05`. O
    `plataforma:processar-cobrancas` só gera anuidade 30 dias antes da renovação **e só
    no modo automática** — ou seja, o ciclo 2026 (cliente entrou em 05/08) não tem fatura
    nenhuma no banco. Gerar manualmente em `/admin/financeiro`.
  - Mesmo desenho na **STILO VINTE** (R$ 7.000, renovação 21/07/2027, geração manual).
  - **EB GESTÃO (empresa 2)**: trial venceu **13/07** e nunca teve cobrança nem regime
    gratuito — está travada em "plano expirado" com zero produtos/clientes/vendas.
    Decidir: cancelar, dar cortesia ou configurar cobrança.
- **18 `failed_jobs` residuais** (`ProvisionarEmpresaFocusJob`, entre 25/07 e 04/08) —
  resíduo do 422 de CNPJ duplicado que o `configIrmaMesmoCnpj()` já resolveu. Nenhuma
  falha nova desde 04/08; só limpar (`queue:flush`).
- **E-mails (04/08)**: validar o 1º boas-vindas de cadastro real e o 1º reset de senha
  de cliente; **trocar a senha da caixa no-reply na Hostinger** (foi exposta em chat)
  e atualizar os `.env`. ✅ 04/08 13:47: `/root/erp/.env` do host sincronizado com o
  do container (backup `.env.bak-20260804`) — recreate não regride mais o remetente.
- **Etiqueta da MISS MERLINDA (11/08)**: formato **"Elgin 3 colunas"** já cadastrado
  (3,2 × 2,5 cm, 3 colunas, espaço 0,2 cm → bobina 10,0 cm). ⚠️ **O espaço de 0,2 cm é
  suposição** — imprimir 1 etiqueta física e conferir o alinhamento; se as colunas forem
  derivando para a direita, medir a largura total do liner e corrigir o campo (10,2 cm ⇒
  espaço 0,3 cm). A prévia em PDF já foi validada. Bipar com o leitor antes do lote.
- **Validar em produção (24/07)**: tabelas de preço no PDV com produto piloto; 1º fechamento de
  caixa com conferência completa; 1º pedido faturado com NF-e + e-mail automático.
- **Reforma Tributária**: emitir 1 NF-e de teste em homologação com os grupos IBS/CBS
  (`docker exec ... php artisan tinker` ou venda piloto) para validar aceite da Focus/SEFAZ;
  ao onboardar o primeiro cliente Lucro Presumido/Real, conferir cClassTrib dos produtos
  (default `000001` = tributação integral — casos de isenção/redução precisam do código
  próprio da tabela RT 2025.002).
- **CNPJ alfanumérico**: BrasilAPI ainda não consulta CNPJ com letras — quando surgir um
  cliente com CNPJ novo, cadastro manual (autopreenchimento pulado por design).
- **Fase 5 (opcional)**: CT-e + MDF-e (transportadoras) — `CTeService`, `MDFeService`, UI gated por plano.
- **NF-e de transferência automática** entre unidades em vendas remotas (CFOP 5152/6152) — hoje é registro interno; emitir nota torna o fluxo 100% fiscal.
- **Autocomplete `/v2/municipios`** na edição de empresa/unidade/cliente para popular `codigo_municipio` IBGE automaticamente.
- **Detector de mudança de regime tributário**: ao trocar `empresa.regime_tributario`, abrir modal "N produtos têm CST não compatível — revisar?" (M3 do plano original).
- **Responsividade mobile/tablet**: revisão do PDV em tablet (só 1 `@media` em `erp.css`).

---

## Referências

- Plano detalhado da integração fiscal: [`docs/PLANO_INTEGRACAO_FISCAL.md`](docs/PLANO_INTEGRACAO_FISCAL.md)
- Guia para agentes (Claude Code): [`CLAUDE.md`](CLAUDE.md)
- Focus NFe API: [postman collection](https://ia365.com.br/Focus%20NFe.postman_collection.json)
- Tabelas SEFAZ (CFOP/CST/CSOSN): CONFAZ + Receita Federal
- TIPI (NCM/IPI): Receita Federal
- IBPT (valor aproximado de tributos): LC 165/2018
