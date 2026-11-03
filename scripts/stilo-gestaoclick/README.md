# STILO VINTE ← Gestão Click (03/11/2026)

Coleta e importação do catálogo e do estoque por loja da STILO VINTE (empresa 3). Ver docs.md, seção de 03/11/2026.

| Passo | Script | Onde roda |
|---|---|---|
| 1. Coletar (só leitura) | `coletar-gc.sh` → `coletar-gc.mjs` | host; decifra a credencial da integração do Gersen dentro do `audit-app` e nunca a imprime. O Dennis roda (o classificador barra o Claude) |
| 2. Plano | `plano.py` | host; lê a coleta + `erp-stilo-antes.json` + `casamento.json`, gera `plano-importacao.json` |
| 3. Comparação p/ o cliente | `comparar.py` | host; planilha GC × ERP |
| 4. Importar | `importar.php` (`DRY=1` ensaia e desfaz) | `docker exec -e DRY=0 erp-com-app php artisan tinker --execute="require '/tmp/importar.php';"` com o plano em `/tmp/plano-importacao.json` |
| 5. Conferir | `validar.php` | idem, ✅/❌ produto a produto |
| Nomes sem "R$ valor" | `nomes.py` → `renomear.php` → `validar-nomes.php` | host / container |

⚠️ `importar.php` recusa rodar se a STILO já tiver movimentação de estoque (foi feito para a carga inicial). Numa
reimportação, casar pelo **código de barras** (o nome no ERP já não tem o preço) e lançar só a diferença de saldo.
Os caminhos `/home/ubuntu/stilo-gc-import/` e o scratchpad citados nos scripts são do dia da carga.
