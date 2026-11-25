# Base de conhecimento — Vendas, PDV e Caixa

## Como abrir o PDV (frente de caixa)?
Palavras-chave: pdv, abrir pdv, frente de caixa, tela de venda, vender, caixa registradora, ponto de venda, abrir venda

O PDV é a tela de vendas do balcão. Para abrir:

1. No menu lateral, clique em **PDV** (fica destacado, logo abaixo do grupo Vendas).
2. Se o seu caixa ainda não estiver aberto, aparece a mensagem **"Caixa Fechado — E necessario abrir o caixa para iniciar as vendas."** Clique em **Abrir Caixa**, informe o troco inicial e confirme.
3. Pronto: o PDV abre com o campo de busca de produto ativo.

No topo do PDV aparecem a **Unidade** (loja), o **Operador** (quem está logado) e o **Caixa #** (número do seu caixa). Se a loja tiver mais de um estoque e a opção estiver ligada, aparece também o campo **"Sai de"**.

Quem pode usar o PDV: **Dono, Gerente, Vendedor e Caixa**. Os perfis **Financeiro** e **Consulta** não vendem.

Se a empresa ligou a opção "Vendedor opera somente o PDV", o vendedor cai direto no PDV ao entrar no sistema.

## Quais são os atalhos do teclado no PDV (F1 a F12)?
Palavras-chave: atalhos, teclas, f1, f2, f3, f4, f5, f6, f7, f8, f9, f10, f12, esc, teclado pdv, tecla de atalho

As teclas de atalho do PDV são:

- **F1** — Buscar produto (coloca o cursor no campo de busca)
- **F2** — Cliente (escolher o cliente da venda)
- **F3** — Vendedor (escolher quem fez a venda)
- **F4** — Desconto na venda
- **F5** — Atalhos (mostra/esconde os botões de produtos; só funciona se a loja ligou os "Botões de atalho no PDV". Sem isso, o F5 recarrega a página normalmente)
- **F6** — Troca / devolução
- **F7** — Sangria (tirar dinheiro do caixa)
- **F8** — Suprimento (colocar dinheiro no caixa)
- **F9** — Cancelar item (tira da venda o item selecionado)
- **F10** — Fechar caixa
- **F12** — Finalizar venda
- **ESC** — Limpar / fechar a janela aberta

A lista também aparece no rodapé da tela do PDV. Não existe função na tecla F11.

## Como buscar ou bipar um produto no PDV?
Palavras-chave: buscar produto, bipar, leitor, codigo de barras, código de barras, scanner, pesquisar produto, produto nao encontrado, produto não aparece

1. Aperte **F1** (ou clique no campo **"Buscar produto por código de barras, código interno ou descrição..."**).
2. Passe o leitor no código de barras, ou digite o código interno, ou digite parte do nome.
3. Na busca pelo nome aparece uma lista: clique no produto (ou use as setas e Enter).

Ao bipar o mesmo produto de novo, a quantidade daquela linha aumenta.

**Mensagens comuns:**
- **"Produto nao encontrado: ..."** — o código lido não está cadastrado nesta empresa. Confira o cadastro do produto (código de barras e código interno) em Cadastros → Produtos.
- **"Erro ao buscar produto"** — falha de conexão; tente de novo. Se aparecer a janela **"Sua sessão expirou"**, entre de novo no sistema (veja o tópico sobre sessão expirada).

O campo de busca fica bloqueado enquanto o caixa estiver fechado.

## Como mudar a quantidade ou tirar um item da venda no PDV?
Palavras-chave: quantidade, alterar quantidade, tirar item, remover item, cancelar item, f9, apagar produto da venda, item errado

- **Mudar a quantidade:** na lista de itens, cada linha tem o campo **QTD**. Clique nele e digite a nova quantidade. Bipar o mesmo produto de novo também soma 1.
- **Tirar um item:** clique na linha do item para selecioná-lo e aperte **F9** (ou o botão **Cancelar F9**). O item sai da venda.
- **Limpar tudo:** **ESC** fecha janelas abertas; para desistir da venda inteira, remova os itens.

Enquanto a venda não for finalizada (F12), nada é gravado: estoque e caixa só mudam ao finalizar.

## Como colocar o cliente na venda e o CPF na nota?
Palavras-chave: cliente na venda, f2, cpf na nota, cnpj na nota, cpf no cupom, identificar cliente, consumidor, cliente nao aparece

**Cliente cadastrado:**
1. Aperte **F2** (ou clique em **"Sem cliente (F2)"**).
2. Na janela **Selecionar Cliente**, busque por nome ou CPF/CNPJ e clique no cliente.

**Só o CPF na nota, sem cadastrar:** digite no campo **"CPF/CNPJ na nota (opcional)"** do painel da direita. O documento vai no cupom fiscal e no recibo, sem criar cadastro. O campo é limpo a cada venda.

Quando a loja liga a opção "Cliente pediu CPF na nota → emite cupom fiscal na hora" (Configurações da Loja), informar o cliente ou o CPF faz o cupom fiscal (NFC-e) sair automaticamente.

O cliente é **obrigatório** quando a venda é na forma de venda a prazo direto com a loja (o botão com o nome que a loja configurou). Se o cliente for marcado como **Atacado** no cadastro, os preços mudam para o preço de atacado ao escolhê-lo.

## Como escolher o vendedor da venda no PDV (F3)?
Palavras-chave: vendedor, f3, trocar vendedor no pdv, comissao vendedor, quem vendeu, vendedor errado, lista de vendedores

1. Aperte **F3** (ou clique no seletor **"Vendedor: ..."** no painel da direita).
2. Escolha o nome. O padrão é **quem está logado**.

A comissão da venda vai para o vendedor escolhido aqui. Se esquecer o F3, a venda fica no nome de quem está logado — o Dono ou Gerente pode corrigir depois (veja "trocar o vendedor de uma venda finalizada").

**A lista mostra gente de outra loja?** Por padrão o F3 lista vendedores, caixas, gerentes e o dono da empresa inteira. Em **Gestão → Configurações da Loja → Acesso do vendedor**, ligue **"No PDV, mostrar só os vendedores desta loja"**. Aí aparecem só os funcionários vinculados à loja (o vínculo é feito em Cadastros → Funcionários). Quem não tem loja vinculada aparece em todas.

## Como dar desconto na venda (F4)?
Palavras-chave: desconto, f4, desconto na venda, abatimento, desconto percentual, desconto em reais, nao deixa dar desconto, limite de desconto

1. Com os itens lançados, aperte **F4** (ou o botão **Desconto F4**).
2. Em **Tipo de Desconto**, escolha **Valor (R$)** ou **Percentual (%)**.
3. Digite o valor e clique **Aplicar**.

**Mensagens que podem aparecer:**
- **"Adicione itens antes de aplicar desconto"** — a venda está vazia.
- **"Esta loja nao permite desconto nas vendas"** — o limite de desconto da loja está em 0.
- **"Desconto acima do maximo permitido pela loja (X%)"** — passou do limite.
- **"Percentual nao pode exceder 100%"** / **"Desconto nao pode exceder o subtotal"**.

O limite é definido por Dono ou Gerente em **Gestão → Configurações da Loja → Preços por Forma de Pagamento → "Desconto máximo permitido na venda (%)"**. Em branco = sem limite; 0 = ninguém dá desconto. Vale para o PDV e para a venda manual.

Não confunda com o **"Desconto no Dinheiro/PIX (%)"**, que é automático quando o preço cadastrado é o do cartão.

## Como finalizar a venda e escolher a forma de pagamento (F12)?
Palavras-chave: finalizar venda, f12, forma de pagamento, receber, pagar, dinheiro, pix, cartao, debito, credito, fechar venda

1. Lance os itens.
2. Clique no botão da forma de pagamento no painel da direita (Dinheiro, PIX, Cartão etc. — os nomes e a ordem são os que a empresa configurou) ou aperte **F12 / FINALIZAR VENDA**.
3. Na janela **Pagamento**, confira o **Valor Total da Venda**, digite o **Valor Recebido** e, no crédito, escolha as **Parcelas**.
4. Clique **Confirmar**.
5. Se a loja não decidiu o documento automaticamente, aparece **"Qual documento imprimir?"** — escolha **Cupom Fiscal** ou **Recibo** (Enter confirma o destacado).
6. Aparece **"Venda Finalizada!"** com o número da venda e o troco. Clique **Imprimir Cupom** se precisar e **Nova Venda** para continuar.

**Mensagens comuns:**
- **"Adicione itens a venda"** — venda vazia.
- **"Selecione uma forma de pagamento"**.
- **"Esta forma de pagamento está desligada em Configurações → Formas de pagamento."** — o Dono/Gerente desligou essa forma.
- **"Nenhum caixa aberto. Abra o caixa antes de registrar vendas."** — abra o caixa.

## Como dividir o pagamento em duas ou mais formas (split)?
Palavras-chave: pagamento dividido, split, duas formas, metade pix metade cartao, pagar com dois cartoes, pagamento misto, faltam

1. Ao abrir a janela **Pagamento**, marque **"Pagamento dividido (split)"**.
2. Digite o valor da primeira forma e confirme. Aparece **"Pagamento adicionado. Restam R$ X"**.
3. Clique na próxima forma: ela já abre com o valor que falta.
4. Repita até completar. A venda só finaliza quando o total for pago.

**Mensagens:**
- **"Valor excede o restante da venda"** — numa forma que não é dinheiro, você digitou mais do que falta.
- **"Pagamento ja completo. Finalize a venda."** — já está pago, só finalizar.
- **"Faltam: R$ X"** — ainda há valor em aberto.

**O preço mudou ao dividir?** Depende da regra da loja para pagamento dividido (Configurações da Loja → "Pagamento dividido (split): qual tabela vale?"). Na regra recomendada, se uma das partes for no crédito, a venda inteira vai para a tabela do crédito. Veja o tópico "Qual preço vale no pagamento dividido".

## Como funciona o troco e o aviso "Dinheiro a menos"?
Palavras-chave: troco, dinheiro a menos, recebi menos, valor menor, faltam, completar com outra forma, corrigir valor, troco errado

**Troco:** pagando em dinheiro, digite em **Valor Recebido** quanto o cliente entregou. Se for mais que o total, o sistema mostra o **TROCO** e grava a venda pelo valor certo.

**Dinheiro a menos:** se você digitar em dinheiro um valor **menor** que o total, aparece a janela **"Dinheiro a menos"** com "Recebido de ... Faltam R$ X" e dois botões:
- **Corrigir valor** — volta para você digitar de novo (por exemplo, se errou a digitação).
- **Completar com outra forma** — o dinheiro fica lançado e aparece **"Dinheiro R$ X lançado. Faltam R$ Y — escolha a outra forma."** Clique na outra forma (PIX, cartão...), que já abre com o valor que falta.

Isso existe para a venda nunca ser gravada com o total cheio e o dinheiro recebido a menos — o que deixaria o caixa sem explicação.

## Por que aparece "Pagamento insuficiente" ao finalizar?
Palavras-chave: pagamento insuficiente, faltam, nao finaliza, não finaliza, venda nao fecha, erro ao finalizar, recebido menos

A venda só é gravada quando o valor pago cobre o total (tolerância de 1 centavo). As mensagens possíveis são:

- Na tela: **"Pagamento insuficiente. Faltam R$ X"**.
- Vindo do servidor: **"Pagamento insuficiente: recebido R$ X de R$ Y. Faltam R$ Z — corrija o valor ou complete com outra forma."**

O que fazer:
1. Confira o valor digitado na forma de pagamento.
2. Se o cliente vai pagar o resto de outro jeito, marque **"Pagamento dividido (split)"** e lance a segunda forma.
3. Se havia crédito de troca, aparece "O crédito cobre R$ X. Escolha a forma para os R$ Y restantes." — escolha a forma para o restante.

Isso não é defeito: impede que a venda fique gravada com valor maior do que entrou no caixa.

## Como parcelar no cartão de crédito no PDV e por que o valor mudou?
Palavras-chave: parcelar, parcelas, parcelado, credito parcelado, juros, acrescimo cartao, valor mudou no cartao, 3x, 6x, 10x

1. Clique na forma de crédito.
2. Na janela **Pagamento**, escolha as **Parcelas** (até o máximo que a loja configurou).
3. Confirme.

**O total aumentou?** Pode ser por dois motivos, ambos configurados pela loja:
- **Acréscimo no crédito**: a loja cobra um percentual a mais no cartão. O PDV mostra o aviso "Tabela: Crédito" ao lado do total, ou uma linha **"Acréscimo cartão"** no resumo.
- **Juros do parcelamento**: a loja preencheu a tabela de juros por número de parcelas. Se a lista mostrar "6x de R$ 180,00 · total R$ 1.080,00", o total já inclui os juros.

Dinheiro, PIX e débito nunca levam juros de parcelamento. Para mudar o máximo de parcelas, os acréscimos ou a tabela de juros, veja o tópico "Como configurar juros de parcelamento".

## Como configurar juros de parcelamento e máximo de parcelas?
Palavras-chave: juros, tabela de juros, juros parcelamento, maximo de parcelas, máximo de parcelas, taxa maquininha, repassar taxa, parcelas sem juros

Quem pode: **Dono e Gerente**.

1. Menu **Gestão → Configurações da Loja**.
2. Na seção **Preços por Forma de Pagamento**, ajuste **"Máximo de parcelas"** (até quantas vezes a loja parcela; aparece no PDV e na etiqueta).
3. Em **"Juros do parcelamento no cartão de crédito"**, preencha o percentual de cada linha (2x, 3x, ...). Em branco ou 0 = parcela sem juros.
4. Use o campo **"Simular com uma venda de R$"** para ver quanto o cliente paga.
5. Salve.

Exemplo da própria tela: venda de R$ 1.000,00 com 8 na linha do 6x → cliente paga R$ 1.080,00 (6x de R$ 180,00).

Observações:
- Vale **só para crédito parcelado**. O crédito à vista (1x) usa o "Acréscimo no Crédito".
- Com juros cadastrados, a opção **"Mostrar o valor de cada parcela na lista do PDV"** fica ligada e travada, para o caixa falar o valor certo ao cliente.
- Se a loja já cobra "Acréscimo no Crédito", o juros da parcela soma em cima dele — a tela mostra um aviso com a conta.
- A configuração vale **só para a loja (unidade) em que você está**.

## Como funcionam os preços por forma de pagamento (à vista, débito e crédito)?
Palavras-chave: tabela de preco, tabela de preço, preco cartao, preço no cartão, preco a vista, preço pix, acrescimo debito, acrescimo credito, preco diferente cartao

Cada loja pode ter um preço para Dinheiro/PIX, outro para Débito e outro para Crédito. Quem configura: **Dono e Gerente**, em **Gestão → Configurações da Loja → Preços por Forma de Pagamento**.

Primeiro escolha o que o **preço de venda cadastrado no produto** significa:
- **à vista (Dinheiro/PIX)** — padrão. O cartão cobra os campos **"Acréscimo no Débito (%)"** e **"Acréscimo no Crédito (%)"**. Exemplo: produto de R$ 300,00 com 8% no crédito → crédito R$ 324,00.
- **do cartão — Dinheiro/PIX ganha desconto** — débito e crédito cobram o preço cadastrado e o **"Desconto no Dinheiro/PIX (%)"** reduz o à vista. Os acréscimos deixam de valer.

**Preço especial num produto:** no cadastro do produto existe o campo **"Preço no Cartão (Crédito e Débito)"**, que tem prioridade sobre a regra geral.

No PDV os preços dos itens mudam na hora em que a forma é escolhida, com o aviso "Tabela: Crédito/Débito". Se a janela de pagamento for fechada sem confirmar, os preços voltam.

## Qual preço vale quando o pagamento é dividido (split)?
Palavras-chave: split preço, pagamento dividido preco, pix mais cartao, tabela split, regra do split, cobrou preco do cartao no pix

Em **Gestão → Configurações da Loja**, bloco **"Pagamento dividido (split): qual tabela vale?"**, o Dono ou Gerente escolhe uma das regras:

1. **Vale a maior tabela entre as formas usadas** (recomendado) — PIX + Crédito cobra a tabela Crédito; PIX + Débito cobra Débito; só PIX + Dinheiro cobra a tabela PIX.
2. **Sempre a tabela menor (Dinheiro/PIX)** — mesmo com cartão no meio, cobra o à vista. A taxa da máquina sai do bolso da loja.
3. **Sempre a tabela maior (Crédito)** — qualquer pagamento dividido cobra o preço do crédito.
4. **Cada forma paga a sua própria tabela** — o acréscimo do cartão incide só sobre a parte paga no cartão. Os preços dos itens não mudam; aparece uma linha à parte "Acréscimo cartão". Nesta regra, o preço próprio de cartão cadastrado no produto não é usado — vale o percentual geral.

**"Paguei metade no PIX e cobrou preço de cartão"** — não é defeito: é a regra 1 ou 3 da loja.

## Como funciona o preço de atacado?
Palavras-chave: atacado, preco atacado, preço de atacado, cliente atacadista, varejo, revenda, tabela atacado

O atacado depende do **cliente**, não da forma de pagamento.

1. No cadastro do produto (Cadastros → Produtos), preencha **"Preço Atacado (opcional)"**.
2. No cadastro do cliente (Cadastros → Clientes), em **Tipo de preço**, escolha **Atacado**.
3. No PDV, ao escolher esse cliente (F2), os itens passam para o preço de atacado em qualquer forma de pagamento, com o aviso "Tabela: Atacado". Ao tirar o cliente, voltam ao preço normal.

**Produto sem preço de atacado** cadastrado: o cliente de atacado paga o preço à vista normal. O PDV avisa quais itens estão sem preço de atacado.

Promoções não valem para cliente de atacado: ele continua com o preço de atacado.

## Recibo ou cupom fiscal: como o PDV decide o que imprimir?
Palavras-chave: recibo, cupom fiscal, nfc-e, nfce, cupom nao fiscal, nota no pdv, auto recibo cupom, qual documento imprimir, emitir cupom

Acima do botão FINALIZAR existem três botões: **Auto / Recibo / Cupom Fiscal**.
- **Recibo** — força o comprovante simples, não fiscal.
- **Cupom Fiscal** — força a NFC-e (cupom fiscal na SEFAZ).
- **Auto** — segue o que a loja configurou.

No modo Auto valem as regras de **Gestão → Configurações da Loja → Recibo ou Cupom Fiscal na Finalização da Venda** (Dono/Gerente):
- **"Venda no cartão emite cupom fiscal automaticamente"**.
- **"Cliente pediu CPF na nota → emite cupom fiscal na hora"**.
- Nas demais vendas, o padrão: **Recibo** ou **Cupom Fiscal (NFC-e)**.

Quando nenhuma regra decide, aparece a janela **"Qual documento imprimir?"** com o padrão da loja destacado — Enter confirma. Para não perguntar, escolha Recibo ou Cupom antes de finalizar.

**Pré-requisito:** o cupom fiscal só sai se a Configuração Fiscal da loja estiver ativa com NFC-e habilitada (certificado A1 e CSC). Sem isso, sai sempre o recibo. No topo do PDV aparece **"NFC-e Ativa"** ou **"Não Fiscal"**.

## Por que saiu recibo em vez do cupom fiscal (NFC-e)?
Palavras-chave: nfce nao saiu, cupom fiscal nao emitido, saiu recibo, nfc-e rejeitada, sefaz fora, erro nfce, cupom não saiu

Se a emissão da NFC-e falha, **a venda nunca trava**: ela é gravada e sai o recibo. Na janela "Venda Finalizada!" aparece o aviso amarelo **"Cupom fiscal (NFC-e) não emitido — saiu recibo."** seguido do motivo.

Causas comuns:
- **Cadastro do produto** com dado fiscal errado (por exemplo, NCM inválido).
- **Configuração Fiscal** da loja incompleta (certificado A1 vencido ou ausente, CSC não informado, NFC-e não habilitada).
- **SEFAZ fora do ar** ou instável.
- No topo do PDV aparece **"Não Fiscal"**: a loja não está emitindo NFC-e.

O que fazer:
1. Leia o motivo no aviso.
2. Corrija o que foi apontado (produto ou Configuração Fiscal, esta última só o Dono).
3. Para emitir o cupom dessa venda depois: **Vendas → Vendas**, abra a venda e, no quadro **Nota Fiscal**, use **"Emitir NFC-e"** (ou **"Emitir NFC-e novamente"**).

Venda para cliente de outro estado sai só como NF-e: a tela avisa "venda para outra UF sai só como NF-e (NFC-e não vale fora do estado)".

## Como reimprimir o cupom ou o recibo de uma venda?
Palavras-chave: reimprimir, segunda via, 2 via, imprimir de novo, impressora falhou, cupom perdido, recibo de novo

**Pelo PDV (qualquer perfil que vende):**
1. No topo do PDV, clique em **Reimprimir**.
2. Escolha o **Dia** (hoje vem marcado). Aparece a lista das vendas desta loja naquele dia.
3. Clique em **Recibo** ou, se a venda tem NFC-e autorizada, em **Cupom fiscal**.

**Logo após a venda:** na janela "Venda Finalizada!", o botão **Imprimir Cupom**.

**Pela tela da venda:** **Vendas → Vendas**, abra a venda e use **Recibo** ou **Imprimir**. No quadro Nota Fiscal, o botão **Cupom** imprime o cupom fiscal na térmica.

A reimpressão no PDV mostra só as vendas da loja em que você está.

## Como escolher de qual estoque a venda sai (campo "Sai de")?
Palavras-chave: sai de, estoque da venda, deposito, depósito, escolher estoque, varios estoques, mais de um estoque, sem estoque neste estoque

Quando a loja tem mais de um estoque (por exemplo, Salão e Depósito), o Dono ou Gerente pode ligar em **Gestão → Configurações da Loja → Estoque na venda (PDV)** a opção **"Deixar o caixa escolher no PDV de qual estoque a venda sai"**.

Com isso ligado:
1. O topo do PDV mostra o campo **"Sai de"**, já no estoque de venda.
2. Troque quando a peça estiver em outro estoque. Ao bipar, o PDV mostra o saldo de cada estoque.
3. Se não houver saldo no escolhido mas houver em outro, aparece: **"Sem estoque em X. Tem em Y — troque o "Sai de" no topo."**

Cancelar a venda devolve a peça para o estoque de onde ela saiu.

Se a loja tem um estoque só, o campo não aparece. Os estoques são cadastrados no botão **Estoques da Loja** (dentro de Configurações da Loja).

Em orçamentos e pedidos, o campo **"Sai de *"** aparece quando a loja tem mais de um estoque e é obrigatório: a baixa é feita dele quando o pedido for faturado.

## O PDV avisa "Sem estoque" — dá para vender mesmo assim? E vender de outra loja?
Palavras-chave: sem estoque, estoque zerado, estoque negativo, vender sem estoque, vender de outra loja, outra unidade, transferencia automatica

Ao bipar um produto sem saldo nesta loja, o PDV avisa mas **não bloqueia** a venda:
- **"Sem estoque para: (produto)"** — não há saldo em nenhuma loja.
- **"Sem estoque local. Disponível em outras: ..."** — há saldo em outra loja da empresa.

O item é lançado mesmo assim e o estoque pode ficar negativo. Isso não é defeito: serve para não travar o balcão quando o cadastro de estoque está desatualizado. Depois, acerte o saldo em Estoque.

**Vender da outra loja:** se a empresa estiver com a opção de vender estoque de outras unidades ligada (feita pela equipe IA365), aparece a janela **"Vender de outra loja?"** — "(produto) está zerado nesta loja. Selecione a unidade de origem — uma transferência automática será criada." Escolha a loja; a peça sai do estoque dela e a transferência fica registrada.

## Como vender produto com tamanho e cor (grade) no PDV?
Palavras-chave: grade, tamanho, cor, variacao, variação, tamanhos e cores, P M G, numeracao, escolher tamanho

Os tamanhos e cores são criados no cadastro do produto, no quadro **"Tamanhos e cores"**. Cada tamanho/cor funciona como um produto próprio, com saldo e código de barras próprios.

**Bipando o código de barras:** vai direto na peça certa (tamanho e cor).

**Buscando pelo nome:**
- Padrão: cada tamanho e cor aparece como um item separado na lista.
- Se o Dono ou Gerente ligou **"Mostrar o produto com tamanhos e cores como uma linha só"** (Configurações da Loja → Tamanhos e cores (grade)), o produto aparece uma vez; ao escolher, abre uma janela para tocar no tamanho e na cor, já mostrando o saldo de cada um nesta loja.

Essa opção vale por loja. Loja sem produto com grade não percebe diferença.

## Como lançar adicionais e usar os botões de atalho (cafeteria)?
Palavras-chave: adicional, adicionais, opcoes, opções, cafeteria, lanchonete, ficha tecnica, atalhos pdv, f5, botoes de produto, sabor, extra

Recursos para cafeteria e alimentação (ficam desligados até a empresa ligar):

**Adicionais / opções:** com **"Usar tipo do produto, ficha técnica e adicionais"** ligado (Configurações da Loja → Cafeteria e alimentação), um produto que tenha grupos de opções abre a janela **Opções** ao ser lançado. Toque nas opções desejadas e clique **Lançar** (ou Enter). Os adicionais somam ao preço do item.
- **"Escolha (grupo)."** — esse grupo é obrigatório.
- **"No máximo N em (grupo)."** — passou do limite de escolhas.

Vender um item produzido (ex.: cappuccino) baixa do estoque os insumos da receita.

**Botões de atalho:** com **"Botões de atalho no PDV desta loja"** ligado, o PDV mostra botões grandes por categoria. A tecla **F5** mostra e esconde. Aparecem os produtos marcados "Mostrar nos atalhos do PDV"; sem nenhum marcado, os 24 mais vendidos da loja nos últimos 30 dias ("Atalhos · mais vendidos (30 dias)").

## O PDV mostrou "Sua sessão expirou" — perdi a venda?
Palavras-chave: sessao expirou, sessão expirou, deslogou, desconectou, caiu o sistema, entrar de novo, produto sumiu da busca

A janela **"Sua sessão expirou"** aparece quando o sistema desconectou por segurança depois de muito tempo parado. A mensagem diz: "Nada do que está na tela foi gravado — entre de novo e refaça esta venda."

O que fazer:
1. Clique **Entrar de novo** e faça login.
2. Volte ao PDV. Se o seu caixa estava aberto, clique em **Abrir Caixa**: o sistema reconecta o mesmo caixa (não abre outro).
3. Refaça a venda que estava na tela.

Vendas já finalizadas antes da queda estão gravadas — confira em **Reimprimir** no topo do PDV.

Se a busca de produto parar de achar tudo de repente, verifique se não é a sessão que caiu.

## Como abrir o caixa?
Palavras-chave: abrir caixa, troco inicial, valor de abertura, iniciar caixa, comecar o dia, começar vendas, fundo de troco

1. Entre no **PDV** (menu lateral) — se o caixa estiver fechado, clique **Abrir Caixa**. Também dá pelo menu **Caixas → Abrir Caixa**.
2. Em **"Valor de Abertura (Troco Inicial)"**, informe o dinheiro que está na gaveta (pode ser 0).
3. Clique **Abrir Caixa**. Aparece "Caixa aberto com sucesso!" e o PDV abre.

A tela mostra também **"Já abertos nesta loja"**: os caixas abertos agora, com o número e o nome do operador.

Quem pode: **Dono, Gerente, Vendedor e Caixa**.

O caixa é **seu e desta loja**: cada pessoa tem o próprio caixa. Se você já tem um caixa aberto nesta loja, o sistema não abre outro — reconecta o que existe e mostra "Voce ja possui um caixa aberto.".

## Como é escolhido o número do caixa?
Palavras-chave: numero do caixa, número do caixa, caixa 1, caixa 2, numero em uso, ja esta em uso, trocar numero caixa

O número do caixa é escolhido **automaticamente** pelo sistema na hora de abrir — não é digitado.

- Ele usa o **menor número livre** da loja.
- Se você costuma usar sempre o mesmo número nesta loja e ele estiver livre, o sistema mantém o seu número.
- Um número só fica livre quando o caixa que o usa é **fechado**.

Na tela de abrir caixa, o quadro **"Já abertos nesta loja"** mostra quem está com cada número e desde quando: "Esses números estão em uso e não podem ser reutilizados até serem fechados."

Se os números estão "presos" por caixas esquecidos abertos, o Dono ou Gerente pode fechá-los em **Caixas** (veja "fechar o caixa de outro operador").

## O PDV diz "Caixa Fechado", mas a loja está vendendo. Por que o dono não vê o caixa do vendedor?
Palavras-chave: caixa fechado, dono nao ve caixa, não vejo o caixa, caixa sumiu, reconectar caixa, caixa do vendedor, outro computador, login novo

Não é defeito: **o caixa é de cada pessoa e de cada loja**. O caixa aberto pelo vendedor pertence a ele. Quando o Dono (ou qualquer outro usuário) entra no PDV, vê **"Caixa Fechado"** porque **ele** não tem caixa aberto — mesmo com a loja vendendo pelo caixa do vendedor.

Consequências:
- **Todo login novo passa pelo "Abrir Caixa".** Se você já tinha um caixa aberto nesta loja, clicar em Abrir Caixa **reconecta** o mesmo caixa ("Voce ja possui um caixa aberto."), não cria outro.
- Para **acompanhar** o caixa de outra pessoa, o Dono, Gerente ou Financeiro usa o menu **Caixas** e clica em **Extrato**.
- Para **vender** no lugar de outra pessoa, abra o seu próprio caixa (terá outro número).

Se a loja quer que a venda fique no nome do vendedor mesmo com um único caixa registrando, veja a opção "Vendedor selecionado é o responsável pela venda e pela entrada no caixa".

## Como fazer uma sangria (retirar dinheiro do caixa)?
Palavras-chave: sangria, f7, retirar dinheiro, tirar dinheiro do caixa, recolher dinheiro, saida de dinheiro, levar para o cofre

1. No PDV, aperte **F7** (ou o botão **Sangria F7**).
2. Informe o **Valor**.
3. Preencha **Descrição / Motivo** (obrigatório), por exemplo "Recolhido para o cofre".
4. Clique **Registrar Sangria**. Aparece "Sangria registrada com sucesso!".

Mensagens: **"Informe o valor da sangria"**, **"Informe a descricao"**, **"Caixa nao esta aberto."** (abra o caixa antes).

A sangria diminui o **dinheiro esperado na gaveta** no fechamento. Aparece no extrato do caixa e no comprovante de fechamento.

## Como fazer um suprimento (colocar dinheiro no caixa)?
Palavras-chave: suprimento, f8, colocar dinheiro, reforco de troco, reforço de troco, entrada de dinheiro, troco extra

1. No PDV, aperte **F8** (ou o botão **Suprim. F8**).
2. Informe o **Valor**.
3. Preencha **Descrição / Motivo** (obrigatório), por exemplo "Reforço de troco".
4. Clique **Registrar Suprimento**. Aparece "Suprimento registrado com sucesso!".

Mensagens: **"Informe o valor do suprimento"**, **"Informe a descricao"**, **"Caixa nao esta aberto."**.

O suprimento aumenta o **dinheiro esperado na gaveta** no fechamento. Não confunda com o troco inicial, que é informado ao abrir o caixa.

## Paguei uma compra ou despesa com dinheiro da gaveta. Como registro no caixa?
Palavras-chave: compra com dinheiro do caixa, pagar fornecedor com caixa, despesa do caixa, compra de suprimentos, comprar material, pagar entregador, vale funcionario

No PDV não existe uma tela própria de "compra". O dinheiro que sai da gaveta deve ser registrado como **Sangria**, para o fechamento bater:

1. No PDV, aperte **F7** (Sangria).
2. Informe o **Valor** pago.
3. Em **Descrição / Motivo**, escreva o que foi pago (ex.: "Compra de sacolas — nota 123").
4. Clique **Registrar Sangria**.

Assim o dinheiro esperado na gaveta já desconta esse valor e o extrato do caixa mostra o motivo.

Se a despesa também precisa aparecer no financeiro da empresa (relatórios, DRE), quem tem acesso ao Financeiro (Dono ou Financeiro) lança a conta em Contas a Pagar.

## Como fechar o caixa (F10)?
Palavras-chave: fechar caixa, f10, fechamento, conferencia, conferência, encerrar caixa, fim do dia, contar gaveta

1. No PDV, aperte **F10** (ou vá em **Caixas → Extrato → Fechar Caixa**).
2. A tela **Fechamento de Caixa** mostra: Abertura, Vendas (total), Suprimentos, Sangrias, Devoluções (trocas), Vendas canceladas (já abatidas) e as **Vendas por forma de pagamento**.
3. Confira o **"Valor Esperado em Dinheiro (gaveta)"** = abertura + vendas em dinheiro + suprimentos − sangrias (− devoluções em dinheiro).
4. Conte a gaveta e digite em **"Valor Contado em Dinheiro (gaveta)"** (obrigatório). O quadro **Diferença** mostra se confere, sobra ou falta.
5. Opcional: **"Conferência das demais formas"** (PIX, débito, crédito...), **Comprovantes** (fechamento da máquina, comprovante de crédito e de débito — JPG, PNG ou PDF) e **Observações**.
6. Clique para fechar e confirme **"Confirmar fechamento do caixa?"**.

O sistema mostra "Caixa fechado com sucesso!" e abre o **comprovante de fechamento**, que já manda imprimir.

PIX e cartão não entram na gaveta: a conferência deles é só informativa.

## Como imprimir o comprovante de fechamento do caixa (2ª via)?
Palavras-chave: comprovante de fechamento, relatorio do caixa, imprimir fechamento, segunda via caixa, papel do caixa, resumo do caixa

Logo após fechar, o comprovante abre sozinho e manda imprimir (formato bobina 80 mm).

Para imprimir de novo a qualquer momento:
1. Menu **Caixas**.
2. Localize o caixa (use os filtros **Status** e datas, se precisar).
3. Clique no ícone de impressora **"Comprovante de fechamento"**, ou abra o **Extrato** e clique em **Comprovante**.

Quem vê: o operador vê o comprovante dos **próprios** caixas; **Dono, Gerente e Financeiro** veem de todos. Outros perfis tentando abrir o de outra pessoa recebem "Você só pode ver os próprios caixas.".

## Como fechar o caixa que outro operador esqueceu aberto?
Palavras-chave: caixa esquecido, caixa aberto de outro, fechar caixa de outro usuario, funcionario foi embora sem fechar, caixa aberto ha dias

Quem pode: **Dono e Gerente**. Vendedor e Caixa fecham só o próprio caixa.

1. Menu **Caixas**.
2. Filtre **Status: Aberto** para ver os caixas abertos.
3. Na linha do caixa, clique em **Fechar caixa** (ou abra o **Extrato** e clique **Fechar Caixa**).
4. A tela avisa: "Você está fechando o caixa de (nome), aberto em (data). Isso não mexe no seu caixa."
5. Conte a gaveta, informe o valor contado e confirme.

Fechar o caixa de outra pessoa não desconecta o seu PDV. Depois de fechado, o número do caixa fica livre para ser usado de novo.

Se tentar fechar sem permissão: **"Você só pode fechar os próprios caixas."**. Se o caixa já estava fechado: **"Este caixa já está fechado."**.

## Onde vejo o histórico e o extrato dos caixas?
Palavras-chave: historico de caixas, histórico de caixa, extrato do caixa, caixas anteriores, movimentacao do caixa, conferir caixa, diferença caixa

1. Menu **Caixas** (tela "Histórico de Caixas").
2. Filtre por **Status** (Aberto/Fechado) e período.
3. A lista mostra: Caixa, Operador, Abertura, Fechamento, Valor Abertura, Esperado (dinheiro), Contado e **Diferença** ("Confere", "+ R$" sobra ou "- R$" falta).
4. Clique em **Extrato** para ver o detalhe: vendas por forma de pagamento, valores recebidos de venda a prazo, suprimentos, sangrias, devoluções em dinheiro, vendas canceladas, descontos concedidos, acréscimos de cartão/juros, conferência por forma, comprovantes anexados e todas as movimentações.

**Quem vê o quê:** Dono, Gerente e Financeiro veem os caixas de todos. Vendedor e Caixa veem **só os próprios**. Isso não é defeito.

## O que é a opção "Vendedor selecionado é o responsável pela venda e pela entrada no caixa"?
Palavras-chave: vendedor responsavel caixa, um caixa varios vendedores, entrada no nome do vendedor, operador do caixa, quem vendeu no caixa

Fica em **Gestão → Configurações da Loja → Caixa e Vendas** (Dono e Gerente alteram).

- **Desligado (padrão):** a entrada no caixa fica registrada no nome do **operador logado** (quem está no computador do caixa).
- **Ligado:** a entrada fica registrada no nome do **vendedor selecionado no PDV (F3)**. O extrato do caixa e a conferência mostram quem de fato fez cada venda.

Use quando vários vendedores atendem e um único caixa registra. Assim cada venda "pertence" ao vendedor certo, não a quem digitou.

A opção vale só para a loja em que você está.

## Como fazer uma troca no PDV (F6)?
Palavras-chave: troca, trocar produto, f6, devolver e levar outro, troca de mercadoria, troca de tamanho, trocar peça, credito da troca

Quem pode: **Dono, Gerente, Vendedor e Caixa**.

1. No PDV, aperte **F6** (botão **Troca F6**).
2. Em **"Qual venda?"**, digite o número da venda, o código do cupom (V123 — dá para ler o código de barras do cupom) ou o nome do cliente. Vale venda de qualquer dia e de qualquer loja da empresa.
3. Marque a quantidade de cada item que volta. Desmarque "volta ao estoque" se a peça está avariada. Se a loja tem mais de um estoque, escolha **"Em qual estoque a peça entra"**.
4. Escolha o **Motivo** (e um detalhe, se quiser).
5. Clique **"Trocar agora (bipar o que leva)"**.
6. Bipe o que o cliente vai levar. O resumo mostra a linha **"Crédito da troca"** e o **"A pagar"**.
7. Finalize: se leva mais caro, paga a diferença; se leva o mesmo valor, fecha zerado; se leva mais barato, a sobra fica em **vale** (ou, se a loja permite, aparece "Sobram do crédito" para escolher **Devolver em dinheiro** ou **Deixar no vale**).

Ao final, use **Comprovante da troca** para imprimir. Se a troca sair da política da loja (prazo vencido, dinheiro), pode ser pedida a senha de um gerente.

## Como trocar uma peça que não tem cupom ou não foi vendida pelo sistema?
Palavras-chave: troca sem cupom, peça sem cupom, sem nota, sem venda, produto vendido antes do sistema, troca sem comprovante, bipar peça

Dentro da troca (F6) há dois caminhos:

**A peça não está na venda encontrada:** no passo dos itens, use o campo de busca (aceita código ou **nome**) e clique **Adicionar**. A peça entra **pelo preço de venda de hoje** ("peça sem cupom entra pelo preço de venda de hoje").

**A peça não foi vendida por este sistema** (venda antiga, de antes de usar o sistema):
1. No F6, clique em **"A peça não foi vendida por este sistema? Peça sem venda — bipar o código"**.
2. Bipe ou busque a peça; ela entra pelo preço de venda de hoje.
3. Siga a troca normalmente.

Sem venda de origem, toda peça precisa ser bipada pelo código. Mensagem: **"Sem venda de origem, toda peça precisa ser bipada pelo código."**

Serviço não volta ao estoque.

## Como registrar uma devolução em que o cliente não leva nada?
Palavras-chave: devolucao, devolução, cliente devolveu, devolver produto, estorno de produto, arrependimento, devolver sem trocar

Há dois jeitos:

**No PDV:** F6 → localize a venda → marque os itens → **"Só devolver"** → escolha se a sobra **vira crédito na loja (vale)** ou **é devolvida em dinheiro pela gaveta** → **Confirmar devolução**. Aparece "Devolução registrada" e o botão **Imprimir comprovante**.

**Fora do PDV:**
1. Menu **Vendas → Trocas e Vales**.
2. Clique **Registrar devolução**.
3. Busque pelo número da venda, código do cupom (V123) ou nome do cliente.
4. Marque o que volta, o estoque, o motivo e o destino da sobra (vale ou dinheiro).
5. **Confirmar devolução**.

Regras:
- Devolver em dinheiro só aparece se a loja permite, e **exige caixa aberto nesta loja** ("Para devolver em dinheiro é preciso ter um caixa aberto nesta loja...").
- Se a venda tem parcelas em aberto (venda a prazo/boleto), o valor devolvido **abate primeiro essas parcelas**.
- Devolveu tudo, a venda passa a "Devolvida".

## Como usar, consultar e cancelar um vale de crédito da troca?
Palavras-chave: vale, vale troca, credito na loja, crédito na loja, usar vale, codigo do vale, saldo do vale, cancelar vale, vale vencido

O vale é o crédito gerado numa troca/devolução. Tem código impresso no comprovante (formato VT-XXXX-XXXX) e vale em **qualquer loja da empresa**.

**Usar no PDV:**
1. Lance os itens e clique na forma **Vale**.
2. Digite ou bipe o **"Código do vale"** e clique **Usar vale**.
3. Aparece o saldo. Pode usar só uma parte: o que sobra continua no vale. Se o vale não cobre tudo, pague o resto com outra forma (split).

Mensagens: **"Vale não encontrado nesta empresa. Confira o código impresso no comprovante."**, **"Informe o código do vale"**, **"O valor do vale não pode passar do total da venda."**.

**Consultar todos:** **Vendas → Trocas e Vales → Vales emitidos** — filtros Ativos, Utilizados, Vencidos, Cancelados; mostra valor, saldo e validade.

**Cancelar um vale:** na lista de vales, botão de cancelar (só **Dono e Gerente**). Só vale ativo pode ser cancelado: "Só um vale ativo pode ser cancelado.". O saldo deixa de valer para o cliente.

## Como configurar a política de trocas (prazo, validade do vale, dinheiro, senha do gerente)?
Palavras-chave: politica de troca, prazo de troca, validade do vale, devolver dinheiro, senha do gerente, regras de troca, 30 dias troca

Quem pode: **Dono e Gerente**, em **Gestão → Configurações da Loja → Trocas e Devoluções**:

- **Prazo para troca (dias)** — contado da data da venda. 0 = sem prazo. Padrão 30.
- **Validade do vale (dias)** — quanto tempo o crédito vale. 0 = não vence. Padrão 90.
- **Quando o cliente devolve mais do que leva, a sobra…**
  - **Vira crédito na loja (vale)** — padrão; a loja não devolve dinheiro.
  - **Pode ser devolvida em dinheiro** — o caixa pode devolver pela gaveta (a saída entra no fechamento) ou ainda dar vale.
- **Fora da política, pedir e-mail e senha de um gerente** — ligado por padrão: troca fora do prazo e devolução em dinheiro só saem com e-mail e senha de um gerente ou do dono. Quem já está logado como gerente/dono não precisa digitar de novo. Desligado: vendedor e caixa fazem qualquer troca sem autorização.

A política usada é a da **loja onde a troca está sendo feita**.

## A troca pediu senha do gerente ou deu erro. O que significa?
Palavras-chave: senha gerente troca, fora do prazo, troca recusada, erro troca, autorizacao gerente, autorização, troca bloqueada

Mensagens comuns na troca:

- **"... precisa da autorização de um gerente: informe e-mail e senha do gerente."** — a troca está fora da política (prazo vencido ou devolução em dinheiro). Um gerente ou o dono digita e-mail e senha no quadro "Fora da política da loja — autorização de um gerente".
- **"E-mail ou senha do gerente inválidos."** — confira os dados.
- **"Esta loja devolve a sobra como crédito na loja (vale). Para devolver em dinheiro, ligue a opção em Configurações da Loja → Trocas."**
- **"Para devolver em dinheiro é preciso ter um caixa aberto nesta loja..."** — abra o caixa ou escolha vale.
- **"Marque a quantidade de pelo menos um item para devolver"**.
- **"Quantidade maior que a disponível para (item) (restam N)."** — parte já foi devolvida antes.
- **"Escolha em qual estoque a peça devolvida entra"**.
- **"Só é possível trocar itens de uma venda concluída..."** — a venda está cancelada ou já toda devolvida.
- **"Já há uma troca em andamento (crédito de ...)"** — termine ou limpe a troca atual antes de abrir outra.

## Como cancelar uma troca ou devolução?
Palavras-chave: cancelar troca, desfazer troca, troca errada, cancelar devolucao, estornar troca, troca lançada errado

1. Menu **Vendas → Trocas e Vales**.
2. Abra a troca/devolução (ícone de ver).
3. Clique **Cancelar troca** (ou **Cancelar devolução**).
4. Informe o **Motivo** (obrigatório). Se for preciso, um gerente autoriza com e-mail e senha.
5. Confirme. Mensagem: "Troca cancelada: estoque, vale e caixa foram desfeitos."

O cancelamento desfaz tudo de uma vez: tira do estoque a peça que tinha voltado, cancela o vale e devolve ao caixa o dinheiro que tinha saído. Se algo não puder ser desfeito, **nada é alterado** e o sistema explica:
- "O vale ... já foi usado em outra venda (...). Cancele essa venda (o saldo volta ao vale) e depois cancele a troca."
- "A peça ... já não está no estoque ..."
- "O dinheiro devolvido ao cliente volta para a gaveta: abra o seu caixa nesta loja para cancelar a troca."
- "A venda nova #N desta troca já tem outra troca/devolução registrada. Cancele essa primeiro."

## Como cancelar uma venda?
Palavras-chave: cancelar venda, estornar venda, estorno, venda duplicada, venda lancada errado, desfazer venda, anular venda

Quem pode: **Dono e Gerente**. Vendedor e Caixa veem o botão, mas recebem "Você não tem permissão para acessar este recurso." — peça ao gerente.

1. Menu **Vendas → Vendas**.
2. Clique no ícone de cancelar na linha da venda, ou abra a venda e clique **Cancelar Venda**.
3. Confirme.

O que acontece:
- O **estoque volta**.
- O valor **sai do caixa na mesma forma de pagamento** (aparece como "Vendas canceladas" no fechamento). Se o caixa da venda ainda está aberto, sai dele; se já foi fechado, sai do **seu caixa aberto nesta loja**.
- As contas a receber da venda são canceladas e o vale usado volta ao saldo.
- **Devolva o dinheiro ao cliente**; PIX e cartão precisam ser estornados no banco ou na maquininha — o sistema não faz isso.

Para devolver só parte da venda, use a troca/devolução, não o cancelamento.

## Por que não consigo cancelar a venda?
Palavras-chave: nao consigo cancelar venda, erro cancelar venda, venda com nota, nfce prazo cancelamento, caixa fechado cancelar, venda com troca

Mensagens e o que fazer:

- **"Esta venda tem um documento fiscal ... Cancele o cupom/nota aqui primeiro (botão Cancelar) — depois cancele a venda."** — o sistema leva até a nota; cancele a nota e depois a venda. A NFC-e tem prazo curto de cancelamento na SEFAZ (em geral 30 minutos).
- Se a **NFC-e já passou do prazo**, a confirmação avisa que só a venda será cancelada e a nota continua válida. Ao confirmar: "Venda cancelada: estoque e caixa estornados. Atenção: a NFC-e nº ... passou do prazo e NÃO foi cancelada na SEFAZ — avise o contador."
- **"O caixa desta venda já foi fechado. Abra o seu caixa nesta loja para cancelar: o valor da venda sai dele."** — abra o seu caixa e tente de novo.
- **"Esta venda tem troca/devolução registrada — não pode ser cancelada por inteiro. Use a troca (F6 no PDV ou "Trocar / Devolver")."**
- **"O cliente já pagou parte desta venda (paga direto com a loja) — não dá para cancelar por inteiro. Use a troca/devolução, que abate do que ele ainda deve."**
- **"Esta venda ja esta cancelada."**
- **"Você não tem permissão para acessar este recurso."** — só Dono e Gerente cancelam.

## Como trocar o vendedor de uma venda já finalizada?
Palavras-chave: trocar vendedor, vendedor errado, esqueci o f3, corrigir vendedor, mudar vendedor da venda, comissao errada

Quem pode: **Dono e Gerente**.

1. Menu **Vendas → Vendas**, abra a venda.
2. No quadro **Cliente / Vendedor**, clique **Trocar vendedor**.
3. Escolha o vendedor certo e confirme. Mensagem: "Vendedor da venda #N trocado para (nome)."

Muda **só o vendedor e a comissão** dele: itens, valores, caixa e nota não mudam. A comissão pendente passa para o novo vendedor, recalculada com o percentual do cadastro dele (se ele tiver 0% ou vazio, a comissão é apagada).

Não é possível quando:
- **"Venda cancelada não troca de vendedor."**
- **"A comissão desta venda já foi paga — não dá para trocar o vendedor."** (a tela mostra "Comissão já paga — vendedor não pode ser trocado").
- **"Vendedor inválido para a loja desta venda."**

A troca fica registrada na auditoria.

## Como vender a prazo direto com a loja (o cliente paga depois)?
Palavras-chave: vender a prazo, fiado, crediario, crediário, caderneta, pendura, cliente paga depois, conta do cliente, carne loja

O botão dessa forma tem o **nome que a loja configurou** (por exemplo, o nome que aparece entre as formas de pagamento do PDV). Ele só aparece se estiver ligado em Formas de pagamento.

1. No PDV, escolha o **cliente** (F2) — é obrigatório.
2. Clique na forma de venda a prazo da loja (o nome do botão é o que a loja configurou).
3. Informe o **1º vencimento** e o número de **Parcelas** (até 12x).
4. Confirme e finalize.

A venda vira conta(s) a receber **pendente(s)** do cliente. O dinheiro **não entra no caixa agora** — entra quando o cliente pagar.

Mensagens:
- **"Escolha o cliente antes (F2)."** / "Venda no "(nome)" precisa do cliente..."
- **"Informe a data do 1º vencimento."**; "O 1º vencimento não pode ser antes de hoje."; "O 1º vencimento não pode passar de 1 ano."
- **"Passa do limite do cliente: pode comprar até R$ X..."** — veja o tópico de limite.

No fechamento do caixa essa forma não é contada: é o que o cliente ficou devendo.

## Como receber do cliente que comprou a prazo direto com a loja?
Palavras-chave: receber do cliente, receber fiado, quem me deve, cliente veio pagar, baixar conta cliente, recibo de pagamento, pagar divida

**Pelo PDV:**
1. Clique no botão **"Receber (nome da forma)"** no painel da direita.
2. Escolha o **Cliente** — aparece quanto ele **DEVE**.
3. Em **"Valor que o cliente está pagando"**, digite o valor (pode ser só uma parte — abate as contas mais antigas primeiro).
4. Em **Como pagou**, escolha a forma e clique **Receber**.

**Pelo menu:** **Vendas → "(nome da forma) — quem me deve"**. A lista mostra quem deve, quanto, quanto está vencido, o próximo vencimento e o limite. Clique **Ver e receber**, preencha **Valor pago**, **Como pagou** e clique **Receber**. Depois use **Imprimir recibo**.

Regras:
- O pagamento entra no **seu caixa aberto** nesta loja: "O pagamento entra no caixa: abra o seu caixa nesta loja para receber."
- O perfil Consulta não recebe: "Seu perfil não recebe pagamentos. Peça ao caixa ou ao gerente."
- "O valor é maior do que o cliente deve (R$ X)." — digite no máximo o devido.

O menu só aparece enquanto a forma estiver ligada.

## Como definir o limite de crédito do cliente para venda a prazo?
Palavras-chave: limite de credito, limite do cliente, passa do limite, credito do cliente, quanto pode dever, bloquear cliente devedor

1. Menu **Cadastros → Clientes**, abra o cliente e edite.
2. Preencha **"Limite de Crédito (opcional)"** — quanto o cliente pode ficar devendo nas compras a prazo direto com a loja.
3. **0 = sem limite.**
4. Salve.

No PDV, ao escolher a forma de venda a prazo, aparece o limite e quanto o cliente ainda pode comprar. Se passar, a venda é recusada com a mensagem: **"(cliente) passa do limite: limite R$ X, já deve R$ Y, pode comprar até R$ Z no "(nome da forma)". Aumente o limite no cadastro do cliente ou receba uma parte."**

O que fazer: aumentar o limite no cadastro, receber uma parte do que ele deve (botão Receber) ou vender em outra forma.

## Como criar um orçamento?
Palavras-chave: orcamento, orçamento, cotacao, cotação, proposta, fazer orcamento, novo orcamento, preço para cliente

Quem pode: **Dono, Gerente, Vendedor e Caixa** (Consulta só vê).

1. Menu **Vendas → Orçamentos** → **Novo Orçamento**.
2. Escolha o **Cliente** (obrigatório).
3. Em **Detalhes**: **Vendedor**, **Validade** (vem com 30 dias) e, se a loja tem mais de um estoque, **Sai de**.
4. Clique **Adicionar Item** e busque produto ou serviço. Ajuste quantidade, preço e desconto (%) por item.
5. Em **Observações**: "Observações Internas" (não aparecem para o cliente) e "Observações para o Cliente" (vem com o texto padrão da loja; marque "Imprimir as observações no orçamento" se quiser no papel).
6. Opcional: **Condições de pagamento e entrega**, **Taxa de entrega (R$)** (desconto não incide sobre ela) e **"Formas de pagamento no orçamento impresso"** (Cartão em até Nx e Desconto Dinheiro/PIX).
7. Desconto geral no **Resumo Financeiro**, campo **"Desconto no orçamento"**: escolha **Em %** ou **Em R$** e digite. É um desconto só, vale para o orçamento inteiro, não passa do subtotal dos itens e não incide sobre a taxa de entrega. O **Desconto Dinheiro/PIX** é outra coisa: aparece só no quadro de formas de pagamento da folha e não muda o TOTAL.
8. **Salvar Orçamento**.

O orçamento não baixa estoque nem entra no caixa.

## Como aprovar, imprimir e converter um orçamento em pedido?
Palavras-chave: converter orcamento, aprovar orcamento, imprimir orcamento, orcamento em pedido, recusar orcamento, orcamento expirado, pdf orcamento

Abra o orçamento em **Vendas → Orçamentos**. Botões disponíveis:

- **Aprovar** / **Recusar** (em "Alterar Status").
- **Imprimir orçamento** — folha para entregar ao cliente.
- **Editar** — enquanto não foi convertido.
- **Converter em Pedido** — confirme "Converter este orçamento em pedido? Esta ação não pode ser desfeita.". Mensagem: "Orcamento convertido em pedido com sucesso!". Depois disso aparece **Imprimir pedido**.

Regras:
- Orçamento **convertido** não pode ser editado nem excluído ("Orcamento convertido nao pode ser editado.").
- Orçamento **recusado** não mostra o botão de converter.
- Passou a validade, a tela mostra "(Expirado)".
- Excluir: só **Dono e Gerente** (e vendedor); o perfil Caixa não exclui.

**Personalizar o impresso:** Dono ou Gerente, em **Gestão → Configurações da Loja → Impressão do Orçamento**: telefone, WhatsApp, e-mail, site/Instagram, condições de pagamento e entrega, observações prontas e rodapé.

## Como criar um pedido e qual é o caminho dele até a venda?
Palavras-chave: pedido, novo pedido, pedido de venda, status do pedido, confirmar pedido, rascunho, entregue, cancelar pedido

Quem pode criar: **Dono, Gerente, Vendedor e Caixa**.

1. Menu **Vendas → Pedidos** → **Novo Pedido** (ou converta um orçamento).
2. Preencha **Cliente** (obrigatório), **Vendedor**, **Sai de** (se houver mais de um estoque), **Condição de pagamento**, **Canal da venda**, os itens (**Adicionar Item**), observações e desconto.
3. **Salvar Pedido** — ele nasce como **Rascunho**.

Caminho do pedido (botões em "Ações" na tela do pedido):
**Rascunho → Confirmar Pedido → Faturar Pedido → Marcar Entregue**. Em qualquer etapa antes de entregue dá para **Cancelar Pedido**.

Regras:
- Só pedido em **rascunho** pode ser editado ("Apenas pedidos em rascunho podem ser editados.").
- Só pedido **confirmado** pode ser faturado.
- Excluir: só rascunho ou cancelado, e só **Dono e Gerente** (e vendedor).
- Ao confirmar, é criada a conta a receber; ao faturar, baixa o estoque e gera a venda.

## Como faturar um pedido?
Palavras-chave: faturar pedido, faturamento, transformar pedido em venda, emitir nota do pedido, abra o caixa faturar, nf-e do pedido

1. Abra o pedido **Confirmado** e clique **Faturar Pedido**.
2. Escolha a **Forma de pagamento** (e parcelas, se for o caso). Se for vale, informe o **Código do vale** e, se não cobrir, a forma do restante.
3. Escolha o documento:
   - **Pedido impresso (folha A4)** — sem valor fiscal.
   - **Cupom Fiscal (NFC-e)**.
   - **Nota Fiscal (NF-e — "nota grande")** — exige cliente com endereço completo; após autorizar, XML e DANFE vão por e-mail ao cliente.
   - **Nenhum documento agora** — só fatura; documentos podem ser emitidos depois pela venda gerada.
4. Clique **Faturar**.

**Precisa de caixa aberto:** a venda do pedido entra no **seu caixa aberto nesta loja**. Sem caixa: **"Abra o caixa nesta loja antes de faturar: a venda do pedido entra no caixa, como no PDV."**

Outras mensagens: "Apenas pedidos confirmados podem ser faturados."; "NF-e exige cliente informado no pedido (com endereço completo)."; "Pedido faturado, mas a NFC-e falhou: ... — o recibo está disponível.". Opções fiscais aparecem como **indisponível** com a lista do que falta na Configuração Fiscal.

## Como imprimir o pedido em A4 e o que é a "Loja de saída"?
Palavras-chave: imprimir pedido, pedido a4, folha do pedido, loja de saida, loja de saída, de onde sai, recibo do pedido

**Imprimir:** abra o pedido em **Vendas → Pedidos** e clique **Imprimir pedido** (folha A4, sem valor fiscal). Se o pedido já foi faturado, aparece também **Recibo da venda** (cupom térmico). Num orçamento já convertido, o botão **Imprimir pedido** aparece na tela do orçamento.

**Loja de saída:** é a loja (unidade) de onde o pedido sai — a loja em que ele foi lançado. Aparece na tela e nos impressos do orçamento e do pedido. Se a loja tem mais de um estoque, aparece também **"Sai de: (estoque)"**, que é de onde a mercadoria será baixada ao faturar.

Para lançar o pedido em outra loja, troque a loja em que você está trabalhando antes de criar o pedido.

## Como trocar o vendedor de um pedido?
Palavras-chave: trocar vendedor pedido, vendedor errado no pedido, mudar vendedor do pedido, corrigir vendedor pedido

Quem pode: **Dono e Gerente**.

1. Abra o pedido em **Vendas → Pedidos**.
2. No quadro **Cliente / Vendedor**, clique **Trocar vendedor**.
3. Escolha e confirme.

Só funciona em pedido **rascunho ou confirmado**. Para pedido já faturado, entregue ou cancelado aparece: **"Pedido faturado, entregue ou cancelado não troca de vendedor aqui — no faturado, troque na venda."** Nesse caso abra a venda gerada (Vendas → Vendas) e use **Trocar vendedor** lá.

Se já estiver com ele: "O pedido já está com este vendedor."

## Como fazer uma venda balcão (fora do PDV)?
Palavras-chave: venda balcao, venda balcão, nova venda, venda manual, venda sem pdv, lancar venda manual, venda rapida

A venda balcão é uma venda lançada em formulário, fora da tela do PDV.

1. No **Dashboard**, clique em **Nova Venda** (atalho rápido).
2. Busque o **Cliente** (opcional) e escolha o **Vendedor**.
3. Em **Adicionar Produto**, busque e inclua os itens (quantidade, preço, desconto).
4. Escolha a **Forma de Pagamento** (obrigatória), o **Desconto (R$)** e as **Observações**.
5. Clique **Finalizar Venda**. Mensagem: "Venda #N registrada com sucesso!".

**Precisa de caixa aberto:** "Abra o caixa antes de registrar a venda: ela entra no caixa, como no PDV."

Outras mensagens: "Esta forma de pagamento está desligada. Escolha outra."; o desconto respeita o limite da loja.

Quem pode: **Dono, Gerente, Vendedor e Caixa**. Na lista de vendas ela aparece com o tipo **Balcão**.

## Como abrir uma ordem de serviço (OS)?
Palavras-chave: ordem de servico, ordem de serviço, os, assistencia tecnica, conserto, abrir os, nova os, equipamento, defeito

A Ordem de Serviço depende do plano contratado; se o menu não abrir, o plano não inclui.

1. Menu **Vendas → Ordens de Serviço** → **Nova OS**.
2. Em **Cliente e Equipamento**: busque o **Cliente** (se não existir, a busca oferece cadastrar na hora — "Cadastrar Cliente" → **Salvar e usar**), descreva o **Equipamento** e o **Defeito Relatado** (obrigatórios).
3. Em **Itens (Produtos e Serviços)**: botões **Produto** e **Serviço** para adicionar peças e mão de obra. Informe desconto se houver.
4. Em **Responsaveis**: **Vendedor** e **Tecnico**.
5. **Observações** (internas).
6. Clique **Criar Ordem de Serviço**.

A OS nasce como **Aberta**. A lista mostra contadores de Abertas, Em Andamento, Aguardando Peca e Concluídas no mês.

Editar a OS: **Dono e Gerente**. Criar e mudar status: também Vendedor e Caixa.

## Como mudar o status da OS, imprimir e converter em venda?
Palavras-chave: status os, laudo tecnico, concluir os, converter os em venda, imprimir os, os entregue, cancelar os, garantia os

**Status** — na OS, **Alterar Status**:
- Aberta → Em Andamento ou Cancelada
- Em Andamento → Aguardando Peca, Concluida ou Cancelada
- Aguardando Peca → Em Andamento, Concluida ou Cancelada
- Concluida → Entregue ou Cancelada
O **Laudo Tecnico** é obrigatório ao concluir. Entregue e Cancelada são finais.

**Converter em venda** (OS Concluida): escolha a **Forma de pagamento** e clique **Converter em Venda**. Baixa as peças do estoque, gera a venda, entra no seu caixa e marca a OS como Entregue.
- "Abra o caixa nesta loja antes de converter a OS: a venda entra no caixa, como no PDV."
- "Esta loja nao tem estoque habilitado para venda. Configure em Estoque antes de converter a OS."
- "Apenas OS concluidas ou entregues podem ser convertidas em venda."
Converta **uma vez só** por OS.

**Imprimir:** botão **Imprimir**. Textos fixos (cabeçalho, termos de garantia, texto legal, rodapé) e os blocos (assinaturas, laudo, valores) são definidos por Dono/Gerente em **Configurações da Loja → Impressão da Ordem de Serviço**, por loja.

## Como criar uma promoção por período?
Palavras-chave: promocao, promoção, preco promocional, oferta, liquidacao, black friday, desconto por periodo, criar promocao

Quem pode: **Dono e Gerente** (e quem tem permissão de editar produtos).

1. Menu **Cadastros → Promoções** → **Nova promoção**.
2. O assistente tem 5 passos:
   1. **Período** — "Nome da promoção", "Começa em" e "Termina em (inclusive)". Vale o dia inteiro.
   2. **Lojas** — marque as lojas (ou "Marcar todas").
   3. **Produtos** — busque por nome, código de barras ou código interno; "Adicionar os resultados" ou **"Adicionar todos os produtos da loja"** (produto com preço R$ 0,00 fica de fora). Pode usar "Usar a lista da última promoção".
   4. **Preços** — para cada produto, o preço **à vista (Dinheiro/PIX)** em R$ ou % de desconto, e o do **cartão** (R$, % ou "regra da loja"). Há "mesmo % para todos".
   5. **Revisar e salvar** — clique **Salvar promoção**.
3. **Salvar rascunho** guarda para continuar depois; rascunho não muda preço.

A promoção entra sozinha no PDV, pedidos, orçamentos e etiquetas nos dias e lojas escolhidos; no PDV o item mostra "· PROMOÇÃO". O preço cadastrado do produto não muda.

## Como duplicar, encerrar ou excluir uma promoção? Por que deu conflito?
Palavras-chave: duplicar promocao, encerrar promocao, excluir promocao, promocao conflito, produto em duas promocoes, promocao nao aplicou

Em **Cadastros → Promoções**, filtre por **Situação** (Rascunhos, Em andamento, Agendadas, Concluídas, Encerradas antes do fim).

- **Duplicar** — cria uma nova promoção com os mesmos produtos e preços; escolha o novo período e confira.
- **Continuar** — retoma um rascunho.
- **Editar** — numa promoção agendada (ainda não começou).
- **Encerrar agora** — "Os produtos voltam ao preço normal hoje mesmo.".
- **Excluir** — só promoção agendada ou rascunho.

Regras e mensagens:
- Um produto não pode estar em duas promoções na mesma loja no mesmo dia: ao salvar, o erro lista produto, promoção, período e lojas.
- "O preço da promoção não é menor que o normal." / "o desconto tem que ser menor que 100%." — a promoção nunca aumenta preço.
- "O último dia não pode ser antes do primeiro."
- Cliente de **atacado** continua com o preço de atacado.
- Com cartão em "regra da loja", cada loja soma o acréscimo do cartão sobre o à vista da promoção.

## Como funciona a comissão do vendedor?
Palavras-chave: comissao, comissão, percentual de comissao, comissao errada, comissao 5%, comissão não aparece, configurar comissao

O percentual de comissão é o do **cadastro do funcionário**:
1. Menu **Cadastros → Funcionários**, edite o funcionário.
2. Em **"Comissão e unidades de acesso"**, preencha **Comissão (%)**.
3. Salve.

Cada venda (PDV ou venda balcão) gera comissão para o **vendedor escolhido na venda (F3)**, com esse percentual. **0% ou vazio = sem comissão.**

**Parece defeito, mas não é:**
- "A venda não gerou comissão" — o vendedor está com 0% ou vazio no cadastro, ou a venda ficou no nome de outra pessoa (esqueceu o F3).
- "Mudei o % e as vendas antigas não mudaram" — o percentual vale para as vendas novas. Ao trocar o vendedor de uma venda, a comissão pendente é recalculada com o % do novo vendedor.
- "Configurei % por categoria/produto em Comissões → Configurar e não mudou" — o percentual usado nas vendas é o do cadastro do funcionário.

Para acompanhar: menu **Relatórios → Comissões** (filtros Vendedor, Loja, Status e período; quadro "Comissão por loja (mês atual)") e o botão **Relatório** (por vendedor).

## Como pagar as comissões em lote?
Palavras-chave: pagar comissao, pagar comissões, baixar comissao, comissao paga, pagamento vendedor, fechar comissao do mes

Quem pode: **Dono e Financeiro**. Gerente, Vendedor e Caixa só consultam.

1. Menu **Relatórios → Comissões**.
2. Filtre por **Vendedor**, **Loja**, **Status: Pendente** e período.
3. Marque as comissões que vai pagar.
4. Clique **Pagar Selecionadas** e confirme. Mensagem: "Comissoes pagas com sucesso!".

O que acontece:
- As comissões marcadas passam para **Paga**.
- É lançada, para cada vendedor, uma conta já paga no Contas a Pagar ("Pagamento comissoes - nome", categoria Comissões), para aparecer no financeiro.

Depois de paga, a comissão trava a troca de vendedor daquela venda. O sistema não faz o pagamento em si (PIX, transferência): ele só registra.

## Como configurar as formas de pagamento (nome, ordem, ligar e desligar)?
Palavras-chave: formas de pagamento, botoes de pagamento, renomear forma, desligar forma, adicionar forma, ordem dos botoes, forma de pagamento nova

Quem pode: **Dono e Gerente**. Vale para **todas as lojas da empresa**.

1. Menu **Gestão → Configurações da Loja** → botão **Formas de pagamento**.
2. A prévia **"Como fica no PDV"** mostra os botões. **Arraste** para mudar a ordem.
3. **Clique num botão** para editar: **Nome no botão** (aparece no botão, no cupom e nos relatórios), **Funciona como** (define nota fiscal, caixa, tabela de preço e parcelas), **Ícone**, **Cor do ícone**, **Tamanho no PDV** (meia linha ou linha inteira) e **Ligada (aparece nas vendas)**.
4. Para desligar, arraste para **"Desligadas — não aparecem nas vendas"**.
5. **Nova forma** cria um botão a mais.
6. Clique **Salvar**. **Desfazer** descarta; **Voltar aos botões de fábrica** restaura o padrão.

Formas do sistema podem ser renomeadas, mudar visual e ser desligadas, mas não excluídas. Vendas antigas continuam com o nome certo. Os botões valem para o PDV, faturar pedido, venda balcão e OS.

Forma desligada no meio do uso dá: "Esta forma de pagamento está desligada..." ou "A forma de pagamento ... está desligada ou não existe nesta empresa. Recarregue a tela."

## Como consultar a lista de vendas e usar os filtros?
Palavras-chave: lista de vendas, consultar venda, buscar venda, vendas do dia, filtro de vendas, historico de vendas, venda por cliente, numero da venda

1. Menu **Vendas → Vendas**.
2. No topo: **Vendas Concluídas**, **Total Concluídas**, **Vendas Hoje** e **Canceladas** — mudam conforme os filtros.
3. Filtros: **Buscar** (número da venda ou nome do cliente), **Loja** (só para o Dono, com opção "Todas"), **Status**, **Origem** (Loja, WhatsApp, Online, Histórico importado, Maquininha Stone), **Forma de pagamento** (inclui vendas com pagamento dividido), **Data Início** e **Data Fim**.
4. A lista mostra Número, Data/Hora, Cliente, Vendedor, Caixa, Origem, Forma Pgto, Total e Status.
5. Clique no ícone de olho para abrir a venda: itens, pagamento, troco, nota fiscal, trocas desta venda e contas a receber.

Os outros perfis veem só as vendas da loja em que estão trabalhando.

## Como usar o relatório de vendas?
Palavras-chave: relatorio de vendas, relatório de vendas, faturamento, ticket medio, ticket médio, vendas por vendedor, vendas por forma de pagamento, imprimir relatorio

1. Menu **Relatórios → Vendas**.
2. Filtros: **Data Início/Fim**, **Origem**, **Forma de pagamento**, **Vendedor**, **Cliente** e **Lojas** (marque uma ou várias; "Marcar todas"/"Limpar"). Clique **Filtrar**.
3. O relatório mostra: Vendas, peças por venda, Peças vendidas, **Faturamento**, **Ticket médio por venda**, Valor bruto dos itens (antes de desconto e acréscimo), Descontos concedidos, Acréscimos (cartão/juros), Trocas/Devoluções e os quadros por forma de pagamento, por vendedor e por loja.
4. **Imprimir** gera uma versão limpa em A4.

A escolha de várias lojas vale para quem gerencia; Vendedor e Caixa ficam presos à loja em que estão trabalhando. O gerente vê as lojas às quais está vinculado.

## Como ver os produtos vendidos (relatório item a item)?
Palavras-chave: produtos vendidos, relatorio de produtos, mais vendidos, quanto vendeu de cada produto, itens vendidos, planilha de vendas

1. Menu **Relatórios → Produtos vendidos**.
2. Filtros: **Data Início/Fim**, **Origem**, **Forma de pagamento**, **Vendedor**, **Categoria**, **Produto**, **Cliente**, **Lojas da venda** e **Saiu da loja** (de qual loja a peça saiu, útil quando uma loja vende estoque de outra). Clique **Filtrar**.
3. Mostra **Peças vendidas**, **Total recebido pelos itens** (já com descontos e acréscimos), **Valor bruto (preço × qtd)**, **Vendas com esses itens** e o quadro por forma de pagamento, além da lista por produto.
4. **Planilha** baixa os dados para Excel; **Imprimir** gera A4.

## O que é "Vendedor opera somente o PDV"?
Palavras-chave: vendedor so pdv, vendedor só pdv, esconder menu do vendedor, vendedor ver custo, restringir vendedor, bloquear relatorio vendedor

Fica em **Gestão → Configurações da Loja → Acesso do vendedor**. Só **Dono e Gerente** alteram, e vale para a **empresa inteira** (todas as lojas).

- **Desligado (padrão):** o vendedor entra no Dashboard e navega pelo menu.
- **Ligado:** ao entrar, o vendedor cai direto no **PDV**. Continua vendendo, fazendo troca pelo F6, usando vale e abrindo/fechando o caixa — e deixa de alcançar Dashboard, relatórios, cadastros, estoque, financeiro, fiscal e comissões, inclusive digitando o endereço no navegador.

Use quando o vendedor é só de balcão e não deve ver faturamento, relatórios ou preço de custo. Gerente, caixa, financeiro, consulta e dono não são afetados.

## Quem pode fazer o quê em vendas (perfis)?
Palavras-chave: permissao, permissão, perfil, nao tenho permissao, você não tem permissão, quem pode cancelar, acesso negado, 403

Resumo por perfil nas telas de venda:

- **PDV, abrir/fechar o próprio caixa, sangria, suprimento, venda balcão:** Dono, Gerente, Vendedor, Caixa.
- **Troca/devolução (F6) e cancelar troca:** Dono, Gerente, Vendedor, Caixa (fora da política pode pedir senha de gerente). **Cancelar vale:** Dono e Gerente.
- **Cancelar venda e trocar vendedor da venda/pedido:** Dono e Gerente.
- **Orçamentos e pedidos:** criar e editar — Dono, Gerente, Vendedor, Caixa; excluir — Dono, Gerente, Vendedor. Consulta só vê.
- **Ver caixas de todos e fechar caixa de outro:** ver — Dono, Gerente, Financeiro; fechar — Dono e Gerente.
- **Pagar comissões:** Dono e Financeiro.
- **Promoções:** Dono e Gerente.
- **Configurações da Loja e Formas de pagamento:** Dono e Gerente.
- **Relatórios de vendas:** todos os perfis, mas Vendedor e Caixa veem só a própria loja.

Sem permissão, aparece **"Você não tem permissão para acessar este recurso."** — peça a quem gerencia a loja. Os perfis são definidos em Cadastros → Funcionários.
