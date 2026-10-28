# Rotina de validação do ERP Comercial — ferramental (28/10/2026)

Automatiza a rotina obrigatória do `CLAUDE.md` (impacto → antes/depois → regressão completa → deploy com portão → rodada
pós-deploy), no mesmo desenho do JL-ERP (`/root/jl-erp/tools`) e do CRM Vacinas (`scripts/validar-tudo.sh`). Saída de tudo
em **`/home/ubuntu/erp-validacao/<branch>/`** (`/` vira `_`): `impacto.md`, `antes/`, `depois/`, `pos-deploy/` e o
`deploys.log` geral. Nada encosta na produção: o app de validação roda em rede própria (`erp-val-net`), com mysql +
pgvector + redis descartáveis, sem `queue:work`/`schedule:work`, e com Focus, InfinitePay, Melhor Envio, OpenAI, Asaas,
Uber, ViaCEP, BrasilAPI e app.ia365 apontados para 127.0.0.1.

```
tools/impacto.sh [branch]                               # 1. o que a mudança alcança (arquivos, rotas, telas, perfis, crons, integrações, empresas)
tools/validar/validar.sh erp-com-app:latest antes       # 2. baseline na imagem NO AR (phpunit + lint + roteiros + smoke)
tools/build.sh [rotulo]                                 #    tag pre-<rotulo>-<data> da imagem do ar + build erp-com-app:<rotulo>-<data>
tools/validar/validar.sh erp-com-app:<rotulo>-<data> depois   # 3. tudo de novo na imagem nova → TOTAL ❌ NOVOS = 0
tools/deploy.sh <branch> --conferir                     # 4. o Claude confere o portão (não muda nada)
sudo tools/deploy.sh <branch>                           # 5. Dennis: rollback tag + :latest + compose up + login/log + pós-deploy
```

**Caminho rápido (28/10/2026):** o `impacto.sh` termina com "Etapas recomendadas": só ferramental/docs → `--so=lint,roteiros`
(minutos); só views/CSS/JS sem PHP de app/ e sem migration → `--so=lint,roteiros,smoke` com `ONLY=<telas tocadas>` (~2 min);
PHP de app/ ou migration → rodada completa (~25 min). O `antes/` é **por imagem no ar**: se ela não mudou, copie a pasta
`antes/` do rótulo anterior em vez de rodar de novo. Teste o roteiro da feature num ambiente próprio
(`tools/validar/ambiente.sh subir <img> dev 8111 <out> <img-no-ar> 8112`) antes da rodada oficial — errar ali custa minutos.

O roteiro **da feature** (`scripts/validar-<tema>-<data>.sh antes|depois`) continua obrigatório — ele prova o pedido; o
`validar.sh` prova que o resto não quebrou. Para ele entrar na regressão das próximas entregas, acrescente uma linha em
`tools/validar/roteiros.tsv` (aceitando `BASE`, `ANTES`, `DB_CT`, `APP_CT`, `OUT`, `XML`).

## `tools/impacto.sh [branch] [--prod <ref>]`

`git diff main...branch` → arquivos; rotas diretas (controller mudado) e indiretas (usa classe/view mudada), com os
**perfis** que têm acesso (middleware `permission:` × matriz do `CheckPermission`); linhas `Route::` novas; **telas Blade**
mudadas + quem as inclui (marca as que têm versão nova e antiga por `?visual=`); migrations; jobs/crons/commands/observers;
**webhooks e integrações** (Focus, InfinitePay, Melhor Envio/Uber, Gersen, Asaas, Agente IA, caixa/PDV); **empresas
atingidas** (SELECT só leitura em `erp-com-mysql`: fiscal ativo, gateway, agente IA, token de integração); alertas 🔴/🟡.

## `tools/validar/validar.sh <imagem> antes|depois|pos-deploy [--so=…] [--prod <ref>] [--rotulo <nome>] [--porta <n>]`

| etapa | o que faz | ❌ novo = |
|---|---|---|
| phpunit | `php artisan test` COMPLETO num container da imagem (vendor dev cacheado por `composer.lock` em `erp-validacao/.vendor/`), banco `erp_comercial_test` + pgvector `agente_busca_test` | teste que passa no `antes/` (imagem no ar) e falha nesta |
| lint | `php -l` em todo PHP de app/routes/config/database/tests + TODAS as blades compiladas (`view:cache` sozinho não linta) | erro que não existe na imagem no ar |
| roteiros | `tools/validar/roteiros.tsv`: cada `auto`/`auto-antes` roda no descartável (`auto-antes` sobe também a imagem no ar no mesmo banco, porta+1) | roteiro que passava na baseline e falha agora |
| smoke | `smoke-telas.mjs`: todas as rotas GET × 7 perfis (admin IA365, dono, gerente, vendedor, caixa, financeiro, consulta), `{param}` resolvido pelo banco descartável, versão nova e antiga quando há (`/`?visual=); print `.png` por tela | tela que abria (ok/negada/redir) e agora quebra (HTTP ≥ 400, exceção Laravel, vazia); ok ↔ negada/redir = mudança de acesso (gate só com `--aceitar-acesso`) |

`antes` = baseline (imagem no ar; tudo o que falha é pré-existente e fica listado); `depois` compara com o `antes/` do mesmo
rótulo; `pos-deploy` (chamado pelo deploy.sh) roda roteiros + smoke contra `erp-com-app:latest` × `depois`. O relatório
traz `ImagemID`, `Commit`, cada etapa com ✅/❌, a prova de que a produção ficou intocada (contagem SELECT antes × depois)
e **`TOTAL ❌ NOVOS`**, que é o que o portão lê. Variáveis: `DUMP=` (banco a partir de um dump em vez do seed demo),
`PERFIS=admin,dono`, `ONLY=/app/pdv`, `MANTER=1` (deixa o ambiente no ar). Tempo: ~5 min phpunit + ~1 min lint + roteiros
+ ~10 min smoke.

**Ambiente** (`tools/validar/ambiente.sh subir <imagem> <nome> <porta> <OUT> [imagem-antes porta-antes]`): a imagem traz o
`.env` e o `bootstrap/cache/config.php` de PRODUÇÃO — são apagados antes do boot e o app nasce só das variáveis do
descartável (APP_KEY gerada na hora, `DB_HOST=mysql`, tokens falsos). `supervisord-val.conf` sobe só php-fpm + nginx.
Banco: `migrate` (entrypoint) + `db:seed` demo + `seed-smoke.php` (financeiro/consulta). O script aborta se achar
`queue:work`/`schedule:work` rodando ou `.env` apontando para fora do descartável.

**Imagem no ar ≠ imagem em disco.** A produção do ERP recebeu código por `tar` por cima da imagem (23–27/10: 70 arquivos).
No `antes`, quando a imagem pedida é a do container e o `docker diff erp-com-app` mostra código, o script grava o que está NO AR
com `docker commit --pause=false erp-com-app erp-com-app:no-ar-<data>` e valida essa imagem (`--sem-commit` desliga). Depois
que os deploys passarem a ser por imagem (`deploy.sh`), o diff fica vazio e nada é comitado.

## `tools/build.sh [rotulo]`

Guarda a imagem no ar como `erp-com-app:pre-<rotulo>-<data>` (regra: nunca deixar a imagem do ar órfã) e faz
`docker build -f docker/prod/Dockerfile -t erp-com-app:<rotulo>-<data> .` — o MESMO Dockerfile e contexto do
`docker-compose.prod.yml` (o compose não é chamado aqui porque exige o `.env` de produção, que só o root lê, e porque
moveria a tag `:latest` antes da validação). `:latest` só é movida pelo `deploy.sh`.

## `tools/deploy.sh <branch> [--conferir] [--aceitar-acesso]`

**Canário e rollback automático (28/10/2026, depois do 502):** antes de mover `:latest`, o script sobe a imagem nova como
`erp-com-canario` do MESMO jeito que a produção sobe (mesma rede, `--env-file $PROD_DIR/.env`, mesmo entrypoint, `SKIP_MIGRATE=1`)
em `127.0.0.1:8098` e exige `/login` 200 (até 120 s); reprovou = produção intocada, log do canário na tela. Depois do `up`, se
`/login` não voltar 200 em 90 s, o próprio script volta a tag `pre-*` e sobe de novo (linha `FALHOU` no `deploys.log`).

Recusa (✋) se: não há `antes/` e `depois/relatorio.md`; faltou etapa; a imagem do relatório sumiu ou já está no ar; o código
da branch mudou depois da validação (só `*.md` pode); `TOTAL ❌ NOVOS ≠ 0`; mudança de acesso sem `--aceitar-acesso`.
Com tudo ok e sem `--conferir` (Dennis, root): tag `pre-<branch>-<data>` da imagem no ar → `docker tag <validada>
erp-com-app:latest` → `docker compose -f docker-compose.prod.yml up -d --no-build app` em `$PROD_DIR` (`/root/erp`; o
entrypoint roda `migrate --force`) → confere imagem no ar, `/login`, 60 s de log → `validar.sh erp-com-app:latest
pos-deploy` → linha em `erp-validacao/deploys.log`. O Claude roda **só `--conferir`**.

## Hook do Claude Code (`tools/hooks/`)

`bloquear-deploy-direto.sh` (PreToolUse › Bash) bloqueia, no contexto do ERP e fora de `tools/build.sh`/`tools/deploy.sh`:
`docker compose … up` da produção, `docker build`/`compose build`, `docker tag … erp-com-app:<tag>` (tags `pre-*` liberadas).
Não mexe em JL nem CRM. Instalar/atualizar (idempotente, preserva os hooks do JL e do CRM, backup do settings.json):
`tools/hooks/instalar.sh` — o Claude não consegue rodar isso sozinho (o modo auto barra alterar o próprio settings.json).
