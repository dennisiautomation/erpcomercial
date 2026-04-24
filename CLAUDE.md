# ERP Comercial SaaS — Guia para Agentes

## Visão Geral
Sistema ERP SaaS multi-tenant para micro/pequenas/médias empresas. Admin (IA365) gerencia a plataforma. Cada empresa tem múltiplas unidades com estoque, caixa e fiscal independentes. Integração fiscal completa via Focus NFe (NFC-e, NF-e, NFS-e + CC-e + manifestação do destinatário). Substituição tributária interestadual.

## Stack
Laravel 12 (PHP 8.4) | Blade + Bootstrap 5.3 (CDN) + Bootstrap Icons | MySQL 8.0 | Redis | Docker (app, mysql, redis, nginx) | Focus NFe (REST) | Chart.js | JsBarcode | spatie/laravel-activitylog

## Estado Atual
- **272 rotas** registradas
- **140 views** Blade
- **162 testes** passando (490 assertions)
- **22 commits** (main)
- Integração Focus NFe: ~95% (falta apenas CT-e/MDF-e e backup mensal de XMLs)

## Docker
```bash
docker compose up -d
docker compose exec app php artisan migrate:fresh --seed --force  # rebuild completo
docker compose exec app php artisan test
```
| Container | Porta host | Porta container |
|---|---|---|
| erp-nginx | 8080 | 80 |
| erp-mysql | 3308 | 3306 |
| erp-redis | 6379 | 6379 |

**MySQL:** user=erp_user, pass=erp_password, db=erp_comercial, test_db=erp_comercial_test

## Logins Demo
| Perfil | Email | Senha |
|---|---|---|
| Admin | admin@ia365.com.br | admin123 |
| Dono | dono@demo.com | dono123 |
| Gerente | gerente@demo.com | gerente123 |
| Vendedor | vendedor@demo.com | vendedor123 |
| Caixa | caixa@demo.com | caixa123 |

## Arquitetura Multi-Tenant
- **empresa_id** em TODAS as tabelas de dados
- **unidade_id** em tabelas operacionais (vendas, estoque, caixa, notas fiscais)
- **Traits**: `BelongsToEmpresa`, `BelongsToUnidade` (app/Traits/)
- **Scopes**: `EmpresaScope`, `UnidadeScope` — TEM flag anti-recursão `static $applying`
- **CUIDADO**: `auth()->user()` dentro de scope causa recursão infinita se o User model também tem o scope
- `session('unidade_id')` define a unidade ativa — setar em testes com `withSession()`
- Admin/Dono veem todas as unidades (UnidadeScope não aplica para eles)

## RBAC
7 perfis em `App\Enums\Perfil`: admin(100), dono(90), gerente(70), financeiro(60), vendedor(50), caixa(40), consulta(10)
- Middleware `permission:modulo,acao` — ação padrão: `ver`
- Middleware `plano:feature` — verifica se plano da empresa inclui a feature
- `$user->perfil` é enum — usar `->value` para string
- Matriz em `app/Http/Middleware/CheckPermission.php::PERMISSIONS`

## Estrutura
```
app/
├── Enums/               # TipoNotaFiscal, StatusNotaFiscal, TipoManifestacao,
│                        # Perfil, StatusVenda, StatusEmpresa, RegimeTributario, ...
├── Exceptions/          # NotaFiscalCancelException, CartaCorrecaoException,
│                        # CertificadoDigitalException, ManifestacaoException,
│                        # NotaFiscalEmissaoException
├── Http/Controllers/
│   ├── Admin/           # Dashboard, Empresa, Unidade, Usuario, Onboarding, Plano
│   ├── App/             # 38+ (todos os módulos, incluindo AuditoriaController,
│   │                    # NFeRecebidaController)
│   ├── Auth/            # PasswordResetController
│   └── Webhook/         # FocusNFeWebhookController
├── Http/Middleware/     # CheckPermission, CheckPlano, EnsureUnidadeSelected
├── Jobs/                # EmitirNFeJob, EmitirNFSeJob, EmitirNFCeJob,
│                        # ConsultarNotaFiscalJob (polling NF-e/NFS-e),
│                        # SincronizarNFesRecebidasJob
├── Models/              # 40+ models incluindo CartaCorrecao, NFeRecebida
├── Providers/           # AppServiceProvider (enriquece Activity com empresa_id)
├── Scopes/              # EmpresaScope, UnidadeScope
├── Services/
│   ├── FocusNFe/        # FocusNFeClient (com postMultipart),
│   │                    # NFCeService, NFeService, NFSeService,
│   │                    # CertificadoDigitalService, SefazStatusService,
│   │                    # ManifestacaoService
│   ├── FiscalAutoConfig.php  # Presets CST/CSOSN/alíquotas por regime
│   ├── ICMSCalculator.php    # Cálculo ST interestadual
│   ├── NotificacaoService.php
│   └── OFXParser.php
└── Traits/              # BelongsToEmpresa, BelongsToUnidade, AuditableModel

resources/views/
├── admin/               # Dashboard, empresas, unidades, usuarios, planos, onboarding
├── app/                 # Todos os módulos incluindo auditoria/, nfes-recebidas/
├── auth/                # Login, reset-password
├── components/
│   ├── erp/             # page-header, stat-card, card, data-table, filter-bar,
│   │                    # status-badge, form-section, import-buttons,
│   │                    # empty-state, fiscal-tooltip
│   ├── alert.blade.php  # Flash messages (erro NÃO auto-dismissa)
│   ├── delete-form.blade.php
│   └── trial-banner.blade.php
└── layouts/app.blade.php

public/
├── css/erp.css          # Design system
└── js/erp-core.js       # Masks, ViaCEP, CNPJ lookup, autocomplete, import CSV,
                         # ERP.toast (com close manual),
                         # ERP.confirm (modal Bootstrap),
                         # spinner automático em submit e autocomplete
```

## Módulos Implementados

### Admin
- Dashboard com stats, onboarding wizard 4 steps (empresa→unidade→dono→fiscal)
- CRUD empresas, unidades, usuarios, planos

### Cadastros (todos com wizard step-by-step)
- **Clientes**: Wizard PF/PJ; endereço opcional; CPF/CNPJ auto-preenche via BrasilAPI; ViaCEP
- **Cliente rápido** (AJAX): `POST /app/clientes/quick` retorna JSON; modal em pedidos quando busca não acha
- **Produtos**: Wizard 3 steps; fiscal adaptativo (mostra campos quando `emissao_fiscal_ativa=true`, com badges indicando NF-e/NFC-e)
- **Fornecedores**: Wizard 3 steps; CNPJ lookup BrasilAPI
- **Serviços**: Wizard 2 steps; skip fiscal se não emite NFS-e
- **Funcionários**: Wizard 3 steps; perfil como cards visuais
- **Categorias**: CRUD hierárquico (parent/child)
- **Etiquetas**: Código de barras (JsBarcode), 3 formatos (10/21/40 por página)
- **Import/Export CSV**: Auto-detect delimitador; template download

### Vendas
- Orçamentos → Pedidos → Vendas (workflow com confirmação de transição)
- **Venda Balcão**: Fora do PDV, com itens dinâmicos + autocomplete
- PDV fullscreen dark, atalhos F1-F12, split payment, verificação estoque
- **Lógica de emissão no PDV**: `emissao_fiscal_ativa && emite_nfce` → emite NFC-e; senão, só cupom/recibo. Cupom/recibo **sempre é impresso**. Falha na NFC-e cai silenciosamente no recibo.
- Devoluções; Comissões (config por produto/categoria, pagamento em lote)

### Fiscal (Focus NFe — 95% cobertura)

**Configuração**
- Tela per-unidade: toggle geral + switches independentes `emite_nfe / emite_nfce / emite_nfse`
- Alerta se fiscal ativo mas nenhum tipo marcado (JS tempo-real)
- Campos NFS-e específicos: série, item LC 116, código tributação, regime especial, incentivador cultural
- **Upload certificado A1 (.pfx)**: enviado direto à Focus via multipart. Arquivo e senha NÃO armazenados localmente; só metadados (validade, CNPJ, nome, data). Badge colorido por vencimento.
- **Status SEFAZ por UF**: badge online/instável/offline, cache global 2min, refresh 60s
- Teste de conexão Focus (valida token)

**NFC-e (cupom, síncrona, PDV)**
- Emissão direta no fechamento de venda
- Consulta, cancelamento, inutilização de numeração
- Mensagens de erro SEFAZ traduzidas pt-BR (prazo, duplicidade, certificado, token, 5xx)

**NF-e (DANFE, assíncrona)**
- Emissão via `EmitirNFeJob` → encadeia `ConsultarNotaFiscalJob` (polling até 10 tentativas com backoff 30s→600s)
- Cancelamento, inutilização, reenvio email
- **Carta de Correção (CC-e)**: persistida em tabela `cartas_correcao` com sequência 1-20 (limite SEFAZ); histórico na view de show da nota com PDF/XML; erros Focus traduzidos

**NFS-e**
- Emissão assíncrona + polling automático (paridade com NF-e)
- Validação obrigatória de `discriminacao` (aceita alias `descricao`) antes de chamar Focus
- Cancelamento

**Transversal**
- Webhooks Focus NFe atualizam status
- **Manifestação do destinatário** (NFes recebidas): Ciência, Confirmação, Não Realizada, Desconhecimento. Sincronização automática a cada 4h (scheduler) + botão "Sincronizar agora". Exige justificativa ≥15 chars para Não Realizada/Desconhecimento. Modal com aviso reforçado para Desconhecimento (ato grave).
- **ST interestadual**: tabela `regras_icms` com alíquotas reais, calculadora ICMS-ST, FCP
- `FiscalAutoConfig`: presets CST/CSOSN/alíquotas por regime (Simples/Presumido/Real)

### Estoque
- Movimentações (entrada/saída/ajuste/transferência)
- Transferências entre unidades (solicitação→aprovação)

### Financeiro
- Contas receber/pagar com parcelas
- Fluxo de caixa com Chart.js
- Boletos e carnês (geração ainda placeholder — gap conhecido)
- Conciliação bancária (parser OFX)
- Contratos recorrentes com faturamento automático
- DRE por unidade/consolidado
- Plano de contas hierárquico + Centro de custos

### Auditoria (NOVO)
- `spatie/laravel-activitylog` 5.x
- Trait `AuditableModel` em: User, Cliente, Produto, Fornecedor, Venda, NotaFiscal, ConfiguracaoFiscal
- Activities enriquecidas com `empresa_id` (isolamento multi-tenant) via listener no `AppServiceProvider`
- Página `/app/auditoria` com filtros (tipo, evento, usuário, período) e drill-down antes/depois
- Acesso restrito a dono/admin (permission `auditoria:ver`)

### Planos e Assinatura
- 3 planos (Básico R$97, Profissional R$197, Enterprise R$397)
- Trial com dias restantes
- Feature gating por plano (middleware `plano:feature`)
- Limites: max_unidades, max_usuarios, max_produtos, max_notas_mes

### Funcionalidades Transversais
- **Reset de senha**, **Busca global** (clientes/produtos/vendas)
- **Notificações**: sino no topbar, contas vencidas, estoque baixo, trial expirando
- **Dashboard**: wizard setup 7 etapas (dispensável), stats/gráficos depois
- **Modal de confirmação Bootstrap** (`ERP.confirm`) substitui `window.confirm()` em 27 fluxos — via `data-confirm` no form ou button
- **Toasts** (`ERP.toast`) com botão close manual; erros ficam 8s ou até fechar
- **Loading spinner automático** em submit de form e autocomplete
- **Tooltips fiscais**: `<x-erp.fiscal-tooltip field="ncm" />` em linguagem leiga (NOTA: subutilizado — ver Gaps UX abaixo)

## Schema de Tabelas Importantes
```
empresas: cnpj, razao_social, nome_fantasia, regime_tributario, plano_id, em_trial, trial_inicio/fim, status
unidades: empresa_id, nome, cnpj, status (ativa/inativa/em_implantacao)
users: empresa_id, perfil (enum), is_admin, comissao_percentual, status
produtos: empresa_id, codigo_interno, descricao, preco_custo, markup, preco_venda, ncm, cfop, cst_csosn, icms/pis/cofins/ipi_aliquota
clientes: empresa_id, tipo_pessoa (pf/pj), cpf_cnpj, nome_razao_social  [endereço NULLABLE]
fornecedores: empresa_id, cpf_cnpj, razao_social (NÃO nome_razao_social!)
servicos: empresa_id, codigo_lc116 (NÃO codigo!), descricao, valor_padrao, iss_aliquota
vendas: empresa_id, unidade_id, total, status (concluida/cancelada/devolvida — NÃO 'finalizada')
venda_itens: total (NÃO subtotal!)
notas_fiscais: tipo (nfe/nfce/nfse), status, focus_ref, chave_acesso, xml_url, danfe_url
cartas_correcao: nota_fiscal_id, numero_sequencia (1-20), correcao, status, protocolo, xml_url, pdf_url
nfes_recebidas: chave_acesso UNIQUE, cnpj_emitente, tipo_ultima_manifestacao, protocolo_manifestacao
regras_icms: uf_origem, uf_destino, aliquota_interna, aliquota_interestadual, mva, fcp, tem_st
configuracoes_fiscais: empresa_id+unidade_id (unique), focus_token, emissao_fiscal_ativa,
  emite_nfe/nfce/nfse, serie_nfe/nfce/nfse, csc_nfce, csc_id_nfce,
  nfse_* (item_lista_servico, codigo_tributacao, regime_especial, incentivador_cultural),
  certificado_validade, certificado_enviado_em, certificado_cnpj, certificado_nome,
  tipo_cupom_pdv (legado)
activity_log: log_name, event, subject_type/id, causer_type/id, properties (→ empresa_id)
notificacoes: user_id, tipo, titulo, mensagem, url, lida
```

## ARMADILHAS CONHECIDAS (LEIA ANTES DE CODAR)
1. **EmpresaScope recursão**: `auth()->user()` dentro do scope chama User model que tem o scope → loop infinito. Scopes têm `static $applying` flag. Não remover.
2. **Fornecedor NÃO tem nome_razao_social** — usa `razao_social`. Cliente SIM tem `nome_razao_social`.
3. **Fornecedor NÃO tem coluna status** — não filtrar por status.
4. **Servico usa `codigo_lc116`** — NÃO `codigo` nem `codigo_servico_municipal`.
5. **Unidade status é `ativa/inativa`** — NÃO `ativo/inativo`.
6. **Venda status é `concluida`** — NÃO `finalizada`.
7. **VendaItem total é `total`** — NÃO `subtotal`.
8. **ConfiguracaoFiscal tem unique (empresa_id, unidade_id)** — NÃO usar `updateOrCreate` direto. Usar `where()->first()` + `update()` ou `create()`.
9. **$user->perfil é enum Perfil** — converter com `->value` antes de usar como string/array key.
10. **$errors pode ser null em views standalone** — usar `$errors = $errors ?? new ViewErrorBag()`.
11. **OrdemServico table = `ordens_servico`** — definir `$table` no model.
12. **Porta nginx é 8080** (não 8000, que estava ocupada).
13. **Services Focus NFe exigem FocusNFeClient com token** — nunca use `app(NFSeService::class)`, use `FocusNFeClient::fromConfig($config)`.
14. **NFSeService emitir() aceita aliases** (`descricao` → `discriminacao`, `valor_servico` → `valor_servicos`). Validação de obrigatórios é ANTES da chamada à Focus.
15. **Certificado .pfx NÃO é persistido local** — upload direto à Focus via multipart. Só metadados ficam no banco.
16. **Activity log** automaticamente preserva `empresa_id` nas properties via listener no AppServiceProvider.

## Blade Components (resources/views/components/erp/)
```blade
<x-erp.page-header title="Clientes" icon="people">botões aqui</x-erp.page-header>
<x-erp.stat-card icon="people" color="primary" :value="$total" label="Ativos" />
<x-erp.card title="Dados" icon="info-circle">conteúdo</x-erp.card>
<x-erp.data-table>thead+tbody<x-slot:pagination>{{ $items->links() }}</x-slot:pagination></x-erp.data-table>
<x-erp.filter-bar :action="route('...')">inputs</x-erp.filter-bar>
<x-erp.status-badge :status="$item->status" />
<x-erp.form-section title="Endereço" icon="geo-alt">campos</x-erp.form-section>
<x-erp.import-buttons :importRoute="route('app.import.clientes')" templateType="clientes" />
<x-erp.empty-state title="Nenhum registro" icon="inbox" description="..." />
<x-erp.fiscal-tooltip field="ncm" />
```

## JS Core (public/js/erp-core.js)
```html
<!-- Masks -->
<input data-mask="cpf|cnpj|cpfCnpj|cep|telefone|money|ncm">

<!-- ViaCEP -->
<input data-cep name="cep">

<!-- CNPJ BrasilAPI (retorna razao_social, nome_fantasia, endereço, ddd_telefone_1) -->
<input data-cnpj-lookup>

<!-- Autocomplete com loading spinner -->
<input data-autocomplete="/app/search/clientes" data-autocomplete-target="cliente_id"
       data-autocomplete-display="nome_razao_social">

<!-- Modal de confirmação (substitui window.confirm) -->
<form data-confirm="Tem certeza?">...</form>
<button data-confirm="Baixar pagamento?">...</button>

<!-- Toast -->
ERP.toast('Salvo!', 'success');
ERP.toast('Erro crítico', 'danger', {title: 'Atenção', duration: 0}); // 0 = não auto-dismiss
```

## Rotas de API (AJAX)
```
# Busca / Autocomplete
GET /app/search/clientes?q=     → [{id, nome_razao_social, cpf_cnpj, telefone}]
GET /app/search/produtos?q=     → [{id, descricao, codigo_interno, preco_venda}]
GET /app/search/fornecedores?q= → [{id, razao_social, cpf_cnpj}]
GET /app/search/vendedores?q=   → [{id, name, perfil}]
GET /app/search/global?q=       → {clientes, produtos, vendas}

# Fiscal
GET  /app/fiscal/calcular-st?uf_origem=SP&uf_destino=MG&valor=100
GET  /app/fiscal/tabela-st/SP
POST /app/configuracao-fiscal/testar     (valida token Focus)
POST /app/configuracao-fiscal/certificado (upload .pfx)
GET  /app/configuracao-fiscal/sefaz-status?uf=SP

# NFes Recebidas (manifestação do destinatário)
POST /app/nfes-recebidas/sincronizar
POST /app/nfes-recebidas/{id}/manifestar  {tipo, justificativa?}

# Cadastros rápidos
POST /app/clientes/quick        → JSON cliente criado

# Notificações / Import / Export
GET /app/notificacoes/contar     → {count}
GET /app/import/template/{tipo}
POST /app/import/{tipo}
GET /app/export/{tipo}

# Webhooks
POST /webhooks/focusnfe
```

## Scheduler (routes/console.php)
- `sincronizar-nfes-recebidas`: a cada 4h, despacha `SincronizarNFesRecebidasJob` para cada unidade com fiscal ativo

## Seeders (DatabaseSeeder chama nesta ordem)
1. **AdminSeeder** — cria admin@ia365.com.br
2. **PermissaoSeeder** — permissões por módulo/ação
3. **PlanoSeeder** — 3 planos
4. **EmpresaDemoSeeder** — empresa demo completa (unidade, usuários, config fiscal, caixa, categorias, produtos, estoque, cliente, fornecedor)
5. **RegraICMSSeeder** — alíquotas ICMS interestaduais (SP/RJ/MG/PR → todos)

## Gaps de UX conhecidos (para melhorar)
1. **Tooltips fiscais subutilizados**: existem em `<x-erp.fiscal-tooltip>` mas não estão aplicados em CSC, ID CSC, item LC 116, regime especial, incentivador cultural na tela de config fiscal. Nem em IE/IM nos cadastros de empresa/unidade.
2. **Mudança de regime tributário na empresa** não avisa sobre impacto em cascata nos produtos / config fiscal.
3. **IE/IM**: falta contexto de quando são obrigatórios. Validação por UF inexistente.
4. **Features do plano** (admin): checkboxes sem descrição de impacto prático. "Multilojas" vs "max_unidades" não tem diferença explicada.
5. **Comissão do vendedor** aparece em qualquer perfil (deveria aparecer só se perfil=vendedor).
6. **CNPJ da unidade**: opcional, mas falta contexto "se não tem, usa CNPJ da empresa".

## Gaps técnicos conhecidos
- **Gateway de pagamento real (PIX/cartão)**: hoje só strings estáticas ('pix'=>'17'). Sem integração Asaas/Mercado Pago.
- **Boletos**: geração em placeholder, PDF fake.
- **CT-e / MDF-e**: não implementados (fora de escopo atual).
- **Backup mensal XMLs** (Focus): não integrado.
- **Responsividade mobile/tablet**: só 1 `@media` em `erp.css`. PDV em tablet precisa revisão.

## Commits (ordem cronológica inversa)
```
74235ad feat(fiscal): manifestação do destinatário (NFes recebidas)
6d7b378 feat(fiscal): status SEFAZ por UF com cache global e badge
b048109 feat(fiscal): valida discriminacao NFS-e antes de enviar + aliases
32d4d7e feat(fiscal): polling automático de NFS-e + EmitirNFSeJob
0e471ed feat(fiscal): upload certificado digital A1 (.pfx) via Focus
bdb758f fix(produto): mostra campos fiscais quando emissão ativa
1539e71 feat: PDV decide emissão por emite_nfce + produto com badges
cbbc6ee feat: Carta de Correção + Auditoria com activity log
e8361fe feat: UX — modal de confirmação, toasts com close, cancel NFC-e feedback
7d5affa feat: cadastro rápido de cliente + config fiscal por tipo + BrasilAPI
b9946f9 docs: CLAUDE.md
4ce9598 feat: wizard setup expandido — 7 etapas
e9bee6c docs: CLAUDE.md
4552ad5 fix: config fiscal update sem duplicate key
2f36855 fix: servico codigo_lc116, config fiscal, search
11ebbd0 feat: wizards, ST interestadual, reset senha, busca global, notificações
598b553 feat: venda balcão, etiquetas, views padronizadas
3887a7a fix: dashboard setup, status concluida
7fe36d2 fix: botão recibo, fornecedor sem status
9951290 feat: onboarding, smart forms, import CSV, fiscal inteligente
fc36b55 fix: rebuild UX + bugfixes + smart forms
642c9de feat: ERP Comercial SaaS completo — Fases 1, 2 e 3
475f128 chore: setup inicial Laravel 12 + Docker
```

## Comandos
```bash
docker compose up -d
docker compose exec app php artisan migrate:fresh --seed --force
docker compose exec app php artisan test
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan route:list
docker compose exec app php artisan queue:work           # processar jobs (NF-e polling, manifestação)
docker compose exec app php artisan schedule:work        # scheduler (sync NFes recebidas 4h)
docker compose exec app composer dump-autoload           # após criar classes novas
```
