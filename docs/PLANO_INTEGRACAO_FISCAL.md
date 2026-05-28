# Plano de Conclusão da Integração Fiscal (Focus NFe)

> **Goal**: quando o admin cria uma empresa-cliente no ERP, a empresa já nasce **100% configurada na Focus NFe** (CNPJ provisionado, tokens emitidos, webhooks ativos, certificado upload-ready), e qualquer NF-e/NFC-e/NFS-e emitida sai sem rejeição por campo faltando, com auto-cálculo correto de ICMS/ST/FCP/IBS/CBS/IS/IPI.
>
> Status atual: **3 dos 8 blocos da API Focus integrados** (NF-e, NFC-e, NFS-e parcial). Auto-onboarding parcial — só pelo wizard, não pelo CRUD admin. Webhooks **nunca criados automaticamente**. Reforma Tributária **órfã** no payload de NF-e/NFC-e. CT-e e MDF-e **não implementados**.

---

## Mapa Focus NFe vs. Estado atual no ERP

| Bloco Focus | Endpoints | Service local | Cobertura |
|---|---|---|---|
| Empresas/Revenda | POST/PUT/GET/DELETE `/v2/empresas` | `FocusEmpresaService` | ✅ código existe; ❌ não chamado no CRUD admin; ❌ master token ausente no .env |
| Webhooks | POST/GET/DELETE `/v2/hooks` | `FocusEmpresaService::cadastrarWebhook` | ❌ método existe mas **nunca chamado**; ❌ payload usa `eventos[]` (errado — Focus aceita `event` singular) |
| Certificado A1 | POST `/v2/empresas/{cnpj}/certificado` (multipart) | `CertificadoDigitalService` | ✅ |
| NF-e | POST/GET/DELETE `/v2/nfe` + CC-e + inutilização + email | `NFeService` | ⚠ 12 campos críticos faltando no payload |
| NFC-e | POST/GET/DELETE `/v2/nfce` + inutilização + email | `NFCeService` | ⚠ `valor_troco`, `valor_total_tributos`, `local_destino` faltando |
| NFS-e | POST/GET/DELETE `/v2/nfse` + email | `NFSeService` + `NFSeDispatcher` | ⚠ payload flat (Focus oficial usa aninhado prestador/tomador/servico) |
| NFS-e Recebidas | GET `/v2/nfses_recebidas` | `NFSesRecebidasService` | ✅ |
| NF-e Recebidas + MDe | GET + POST `/v2/nfes_recebidas/CHAVE/manifesto` | `ManifestacaoService` | ✅ |
| CT-e | POST/GET/DELETE `/v2/cte` + CC-e + inutilização | — | ❌ **não implementado** |
| MDF-e | POST/GET/DELETE `/v2/mdfe` + condutor + DFe + encerrar | — | ❌ **não implementado** |
| CT-e Recebidos + MDe | GET + POST `/v2/ctes_recebidas/CHAVE/desacordo` | — | ❌ não implementado |
| Backups | GET `/v2/backups/CNPJ.json` | `BackupXmlService` | ✅ código existe; ❌ não há job agendado |
| Emails bloqueados | GET/DELETE `/v2/blocked_emails/EMAIL` | `EmailsBloqueadosService` | ✅ |
| APIs auxiliares (NCM/CFOP/CNAE/CEP/CNPJ/Municípios) | GET `/v2/{ncms,cfops,codigos_cnae,ceps,cnpjs,municipios}` | `FocusReferenciasService` | ✅ |

---

## Bugs e gaps identificados (priorizados)

### 🔴 Críticos — bloqueiam emissão real
1. **`FOCUS_MASTER_TOKEN` ausente no `.env`** → auto-criação de empresa cai sempre no modo legado (cola token manual). Token correto: `rriUTW5kJHPHmoNBmqyIxjDnae5raCn3`.
2. **EmpresaController e UnidadeController não chamam `FocusEmpresaService::criar()`** → só o wizard de onboarding cria empresa na Focus. Admin que cria empresa pelo CRUD comum precisa configurar tudo manualmente depois.
3. **Webhooks nunca cadastrados** → `cadastrarWebhook()` existe mas nenhum lugar do código o chama. Resultado: status de NF-e/NFC-e/NFS-e/recebidas não atualizam por push, só por polling.
4. **`cadastrarWebhook` envia chave `eventos: []`** (array) → API Focus espera `event: "nfe"` (singular, um hook por evento). Mesmo se chamado, falharia silenciosamente.
5. **Campos obrigatórios da NF-e faltando no payload** (`NFeService::buildPayload`):
   - `cnpj_responsavel_tecnico`, `contato_responsavel_tecnico`, `email_responsavel_tecnico`, `telefone_responsavel_tecnico` (obrigatório desde 2018 — NT 2018/003)
   - `local_destino` (1=interna, 2=interestadual, 3=exterior)
   - `indicador_intermediario` (0=não, 1=marketplace)
   - Totais: `icms_base_calculo`, `icms_valor_total`, `valor_total_tributos` (LC 165/2018 — obrigatório), `fcp_valor_total`, `icms_base_calculo_st`, `icms_valor_total_st`, `valor_ipi`, `valor_pis`, `valor_cofins`, `valor_frete`, `valor_seguro`, `valor_outras_despesas`
   - Fatura/duplicatas (`numero_fatura`, `valor_original_fatura`, `valor_liquido_fatura`, `duplicatas[]`) — exigido se houver pagamento a prazo (boleto/crediário)
6. **NCM fallback `'00000000'`** → SEFAZ rejeita. Deve falhar local com mensagem clara.
7. **CST PIS/COFINS hard-coded `'99'`** e **IPI hard-coded `'50'`** → quebra produtos com isenção, monofásico, imunidade.
8. **`icms_modalidade_base_calculo = '3'`** hard-coded → quebra ST, MVA, pauta.
9. **`ReformaTributariaCalculator` órfão em NF-e/NFC-e** → IBS/CBS/IS coletados no produto, mas nunca enviados. Em 2026 (alíquota-teste) e 2027 (IS real) vira problema fiscal.
10. **`ICMSCalculator` (ST interestadual) só vive como calculadora de tela** → em vendas interestaduais com ST, payload sai sem base ST, ICMS-ST, MVA, FCP.
11. **Bloco DI (importação) não vai no payload** → produtos com `origem ∈ {1,2,3,6,7,8}` exigem DI; SEFAZ rejeita.

### 🟠 Importantes — correção de cálculo / UX
12. **NFC-e sem `valor_troco`** (obrigatório quando dinheiro > total) e sem `valor_total_tributos`.
13. **NFS-e payload flat** → Focus oficial usa estrutura aninhada `prestador / tomador / servico`. Pode estar passando por sorte; oficial é aninhada.
14. **NFS-e sem `codigo_municipio` no prestador** (IBGE 7 dígitos) → obrigatório.
15. **`cliente->ie` reaproveitado como Inscrição Municipal do tomador** em NFS-e (linha 405 do `NFSeService`) → IE ≠ IM.
16. **Snapshot fiscal não preservado** → payload lê `$produto->ncm` etc. direto. Edição do produto entre venda e re-emissão muda o histórico. Deve copiar para `venda_itens`.
17. **`pagamento_detalhes` lido sem schema** → array livre, código confia em chaves arbitrárias.
18. **Tooltip fiscal subutilizado** → existe `<x-erp.fiscal-tooltip>` mas não aplicado em CSC, ID CSC, item LC 116, regime especial NFS-e, IE/IM dos cadastros.
19. **Mudança de regime tributário** não avisa sobre impacto em produtos / config fiscal.

### 🟡 Funcionalidades faltantes
20. **CT-e** não implementado (transportadoras).
21. **MDF-e** não implementado (manifesto eletrônico — qualquer empresa que transporte carga própria precisa).
22. **CT-e recebidos / desacordo** não implementado.
23. **Backup XMLs**: `BackupXmlService` existe mas não há cron — risco fiscal (LGPD/Receita exige guarda de 5 anos).
24. **Painel de monitoramento Focus** (Status SEFAZ + rate-limit + saúde webhooks) não existe.

---

## Plano de execução — 5 fases

Cada fase é commit-mergeável independente. Total estimado: ~25 commits, 4-6 sprints (1 dev).

### Fase 1 — Fundação do auto-provisionamento (3-4 commits)
**Goal:** quando admin cria empresa → tudo configurado.

- **F1.1** Adicionar `FOCUS_MASTER_TOKEN` ao `.env` (e `.env.example` sem o valor real)
- **F1.2** **Fix bug `cadastrarWebhook`**: mudar payload de `'eventos' => [...]` para 1 chamada por evento (`'event' => 'nfe'`, `'event' => 'nfse'`, etc.). Persistir lista de hook IDs criados em `configuracoes_fiscais.focus_webhook_ids` (JSON).
- **F1.3** Hook no `EmpresaController::store` e `UnidadeController::store` chamando `FocusEmpresaService::criar()` + `cadastrarWebhook()` para cada evento aplicável (`nfe`, `nfse`, `nfce_contingencia`, `nfe_recebida`, `nfse_recebida`, `inutilizacao`). Idempotente (se empresa já existe na Focus, sincroniza).
- **F1.4** Job `ProvisionarEmpresaFocusJob` em background — não bloqueia o submit do form. Retry exponencial. Notificação no sino quando concluído / com erro.
- **F1.5** Página `/admin/empresas/{id}/saude-focus` mostrando: empresa criada ✓/✗, certificado ✓/✗ + validade, webhooks ✓/✗ (lista de hook IDs), tokens homolog/prod ✓/✗, último sync.

### Fase 2 — Payload correto (5-6 commits)
**Goal:** zero rejeição da SEFAZ por campo faltando.

- **F2.1** Adicionar Responsável Técnico em `ConfiguracaoFiscal` (`responsavel_tecnico_cnpj`, `_nome`, `_email`, `_telefone`) + UI + envio em `NFeService::buildPayload`.
- **F2.2** Migration para campos no `Produto`: `cst_pis`, `cst_cofins`, `cst_ipi`, `icms_modalidade_bc`, `icms_modalidade_bc_st`, `mva_st`, `pis_quantidade`, `cofins_quantidade` (substituem hard-codes).
- **F2.3** Refatorar `NFeService::buildPayload` para incluir:
  - Bloco fiscal responsável técnico
  - `local_destino` (calcular automaticamente: emit_uf == dest_uf ? 1 : 2)
  - `indicador_intermediario` (default 0; configurável quando venda vinda de marketplace)
  - Totais: `icms_base_calculo`, `icms_valor_total`, `fcp_valor_total`, `icms_base_calculo_st`, `icms_valor_total_st`, `valor_ipi`, `valor_pis`, `valor_cofins`, `valor_outras_despesas`, `valor_frete`, `valor_seguro`
  - `valor_total_tributos` calculado via IBPT (cache local; usar `produto.percentual_tributos` se houver)
  - Fatura + duplicatas quando `forma_pagamento` envolve boleto/crediário/parcelamento
- **F2.4** `NFCeService::buildPayload`: adicionar `local_destino`, `valor_troco` (calculado de `pagamento_detalhes`), `valor_total_tributos`.
- **F2.5** `NFSeService::buildPayload`: migrar para estrutura aninhada `prestador/tomador/servico`. Adicionar `codigo_municipio` IBGE. Corrigir IM do tomador (usar `cliente->im`, não `cliente->ie` — adicionar coluna `im` em `clientes` via migration).
- **F2.6** Bloco DI no item NF-e quando `produto->ehImportado()`.
- **F2.7** Validação pre-flight: rejeitar emissão com mensagem clara se NCM vazio, CFOP vazio, ou destinatário sem endereço completo (NF-e).

### Fase 3 — Inteligência fiscal (4-5 commits)
**Goal:** o ERP calcula o que o usuário não sabe.

- **F3.1** Integrar `ICMSCalculator::calcular()` no `buildPayload` de NF-e e NFC-e: quando UF origem ≠ UF destino e produto tem CST/CSOSN de ST (10/30/60/70/90/201/202/203/500), injetar `icms_base_calculo_st`, `icms_valor_total_st`, `fcp_valor_total_st`, `mva` no item.
- **F3.2** Integrar `ReformaTributariaCalculator::blocoPayload()` em NFCeService e NFeService item-por-item, gated por `configuracao_fiscal.ibs_ativo/cbs_ativo/is_ativo`.
- **F3.3** Snapshot fiscal: copiar `ncm, cfop, cst_csosn, icms_aliquota, pis_aliquota, cofins_aliquota, ipi_aliquota, origem, cest` do `Produto` para `venda_itens` no momento da venda. Emissão lê do snapshot. Migration + worker do PDV + worker da venda balcão.
- **F3.4** `FiscalAutoConfig` expandido: presets CST PIS/COFINS por regime; preset IPI por NCM (tabela TIPI cache).
- **F3.5** Detector de mudança de regime: ao mudar `empresa.regime_tributario`, abrir modal "X produtos têm CST não compatível com novo regime — revisar?"

### Fase 4 — Robustez operacional (3-4 commits)
**Goal:** ninguém perde XML, ninguém é pego de surpresa.

- **F4.1** Cron diário (3h) chamando `BackupXmlService` → salvar mensal em `storage/app/fiscal/backups/{cnpj}/{YYYY-MM}.zip`. Retenção 5 anos. Comando artisan `fiscal:backup-xmls`.
- **F4.2** Job de saúde de webhooks: testar cada `webhook_id` semanalmente via `POST /v2/{event}/{ref}/hook`. Notificar se algum falhou.
- **F4.3** Dashboard fiscal `/app/fiscal/dashboard`: NF-es por status (autorizada/rejeitada/cancelada/pendente), top 5 erros, status SEFAZ por UF, validade certificado, webhooks ativos, último backup, rate-limit Focus consumido.
- **F4.4** Cron de saúde do certificado: 30/15/7/1 dias antes de vencer → notificação no sino + email para `dono`.

### Fase 5 — CT-e e MDF-e (5-7 commits — opcional, fora do escopo "comércio")
**Goal:** cobertura 100% Focus para quem transporta carga.

Só faz sentido se algum cliente do SaaS for transportadora. Caso contrário, deixar marcado como "Pro/Enterprise feature, on demand".

- **F5.1** `CTeService` (envio normal/substituto/complemento/anulação, consulta, cancelamento, CC-e, inutilização)
- **F5.2** `MDFeService` (envio, consulta, cancelamento, inclusão de condutor, inclusão de DF-e, encerramento)
- **F5.3** UI CT-e (per-unidade, gated por plano)
- **F5.4** UI MDF-e
- **F5.5** CT-e recebidos + desacordo

---

## Critérios de "feito" (DoD)

Cada fase só é mergeada se:
1. Testes unitários + feature dos payloads validados contra exemplos da collection Postman.
2. Smoke test em **homologação** (token homolog da empresa demo) emitindo NF-e + NFC-e + NFS-e sem rejeição.
3. CLAUDE.md atualizado (gaps movidos para "implementado").
4. Sem regressão nos 162 testes existentes.

## Métricas de saída do projeto

- ✅ 100% das empresas criadas no admin nascem com tokens Focus, webhooks ativos e config fiscal pronta.
- ✅ < 1% de rejeição SEFAZ por campo faltando (medido via dashboard fiscal).
- ✅ Backup XML diário rodando há 30 dias sem falha.
- ✅ CC-e, NFS-e, manifestação destinatário, Reforma Tributária todos cobertos.
- ✅ Tempo médio de "cliente novo → primeira NF-e autorizada" < 15 min.

---

## Ordem sugerida para o primeiro PR

Por blast-radius e impacto, recomendo executar nesta ordem:

1. **F1.1** (env) + **F1.2** (fix bug `eventos→event`) + **F2.1** (responsável técnico) — 1 commit, desbloqueia tudo
2. **F1.3** + **F1.4** + **F1.5** — auto-provisionamento completo
3. **F2.3** + **F2.4** + **F2.5** + **F2.6** + **F2.7** — payloads corretos
4. **F3.x** — inteligência fiscal
5. **F4.x** — robustez
6. **F5.x** se houver demanda

---

_Documento gerado a partir da análise cruzada entre a Postman Collection oficial da Focus NFe (`Focus%20NFe.postman_collection.json`) e a base de código `/root/erp-comercial` em 2026-05-28._
