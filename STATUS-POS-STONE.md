# Status dos projetos — maquininha Stone (JL Expresso e IA365)

Atualizado em **14/11/2026**. Este arquivo é o mesmo nos três repositórios.

| Projeto | Repositório | Branch atual | Situação |
|---|---|---|---|
| JL Passagens (app) | `dennisiautomation/APP-STONE-JL` | `stone-homologacao` | Homologação enviada à Stone em 14/11; aguardando resposta |
| IA365 Vendas (app) | `dennisiautomation/APP-IA365-STONE` | `main` | Funcionando na homologação do ERP; ainda não enviado à Stone |
| ERP Comercial (backend) | `dennisiautomation/erpcomercial` | `feature/pos-stone-app` | API da maquininha no ar na homologação; não está em produção |

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
- **Servidores por build:** maquininha de teste (`positivo`) → `homolog-erp.ia365.com.br`; release → `erp.ia365.com.br`; emulador (`debug`) → cópia local.
- **Validação:** instalado na Gertec GPOS730; login e uso validados pelo Dennis com usuários reais da homologação em 14/11.
- **Documentação:** `README.md` e `docs/REQUISITOS.md`.

Falta:
1. Atualizar a Positivo D400, que ainda está com o APK antigo (servidor local).
2. Criar a keystore de release e guardar em local seguro.
3. Processo da Stone: roteiro de testes, guia de ativação, vídeo e envio. O caminho completo está em `APP-STONE-JL`, `docs.md` §17.
4. Depende de a API estar em produção para o build de release funcionar.

## ERP Comercial — backend da maquininha

- **API:** `api/integracao/pos/v1`, na branch `feature/pos-stone-app`.
- **Homologação:** no ar em `https://homolog-erp.ia365.com.br` desde 14/11/2026.
- **Produção:** `https://erp.ia365.com.br` ainda responde 404 nessas rotas.
- **Documentação:** `docs.md`, bloco "Atualização 14/11/2026" dentro de "App POS Stone".

Falta:
1. Merge da `main` de 14/11 (formas de pagamento configuráveis por empresa) na branch. O POS ainda usa as quatro formas fixas; esse encaixe precisa ser conferido.
2. Rodar PHPUnit e o roteiro de validação (não rodados em 14/11).
3. Deploy em produção com as migrations `pos_*`.

## Maquininhas de teste (Stone Code de teste 206192723)

| Terminal | Serial | Situação |
|---|---|---|
| Positivo D400 | 4AK459T1G | Ativada. JL Passagens 1.2.2; IA365 Vendas antigo. No Partner Hub o serial está digitado `4ak129t1g`, sem impacto |
| Gertec GPOS730 | 6801132650000263 | Ativada em 14/11 após recadastro do modelo (estava como GPOS700X). JL Passagens 1.2.2 e IA365 Vendas 1.0.0 |
| Sunmi P2 | PB09218474721 | Vinculado no Partner Hub; não usado nesta rodada |

Dicas que custaram tempo:
- Ativação no sandbox: usar Wi-Fi com dados móveis desligados; pelo chip o servidor recusa a conexão.
- "Unprocessable entity" na ativação: cadastro do terminal errado na Stone. Recadastrar pelo formulário "Adicionar novo meio de captura ao STONECODE de teste".
- `adb` não lista o terminal com o Chrome aberto: desmarcar "Discover USB devices" em `chrome://inspect/#devices` e reconectar.
- A GPOS730 aceita o APK assinado com a chave debug da Positivo.

## Fora dos repositórios (backup à parte)

- `release.jks` e `keystore.properties` do JL Passagens — sem a chave não dá para atualizar o app na Stone.
- `local.properties` dos dois apps (URLs, chave da API, Stone Code, usuários de teste).
- Chave debug da Positivo (`positivo/`), APKs gerados (`dist/`) e o vídeo de demonstração.
