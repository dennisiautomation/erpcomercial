# ERP Comercial SaaS — Guia para Agentes

## Visão Geral
Sistema ERP SaaS multi-tenant para micro/pequenas/médias empresas. Admin (IA365) gerencia a plataforma. Cada empresa tem múltiplas unidades com estoque, caixa e fiscal independentes. Integração fiscal via Focus NFe (NFC-e, NF-e, NFS-e). Substituição tributária interestadual.

## Stack
Laravel 12 (PHP 8.4) | Blade + Bootstrap 5.3 (CDN) + Bootstrap Icons | MySQL 8.0 | Redis | Docker (app, mysql, redis, nginx) | Focus NFe (REST) | Chart.js | JsBarcode

## Estado Atual: ~49.000 linhas, 261 rotas, 138 views, 12 commits

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
- **unidade_id** em tabelas operacionais (vendas, estoque, caixa)
- **Traits**: `BelongsToEmpresa`, `BelongsToUnidade` (app/Traits/)
- **Scopes**: `EmpresaScope`, `UnidadeScope` (app/Scopes/) — TEM flag anti-recursão `static $applying`
- **CUIDADO**: `auth()->user()` dentro de scope causa recursão infinita se o User model também tem o scope
- `session('unidade_id')` define a unidade ativa — setar em testes com `withSession()`
- Admin/Dono veem todas as unidades (UnidadeScope não aplica para eles)

## RBAC
7 perfis em `App\Enums\Perfil`: admin(100), dono(90), gerente(70), financeiro(60), vendedor(50), caixa(40), consulta(10)
- Middleware `permission:modulo,acao` — ação padrão: `ver`
- Middleware `plano:feature` — verifica se plano da empresa inclui a feature
- `$user->perfil` é enum — usar `->value` para string

## Estrutura
```
app/
├── Enums/           # 11 enums (StatusEmpresa, Perfil, StatusVenda, etc.)
├── Http/Controllers/
│   ├── Admin/       # 5 (Dashboard, Empresa, Unidade, Usuario, Onboarding, Plano)
│   ├── App/         # 35+ (todos os módulos)
│   ├── Auth/        # PasswordResetController
│   └── Webhook/     # FocusNFeWebhookController
├── Http/Middleware/  # CheckPermission, CheckPlano, EnsureUnidadeSelected
├── Jobs/            # EmitirNFCeJob, EmitirNFeJob, ConsultarNotaFiscalJob
├── Models/          # 38 models (Empresa, Unidade, User, Venda, Produto, NotaFiscal, RegraICMS, Notificacao, etc.)
├── Scopes/          # EmpresaScope, UnidadeScope
├── Services/
│   ├── FocusNFe/    # FocusNFeClient, NFCeService, NFeService, NFSeService
│   ├── FiscalAutoConfig.php  # Presets fiscais por regime tributário
│   ├── ICMSCalculator.php    # Cálculo ST interestadual
│   ├── NotificacaoService.php
│   └── OFXParser.php
└── Traits/          # BelongsToEmpresa, BelongsToUnidade

resources/views/
├── admin/           # Dashboard, empresas CRUD, unidades, usuarios, planos, onboarding wizard (4 steps)
├── app/             # Todos os módulos: cadastros, vendas, PDV, estoque, financeiro, fiscal, etc.
├── auth/            # Login (glass-morphism), forgot-password, reset-password
├── components/
│   ├── erp/         # 9 componentes: page-header, stat-card, card, data-table, filter-bar, status-badge, form-section, import-buttons, empty-state, fiscal-tooltip
│   ├── alert.blade.php
│   ├── delete-form.blade.php
│   └── trial-banner.blade.php
└── layouts/app.blade.php  # Sidebar dark, topbar com busca global e sino notificações

public/
├── css/erp.css      # Design system (variáveis CSS, stat-card, erp-table, badge-status, btn-erp, etc.)
└── js/erp-core.js   # Inteligência: masks, ViaCEP, CNPJ lookup, autocomplete, import CSV, price calc, parcelas
```

## Módulos Implementados

### Admin
- Dashboard com stats, onboarding wizard 4 steps (empresa→unidade→dono→fiscal)
- CRUD empresas, unidades (shallow routes), usuarios, planos

### Cadastros (todos com wizard step-by-step)
- **Clientes**: Wizard PF/PJ com cards grandes, CPF/CNPJ auto-preenche, ViaCEP
- **Produtos**: Wizard 3 steps, fiscal inteligente por regime, CFOP/CST dropdown, tooltips
- **Fornecedores**: Wizard 3 steps, CNPJ lookup ReceitaWS
- **Serviços**: Wizard 2 steps, skip fiscal se não emite NFS-e
- **Funcionários**: Wizard 3 steps, perfil como cards visuais
- **Categorias**: CRUD hierárquico (parent/child)
- **Import CSV**: Botão + template em clientes/produtos/fornecedores
- **Export CSV**: Botão em todas as listas
- **Etiquetas**: Código de barras (JsBarcode), 3 formatos (10/21/40 por página)

### Vendas
- Orçamentos (conversão→pedido), Pedidos (workflow status), Vendas
- **Venda Balcão**: Criar venda fora do PDV com itens dinâmicos + autocomplete
- PDV fullscreen dark theme, atalhos F1-F12, split payment, verificação estoque
- Devoluções, Comissões avançadas (config por produto/categoria, pagamento em lote)

### Fiscal (Focus NFe)
- NFC-e (síncrona PDV), NF-e (assíncrona + polling), NFS-e
- Config fiscal simplificada: "Emite nota? SIM/NÃO" + modo avançado colapsável
- Tipos explicados: NFC-e = cupom PDV, NF-e = DANFE empresas, NFS-e = serviços
- Emitir NF-e direto da tela de vendas
- Recibo não fiscal imprimível em qualquer venda
- **ST interestadual**: tabela `regras_icms` com alíquotas reais (SP/RJ/MG/PR→todos), calculadora ICMS-ST, FCP
- FiscalAutoConfig: presets CST/CSOSN/alíquotas por regime (Simples/Presumido/Real)
- Webhooks Focus NFe para status updates

### Estoque
- Movimentações (entrada/saída/ajuste/transferência)
- Transferências entre unidades (solicitação→aprovação)

### Financeiro
- Contas receber/pagar com parcelas
- Fluxo de caixa com Chart.js
- Boletos e carnês
- Conciliação bancária (parser OFX)
- Contratos recorrentes com faturamento automático
- DRE por unidade/consolidado
- Plano de contas hierárquico + Centro de custos

### Planos e Assinatura
- 3 planos (Básico R$97, Profissional R$197, Enterprise R$397)
- Trial com dias restantes
- Feature gating por plano (middleware `plano:feature`)
- Limites: max_unidades, max_usuarios, max_produtos, max_notas_mes

### Funcionalidades Transversais
- **Reset de senha**: /esqueci-senha → token → nova senha (link no login)
- **Busca global**: topbar busca em clientes, produtos, vendas (AJAX com dropdown agrupado)
- **Notificações**: sino no topbar com contagem, contas vencidas, estoque baixo, trial expirando (tabela notificacoes, NotificacaoService, auto-gera no dashboard)
- **Dashboard inteligente**: wizard setup 7 etapas no primeiro acesso (dispensável com X), stats/gráficos depois
  - 7 etapas: produtos (3+), clientes, fornecedores, equipe, estoque, fiscal (opcional), primeira venda
  - Cada etapa: botão ação + importar CSV quando aplicável
  - Grid 2 colunas, cards com ícones, status verde quando concluído
  - Progresso em % com barra visual
- **Tooltips fiscais**: componente `<x-erp.fiscal-tooltip field="ncm" />` com linguagem simples
- **Export CSV**: botão em clientes, produtos, fornecedores, vendas, contas receber/pagar (ExportController)
- **Import CSV**: botão + download modelo em clientes, produtos, fornecedores (ImportController, auto-detect delimitador/encoding)

## Schema de Tabelas Importantes
```
empresas: cnpj, razao_social, nome_fantasia, regime_tributario, plano_id, em_trial, trial_inicio/fim, status
unidades: empresa_id, nome, cnpj, status (ativa/inativa/em_implantacao)
users: empresa_id, perfil (enum), is_admin, comissao_percentual, status
produtos: empresa_id, codigo_interno, descricao, preco_custo, markup, preco_venda, ncm, cfop, cst_csosn, icms/pis/cofins/ipi_aliquota
clientes: empresa_id, tipo_pessoa (pf/pj), cpf_cnpj, nome_razao_social
fornecedores: empresa_id, cpf_cnpj, razao_social (NÃO nome_razao_social!)
servicos: empresa_id, codigo_lc116 (NÃO codigo!), descricao, valor_padrao, iss_aliquota
vendas: empresa_id, unidade_id, total, status (concluida/cancelada/devolvida — NÃO 'finalizada')
venda_itens: total (NÃO subtotal!)
notas_fiscais: tipo (nfe/nfce/nfse), status, focus_ref, chave_acesso, xml_url, danfe_url
regras_icms: uf_origem, uf_destino, aliquota_interna, aliquota_interestadual, mva, fcp, tem_st
configuracoes_fiscais: empresa_id+unidade_id (unique), focus_token, emissao_fiscal_ativa, tipo_cupom_pdv
notificacoes: user_id, tipo, titulo, mensagem, url, lida
```

## ARMADILHAS CONHECIDAS (LEIA ANTES DE CODAR)
1. **EmpresaScope recursão**: `auth()->user()` dentro do scope chama o User model que tem o scope → loop infinito. Scopes têm `static $applying` flag. Não remover.
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

## Blade Components (resources/views/components/erp/)
Usar SEMPRE para consistência:
```blade
<x-erp.page-header title="Clientes" icon="people">botões aqui</x-erp.page-header>
<x-erp.stat-card icon="people" color="primary" :value="$total" label="Ativos" />
<x-erp.card title="Dados" icon="info-circle">conteúdo</x-erp.card>
<x-erp.data-table>thead+tbody<x-slot:pagination>{{ $items->links() }}</x-slot:pagination></x-erp.data-table>
<x-erp.filter-bar>inputs</x-erp.filter-bar>
<x-erp.status-badge :status="$item->status" />
<x-erp.form-section title="Endereço" icon="geo-alt">campos</x-erp.form-section>
<x-erp.import-buttons :importRoute="route('app.import.clientes')" templateType="clientes" />
<x-erp.empty-state title="Nenhum registro" icon="inbox" />
<x-erp.fiscal-tooltip field="ncm" />
```

## JS Core (public/js/erp-core.js)
```html
<!-- Masks -->
<input data-mask="cpf"> <input data-mask="cnpj"> <input data-mask="cpfCnpj">
<input data-mask="cep"> <input data-mask="telefone"> <input data-mask="ncm">

<!-- ViaCEP auto-preenche endereço -->
<input data-cep name="cep">

<!-- CNPJ lookup ReceitaWS auto-preenche razao_social, endereço, etc -->
<input data-cnpj-lookup>

<!-- Autocomplete -->
<input data-autocomplete="/app/search/clientes" data-autocomplete-target="cliente_id" data-autocomplete-display="nome_razao_social">

<!-- Price calculator -->
<input data-price="custo"> <input data-price="markup"> <input data-price="venda">

<!-- Import CSV -->
<button data-import="/app/import/clientes">

<!-- Parcelas generator -->
<div data-parcelas>
```

## Rotas de API (AJAX)
```
GET /app/search/clientes?q=      → JSON [{id, nome_razao_social, cpf_cnpj, telefone}]
GET /app/search/produtos?q=      → JSON [{id, descricao, codigo_interno, preco_venda}]
GET /app/search/fornecedores?q=  → JSON [{id, razao_social, cpf_cnpj}]
GET /app/search/vendedores?q=    → JSON [{id, name, perfil}]
GET /app/search/global?q=        → JSON {clientes: [...], produtos: [...], vendas: [...]}
GET /app/fiscal/calcular-st?uf_origem=SP&uf_destino=MG&valor=100 → JSON cálculo ST
GET /app/fiscal/tabela-st/SP     → JSON tabela ST para todos os estados
GET /app/notificacoes/contar     → JSON {count: N}
GET /app/import/template/{tipo}  → Download CSV modelo
POST /app/import/clientes        → JSON {success, imported, errors}
GET /app/export/clientes         → Download CSV
POST /webhooks/focusnfe          → 200 (webhook Focus NFe)
```

## Seeders (DatabaseSeeder chama nesta ordem)
1. **AdminSeeder** — cria admin@ia365.com.br
2. **PermissaoSeeder** — cria permissões por módulo/ação
3. **PlanoSeeder** — 3 planos (Básico R$97, Profissional R$197, Enterprise R$397)
4. **EmpresaDemoSeeder** — empresa demo completa:
   - Empresa com plano Enterprise, trial 30 dias
   - Unidade "Loja Centro"
   - 5 usuários (dono, gerente, vendedor, caixa) vinculados à unidade
   - ConfiguracaoFiscal (homologação, cupom não fiscal)
   - Caixa aberto para o operador de caixa
   - 2 categorias, 3 produtos com dados fiscais
   - Estoque inicial (100 unidades cada produto)
   - 1 cliente PF, 1 fornecedor PJ
5. **RegraICMSSeeder** — alíquotas ICMS interestaduais reais (SP/RJ/MG/PR → todos os estados)

## Commits
```
4ce9598 feat: wizard setup expandido — 7 etapas
e9bee6c docs: CLAUDE.md atualizado
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
```
