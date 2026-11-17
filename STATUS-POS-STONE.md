# Status dos projetos — maquininha Stone (JL Expresso e IA365)

Atualizado em **17/11/2026** (bloco do ERP Comercial; os dos apps como em 16/11). Este arquivo é o mesmo nos três repositórios.

| Projeto | Repositório | Branch atual | Situação |
|---|---|---|---|
| JL Passagens (app) | `dennisiautomation/APP-STONE-JL` | `stone-homologacao` | Homologação enviada à Stone em 14/11; aguardando resposta |
| IA365 Vendas (app) | `dennisiautomation/APP-IA365-STONE` | `stone-homologacao` | Homologação enviada à Stone em 16/11; aguardando resposta |
| ERP Comercial (backend) | `dennisiautomation/erpcomercial` | `main` | API da maquininha EM PRODUÇÃO desde 17/11/2026 (e na homologação) |

## JL Passagens — app de venda de passagens

- **Versão:** 1.2.2 (versionCode 5), pacote `br.com.jlexpresso.passagens.pos`, integração Deeplink.
- **Homologação Stone:** enviada em 14/11/2026 pelo Partner Hub (parceiro iAutomation Consultoria). Prazo de resposta: até 5 dias úteis.
- **Testes do roteiro (10/11, Positivo D400):** crédito negado R$ 0,51 e R$ 0,55, crédito aprovado R$ 5,00, débito aprovado R$ 80,00 e os dois cancelamentos. Parcelado não se aplica. PIX não executado.
- **Vídeo:** https://youtu.be/bfNX3Z_PLR4 (não listado).
- **Servidores:** o mesmo APK usa `app.jlexpresso.com` (produção) e cai para `passagens.jlexpresso.com` (teste) enquanto a produção não tiver o módulo. Código `JL-TESTE` força o teste.
- **Hoje:** a produção ainda não tem o módulo de passagens, então o app usa o servidor de teste.
- **Documentação:** `README.md`, `docs.md` (seção 17) e `docs/stone/homologacao/`.

Falta:
1. Resposta da Stone. Se pedirem correção: versionCode 6 e reenvio em Homologação › Versionamento.
2. Publicar o módulo de passagens em `app.jlexpresso.com` (branch `feat/passagens` do backend `jlexpessoencomendas`).
3. Conferir se a chave da API de produção é a mesma do teste.
4. Horário de atendimento e suporte técnico no Fale Conosco (vazios no backend).
5. Manter o servidor de teste e o usuário de teste no ar enquanto a Stone avalia.
6. A `main` deste repositório é antiga; o código atual está em `stone-homologacao`.

## IA365 Vendas — app do ERP na maquininha

- **Versão:** 1.0.0 (versionCode 1), pacote `br.com.ia365.vendas.pos`, integração Deeplink.
- **Funções:** venda (PDV), pedido, caixa, clientes, e o pedido de balcão/cafeteria (nome ou mesa, cobrado depois).
- **Servidores (16/11):** o release (APK da Stone) tenta `erp.ia365.com.br` e cai para `homolog-erp.ia365.com.br` se a produção responder 404 ou 422 no login, igual ao JL; faixa "SERVIDOR DE TESTE" na homologação. Maquininha de teste (`positivo`) → só homologação; emulador (`debug`) → cópia local. A API foi publicada em produção em 17/11 junto com a unificada (ordem do Dennis), antes da aprovação; o avaliador da Stone continua entrando pela homologação porque o usuário de teste só existe lá.
- **Homologação Stone:** enviada em 16/11/2026 pelo Partner Hub (nova aplicação, 1.0.0 build 1, APK assinado com `release.jks`). Status: "Aguardando aprovação do time de integrações". Prazo: até 5 dias úteis.
- **Roteiro (16/11, Gertec GPOS730):** 002 e 003 negados como esperado; 004 crédito R$ 5,00 e 006 crédito 2x R$ 100,00 aprovados; 008 débito R$ 80,00 aprovado; 005, 007 e 009 cancelamentos aprovados. PIX (010) não feito: desabilitado no terminal de sandbox (não obrigatório).
- **Vídeo:** https://youtube.com/shorts/jvsAmtstm0M (não listado).
- **Teste na homologação do ERP:** usuário `stone.teste@cafe.demo` (gerente, Café Demo / loja Café Paulista; senha no `local.properties`), produtos TESTE STONE (ids 177–182).
- **Suporte no app:** (11) 98802-7022 (telefone e WhatsApp), suporte@ia365.com.br.
- **Documentação:** `README.md`, `docs/REQUISITOS.md` e `docs/stone/homologacao/` (evidências, `RESULTADOS-2026-11-16.md`, roteiro e guia em PDF, scripts, `ROTEIRO-VIDEO.md`).

Falta:
1. Resposta da Stone. Se pedirem correção: versionCode 2 e reenvio em Homologação › Versionamento.
2. Backup de `release.jks` e `keystore.properties` (sem eles não dá para atualizar o app na Stone).
3. ~~Publicar a API em produção~~ — feito em 17/11; o mesmo APK já usa a produção para quem tem login lá.
4. Atualizar a Positivo D400, que ainda está com o APK antigo (servidor local).

## ERP Comercial — backend da maquininha

- **API:** `api/integracao/pos/v1` — **EM PRODUÇÃO desde 17/11/2026 10:50 BRT** (unificada, `main`); continua também na homologação.
- **Produção:** `https://erp.ia365.com.br/api/integracao/pos/v1/auth/login` responde (422 sem dados). Qualquer usuário vendedor, caixa ou gerente de empresa ativa entra; não há chave por empresa.
- **Homologação:** `https://homolog-erp.ia365.com.br`, com o usuário de teste da Stone (`stone.teste@cafe.demo`) e os produtos TESTE STONE.
- **Formas de pagamento:** `GET /me` devolve `regras.formas_pagamento` (só as ligadas na loja, com o nome que a loja deu) e `POST vendas/cotar` recusa a forma desligada (422) antes de cobrar na Stone. Roteiro `scripts/validar-pos-stone-28out.sh`: 38 ✅ / 0 ❌.
- **Documentação:** `docs.md`, seção "App de vendas na maquininha Stone" (blocos 14/11, 16/11 e 17/11) e "Unificada em produção (17/11/2026)".

**Qual APK fala com qual servidor (o que pegou o Dennis em 17/11):**

| APK | Onde está | Servidor |
|---|---|---|
| release (assinado com `release.jks`) — o enviado à Stone em 16/11 | Mac do Dennis (`dist/`) e Partner Hub | tenta `erp.ia365.com.br`; cai para a homologação só se a produção responder 404 ou 422 no login |
| `positivo` (chave debug) | instalado na Gertec GPOS730 | **só** `homolog-erp.ia365.com.br` |
| `debug` | emulador | cópia local |

Em 17/11 ~11:41 o Dennis tentou entrar na GPOS730 com o login de um cliente real e recebeu "e-mail ou senha não conferem": as 3 tentativas chegaram só na homologação (log do `erp-caf-app`), nenhuma na produção — era o build `positivo`. **Com o APK aprovado pela Stone (o release) os clientes reais entram na produção sem reenvio.** Para testar cliente real na GPOS730, instalar o APK release.

Falta:
1. Resposta da Stone sobre o IA365 Vendas (enviado 16/11).
2. App: ler `regras.formas_pagamento` do `/me` para esconder o botão da forma desligada e mostrar o nome da loja (hoje a forma desligada aparece e é recusada ao cotar, ninguém é cobrado).
3. Decidir se a API da maquininha deve ter liberação por empresa (hoje é aberta a toda empresa ativa).

## Maquininhas de teste (Stone Code de teste 206192723)

| Terminal | Serial | Situação |
|---|---|---|
| Positivo D400 | 4AK459T1G | Ativada. JL Passagens 1.2.2; IA365 Vendas antigo. No Partner Hub o serial está digitado `4ak129t1g`, sem impacto |
| Gertec GPOS730 | 6801132650000263 | Ativada em 14/11 após recadastro do modelo (estava como GPOS700X). JL Passagens 1.2.2 e IA365 Vendas 1.0.0 (build positivo de 16/11). PIX desabilitado no sandbox |
| Sunmi P2 | PB09218474721 | Vinculado no Partner Hub; não usado nesta rodada |

Dicas que custaram tempo:
- A Stone cancela o pagamento após ~20 s sem cartão; o cancelamento às vezes pede a senha administrativa do terminal.
- Ativação no sandbox: usar Wi-Fi com dados móveis desligados; pelo chip o servidor recusa a conexão.
- "Unprocessable entity" na ativação: cadastro do terminal errado na Stone. Recadastrar pelo formulário "Adicionar novo meio de captura ao STONECODE de teste".
- `adb` não lista o terminal com o Chrome aberto: desmarcar "Discover USB devices" em `chrome://inspect/#devices` e reconectar.
- A GPOS730 aceita o APK assinado com a chave debug da Positivo.

## Fora dos repositórios (backup à parte)

- `release.jks` e `keystore.properties` do JL Passagens — sem a chave não dá para atualizar o app na Stone.
- `local.properties` dos dois apps (URLs, chave da API, Stone Code, usuários de teste).
- Chave debug da Positivo (`positivo/`), APKs gerados (`dist/`) e o vídeo de demonstração.
