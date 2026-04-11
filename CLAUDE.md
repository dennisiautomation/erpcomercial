# ERP Comercial SaaS — Guia Completo para Agentes

## Visão Geral

Sistema ERP comercial SaaS multi-tenant para micro, pequenas e médias empresas. Inspirado no GestãoClick com integração fiscal via Focus NFe. O Admin (IA365) gerencia a plataforma, cadastra empresas clientes, cada empresa tem múltiplas unidades (filiais) com estoque, caixa e fiscal independentes.

## Stack Tecnológica

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 12 (PHP 8.4) |
| Frontend | Blade + Bootstrap 5.3 (CDN) + Bootstrap Icons |
| Banco | MySQL 8.0 |
| Cache/Filas | Redis |
| Infra | Docker (4 containers: app, mysql, redis, nginx) |
| API Fiscal | Focus NFe (REST, JSON, Basic Auth) |
| Autenticação | Laravel Auth + Middleware CheckPermission (RBAC) |
| Multi-tenant | empresa_id + unidade_id em todas as tabelas + Global Scopes |

## Docker

```bash
docker compose up -d          # Subir containers
docker compose exec app bash  # Acessar container app
```

| Container | Porta | Uso |
|---|---|---|
| erp-nginx | 8000 | HTTP (app) |
| erp-mysql | 3308 (host) → 3306 (container) | MySQL |
| erp-redis | 6379 | Cache, sessões, filas |
| erp-app | 9000 (FPM) | PHP-FPM |

**Credenciais MySQL:** user=erp_user, pass=erp_password, db=erp_comercial, test_db=erp_comercial_test

## Acesso ao Sistema

| Perfil | Email | Senha |
|---|---|---|
| Admin (IA365) | admin@ia365.com.br | admin123 |
| Dono empresa | dono@demo.com | dono123 |
| Gerente | gerente@demo.com | gerente123 |
| Vendedor | vendedor@demo.com | vendedor123 |
| Caixa | caixa@demo.com | caixa123 |

## Arquitetura Multi-Tenant

### 3 Níveis de Hierarquia
1. **Admin (IA365)** — operador master da plataforma, `is_admin=true`, sem empresa_id
2. **Empresa** — cliente do SaaS (CNPJ), com múltiplas unidades
3. **Unidade** — filial/loja, com estoque, caixa e fiscal independentes

### Isolamento de Dados
- **Trait `BelongsToEmpresa`** (app/Traits/) — Adiciona EmpresaScope automático + auto-set empresa_id no creating
- **Trait `BelongsToUnidade`** (app/Traits/) — Adiciona UnidadeScope + auto-set unidade_id da sessão
- **EmpresaScope** (app/Scopes/) — Filtra por `auth()->user()->empresa_id`
- **UnidadeScope** (app/Scopes/) — Filtra por `session('unidade_id')`, NÃO aplica para Admin/Dono (veem tudo)
- **Regra**: TODA tabela que pertence a uma empresa TEM `empresa_id`. Tabelas de operação (vendas, estoque, caixa) TEM TAMBÉM `unidade_id`

### Fluxo de Login
1. Usuário faz login
2. Se Admin → redireciona para `/admin/dashboard`
3. Se empresa user com 1 unidade → auto-seleciona e vai para `/app/dashboard`
4. Se empresa user com N unidades → redireciona para `/selecionar-unidade`
5. Após selecionar unidade → `session('unidade_id')` é definido → acessa o sistema

## RBAC — Perfis e Permissões

7 perfis definidos em `App\Enums\Perfil`:

| Perfil | Nível | Escopo |
|---|---|---|
| admin | 100 | Toda plataforma |
| dono | 90 | Empresa + todas unidades |
| gerente | 70 | Unidade específica |
| financeiro | 60 | Financeiro da empresa/unidade |
| vendedor | 50 | Vendas da unidade |
| caixa | 40 | PDV da unidade |
| consulta | 10 | Apenas visualização |

**Middleware `CheckPermission`** (app/Http/Middleware/CheckPermission.php):
- Uso na rota: `->middleware('permission:modulo,acao')`
- Ação padrão se omitida: `ver`
- Admin sempre tem acesso total
- Matriz de permissões hardcoded no middleware (constante PERMISSIONS)
- `$user->perfil` é um enum `Perfil` — converter para `.value` antes de usar como índice de array

**Middleware `EnsureUnidadeSelected`**: Redireciona para seleção de unidade se `session('unidade_id')` não existir.

## Estrutura de Diretórios

```
app/
├── Enums/              # 11 enums PHP 8.4 (StatusEmpresa, Perfil, etc.)
├── Http/
│   ├── Controllers/
│   │   ├── Admin/      # 4 controllers (Dashboard, Empresa, Unidade, Usuario)
│   │   ├── App/        # 28 controllers (todos os módulos do ERP)
│   │   └── Webhook/    # 1 controller (FocusNFeWebhook)
│   └── Middleware/      # CheckPermission, EnsureUnidadeSelected
├── Jobs/               # 3 jobs (EmitirNFCe, EmitirNFe, ConsultarNotaFiscal)
├── Models/             # 37 models Eloquent
├── Scopes/             # EmpresaScope, UnidadeScope
├── Services/
│   ├── FocusNFe/       # FocusNFeClient, NFCeService, NFeService, NFSeService
│   └── OFXParser.php   # Parser de extratos bancários OFX
└── Traits/             # BelongsToEmpresa, BelongsToUnidade

database/
├── migrations/         # 42 migrations
└── seeders/            # AdminSeeder, PermissaoSeeder, EmpresaDemoSeeder

resources/views/
├── admin/              # Views do painel admin
├── app/                # Views dos módulos ERP
├── auth/               # Login
├── components/         # Alert, DeleteForm
└── layouts/            # app.blade.php (layout base com sidebar)

routes/web.php          # 208 rotas
tests/                  # 125 testes / 411 assertions
```

## Módulos Implementados

### Fase 1 — MVP
- **Painel Admin**: Dashboard, CRUD empresas, onboarding 4 passos, unidades, usuários
- **Cadastros**: Clientes (PF/PJ), Produtos (c/ fiscal), Fornecedores, Categorias, Serviços, Funcionários, Transportadoras
- **Vendas**: Orçamentos (conversão→pedido), Pedidos (workflow status), Vendas, Devoluções, Comissões
- **PDV**: Frente de caixa fullscreen, código barras, split payment, atalhos F1-F12, cupom não fiscal 80mm
- **Estoque**: Movimentações (entrada/saída/ajuste), Transferências entre unidades, Inventário
- **Financeiro**: Contas a receber/pagar, Fluxo de caixa, Parcelas
- **Relatórios**: Vendas, Estoque, Financeiro

### Fase 2 — Fiscal (Focus NFe)
- **NFC-e**: Emissão síncrona no PDV, cancelamento, inutilização
- **NF-e**: Emissão assíncrona, consulta polling, cancelamento, carta de correção
- **NFS-e**: Emissão para serviços, cancelamento
- **Webhooks**: Receiver para status updates da Focus NFe
- **Config Fiscal**: Por unidade (token, série, ambiente, toggle fiscal/não fiscal)
- **Jobs**: EmitirNFCeJob, EmitirNFeJob, ConsultarNotaFiscalJob (com backoff)

### Fase 3 — Avançado
- **Dashboard Multilojas**: Visão consolidada, ranking, comparação entre unidades, alertas
- **Ordens de Serviço**: CRUD, workflow status, laudo técnico, conversão OS→Venda
- **Contratos Recorrentes**: Cobranças automáticas, faturamento periódico
- **Boletos**: Geração, carnês, baixa, cancelamento
- **Conciliação Bancária**: Parser OFX, conciliação auto/manual, interface split-view
- **DRE**: Por unidade e consolidado, plano de contas hierárquico, exportação CSV
- **Centro de Custos**: CRUD vinculado a contas pagar/receber
- **Comissões Avançadas**: Relatórios, pagamento em lote, config por produto/categoria

### Fase 4 — Expansão (PENDENTE)
- [ ] API REST pública para integrações terceiros
- [ ] Integrações e-commerce (Nuvemshop, Mercado Livre)
- [ ] App mobile (PWA)
- [ ] Módulo de atendimento e agenda
- [ ] Importação de XML de NF-e de entrada (compras)
- [ ] Geração de etiquetas de código de barras
- [ ] Cotações online com múltiplos fornecedores
- [ ] Integração bancária para boletos registrados (API bancos)
- [ ] Manifesto do Destinatário (MD-e)
- [ ] NFS-e Nacional (novo padrão)

## Integração Fiscal — Focus NFe

### Arquitetura Multi-Tenant
- Cada unidade tem sua `ConfiguracaoFiscal` com token Focus NFe próprio
- `FocusNFeClient::forUnidade($unidade)` — cria client autenticado para a unidade
- Ambientes separados por unidade (homologação/produção)

### Endpoints Focus NFe
| Operação | Método | Endpoint | Processamento |
|---|---|---|---|
| Emitir NFC-e | POST | /v2/nfce?ref={ref} | Síncrono |
| Consultar NFC-e | GET | /v2/nfce/{ref} | — |
| Cancelar NFC-e | DELETE | /v2/nfce/{ref} | — |
| Emitir NF-e | POST | /v2/nfe?ref={ref} | Assíncrono |
| Consultar NF-e | GET | /v2/nfe/{ref}?completa=1 | — |
| Cancelar NF-e | DELETE | /v2/nfe/{ref} | — |
| CC-e NF-e | POST | /v2/nfe/{ref}/carta_correcao | — |
| Inutilizar | POST | /v2/nfe/inutilizacao | — |
| Emitir NFS-e | POST | /v2/nfse?ref={ref} | Assíncrono |

### URLs
- Homologação: `https://homologacao.focusnfe.com.br`
- Produção: `https://api.focusnfe.com.br`
- Auth: HTTP Basic (token como user, senha vazia)

### Fluxo PDV com Fiscal
1. Venda registrada no PDV
2. Verifica `ConfiguracaoFiscal` da unidade
3. Se `emissao_fiscal_ativa=true` e `tipo_cupom_pdv=fiscal` → emite NFC-e síncrona
4. Se erro na NFC-e → fallback para cupom não fiscal (recibo)
5. Se fiscal desativado → gera cupom não fiscal

### Webhook
- Endpoint público: `POST /webhooks/focusnfe`
- Recebe status updates (autorizada, cancelada, etc.)
- Atualiza NotaFiscal no banco

## Convenções de Código

### Models
- Usar traits `BelongsToEmpresa` e/ou `BelongsToUnidade` conforme a tabela
- Definir `$table` explicitamente para nomes em português irregular (fornecedores, permissoes, etc.)
- Casts para enums PHP 8.4: `'status' => StatusEmpresa::class`
- Casts decimais: `'valor' => 'decimal:2'`
- SoftDeletes em todas as tabelas exceto audit trails (estoque_movimentacoes)
- `withoutGlobalScopes()` ao criar registros em seeders/testes

### Controllers
- Admin: `App\Http\Controllers\Admin\` — verificar `$request->user()->is_admin` ou `abort(403)`
- App: `App\Http\Controllers\App\` — protegidos por middleware `permission:modulo`
- Usar DB::transaction() para operações que afetam múltiplas tabelas
- Retornar JSON para endpoints AJAX (PDV, busca de produto, etc.)
- Redirect com flash message para operações CRUD tradicionais

### Views
- Estender `layouts.app`
- Usar `@section('content')` e `@push('scripts')`
- Bootstrap 5.3 via CDN
- Formulários: `@csrf`, `@method('PUT')` quando necessário
- Validação: `@error('field')` com classe `is-invalid`
- Inputs pré-preenchidos: `old('field', $model->field)`

### Rotas
- Admin: `admin.*` (prefix `/admin`)
- App: `app.*` (prefix `/app`, middleware `auth` + `unidade`)
- Webhook: sem auth, prefix `/webhooks`
- Resources usam `Route::resource()` com middleware inline

### Testes
- `RefreshDatabase` trait em todos os testes
- `Tests\Traits\CreatesTestData` para criar empresa, unidade, user, produto, cliente
- `Http::fake()` para mockar Focus NFe
- `$this->actingAs($user)->withSession(['unidade_id' => $id])` para simular contexto
- `withoutGlobalScopes()` ao criar registros de teste
- Test database: `erp_comercial_test` (MySQL, mesma instância Docker)

## Comandos Úteis

```bash
# Rodar na raiz do projeto (/Users/denniscanteli/Desktop/erp/erp-comercial)

# Docker
docker compose up -d
docker compose exec app bash

# Dentro do container (ou com docker compose exec app prefixo)
php artisan migrate --force
php artisan migrate:fresh --force       # Cuidado: apaga tudo
php artisan db:seed --force
php artisan test
php artisan test --filter="NomeTeste"
php artisan route:list
php artisan optimize:clear

# Banco de teste
docker exec erp-mysql mysql -uerp_user -perp_password -e "DROP DATABASE IF EXISTS erp_comercial_test; CREATE DATABASE erp_comercial_test;"
```

## Regras de Negócio Críticas

### Venda no PDV
1. Caixa deve estar aberto (`Caixa` com status=aberto na sessão)
2. Venda criada com itens → baixa automática no estoque (EstoqueMovimentacao tipo=saida)
3. MovimentacaoCaixa criada (tipo=venda)
4. ContaReceber criada automaticamente
5. Comissão calculada se vendedor tem `comissao_percentual`
6. NFC-e emitida se config fiscal ativa

### Orçamento → Pedido → Venda
- Orçamento convertido → status muda para `convertido`, cria Pedido com mesmos itens
- Pedido confirmado → cria ContaReceber
- Pedido faturado → baixa estoque

### Estoque Multi-Unidade
- Cada unidade tem estoque isolado
- Transferência: solicitada → aprovada → concluída (movimenta entre unidades)
- EstoqueMovimentacao mantém quantidade_anterior e quantidade_posterior (audit trail, sem soft delete)

### Financeiro
- ContaReceber: gerada automaticamente por vendas/pedidos, suporta parcelas
- ContaPagar: manual ou automática (compras), suporta recorrência
- Boleto vinculado a ContaReceber e/ou Contrato
- Conciliação: importa OFX → match automático por valor+data (±5 dias)
- DRE: calculado a partir de contas pagas agrupadas por plano de contas

### Notas Fiscais
- `ref` (referência Focus NFe) deve ser única por token — formato: `{tipo}-{id}-{timestamp}`
- NFC-e é síncrona, NF-e/NFS-e são assíncronas (usar Job para polling)
- Cancelamento requer justificativa de 15-255 caracteres
- XML armazenado na Focus NFe por 5 anos
- Chave de acesso é o identificador único da nota na SEFAZ

## Observações para o Próximo Agente

1. **Não alterar migrations existentes** — criar novas migrations para mudanças
2. **Sempre rodar testes** após qualquer alteração: `docker exec erp-app php artisan test`
3. **Respeitar o multi-tenant** — usar traits BelongsToEmpresa/BelongsToUnidade
4. **Perfil enum** — `$user->perfil` é `App\Enums\Perfil`, usar `->value` para string
5. **Session unidade_id** — controllers App dependem disso, sempre setar nos testes
6. **Focus NFe** — tokens são por empresa/unidade, nunca hardcoded
7. **Views são Blade + Bootstrap 5** — NÃO usar React, Vue ou SPA
8. **Porta MySQL** — 3308 no host (3306 estava ocupada), 3306 dentro do Docker
9. **O `version` no docker-compose.yml** é obsoleto mas funciona — ignorar o warning
