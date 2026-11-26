# Cadastros, estoque e lojas

## Como cadastrar um produto novo?
Palavras-chave: cadastrar produto, novo produto, incluir produto, criar produto, cadastro de mercadoria, adicionar item, produto novo, cadastra produto

Quem pode: Dono e Gerente. Vendedor ou Caixa só se o dono/gerente ligar para ele a opção "Pode cadastrar e editar produtos" no cadastro de Funcionários.

1. No menu lateral, abra **Cadastros → Produtos**.
2. Clique em **Novo Produto**.
3. Etapa **"O que você vende?"**: preencha o **Nome do produto**, escolha a **Unidade de medida** (UN, KG, CX, PCT, LT, MT, M2, M3, PAR, JG) clicando no quadrinho, e digite o **preço de venda**. Categoria, descrição detalhada e preço de custo são opcionais.
4. Se é só isso que você precisa para vender, clique em **Salvar e Pronto**. Senão, clique em **Avançar**.
5. Etapa **"Identificacao"**: código de barras, fornecedor, SKU, foto, estoque mínimo, peso e medidas da embalagem (todos opcionais).
6. Etapa **"Dados Fiscais"**: NCM, CFOP, CST/CSOSN, origem e alíquotas. Se a empresa não emite nota, a tela diz "Sua empresa não emite nota fiscal" e basta clicar em **Salvar Produto**.
7. Ao final aparece "Produto cadastrado com sucesso!".

Importante: o cadastro do produto **não tem campo de quantidade em estoque**. Depois de salvar, dê entrada pela tela **Estoque → Movimentações → Nova Movimentação** (tipo Entrada) ou pela **Entrada por NF-e**.

## Quais campos do produto são obrigatórios de verdade?
Palavras-chave: campo obrigatorio produto, o que preciso preencher, asterisco, nao salva produto, campo vermelho, erro ao salvar produto

Para salvar um produto, só três coisas são obrigatórias:
- **Nome do produto** (até 255 letras);
- **Unidade de medida** (clicar em um dos quadrinhos UN, KG, CX...);
- **Preço de venda** (pode ser zero, mas não pode ficar em branco).

Na edição também é obrigatório o **Status** (Ativo ou Inativo).

Todo o resto é opcional: código de barras (o sistema gera um se ficar vazio), SKU (também gerado), categoria, fornecedor, foto, preço de custo, estoque mínimo, peso, medidas e dados fiscais.

Se ao clicar em Avançar o quadro de unidades ficar com borda vermelha, é porque você não escolheu a unidade de medida. Se aparecer "O campo descrição é obrigatório.", faltou o nome do produto. "O campo preço de venda é obrigatório." significa que o preço ficou vazio.

Os dados fiscais não são exigidos para salvar, mas são exigidos na hora de **emitir nota fiscal** (veja o tópico sobre NCM e CFOP).

## O que fazem os botões "Salvar e Pronto", "Pular e Salvar" e "Salvar Produto"?
Palavras-chave: salvar e pronto, pular e salvar, pular fiscal, salvar rapido, nao quero preencher fiscal, dados fiscais automaticos

São três jeitos de terminar o cadastro do produto:
- **Salvar e Pronto** (na primeira etapa): grava só com nome, unidade e preço. Ideal para cadastrar rápido.
- **Pular e Salvar** (na etapa Dados Fiscais, quando a empresa emite nota): grava sem você revisar os dados fiscais.
- **Salvar Produto**: grava tudo o que você preencheu.

Quando o CST/CSOSN fica vazio, o sistema preenche sozinho CST/CSOSN, CFOP, alíquotas de ICMS, PIS e COFINS e a origem com o padrão do regime tributário da empresa (Simples, Presumido ou Real). Na tela fiscal aparece o aviso "Preenchemos os dados fiscais baseado no regime...".

Atenção: o **NCM não é preenchido automaticamente**. Produto salvo sem NCM vende normalmente com recibo, mas a nota fiscal (NFC-e ou NF-e) vai recusar o item com "NCM inválido". Se a loja emite nota, edite o produto e informe o NCM antes de vender.

## Como funciona o código de barras do produto?
Palavras-chave: codigo de barras, EAN, ean13, gtin, leitor, bipar, codigo automatico, sem codigo de barras, barcode

No cadastro do produto (etapa **Identificacao**) há o campo **Código de barras (opcional)**.

- Se o produto já tem código de barras impresso na embalagem (industrializado), clique no campo e passe o leitor, ou digite o número.
- Se o produto não tem código (roupa, artesanato, produto a granel), **deixe em branco**: o sistema gera um código de barras interno de 13 dígitos (começa com 2), pronto para imprimir nas etiquetas e bipar no PDV.

O código pode ser trocado depois em **Editar** produto.

Cuidado: o sistema **não impede** dois produtos com o mesmo código de barras. Antes de cadastrar, busque o código na lista de produtos para ver se ele já existe; se houver duplicidade, ao bipar o PDV pode trazer o produto errado. A busca da lista de produtos aceita código de barras, SKU ou código interno.

## O que é SKU e código interno?
Palavras-chave: sku, codigo interno, codigo do produto, referencia, numero do produto, codigo sequencial

Todo produto tem dois códigos de controle além do código de barras:
- **Código interno**: número sequencial de 6 dígitos (000001, 000002...) que o sistema dá sozinho ao salvar. Aparece na lista de produtos na coluna Código e na tela de edição como "Gerado automaticamente".
- **SKU**: código de referência que você pode criar do seu jeito (ex.: CAM-AZU-M). Se deixar vazio, o sistema grava "SKU-" + o código interno.

Os dois servem para achar o produto: na lista de produtos, no PDV, nas etiquetas e na contagem de estoque você pode digitar qualquer um deles.

Na importação por planilha, a coluna **codigo** é o código interno. Se a planilha trouxer o código, o sistema usa esse número e, numa segunda importação, atualiza o mesmo produto em vez de criar outro.

## Como funcionam preço de custo, markup e preço de venda?
Palavras-chave: preco de custo, markup, margem, lucro, preco de venda, calcular preco, custo do produto

Na primeira etapa do cadastro do produto:
- **Preço de venda** (obrigatório): é o preço cadastrado. Dependendo da configuração da loja, ele aparece como "Preço à vista (Dinheiro/PIX)" ou "Preço de venda (cartão)".
- **Preço de custo** (opcional): quanto você pagou.
- **Markup**: é calculado sozinho e não dá para digitar. Conta: (venda − custo) ÷ custo × 100. Exemplo: custo R$ 50 e venda R$ 80 = markup 60%.

Se mudar o preço direto na lista de produtos, o markup é recalculado quando o produto tem custo.

O preço de custo também é atualizado automaticamente quando você dá entrada pela **Entrada por NF-e** com a opção "Atualizar o preço de custo dos produtos com o custo desta nota" ligada. Nesse caso o preço de venda **não muda**.

Para produto do tipo Produzido (cafeteria), ao salvar a ficha técnica o custo passa a ser o custo da receita.

## Como cadastrar preço diferente no cartão, no PIX ou para atacado?
Palavras-chave: preco cartao, preco credito, preco debito, preco pix, preco dinheiro, preco atacado, preco diferente forma de pagamento, tabela de preco

Na primeira etapa do cadastro do produto há quadros opcionais logo abaixo do preço de venda:

- **Preço no Cartão (Crédito e Débito)**: aparece quando a loja usa o preço cadastrado como preço à vista. Em branco, o cartão segue a regra geral de acréscimo das **Configurações da Loja**.
- **Preço no Dinheiro/PIX**: aparece quando a loja configurou que o preço cadastrado é o do cartão. Em branco, Dinheiro/PIX sai com o desconto da loja.
- **Preço Atacado**: usado automaticamente no PDV quando o cliente da venda está marcado como **Atacado** no cadastro dele, em qualquer forma de pagamento. Em branco, o cliente de atacado paga o preço à vista.

Esses preços são **fixos**: não acompanham quando você muda o preço de venda. Na lista de produtos eles aparecem com o aviso "Fixo: ..." em amarelo. Para mudar, abra **Editar** no produto.

A regra geral (acréscimo no débito/crédito, desconto no PIX) fica em **Gestão → Configurações da Loja → aba Preços e parcelas**.

## Como mudar o preço de um produto rápido pela lista?
Palavras-chave: alterar preco, mudar preco, editar preco rapido, preco na lista, reajuste, atualizar preco

Quem pode: Dono, Gerente e quem tem a opção "Pode cadastrar e editar produtos".

1. Abra **Cadastros → Produtos**.
2. Na coluna de preço, clique sobre o valor (aparece um lapisinho ao lado e a dica "Clique para alterar o preço").
3. Digite o novo preço e confirme.

O que muda: só o preço principal da lista (o preço à vista ou o do cartão, conforme a configuração da loja). Se o produto tem preço de custo, o markup é recalculado.

O que **não** muda: preços fixos por forma de pagamento (Cartão, PIX, Atacado). Se o produto tiver algum, a lista mostra embaixo "Fixo: Crédito R$ ..." — para alterar esses, use **Editar**.

Produtos com tamanhos e cores agrupados aparecem com "a partir de" e não têm o clique de preço; abra o produto e use **Mesmo preço para todos** no quadro de tamanhos e cores.

Vendedor, Caixa e Consulta veem o preço, mas não conseguem clicar para alterar.

## Como colocar ou trocar a foto do produto?
Palavras-chave: foto produto, imagem produto, trocar foto, adicionar foto, foto nao sobe, imagem grande, upload foto

A foto aceita **JPG, PNG ou WEBP de até 10 MB**.

Pelo cadastro: em **Editar** produto, etapa **Identificacao**, campo **Foto do produto**, escolha o arquivo e salve.

Pela tela do produto: abra o produto (ícone de olho na lista). No quadro "Estoque e Especificacoes", em "Foto do Produto", escolha a imagem e clique em **Adicionar foto** ou **Trocar foto**.

Quem pode: Dono e Gerente trocam tudo. O **Vendedor** pode trocar **só a foto**: ao abrir a edição aparece o aviso "Seu perfil altera apenas a foto do produto" e os outros campos ficam travados. O Caixa e o Consulta não trocam foto.

Mensagens de erro:
- "O arquivo foto não pode ser maior que 10240 KB." — a imagem passa de 10 MB. Diminua a foto (tire com resolução menor ou use um compressor) e envie de novo.
- "O campo foto deve ser uma imagem." — o arquivo não é imagem (ex.: PDF ou HEIC do iPhone). Salve como JPG.
- "Falha no envio de foto — o arquivo pode ser grande demais." — o envio não chegou inteiro; tente uma foto menor ou outra conexão.

## Quando NCM, CEST, CFOP e CST são obrigatórios?
Palavras-chave: ncm, cest, cfop, cst, csosn, dados fiscais, origem, aliquota, icms, pis, cofins, ipi, fiscal produto

Para **salvar** o produto, nenhum dado fiscal é obrigatório. Eles passam a ser necessários para **emitir nota fiscal**:
- **NCM** (classificação fiscal do produto, 8 dígitos): obrigatório na NFC-e e na NF-e. Vazio ou "00000000" faz a nota parar com "NCM inválido".
- **CFOP**: obrigatório na NF-e ("CFOP não definido"). O sistema já sugere o CFOP de venda dentro do estado.
- **CST/CSOSN**: se ficar vazio, o sistema preenche com o padrão do regime da empresa. Empresa do Simples vê a lista CSOSN (102, 103, 300, 400, 500); as demais veem CST (00, 10, 20, 40, 41, 60).
- **Origem**: também preenchida pelo padrão do regime.
- **CEST**: só para produtos com substituição tributária; deixe vazio se a contabilidade não indicou.
- **Alíquotas** (ICMS, PIS, COFINS, IPI): vêm "Pre-configurado" conforme o regime.

A etapa **Dados Fiscais** só mostra os campos quando a emissão fiscal da loja está ligada. Se aparecer "Você ativou a emissão fiscal, mas ainda não escolheu o tipo (NF-e ou NFC-e)", preencha mesmo assim — o produto fica pronto para quando a Configuração Fiscal for concluída.

No campo NCM você pode clicar para ver os já usados ou digitar uma palavra (ex.: "leite", "arroz") para procurar. Em caso de dúvida sobre NCM e CST, confirme com o seu contador.

## A nota fiscal deu "NCM inválido" ou "CFOP não definido". O que fazer?
Palavras-chave: ncm invalido, cfop nao definido, nota recusada produto, erro nfce produto, erro nfe item, nao emite nota

Essas mensagens aparecem antes de a nota ir para a SEFAZ, no formato "Item #2 (Nome do produto): NCM inválido.". A causa é o **cadastro do produto**, não a venda.

Como resolver:
1. Anote o nome do produto que aparece na mensagem.
2. Abra **Cadastros → Produtos**, busque o produto e clique em **Editar** (lápis).
3. Avance até a etapa **Dados Fiscais**.
4. Preencha o **NCM** com 8 dígitos válidos (não use 00000000) e confira o **CFOP**.
5. Salve e tente emitir a nota de novo.

Por que acontece: o botão **Salvar e Pronto** e o **Pular e Salvar** gravam o produto sem NCM, e produtos importados por planilha sem a coluna ncm também ficam sem.

Dica: para evitar, filtre na lista os produtos novos e revise a etapa Dados Fiscais de cada um antes de vendê-los com nota. Quem não sabe o NCM deve pedir ao contador ou olhar na nota de compra do fornecedor (o NCM vem no XML/DANFE).

## Para que serve o fornecedor no cadastro do produto?
Palavras-chave: fornecedor do produto, de quem compro, vincular fornecedor, produto fornecedor, sem fornecedor

O campo **Fornecedor (opcional)** fica na etapa **Identificacao** do cadastro do produto e serve para registrar de quem a loja compra aquele item. O produto funciona igual com ou sem fornecedor.

Para usar:
1. Cadastre antes o fornecedor em **Cadastros → Fornecedores**.
2. No produto, escolha o nome dele na lista "Fornecedor". Se nenhum fornecedor estiver cadastrado, a tela mostra o atalho "cadastre um".

Na **Entrada por NF-e**, o fornecedor da nota é localizado pelo CNPJ (ou cadastrado sozinho ao confirmar) e os produtos criados pela nota já nascem ligados a ele.

## Como funciona o estoque mínimo e o aviso de estoque baixo?
Palavras-chave: estoque minimo, estoque baixo, alerta estoque, aviso falta produto, estoque critico, reposicao

No cadastro do produto, etapa **Identificacao**, o campo **Estoque mínimo** define a partir de quando o sistema avisa ("Te avisamos quando o estoque ficar abaixo desse número").

Como o aviso aparece:
- No **sino** do topo, com a mensagem "N produto(s) com estoque baixo". O aviso é gerado quando alguém abre o **Dashboard**; clicar nele leva ao Relatório de Estoque.
- Em **Relatórios → Estoque**: produtos com saldo igual ou abaixo do mínimo ficam como **Estoque Baixo**; com saldo zero ou negativo, como **Estoque Critico**.

Parece defeito mas não é:
- Produto com estoque mínimo **0** nunca aparece como baixo, nem quando acaba. Coloque pelo menos 1.
- O mínimo é **um número só por produto**, e o relatório e o sino comparam com o saldo **somado de todas as lojas** da empresa. Uma loja zerada pode não gerar aviso se outra tiver saldo.

## Onde coloco a quantidade em estoque de um produto novo?
Palavras-chave: estoque inicial, quantidade inicial, saldo inicial, colocar estoque, dar entrada, produto sem estoque, estoque zerado

O cadastro do produto não tem campo de quantidade. Produto novo nasce com **estoque zero** em todas as lojas. Para colocar quantidade, escolha uma das formas:

1. **Movimentação manual** (Dono ou Gerente): **Estoque → Movimentações → Nova Movimentação**, tipo **Entrada**, busque o produto, informe a quantidade (e o custo, se quiser) e clique em **Registrar Movimentação**. Dá para lançar vários produtos de uma vez com **Adicionar item**.
2. **Nota de compra**: **Estoque → Entrada por NF-e**, enviando o XML da nota do fornecedor.
3. **Planilha** (para carga inicial grande): em **Estoque → Movimentações**, use **Importar planilha** com o modelo "Saldo de Estoque".
4. **Estoque por Loja** (planos com Multilojas): em **Gestão → Estoque por Loja**, digite a quantidade direto na tabela.

A entrada vai para a loja que está selecionada no topo da tela. Confira o nome da loja antes de lançar.

## Qual a diferença entre inativar e excluir um produto?
Palavras-chave: inativar produto, desativar produto, excluir produto, apagar produto, produto inativo, esconder produto, remover produto

**Inativar** (recomendado para produto que saiu de linha):
1. **Cadastros → Produtos**, clique em **Editar** no produto.
2. Na primeira etapa, troque **Status** para **Inativo** e salve.

Produto inativo some da busca do PDV, das etiquetas e das transferências, mas continua na lista de produtos (filtro Status → Inativo) e todo o histórico de vendas e estoque fica intacto. Para voltar a vender, mude para Ativo.

**Excluir** (botão vermelho de lixeira na lista, só Dono e Gerente):
- Pede confirmação "Tem certeza que deseja excluir este produto?" e mostra "Produto excluido com sucesso!".
- O produto some da lista e a **foto é apagada**.
- Uma transferência pendente com esse produto não poderá mais ser aprovada.

Use excluir só para cadastro feito por engano. Para produto com vendas, prefira inativar.

## O produto ficou cadastrado duas vezes. O que aconteceu?
Palavras-chave: produto duplicado, cadastrou duas vezes, produto repetido, duplicidade, clique duplo salvar, dois produtos iguais

Causas mais comuns:
- **Clique repetido no salvar**: o sistema ignora o segundo envio do mesmo nome pelo mesmo usuário em até 10 segundos e mostra "Produto cadastrado com sucesso! O segundo clique em salvar foi ignorado." Depois disso, salvar de novo cria outro produto.
- **Planilha importada duas vezes sem a coluna codigo**: sem o código, cada importação cria produtos novos.
- **Tamanhos e cores**: cada tamanho/cor da grade é um produto próprio, com código próprio. Isso é normal.

O sistema não bloqueia nomes ou códigos de barras repetidos.

Como resolver:
1. Em **Cadastros → Produtos**, busque o nome.
2. Veja qual dos dois tem vendas e estoque (abra pelo olho: "Últimas Movimentações de Estoque").
3. No que sobrou sem uso, use **Excluir** (ou **Editar → Status Inativo** se já teve movimento).
4. Se o duplicado já recebeu estoque, transfira o saldo com uma movimentação de **Perda** nele e **Entrada** no correto, explicando nas Observações.

## Como buscar um produto na lista?
Palavras-chave: buscar produto, procurar produto, pesquisar produto, nao acho produto, filtro produto, achar produto

Em **Cadastros → Produtos**, o campo **Buscar** aceita "Descrição, código de barras, SKU ou código interno".

Como a busca funciona:
- Pelo nome, ela procura **cada palavra** em qualquer ordem e ignora espaços, traços e barras: "short saia" acha "Short / Saia Jeans".
- Acentos e maiúsculas não importam.
- Códigos (barras, SKU, interno) podem ser digitados inteiros ou em parte.

Filtros ao lado: **Categoria**, **Status** (Ativo/Inativo) e, para cafeterias, **Tipo**. Clique em **Filtrar** para aplicar e no X para limpar.

Se não achar: confira se o filtro Status não está em "Ativo" com o produto inativo, ou se a categoria escolhida é outra. A lista mostra 15 produtos por página.

## Por que a coluna de estoque da lista mostra zero ou um número diferente?
Palavras-chave: estoque zerado lista, estoque errado, saldo diferente, quantidade nao bate, estoque outra loja, coluna estoque

A coluna **Estoque** da lista de produtos mostra o saldo **da loja selecionada no topo da tela** (o nome da loja aparece no título da coluna, ex.: "Estoque Centro"). Ela não soma as outras lojas.

Causas comuns de "estoque errado":
- Você está com outra loja selecionada. Troque em **Trocar Unidade**, no botão com o nome da loja no topo.
- A loja tem mais de um estoque (Salão, Depósito...). A coluna soma todos e abre o detalhe "Vitrine 3 · Depósito 7".
- Número em vermelho = saldo zero ou negativo (saiu mais do que entrou).
- Produto do tipo **Produzido** (cafeteria) mostra "pela receita": quem tem saldo são os insumos.
- Produto com tamanhos e cores agrupados mostra a soma das variações.

Para ver o saldo em cada loja, abra o produto (olho) e veja o quadro **Estoque por Unidade**, ou use **Gestão → Estoque por Loja**.

## O que é o tipo do produto (Revenda, Produzido, Insumo, Adicional)?
Palavras-chave: tipo do produto, cafeteria, lanchonete, produzido, insumo, adicional, revenda, ficha tecnica, receita, alimentacao

O campo **Tipo do produto** só aparece quando a empresa liga a opção de cafeteria/alimentação. Para ligar: **Gestão → Configurações da Loja → aba Estoque → "Usar tipo do produto, ficha técnica e adicionais"** (vale para a empresa toda; só Dono ou Gerente mudam).

Os quatro tipos:
- **Revenda**: "Compro e vendo pronto (água, refrigerante). O estoque baixa o próprio produto." É o padrão de todo produto.
- **Produzido (ficha técnica)**: "Feito aqui (cappuccino, misto quente). A venda baixa os insumos da receita."
- **Insumo**: "Matéria-prima (café em grão, leite, copo). Não aparece no PDV."
- **Adicional**: "Opção de outro produto (shot extra, leite de aveia). Escolhido na hora da venda."

Junto com o tipo aparece a chave **Mostrar nos atalhos do PDV** (só para Revenda e Produzido), que coloca o produto nos botões de atalho do PDV (tecla F5), se a loja ligou "Botões de atalho no PDV desta loja".

Parece defeito mas não é: produto Insumo não aparece na busca do PDV, de propósito.

## Como montar a ficha técnica (receita) de um produto produzido?
Palavras-chave: ficha tecnica, receita, composicao, insumos, baixar insumo, cappuccino, montar receita

1. Cadastre antes os insumos (café, leite, copo...) como produtos do tipo **Insumo**, com o preço de custo.
2. Abra o produto do tipo **Produzido** (ícone de olho na lista) e clique em **Ficha técnica e opções**.
3. No quadro "Ficha técnica (receita de 1 ...)", clique em **Adicionar insumo**, escolha o insumo e a quantidade **na unidade do insumo**: café em kg → 18 g = 0,018; leite em L → 150 ml = 0,150; copo em un → 1.
4. Embaixo, marque os **grupos de opções** que o caixa escolhe ao lançar o produto.
5. Clique em **Salvar**. Aparece "Ficha técnica e opções salvas.".

Ao salvar, o custo do produto passa a ser o custo da receita. Cada venda baixa os insumos; o produto produzido não tem saldo próprio.

Mensagens de erro:
- "Produto Produzido precisa de pelo menos um insumo na receita — senão a venda não baixa nada do estoque."
- "O mesmo insumo apareceu duas vezes na receita — some as quantidades numa linha só."
- "O produto não pode ser insumo da própria receita."
- "Um dos insumos não pode entrar na receita (só Insumo ou Revenda desta empresa)."

## Como criar grupos de opções (tamanho, leite, extras) para o PDV?
Palavras-chave: grupo de opcoes, adicionais, opcoes do pdv, tamanho p m g cafe, extras, complemento, escolha no pdv

Disponível para empresas com a opção de cafeteria ligada.

1. Em **Cadastros → Produtos**, clique em **Grupos de opções** e depois em **Novo grupo**.
2. Preencha o **Nome do grupo** (ex.: Tamanho, Leite, Extras).
3. Em **Quantas o cliente pode escolher**, defina o limite.
4. Marque **Obrigatório** se o PDV só puder lançar o produto depois da escolha (ex.: tamanho P/M/G).
5. Clique em **Adicionar opção** para cada opção; escolha um produto do tipo Adicional já cadastrado ou "— novo adicional —".
6. Clique em **Salvar grupo**.
7. Abra o produto vendido, clique em **Ficha técnica e opções** e marque o grupo.

No PDV, ao lançar o produto, o caixa escolhe as opções; o preço do adicional soma ao do produto e a receita do adicional (se houver) baixa do estoque. Adicional sem receita não baixa nada (ex.: tamanho P).

## Como cadastrar um produto com tamanhos e cores (grade)?
Palavras-chave: grade, tamanho, cor, variacao, numeracao, p m g, roupa, calcado, tamanhos e cores, variantes

Na grade, **cada tamanho/cor vira um produto próprio**, com código de barras, preço e estoque próprios.

1. Cadastre e salve o produto normalmente (ex.: "Vestido Longo").
2. Abra **Editar** nesse produto. No alto aparece "Este produto tem tamanhos ou cores? Crie todos de uma vez" — clique para abrir.
3. Em **Opção 1** deixe "Tamanho" e em **Valores, com vírgula** digite "P, M, G". Em **Opção 2** escreva "Cor" e os valores "Preto, Branco". Há uma terceira opção livre (ex.: Voltagem).
4. Clique em **Criar tamanhos e cores** e confirme.

O produto atual vira a primeira combinação (P / Preto) e o sistema cadastra os outros como cópias: mesmo preço, dados fiscais e fotos, cada um com seu código de barras e **estoque zero**. Aparece "Pronto: N produtos novos, com estoque zero...". Depois dê entrada no estoque de cada um.

Limite: 60 combinações. Erros possíveis: "Informe pelo menos duas opções (ex.: P, M, G).", "São N combinações — o limite é 60. Reduza as opções." e "Este produto já faz parte de uma grade.".

Quem pode: quem edita produtos (Dono, Gerente ou quem tem a chave). Os outros veem "Seu perfil não cria tamanhos e cores".

## Como acrescentar tamanho, juntar produto ou mudar preço de toda a grade?
Palavras-chave: acrescentar tamanho, nova cor, juntar produto na grade, tirar da grade, desfazer grade, mesmo preco grade, mesma foto grade

Abra **Editar** em qualquer produto da grade. O quadro "Tamanhos e cores de ..." mostra uma linha por variação, com código, preço, estoque nesta loja e status.

- **Chegou tamanho ou cor nova?** Escolha a opção (Tamanho, Cor...), digite os valores novos (ex.: "GG, XG") e clique em **Acrescentar**. Os produtos que faltam são criados com estoque zero.
- **Juntar um produto que já está cadastrado**: digite o código dele e o tamanho/cor que ele representa e clique em **Juntar**.
- **Mesmo preço para todos**: aplica o preço deste produto (e os preços fixos por forma de pagamento) às outras variações. Salve o produto antes se acabou de mudar o preço.
- **Mesma foto para todos**: copia a foto principal deste produto para as outras.
- **Tirar**: tira a variação da grade (ela continua cadastrada). Na linha do principal o botão é **Desfazer grade**, que separa todos.

Mensagens: "Informe pelo menos um valor que a grade ainda não tem.", "O produto X já está em uma grade.", "Já existe uma variação com essas opções (cód. X).", "Este produto não tem foto para copiar.", "Passaria de 60 variações. Reduza as opções."

## Como mostrar os tamanhos e cores como uma linha só no PDV e na lista?
Palavras-chave: agrupar grade, uma linha so, grade no pdv, escolher tamanho no pdv, lista de produtos com grade

Por padrão, cada tamanho/cor aparece como um produto separado no PDV e na lista de produtos. Para agrupar:

1. **Gestão → Configurações da Loja**, aba **Estoque**.
2. Ligue **Mostrar o produto com tamanhos e cores como uma linha só**.
3. Clique em **Salvar Configurações**.

Com a opção ligada: no PDV o produto aparece uma vez e o caixa toca no tamanho e na cor, já vendendo o saldo de cada um. Na lista de produtos aparece uma linha por produto, com o saldo somado, preço "a partir de" quando as variações têm preços diferentes e um clique para abrir os tamanhos. Bipar o código de barras continua indo direto na peça certa.

A opção é **por loja**. Quem pode mudar: Dono e Gerente. Loja sem nenhum produto com grade não percebe diferença.

## Como importar produtos por planilha?
Palavras-chave: importar produtos, planilha, excel, csv, xlsx, importacao, carga de produtos, migrar produtos, modelo excel

Quem pode: Dono e Gerente (e quem tem "Pode cadastrar e editar produtos").

1. Em **Cadastros → Produtos**, clique em **Modelo Excel** para baixar a planilha com as colunas certas e exemplos.
2. Preencha uma linha por produto. Colunas: descricao, codigo, codigo_barras, sku, unidade, ncm, cest, origem, preco_custo, markup, preco_venda, preco_debito, preco_credito, estoque_minimo, cfop, cst, icms, pis, cofins, ipi, altura, largura, comprimento.
3. Só **descricao** é indispensável. Sem unidade, vira UN.
4. Salve como **.xlsx** ou **.csv** (até 10 MB) e clique em **Importar planilha**.

Pontos importantes:
- A coluna **codigo** é o código interno. Com ela, importar de novo **atualiza** o mesmo produto. Sem ela, cada importação **cria produtos novos** (duplica).
- As linhas de exemplo do modelo são ignoradas se ficarem iguais.
- A importação **não** dá estoque. Para a quantidade, use a planilha "Saldo de Estoque" em Estoque → Movimentações.
- A planilha de produtos não tem coluna de categoria nem de fornecedor; ajuste depois na edição.
- Código de barras não é gerado na importação: produto sem a coluna codigo_barras fica sem código.

## O que significam as mensagens da importação de planilha?
Palavras-chave: erro importacao, linha pulada, importacao falhou, xls nao aceita, planilha nao importa, 0 linhas importadas

Ao terminar, o sistema mostra "N de M linhas importadas!" ou abre um resumo com o que falhou, linha a linha.

Mensagens comuns:
- "Envie uma planilha .xlsx ou um arquivo .csv. Formatos antigos (.xls) precisam ser salvos como .xlsx." — abra no Excel e use Salvar como → Pasta de Trabalho do Excel (.xlsx).
- "Arquivo vazio ou sem dados" — a planilha só tem o cabeçalho ou está na aba errada.
- "Linha N: pulada — campo obrigatório ausente (confira o Modelo Excel)." — faltou a coluna essencial daquela linha (produto sem descricao; fornecedor sem cnpj; cliente sem nome e sem CPF/CNPJ; saldo sem codigo ou com quantidade zero).
- "Arquivo grande demais (limite 10 MB)." — divida a planilha.
- "Sessão expirada — recarregue a página e tente de novo."
- No saldo de estoque: "produto de código X não existe — importe os produtos primeiro." e "Selecione a loja antes de importar o estoque — o saldo é por unidade."

Dica: os nomes das colunas podem ter acento e maiúsculas ("Preço Venda" vira preco_venda), mas precisam corresponder aos do Modelo Excel. Coluna com nome diferente é ignorada sem aviso.

## Como importar o saldo de estoque por planilha?
Palavras-chave: importar estoque, saldo inicial planilha, carga de estoque, migrar estoque, importar quantidade

Usado para colocar o estoque inicial de muitos produtos de uma vez (por exemplo, ao migrar de outro sistema). Quem pode: Dono e Gerente.

1. Importe os produtos **antes** (com a coluna codigo preenchida).
2. Confira no topo da tela qual loja está selecionada: o saldo entra **nessa loja**, no estoque de venda dela.
3. Vá em **Estoque → Movimentações** e clique em **Modelo Excel** (modelo "Saldo de Estoque"). Colunas: codigo, descricao, quantidade, custo_unitario, observacao.
4. Preencha o **codigo** (código interno do produto) e a **quantidade** que existe hoje.
5. Clique em **Importar planilha**.

Como o sistema trata:
- A quantidade da planilha é o **saldo final desejado**. Se o produto já tem saldo menor, entra só a diferença. Se já tem igual ou maior, a linha é pulada (não reduz estoque).
- Quantidade zero ou negativa é pulada.
- Cada entrada fica registrada como movimentação com a observação da planilha (padrão "Saldo inicial importado").

Para outra loja, troque a loja no topo e importe a planilha daquela loja.

## Como exportar a lista de produtos, clientes ou fornecedores?
Palavras-chave: exportar, baixar lista, planilha de produtos, exportar clientes, exportar fornecedores, csv, backup cadastro

Nas telas **Cadastros → Produtos**, **Cadastros → Clientes** e **Cadastros → Fornecedores** há o botão **Exportar** no topo. Ele baixa um arquivo **.csv** que abre no Excel.

O que vem em cada um:
- **Produtos**: código interno, código de barras, descrição, unidade, preço de custo, preço de venda, preço débito, preço crédito, NCM, CFOP e status. Vêm todos os produtos da empresa, ativos e inativos.
- **Clientes**: CPF/CNPJ, nome, nome fantasia, telefone, e-mail, cidade, UF e status.
- **Fornecedores**: CPF/CNPJ, razão social, telefone, e-mail, cidade e UF.

A exportação não traz saldo de estoque. Para estoque, use **Relatórios → Estoque** (imprimir) ou **Relatórios → Entradas e saídas** (botão Excel), ou a **Contagem cega** (Baixar .xlsx).

Dica: ao abrir o .csv no Excel, códigos de barras longos podem aparecer em formato científico (7,89E+12). Formate a coluna como Texto antes de salvar de novo.

## Como cadastrar um cliente?
Palavras-chave: cadastrar cliente, novo cliente, incluir cliente, cliente pessoa fisica, cliente pessoa juridica, cadastro de cliente, cnpj cliente

Quem pode: Dono, Gerente e Vendedor. Caixa e Consulta só consultam.

1. **Cadastros → Clientes → Novo Cliente**.
2. Etapa **Tipo**: escolha **Pessoa Física** ou **Pessoa Jurídica**.
3. Etapa **Dados**: CPF (ou CNPJ), **Nome Completo** (ou Razão Social, obrigatório), Nome Fantasia e Inscrição Estadual. Ao digitar o CNPJ, o sistema busca e preenche os dados da empresa.
4. Etapa **Endereço** (opcional): digite o CEP e o endereço é completado sozinho.
5. Etapa **Contato**: Telefone, WhatsApp, E-mail, Limite de Crédito, Tipo de preço (Varejo/Atacado) e Observações.
6. Clique em **Salvar Cliente**. Aparece "Cliente cadastrado com sucesso!".

O endereço não é obrigatório para vender, mas é para emitir **NF-e** para o cliente. A NF-e também exige o CPF/CNPJ.

Também dá para cadastrar o cliente na hora, sem sair da tela, pelo cadastro rápido de **Pedidos** e da **Ordem de Serviço**.

## O CPF/CNPJ do cliente é obrigatório? E o erro "já está em uso"?
Palavras-chave: cpf obrigatorio, cnpj obrigatorio, cliente sem cpf, cpf ja cadastrado, cpf em uso, documento obrigatorio, cpf duplicado

Depende da sua empresa. Por padrão o CPF/CNPJ é **obrigatório** no cadastro de clientes e fornecedores. A IA365 pode deixar o documento **opcional** para a sua empresa; nesse caso o rótulo aparece como "CPF (opcional)". Essa escolha é feita pela equipe da IA365 — peça ao suporte se quiser mudar.

Mesmo com o documento opcional:
- A **NF-e** continua exigindo o CPF/CNPJ do cliente: "NF-e exige CPF/CNPJ do destinatário — cadastre o documento do cliente ...".
- A NFC-e (cupom) não é afetada; o CPF pode ir só na nota, sem cadastro.

Mensagens:
- "O campo CPF/CNPJ é obrigatório." — a empresa exige o documento.
- "Este CPF/CNPJ já está em uso." — já existe um cliente com esse documento na empresa. Busque em **Cadastros → Clientes** pelo número (o cliente pode estar com status Inativo ou Bloqueado e por isso não apareceu na busca da venda). Clientes excluídos não contam.
- Para fornecedores: "Informe o CPF ou CNPJ do fornecedor." e "Já existe um fornecedor cadastrado com este documento."

## Para que servem o limite de crédito e o tipo de preço do cliente?
Palavras-chave: limite de credito, limite cliente, fiado limite, crediario limite, cliente atacado, tipo de preco, preco atacado cliente

Os dois ficam na etapa **Contato** do cadastro do cliente.

**Limite de Crédito (opcional)**: "Quanto o cliente pode ficar devendo nas compras para pagar depois, direto com a loja." **0 = sem limite.** Quando a loja vende na forma de pagamento "direto com a loja" (o nome do botão é definido por cada loja), o PDV confere: se a dívida passar do limite, a venda é barrada com a mensagem "... passa do limite: limite R$ X, já deve R$ Y, pode comprar até R$ Z ... Aumente o limite no cadastro do cliente ou receba uma parte."

**Tipo de preço**: **Varejo** (padrão) ou **Atacado**. "No PDV, cliente de atacado assume o Preço Atacado do produto." O Preço Atacado é cadastrado em cada produto; produto sem preço de atacado sai pelo preço à vista.

Para alterar um cliente existente: **Cadastros → Clientes**, lápis (Editar), ajuste e salve.

## O que muda quando o cliente está Inativo ou Bloqueado?
Palavras-chave: cliente bloqueado, cliente inativo, bloquear cliente, cliente nao aparece, nao acho cliente na venda, status cliente

Na edição do cliente há o campo **Status** com três opções: **Ativo**, **Inativo** e **Bloqueado**.

O que acontece na prática: clientes **Inativos ou Bloqueados não aparecem na busca de cliente** das telas de venda (PDV, pedido, orçamento). Continuam na lista de **Cadastros → Clientes**, onde dá para filtrar por Status.

Parece defeito mas não é: "o cliente não aparece na hora de vender, mas está cadastrado" — confira o status dele. Mude para **Ativo** e salve.

Se ao tentar cadastrar de novo aparecer "Este CPF/CNPJ já está em uso.", é porque o cadastro antigo existe com outro status. Reative o antigo em vez de criar outro.

Excluir cliente: botão de lixeira na lista (Dono, Gerente e Vendedor). O histórico de vendas continua.

## Como cadastrar um cliente rápido sem sair da venda?
Palavras-chave: cadastro rapido cliente, cliente rapido, cadastrar cliente no pedido, novo cliente na venda, cliente na ordem de servico

Nas telas de **Pedido** e de **Ordem de Serviço** existe o cadastro rápido:

1. No campo de busca de cliente, digite o nome ou o documento.
2. Se o cliente não for encontrado, aparece o botão **Cadastrar novo cliente**.
3. Na janela **Cadastrar Cliente**, escolha **Pessoa Física** ou **Pessoa Jurídica**, preencha CPF/CNPJ (obrigatório ou opcional, conforme a sua empresa), o Nome ou Razão Social e o telefone.
4. Salve: o cliente já fica escolhido na venda.

O cadastro rápido segue as mesmas regras do cadastro completo: o documento não pode repetir ("Este CPF/CNPJ já está em uso."). Depois, complete o endereço em **Cadastros → Clientes → Editar**, principalmente se for emitir NF-e.

## Como importar clientes por planilha?
Palavras-chave: importar clientes, planilha clientes, migrar clientes, excel clientes, csv clientes

Quem pode: Dono, Gerente e Vendedor.

1. **Cadastros → Clientes**, clique em **Modelo Excel**.
2. Preencha: cpf_cnpj, nome, nome_fantasia, ie, cep, logradouro, numero, complemento, bairro, cidade, uf, telefone, whatsapp, email, limite_credito.
3. Salve em .xlsx ou .csv e clique em **Importar planilha**.

Regras:
- Cada linha precisa de **CPF/CNPJ ou nome**. Sem os dois, a linha é pulada.
- Com documento, o cliente é identificado pelo CPF/CNPJ: importar de novo **atualiza** o mesmo cliente. Sem documento, ele é identificado pelo nome exato.
- 11 dígitos = Pessoa Física; mais que isso = Pessoa Jurídica.
- Campos de endereço vazios recebem preenchimento genérico ("-", "S/N", CEP 00000000 e UF SP). Revise o endereço antes de emitir NF-e para esses clientes.
- Clientes importados entram como Ativos.

## Como cadastrar um fornecedor? Por que pede endereço completo?
Palavras-chave: cadastrar fornecedor, novo fornecedor, fornecedor endereco obrigatorio, cnpj fornecedor, informe o cep, cadastro de fornecedor

Quem pode: Dono e Gerente (e quem tem a opção "Pode cadastrar e editar produtos").

1. **Cadastros → Fornecedores → Novo Fornecedor**.
2. Etapa **Identificacao**: CPF ou CNPJ (ao digitar o CNPJ, os dados são buscados sozinhos), **Nome ou razão social** e Nome Fantasia.
3. Etapa **Endereço**: CEP, Logradouro, Número, Bairro, Cidade e UF — **todos obrigatórios** no fornecedor. Digite o CEP e o resto é preenchido.
4. Etapa **Contato e Condicoes**: **Telefone** (obrigatório), Contato / Representante, E-mail e Condicoes Comerciais.
5. Salve. Aparece "Fornecedor cadastrado com sucesso!".

Mensagens que podem aparecer: "Informe a razão social.", "Informe o CEP.", "Informe o logradouro.", "Informe o número.", "Informe o bairro.", "Informe a cidade.", "Selecione o estado (UF).", "Informe um telefone de contato.", "Informe o CPF ou CNPJ do fornecedor." e "Já existe um fornecedor cadastrado com este documento."

Dica: se não souber o número, use "S/N". Fornecedores também são criados sozinhos ao confirmar uma **Entrada por NF-e** de um CNPJ novo, e podem ser importados por planilha (colunas cnpj, razao_social etc.; linha sem CNPJ é pulada).

## Como cadastrar um serviço e o código LC 116?
Palavras-chave: cadastrar servico, novo servico, codigo lc 116, item lista servico, iss, aliquota iss, cnae, nfse servico

Quem pode: Dono e Gerente.

1. **Cadastros → Serviços → Novo Serviço**.
2. Etapa **Dados do Serviço**: **Descrição** e **Valor Padrão (R$)** (os dois obrigatórios).
3. Etapa **Dados Fiscais** (só aparece preenchível se a loja emite NFS-e):
   - **Código LC 116**: item da Lista de Serviços da Lei Complementar 116, que define o serviço para o ISS (ex.: 14.01).
   - **CNAE**: digite a atividade (ex.: "consultoria") e escolha na lista.
   - **Alíquota ISS (%)**.
4. Salve. Aparece "Serviço cadastrado com sucesso!".

Se a empresa não emite NFS-e, a etapa fiscal mostra "Sua empresa não emite NFS-e" e o botão **Pular e Salvar**.

O código LC 116 e a alíquota de ISS corretos dependem do seu município e da atividade; confirme com o contador. Serviço pode ser inativado na edição (Status).

## Como criar e organizar categorias de produtos?
Palavras-chave: categoria, criar categoria, subcategoria, categoria pai, excluir categoria, nome ja em uso, departamento

Quem pode: Dono e Gerente.

1. **Cadastros → Categorias → Nova Categoria**.
2. Preencha o **Nome** (obrigatório).
3. Em **Categoria Pai**, escolha "Nenhuma (categoria raiz)" ou uma categoria existente para criar uma subcategoria (ex.: Roupas → Vestidos).
4. Descrição é opcional. Salve.

Regras e mensagens:
- O nome é **único na empresa**: "Este nome já está em uso." Escolha outro nome ou edite a categoria existente.
- Não dá para excluir categoria com produtos: "Não é possível excluir uma categoria que possui produtos vinculados." Mude os produtos para outra categoria (Editar produto → Categoria) e depois exclua, ou apenas **inative** a categoria (Status Inativa na edição).

Categoria inativa some do filtro de categoria da lista de produtos.

## Quais são os perfis de usuário e o que cada um pode fazer?
Palavras-chave: perfil, permissao, acesso, nivel de acesso, dono, gerente, vendedor, caixa, financeiro, consulta, quem pode, restricao

O sistema tem 7 perfis (o 7º, Administrador, é só da equipe IA365):

- **Dono/Gestor**: tudo da empresa, todas as lojas. Único que mexe em Minha Empresa, Configuração Fiscal, Integrações e Auditoria.
- **Gerente** ("Gerencia a unidade"): cadastros, estoque, funcionários, Configurações da Loja, Minhas Lojas e Multilojas, vendo só as lojas às quais está vinculado. No financeiro só consulta.
- **Vendedor** ("Registra vendas e orçamentos"): PDV, orçamentos, pedidos, clientes (cadastra e edita); vê produtos e estoque; troca só a foto do produto.
- **Operador de Caixa** ("Opera o PDV"): PDV, caixa, orçamentos e pedidos (sem excluir), emite nota; só consulta clientes e produtos; não vê estoque.
- **Financeiro** ("Gerencia contas e relatórios"): contas a pagar/receber, fluxo, relatórios; não vê cadastros de clientes/produtos nem estoque.
- **Consulta** ("Apenas visualização"): vê quase tudo, não altera nada.

Ajustes possíveis: "Pode cadastrar e editar produtos" (por funcionário) e "Vendedor opera somente o PDV" (para a empresa toda).

Se alguém vê a mensagem "Você não tem permissão para acessar este recurso.", o perfil dele não alcança aquela tela.

## Como criar um usuário (funcionário) para acessar o sistema?
Palavras-chave: criar usuario, novo funcionario, cadastrar funcionario, novo login, dar acesso, adicionar usuario, limite de usuarios

Quem pode: Dono e Gerente.

1. **Cadastros → Funcionários → Novo Funcionário**.
2. **Dados Pessoais**: Nome Completo (obrigatório), CPF e Telefone.
3. **Acesso**: **E-mail (login)**, **Senha** e **Confirmar Senha**, e o **Perfil de Acesso** (Gerente, Vendedor, Caixa, Financeiro ou Consulta).
4. **Configuração**: Comissão (%), "Pode cadastrar e editar produtos" e as **Unidade(s) de Acesso** (lojas em que ele trabalha).
5. Salve. Aparece "Funcionário cadastrado com sucesso! E-mail de boas-vindas enviado.".

Regras e mensagens:
- A senha precisa ter **pelo menos 8 caracteres** ("O campo senha deve ter pelo menos 8 caracteres."). A tela pode aceitar 6 na conferência, mas o sistema exige 8.
- "A confirmação de senha não confere." — as duas senhas estão diferentes.
- "Este e-mail já está em uso." — cada e-mail só pode ter um login em todo o sistema, inclusive em outra empresa. Use outro e-mail.
- "Você chegou ao limite de usuários do seu plano. Para cadastrar mais, mude de plano em Meu Plano ou fale com a nossa equipe."

Marque sempre ao menos uma loja em Unidade(s) de Acesso.

## O funcionário entra e aparece "Nenhuma unidade disponível". O que fazer?
Palavras-chave: nenhuma unidade disponivel, nao consegue entrar na loja, sem loja, vincular loja funcionario, unidade de acesso, funcionario sem loja

Depois do login, cada usuário escolhe a loja (unidade) em que vai trabalhar. O Dono vê todas as lojas ativas da empresa. Os demais perfis veem **só as lojas marcadas no cadastro dele**, em **Unidade(s) de Acesso**.

Se nenhuma loja foi marcada (ou se as lojas dele estão inativas), aparece: "Nenhuma unidade disponível. Entre em contato com o administrador."

Como resolver (Dono ou Gerente):
1. **Cadastros → Funcionários**, clique no lápis (Editar) do funcionário.
2. Avance até a etapa **Configuração**.
3. Em **Unidade(s) de Acesso**, marque as lojas em que ele trabalha.
4. Salve e peça para ele sair e entrar de novo.

Quem tem uma loja só entra direto nela; quem tem várias vê a tela **Selecionar Unidade**.

O vínculo de loja também define quem aparece no F3 (vendedores) do PDV quando a opção "No PDV, mostrar só os vendedores desta loja" está ligada.

## Como deixar o vendedor usar somente o PDV?
Palavras-chave: vendedor so pdv, restringir vendedor, vendedor nao ver relatorio, esconder faturamento, vendedor apenas pdv, bloquear menu vendedor

Quem pode ligar: Dono ou Gerente.

1. **Gestão → Configurações da Loja**, aba **Caixa e vendedor**.
2. No quadro **Acesso do vendedor**, ligue **Vendedor opera somente o PDV**.
3. Clique em **Salvar Configurações**.

Com a opção ligada, ao entrar o vendedor cai direto no PDV. Ele continua vendendo, fazendo troca pelo F6, usando vale e abrindo/fechando o caixa, mas deixa de alcançar Dashboard, relatórios, cadastros, estoque, financeiro, fiscal e comissões — mesmo digitando o endereço no navegador.

Atenção: esta opção **vale para a empresa inteira**, todas as lojas (é a única da tela assim). Gerente, caixa, financeiro, consulta e dono não são afetados.

Exceção: vendedor que recebeu "Pode cadastrar e editar produtos" continua acessando a tela de produtos.

Na mesma área há **No PDV, mostrar só os vendedores desta loja**: o F3 passa a listar só quem está vinculado à loja do caixa (dono e IA365 aparecem sempre; quem não tem loja vinculada aparece em todas).

## Como deixar um vendedor ou caixa cadastrar produtos?
Palavras-chave: vendedor cadastrar produto, caixa editar produto, permissao produto, liberar cadastro de produto, pode editar produtos

Por padrão, só Dono e Gerente cadastram e editam produtos. Para liberar uma pessoa específica sem mudar o perfil dela:

1. **Cadastros → Funcionários**, Editar no funcionário.
2. Etapa **Configuração**, em **Produtos**, ligue **Pode cadastrar e editar produtos**.
3. Salve.

A pessoa passa a cadastrar e editar produtos (preço, fiscal, foto, grade), além de fornecedores, categorias e serviços. **Excluir** continua só com Dono e Gerente.

Vale mesmo quando a empresa usa "Vendedor opera somente o PDV".

Sem essa chave, o vendedor só consegue trocar a **foto** do produto, e o caixa só consulta.

## Como funciona a comissão do funcionário?
Palavras-chave: comissao, porcentagem vendedor, percentual comissao, comissao do vendedor, calcular comissao

O percentual de comissão de cada pessoa fica no cadastro dela:

1. **Cadastros → Funcionários**, Editar.
2. Etapa **Configuração**, campo **Comissão (%)** (de 0 a 100).
3. Salve.

As vendas do vendedor usam o percentual do cadastro dele para calcular a comissão. Para conferir e pagar, use **Relatórios → Comissões**.

Dicas:
- Em branco ou 0 = sem comissão.
- Mudar o percentual vale para as vendas a partir da mudança.
- O vendedor da venda é o escolhido no PDV (tecla F3). Se a loja ligou "Vendedor selecionado é o responsável pela venda e pela entrada no caixa" (Configurações da Loja → Caixa e vendedor), a venda fica no nome dele mesmo que outra pessoa opere o caixa.

## Como trocar a senha de um funcionário ou desativá-lo?
Palavras-chave: trocar senha, mudar senha, alterar senha funcionario, redefinir senha usuario, desativar funcionario, bloquear usuario, funcionario saiu

Não existe tela para o próprio usuário trocar a senha estando logado. As formas são:
- O próprio usuário usa **Esqueci minha senha** na tela de login.
- O **Dono ou Gerente** define a nova senha:
  1. **Cadastros → Funcionários**, lápis (Editar).
  2. Etapa **Acesso**: preencha **Nova Senha** e **Confirmar Nova Senha** (mínimo 8 caracteres). Em branco, a senha atual é mantida.
  3. Salve.

**Desativar** quem saiu da empresa:
1. Na lista de Funcionários, clique no botão vermelho **Desativar** e confirme ("Tem certeza que deseja desativar este funcionário?"). Ou, na edição, mude **Status** para Inativo.
2. Aparece "Funcionário desativado com sucesso!". O cadastro não é apagado.

Recomendação: ao desativar, **troque também a senha** dele e retire as lojas de "Unidade(s) de Acesso", para garantir que ele não entre mais.

Ninguém consegue desativar o próprio usuário ("Você não pode excluir seu próprio usuário.").

## Esqueci minha senha. Como recupero?
Palavras-chave: esqueci a senha, recuperar senha, redefinir senha, perdi a senha, nao lembro a senha, link de senha, email de senha nao chegou

1. Na tela de login, clique em **Esqueci minha senha**.
2. Na tela **Recuperar Senha**, digite o e-mail de login e clique em **Enviar Link de Recuperacao**.
3. Aparece: "Se este e-mail estiver cadastrado, você receberá as instruções de redefinição em instantes. Confira também a caixa de spam."
4. Abra o e-mail e clique no link. Na tela **Nova Senha**, informe o e-mail, a nova senha (mínimo 8 caracteres) e a confirmação, e clique em **Redefinir Senha**.
5. Aparece "Senha redefinida com sucesso!" e você entra com a nova senha.

Problemas comuns:
- O e-mail não chega: confira o spam, se o e-mail digitado é exatamente o do login e se o usuário está ativo (usuário inativo não recebe). Se continuar, peça ao Dono ou Gerente para definir uma senha nova pelo cadastro de Funcionários.
- "Este link expirou. Solicite uma nova redefinição." — o link vale por **60 minutos**. Peça outro.
- "Token invalido ou expirado." — o link já foi usado ou um link mais novo foi pedido. Use sempre o e-mail mais recente.

## Apareceu "Muitas tentativas de login". O que fazer?
Palavras-chave: muitas tentativas, bloqueado login, senha errada varias vezes, conta travada, nao consigo entrar, e-mail ou senha incorretos

Para proteger contra invasão, o login trava depois de muitas tentativas erradas:
- **8 senhas erradas para o mesmo e-mail** em 15 minutos travam aquele e-mail.
- **30 tentativas erradas do mesmo computador/rede** em 15 minutos travam aquele endereço de internet.

A mensagem é: "Muitas tentativas de login. Aguarde N minuto(s) e tente de novo." O bloqueio dura até **15 minutos** e sai sozinho; ninguém precisa desbloquear.

O que fazer:
1. Espere o tempo indicado.
2. Enquanto isso, use **Esqueci minha senha** para criar uma senha nova, ou peça ao Dono/Gerente.
3. Entre com cuidado: confira Caps Lock e se o e-mail está certo.

Login certo zera a contagem do e-mail.

"E-mail ou senha incorretos." é a mensagem de cada tentativa errada (o sistema não diz qual dos dois está errado, por segurança).

## Como gerar e imprimir etiquetas com código de barras?
Palavras-chave: etiqueta, imprimir etiqueta, gerar etiqueta, codigo de barras etiqueta, etiqueta de preco, etiqueta produto, tag

Quem pode: Dono, Gerente, Vendedor, Caixa e Consulta.

1. Abra **Cadastros → Etiquetas** (ou o botão **Gerar Etiquetas** na lista de produtos).
2. Em **Formato da Etiqueta**, escolha o papel: folha A4 (2 x 5, 3 x 7, 4 x 10), uma bobina térmica ou um formato próprio em "Meus formatos".
3. Em **Produtos**, use **Filtrar produtos...** para achar e marque os produtos (ou **Selecionar Todos**).
4. Na coluna **Qtd. Etiquetas**, informe quantas de cada (1 a 100).
5. Confira o resumo (produtos selecionados e total de etiquetas) e clique em **Gerar Etiquetas**.
6. Na tela de impressão, imprima pelo navegador.

Só aparecem produtos **ativos**. Produto sem código de barras (por exemplo, importado por planilha sem essa coluna) sai sem barras: edite o produto e digite um código na etapa Identificacao.

Se a tela avisar "Selecione pelo menos um produto.", marque algum produto antes de gerar.

O preço impresso segue as Configurações da Loja: quando há acréscimo no crédito, a etiqueta sai como "6x R$ 54,00 ou R$ 300,00 no PIX".

## Quais formatos de etiqueta existem e como imprimir na térmica?
Palavras-chave: formato etiqueta, a4, pimaco, termica, bobina, zebra, elgin, argox, tomate, tag roupa, margem impressao, etiqueta desalinhada

Formatos prontos em **Cadastros → Etiquetas**:
- Folha A4: **2 x 5** (10 por página, grande), **3 x 7** (21, média) e **4 x 10** (40, pequena).
- Impressora térmica (bobina): **40 × 25 mm**, **50 × 30 mm** e **60 × 40 mm** (1 coluna), **33 × 22 mm** (2 colunas), **36 × 20 mm** (2 colunas, bobina 74 mm, Argox) e **Tag Roupa 35 × 60 mm** (3 colunas, bobina 105 mm, com furo).

Para a térmica: na janela de impressão do navegador, escolha a impressora térmica e coloque **Margens: Nenhuma**. Também desligue cabeçalho e rodapé do navegador e use escala 100%.

Se a etiqueta sair desalinhada, cortada ou pulando: confira se o tamanho do papel nas preferências da impressora é o mesmo da etiqueta. Se a sua bobina não está na lista, use **Cadastrar o formato da minha bobina**.

Sempre imprima 1 etiqueta de teste antes de rodar um lote grande.

## Como cadastrar o formato da minha bobina de etiquetas?
Palavras-chave: cadastrar formato etiqueta, formato personalizado, minha bobina, medida etiqueta, colunas etiqueta, espaco entre colunas

Quem pode: Dono e Gerente (e quem cadastra produtos).

1. Em **Cadastros → Etiquetas**, clique em **Cadastrar o formato da minha bobina**.
2. Meça a etiqueta com uma régua e informe **em centímetros**: **Nome do formato**, **Largura (cm)**, **Altura (cm)**, **Colunas** e **Espaço entre colunas (cm)** (0 se elas se encostam).
3. Informe a largura da bobina (a medida do papel, que está nas preferências da impressora em "Papel de etiquetas"). O botão de calcular sugere o espaço pelo tamanho da bobina.
4. Em **Arranjo da etiqueta**, escolha "Padrão — descrição, barras, preço" ou "Nome da loja no topo — preço pequeno, barras grandes embaixo".
5. Marque, se quiser, **Imprimir nome/logo da empresa** (só cabe a partir de 2,2 cm de altura).
6. Clique em **Salvar formato**. Aparece "Formato cadastrado! Imprima 1 etiqueta de teste e ajuste as medidas se precisar."

Erro comum: "N colunas de X cm com Y cm de espaço exigem uma bobina de Z cm, mas a sua tem W cm. Nessa bobina cabem K coluna(s)..." — reduza o número de colunas ou a largura.

O formato aparece em **Meus formatos**, com links para editar o layout ou excluir.

## Como mudar o desenho (layout) da etiqueta?
Palavras-chave: editor de etiqueta, layout etiqueta, mudar etiqueta, mover codigo de barras, logo na etiqueta, personalizar etiqueta, fonte etiqueta

Quem pode: Dono e Gerente (e quem cadastra produtos).

1. Em **Cadastros → Etiquetas**, clique no link de editar o layout ao lado do formato (vale para os formatos prontos e para os seus).
2. Na tela **Layout da etiqueta**, arraste os itens para posicionar e use a alça azul do canto para redimensionar. Setas do teclado movem 0,5 mm (com Shift, 0,1 mm); Delete tira o item.
3. Em **Itens da etiqueta**, desmarque o que não quer imprimir. O conteúdo vem sempre do cadastro do produto e da loja.
4. Clicando num item, ajuste **Fonte**, **Tamanho (pt)**, **Alinhamento**, **Grossura da letra** e **Itálico**.
5. Em **Desenhos e imagens**, adicione **Retângulo**, **Linha** ou **Enviar imagem** (PNG, JPG, GIF ou WEBP até 2 MB; até 30 imagens na galeria).
6. Clique em **Salvar layout**. Aparece "Layout salvo! Imprima 1 etiqueta de teste antes de rodar o lote."

O desenho vale só para aquele formato. Para desfazer, use **Voltar ao automático**. **Sair sem salvar** descarta as mudanças.

Se aparecer o aviso de que a empresa não tem logo cadastrada, o item "Logo da loja" não sai na impressão: envie a logo em **Gestão → Minha Empresa**.

Erro "A etiqueta precisa de pelo menos um item. Adicione um campo antes de salvar.": você desmarcou tudo.

## A etiqueta sai fraca ou apagada na impressora térmica. Como engrossar?
Palavras-chave: etiqueta fraca, etiqueta apagada, letra fina, negrito etiqueta, extra grosso, termica clara, impressao fraca

No editor de layout da etiqueta há a **Grossura da letra** de cada texto e, para mudar todos de uma vez, **Grossura de todos os textos**. As opções são:
- **Normal**;
- **Negrito** (o máximo que a fonte tem);
- **Extra grosso**;
- **Máximo (térmica fraca)**.

Como fazer:
1. **Cadastros → Etiquetas**, abra o layout do formato que você usa.
2. Em **Grossura de todos os textos**, escolha **Extra grosso** (ou **Máximo** se ainda sair claro).
3. Clique em **Salvar layout** e imprima 1 etiqueta de teste.

Outras causas de impressão fraca que não são do sistema: temperatura/densidade baixa nas configurações da impressora, cabeça de impressão suja ou papel térmico de baixa qualidade. Ajuste a densidade no driver da impressora.

## Como lançar entrada, ajuste, perda ou bonificação no estoque?
Palavras-chave: movimentacao de estoque, entrada de estoque, ajuste de estoque, perda, avaria, brinde, bonificacao, baixar estoque, nova movimentacao

Quem pode: Dono e Gerente. Vendedor e Consulta só consultam.

1. **Estoque → Movimentações → Nova Movimentação**.
2. Escolha o tipo:
   - **Entrada**: adiciona ao estoque (compras, recebimentos).
   - **Ajuste**: é o balanço — você digita a **quantidade contada** e o estoque fica igual a ela (para mais ou para menos; aceita zero).
   - **Perda**: tira do estoque (produtos danificados, vencidos ou extraviados).
   - **Bonificacao**: tira do estoque (saída para brindes ou amostras).
3. Se a loja tem mais de um estoque, escolha **Em qual estoque**.
4. Em **Itens da Movimentação**, digite nome, código ou código de barras (mínimo 3 letras ou números), escolha o produto, a **Quantidade** e, se quiser, o **Custo Unit.** Use **Adicionar item** para lançar vários de uma vez.
5. Escreva o motivo em **Observações** (até 500 caracteres, vale para todos os itens).
6. Clique em **Registrar Movimentação**.

O lançamento vai para a loja selecionada no topo da tela. Movimentação registrada **não pode ser editada nem apagada** (fica para auditoria): para corrigir um lançamento errado, faça o lançamento contrário (ex.: Perda para desfazer uma Entrada a mais) explicando nas Observações.

## Como deixar o estoque igual ao que contei? (Ajuste / balanço)
Palavras-chave: acertar estoque, corrigir saldo, estoque contado, inventario, diminuir estoque, ajuste negativo, zerar estoque, balanco, ajuste soma

Use o tipo **Ajuste** em **Estoque → Movimentações → Nova Movimentação**. O Ajuste é o balanço: no campo **Quantidade contada** você digita o que contou na prateleira, e o estoque fica **igual** a esse número.

- A tela mostra ao lado "No sistema: X" e a diferença que será lançada ("entra 3" ou "sai 3").
- Contou zero? Digite 0: o estoque zera.
- Contou o mesmo que o sistema mostra: nada é registrado.
- Na lista, o ajuste aparece com a diferença e a seta para cima ou para baixo.

Para **somar** mercadoria que chegou, o tipo certo é **Entrada**, não Ajuste. (Até 26/11/2026 o Ajuste somava a quantidade digitada; os ajustes antigos continuam no histórico como foram lançados.)

Outro caminho, para empresas com Multilojas no plano (Dono ou Gerente): **Gestão → Estoque por Loja**, digitando a quantidade final na célula do produto/loja.

Para inventário completo, imprima antes a **Contagem cega** (Relatórios → Contagem cega), conte, e depois lance o Ajuste com as quantidades contadas.

## Como usar mais de um estoque na mesma loja (depósito, vitrine, avaria)?
Palavras-chave: varios estoques, deposito, vitrine, avaria, estoque da loja, criar estoque, estoque de venda, local de estoque

Cada loja nasce com um estoque. Se quiser separar (Salão, Depósito, Avaria...), cada um com saldo próprio:

1. **Gestão → Configurações da Loja**, botão **Estoques da Loja** no topo.
2. Clique em **Novo Estoque**.
3. Preencha o **Nome** (ex.: Depósito) e, se quiser, um **Código**.
4. **É o estoque de venda desta loja**: o PDV e as vendas baixam daqui. Só um por loja — marcar este desmarca o atual.
5. **Vem pré-selecionado nos formulários**: o padrão das telas (também único por loja).
6. Salve. Aparece "Estoque criado.".

Como funciona no dia a dia:
- A venda sempre baixa do estoque de venda (a não ser que a loja deixe o caixa escolher no PDV).
- O saldo da loja é a soma de todos os estoques.
- Para mover peças entre eles, use **Estoque → Transferências** (origem e destino podem ser da mesma loja).
- As movimentações passam a perguntar "Em qual estoque".

Mensagens: "Esta loja já tem um estoque com esse nome." e "O estoque de venda/padrão da loja não pode ser inativado. Promova outro antes." Inativar não apaga o histórico. Quem pode: Dono e Gerente.

## Como deixar o caixa escolher de qual estoque a venda sai?
Palavras-chave: sai de, escolher estoque no pdv, vender do deposito, estoque na venda, pdv estoque

Para lojas com mais de um estoque ativo:

1. **Gestão → Configurações da Loja**, aba **Estoque**.
2. Ligue **Deixar o caixa escolher no PDV de qual estoque a venda sai**.
3. Clique em **Salvar Configurações**.

Com a opção ligada, o PDV mostra o campo **"Sai de"**, já vindo no estoque de venda. O caixa troca quando a peça está em outro estoque (ex.: Depósito), e ao bipar o produto o PDV mostra o saldo de cada estoque. Cancelar a venda devolve a peça para o estoque de onde ela saiu.

Desligada, toda venda baixa do estoque de venda, como sempre.

Se a loja tem um estoque só, o campo não aparece no PDV — a própria tela avisa para cadastrar outro em **Estoques da Loja**.

## Como transferir produtos de uma loja para outra?
Palavras-chave: transferencia, transferir estoque, mandar produto outra loja, mover estoque, enviar mercadoria filial, solicitar transferencia

Quem pode: Dono e Gerente.

1. Selecione no topo a loja **de onde a mercadoria sai**.
2. **Estoque → Transferências → Nova Transferência**.
3. Em **Loja destino**, escolha a loja que vai receber. Se houver vários estoques, escolha também **Estoque de origem** e **Estoque de destino** (dá para transferir dentro da mesma loja, ex.: Depósito → Salão).
4. Em **Itens da Transferência**, busque cada produto (mínimo 3 letras ou números) e informe a quantidade.
5. Escreva uma observação, se quiser, e clique em **Solicitar Transferência**.
6. Aparece "Transferencia solicitada com sucesso!". O estoque **ainda não mudou**.

Depois, alguém com permissão abre a transferência e clica em **Aprovar Transferência** — é nesse momento que o estoque sai da origem e entra no destino.

Mensagens: "Origem e destino não podem ser o mesmo estoque." e "Origem e destino são o mesmo estoque — não há o que transferir."

Na tela da transferência há o botão **Imprimir** para mandar a lista de itens junto com a mercadoria.

## O que significam aprovar, em trânsito, concluir e cancelar na transferência?
Palavras-chave: aprovar transferencia, transferencia em transito, concluir transferencia, cancelar transferencia, status transferencia, transferencia pendente

Uma transferência passa por estas situações (filtro **Status** em Estoque → Transferências):

1. **Solicitada**: criada, estoque ainda não mexido. Botões: **Aprovar Transferência** e **Cancelar**.
2. **Aprovada**: ao aprovar ("O estoque sera movimentado automaticamente"), o sistema tira da origem e coloca no destino na hora. Botão seguinte: **Marcar em transito**.
3. **Em trânsito**: só registra que a mercadoria está a caminho. Botão: **Marcar concluida**.
4. **Concluída**: registra que chegou. "O estoque ja foi movimentado na aprovacao; este passo apenas registra o andamento."
5. **Cancelada**: só dá para cancelar ou excluir enquanto está Solicitada.

Mensagens:
- "Esta transferencia nao pode ser aprovada." / "Esta transferencia nao pode ser cancelada." — ela já saiu de Solicitada.
- "Somente transferencias aprovadas podem seguir para em transito." / "Somente transferencias em transito podem ser concluidas."
- "Não é possível aprovar: o(s) produto(s) X foi/foram excluído(s)..." — a transferência não pode ser editada; cancele e faça outra sem esse produto.

Parece defeito mas não é: o estoque do destino aumenta já na aprovação, antes da mercadoria chegar. Transferência aprovada não se desfaz: se foi por engano, faça outra no sentido contrário.

## Como controlar peças emprestadas (em poder de terceiros)?
Palavras-chave: comodato, peca emprestada, influencer, editorial, showroom, consignado, em poder de terceiros, peca que volta, emprestimo

Para peças que saem e devem voltar (influencer, editorial, showroom, prova):

1. **Estoque → Movimentações → Nova Movimentação**, tipo **Bonificacao**.
2. Adicione os itens e quantidades.
3. No quadro **A peça volta?**, ligue **Esta peça deve retornar**.
4. Preencha **Com quem fica** (obrigatório), **Contato** (@ ou telefone) e **Volta em** (data, obrigatória, não pode ser no passado).
5. Clique em **Registrar Movimentação**.

O estoque baixa normalmente e a peça aparece em **Estoque → Em poder de terceiros**, com os quadros "Aguardando retorno", "Atrasadas", "Peças fora" e "Já voltaram". Passada a data, a peça fica atrasada e o sino avisa "N peça(s) não voltaram na data prevista".

Quando a peça voltar: clique em **Registrar retorno**, informe **Quanto voltou** (pode ser em partes) e **Confirmar retorno**. Ela volta ao estoque da loja de onde saiu ("Retorno registrado — a peça voltou para o estoque.").

Se não vai voltar: botão **Não voltou** ("Encerrar como não devolvida? O estoque NÃO volta.").

Mensagens: "Diga com quem a peça vai ficar.", "Informe a data prevista de retorno.", "A data prevista de retorno não pode ser no passado.", "Faltam apenas N para voltar."

## Como fazer contagem de estoque (inventário) com a contagem cega?
Palavras-chave: contagem cega, inventario, balanco, contar estoque, folha de contagem, conferencia de estoque

A **Contagem cega** gera uma folha para contar o estoque físico **sem mostrar o saldo do sistema** — de propósito, para quem conta não ser influenciado.

1. **Relatórios → Contagem cega**.
2. Filtre por **Loja**, **Categoria**, **Buscar** (SKU, código ou descrição) e marque os **Estoques na folha**. Há também a opção de listar só produtos com saldo (para contagem por partes).
3. Clique em **Imprimir** (ou **Baixar .xlsx** para preencher no computador).
4. A folha traz SKU, código, código de barras, produto, categoria, unidade e **uma coluna em branco por estoque** da loja para anotar a quantidade contada.

Depois da contagem, compare com o saldo do sistema e lance as diferenças: **Ajuste** para o que sobrou, **Perda** para o que faltou, ou digite o número contado em **Gestão → Estoque por Loja** (se o plano tiver Multilojas).

O sistema ainda não recebe a folha preenchida de volta para ajustar sozinho; o lançamento das diferenças é manual.

## O que mostra o Relatório de Estoque?
Palavras-chave: relatorio de estoque, posicao de estoque, estoque critico, curva abc, estoque ok, produtos em falta, valor em estoque

Em **Relatórios → Estoque** você vê a posição atual de todos os produtos da empresa:
- Cartões **Total de Produtos**, **Estoque OK**, **Estoque Baixo** e **Estoque Critico**.
- Lista de alertas (botão vermelho para abrir) com Estoque Atual, Mínimo, Falta e Situação.
- Tabela completa com Categoria, Preço Custo, Estoque Atual, Mínimo, Status e **Curva** ABC.

Como ler:
- **Baixo**: saldo igual ou abaixo do estoque mínimo. **Crítico**: saldo zero ou negativo (só para produtos com estoque mínimo preenchido).
- **Curva A/B/C**: A são os produtos que somam até 80% do faturamento, B até 95% e C o resto. Produto sem venda não recebe curva.

Parece defeito mas não é: o **Estoque Atual** deste relatório é a **soma de todas as lojas** da empresa, não só a do topo. Para o saldo de uma loja, veja a coluna Estoque da lista de produtos ou **Gestão → Estoque por Loja**.

Use o botão de imprimir no topo para tirar em papel ou PDF.

## Como ver tudo o que entrou e saiu do estoque num período?
Palavras-chave: entradas e saidas, relatorio movimentacao, historico estoque, extrato estoque, kardex, o que saiu do estoque

Quem pode: Dono, Gerente, Vendedor e Consulta.

1. **Relatórios → Entradas e saídas**.
2. Escolha o período (**De**/**Até**) ou um atalho: Hoje, 7 dias, 30 dias, Este mês, Mês passado.
3. Escolha **Tudo**, **Só entradas** ou **Só saídas**.
4. Filtre, se quiser, por **Produto**, **Categoria**, **Lançado por** (origem: venda, transferência, nota...), **Usuário**, tipo e lojas.
5. Clique em filtrar.

O relatório mostra resumos por tipo e por loja, o total por produto (Entrou, Saiu, Saldo do período) e cada movimentação com data, loja, produto e tipo.

Para levar: **Excel** baixa a aba aberta inteira; **PDF / Imprimir** abre pronta para salvar em PDF (escolha "Salvar como PDF" no destino da impressão). O PDF mostra até um limite de linhas; se aparecer o aviso, use o Excel ou diminua o período.

Para ver um produto só, também dá para abrir o produto (olho) e olhar "Últimas Movimentações de Estoque", ou usar o filtro Produto em **Estoque → Movimentações**.

## Como dar entrada no estoque pela nota fiscal de compra (XML)?
Palavras-chave: entrada por nfe, xml nota de compra, importar nota, entrada de mercadoria, nota do fornecedor, lancar nota de compra, danfe

Quem pode: Dono e Gerente.

1. Peça ao fornecedor o arquivo **.xml** da nota (vem por e-mail ou no portal dele). O PDF/DANFE não serve: ele não traz os dados dos produtos.
2. Selecione no topo a loja que recebeu a mercadoria.
3. **Estoque → Entrada por NF-e**. Em "1. Enviar o XML da nota", escolha o arquivo e clique em **Ler a nota**.
4. Abre a tela **Conferir NF-e nº ...** com o fornecedor ("Já cadastrado" ou "Será cadastrado ao confirmar"), número, emissão e total.
5. Ligue cada produto da nota a um produto seu e confira as quantidades (veja o tópico sobre a conferência).
6. Clique em **Confirmar entrada no estoque** e confirme.

Aparece "Entrada lançada no estoque." (e quantas contas a pagar foram criadas). A quantidade entra no estoque, o custo é atualizado e, se marcado, as parcelas viram contas a pagar.

Atalho: em **Fiscal → NFes Recebidas**, o ícone de entrada na linha da nota abre a conferência sem precisar do arquivo (exige ter feito a "Ciência da operação").

Da próxima nota do mesmo fornecedor, os produtos já vêm ligados sozinhos.

## Como conferir a NF-e de compra antes de lançar no estoque?
Palavras-chave: conferir nfe, ligar produto da nota, criar produto da nota, fator de conversao, caixa com 12, custo da nota, contas a pagar da nota

Na tela **Conferir NF-e**, cada linha mostra o produto como está na nota e, em "No seu estoque", o que fazer:
- **Ligar a um produto meu**: escolha o produto do seu cadastro. Quando o sistema reconhece (pelo código do fornecedor ou código de barras), aparece "ligado automaticamente".
- **Criar produto novo**: cadastra o produto com os dados da nota; informe o **preço de venda** e a unidade.
- **Não lançar**: ignora o item (ex.: frete cobrado como produto).

**Conversão**: se a nota vende por caixa e você vende por unidade, ajuste "1 CX = 12 un". A quantidade que entra no estoque é a da nota vezes esse número.

Embaixo:
- **Em qual estoque a mercadoria entra** (quando a loja tem mais de um).
- **Atualizar o preço de custo dos produtos com o custo desta nota** (ligado por padrão). Custo = valor do item + frete, seguro, IPI e ST da nota − desconto, dividido pela quantidade que entra. O preço de venda não muda.
- **Lançar esta nota em Contas a pagar**: usa as parcelas da nota; se a nota não traz parcelas, informe o vencimento. Só Dono e Financeiro lançam contas a pagar; para o Gerente aparece o aviso de que o financeiro lança depois.

**Descartar** apaga a conferência sem lançar nada (dá para enviar o XML de novo depois).

## Quais erros podem aparecer na Entrada por NF-e?
Palavras-chave: erro entrada nfe, xml invalido, nota ja lancada, nota em conferencia, nao consegui baixar xml, erro ao ler nota

Ao enviar o arquivo:
- "Envie o arquivo .xml da nota (não o PDF/DANFE)." / "O arquivo não é um XML de nota fiscal." — foi enviado PDF ou outro arquivo.
- "Este XML não é de NF-e (não achei o bloco infNFe). Envie o XML da nota de compra." — pode ser XML de evento, CT-e ou NFS-e.
- "Não consegui ler o XML — o arquivo está corrompido ou incompleto." — peça o arquivo de novo ao fornecedor.
- "O XML passa de 5 MB — confira se é mesmo o arquivo da nota."
- "Esta nota (nº X) já deu entrada no estoque em ... Não dá para lançar duas vezes." — a nota já foi lançada; nada a fazer.
- "Esta nota já está em conferência na loja X. Termine (ou descarte) por lá."
- "Não consegui baixar o XML completo desta nota. Faça a "Ciência da operação" em NFes Recebidas e tente de novo, ou envie aqui o arquivo XML que o fornecedor mandou."

Ao confirmar:
- "Item N (...): escolha o produto, marque "Criar novo" ou "Não lançar"."
- "Item N (...): informe o preço de venda do produto novo."
- "Item N: a conversão tem de ser maior que zero." / "Item N: quantidade zerada."
- "Nenhum item ficou para lançar — marque ao menos um."
- "A nota não traz as parcelas: informe o vencimento da conta a pagar."

Aviso amarelo "Esta nota foi emitida para o documento ..., que não é o desta loja nem o da empresa." — confira se a compra é mesmo sua antes de lançar.

## Como cadastrar ou editar uma loja (filial)?
Palavras-chave: cadastrar loja, nova loja, filial, nova unidade, editar loja, minhas lojas, limite de lojas, inativar loja

Quem pode: Dono (todas as lojas) e Gerente (cria lojas e edita só as lojas às quais está vinculado).

1. **Gestão → Minhas Lojas → Nova Loja**.
2. Preencha **Nome da Loja**, **CNPJ**, **Status** (Ativa, Inativa ou Em implantação), Inscrição Estadual, Inscrição Municipal, **Telefone**, **Gerente responsável** e o endereço (**CEP**, **Logradouro**, **Número**, **Bairro**, **Cidade**, **UF** obrigatórios).
3. Salve. Aparece "Loja cadastrada! O vínculo fiscal (Focus NFe) está sendo preparado automaticamente...".

Regras:
- Lojas com o **mesmo CNPJ** compartilham certificado A1, CSC e numeração das notas.
- Nome repetido: "Já existe uma loja com este nome. Use um nome diferente (ex: "Filial Centro")."
- Limite do plano: o botão vira "Limite de lojas do plano atingido" e aparece "Sua empresa atingiu o limite de lojas do plano atual. Fale com a IA365 para ampliar."
- Não dá para excluir loja por aqui: inative na edição ou peça ao suporte da IA365.
- Gerente que cria a loja fica vinculado a ela. Se ele tentar editar outra: "Você só pode editar as lojas às quais está vinculado."

Os dados fiscais da loja (certificado, séries) ficam em **Fiscal → Configuração Fiscal**, só para o Dono.

## O que é a política de estoque entre lojas?
Palavras-chave: politica de estoque, ver estoque outra loja, vender estoque de outra loja, silos, estoque compartilhado, multiloja estoque

Empresas com mais de uma loja têm uma regra de como as lojas enxergam o estoque umas das outras. Ela é definida pela equipe da IA365 (peça ao suporte para mudar):

- **Silos independentes**: cada loja só vê o próprio estoque.
- **Visualizar apenas**: as lojas veem o saldo das outras, mas para mover produto precisam de **Transferência** (com aprovação). O vendedor pode orientar o cliente a buscar na outra loja.
- **Ver e vender**: o PDV pode vender produto de outra loja e o sistema cria a transferência automática da origem para o destino.

Para saber qual está ligada na sua empresa, abra qualquer produto (olho): no quadro **Estoque por Unidade** aparece "Política: silos independentes", "somente visualização" ou "vender e transferir" entre unidades. (Esse quadro só aparece com mais de uma loja.)

Fiscalmente, cada loja continua sendo independente.

## O que são Multilojas e Estoque por Loja?
Palavras-chave: multilojas, comparar lojas, faturamento por loja, estoque por loja, ranking de lojas, visao consolidada

Disponível no plano com **Multilojas**, para Dono e Gerente (o Gerente vê só as lojas às quais está vinculado). Sem o recurso no plano aparece "Seu plano nao inclui esta funcionalidade. Faca upgrade para acessar."

**Gestão → Multilojas**: visão consolidada das lojas com Faturamento, Vendas, Ticket Médio, Comissão, Vendedores, Estoque Critico e Status, além de alertas. O botão **Comparar Unidades** compara as lojas escolhidas num período (Data Início/Data Fim).

**Gestão → Estoque por Loja**: tabela com o saldo de cada produto em cada loja e o Total.
1. Busque por descrição, código ou SKU.
2. Clique na célula e digite a **quantidade final** (ou use as setinhas ▲▼).
3. Clique no botão verde de salvar ("Edite as quantidades e salve tudo de uma vez").
4. Aparece "Estoque atualizado — N ajuste(s) em M produto(s)." (ou "Nenhuma alteração detectada...").

Cada mudança vira um ajuste registrado nas movimentações, no estoque de venda daquela loja. Célula com saldo negativo ou com fração mostra um aviso ao passar o mouse; digite a quantidade contada para corrigir.

## O que tem nas Configurações da Loja?
Palavras-chave: configuracoes da loja, configurar loja, ajustes da loja, opcoes do pdv, onde configura, parametros

**Gestão → Configurações da Loja** (Dono e Gerente) muda o comportamento **somente da loja selecionada no topo**. Nada muda até clicar em **Salvar Configurações**. Abas:

- **Caixa e vendedor**: "Vendedor selecionado é o responsável pela venda e pela entrada no caixa"; Acesso do vendedor ("Vendedor opera somente o PDV" e "No PDV, mostrar só os vendedores desta loja" — estas duas valem para a empresa toda).
- **Preços e parcelas**: se o preço cadastrado é o preço à vista ou o do cartão, desconto no Dinheiro/PIX, desconto máximo na venda, acréscimo no débito e no crédito, máximo de parcelas e tabela de juros.
- **Cupom e recibo**: recibo ou cupom fiscal na finalização da venda.
- **Trocas**: prazo para troca, validade do vale e senha do gerente.
- **Estoque**: escolher estoque no PDV ("Sai de"), tamanhos e cores como uma linha só, cafeteria/ficha técnica e botões de atalho no PDV.
- **Impressos**: textos da Ordem de Serviço e do Orçamento.

No topo da tela há os atalhos **Formas de pagamento**, **Estoques da Loja** e, quando liberado, **Pagamento e entrega**.

Em cada opção, clique em **Entenda esta opção** para ver o que muda ligada e desligada. Lojas diferentes podem ter configurações diferentes: confira a loja no topo antes de salvar.

## Como alterar os dados da empresa e enviar a logo?
Palavras-chave: minha empresa, dados da empresa, logo, logotipo, alterar endereco empresa, telefone da empresa, mudar cnpj, razao social

Quem pode: só o **Dono**.

1. **Gestão → Minha Empresa**.
2. Altere Nome Fantasia, **Logo**, Telefone, E-mail, Código do Município (IBGE, 7 dígitos, usado nas notas fiscais) e endereço.
3. Clique em **Salvar**. Aparece "Dados da empresa atualizados. O logo aparece nas etiquetas e documentos."

Logo: imagem PNG ou JPG de até **2 MB**. Erros: "O logo precisa ser uma imagem (PNG/JPG)." e "O logo pode ter no máximo 2 MB."

**CNPJ e Razão Social** não podem ser alterados aqui: "Alteração de CNPJ ou razão social: fale com o suporte da IA365."

Os dados de cada loja (endereço, IE, telefone) ficam em **Gestão → Minhas Lojas**, não aqui.

## Como trocar de loja (unidade) no topo da tela?
Palavras-chave: trocar loja, trocar unidade, mudar de loja, selecionar loja, outra filial, loja errada, unidade ativa

Tudo o que você faz (vendas, caixa, movimentações de estoque, configurações da loja) vale para a loja ativa, cujo nome aparece no botão do topo, ao lado do sino.

Para trocar:
1. Clique no botão com o **nome da loja** no topo.
2. Clique em **Trocar Unidade**.
3. Na tela **Selecionar Unidade**, clique na loja desejada.

Quem vê quais lojas: o Dono vê todas as lojas ativas da empresa. Os outros perfis veem só as lojas marcadas no cadastro deles (Unidade(s) de Acesso). Quem tem só uma loja não precisa escolher.

Se a loja que você procura não aparece: ela pode estar inativa (Minhas Lojas) ou não estar vinculada ao seu usuário — peça ao Dono ou Gerente para marcar em **Cadastros → Funcionários → Editar → Unidade(s) de Acesso**.

## Como usar a busca geral do topo?
Palavras-chave: busca global, pesquisar, procurar cliente, procurar venda, buscar no sistema, lupa do topo

No topo da tela há o campo **"Buscar clientes, produtos, vendas..."**.

1. Digite pelo menos **2 letras ou números**.
2. Aparecem até 5 resultados de cada grupo:
   - **Clientes**: por nome ou CPF/CNPJ;
   - **Produtos**: por descrição, código de barras ou código interno (mostra o preço);
   - **Vendas**: pelo número da venda.
3. Clique no resultado para abrir.

O campo só aparece em telas de computador/tablet maiores. No celular, use a busca dentro de cada tela (Clientes, Produtos, Vendas).

Para buscas mais completas (por palavras em qualquer ordem, filtros de categoria e status), use a busca da própria tela de **Cadastros → Produtos** ou **Cadastros → Clientes**.

## Como funcionam as notificações do sino?
Palavras-chave: notificacao, sino, aviso, alerta, marcar como lida, mensagens do sistema, avisos do topo

O **sino** no topo mostra um número com os avisos não lidos. Clique nele para ver os últimos.

Avisos que aparecem, entre outros:
- "N conta(s) a receber vencida(s)";
- "N produto(s) com estoque baixo";
- "N peça(s) não voltaram na data prevista" (peças em poder de terceiros);
- teste grátis perto de vencer, faturas e avisos fiscais (como certificado digital vencendo).

Os avisos de contas vencidas, estoque baixo e peças atrasadas são gerados quando você abre o **Dashboard**, sem repetir o mesmo aviso no mesmo dia enquanto ele não for lido.

Ações:
- Clique no aviso para ir à tela correspondente.
- **Marcar todas como lidas** zera o número.
- **Ver todas** abre a tela **Notificações**, com o histórico e o botão de marcar cada uma como lida.

## Como instalar o ERP como aplicativo no computador ou celular?
Palavras-chave: instalar aplicativo, app, pwa, icone na area de trabalho, atalho, tela inicial celular, baixar aplicativo

O ERP pode ser instalado como aplicativo: ganha um ícone próprio e abre em janela/tela cheia. Nada muda no uso.

Pelo convite: depois de navegar por algumas telas aparece o quadro **"Instalar o ERP no computador"** (ou "no celular"). Clique em **Instalar** e confirme no navegador. **Agora não** adia o convite por 7 dias.

Pelo menu: clique no seu nome no canto superior direito e em **Instalar aplicativo** (essa opção só aparece quando o navegador permite instalar).

Se o botão não aparecer, instale pelo próprio navegador:
1. Clique no ícone de instalar no fim da barra de endereço; ou
2. Abra o menu ⋮ do navegador e escolha **Instalar ERP Comercial**.

Funciona melhor no Google Chrome e no Microsoft Edge. Depois de instalado, o convite não aparece mais. O login e as permissões são os mesmos da versão no navegador.
