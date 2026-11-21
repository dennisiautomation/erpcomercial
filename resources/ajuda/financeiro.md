# Financeiro, relatórios e assinatura

## Quem pode mexer no Financeiro?
Palavras-chave: permissao financeiro, perfil, acesso negado, nao tenho permissao, quem ve contas, financeiro bloqueado, 403, sem acesso

O menu **Financeiro** aparece para todos, mas cada perfil tem um limite diferente:

- **Dono/Gestor, Administrador e Financeiro:** veem, criam, editam, dão baixa e excluem contas a receber e a pagar.
- **Gerente:** só **vê** as telas do Financeiro (listas, fluxo de caixa, despesas por centro de custo, DRE). Não cria conta, não dá baixa, não exclui.
- **Consulta:** só vê.
- **Vendedor e Operador de Caixa:** não entram no Financeiro.

Se a pessoa clicar num item sem ter o perfil certo, aparece a mensagem **"Você não tem permissão para acessar este recurso."** Isso não é defeito: é o perfil dela. Quem troca o perfil é o dono, em **Cadastros → Funcionários**, abrindo o funcionário e mudando o perfil.

Exceções importantes:
- **Folha de Pagamento:** só Dono/Gestor e Financeiro (o gerente não vê, mesmo podendo ver o resto).
- **Plano de Contas e Centros de Custo** ficam no menu **Gestão**, que só aparece para Dono, Administrador e Gerente.
- **Comissões** (em Relatórios): todos que vendem podem ver; **pagar** e **configurar** é só do Dono e do Financeiro.

## Como ver o que tenho para receber (Contas a Receber)?
Palavras-chave: contas a receber, recebiveis, quem me deve, parcelas, receber, a receber, contas receber, cr

1. No menu, abra **Financeiro → Contas a Receber**.
2. No topo aparecem três quadros: **Total Pendente**, **Total Vencido** e **Recebido no Mês**.
3. Use os filtros: **Forma de pagamento**, **Status**, **Cliente**, **Vencimento De** e **Vencimento Até**. O botão **Limpar** tira todos os filtros.
4. A lista mostra cliente, descrição, parcela (por exemplo 2/5), vencimento, valor e status. Conta atrasada fica com uma faixa vermelha à esquerda e o texto "X dias em atraso".
5. O ícone de olho abre os **Detalhes**, com a venda de origem (quando a conta veio de uma venda) e o **Histórico de Parcelas**.

De onde vêm as contas: toda venda do PDV gera conta a receber. Venda em dinheiro, PIX ou outra forma à vista já entra como **Paga**. Venda no cartão, quando a loja cadastrou as taxas em **Máquinas de Cartão**, entra **Pendente**, com a data em que a máquina vai pagar. Venda direto com a loja (o botão que a loja chamou de "Fiado", "Crediário" etc.) entra **Pendente** até o cliente pagar.

Os quadros e a lista somam **todas as lojas da empresa**, não só a loja em que você está.

Botões no topo: **Exportar** (planilha Excel), **Importar planilha**, **Modelo Excel** e **Nova Conta**.

## Como lançar uma conta a receber à mão (com parcelas)?
Palavras-chave: nova conta a receber, lancar recebivel, parcelar, carnê manual, cadastrar conta receber, divida cliente

Perfis: Dono/Gestor, Administrador e Financeiro.

1. Vá em **Financeiro → Contas a Receber → Nova Conta**.
2. Escolha o **Cliente** (obrigatório).
3. Preencha **Descrição** (ex.: "Serviço prestado").
4. Em **Valor Total**, digite o valor cheio.
5. Em **Parcelas**, escolha de 1 a 48. O sistema divide o valor em partes iguais; se sobrar centavo, ele vai para a última parcela.
6. Em **1o Vencimento**, escolha a data da primeira parcela. As outras vencem uma por mês, no mesmo dia.
7. (Opcional) Escolha a **Forma de Pagamento** e escreva **Observações**.
8. Confira o quadro **Prévia das Parcelas** e salve.

Mensagem possível: **"Esta forma de pagamento está desligada. Escolha outra."** — a forma foi desligada em Configurações da Loja → Formas de pagamento. Escolha outra ou peça ao gerente para religar.

Atenção: conta a receber não tem tela de edição. Se lançou errado e ela ainda está pendente, exclua (ícone de lixeira) e lance de novo.

## Como dar baixa (marcar como recebida) numa conta a receber?
Palavras-chave: dar baixa receber, marcar paga, recebi, quitar conta, baixar parcela, confirmar recebimento

Perfis: Dono/Gestor, Administrador e Financeiro.

1. Abra **Financeiro → Contas a Receber**.
2. Na linha da conta **Pendente**, clique no botão verde com o sinal de "certo" (**Dar baixa**).
3. Confirme a pergunta "Confirma o recebimento de R$ ...?".
4. A conta passa para **Paga**, com o valor cheio e a data de hoje.

Limites desta baixa:
- Ela marca o **valor inteiro**, com a **data de hoje**. Não dá para informar outro valor, outra data nem baixa parcial.
- Ela **não lança dinheiro no caixa**. Serve para controle do financeiro.
- Não existe botão para desfazer (estornar) a baixa de conta a receber. Se marcou por engano, fale com o suporte da IA365.

Se a conta for de venda **direto com a loja** (o botão que a loja chamou de Fiado, Crediário etc.), o botão verde vira **Receber no caixa** e leva para a ficha do cliente — veja o tópico sobre receber do cliente que comprou para pagar depois.

Dica: faça a baixa pela **lista**. Na tela de Detalhes, o botão **Marcar como Paga** pode dar erro (veja o tópico "Erro ao clicar em Marcar como Paga").

## Erro ao clicar em "Marcar como Paga" nos detalhes da conta a receber
Palavras-chave: marcar como paga erro, method not allowed, 405, pagina de erro baixa, nao baixa nos detalhes

Sintoma: na tela de **Detalhes** de uma conta a receber (ícone de olho), ao clicar em **Marcar como Paga** e confirmar, aparece uma página de erro e a conta continua pendente.

O que fazer enquanto isso não é corrigido:
1. Volte para **Financeiro → Contas a Receber**.
2. Ache a conta na lista (use os filtros de Cliente e Vencimento).
3. Clique no botão verde com o sinal de "certo" (**Dar baixa**) na própria linha e confirme.

Pela lista a baixa funciona normalmente. Se o erro aparecer também pela lista, anote o número da conta (#) e fale com o suporte da IA365.

## O filtro "Vencida" das Contas a Receber não mostra nada
Palavras-chave: filtro vencida vazio, contas vencidas nao aparecem, atrasadas receber, inadimplentes, vencidas lista vazia

Em **Financeiro → Contas a Receber**, escolher **Status: Vencida** costuma trazer a lista vazia, mesmo com contas atrasadas. É que o sistema não troca a conta atrasada para "Vencida": ela continua **Pendente** e só aparece em vermelho na tela.

Para ver as atrasadas:
1. Em **Status**, escolha **Pendente**.
2. Em **Vencimento Até**, coloque a data de ontem.
3. Filtre. As contas aparecem com a faixa vermelha e "X dias em atraso".

O quadro **Total Vencido** no topo já soma certo as pendentes atrasadas.

Em **Contas a Pagar** o filtro "Vencida" funciona normalmente.

Para quem compra direto com a loja (fiado/crediário), a tela **Vendas → "(nome do botão) — quem me deve"** tem a opção **Só quem está com conta vencida**.

## Por que a venda no cartão aparece como pendente nas Contas a Receber?
Palavras-chave: cartao pendente, venda cartao nao paga, recebivel cartao, maquininha pendente, credito pendente, D+30

Quando a loja cadastra as taxas e prazos em **Financeiro → Máquinas de Cartão**, cada venda no cartão vira uma conta a receber **Pendente**, com vencimento na data em que a máquina deve depositar (por exemplo D+30) e com o valor líquido calculado. Venda parcelada no cartão vira uma conta por parcela: a 1ª no prazo da regra e as outras a cada 30 dias.

Isso não é defeito: o cliente já pagou, mas o dinheiro ainda não caiu na sua conta. É o que a máquina vai te pagar.

Quando o dinheiro cair:
1. Abra **Financeiro → Contas a Receber**.
2. Filtre por **Forma de pagamento** (crédito ou débito) e pelo vencimento.
3. Dê baixa em cada conta (botão verde).

Para ver o total previsto com taxas, abra **Financeiro → Máquinas de Cartão → Recebíveis de Cartão**.

Sem regra cadastrada para o cartão, a venda no cartão entra como **Paga** na hora.

## Como cadastrar as taxas da maquininha de cartão?
Palavras-chave: maquina de cartao, adquirente, taxa cartao, prazo recebimento, stone taxa, cielo, rede, getnet, mdr, d+30, maquininha taxa

Perfis: Dono/Gestor, Administrador e Financeiro (o gerente só vê).

1. Abra **Financeiro → Máquinas de Cartão**.
2. No quadro **Nova regra**, preencha:
   - **Adquirente / máquina**: o nome que você reconhece (ex.: "Stone loja 1").
   - **Forma**: "Cartão de Débito (todos)", "Cartão de Crédito (todos)" ou um botão de cartão que a loja criou (ex.: "Cartão Elo").
   - **Parc. de** e **até**: a faixa de parcelas (ex.: 1 até 6). Vai de 1 a 24.
   - **Taxa (%)**: o percentual que a máquina cobra.
   - **Prazo (D+)**: em quantos dias o dinheiro cai (0 a 365).
3. Clique no botão **+**.
4. Repita para cada faixa (ex.: crédito 1x, crédito 2x a 6x, débito).

Em **Regras cadastradas** você ajusta e salva (ícone de certo) ou remove (lixeira). Um botão de cartão criado pela loja sem regra própria usa a regra do tipo dele (crédito ou débito).

O PDV usa essas regras para lançar a venda no cartão nas Contas a Receber com valor líquido e data prevista. A regra vale para as vendas feitas **depois** de cadastrada; vendas antigas não mudam.

## Como ver quanto a maquininha vai me pagar (Recebíveis de Cartão)?
Palavras-chave: recebiveis de cartao, previsao cartao, valor liquido, taxas das maquinas, quanto vou receber do cartao, antecipacao

1. Abra **Financeiro → Máquinas de Cartão**.
2. Clique em **Recebíveis de Cartão** (no topo).
3. Escolha o período em **De** e **Até** e, se quiser, o **Status** (Pendente ou Recebida).
4. Os quadros mostram **Bruto no período**, **Taxas das máquinas** e **Líquido a receber**.
5. A tabela **Previsão de recebimentos** mostra, por parcela: data prevista, venda, máquina, parcela, bruto, taxa, líquido e status.

Só aparecem vendas feitas depois que as regras foram cadastradas em Máquinas de Cartão. Se a tela vier vazia com o aviso "Nenhum recebível de cartão", confira se as regras existem e estão ativas.

Para marcar que o dinheiro caiu, dê baixa na conta em **Financeiro → Contas a Receber**.

## Como receber do cliente que comprou para pagar depois direto com a loja?
Palavras-chave: fiado, crediario, receber fiado, conta da loja, carne, quem me deve, cliente devendo, pagar divida, receber parcela cliente

O botão de pagamento "direto com a loja" tem o nome que a sua loja escolheu (pode ser "Fiado", "Crediário", "Conta da loja"...). Ele só aparece se estiver ligado em **Configurações da Loja → Formas de pagamento**.

Para receber quando o cliente vem pagar:
1. Abra o seu caixa (o pagamento sempre entra no caixa de quem recebe).
2. Vá em **Vendas → "(nome do botão) — quem me deve"**. Busque o cliente por nome ou CPF.
3. Clique em **Ver e receber**.
4. Em **Valor pago**, digite quanto ele está pagando. Pode ser só uma parte: o sistema abate primeiro as contas que vencem antes.
5. Em **Como pagou**, escolha dinheiro, PIX, cartão ou transferência.
6. Clique em **Receber**. Sai o recibo de pagamento para imprimir.

No PDV também existe o botão **Receber (nome do botão)**, que faz o mesmo.

Pela tela **Contas a Receber**, a conta desse tipo mostra o botão **Receber no caixa**, que leva para a mesma ficha.

Mensagens:
- **"Abra o caixa antes de receber: o pagamento entra no caixa."** — abra o caixa e tente de novo.
- **"O valor (R$ X) é maior do que o cliente deve (R$ Y)."** — digite no máximo o que ele deve.
- **"Seu perfil não recebe pagamentos. Peça ao caixa ou ao gerente."** — perfis Financeiro e Consulta não recebem.

## O cliente passou do limite de crédito na venda direto com a loja
Palavras-chave: limite de credito, passa do limite, fiado bloqueado, crediario recusado, limite cliente, nao deixa vender fiado

Mensagem no PDV: **"(Cliente) passa do limite: limite R$ X, já deve R$ Y, pode comprar até R$ Z no "(nome do botão)". Aumente o limite no cadastro do cliente ou receba uma parte."**

Causa: o cliente tem um limite de crédito no cadastro e a compra nova, somada ao que ele já deve, passa desse limite.

O que fazer (escolha um):
1. **Receber uma parte** antes: use **Receber (nome do botão)** no PDV ou a ficha em **Vendas → "(nome do botão) — quem me deve"**.
2. **Dividir o pagamento**: parte em dinheiro/PIX/cartão e o resto direto com a loja, dentro do que ainda cabe.
3. **Aumentar o limite**: em **Cadastros → Clientes**, edite o cliente e mude o campo **Limite de Crédito**. Limite **0 ou vazio = sem limite**.

Outras mensagens dessa venda:
- **"Venda no "(nome)" precisa do cliente: escolha o cliente antes de finalizar."** — esse tipo de venda exige cliente.
- **"O 1º vencimento não pode ser antes de hoje."** / **"O 1º vencimento não pode passar de 1 ano."** — ajuste a data.

## Como lançar uma conta a pagar?
Palavras-chave: nova conta a pagar, lancar despesa, cadastrar boleto fornecedor, pagar fornecedor, despesa, conta pagar, cp

Perfis: Dono/Gestor, Administrador e Financeiro.

1. Abra **Financeiro → Contas a Pagar → Nova Conta**.
2. Em **Pagar para**, escolha:
   - **Fornecedor**: busque e escolha na lista.
   - **Funcionário**: salário, vale, benefício (aparecem os cadastrados em Cadastros → Funcionários).
   - **Imposto / outro**: escreva o nome (INSS, FGTS, Receita Federal, Prefeitura, Vale-transporte...).
3. Preencha **Descrição**, **Valor** e **Vencimento**.
4. (Opcional) **Mês de competência**: o mês a que a despesa pertence (salário de outubro pago em novembro = outubro).
5. (Opcional) **Plano de contas** e **Centro de custo**: é por aqui que a despesa aparece separada no DRE.
6. (Opcional) **Forma de pagamento** e **Observações**.
7. Para dividir o valor, use **Parcelas** (até 48, uma por mês). Para despesa que se repete, use **Conta Recorrente** (tópico próprio).
8. Clique em **Salvar Conta**.

Mensagens de erro da tela:
- **"Escolha o fornecedor na lista. Se ele não aparecer, cadastre o fornecedor primeiro (link abaixo do campo)."**
- **"Escolha o funcionário que vai receber."**
- **"Escreva para quem é o pagamento (ex.: INSS, FGTS, Receita Federal)."**
- **"Mês de competência inválido."**

## Como lançar uma despesa que se repete todo mês (conta recorrente)?
Palavras-chave: conta recorrente, despesa fixa, aluguel todo mes, repetir conta, mensal, recorrencia, 12 meses, conta fixa

1. Abra **Financeiro → Contas a Pagar → Nova Conta** e preencha como uma conta normal.
2. Na seção **Recorrência**, ligue **Conta Recorrente**.
3. Em **Repete a cada**, escolha **Mensal**, **Bimestral**, **Trimestral**, **Semestral** ou **Anual**.
4. Salve.

O sistema lança de uma vez os **próximos 12 vencimentos**, todos com o mesmo valor (no anual, 12 anos; no trimestral, 12 trimestres). Eles já aparecem no Fluxo de Caixa como programados. Vencimento no dia 31 cai no último dia dos meses mais curtos.

Quando o valor de um mês mudar (guia de imposto, conta de luz), ajuste só aquela conta em **Editar** ou informe o valor certo na hora de **Dar baixa**.

Se o valor mudou de vez (aluguel reajustado, salário novo), edite a conta e marque **"Aplicar também nas próximas N contas pendentes desta série"**: o novo valor vai para as seguintes que ainda não foram pagas.

O sistema não cria sozinho o 13º lançamento: quando os 12 estiverem acabando, lance a recorrência de novo.

## Como editar uma conta a pagar?
Palavras-chave: editar conta a pagar, mudar valor despesa, alterar vencimento, corrigir conta, reajuste, editar parcela

Perfis: Dono/Gestor, Administrador e Financeiro.

1. Em **Financeiro → Contas a Pagar**, clique no lápis (**Editar**) da conta, ou abra os detalhes e clique em **Editar**.
2. Ajuste valor, vencimento, favorecido, competência, plano de contas, centro de custo, forma ou observações.
3. Se a conta faz parte de uma série (parcelas ou recorrência), aparece a opção **"Aplicar também nas próximas N contas pendentes desta série"**. Marque se o ajuste vale daqui para a frente. Vencimento e competência das próximas não mudam; só valor, favorecido, descrição, classificação e forma.
4. Clique em **Salvar**.

**Só dá para editar conta pendente.** Se a conta já foi paga, aparece: **"Só dá para editar conta pendente. Para corrigir uma conta paga, estorne a baixa primeiro."** Nesse caso:
1. Abra os detalhes da conta paga.
2. Clique em **Estornar baixa** (ela volta a pendente).
3. Edite.
4. Dê baixa de novo com o valor e a data certos.

## Como dar baixa (pagar) numa conta a pagar?
Palavras-chave: dar baixa conta a pagar, paguei, quitar despesa, marcar como paga, baixa com juros, valor diferente, pagar conta

Perfis: Dono/Gestor, Administrador e Financeiro.

1. Em **Financeiro → Contas a Pagar**, clique no botão verde (**Dar baixa**) da conta pendente. Ou abra os detalhes e clique em **Dar baixa**.
2. Na janela **Dar baixa**, confira ou mude:
   - **Valor pago**: já vem com o valor lançado. Se pagou com juros ou desconto, digite o que pagou de fato. O sistema avisa que o valor é diferente e guarda o que foi pago.
   - **Pago em**: a data do pagamento.
   - **Forma de pagamento**: Boleto, PIX, Transferência, Débito Automático, Dinheiro ou "Não informar".
3. Clique em **Confirmar pagamento**.

Mensagens:
- **"A data do pagamento não pode ser no futuro. Para programar, deixe a conta pendente com o vencimento certo."**
- **"Informe o valor que foi pago."**
- **"Esta conta não está pendente."** — ela já foi paga.

A baixa não tira dinheiro do caixa da loja; é controle do financeiro. Para registrar dinheiro saindo da gaveta, use a sangria do caixa.

## Como estornar (desfazer) a baixa de uma conta a pagar?
Palavras-chave: estornar baixa, desfazer pagamento, paguei errado, voltar para pendente, cancelar baixa, estorno conta pagar

Perfis: Dono/Gestor, Administrador e Financeiro.

1. Abra **Financeiro → Contas a Pagar** e filtre **Status: Paga** se precisar.
2. Clique no olho (**Detalhes**) da conta.
3. Clique em **Estornar baixa**.
4. Confirme: "Estornar a baixa? A conta volta para pendente e sai do que foi pago."

A conta volta a **Pendente**, sem valor pago e sem data de pagamento. Depois você pode editar e dar baixa de novo.

Mensagem: **"Só dá para estornar conta que está paga."** — a conta não está paga.

Em Contas a Receber não existe estorno de baixa pela tela.

## Como excluir uma conta a pagar ou a receber?
Palavras-chave: excluir conta, apagar despesa, deletar conta, remover lancamento, lixeira financeiro

Perfis: Dono/Gestor, Administrador e Financeiro.

1. Abra **Financeiro → Contas a Pagar** (ou **Contas a Receber**).
2. Na linha da conta **pendente**, clique na lixeira (**Excluir**).
3. Confirme "Confirma a exclusão desta conta?".

O botão de excluir só aparece em conta pendente. Conta paga não tem lixeira: em Contas a Pagar, estorne a baixa primeiro e depois exclua.

Cada parcela é uma conta separada. Para apagar todas as parcelas de um lançamento, exclua uma por uma.

Excluir uma conta lançada pela **Folha de Pagamento** e lançar a folha de novo faz a conta voltar. Para parar de lançar, desligue o funcionário ou o item na própria folha.

Vendas do PDV geram contas a receber já pagas: elas não têm lixeira. Para desfazer, cancele a venda.

## Como filtrar e exportar as Contas a Pagar?
Palavras-chave: filtrar contas a pagar, exportar despesas, planilha contas, excel contas pagar, vencidas pagar, pago no mes

Em **Financeiro → Contas a Pagar**:
- Quadros do topo: **Total Pendente**, **Total Vencido** e **Pago no Mês**.
- Filtros: **Status** (Pendente, Paga, Vencida, Cancelada), **Fornecedor**, **Plano de contas**, **Centro de custo**, **Vencimento De** e **Vencimento Até**.
- **Vencida** mostra as pendentes com vencimento já passado.
- Conta de funcionário aparece com a etiqueta "Funcionário" embaixo do nome.

Para baixar em planilha, clique em **Exportar** (gera arquivo Excel). O mesmo botão existe em Contas a Receber.

Quando você chega pela Folha de Pagamento (botão de ver o mês), aparece a faixa azul "Mostrando só as contas lançadas pela folha do mês de competência ...". Para ver tudo de novo, use **Limpar** ou abra o menu de novo.

As listas e os totais mostram as contas de todas as lojas da empresa.

## Como criar o plano de contas padrão?
Palavras-chave: plano de contas padrao, criar contas padrao, classificacao despesas, categorias financeiras, plano de contas vazio

Perfis: Dono/Gestor, Administrador e Financeiro. O menu Gestão (onde fica o Plano de Contas) só aparece para Dono, Administrador e Gerente.

Jeito mais rápido:
1. Abra **Gestão → Plano de Contas**.
2. Clique em **Criar contas padrão** e confirme.

Também dá pela tela **Nova Conta a Pagar**: quando a empresa ainda não tem plano de contas, aparece o quadro **Plano de contas** com o botão **Criar contas padrão** (o que você já tinha digitado na tela precisa ser preenchido de novo).

O padrão cria grupos e contas de salários, vale, 13º, férias, INSS, FGTS, vale-transporte, aluguel, energia e outras despesas fixas. As contas que você já tem não mudam. Se clicar de novo, aparece "O plano de contas padrão já estava todo criado — nada foi alterado."

Depois de criado, escolha a conta do plano em cada conta a pagar e em cada item da folha. É isso que separa salários, encargos e benefícios no DRE.

## Como cadastrar uma conta no plano de contas?
Palavras-chave: nova conta plano de contas, sintetica, analitica, conta pai, codigo conta, grupo de contas, tipo custo despesa receita

1. Abra **Gestão → Plano de Contas → Nova Conta**.
2. **Conta Pai**: escolha o grupo onde ela fica, ou "Nenhuma (Conta Raiz)".
3. **Código**: use pontos para mostrar a hierarquia (ex.: 1, 1.1, 1.1.1). Não pode repetir.
4. **Nome**.
5. **Tipo**: **Receita**, **Despesa** ou **Custo**.
6. **Natureza**: **Sintética (Grupo)** — só agrupa, não recebe lançamento; ou **Analítica (Lançável)** — é a que aparece para escolher nas contas.
7. Salve.

Importante:
- Só conta **Analítica** aparece na lista de plano de contas das Contas a Pagar.
- No DRE, conta do tipo **Custo** entra em "Custo das Mercadorias Vendidas (CMV)"; **Despesa** entra em "Despesas Operacionais".
- Grupo cujo nome começa com "Pessoal" é somado em "Despesas com Pessoal".

Mensagens:
- **"Este codigo ja existe para esta empresa."** — use outro código.
- **"Nao e possivel excluir conta com subcontas vinculadas."** — apague ou mova as subcontas antes.
- **"Nao e possivel excluir conta com movimentacoes vinculadas."** — a conta já foi usada.

## Como cadastrar centros de custo?
Palavras-chave: centro de custo, centros de custo, setor, departamento, rateio, custo por loja, cadastrar centro

Centro de custo serve para saber quanto cada área gasta (ex.: Loja Centro, Administrativo, Entregas).

1. Abra **Gestão → Centros de Custo → Novo Centro**.
2. Preencha **Código** (não pode repetir), **Nome** e, se quiser, **Descrição**.
3. Salve.

Depois, escolha o centro de custo em cada conta a pagar, na ficha de salário do funcionário (Folha) e nos itens da folha.

Mensagens:
- **"Este codigo ja existe para esta empresa."** — use outro código.
- **"Nao e possivel excluir centro de custo com movimentacoes vinculadas."** — ele já foi usado em contas.

Para ver quanto cada centro gastou, use **Financeiro → Despesas por Centro de Custo**.

O menu Gestão aparece para Dono, Administrador e Gerente. O perfil Financeiro não vê o menu Gestão, mas chega ao cadastro pelo link **"Cadastre os centros de custo"**, que fica embaixo do campo Centro de custo na tela de Nova Conta a Pagar.

## O que é e quem vê a Folha de Pagamento?
Palavras-chave: folha de pagamento, salarios, folha, holerite, pagar funcionarios, folha nao aparece, rh

A **Folha de Pagamento** (menu **Financeiro → Folha de Pagamento**) organiza salários, vales, guias e benefícios e **lança tudo no Contas a Pagar**. Ela **não paga nada** sozinha: é no Contas a Pagar que você ajusta o valor do mês e dá a baixa. O Fluxo de Caixa e o DRE leem o Contas a Pagar.

**Quem vê:** só **Dono/Gestor** e **Financeiro**. O gerente não vê esse item no menu. Se outro perfil tentar abrir, aparece "A folha de pagamento é restrita ao dono e ao financeiro."

**O sistema não calcula INSS, IRRF, FGTS nem descontos.** O salário que você digita é o valor que a empresa paga ao funcionário (líquido). As guias (INSS, FGTS, IRRF) você cadastra como itens com o valor que o contador passar.

A tela tem três partes:
1. **Funcionários — salário e vale**: a ficha de cada funcionário.
2. **Guias e benefícios — valor que você digita**: INSS, FGTS, vale-transporte etc.
3. **Lançar no Contas a Pagar**: escolhe os meses e lança.

No topo: **Salários por mês**, **Guias e benefícios por mês** e **Folha de (mês)**.

Os funcionários que aparecem são os cadastrados em **Cadastros → Funcionários**.

## Como preencher o salário e o vale do funcionário na folha?
Palavras-chave: ficha funcionario folha, salario funcionario, vale adiantamento, dia do pagamento, admissao, demissao, quinzena

1. Abra **Financeiro → Folha de Pagamento**.
2. No quadro **1. Funcionários — salário e vale**, clique no botão de editar ao lado do funcionário.
3. Na tela **Salário e vale — (nome)** preencha:
   - **Contrato**: **Cargo**, **Admissão** e **Desligamento**. A folha só lança os meses entre a admissão e o desligamento (em branco = sem limite).
   - **Salário**: **Salário do mês** (o valor pago, líquido) e **Dia do pagamento**. Em **Paga quando**, ligue **No mês seguinte ao trabalhado** se o salário de outubro vence em novembro.
   - **Vale (adiantamento)**: **Valor do vale** (zero = sem vale) e **Dia do vale**. O vale vence no próprio mês e é descontado do salário: a folha lança o vale e, no dia do pagamento, só o que falta.
   - **Onde a despesa entra**: **Loja** e **Centro de custo**.
   - **Entra na folha**: desligue para parar de lançar o funcionário sem apagar os dados.
4. Clique em **Salvar**.

Mensagens: **"O vale não pode ser maior que o salário."** e **"A data de desligamento não pode ser antes da admissão."**

Salvar a ficha **não muda as contas já lançadas**. Se o salário mudou, lance a folha de novo marcando **Atualizar o valor das contas pendentes já lançadas**.

## Como cadastrar guias e benefícios na folha (INSS, FGTS, vale-transporte)?
Palavras-chave: guia inss, fgts, irrf, vale transporte, vale refeicao, plano de saude, encargos, beneficios, item da folha

1. Abra **Financeiro → Folha de Pagamento**.
2. No quadro **2. Guias e benefícios**, clique para incluir um novo item (tela **Nova guia ou benefício**).
3. Preencha:
   - **Tipo**: Encargo / imposto, Benefício ou Outro.
   - **Nome**: ex.: Guia INSS, Guia FGTS, IRRF sobre salários, Vale-transporte.
   - **De quem é**: "Da empresa (uma conta só por mês)" ou um funcionário.
   - **Paga para**: em branco, paga ao próprio funcionário (ou usa o nome do item, se for da empresa).
   - **Valor por mês**: o valor de costume (o do mês você ajusta no Contas a Pagar).
   - **Dia do vencimento** e **Vence quando**: ligado = INSS/FGTS de outubro vencem em novembro.
   - **Plano de contas**, **Centro de custo** e **Loja**.
   - **Ativo (entra na folha)**.
4. Clique em **Salvar**.

O sistema **não calcula** o valor das guias. Use o valor que o contador informar.

Excluir um item não apaga as contas que ele já lançou: elas continuam no Contas a Pagar.

## Como lançar a folha no Contas a Pagar?
Palavras-chave: lancar folha, gerar contas folha, folha do mes, lancar salarios, atualizar pendentes, folha duplicada

1. Abra **Financeiro → Folha de Pagamento**.
2. No quadro **3. Lançar no Contas a Pagar**, escolha **A partir do mês** e **Quantos meses**. Clique em **Ver período** para conferir a prévia (Previsto, Contas, Já lançado, Pago e Situação de cada mês).
3. Se você mudou um salário ou o valor de uma guia, marque **Atualizar o valor das contas pendentes já lançadas**. Atenção: isso sobrescreve ajustes feitos à mão em contas pendentes desse período. Conta paga nunca muda.
4. Clique em **Lançar (mês) no Contas a Pagar** e confirme.

O que já foi lançado **não é duplicado**: pode lançar de novo sem medo.

Mensagem: **"Nada para lançar: preencha o salário de pelo menos um funcionário ou cadastre uma guia/benefício."**

Depois de lançar, o botão de ver ao lado de cada mês abre o Contas a Pagar filtrado com as contas daquele mês. Para ajustar um mês diferente (falta, hora extra), edite só aquela conta no Contas a Pagar ou informe o valor certo na baixa.

## Como ver o DRE (resultado da empresa)?
Palavras-chave: dre, demonstrativo de resultado, lucro, prejuizo, resultado liquido, margem, lucro bruto, balanco

Perfis: Dono/Gestor, Administrador, Financeiro, Gerente e Consulta (só ver). O DRE precisa estar incluído no plano da empresa.

1. Abra **Relatórios → DRE**.
2. Escolha **Data Início** e **Data Fim**.
3. Em **Considerar**, escolha:
   - **O que foi pago e recebido (caixa)**: só entra o que já foi pago e recebido, na data do pagamento. Conta pendente não aparece.
   - **O mês a que pertence (competência)**: cada despesa entra no mês a que pertence, paga ou não (salário de outubro pago em novembro conta em outubro). Conta sem mês de competência entra pelo vencimento. As receitas entram na data em que foram lançadas.
4. Em **Unidade**, escolha uma loja ou **Todas**.
5. Veja os quadros **Resultado Líquido**, **Receita Bruta**, **Despesas com Pessoal** e **Margem Líquida**, e a tabela com o **Período Anterior** para comparar.

Botões: **Por Unidade** (uma coluna por loja, lado a lado) e **Exportar CSV** (planilha).

De onde vêm os números: a **receita** vem das Contas a Receber; os **custos e despesas** vêm das Contas a Pagar, separados pelo plano de contas.

## Por que o DRE mostra números estranhos (CMV zerado, despesas sem classificação)?
Palavras-chave: dre errado, cmv zero, despesas sem classificacao, dre nao bate, lucro errado, receita errada dre

Situações comuns que parecem defeito, mas são como o DRE funciona:

- **CMV zerado:** o custo das mercadorias **não** vem do custo dos produtos vendidos. Ele soma só as contas a pagar classificadas numa conta do plano de contas do tipo **Custo** (ex.: compras de mercadoria). Classifique as compras de mercadoria assim.
- **"Outras Despesas (sem classificacao)" alta:** são contas a pagar sem plano de contas. Edite as pendentes e escolha o plano de contas; para as pagas, estorne a baixa, edite e baixe de novo.
- **Deduções e Resultado Financeiro sempre zero:** o sistema ainda não calcula impostos sobre venda nem juros nessas linhas.
- **Receita menor do que o vendido (regime caixa):** vendas no cartão com taxa cadastrada e vendas direto com a loja só entram quando a conta a receber é baixada.
- **Taxa da maquininha não aparece como despesa:** o sistema não lança a taxa como despesa. Se quiser vê-la no DRE, lance uma conta a pagar.
- **Despesas com Pessoal:** soma as contas lançadas pela Folha, as pagas a um funcionário e as classificadas num grupo do plano de contas que começa com "Pessoal".
- **Relatório Financeiro diferente do DRE:** os dois calculam o custo de jeitos diferentes. Para análise de resultado, use o DRE.

## Como ver o Fluxo de Caixa (entradas, saídas e programado)?
Palavras-chave: fluxo de caixa, entradas e saidas, saldo do periodo, previsto, programado, contas a vencer, projeção

1. Abra **Financeiro → Fluxo de Caixa**.
2. Escolha **Data Início** e **Data Fim** (o padrão é o mês atual).
3. Os quadros mostram **Entradas Realizadas** (contas a receber recebidas), **Saídas Realizadas** (contas a pagar pagas), **Saldo do Período** e **Previsto (Pendente)**.
4. O gráfico **Fluxo Diário** pode ser visto em **Barras** ou **Linha**.
5. O quadro **Programado — a pagar no período** lista as contas a pagar pendentes que vencem no período, com o valor acumulado. Conta atrasada aparece com "em atraso".
6. O **Detalhamento Diário** mostra dia a dia entradas, saídas e saldo acumulado.

Filtros extras: **Saídas do plano de contas** e **Saídas do centro de custo**. Eles filtram **só as saídas**. Quando usados, aparece o aviso: "As saídas (pagas e programadas) estão filtradas. As entradas continuam todas, então o saldo abaixo não é o saldo da empresa." Isso é esperado.

O Fluxo de Caixa lê as Contas a Receber e a Pagar; ele não é o saldo da gaveta do caixa nem o saldo do banco.

## Como ver as despesas por centro de custo?
Palavras-chave: despesas por centro de custo, quanto cada setor gasta, gasto por loja, custo por departamento, relatorio centro de custo

1. Abra **Financeiro → Despesas por Centro de Custo**.
2. Escolha **Data Início** e **Data Fim**.
3. Em **Considerar**, escolha:
   - **O que foi pago no período**;
   - **O mês a que a despesa pertence (pago ou não)**;
   - **O que vence no período (pago ou não)**.
4. Em **Loja**, escolha uma ou **Todas**.
5. Os quadros mostram **Total do período**, **Pessoal**, **Outras despesas e custos** e **Já pago**. A tabela mostra, por centro de custo: Pessoal, Outras despesas, Custos, Total, % do total e Já pago.

Este relatório só lê o Contas a Pagar. Pessoal = contas lançadas pela folha, pagas a funcionário ou classificadas nos grupos "Pessoal" do plano de contas.

Se aparecer "Nenhuma despesa no período", troque o período ou o "Considerar": por pagamento só entra o que já foi pago.

O botão no topo leva ao DRE do mesmo período. Esta tela mostra salários, por isso fica no Financeiro (vendedor e caixa não veem).

## Como conciliar o extrato do banco por arquivo OFX?
Palavras-chave: conciliacao bancaria, ofx, extrato, importar extrato, conferir banco, conciliar, auto conciliar

Perfis: Dono/Gestor, Administrador e Financeiro. A conciliação precisa estar no plano da empresa.

1. No internet banking, exporte o extrato em formato **OFX**.
2. Abra **Financeiro → Conciliação → Importar OFX**.
3. Preencha **Banco**, escolha o **Arquivo OFX** (até 5 MB) e clique em **Importar Extrato**.
4. Abra a conciliação criada (ícone de ver).
5. Clique em **Auto-Conciliar**: o sistema liga cada lançamento do banco a uma conta pendente de **mesmo valor** com vencimento até 5 dias antes ou depois.
6. Para os que sobraram, clique em **Conciliar** na linha e escolha a conta a receber ou a pagar.
7. No fim, clique em **Finalizar**.

**Importante:** conciliar aqui **só liga** o lançamento do banco à conta. **Não dá baixa** na conta. Depois de conciliar, dê baixa nas contas em Contas a Receber e Contas a Pagar.

Só aparecem contas **pendentes** para ligar. Vendas à vista do PDV já entram como pagas e não aparecem.

Mensagens: **"Nenhuma transacao encontrada no arquivo OFX."** (arquivo vazio ou fora do padrão OFX; exporte de novo) e **"Selecione uma conta para conciliar."**

## O que é o Open Finance e por que não aparece para mim?
Palavras-chave: open finance, ligar banco, extrato automatico, conta bancaria conectada, open finance sumiu, nao tem open finance

O **Open Finance** (menu **Financeiro → Open Finance**) traz o extrato do banco sozinho para o sistema, várias vezes por dia, e sugere qual conta a receber ou a pagar é cada lançamento. Você só confere e confirma; a baixa sai com a data e o valor do banco.

Ele **só aparece quando a IA365 libera para a sua empresa**. Se o item não está no menu, a função não está ligada para você. Se a tela abrir com o texto "Esta função não faz parte do seu plano atual. Para ligar, fale com a IA365.", é a mesma situação: fale com a IA365.

O plano define quantas contas bancárias podem ser ligadas (o topo da tela mostra "X de Y contas usadas"). Ao passar do limite aparece: **"Você já conectou todas as contas do seu plano (N). Para ligar mais uma, fale com a IA365."**

Perfis: Dono/Gestor, Administrador e Financeiro fazem tudo; Gerente só vê.

## Como ligar uma conta do banco no Open Finance?
Palavras-chave: ligar conta banco, conectar banco, autorizar banco, open finance conectar, ja autorizei, buscar extrato

(Só para empresas com o Open Finance liberado.)

1. Abra **Financeiro → Open Finance** e clique em **Ligar uma conta**.
2. Preencha **Nome para você reconhecer**, **Banco** (escolha na lista ou digite os 3 números), **Agência** e **Dígito**, **Conta** e **Dígito**, e o **CPF ou CNPJ do dono da conta**.
3. Salve. Aparece: "Conta cadastrada. Agora clique em "Conectar ao banco" e autorize — depois disso o extrato começa a chegar (pode levar até 24 horas)."
4. Clique em **Conectar ao banco**. Abre a página do seu banco: autorize lá dentro.
5. Volte e clique em **Já autorizei**. Depois de conectada, o botão vira **Buscar extrato**.

Mensagens do cadastro:
- **"Informe o CPF (11 números) ou o CNPJ (14 números) do dono da conta."**
- **"Na agência, só números (o dígito vai no campo ao lado)."** e o mesmo para a conta.
- **"Esta conta já está cadastrada."**

Nas ações da conta: **Buscar cartões desta conta** (para trazer compras do cartão de crédito, ligando **Trazer as compras**) e **Desconectar do banco** (a vaga fica livre; o que já foi conciliado continua).

O extrato pode levar até 24 horas para começar a chegar. Isso é normal.

## Como conferir e conciliar os lançamentos do Open Finance?
Palavras-chave: conferir lancamentos, sugestao conciliacao, confirmar certos, lancar despesa banco, nao e isso, ignorar lancamento, desfazer conciliacao

A tela **Financeiro → Open Finance** separa os lançamentos em abas: **Conferir** (com sugestão), **Sem sugestão**, **Conciliados** e **Ignorados**. Dá para ver só **Entradas** ou só **Saídas**, e escolher a conta ou o cartão.

O que fazer em cada lançamento:
- **Confirmar**: aceita a sugestão. A conta é baixada com a data e o valor do banco. A etiqueta **Certo** indica sugestão de alta confiança; **Conferir** pede atenção.
- **Confirmar os N certos**: confirma de uma vez todos os marcados como certos.
- **Não é isso**: tira a sugestão; o lançamento vai para "Sem sugestão".
- **Ligar a uma conta já cadastrada**: escolha a conta a receber ou a pagar em aberto.
- **Lançar** (saída sem conta): abre **Lançar como despesa** — descrição, favorecido (pode cadastrar como fornecedor na hora), conta do plano de contas e centro de custo (pode criar novos ali). Marque **Lembrar esta classificação** para os próximos pagamentos ao mesmo favorecido. A despesa entra no Contas a Pagar já paga.
- **Lançar N despesas conhecidas**: lança de uma vez as que o sistema já sabe classificar.
- **É pagamento de fatura do (cartão)**: não entra como despesa, porque as compras do cartão já entram uma a uma.
- **Ignorar**: para o que não é conta a pagar nem a receber.
- Na aba **Conciliados**, **Desfazer a conciliação** devolve a conta para "em aberto".

## O que funciona na tela de Boletos?
Palavras-chave: boleto, gerar boleto, carne, boleto bancario, linha digitavel, nosso numero, imprimir boleto, registrar boleto

Perfis: Dono/Gestor, Administrador e Financeiro. Precisa estar no plano da empresa.

A tela **Financeiro → Boletos** é um **controle interno** de cobranças. Hoje ela **não registra boleto em banco**: não gera código de barras, linha digitável nem nosso número (esses campos ficam com "-"), e não tem boleto para imprimir ou mandar ao cliente. Para cobrar por boleto de verdade, emita o boleto no seu banco e use esta tela (ou as Contas a Receber) para controlar.

O que dá para fazer:
1. **Gerar Carne**: escolha o **Cliente**, **Descrição**, **Valor Total**, **Parcelas** (2 a 48) e **Primeiro Vencimento**. O sistema cria uma conta a receber e um registro de boleto para cada parcela, uma por mês.
2. **Baixar** (ícone de certo, "Marcar como pago?"): marca o boleto como pago e também marca como paga a conta a receber ligada, com o valor cheio e a data de hoje.
3. **Cancelar** ("Cancelar este boleto?"): cancela só o registro do boleto. A conta a receber ligada **continua pendente** — exclua-a em Contas a Receber se a cobrança não vale mais.

Filtros: Status, Cliente, Vencimento De/Até. O quadro **Total Vencido** fica zerado, porque o sistema não muda o boleto para "Vencido" sozinho; veja os atrasados pelo filtro de vencimento.

## Como usar Contratos (cobrança recorrente de clientes)?
Palavras-chave: contrato, cobranca recorrente, mensalidade cliente, assinatura cliente, faturar contrato, recorrencia receber

Perfis: Dono/Gestor, Administrador e Financeiro. Precisa estar no plano da empresa.

Para cobrar um cliente todo mês (mensalidade, manutenção, aluguel de equipamento):
1. Abra **Financeiro → Contratos** e crie um contrato (**Novo Contrato**).
2. Preencha **Cliente**, **Descrição**, **Valor**, **Periodicidade** (Mensal, Trimestral, Semestral ou Anual), **Data Início**, **Data Fim** (opcional) e **Observações**.
3. Salve. O próximo faturamento começa na data de início.

Para cobrar:
1. Na lista ou dentro do contrato, clique em **Faturar** e confirme "Gerar faturamento?".
2. O sistema cria uma **conta a receber** e um registro de **boleto** com vencimento no "Prox. Faturamento" e avança a próxima data conforme a periodicidade.

**O faturamento não é automático:** é preciso clicar em **Faturar** a cada período. Use o quadro **Vencendo em 30 dias** e a coluna **Prox. Faturamento** para lembrar. O boleto criado aqui tem as mesmas limitações da tela de Boletos (não é registrado no banco).

Mensagem: **"Apenas contratos ativos podem ser faturados."** — o contrato está Vencido, Cancelado ou Suspenso. Edite e mude o **Status** para Ativo, se for o caso.

O status do contrato não muda sozinho quando chega a Data Fim; altere em Editar.

## Como funcionam as comissões dos vendedores?
Palavras-chave: comissao, comissoes, percentual vendedor, comissao vendedor, quanto o vendedor ganhou, comissao nao gera

A comissão nasce sozinha em cada venda (PDV e venda balcão), calculada sobre o total da venda com o **percentual do cadastro do vendedor**.

Para definir o percentual:
1. Abra **Cadastros → Funcionários** e edite o vendedor.
2. No passo de comissão, preencha **Comissão (%)**.
3. Salve.

Vendedor **sem percentual (ou 0%) não gera comissão**. O percentual vale para as vendas feitas depois de salvo; as antigas não mudam.

Para ver as comissões: **Relatórios → Comissões**. Quadros **Pendentes**, **Pagas no Mês** e **Total no Mês**; filtros por **Vendedor**, **Loja**, **Status**, **De** e **Até**. O botão **Relatório** agrupa por vendedor no período.

Atenção — parece defeito: a tela **Comissões → Configurar** tem percentuais por vendedor, por categoria e por produto, mas **esses valores não são usados no cálculo** hoje. O que vale é o **Comissão (%)** do cadastro do funcionário.

Se a venda trocar de vendedor (correção feita por gerente ou dono), a comissão acompanha. Depois de paga, a comissão trava a troca.

## Como pagar as comissões?
Palavras-chave: pagar comissao, comissao paga, quitar comissao, pagar vendedores, baixar comissao

Perfis: Dono/Gestor, Administrador e Financeiro.

1. Abra **Relatórios → Comissões**.
2. Filtre por **Vendedor**, **Loja**, **Status: Pendente** e período.
3. Marque as comissões que vai pagar (caixa de seleção na primeira coluna).
4. Clique em **Pagar Selecionadas** e confirme "Confirma o pagamento das comissões selecionadas?".

O que acontece:
- As comissões marcadas passam para **Paga**.
- Para cada vendedor, o sistema cria uma conta no **Contas a Pagar** já paga, com a descrição "Pagamento comissoes - (nome)", categoria "Comissoes", forma Transferência e data de hoje.

Se o pagamento foi em outra forma ou outra data, ajuste essa conta em Contas a Pagar (estorne a baixa, edite e dê baixa de novo).

Não há botão para voltar uma comissão paga para pendente.

## Por que o vendedor consegue ver as comissões de todo mundo?
Palavras-chave: vendedor ve comissao dos outros, privacidade comissao, esconder comissao, comissao de outro vendedor

Hoje, a tela **Relatórios → Comissões** abre para todos os perfis que vendem (vendedor e operador de caixa também) e mostra as comissões de **todos os vendedores** da empresa, com filtro por vendedor. Não existe opção para cada vendedor ver só a própria.

O que dá para fazer:
- **Pagar** e **Configurar** continuam restritos ao Dono e ao Financeiro.
- Se a empresa quiser que o vendedor só opere o PDV, o dono ou o gerente pode ligar **Vendedor opera somente o PDV** em **Gestão → Configurações da Loja**. Nesse modo o vendedor não vê as telas fora do PDV.

Se isso for um problema para a sua loja, registre o pedido com o suporte da IA365.

## Como ver quem fez o quê no sistema (Auditoria)?
Palavras-chave: auditoria, historico de alteracoes, quem alterou, quem apagou, log, rastrear usuario, quem mudou preco

Perfis: **Dono/Gestor** e Administrador.

1. Abra **Gestão → Auditoria**.
2. Use os filtros:
   - **Tipo**: o tipo de registro (produto, cliente, venda etc.).
   - **Evento**: **Criado**, **Alterado**, **Excluído** ou **Restaurado**.
   - **Usuário**: quem fez.
   - **De** e **Até**: o período.
3. Clique em **Filtrar**.
4. A lista mostra **Quando**, **Usuário**, **Tipo**, **Evento** e **Descrição**. Clique em **Detalhes** para ver campo por campo o **Antes** e o **Depois**.

A lista mostra 30 registros por página.

Se aparecer "Nenhum registro de auditoria", troque o período ou os filtros.

## Quais relatórios existem e quem pode ver?
Palavras-chave: relatorios, menu relatorios, lista de relatorios, onde ficam os relatorios, relatorio nao abre

No menu **Relatórios**:
- **Vendas**: faturamento, ticket médio, por forma, por vendedor, por loja, top produtos e clientes.
- **Produtos vendidos**: item a item, com loja da venda e loja de onde saiu.
- **Estoque**: posição de estoque e curva ABC.
- **Entradas e saídas**: movimentações do estoque no período (só para quem vê o módulo Estoque).
- **Contagem cega**: folha para contar o estoque sem ver o saldo.
- **Financeiro**: receitas, despesas e contas pendentes por faixa de vencimento.
- **DRE**: resultado (Dono, Administrador, Financeiro, Gerente e Consulta).
- **Comissões**.

Os relatórios em geral abrem para todos os perfis. Vendedor e operador de caixa veem a loja em que estão; Dono e Administrador podem marcar várias lojas; o Gerente marca só as lojas a que está vinculado.

Exportar: **Entradas e saídas** tem **Excel** e **PDF / Imprimir**; **Produtos vendidos** tem **Planilha** e **Imprimir**; **Vendas**, **Estoque**, **Financeiro** e **Contagem cega** têm **Imprimir** (na janela de impressão escolha "Salvar como PDF" para gerar o arquivo). O DRE tem **Exportar CSV**.

## Como usar o Relatório de Vendas?
Palavras-chave: relatorio de vendas, faturamento, vendas do mes, ticket medio, vendas por vendedor, vendas por forma de pagamento, vendas por loja

1. Abra **Relatórios → Vendas**.
2. Escolha **Data Início** e **Data Fim**.
3. Filtros opcionais: **Origem** (vendas PDV/balcão, pedidos faturados...), **Forma de pagamento**, **Vendedor** e **Cliente**.
4. Se você é Dono, Administrador ou Gerente com várias lojas, marque as lojas nas "pílulas" (botões **Marcar todas** e **Limpar**).
5. Clique em filtrar.

Indicadores: **Vendas**, **Peças vendidas**, **Faturamento**, **Ticket médio por venda**, **Valor bruto dos itens**, **Descontos concedidos**, **Acréscimos (cartão / juros)** e **Trocas / Devoluções**.

Quadros: **Por forma de pagamento**, **Por vendedor**, **Por loja**, **Top 10 Produtos**, **Top 10 Clientes** e **Vendas no Período**.

Para PDF ou papel, clique em **Imprimir**; o impresso sai com o cabeçalho da empresa e os filtros usados. Este relatório não tem botão de Excel; para planilha de itens, use **Produtos vendidos → Planilha**.

Vendedor e operador de caixa veem só a loja em que estão no momento.

## Como usar o relatório de Produtos vendidos?
Palavras-chave: produtos vendidos, relatorio de produtos, itens vendidos, quanto vendeu de cada produto, mais vendidos, planilha itens

1. Abra **Relatórios → Produtos vendidos**.
2. Escolha **Data Início** e **Data Fim**.
3. Filtros: **Origem**, **Forma de pagamento**, **Vendedor**, **Categoria**, **Produto** e **Cliente**.
4. Marque as **Lojas da venda** e as lojas de onde o produto **saiu** (podem ser diferentes quando a venda é numa loja e o produto sai de outra).
5. Filtre.

Indicadores: **Peças vendidas**, **Total recebido pelos itens**, **Valor bruto (preço × qtd)** e **Vendas com esses itens**.

Quadros: **Por forma de pagamento**, **Por loja de onde saiu**, **Resumo por produto** e **Item a item** (data, venda, loja da venda, saiu de).

Botões: **Planilha** (baixa em Excel com tudo, item a item) e **Imprimir**.

## Como usar o relatório de Entradas e saídas do estoque?
Palavras-chave: entradas e saidas, movimentacao estoque relatorio, o que entrou no estoque, o que saiu, kardex, historico estoque

Quem vê: quem tem acesso ao módulo Estoque (Dono, Administrador, Gerente, Vendedor e Consulta). O operador de caixa não vê.

1. Abra **Relatórios → Entradas e saídas**.
2. Escolha **De** e **Até**, ou use o **Período rápido**: Hoje, 7 dias, 30 dias, Este mês, Mês passado.
3. Em **Mostrar**, escolha **Tudo**, só o que entrou ou só o que saiu.
4. Filtros: **Produto**, **Categoria**, **Lançado por** (Venda, Pedido, Troca / devolução, Transferência entre lojas, Entrada por NF-e, Ordem de serviço, Importação de planilha, Bonificação que volta, Lançamento manual) e **Usuário**.
5. Indicadores: **Entrou no estoque**, **Saiu do estoque**, **Saldo do período (entrou − saiu)** e **Movimentações**; quadros **Por tipo** e **Por loja**.
6. Abas: **Resumo por produto** e **Movimento a movimento**.

Exportar: **Excel** baixa a aba aberta, inteira. **PDF / Imprimir** abre a aba pronta; na janela de impressão escolha **Salvar como PDF**. O PDF mostra só as primeiras linhas (a tela avisa o limite); para o relatório inteiro, use o Excel ou diminua o período.

## Como imprimir a folha de contagem cega do estoque?
Palavras-chave: contagem cega, inventario, contar estoque, balanco estoque, folha de contagem, conferencia fisica

1. Abra **Relatórios → Contagem cega**.
2. Escolha a **Loja**, a **Categoria** (ou Todas) e, se quiser, **Buscar** um produto.
3. Em **Estoques na folha**, marque quais estoques da loja entram (cada um vira uma coluna para anotar).
4. (Opcional) Marque **Só produtos com saldo** — o saldo decide se o produto entra, mas não aparece na folha.
5. Clique em **Imprimir**.

A folha sai **sem a quantidade do sistema**, de propósito: quem conta anota o que achou na prateleira, e a comparação vem depois. Isso não é defeito.

Para ter outra coluna, crie outro estoque em **Estoques da Loja** (link no aviso da tela).

## O que mostra o Relatório de Estoque?
Palavras-chave: relatorio de estoque, estoque baixo, estoque critico, curva abc, posicao de estoque, estoque minimo

1. Abra **Relatórios → Estoque**.
2. No topo: **Total de Produtos**, **Estoque OK**, **Estoque Baixo** e **Estoque Crítico**.
3. Um quadro vermelho lista os produtos abaixo do mínimo (pode recolher pela seta).
4. Abas: **Posição de Estoque** (lista dos produtos com a situação) e **Curva ABC** (gráfico com Curva A, B e C).
5. Para papel ou PDF, clique em **Imprimir**.

Como a situação é calculada:
- **Crítico**: produto com estoque mínimo cadastrado e saldo zero ou negativo.
- **Baixo**: produto com estoque mínimo cadastrado e saldo igual ou abaixo do mínimo.
- Produto **sem estoque mínimo** cadastrado não entra como baixo nem crítico. Para receber o alerta, preencha o estoque mínimo no cadastro do produto.

A tela mostra a posição na hora em que foi aberta ("Posição atualizada em...").

## O que mostra o Relatório Financeiro?
Palavras-chave: relatorio financeiro, receitas e despesas, resultado do mes, contas pendentes por vencimento, proximos 7 dias

1. Abra **Relatórios → Financeiro**.
2. Escolha **Data Início** e **Data Fim** (o padrão é o mês atual) e filtre.
3. Quadros: **Receitas** (contas a receber recebidas no período), **Despesas Totais** (contas a pagar pagas no período), **Lucro Bruto** e **Resultado Líquido**.
4. Um gráfico separa Receitas, Custos (CMV) e Despesas Operacionais.
5. Os quadros **Contas a Receber (Pendentes)** e **Contas a Pagar (Pendentes)** agrupam o que está em aberto em: Vencido, Próximos 7 dias, Próximos 30 dias e Acima de 30 dias.
6. Para papel ou PDF, use **Imprimir**.

Atenção:
- Os números somam **todas as lojas** da empresa.
- O "custo" daqui só considera contas a pagar com a categoria antiga de custo. Por isso o Lucro Bruto daqui pode ser diferente do DRE. Para análise de resultado, prefira o **DRE**.
- Este relatório abre para todos os perfis que veem Relatórios, inclusive vendedor e caixa.

## Onde vejo o meu plano e os limites (lojas, usuários, produtos)?
Palavras-chave: meu plano, plano atual, limite do plano, quantos usuarios posso, upgrade, mudar de plano, comparar planos

Só o **Dono/Gestor** vê **Meu Plano** (menu lateral e menu do seu nome no topo).

Na tela:
- **Plano Atual**: nome, **Valor Mensal**, **Tipo de Cobrança** e **Status** (Trial com os dias restantes, Ativo, ou Expirado).
- **Uso do Plano**: barras de **Unidades**, **Usuarios**, **Produtos** e **Notas Fiscais (mes)** — quanto usa e quanto o plano permite.
- **Funcionalidades do Plano**: PDV, Fiscal, Multilojas, Ordens de Serviço, Contratos, Conciliação, DRE, Boletos e API Externa.
- **Comparar Planos**: mostra os planos lado a lado (mensal ou anual). Para mudar de plano, use o contato da IA365 que aparece no plano escolhido.

Mensagens de limite:
- **"Sua empresa atingiu o limite de lojas do plano atual. Fale com a IA365 para ampliar."**
- **"Você chegou ao limite de usuários do seu plano. Para cadastrar mais, mude de plano em Meu Plano ou fale com a nossa equipe."**
- **"Você chegou ao limite de produtos do seu plano. Para cadastrar mais, mude de plano em Meu Plano ou fale com a nossa equipe."**

Os limites de usuários e produtos só travam o cadastro nas empresas que contrataram pelo site. Ao chegar no limite, inative quem não usa mais ou fale com a IA365.

## "Seu plano nao inclui esta funcionalidade" — o que fazer?
Palavras-chave: seu plano nao inclui, faca upgrade, funcionalidade bloqueada, dre bloqueado, boleto bloqueado, contratos bloqueado, conciliacao bloqueada

Mensagem: **"Seu plano nao inclui esta funcionalidade. Faca upgrade para acessar."**

Causa: a tela pertence a uma função que não faz parte do plano da empresa. Isso vale para: **Fiscal** (notas), **Ordens de Serviço**, **Contratos**, **Conciliação**, **DRE**, **Boletos** e **Multilojas**.

O menu continua mostrando esses itens mesmo quando o plano não os inclui: a pessoa clica e recebe a mensagem. Não é defeito da tela.

O que fazer:
1. O dono abre **Meu Plano** e confere em **Funcionalidades do Plano** o que está incluído.
2. Para incluir a função, use **Comparar Planos** e fale com a IA365 pelo contato que aparece lá.

Se a tela levar para "Seu período de avaliação terminou", o teste grátis ou a assinatura venceu — veja o tópico sobre teste grátis.

## Teste grátis: quanto dura e o que acontece quando acaba?
Palavras-chave: teste gratis, periodo de avaliacao, trial, teste acabou, dias restantes, avaliacao terminou, sistema bloqueado teste

Durante o teste aparece a faixa amarela **"Período de avaliação. X dias restantes."** no topo. Para o dono, o gerente e o financeiro, ela tem o link **Assinar agora** (empresa que contratou pelo site) ou **Ver planos** (dono das demais empresas). A faixa pode ser fechada no X.

Quando o teste acaba:
- **Empresa que se cadastrou pelo site:** o sistema inteiro fica bloqueado na tela **"Seu teste grátis terminou"**. Os dados continuam guardados. Dono, gerente ou financeiro clicam em **Assinar agora** e escolhem PIX, boleto ou cartão; o acesso volta assim que o pagamento é confirmado. Os outros perfis veem "Avise o responsável pela loja".
- **Demais empresas:** o PDV, as vendas, o estoque e os cadastros continuam abrindo. Só as funções do plano (Fiscal, OS, Contratos, Conciliação, DRE, Boletos, Multilojas) levam para a tela **"Seu período de avaliação terminou"**, com os planos e o contato da IA365.

Se a tela for **"Falta só o pagamento"**, a assinatura já foi pedida e a primeira mensalidade está em aberto: clique em **Ver a fatura e pagar**.

## Como assinar o sistema (PIX, boleto ou cartão)?
Palavras-chave: assinar, assinatura, contratar sistema, pagar mensalidade, assinar agora, pix boleto cartao, infinitepay

Vale para empresas que se cadastraram pelo site. Quem assina: **Dono/Gestor, Gerente, Administrador e Financeiro**. Outros perfis veem "A assinatura do sistema fica com o dono, o gerente e o financeiro da loja."

1. Clique em **Assinar agora** (na faixa do teste, na tela Faturas ou na tela de teste encerrado).
2. Na tela **Assinar o ERP Comercial**, confira o valor por mês e o vencimento da primeira mensalidade (no fim do teste; se o teste já acabou, vence hoje).
3. Em **Como você quer pagar?**, escolha:
   - **PIX**: todo mês o código chega no sistema e no e-mail; a baixa é na hora.
   - **Boleto**: todo mês o boleto chega no sistema e no e-mail; o banco leva até 2 dias úteis para confirmar.
   - **Cartão de crédito**: você paga agora pela InfinitePay. Nos meses seguintes o link chega no sistema e no e-mail. **Não é débito automático.**
4. Clique em **Assinar agora** e espere "Gerando a cobrança…" (toque uma vez só).

Depois: "Assinatura pedida. O PIX (ou boleto) da primeira mensalidade está abaixo." Com cartão, o sistema abre a página de pagamento.

Mensagens: **"A cobrança ainda não está ligada..."** (fale com a IA365), **"O pagamento por cartão não está disponível agora. Escolha PIX ou boleto."**, **"Esta empresa já tem assinatura."**, **"A fatura não voltou do financeiro. Tente de novo em alguns minutos."** e **"Para gerar o boleto falta completar o cadastro da empresa: ... "** — o dono completa em **Gestão → Minha Empresa** ou escolhe PIX.

## Onde vejo e pago as faturas do sistema?
Palavras-chave: faturas, fatura do sistema, mensalidade, pagar fatura, copiar pix, copiar boleto, segunda via, recibo, nota fiscal da mensalidade

Quem vê: **Dono/Gestor, Gerente, Administrador e Financeiro**. Outros perfis recebem "As faturas do sistema ficam com o dono, o gerente e o financeiro da loja."

1. Clique em **Faturas** no menu lateral (ou em **Ver fatura** na faixa do topo).
2. Em cada fatura em aberto aparecem os botões disponíveis:
   - **Ver fatura: PIX, boleto e nota fiscal** (abre a página da fatura);
   - **Copiar PIX** e **Copiar boleto**;
   - **Pagar com cartão**, **Gerar boleto** e **Gerar PIX** (quando faltar alguma dessas formas, em empresas que assinaram pelo sistema).
3. Pague. **A baixa é automática**: assim que o pagamento é confirmado, a fatura sai da lista.

Embaixo fica **Faturas pagas**, com **Ver recibo** ou **Recibo e nota**.

Se aparecer "Para pagar esta fatura, fale com a IA365", a fatura foi lançada à mão e não tem link: use o contato da tela.

Pagou no cartão e voltou para o sistema com "Ainda não recebemos a confirmação do cartão..."? Espere: quando a InfinitePay confirmar, a fatura é baixada sozinha.

"Tudo em dia. Não há fatura em aberto." quer dizer que não há nada a pagar.

## Faixa de fatura no topo e acesso suspenso por atraso
Palavras-chave: fatura em atraso, acesso suspenso, sistema bloqueado, faixa vermelha, fatura disponivel, bloqueio por falta de pagamento

**Faixa no topo:**
- Dono, gerente, administrador e financeiro veem a faixa desde que a fatura é emitida: azul (**Fatura disponível.**), amarela perto do vencimento e vermelha em atraso (**Fatura em atraso.**), com valor e botão **Ver fatura**. A azul e a amarela podem ser fechadas; a vermelha não.
- Os outros perfis só veem a faixa quando há atraso: **"Fatura do sistema em atraso. Avise o responsável pela loja."**, sem valor.
- A faixa some sozinha quando a fatura é paga.

**Acesso suspenso:** se a fatura passar do vencimento mais os dias de tolerância, o sistema inteiro abre na tela **"Acesso temporariamente suspenso"**. Os responsáveis veem as faturas pendentes e o botão para pagar (ou ir em Faturas). Os outros perfis veem "Avise o responsável pela loja".

Pagou? O acesso volta **sozinho** assim que o pagamento é confirmado (PIX na hora; boleto em até 2 dias úteis). Não é preciso pedir desbloqueio.

Avisos também chegam no sino e no e-mail cadastrado dos responsáveis: fatura emitida, vencendo, em atraso, suspensão e pagamento confirmado.

## Onde estão os vídeos de treinamento do sistema?
Palavras-chave: treinamento, videos, curso, tutorial, como usar o sistema, aprender, video aula, curso loja online

1. Clique em **Treinamento** no menu lateral (todos os perfis têm; no modo "só PDV" o link fica no topo do PDV).
2. À direita fica a lista **Vídeos**. Clique em um para assistir.
3. O vídeo conta como assistido quando chega ao fim ou quando você clica em **Marcar como assistido**. Aparece o selo verde **Assistido** na lista.
4. Ao abrir a tela, o sistema já começa pelo primeiro vídeo que você ainda não viu.

Os vídeos são separados por seção (por exemplo **Sistema** e **Loja online**) quando você enxerga mais de uma.

Cada perfil vê os vídeos feitos para ele. Dono, gerente e administrador da empresa veem todos. O curso da **Loja online** aparece para dono e gerente mesmo que a loja online da empresa esteja desligada.

Se aparecer "Nenhum vídeo disponível", ainda não há vídeo publicado para o seu perfil.

## Como entrar no app de vendas na maquininha Stone?
Palavras-chave: maquininha stone, app stone, pos, ia365 vendas, login maquininha, vender na maquininha, smart pos, nao conferem

O app **IA365 Vendas** roda na maquininha Stone (smart POS) e usa o mesmo cadastro do sistema: faz venda com cupom, pedido, caixa e clientes, e o pagamento no cartão é cobrado na própria maquininha.

Para entrar:
1. Abra o app na maquininha.
2. Digite o **mesmo e-mail e senha** que você usa no sistema.
3. Escolha a loja, se tiver mais de uma.

Podem usar: **Vendedor, Operador de Caixa, Gerente e Dono/Gestor**. Financeiro e Consulta não usam o app.

Mensagens do login:
- **"E-mail ou senha não conferem. Confira e tente de novo."** — confira os dados. Se estiverem certos, a maquininha pode estar com uma versão do app de teste: fale com a IA365.
- **"Seu usuário está desativado. Peça ao gerente da loja para reativar."**
- **"Seu perfil de acesso não usa o app de vendas. Peça ao gerente para liberar (vendedor, caixa ou gerente)."**
- **"Seu usuário não está em nenhuma loja ativa. Peça ao gerente para incluir você em uma loja."**
- **"O acesso da empresa está suspenso. Fale com o responsável pela conta."** — veja as faturas do sistema.
- **"Você não tem acesso a esta loja."**

As vendas feitas na maquininha aparecem no sistema normalmente (Vendas, Caixa e Contas a Receber, com a etiqueta "Maquininha Stone" nos detalhes). Para instalar o app na sua maquininha, fale com a IA365.

## A forma de pagamento aparece no app da maquininha mas é recusada
Palavras-chave: forma desligada maquininha, maquininha recusa forma, stone forma de pagamento, botao pagamento nao funciona pos

O app da maquininha pode mostrar uma forma de pagamento que a loja desligou em **Configurações da Loja → Formas de pagamento**. Ao tentar cobrar com ela, o sistema recusa **antes** de cobrar o cartão — ninguém é cobrado.

O que fazer:
- Escolha outra forma de pagamento que esteja ligada na loja; ou
- Peça ao gerente ou ao dono para religar a forma em **Gestão → Configurações da Loja → Formas de pagamento**.

O nome que aparece para as formas segue o que a loja configurou no sistema.

## Venda da maquininha Stone nas Contas a Receber e no caixa
Palavras-chave: venda maquininha financeiro, stone contas a receber, transacao stone, conferir venda pos, cancelar venda maquininha

Cada venda feita no app da maquininha entra no sistema como uma venda comum:
- Em **Vendas**, com o cupom e os itens.
- No **caixa** aberto do operador.
- Em **Financeiro → Contas a Receber**: nos **Detalhes** da conta aparece o quadro **Venda de origem**, com a etiqueta **Maquininha Stone** e o bloco **Transação na Stone** (dados da autorização do cartão).

Se a loja cadastrou as taxas em **Máquinas de Cartão**, a venda no cartão entra como pendente, com a data prevista e o valor líquido (veja o tópico sobre venda no cartão pendente).

Cancelamento pela maquininha: o vendedor e o caixa só cancelam a venda que eles mesmos fizeram, com o caixa deles aberto; o gerente cancela a de outro. O app estorna o cartão na Stone e o sistema desfaz a venda, o estoque, o caixa e a conta a receber.

## Como importar contas a receber por planilha?
Palavras-chave: importar contas a receber, planilha contas, modelo excel, subir planilha, importar recebiveis, migrar sistema antigo

Perfis: Dono/Gestor, Administrador e Financeiro.

1. Abra **Financeiro → Contas a Receber**.
2. Clique em **Modelo Excel** para baixar a planilha modelo (.xlsx), com as colunas certas e exemplos.
3. Preencha a planilha sem mudar os nomes das colunas.
4. Clique em **Importar planilha** e envie o arquivo preenchido (.xlsx ou .csv).
5. Confira as contas na lista.

Dica: cadastre os clientes antes, para que as contas sejam ligadas a eles.

Para tirar os dados do sistema, use **Exportar** (planilha Excel), que existe tanto em Contas a Receber quanto em Contas a Pagar.

## Por que o gerente não consegue lançar nem dar baixa no financeiro?
Palavras-chave: gerente nao lanca conta, gerente sem permissao financeiro, gerente so ve, liberar financeiro gerente

O perfil **Gerente** foi feito para tocar a loja, e no Financeiro ele **só vê**: listas de contas, Fluxo de Caixa, Despesas por Centro de Custo, Máquinas de Cartão e DRE. Ao tentar criar, editar, dar baixa ou excluir, aparece **"Você não tem permissão para acessar este recurso."**

A Folha de Pagamento nem aparece para o gerente.

Para alguém cuidar do financeiro:
- Dê a essa pessoa o perfil **Financeiro** (em **Cadastros → Funcionários**, editar, campo de perfil); ou
- Deixe a tarefa com o **Dono/Gestor**.

O perfil Financeiro faz tudo no Financeiro, mas não vê o menu **Gestão** (Plano de Contas, Centros de Custo). Ele chega ao cadastro de centros de custo e ao plano de contas padrão pelos links das telas de Contas a Pagar e da Folha.
