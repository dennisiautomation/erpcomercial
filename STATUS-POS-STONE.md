# Status dos projetos — maquininha Stone (JL Expresso e IA365)

Atualizado em **20/11/2026**. Este arquivo é o mesmo nos três repositórios.

| Projeto | Repositório | Branch atual | Situação |
|---|---|---|---|
| JL Passagens (app) | `dennisiautomation/APP-STONE-JL` | `stone-homologacao` | 1.2.3 aprovada para piloto em 19/11, **mas não abre em máquina real**; **1.2.4 enviada à Stone em 20/11** (aguardando) |
| IA365 Vendas (app) | `dennisiautomation/APP-IA365-STONE` | `stone-homologacao` | **1.0.1 aprovada para piloto em 19/11, funcionando no piloto** |
| ERP Comercial (backend) | `dennisiautomation/erpcomercial` | `feature/pos-stone-app` | API da maquininha no ar na homologação **e em produção** |

## Como a Stone publica uma versão (Central de Ajuda, conferido em 20/11)

1. **Versionamento** (Partner Hub › Integração › Homologações › Nova Homologação › SDK Android › escolher o app): toda versão
   nova passa pela Stone, mesmo com o app só em piloto. No versionamento a análise é só do time de QA: assinar o APK com os
   fabricantes, testar download e instalação nos POS e distribuir. Não há outro caminho: o POS só instala APK assinado pela Stone.
2. **Aprovado para piloto:** o piloto já é o ambiente de **produção da Stone**, com alcance restrito aos Stone Codes que a gente
   inclui ("1 Cliente +", pede CNPJ). A versão nova precisa ser incluída de novo no piloto depois de aprovada.
3. **Rollout:** obrigatório depois do piloto; libera para todos os clientes do app. Não fazer às sextas (suporte só em dias
   úteis, 9h–18h). Depois do rollout, novos clientes entram por Integração › Ativação de Produto.
4. Depois de incluir no piloto ou fazer rollout: ~10 min para o download ficar disponível; o POS procura atualização em até
   3 h, ou na hora se reiniciar com Wi-Fi.

## JL Passagens — app de venda de passagens

- **Versão em análise:** 1.2.4 (versionCode 7), pacote `br.com.jlexpresso.passagens.pos`, Deeplink. Enviada em 20/11 por
  Versionamento; roteiro e Teste 001 refeitos; APK, roteiro e guia baixados de volta do armazenamento da Stone e conferidos.
- **Defeito da 1.2.3 (20/11):** na Sunmi P2-B do piloto o app abria em tela branca e fechava. O APK release (R8 em modo
  completo) quebrava no início ("Missing type parameter", Gson) e a venda também falharia ("Class cannot be cast to
  ParameterizedType", Retrofit). O roteiro de 10/11 foi feito com o build `positivo`, sem R8; o APK release nunca tinha sido
  aberto num aparelho. Correção na 1.2.4: `android.enableR8.fullMode=false` + regras de keep. Testado no emulador com o APK
  exato enviado: abre, login, trechos, viagens e mapa de poltronas (pagamento só na maquininha).
- **Regra nova:** antes de qualquer envio à Stone, abrir o APK release exato num emulador ou aparelho.
- **Piloto:** CNPJ 29.284.757/0001-36, Stone Code 633980167 (Sunmi P2-B). Quando a 1.2.4 for aprovada, incluir de novo no
  piloto, reiniciar a P2-B e testar com `JL-TESTE`.
- **Servidores:** o mesmo APK usa `app.jlexpresso.com` (produção, ainda 404 no módulo) e cai para `passagens.jlexpresso.com`
  (teste). Decisão do Dennis: o JL usa o teste por enquanto.
- **Vídeo:** https://youtu.be/bfNX3Z_PLR4 (não listado).
- **Documentação:** `docs.md` (seção 17), `docs/stone/homologacao/evidencias/RESULTADOS-2026-11-10.md`.

Falta:
1. Aprovação da 1.2.4 pela Stone e teste na P2-B (abrir, venda pequena, cancelamento).
2. Rollout (não às sextas; freezes da Stone 21/11–04/12 e 21/12–03/01).
3. Publicar o módulo de passagens em `app.jlexpresso.com` quando o Dennis decidir.
4. A `main` deste repositório é antiga; o código atual está em `stone-homologacao`.

## IA365 Vendas — app do ERP na maquininha

- **Versão no ar:** 1.0.1 (versionCode 2), aprovada para piloto em 19/11, pacote `br.com.ia365.vendas.pos`, Deeplink.
- **Piloto:** Stone Code 633980167 (Sunmi P2-B). Vendas reais nº 30 e 31 em produção (empresa 1 ia365, loja Dennis Canteli).
- **Recibo espaçado na P2-B (20/11):** a P2-B põe espaço grande em volta de cada item de impressão. Corrigido **no servidor**
  (ERP `PosCupomService::compactar`, em produção desde 20/11): o recibo vai em ~6 blocos, letra média, TOTAL numa linha,
  quebra por palavra. Vale para a 1.0.1, sem reenvio. A 1.0.2 do app (mesma correção no app) **não foi enviada**.
- **Servidores:** tenta `erp.ia365.com.br`; cai para `homolog-erp.ia365.com.br` só com 404/422 no login.
- **NFC-e:** a loja Dennis Canteli não tem Configurações da Loja salvas (cupom automático no cartão desligado) e o fiscal da
  empresa 1 está em homologação; por isso a venda sai com RECIBO. Decisão do Dennis.
- **Vídeo:** https://youtube.com/shorts/jvsAmtstm0M (não listado).
- **Documentação:** `README.md`, `docs/REQUISITOS.md`, `docs/stone/homologacao/` (`RESULTADOS-2026-11-16.md`).

Falta:
1. Rodar o piloto por alguns dias (vendas, cancelamento, fechamento de caixa) e depois o rollout.
2. Backup de `release.jks` e `keystore.properties`.
3. Se quiser o recibo idêntico ao da Stone: imprimir como imagem (exige versão nova do app).

## ERP Comercial — backend da maquininha

- **API:** `api/integracao/pos/v1`, branch `feature/pos-stone-app`; no ar em homologação e em produção.
- **20/11:** correção do recibo aplicada em produção por `docker cp` com ordem do Dennis (rollback em
  `/home/ubuntu/erp-backups/pos-cupom-20nov/`); ver `docs.md`, "Atualização 20/11/2026".
- **Atenção no próximo deploy:** o código em produção não bate com nenhuma branch (290 arquivos copiados por cima da imagem e
  `CheckPermission.php` alterado sem commit em `/home/ubuntu/apps/erp-producao-17nov`). Build pelo git sem conferir isso
  desfaz alterações que estão no ar.

## Maquininhas

| Terminal | Serial | Situação |
|---|---|---|
| Positivo D400 (teste) | 4AK459T1G | IA365 Vendas 1.0.2 de teste (build positivo) logado na homologação; imprime colado |
| Gertec GPOS730 (teste) | 6801132650000263 | JL e IA365 em builds de teste; imprime colado; PIX desabilitado no sandbox |
| Sunmi P2-B (comercial, piloto) | PB09218474721 | Stone Code 633980167. IA365 1.0.1 ok; JL 1.2.3 não abre (aguarda 1.2.4). Sem USB/adb |

Dicas que custaram tempo:
- A Stone cancela o pagamento após ~20 s sem cartão.
- A barra verde do Partner Hub não garante o upload: baixar cada arquivo do armazenamento e comparar antes de Enviar.
- Com bateria baixa (D400 a 1–2%) a impressora não imprime, mas o app da Stone responde "SUCCESS".
- `adb` não lista o terminal com o Chrome aberto: desmarcar "Discover USB devices" em `chrome://inspect/#devices`.

## Fora dos repositórios (backup à parte)

- `release.jks` e `keystore.properties` dos dois apps — sem a chave não dá para atualizar o app na Stone.
- `local.properties` dos dois apps (URLs, chaves, Stone Code, usuários de teste).
- Chave debug da Positivo (`positivo/`), APKs gerados (`dist/`) e os vídeos.
