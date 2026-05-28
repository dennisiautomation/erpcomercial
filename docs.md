# ERP Comercial IA365 — Documentação Técnica

> SaaS ERP multi-tenant para PMEs. Admin (IA365) gerencia a plataforma; cada empresa-cliente tem múltiplas unidades com fiscal, estoque e caixa independentes. Integração 100% Focus NFe (NF-e, NFC-e, NFS-e, CC-e, manifestação do destinatário, backup XMLs).

**Última revisão:** 2026-05-28 · **Estado:** integração fiscal Fase 1-4 + multi-loja concluídas

---

## Sumário
1. [Stack e ambientes](#stack-e-ambientes)
2. [Multi-tenancy](#multi-tenancy)
3. [Integração fiscal Focus NFe](#integração-fiscal-focus-nfe)
4. [Multi-loja — política de estoque](#multi-loja--política-de-estoque)
5. [Schema essencial](#schema-essencial)
6. [Comandos artisan](#comandos-artisan)
7. [Crons agendados](#crons-agendados)
8. [Logins demo](#logins-demo)
9. [Armadilhas conhecidas](#armadilhas-conhecidas)
10. [Próximos passos](#próximos-passos)

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

- **Backup XMLs** (`fiscal:backup-xmls`, diário 3h): solicita backup mensal Focus, baixa para `storage/app/fiscal/backups/{cnpj}/{YYYY-MM}.zip`. Retenção 5 anos.
- **Saúde de webhooks** (`fiscal:saude-webhooks`, segunda 4h): compara `focus_webhook_ids` locais vs lista remota Focus; recadastra ausentes; notifica dono.
- **Alerta certificado** (`fiscal:alertar-certificado`, diário 8h): janelas 30/15/7/1 dias antes do vencimento.
- **Dashboard fiscal** `/app/fiscal/dashboard`: NF-es por status (30d), top 5 erros, série diária 14d (Chart.js), saúde por unidade, status SEFAZ por UF.

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
└── AlertarCertificadoVencendoCommand.php

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

## Schema essencial

### Tabelas-chave (campos relevantes)

```text
empresas
  cnpj, razao_social, nome_fantasia, regime_tributario, plano_id,
  em_trial, trial_inicio/fim, status,
  politica_estoque_inter_unidade ENUM('silos','ver_apenas','ver_e_vender'),
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

---

## Armadilhas conhecidas

1. **EmpresaScope recursão**: `auth()->user()` dentro do scope chama User model que tem o scope → loop infinito. Scopes têm flag `static $applying`. Não remover.
2. **Fornecedor NÃO tem `nome_razao_social`** — usa `razao_social`. Cliente SIM tem `nome_razao_social`.
3. **Fornecedor NÃO tem `status`** — não filtrar por status.
4. **Servico usa `codigo_lc116`** — NÃO `codigo` nem `codigo_servico_municipal`.
5. **Unidade.status é `ativa/inativa`** — NÃO `ativo/inativo`.
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

---

## Próximos passos

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
