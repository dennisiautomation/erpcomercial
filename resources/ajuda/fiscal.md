# Fiscal — notas fiscais, configuração e rejeições

## Onde fica a parte fiscal no menu?
Palavras-chave: menu fiscal, onde emitir nota, notas fiscais, configuracao fiscal, nfes recebidas, backups xml, emails bloqueados

Tudo o que é fiscal fica no menu lateral, no grupo **Fiscal**. Ao clicar em **Fiscal** abrem os itens:

1. **Notas Fiscais** — lista de todas as notas da loja selecionada (NF-e, NFC-e e NFS-e), com busca, filtros e os botões **Emitir NF-e (DANFE)**, **Emitir NFS-e** e **Inutilizar**.
2. **NFes Recebidas** — notas que fornecedores emitiram contra o CNPJ da sua empresa (manifestação do destinatário).
3. **NFSes Recebidas** — notas de serviço que você contratou (você como tomador).
4. **Emails Bloqueados** — e-mails de clientes que pararam de receber DANFE/XML porque voltaram (bounce).
5. **Backups XML** — pacote mensal com todos os XMLs para guardar ou mandar ao contador.
6. **Configuração Fiscal** — certificado digital, ambiente, séries, CSC, NFS-e, responsável técnico e regime tributário. Este item só aparece para quem tem permissão (o dono da empresa).

Importante: as notas são **por loja**. A tela mostra as notas da loja que está selecionada no topo do sistema. Se você tem várias lojas, troque a loja para ver as notas de cada uma.

Onde cada nota nasce: a **NF-e** sai da tela da venda (botão **Emitir NF-e (DANFE)**) ou ao faturar um pedido; a **NFC-e** sai no PDV ao finalizar a venda; a **NFS-e** sai pelo botão **Emitir NFS-e** em Notas Fiscais.

## Quem pode emitir, cancelar e configurar notas?
Palavras-chave: permissao fiscal, perfil, quem pode cancelar nota, quem pode configurar, acesso negado, 403 fiscal, vendedor nao ve notas

O acesso depende do perfil do usuário:

- **Dono**: faz tudo — vê, emite, cancela, configura, envia certificado, muda o regime tributário e desbloqueia e-mails.
- **Gerente** e **Caixa**: veem as notas e podem emitir, cancelar, fazer carta de correção, manifestar notas recebidas e gerar backups. Não abrem a **Configuração Fiscal**.
- **Financeiro** e **Consulta**: só consultam as notas (ver, baixar XML e DANFE).
- **Vendedor**: não tem acesso ao menu de Notas Fiscais. A NFC-e da venda dele sai normalmente pelo PDV.

Só o **dono** pode:
1. Abrir e salvar **Fiscal → Configuração Fiscal**.
2. Enviar o certificado digital A1.
3. Alterar o **Regime tributário da empresa** (botão **Salvar regime**). Para os outros aparece "Somente o dono da empresa pode alterar o regime tributário."
4. Desbloquear e-mail em **Emails Bloqueados**.

Se aparecer "acesso negado" ao clicar em algo fiscal, o perfil do usuário não tem essa permissão — peça ao dono para fazer ou para mudar o perfil do usuário.

## Como ligar a emissão de nota fiscal na minha loja?
Palavras-chave: ativar nota fiscal, ligar emissao, comecar a emitir, sim emitimos, configurar fiscal, primeira nota

1. Entre como **dono** e abra **Fiscal → Configuração Fiscal**.
2. Na pergunta **"Sua empresa emite nota fiscal eletronica?"** marque **Sim, emitimos**. (Se marcar **Não, apenas recibos**, o sistema só imprime recibos sem valor fiscal.)
3. Em **"No PDV (frente de caixa), emitir:"** escolha **NFC-e (Cupom Fiscal)** ou **Recibo (Não Fiscal)**.
4. Ligue a chave **Habilitar** dos tipos que você usa: **NF-e (DANFE)**, **NFC-e (Cupom Fiscal)** e/ou **NFS-e (Serviços)**. Se ativar a emissão sem marcar nenhum tipo aparece o aviso "Emissão fiscal ativada, mas nenhum tipo marcado."
5. Preencha os campos de cada tipo (série, CSC e ID CSC da NFC-e, dados da NFS-e).
6. Preencha o **Responsável técnico**.
7. Escolha o **Ambiente**: comece em **Homologação (testes)** e mude para **Produção (real)** quando os testes derem certo.
8. Clique em **Salvar**.
9. Envie o **Certificado Digital A1** (arquivo .pfx e senha) e clique em **Enviar**.

O quadro **"O que falta para começar a emitir"** mostra item por item o que ainda falta. Quando um tipo de nota mostra "Pronto para emitir!", pode testar.

## O que é o quadro "O que falta para começar a emitir"?
Palavras-chave: checklist fiscal, pronto para emitir, falta configurar, itens vermelhos, empresa conectada focus, tokens emitidos

No topo da **Configuração Fiscal** aparece o quadro **"O que falta para começar a emitir"**, com um cartão para cada tipo de nota que você habilitou. Cada cartão tem uma contagem (ex.: 3/5) e uma lista:

- **Empresa conectada à Focus NFe** e **Tokens emitidos (homolog/produção)**: a ligação com o emissor de notas. Na maioria das contas a plataforma faz isso sozinha quando você salva a configuração. Se ficar vermelho por muito tempo, chame o suporte.
- **Certificado digital A1 (.pfx)**: envie o arquivo no cartão **Certificado Digital A1**.
- **Responsável técnico (NT 2018/003)**: preencha o cartão **Responsável técnico**.
- **Série NF-e definida** / **Série NFC-e definida**: número da série.
- **CSC (Código Segurança SEFAZ)** e **ID do CSC**: obrigatórios para NFC-e.
- **Item da Lista LC 116** (obrigatório) e **Código tributação municipal** (opcional) para NFS-e.

Item com X vermelho é obrigatório; círculo cinza com "(opcional)" pode ficar sem preencher. Quando todos os obrigatórios ficam verdes aparece **"Pronto para emitir!"**.

## O que é "Empresa gerenciada pela plataforma" e o token da Focus NFe?
Palavras-chave: token focus, focus nfe, gerenciada pela plataforma, provisionamento, testar conexao, integracao focus, webhooks

O ERP emite as notas através da **Focus NFe**, um serviço que conversa com a SEFAZ e com as prefeituras.

- Se aparecer **"Empresa gerenciada pela plataforma."**, a ligação com a Focus foi feita automaticamente: não existe token para colar. O quadro **Status da integração Focus NFe** mostra o número da empresa, "homologação ✓", "produção ✓" e os webhooks (avisos automáticos de autorização).
- Se aparecer **"Ainda não conectada."**, basta salvar a Configuração Fiscal: a empresa é criada na Focus em segundo plano e a mensagem de sucesso diz "Provisionamento na Focus NFe em segundo plano." Aguarde alguns minutos e recarregue a tela.
- Em contas antigas aparece o campo **Token Focus NFe**, com o botão **Testar**. Cole o token recebido da Focus e clique em **Testar**: deve aparecer "Conexao com Focus NFe realizada com sucesso!". Se aparecer "Token invalido ou sem permissao.", o token está errado ou é de outro ambiente.

Se aparecer "sem webhooks" no status, as notas ainda são atualizadas pela consulta automática, só que podem demorar mais para mudar de **Pendente** para **Autorizada**. Se continuar assim, avise o suporte.

## Qual a diferença entre ambiente de Homologação e Produção?
Palavras-chave: homologacao, producao, ambiente de teste, nota de teste, nota sem valor fiscal, trocar ambiente, ambiente real

O campo **Ambiente** fica no topo da **Configuração Fiscal** e vale para NF-e, NFC-e e NFS-e ao mesmo tempo.

- **Homologação (testes)**: as notas vão para o ambiente de testes da SEFAZ. **Não têm valor fiscal**, não contam imposto e servem só para conferir se tudo está certo. Em homologação dá para testar até sem enviar o certificado (a Focus usa um certificado genérico de teste), mas a NFC-e ainda precisa de CSC e ID CSC.
- **Produção (real)**: notas de verdade, com valor fiscal. Exige certificado digital A1 válido.

Passo a passo para virar a chave:
1. Faça algumas vendas de teste em homologação e confira se as notas ficam **Autorizadas**.
2. Envie o certificado A1, se ainda não enviou.
3. Mude **Ambiente** para **Produção (real)** e clique em **Salvar**.

Na tela de cada nota aparece uma etiqueta com o ambiente em que ela foi emitida. Nota de homologação não precisa ser cancelada: ela não existe para a Receita. Se a venda era real e saiu em homologação, emita a nota de novo depois de mudar para Produção.

## Como habilitar a NF-e e qual série usar?
Palavras-chave: habilitar nfe, serie nfe, nota grande, danfe, modelo 55, serie obrigatoria

1. Abra **Fiscal → Configuração Fiscal**.
2. No cartão **NF-e (DANFE)** ligue a chave **Habilitar**.
3. Preencha **Série NF-e** (obrigatório). Normalmente é **1**. Se você já emitia NF-e em outro sistema na mesma série, use uma série que nunca foi usada (ex.: 2 ou 3) ou peça ao suporte para continuar a numeração do sistema antigo — senão a SEFAZ recusa por duplicidade (rejeição 539).
4. Clique em **Salvar**.

A NF-e não sai no PDV: ela é emitida pela tela da venda (botão **Emitir NF-e (DANFE)**) ou ao faturar um pedido escolhendo **Nota Fiscal (NF-e — "nota grande")**.

O número da nota não é digitado: o emissor (Focus NFe) controla a sequência dentro da série.

## Como configurar a NFC-e (série, CSC e ID CSC)?
Palavras-chave: habilitar nfce, cupom fiscal, serie nfce, csc, id csc, token csc, codigo de seguranca, qr code

1. Abra **Fiscal → Configuração Fiscal**.
2. No cartão **NFC-e (Cupom Fiscal)** ligue **Habilitar**.
3. Preencha **Série NFC-e** (obrigatório).
4. Preencha **CSC (Código de Segurança SEFAZ)** (obrigatório) — o código longo que a SEFAZ do seu estado fornece.
5. Preencha **ID CSC** (obrigatório) — o número curto que acompanha o CSC (ex.: 1 ou 000001). No sistema antigo esse campo às vezes se chamava "Token".
6. Em **"No PDV (frente de caixa), emitir:"** marque **NFC-e (Cupom Fiscal)** se quiser cupom fiscal no PDV.
7. Clique em **Salvar**.

Sem CSC + ID, a SEFAZ rejeita a NFC-e mesmo em homologação, e o próprio ERP já avisa antes de enviar: "CSC / ID do CSC não configurados".

Os campos cinza **Versão da NFC-e** e **Última NFC-e emitida** são só informativos: a versão é fixa e o número é controlado pelo emissor. O texto de rodapé vai no cartão **Informações complementares**.

## Como conseguir o CSC e o ID do CSC da NFC-e?
Palavras-chave: obter csc, gerar csc, portal sefaz, codigo de seguranca do contribuinte, id token, csc nfce

O CSC (Código de Segurança do Contribuinte) é gerado pela **SEFAZ do seu estado**, não pelo ERP.

1. Entre no portal da SEFAZ do seu estado (e-CAC ou sistema equivalente) com o certificado digital ou a senha da empresa. Se não souber o caminho, o contador costuma fazer isso.
2. Procure o menu **NFC-e** e a opção de gerar/consultar o CSC ("Gerar CSC").
3. Anote o **código** (CSC) e o **identificador** (ID, ex.: 1, 2 ou 000001).
4. No ERP, abra **Fiscal → Configuração Fiscal** e cole no cartão **NFC-e (Cupom Fiscal)**: o código em **CSC (Código de Segurança SEFAZ)** e o número em **ID CSC**.
5. Clique em **Salvar** (a mensagem deve terminar com "Sincronizado com Focus NFe ✓").

O CSC é do CNPJ: lojas com o mesmo CNPJ usam o mesmo código. Se você gerar um CSC novo na SEFAZ, o antigo pode ser revogado — atualize no ERP no mesmo dia, senão as NFC-e passam a ser rejeitadas (rejeições 462, 463 ou 464).

## Vim de outro sistema: como evitar nota duplicada (numeração e série)?
Palavras-chave: migracao, outro sistema, ultima nota, numeracao, serie nova, duplicidade, sistema antigo, gestao, continuar numeracao

No ERP não existe campo para digitar "última nota emitida": quem numera as notas é o emissor (Focus NFe), que começa no número 1 de cada série.

Se o seu sistema antigo já usou aquela série, a nova nota vai repetir um número que já existe na SEFAZ e volta **"Rejeição 539: Duplicidade de NF-e com diferença na chave de acesso"** (ou 204). Cada tentativa assim gasta um número.

Como resolver:
1. Pergunte ao contador (ou veja no sistema antigo) quais séries já foram usadas para NF-e e para NFC-e.
2. Em **Fiscal → Configuração Fiscal**, troque a **Série NF-e** e/ou **Série NFC-e** para uma série **nunca usada** (ex.: a última + 1).
3. Clique em **Salvar** e emita a nota de novo.
4. Outra opção: pedir ao suporte para iniciar a numeração no próximo número do sistema antigo.

Atenção: se o sistema antigo continuar emitindo na mesma série ao mesmo tempo que o ERP, as duplicidades voltam. Use séries diferentes em cada sistema e avise o contador da série nova.

## Tenho várias lojas com o mesmo CNPJ. Preciso configurar cada uma?
Palavras-chave: cnpj compartilhado, mesmo cnpj, varias lojas, filial, matriz, certificado compartilhado, csc compartilhado

Quando duas ou mais lojas usam o mesmo CNPJ, a **Configuração Fiscal** mostra o aviso **"CNPJ compartilhado entre lojas."** com o nome das outras lojas.

- O **certificado digital A1**, o **CSC** e a **numeração das notas** pertencem ao CNPJ: configurados uma vez, valem para todas essas lojas.
- Como a numeração é uma só, as lojas do mesmo CNPJ dividem a mesma sequência de números na mesma série.
- Os demais campos (habilitar tipos, série, PDV) continuam sendo salvos loja a loja: confira cada loja trocando a loja selecionada no topo do sistema.

Lojas com CNPJ diferente (filiais com CNPJ próprio) precisam de configuração completa cada uma, inclusive certificado e CSC próprios. O CNPJ e a Inscrição Estadual de cada loja ficam em **Gestão → Minhas Lojas → Editar**.

## Como configurar a NFS-e (nota de serviço)?
Palavras-chave: nfse, nota de servico, iss, rps, item lc 116, codigo tributacao municipal, regime especial, prefeitura, padrao nacional

1. Abra **Fiscal → Configuração Fiscal**.
2. No cartão **NFS-e (Serviços)** ligue **Habilitar**.
3. Preencha:
   - **Série RPS** (normalmente 1);
   - **Item LC 116** (obrigatório) — o item da lista de serviços, ex.: 01.01 ou 17.19;
   - **Código de Tributação Municipal** — conforme a prefeitura (opcional na maioria das cidades);
   - **Regime Especial** — Nenhum, Microempresa Municipal, Estimativa, Sociedade de Profissionais, Cooperativa, MEI ou ME / EPP;
   - **Incentivador Cultural** — marque só se a empresa for.
4. Em **Padrão da NFS-e** escolha **Municipal (prefeitura)** ou **Nacional (Portal RFB)**. Pergunte ao contador qual a sua cidade usa.
5. Clique em **Salvar**.

O item LC 116 e o código municipal são definidos pelo contador ou pela prefeitura. A Inscrição Municipal da loja fica em **Gestão → Minhas Lojas → Editar**. Para NFS-e o certificado A1 muitas vezes não é exigido — depende da prefeitura (algumas, como São Paulo e Curitiba, pedem).

## O que é o Responsável técnico e como preencher?
Palavras-chave: responsavel tecnico, nt 2018 003, cnpj responsavel, contato, rejeicao 972, usar dados

A SEFAZ exige que a NF-e informe quem é o responsável técnico pelo sistema emissor. Sem ele a NF-e é rejeitada, e o próprio ERP já barra antes com "Responsável técnico não cadastrado — obrigatório desde a NT 2018/003."

1. Abra **Fiscal → Configuração Fiscal** e vá até o cartão **Responsável técnico**.
2. Preencha **CNPJ/CPF**, **Nome do contato**, **E-mail** e **Telefone** (todos obrigatórios).
3. Se for você mesmo, clique em **Usar meus dados (seu nome)**: CNPJ, nome e e-mail são preenchidos automaticamente; complete o telefone.
4. Clique em **Salvar**.

Geralmente quem aparece aqui é o dono da empresa, o contador ou a pessoa de TI que cuida do sistema fiscal. Na NFC-e esse bloco também é enviado quando está preenchido.

## Como colocar uma mensagem fixa em todas as notas (informações complementares)?
Palavras-chave: informacoes complementares, observacao na nota, rodape da nota, mensagem fixa, informacoes adicionais, texto do cupom

1. Abra **Fiscal → Configuração Fiscal**.
2. Vá ao cartão **Informações complementares**.
3. Escreva o texto (ex.: "Trocas em até 7 dias com o cupom.").
4. Clique em **Salvar**.

Esse texto sai em todas as NF-e e NFC-e, no campo de informações adicionais. As observações digitadas na própria venda continuam saindo também — as duas aparecem juntas, separadas por " | ".

Não use este campo para informação fiscal obrigatória (ICMS, ST, Simples Nacional etc.): essa parte já sai automática conforme o regime e os dados dos produtos.

## Como definir o regime tributário da empresa?
Palavras-chave: regime tributario, simples nacional, lucro presumido, lucro real, crt, salvar regime, mudar regime

O regime tributário fica no primeiro cartão da **Configuração Fiscal**, **Regime tributário da empresa**, e vale para todas as lojas da empresa.

1. Entre como **dono** e abra **Fiscal → Configuração Fiscal**.
2. Em **Regime** escolha **Simples Nacional**, **Lucro Presumido** ou **Lucro Real**.
3. Clique em **Salvar regime** e confirme.

O regime muda como todas as notas saem:
- **Simples Nacional**: os produtos usam **CSOSN** (ex.: 102) e o ICMS não é destacado.
- **Lucro Presumido / Real**: os produtos usam **CST** (ex.: 00) e as notas saem com IBS/CBS automaticamente (obrigatório desde 03/08/2026).

Confirme o regime com o contador antes de mudar. Se o regime do ERP não bater com o cadastro da empresa na SEFAZ, as notas são rejeitadas (rejeições 590/591). Depois de mudar, revise os produtos: o código fiscal (CST ou CSOSN) de cada produto tem que combinar com o novo regime.

## O que o sistema preenche sozinho no produto conforme o regime (CST/CSOSN)?
Palavras-chave: preset fiscal, csosn 102, cst 00, cfop 5102, sugestao fiscal, dados fiscais automaticos, padrao do regime

Quando a emissão de nota está ligada, a etapa **Dados Fiscais** do cadastro do produto mostra uma faixa "Regime ..." com valores sugeridos para o seu regime. O sistema sugere:

- **Simples Nacional**: CSOSN **102 - Tributada sem crédito**, CFOP **5102** (venda de mercadoria adquirida), PIS e COFINS sem destaque.
- **Lucro Presumido**: CST **00 - Tributada integralmente**, CFOP 5102, PIS 0,65% e COFINS 3%.
- **Lucro Real**: CST **00**, CFOP 5102, PIS 1,65% e COFINS 7,6%.

Essas sugestões cobrem o caso comum do varejo, mas não servem para todo produto. Produtos com substituição tributária, isentos, monofásicos (ex.: alguns cosméticos, bebidas, autopeças) ou com redução de base precisam de código próprio. **Quem define o código certo é o contador**; o lojista só grava no produto. Revise os valores antes de salvar.

Na venda para outro estado o ERP troca sozinho o CFOP 5xxx pelo 6xxx correspondente (ex.: 5102 vira 6102, ou 6108 para consumidor sem IE). O cadastro do produto não muda.

## Onde preencho NCM, CFOP, CST/CSOSN, CEST e origem do produto?
Palavras-chave: ncm do produto, cfop, cst, csosn, cest, origem, aliquota icms, dados fiscais, cadastro do produto, codigo de barras ean

1. Abra o produto em **Produtos** e clique para editar.
2. Avance até a etapa **Dados Fiscais** (se a emissão de nota estiver desligada, aparece "Sua empresa não emite nota fiscal" e os campos não aparecem).
3. Preencha:
   - **NCM** — digite parte do nome (ex.: "camiseta") e escolha na lista; são 8 dígitos;
   - **CFOP** — normalmente 5102 para revenda;
   - **CSOSN** (Simples Nacional) ou **CST** (Lucro Presumido/Real);
   - **Alíquotas de impostos** — ICMS, PIS, COFINS e IPI (no Simples normalmente ficam zeradas);
   - **Origem do produto** — 0 para nacional, 1 ou 2 para importado etc.;
   - **CEST** (opcional) — obrigatório quando o NCM está na lista de substituição tributária.
4. Clique em **Atualizar Produto**.

O **Código de barras** fica na etapa anterior e vai para a nota como código GTIN/EAN. Os códigos (NCM, CST, CSOSN, CEST) são definidos pelo contador; o lojista grava no produto. Uma venda já feita guarda os dados fiscais do momento da venda.

## Onde fica a Inscrição Estadual da minha loja?
Palavras-chave: inscricao estadual emitente, ie da loja, minhas lojas, inscricao municipal, cnpj da loja, endereco da loja

A Inscrição Estadual (IE) e a Inscrição Municipal (IM) da loja ficam no cadastro da loja:

1. No menu, abra **Gestão → Minhas Lojas**.
2. Clique em **Editar** na loja.
3. Preencha **Inscrição Estadual** (e **Inscrição Municipal**, se emite NFS-e). Confira também **CNPJ** e o **Endereço** completo (CEP, logradouro, número, bairro, cidade, UF).
4. Clique em **Salvar Alterações**.

Sem IE a NFC-e não sai: o ERP avisa antes "Inscrição Estadual não cadastrada — preencha ... Sem IE a SEFAZ rejeita a NFC-e." IE errada causa rejeições como "IE do emitente inválida" (209) ou "IE do emitente não cadastrada" (230).

Use a IE exatamente como está no cadastro da SEFAZ (pode digitar com ou sem pontos). Se tiver dúvida, o contador confirma.

## Reforma Tributária: preciso configurar IBS e CBS?
Palavras-chave: reforma tributaria, ibs, cbs, is, imposto seletivo, cclasstrib, nt 2025 002, ec 132, 2026, 2027

O cartão **Reforma Tributária (EC 132/2023) — IBS / CBS / IS** da **Configuração Fiscal** mostra a situação da sua empresa:

- **Lucro Presumido ou Lucro Real**: aparece **"Envio automático ativo"**. Todas as NF-e, NFC-e e NFS-e já saem com IBS e CBS sozinhas. Nada a configurar. A SEFAZ rejeita notas do regime normal sem esses campos desde 03/08/2026.
- **Simples Nacional**: a obrigação começa em **04/01/2027**. Até lá é opcional; se quiser antecipar, ligue as chaves **IBS** e **CBS** no cartão e clique em **Salvar**.

Em 2026 as alíquotas são de teste (CBS 0,9% e IBS 0,1%) e **não mudam o valor que o cliente paga**: são compensadas no PIS/COFINS.

Cada produto sai com CST **000** e classificação **000001** (tributação integral, o caso comum do varejo). Produto com isenção ou redução: o contador informa o **CST IBS/CBS** e a **Classificação (cClassTrib)** próprios, e o lojista grava na etapa **Dados Fiscais** do produto. Campos em branco usam os valores legais.

Se uma nota do regime normal voltar rejeitada citando IBS, CBS ou cClassTrib, confira se o **Regime tributário da empresa** está certo e se o produto não tem CST IBS/CBS ou classificação digitados errado.

## Como enviar o certificado digital A1?
Palavras-chave: certificado digital, certificado a1, enviar pfx, arquivo p12, senha do certificado, instalar certificado

Só o **dono** consegue enviar o certificado.

1. Abra **Fiscal → Configuração Fiscal** e vá ao cartão **Certificado Digital A1**.
2. Em **Arquivo do certificado (.pfx)**, arraste o arquivo para a área **"Arraste o arquivo .pfx aqui"** ou clique nela para escolher. Aceita arquivos **.pfx** ou **.p12** de até 2 MB.
3. Em **Senha**, digite a senha definida quando o certificado foi comprado.
4. Clique em **Enviar**.
5. Deve aparecer "Certificado digital enviado com sucesso!" e, no cartão, "Enviado em ... — validade até ...".

O arquivo vai direto para o emissor de notas (Focus NFe) e não fica guardado no ERP; a senha também não é gravada.

Dicas:
- Só funciona certificado **A1** (arquivo). O A3 (cartão ou token USB) não serve.
- Não achou o arquivo no seletor? Digite as primeiras letras do nome no campo **Buscar** da janela ou ordene pela coluna **Nome**.
- O certificado tem que ser do **mesmo CNPJ** da loja.
- Lojas com o mesmo CNPJ compartilham o certificado: basta enviar uma vez.

## Deu erro ao enviar o certificado. O que fazer?
Palavras-chave: erro certificado, senha incorreta, certificado invalido, cnpj diferente, certificado vencido, nao provisionada, pfx invalido

Mensagens comuns ao clicar em **Enviar** no cartão **Certificado Digital A1** e o que fazer:

- **"Senha do certificado incorreta."** — redigite a senha com atenção (maiúsculas contam). Se não lembra, só quem emitiu o certificado (a certificadora) pode ajudar.
- **"O arquivo enviado não parece ser um certificado A1 (.pfx/.p12) válido."** — você mandou outro arquivo (ex.: .cer, .zip, atalho). Peça à certificadora o arquivo .pfx.
- **"O certificado é do CNPJ X, diferente do CNPJ desta unidade (Y)."** — o certificado é de outra empresa ou filial. Envie o certificado do CNPJ desta loja, ou corrija o CNPJ da loja em **Gestão → Minhas Lojas → Editar**.
- **"Este certificado venceu em ..."** — compre/renove o certificado A1.
- **"A empresa não tem CNPJ válido cadastrado..."** — preencha o CNPJ nos dados da empresa/loja.
- **"Esta unidade ainda não foi provisionada na Focus NFe..."** ou **"Informe o token Focus NFe antes de enviar o certificado."** — a ligação com o emissor ainda não terminou. Clique em **Salvar** na Configuração Fiscal, espere alguns minutos e tente de novo; se continuar, chame o suporte.
- **"O arquivo é grande demais (máximo 2MB)."** — certificado A1 tem menos de 100 KB; o arquivo está errado.
- **"Token master ... Avise o suporte."** — problema da plataforma: chame o suporte.

## Quando meu certificado vence e como sou avisado?
Palavras-chave: vencimento certificado, certificado expira, aviso certificado, validade a1, certificado vencido, renovar certificado

No cartão **Certificado Digital A1** da **Configuração Fiscal** aparece uma etiqueta:
- **Válido — N dias** (verde): mais de 30 dias;
- **Expira em N dias** (amarelo): 30 dias ou menos;
- **VENCIDO** (vermelho): já venceu.

Além disso, o dono recebe aviso no sino de notificações **30, 15, 7 e 1 dia antes** do vencimento ("Certificado digital vence em N dia(s)"; a partir de 7 dias vem com "URGENTE"). O painel **Dashboard Fiscal** também mostra a situação do certificado de cada loja.

Com o certificado vencido, NF-e e NFC-e **param de ser autorizadas** em produção.

Para renovar:
1. Compre um novo certificado A1 na certificadora (o certificado A1 costuma valer 1 ano).
2. Abra **Fiscal → Configuração Fiscal → Certificado Digital A1**.
3. Envie o novo arquivo .pfx com a senha e clique em **Enviar**.
4. Confira a nova data em "validade até".

Não é preciso apagar o antigo: o novo substitui.

## Como vejo se a SEFAZ está funcionando?
Palavras-chave: status sefaz, sefaz fora do ar, sefaz offline, sefaz instavel, sefaz online, autorizador

Na **Configuração Fiscal** aparece uma faixa **SEFAZ (UF)** com a situação do autorizador do estado da sua loja:

- **Online** (verde): "operacional" — as notas devem sair normalmente.
- **Instável** (amarelo): "com lentidão. Emissões podem demorar."
- **Offline** (vermelho): "fora do ar. Emissões estão falhando — aguarde o retorno."
- **—** (cinza): não foi possível consultar agora.

A faixa se atualiza sozinha a cada minuto e tem um botão de atualizar.

Se a SEFAZ estiver fora do ar:
- No PDV a venda **nunca trava**: sai o recibo e aparece o aviso "Cupom fiscal (NFC-e) não emitido — saiu recibo.". Depois que a SEFAZ voltar, emita o cupom pela tela da venda (**Emitir NFC-e novamente**).
- Para NF-e, aguarde e emita de novo pela venda.

Mensagens como "Rejeição 108: Serviço paralisado momentaneamente" ou "109: Serviço paralisado sem previsão" têm a mesma causa.

## Como emitir NF-e de uma venda?
Palavras-chave: emitir nfe, emitir danfe, nota grande, nfe da venda, modelo 55, nota para empresa, cnpj

1. Abra a venda (menu **Vendas**, ou o botão **Emitir NF-e (DANFE)** em **Fiscal → Notas Fiscais**, que leva à lista de vendas).
2. No quadro **Nota Fiscal** clique em **Emitir NF-e (DANFE)** e confirme.
3. O sistema abre a tela da nota com "NF-e enviada para autorizacao com sucesso!". A NF-e fica **Pendente** por alguns segundos e vira **Autorizada** sozinha. Se demorar, clique em **Consultar Status**.
4. Com a nota autorizada, use os botões **DANFE** (PDF para imprimir) e **XML**.

Antes de enviar, o ERP confere e avisa se falta algo:
- cliente informado na venda ("NF-e exige destinatário identificado — informe o cliente.");
- endereço completo do cliente (logradouro, cidade, UF e CEP);
- CPF ou CNPJ do cliente;
- NCM e CFOP em todos os produtos;
- responsável técnico na Configuração Fiscal.

Depois de autorizada, o XML e o DANFE são enviados ao e-mail do cliente (se ele tiver e-mail no cadastro). A NF-e serve para venda a empresas, para outro estado ou quando o cliente pede a "nota grande".

## Como emitir NF-e ao faturar um pedido?
Palavras-chave: faturar pedido, nota do pedido, pedido nota fiscal, nfe pedido, faturamento, documento do pedido

1. Abra o pedido em **Pedidos**.
2. Clique em **Faturar Pedido**.
3. Escolha a **Forma de pagamento** (e as parcelas, se houver).
4. Em "Qual documento deseja emitir?" escolha:
   - **Pedido impresso (folha A4)** — sem valor fiscal;
   - **Cupom Fiscal (NFC-e)** — emitido na hora pela SEFAZ;
   - **Nota Fiscal (NF-e — "nota grande")** — DANFE modelo 55; exige cliente com endereço completo;
   - **Nenhum documento agora** — só fatura; a nota pode ser emitida depois pela venda gerada.
5. Confirme.

Se uma opção aparecer em cinza com **"Falta: ..."**, o texto diz o que corrigir antes (ex.: endereço do cliente, CSC, certificado).

O faturamento baixa o estoque e lança a venda no caixa aberto da loja. Se o pedido tem **frete**, ele vai para a nota: na NF-e como frete; na NFC-e como outras despesas. A nota de um pedido também pode ser emitida depois, na tela da venda gerada.

## Como o PDV decide se emite cupom fiscal (NFC-e) ou recibo?
Palavras-chave: pdv nfce, cupom fiscal no pdv, recibo ou cupom, auto recibo cupom fiscal, padrao de impressao, cpf na nota emite fiscal, cartao emite cupom

Acima do botão **FINALIZAR VENDA** ficam três botões: **Auto**, **Recibo** e **Cupom Fiscal**.

- **Recibo**: força recibo (não fiscal).
- **Cupom Fiscal**: força a NFC-e.
- **Auto** (padrão): segue as regras de **Gestão → Configurações da Loja**, aba **Cupom e recibo**:
  1. **Venda no cartão emite cupom fiscal automaticamente** — venda com débito ou crédito sai com NFC-e.
  2. **Cliente pediu CPF na nota → emite cupom fiscal na hora** — se o operador informou cliente ou CPF, sai NFC-e.
  3. **Nas demais vendas (dinheiro/PIX, sem CPF), o padrão é:** **Recibo** ou **Cupom Fiscal (NFC-e)**.

Em alguns casos o PDV pergunta "Qual documento imprimir?" com as opções **Cupom Fiscal** e **Recibo**.

Pré-requisito: a **Configuração Fiscal** da loja precisa estar com **Sim, emitimos**, NFC-e habilitada, certificado A1 e CSC. Sem isso sai sempre o recibo, independentemente do que estiver marcado.

## Por que a venda no PDV saiu só com recibo e não com cupom fiscal?
Palavras-chave: saiu recibo, nfce nao emitida, cupom nao saiu, cupom fiscal nao emitido, so recibo, pdv sem nfce

Quando a NFC-e falha, a venda **não trava**: o PDV imprime o recibo e mostra o aviso amarelo **"Cupom fiscal (NFC-e) não emitido — saiu recibo."** seguido do motivo. Causas mais comuns:

1. **Recibo escolhido**: o operador clicou em **Recibo**, ou o modo **Auto** caiu no padrão "Recibo" (veja **Gestão → Configurações da Loja → Cupom e recibo**).
2. **Fiscal desligado ou incompleto**: em **Fiscal → Configuração Fiscal** falta "Sim, emitimos", a NFC-e habilitada, o CSC/ID CSC ou o certificado.
3. **Rejeição da SEFAZ**: o aviso começa com "NFC-e rejeitada:" e traz o motivo (NCM, duplicidade, CSC etc.). Procure nesta ajuda pelo número da rejeição.
4. **SEFAZ ou internet fora do ar** no momento da venda.
5. **Cliente de outro estado**: NFC-e não vale para outra UF.

Para emitir o cupom depois de corrigir:
1. Abra a venda (menu **Vendas**).
2. No quadro **Nota Fiscal** clique em **Emitir NFC-e** (ou **Emitir NFC-e novamente**, se houve tentativa rejeitada).

O cupom emitido depois sai com a data e hora do momento da emissão.

## Como colocar o CPF do cliente no cupom fiscal (CPF na nota)?
Palavras-chave: cpf na nota, cnpj na nota, nfce com cpf, identificar consumidor, cupom com cpf

No PDV há duas formas:

1. **Sem cadastrar o cliente**: digite o número no campo **CPF/CNPJ na nota (opcional)** antes de finalizar. O número vai só no cupom fiscal e é apagado depois da venda.
2. **Com cliente cadastrado**: selecione o cliente (atalho **F2** Cliente). O CPF/CNPJ e o nome do cadastro vão no cupom.

Se a loja ligou **Cliente pediu CPF na nota → emite cupom fiscal na hora** (em **Gestão → Configurações da Loja → Cupom e recibo**), informar CPF faz a NFC-e sair automaticamente no modo **Auto**.

Confira o número antes de finalizar: CPF com dígito errado faz a SEFAZ rejeitar ("Rejeição 237: CPF do destinatário inválido"). Para empresa, informe o CNPJ — mas venda para empresa que vai usar a nota como compra costuma ser feita com **NF-e**, não com cupom.

## Como emitir a NFC-e depois, pela tela da venda?
Palavras-chave: emitir nfce depois, emitir nfce novamente, cupom depois da venda, reemitir cupom, nota rejeitada reemitir

1. Abra **Vendas** e clique na venda.
2. No quadro **Nota Fiscal**:
   - sem nenhuma nota: clique em **Emitir NFC-e** e confirme;
   - com tentativa rejeitada ou cancelada: aparece "Esta venda não tem nota fiscal válida" e os botões **Emitir NFC-e novamente** e **Emitir NF-e**.
3. Acompanhe o status na tela da nota.

Antes de emitir de novo, **corrija o motivo** da rejeição (produto, cliente ou Configuração Fiscal); senão a mesma rejeição volta.

Os botões só somem quando a venda já tem uma nota viva (Autorizada, Pendente ou Contingência). Para emitir a NFC-e por essa tela o usuário precisa ter acesso a Notas Fiscais (dono, gerente ou caixa).

Se o cliente da venda é de outro estado, o botão de NFC-e não aparece: aparece só **Emitir NF-e**, com a explicação "venda para outra UF sai só como NF-e".

## Venda para cliente de outro estado: NFC-e ou NF-e?
Palavras-chave: outro estado, interestadual, cliente de fora, nfce interestadual, cfop 6102, cfop 6108, uf diferente

A NFC-e (cupom) só vale para venda **dentro do estado** da loja. Para cliente com endereço em outra UF use a **NF-e**:

1. Confira o cadastro do cliente (**Clientes** → editar): CPF/CNPJ, CEP, logradouro, número, bairro, **Cidade** e **UF** corretos.
2. Abra a venda e clique em **Emitir NF-e (DANFE)**.

O ERP ajuda sozinho:
- esconde o botão de NFC-e e, se tentar pelo PDV, avisa "Cliente ... é de XX e a loja emite por YY: NFC-e não vale para outra UF — emita NF-e (modelo 55)";
- troca o CFOP do item de 5xxx para 6xxx (ex.: 5102 → 6102 para contribuinte com IE, 6108 para consumidor sem IE);
- marca "consumidor final" quando o cliente não tem IE.

Se a SEFAZ responder "NFC-e para operacao interestadual", a venda foi tentada como cupom: emita NF-e. Venda interestadual para consumidor final pode exigir a partilha do ICMS (DIFAL, rejeição 694) — veja o tópico próprio e fale com o contador.

## Como emitir NFS-e (nota de serviço)?
Palavras-chave: emitir nfse, nota de servico, iss, aliquota iss, discriminacao do servico, prestacao de servico

1. Confirme que a NFS-e está habilitada em **Fiscal → Configuração Fiscal** (cartão **NFS-e (Serviços)**, com **Item LC 116** preenchido).
2. Abra **Fiscal → Notas Fiscais** e clique em **Emitir NFS-e**.
3. Preencha:
   - **Cliente** (obrigatório; o tomador do serviço);
   - **Descrição / Discriminacao do Serviço** — o que foi feito; aparece na nota;
   - **Valor do Serviço (R$)**;
   - **Alíquota ISS (%)** — informada pelo contador ou pela prefeitura. O **Valor ISS (R$)** e o valor líquido são calculados na hora.
4. Clique em **Emitir NFS-e**.

A NFS-e é processada pela prefeitura (ou pelo Portal Nacional): aparece "NFS-e enviada! Estamos aguardando a autorização da prefeitura. Esta página atualiza automaticamente." A nota fica **Pendente** até a resposta.

Mensagens do ERP antes de enviar: "Informe a descrição do serviço (discriminação)..." e "Valor do serviço precisa ser maior que zero.". Rejeições da prefeitura variam por cidade; leve a mensagem ao contador quando citar código de serviço, alíquota ou inscrição municipal.

## O que significa cada status da nota (Pendente, Autorizada, Rejeitada...)?
Palavras-chave: status da nota, pendente, autorizada, rejeitada, cancelada, inutilizada, contingencia, consultar status, processando

Os status aparecem em **Fiscal → Notas Fiscais** e na tela de cada nota:

- **Pendente** — enviada e aguardando resposta da SEFAZ ou da prefeitura. NF-e e NFS-e passam por aqui alguns segundos (às vezes minutos). Clique em **Consultar Status** (na lista, o botão de seta circular) para atualizar.
- **Autorizada** — nota válida. Libera **XML**, **DANFE**/**Imprimir Cupom**, **Cancelar** e, na NF-e, **CC-e**.
- **Rejeitada** — a SEFAZ recusou; a nota não tem valor e não precisa ser cancelada. O motivo aparece em **Dados Tecnicos (Focus NFe) → Focus Mensagem**. Corrija e emita de novo.
- **Cancelada** — cancelada na SEFAZ, com motivo e protocolo no quadro **Cancelamento**.
- **Inutilizada** — número descartado oficialmente.
- **Contingência** — emitida em modo de emergência porque a SEFAZ estava fora; será transmitida depois.

A tela da nota tem uma **Timeline do Documento** mostrando por onde ela passou. Os contadores do topo da lista (**Emitidas no Mês**, **Autorizadas no Mês**, **Canceladas no Mês**) contam o mês atual da loja selecionada.

## Onde vejo o motivo da rejeição de uma nota?
Palavras-chave: motivo rejeicao, mensagem sefaz, por que rejeitou, erro da nota, focus mensagem, dados tecnicos

1. Abra **Fiscal → Notas Fiscais**.
2. No filtro **Status** escolha **Rejeitada** e clique em filtrar (ou busque pelo número, cliente ou chave).
3. Clique em **Detalhes** (ícone de olho) na nota.
4. Abra o quadro **Dados Tecnicos (Focus NFe)** no fim da página.
5. Leia o campo **Focus Mensagem**: ali está o texto da SEFAZ, por exemplo "Rejeicao: Total do Desconto difere do somatorio dos itens" ou "539 Duplicidade de NF-e...".

No PDV, o mesmo motivo aparece no aviso amarelo "Cupom fiscal (NFC-e) não emitido — saiu recibo.". Na tela da venda, as tentativas aparecem no quadro **Nota Fiscal** com status **Rejeitada**.

O **Dashboard Fiscal** mostra os 5 erros SEFAZ mais frequentes dos últimos 30 dias, útil para achar um problema que se repete (ex.: o mesmo NCM errado em vários produtos).

Com o texto em mãos, procure nesta ajuda pelo número da rejeição ou por uma palavra da mensagem.

## Minha nota foi rejeitada. Posso emitir de novo?
Palavras-chave: reemitir nota, nota rejeitada, emitir novamente, corrigir e reenviar, numero pulado, rejeitada gasta numero

Sim. Nota **Rejeitada** não tem valor fiscal, não precisa ser cancelada e não impede nova emissão.

1. Leia o motivo em **Dados Tecnicos (Focus NFe) → Focus Mensagem**.
2. Corrija a causa onde ela está: cadastro do produto (NCM, CFOP, CST/CSOSN, código de barras), cadastro do cliente (CPF, endereço, IE), dados da loja (IE, endereço) ou **Configuração Fiscal** (série, CSC, certificado, regime).
3. Abra a venda e use **Emitir NFC-e novamente** ou **Emitir NF-e**.

Pontos de atenção:
- A nova nota usa os dados **atuais** do cliente e da configuração. Os dados fiscais dos itens vendidos ficam guardados na venda; se o erro era do produto e a nova tentativa repetir a rejeição, fale com o suporte.
- A data da nota será a do momento da nova emissão.
- Algumas rejeições fazem o número ser "pulado". Números pulados podem precisar de **inutilização** — o contador avisa quando for o caso.
- Não insista várias vezes sem corrigir: a mesma rejeição volta.

## Como baixar o XML e o DANFE ou reimprimir o cupom?
Palavras-chave: baixar xml, baixar danfe, imprimir danfe, pdf da nota, reimprimir cupom, segunda via, imprimir cupom fiscal

Pela lista:
1. Abra **Fiscal → Notas Fiscais** e encontre a nota (campo **Buscar** aceita número, chave ou nome do cliente).
2. Na coluna **Ações**: botão **XML** (ícone de código) baixa o arquivo XML; botão vermelho de PDF abre o **DANFE** (NF-e), o cupom (NFC-e) ou o **PDF da NFS-e**.

Pela tela da nota (**Detalhes**): botões **XML** e **DANFE** / **Imprimir Cupom** / **PDF da NFS-e** no topo.

Pela venda: no quadro **Nota Fiscal** da venda, botões **XML**, **DANFE** e, para NFC-e autorizada, **Cupom** (abre o cupom na impressora térmica).

Os botões só aparecem quando a nota está autorizada (ou cancelada) e o arquivo já existe. O ERP guarda uma cópia própria do XML de cada nota, então o XML continua disponível mesmo se o emissor estiver fora do ar. Se aparecer "XML nao disponivel para esta nota." ou "Documento nao disponivel para esta nota.", a nota ainda não foi autorizada — clique em **Consultar Status**.

Para mandar todos os XMLs do mês ao contador, use **Fiscal → Backups XML**.

## O cliente não recebeu a nota por e-mail. Como reenviar?
Palavras-chave: reenviar email, nota por email, cliente nao recebeu, email da nota, danfe por email, xml por email, emails bloqueados, bounce

Quando a NF-e é autorizada, o XML e o DANFE vão automaticamente para o **e-mail do cadastro do cliente**. Se ele não recebeu:

1. Confira o e-mail em **Clientes** → editar o cliente. Sem e-mail no cadastro, nada é enviado.
2. Veja se o e-mail está em **Fiscal → Emails Bloqueados**. E-mails que voltam (caixa cheia, endereço inexistente) são bloqueados pelo emissor para proteger a reputação de envio. Depois de corrigir o cadastro, o **dono** clica em **Desbloquear** para os envios voltarem.
3. Peça para o cliente olhar a caixa de spam.

O ERP **não tem um botão de "reenviar e-mail"**. Para mandar agora, abra a nota em **Fiscal → Notas Fiscais**, baixe o **XML** e o **DANFE** e envie pelo seu e-mail ou WhatsApp.

A NFC-e também é enviada ao cliente que tem e-mail no cadastro; o comprovante principal da NFC-e é o cupom impresso com QR Code.

## Como cancelar uma nota fiscal?
Palavras-chave: cancelar nota, cancelar nfe, cancelar nfce, cancelar cupom, cancelamento, justificativa, estornar nota

Só nota **Autorizada** pode ser cancelada.

1. Abra **Fiscal → Notas Fiscais** e clique em **Detalhes** na nota.
2. Clique em **Cancelar** (botão vermelho no topo).
3. Escreva a **Justificativa** com pelo menos 15 caracteres (ex.: "Venda desfeita a pedido do cliente").
4. Clique em **Confirmar Cancelamento**.
5. Deve aparecer "Nota fiscal cancelada com sucesso!" e o quadro **Cancelamento** com motivo e protocolo.

Cuidados:
- O cancelamento é **irreversível**.
- Há **prazo**: em geral **30 minutos** para NFC-e e **24 horas** para NF-e, contados da autorização. Fora do prazo, aparece por exemplo "O prazo para cancelamento desta NFC-e foi excedido... Emita uma nota de devolução." — fale com o contador.
- Cancelar a nota **não** cancela a venda, nem devolve estoque ou dinheiro. Para desfazer a venda inteira, depois de cancelar a nota use **Cancelar Venda** na tela da venda.
- Depois de cancelar, confira se o status mudou para **Cancelada**. Se a nota continuar **Autorizada**, ela ainda vale na SEFAZ: chame o suporte.

## Qual o prazo para cancelar nota e o que fazer depois dele?
Palavras-chave: prazo cancelamento, 30 minutos, 24 horas, cancelamento fora do prazo, nota de devolucao, cancelamento extemporaneo, rejeicao 220, rejeicao 501

Prazos gerais da SEFAZ (alguns estados têm regras próprias):
- **NFC-e (cupom)**: até **30 minutos** depois da autorização.
- **NF-e**: até **24 horas** depois da autorização.
- **NFS-e**: depende da prefeitura.

Passado o prazo, a SEFAZ responde com mensagens como "Rejeição 220", "Rejeição 501" ou "prazo de cancelamento excedido", e o ERP mostra "O prazo para cancelamento desta NFC-e foi excedido (máximo 30 minutos após autorização). Emita uma nota de devolução."

O que fazer:
1. **Não** tente cancelar de novo: a resposta será a mesma.
2. Fale com o **contador**: o caminho normal é uma nota de **devolução/entrada** que anula a venda no fiscal, ou o pedido de cancelamento fora do prazo direto na SEFAZ, conforme o estado.
3. Se quiser desfazer a venda no sistema (estoque, caixa), use **Cancelar Venda** na tela da venda. Com cupom fora do prazo o sistema avisa que só a **venda** será cancelada e que a nota continua válida na SEFAZ — avise o contador.

## Como cancelar uma venda que tem nota fiscal?
Palavras-chave: cancelar venda com nota, venda com cupom, desfazer venda, devolver estoque, cancelar venda nfce

1. Abra a venda em **Vendas**.
2. Se a nota ainda está no prazo: primeiro cancele a nota (no quadro **Nota Fiscal**, clique nos três pontinhos da nota → **Cancelar** → justificativa → **Confirmar Cancelamento**).
3. Volte à venda e clique em **Cancelar Venda**. O estoque volta, as contas a receber são canceladas e o valor sai do caixa na mesma forma de pagamento.

Se tentar cancelar a venda com nota ainda válida, o sistema bloqueia: "Esta venda tem um documento fiscal Autorizada. Cancele o cupom/nota aqui primeiro (botão Cancelar) — depois cancele a venda."

Se a NFC-e já passou dos 30 minutos, a confirmação de **Cancelar Venda** avisa que o cupom não pode mais ser cancelado; se continuar, só a **venda** é cancelada e a nota segue válida na SEFAZ. Nesse caso avise o contador para fazer o acerto fiscal (normalmente nota de devolução).

## Como fazer uma Carta de Correção (CC-e)?
Palavras-chave: carta de correcao, cce, cc-e, corrigir nota, corrigir nfe, erro na nota autorizada

A carta de correção existe só para **NF-e Autorizada** (não para NFC-e nem NFS-e).

1. Abra **Fiscal → Notas Fiscais** e clique em **Detalhes** na NF-e.
2. Clique em **CC-e** (no topo) ou em **Nova CC-e** no quadro de cartas.
3. Em **Texto da Correcao** escreva o que muda, com pelo menos 15 caracteres. Seja completo, por exemplo: "Onde se lê endereço de entrega Rua A, 10, leia-se Rua A, 100."
4. Clique em **Enviar CC-e**.
5. Deve aparecer "Carta de Correção #N autorizada pela SEFAZ!".

O quadro **Cartas de Correção (N/20)** lista todas, com data, texto, quem enviou, status (Autorizada, Rejeitada ou Pendente), protocolo e botões de **PDF** e **XML**.

Cada nova carta **substitui** a anterior: se precisar corrigir duas coisas, escreva as duas correções no texto da última carta.

## O que a Carta de Correção pode e não pode corrigir?
Palavras-chave: limite carta de correcao, o que corrigir cce, 20 cartas, prazo cce, 720 horas, cce rejeitada, cce rejeitada, erro carta de correcao, carta de correcao nao autorizada, limite cce, prazo cce

**Pode** corrigir erros de texto que não mudam imposto nem a identidade da operação, por exemplo: endereço de entrega, dados do transportador, descrição complementar, informações adicionais, unidade ou embalagem (sem mudar quantidade/valor).

**Não pode** corrigir (regra da SEFAZ):
- valores e impostos (preço, quantidade, base de cálculo, alíquota, diferença de valor);
- dados cadastrais que mudem o **destinatário** ou o **remetente** (trocar de cliente, CNPJ/CPF);
- a **data** de emissão ou de saída.

Nesses casos: cancele a nota (se ainda estiver no prazo) e emita outra, ou fale com o contador sobre nota complementar ou de devolução.

Limites:
- até **20 cartas** por NF-e ("Esta NF-e já atingiu o limite de 20 Cartas de Correção.");
- prazo de **720 horas (30 dias)** depois da autorização ("O prazo para emitir Carta de Correção foi excedido (720h após autorização).");
- só em nota autorizada e não cancelada.

Se a carta voltar **Rejeitada**, o motivo aparece em vermelho embaixo do texto da carta.

**Mensagens de erro da CC-e**
- **"Carta de correcao disponivel apenas para NF-e."** — NFC-e e NFS-e não têm carta de correção.
- **"Certificado digital inválido ou expirado..."** — renove o certificado.
- **"A SEFAZ está instável no momento..."** / **"Não foi possível conectar à SEFAZ..."** — tente em alguns minutos.

## Como inutilizar numeração de nota?
Palavras-chave: inutilizar, inutilizacao, numero pulado, pular numero, faixa de numeracao, buraco na sequencia

Inutilizar é avisar à SEFAZ que um número (ou faixa) **não será usado**, por exemplo quando a sequência pulou. Só para números que **nunca** viraram nota autorizada.

1. Abra **Fiscal → Notas Fiscais** e clique em **Inutilizar**.
2. Escolha o **Tipo**: **NF-e** ou **NFC-e**.
3. Informe a **Série**, o **Número Inicial** e o **Número Final** (para um só número, repita o mesmo nos dois).
4. Escreva a **Justificativa** (mínimo 15 caracteres).
5. Clique em **Inutilizar Numeração** e confirme.

A inutilização é **irreversível**. Pelas regras da SEFAZ ela deve ser feita, em geral, até o dia 10 do mês seguinte ao do número pulado. Na dúvida sobre quais números inutilizar, pergunte ao contador.

Se aparecer **"Erro ao inutilizar:"** seguido de um texto técnico (em inglês, citando tipos de dados), é falha do sistema e não da SEFAZ: não insista e chame o suporte com a série e a faixa de números. Depois de qualquer inutilização, confirme com o suporte ou o contador se ela foi registrada na SEFAZ.

## O que são os eventos Ator Interessado e Insucesso de Entrega?
Palavras-chave: ator interessado, insucesso de entrega, evento nfe, transportadora xml, 110150, 110192, entrega nao realizada

Na tela de uma **NF-e Autorizada** aparece o quadro **Eventos NFe**, com dois botões:

**Ator Interessado** (evento 110150): autoriza outro CNPJ a baixar o XML da nota — transportadora, marketplace, seguradora.
1. Clique em **Ator Interessado**.
2. Informe o **CNPJ do Ator**, a **Razão Social (opcional)** e o **Tipo de Ator** (Transportador, Autor do fato, Marketplace / Intermediador ou Outro).
3. Clique em **Registrar**.

**Insucesso de Entrega** (evento 110192): registra que a mercadoria não pôde ser entregue, permitindo voltar com a carga sem emitir nova NF-e.
1. Clique em **Insucesso de Entrega**.
2. Escolha o **Motivo**: Sem funcionamento do estabelecimento, Recusa do destinatário, Endereço não encontrado / inexistente ou Outros.
3. Informe **Data/hora da tentativa**; **Justificativa** é obrigatória para "Outros" (mínimo 15 caracteres); latitude e longitude são opcionais.
4. Clique em **Registrar**.

Cada evento aparece na lista com status (autorizado, pendente ou rejeitado) e protocolo.

## O que é a tela NFes Recebidas?
Palavras-chave: nfes recebidas, notas de fornecedor, notas contra meu cnpj, manifestacao do destinatario, sincronizar notas, compras

**Fiscal → NFes Recebidas** mostra as NF-e que **outras empresas emitiram contra o CNPJ da sua loja** (compras de fornecedores, por exemplo), buscadas na Receita.

- O sistema busca notas novas sozinho **a cada 4 horas**. Para buscar na hora, clique em **Sincronizar agora** ("N NF-e(s) recebida(s) foram sincronizadas." ou "Nenhuma NF-e nova na Receita.").
- Filtros: **Status** (Pendentes de manifestação, Já manifestadas ou um tipo específico), **Emitente (nome ou CNPJ)**, **De** e **Até**.
- Cada linha mostra data, emitente, chave, valor e a manifestação feita. Há botões para **DANFE**, **Manifestar** e dar entrada no estoque.

Se aparecer **"Configuração fiscal pendente."** e o botão estiver desligado, a emissão fiscal não está ativa ou a ligação com o emissor não foi encontrada para esta loja: confira **Fiscal → Configuração Fiscal**. Se ela estiver completa e mesmo assim a tela disser que falta token, chame o suporte.

Enquanto isso, para dar entrada de uma compra, use **Entrada por XML** (Estoque › Entrada por NF-e) com o arquivo que o fornecedor mandou.

## Como manifestar uma nota recebida (ciência, confirmação, desconhecimento, não realizada)?
Palavras-chave: manifestar, manifestacao, ciencia da emissao, confirmacao da operacao, desconhecimento da operacao, operacao nao realizada, 210210, 210200, 210220, 210240

1. Abra **Fiscal → NFes Recebidas**.
2. Na nota, clique em **Manifestar**.
3. Escolha o **Tipo de manifestação**:
   - **Ciência da Emissão** — "Tenho conhecimento da emissão desta NF-e." Libera o XML completo para dar entrada; não confirma nada.
   - **Confirmação da Operação** — "Confirmo que a operação foi realizada." Use quando a mercadoria chegou certa.
   - **Operação Não Realizada** — "A operação descrita não foi efetivamente realizada." Ex.: pedido desistido, mercadoria devolvida sem entrar.
   - **Desconhecimento da Operação** — "Desconheço esta operação — não autorizei a emissão." Ato grave: só para fraude ou nota emitida indevidamente contra o seu CNPJ.
4. **Justificativa** (mínimo 15 caracteres) é obrigatória para "Operação Não Realizada" e "Desconhecimento".
5. Confirme. Aparece "Manifestação "..." registrada com sucesso.".

Na dúvida entre confirmar ou recusar, pergunte ao contador. Os prazos da Receita para a manifestação conclusiva costumam ser de até 180 dias da emissão; fora dele aparece "O prazo para manifestação desta NF-e foi excedido.". "Esta NF-e já possui uma manifestação registrada." indica que já foi feita.

## Como dar entrada no estoque a partir de uma nota recebida?
Palavras-chave: entrada por nfe, entrada de mercadoria, xml do fornecedor, dar entrada estoque, nota recebida estoque, ciencia da operacao

Pela tela de notas recebidas:
1. Abra **Fiscal → NFes Recebidas**.
2. Se ainda não fez, faça a **Ciência da Emissão** na nota (botão **Manifestar**). Sem ela a Receita entrega só um resumo, sem os itens.
3. Clique no botão verde **Dar entrada dos produtos no estoque** da nota.
4. Confira o rascunho: ligue cada item ao produto do seu cadastro, escolha o estoque e confirme.

Depois disso a nota mostra a etiqueta **No estoque**.

Se aparecer "Não consegui baixar o XML completo desta nota. Faça a "Ciência da operação" em NFes Recebidas e tente de novo, ou envie aqui o arquivo XML que o fornecedor mandou.", faça a ciência e espere alguns minutos — ou use o arquivo XML do fornecedor.

Pelo arquivo do fornecedor: clique em **Entrada por XML** (ou vá a Estoque › Entrada por NF-e) e envie o XML. Esse caminho não depende da sincronização.

## O que é a tela NFSes Recebidas?
Palavras-chave: nfses recebidas, nota de servico recebida, servicos contratados, tomador, iss retido, sincronizar nfse

**Fiscal → NFSes Recebidas** lista as notas de serviço que **outros prestadores emitiram para a sua empresa** (você como tomador): contador, manutenção, internet etc.

- Colunas: **Emissão**, **Prestador** (nome e CNPJ), **Nº / Série**, **Serviço** (discriminação e item LC 116), **Valor**, **ISS** (com a etiqueta "ISS retido" quando for o caso) e **Status** (Autorizadas ou Canceladas). Notas do padrão nacional mostram "Portal Nacional".
- O sistema busca as notas sozinho **a cada 6 horas**; para buscar na hora clique em **Sincronizar agora**.
- Use os filtros de prestador, status e período.

Requisitos: emissão fiscal ativa e **NFS-e habilitada** na **Configuração Fiscal**. Sem isso aparecem "Fiscal desativado" ou "Esta unidade não está configurada para emitir/receber NFS-e.".

A disponibilidade depende da prefeitura: nem toda cidade disponibiliza as notas recebidas para consulta automática.

## Como funcionam os Backups XML?
Palavras-chave: backup xml, xml do mes, zip de xml, mandar xml ao contador, guardar xml, arquivo mensal

**Fiscal → Backups XML** monta um pacote **ZIP por mês** com todos os XMLs fiscais transmitidos (NF-e, NFC-e e NFS-e, autorizadas e canceladas), mais um índice das notas.

1. Abra **Fiscal → Backups XML**.
2. Na linha do mês:
   - **Pronto** — clique em **Baixar ZIP**;
   - **Não gerado** — clique em **Gerar**;
   - **em andamento** — mês atual, ainda recebendo notas.
3. Se emitiu ou cancelou nota depois do pacote ser gerado, clique em **Regerar**.

O sistema também gera os pacotes sozinho toda madrugada. É **obrigação do contribuinte guardar cópia própria** dos XMLs (por pelo menos 5 anos): baixe e guarde em local seguro, e é esse ZIP que normalmente se manda ao contador.

Se aparecer "Ative a emissão fiscal para gerar os pacotes mensais de XML.", a emissão fiscal da loja está desligada. Lojas com o mesmo CNPJ entram no mesmo pacote.

## O que mostra o Dashboard Fiscal?
Palavras-chave: dashboard fiscal, painel fiscal, resumo fiscal, top erros sefaz, saude por unidade, notas rejeitadas no mes

O **Dashboard Fiscal** mostra a visão consolidada dos **últimos 30 dias**:
- contadores de notas **Autorizadas**, **Pendentes**, **Rejeitadas** e **Canceladas**;
- **Por tipo de nota** (NF-e, NFC-e, NFS-e);
- **Top 5 erros SEFAZ** — as rejeições mais repetidas, ótimo para achar um erro de cadastro que afeta muitas vendas;
- **Saúde por unidade** — para cada loja: ligação com o emissor (Focus), validade do certificado, webhooks, último backup e situação da SEFAZ;
- gráfico **Emissões — últimos 14 dias**.

Hoje o painel não tem item próprio no menu lateral: acesse pelo endereço do sistema terminando em **/app/fiscal/dashboard**. Precisa de acesso a Notas Fiscais.

Para o detalhe de cada nota, use **Fiscal → Notas Fiscais**.

## Desconto geral da venda sai na nota?
Palavras-chave: desconto na nota, desconto geral, rateio desconto, desconto total, total do desconto difere, f4 desconto

Sim. O desconto dado no total da venda (por exemplo, 5% na venda inteira) é **dividido entre os itens** automaticamente, proporcional ao valor de cada um, e a soma fecha exatamente com o desconto total. Os centavos de arredondamento vão para o item de maior valor. Isso vale para NF-e e NFC-e.

Isso existe porque a SEFAZ confere se o desconto do total é igual à soma dos descontos dos itens; se não for, rejeita com "Total do Desconto difere do somatorio dos itens" (rejeição 537 na maioria dos estados).

Se uma nota com desconto ainda assim voltar com essa rejeição:
1. Confira se a venda não foi editada depois de feita (desconto alterado à mão).
2. Emita de novo pela venda (**Emitir NFC-e novamente** / **Emitir NF-e**).
3. Persistindo, chame o suporte com o número da venda.

Juros de parcelamento e acréscimo de cartão entram na nota como "outras despesas", também divididos entre os itens.

## O frete do pedido vai para a nota?
Palavras-chave: frete na nota, valor do frete, frete pedido, modalidade frete, outras despesas, total da nf difere

Sim, quando o frete faz parte do total da venda (por exemplo, pedido faturado com frete):

- **NF-e**: o frete vai no campo de frete, dividido entre os itens, e a modalidade fica "por conta do remetente".
- **NFC-e**: o cupom não aceita frete, então o valor entra como **outras despesas**, também dividido entre os itens.

Assim o total da nota bate com a soma dos itens + frete e com o pagamento. Antes desse ajuste, nota de pedido com frete voltava "Total da NF difere do somatorio dos Valores compoe o valor Total da NF" (rejeição 610).

Se ainda aparecer erro de total:
1. Confira se o total da venda é exatamente produtos − desconto + frete (+ juros, se houver).
2. Se a venda foi alterada depois do faturamento, chame o suporte com o número do pedido e da venda.

Frete cobrado à parte, fora do total da venda, não entra na nota.

## Cliente com Inscrição Estadual "ISENTO": como cadastrar?
Palavras-chave: ie isento, isento, inscricao estadual isenta, nao contribuinte, indicador ie, nfe sem tag ie, rejeicao 805

No cadastro do cliente (**Clientes** → editar, campo **Inscrição Estadual**, que aparece para pessoa jurídica):

- **Cliente contribuinte de ICMS** (loja, indústria, distribuidor): digite a IE com números (pode ter pontos).
- **Cliente isento de IE**: digite **ISENTO**. O ERP manda o cliente como "contribuinte isento", sem número. Nos estados que não aceitam esse tipo (AM, BA, CE, GO, MG, MS, MT, PE, RN, SE e SP — rejeição 805), o ERP manda automaticamente como "não contribuinte".
- **Não contribuinte** (pessoa física, clínica, escola, prestador de serviço sem IE): deixe o campo **vazio**.

Qualquer outro texto no campo (ex.: "não tem", "inseto") é tratado como vazio (não contribuinte).

Na dúvida se o cliente é contribuinte, peça a ele o cartão do CNPJ/consulta do Sintegra ou pergunte ao contador. IE errada leva a rejeições como 210 ("IE do destinatário inválida"), 232/233 (IE não informada/não cadastrada) ou 302 (denegada).

## Cliente sem endereço ou sem CPF/CNPJ: posso emitir nota?
Palavras-chave: cliente sem endereco, endereco incompleto, cliente sem cpf, nfe sem cliente, consumidor final, destinatario identificado

Depende do tipo de nota:

**NFC-e (cupom)**: não precisa de cliente, nem de endereço. Pode sair para "Consumidor Final" ou com CPF/CNPJ na nota. (Vendas de valor alto podem exigir identificação do consumidor — veja o tópico sobre limite.)

**NF-e**: exige cliente identificado. O ERP para antes de enviar e mostra o que falta:
- "NF-e exige destinatário identificado — informe o cliente."
- "Endereço incompleto do cliente NOME." — faltam **Logradouro**, **Cidade**, **UF** ou **CEP**.
- "NF-e exige CPF/CNPJ do destinatário — cadastre o documento do cliente NOME."

Como corrigir:
1. Abra **Clientes**, edite o cliente e preencha **CPF** (ou CNPJ), **CEP**, **Logradouro**, **Número**, **Bairro**, **Cidade** e **UF**. Sem número, deixe vazio que sai "S/N".
2. Salve.
3. Volte à venda e clique em **Emitir NF-e**.

Se a venda foi feita sem cliente, não é possível emitir NF-e para ela pelo ERP: emita NFC-e ou faça uma nova venda com o cliente.

## Como funciona a substituição tributária (ICMS-ST) interestadual?
Palavras-chave: substituicao tributaria, icms st, st interestadual, mva, calculadora st, cst 10, csosn 500, fcp st

O ERP calcula o **ICMS-ST** automaticamente na NF-e quando:
1. o produto tem um código de situação tributária **com substituição tributária** (CST de ST no regime normal; CSOSN de ST no Simples); e
2. o cliente é de **outro estado**.

O cálculo usa a tabela interna de alíquotas entre estados e a MVA (margem) padrão da operação, e preenche base, alíquota, valor do ST e FCP-ST. Venda dentro do mesmo estado não calcula ST.

Cuidados:
- ST depende de convênios e protocolos entre os estados e do tipo de mercadoria (NCM/CEST). **Quem define se o produto tem ST, qual CST/CSOSN e qual CEST usar é o contador**; o lojista grava no produto em **Dados Fiscais**.
- Produto com ST normalmente precisa de **CEST** — sem ele a SEFAZ rejeita (rejeição 806).
- A calculadora de ST hoje não tem tela própria para o usuário: o cálculo acontece dentro da emissão. Para simular valores antes de vender, peça ao contador.

Se a nota voltar com rejeição de ST (base, valor ou alíquota do ST), leve a mensagem ao contador e ao suporte.

## Quais verificações o ERP faz antes de enviar a nota?
Palavras-chave: nao foi possivel emitir, erro antes de enviar, validacao nfe, validacao nfce, ncm invalido, cfop nao definido, pre validacao

Para evitar rejeição, o ERP confere a venda antes de mandar para a SEFAZ. Se faltar algo, aparece "Não foi possível emitir a NF-e:" (ou NFC-e) com a lista:

**NF-e**
- "NF-e exige destinatário identificado — informe o cliente." → selecione o cliente na venda/pedido.
- "Endereço incompleto do cliente ..." → **Clientes** → editar → CEP, logradouro, cidade, UF.
- "NF-e exige CPF/CNPJ do destinatário..." → preencha o documento no cliente.
- "Item #N (produto): NCM inválido." → produto sem NCM ou com 00000000: **Produtos** → editar → **Dados Fiscais** → **NCM**.
- "Item #N (produto): CFOP não definido." → mesmo lugar, campo **CFOP**.
- "Responsável técnico não cadastrado..." → **Configuração Fiscal** → **Responsável técnico**.

**NFC-e**
- "Inscrição Estadual não cadastrada..." → **Gestão → Minhas Lojas → Editar** → **Inscrição Estadual**.
- "CSC / ID do CSC não configurados..." → **Configuração Fiscal** → cartão NFC-e.
- "Cliente ... é de XX ...: NFC-e não vale para outra UF — emita NF-e (modelo 55)."
- "Item #N: NCM inválido."

Também: "Emissao fiscal nao esta ativa para esta unidade." (ligue em **Configuração Fiscal**) e "A venda nao possui itens...". Depois de corrigir, emita de novo pela venda.

## Rejeição 539 (e 613) — Duplicidade de NF-e com diferença na chave de acesso
Palavras-chave: 539, duplicidade, diferenca na chave, numero ja usado, serie usada, outro sistema, nfce duplicada, 613, chave de acesso difere, nota autorizada depois rejeitada, chave diferente

Texto típico: **"Rejeição 539: Duplicidade de NF-e, com diferença na Chave de Acesso [chNFe: ...]"**.

O que significa: o **número + série** desta nota já foi usado por outra nota do mesmo CNPJ na SEFAZ — quase sempre emitida por **outro sistema** (o sistema antigo, ou outro emissor que ainda está em uso) ou por outra loja com o mesmo CNPJ fora do ERP. A chave entre colchetes é da nota que já existe; os dígitos dela mostram ano/mês, série e número.

Onde corrigir no ERP:
1. Abra **Fiscal → Configuração Fiscal**.
2. No cartão da nota rejeitada (**NF-e (DANFE)** ou **NFC-e (Cupom Fiscal)**), troque a **Série** para uma série **nunca usada** por nenhum sistema (pergunte ao contador quais já foram usadas).
3. Clique em **Salvar**.
4. Emita de novo pela venda (**Emitir NFC-e novamente** / **Emitir NF-e**).

Alternativa: pedir ao suporte para continuar a numeração a partir do último número usado no sistema antigo.

Dá para reemitir? **Sim**, depois de trocar a série. Não adianta repetir sem mudar nada: cada tentativa gasta um número e volta a mesma rejeição. Se o sistema antigo continua emitindo, cada sistema precisa ter a **sua** série. Avise o contador da série nova.

**Variação: rejeição 613 — "Chave de Acesso difere da existente em BD"**
Mesma causa da 539: o número e a série já pertencem a outra nota do mesmo CNPJ. Às vezes a nota parece autorizada e, numa consulta seguinte, aparece com a 613. Clique em **Consultar Status** na nota; se ficar **Rejeitada**, troque a série como acima e emita de novo. Avise o contador se alguma dessas notas chegou ao cliente como autorizada.

## Rejeição 204 — Duplicidade de NF-e
Palavras-chave: 204, duplicidade de nfe, nota ja autorizada, mesma nota enviada duas vezes, duplicada

Texto típico: **"Rejeição 204: Duplicidade de NF-e [nRec: ...]"**.

O que significa: a SEFAZ já recebeu **exatamente a mesma nota** (mesma chave). Acontece quando a nota foi enviada duas vezes — por exemplo, a primeira tentativa foi autorizada, mas a resposta demorou e houve um novo envio.

O que fazer:
1. Abra **Fiscal → Notas Fiscais** e procure as notas da mesma venda.
2. Se uma delas está **Pendente**, clique em **Consultar Status**: muitas vezes ela vira **Autorizada** e o problema acaba aí.
3. Abra a venda: se já existe uma nota **Autorizada**, a venda já está com nota — não emita outra.
4. Se nenhuma nota da venda ficou autorizada e a rejeição se repete, o número está ocupado por outro sistema: siga o tópico da **rejeição 539** (trocar a série).

Dá para reemitir? Só se a venda não tiver nota autorizada. Emitir duas notas válidas para a mesma venda é erro fiscal — se isso acontecer, cancele uma delas dentro do prazo.

## Rejeição 225 — Falha no Schema XML
Palavras-chave: 225, schema xml, erro na validacao do schema, falha no schema, xml invalido, campo vazio

Texto típico: **"Rejeição 225: Falha no Schema XML da NFe"** ou **"Erro na validação do Schema XML"**.

O que significa: a nota foi montada com algum campo em formato que a SEFAZ não aceita — campo obrigatório vazio, texto onde deveria haver número, tamanho errado. As causas mais comuns no ERP:

1. **Inscrição Estadual da loja vazia ou errada** → **Gestão → Minhas Lojas → Editar → Inscrição Estadual**.
2. **CFOP vazio no produto** → **Produtos** → editar → **Dados Fiscais** → **CFOP** (normalmente 5102).
3. **NCM com menos de 8 dígitos** ou com letras → campo **NCM** do produto.
4. **CEST** com formato errado (precisa ter 7 dígitos) → campo **CEST** do produto, ou deixe vazio se o contador disser que não precisa.
5. **CEP, telefone ou número** do cliente com caracteres estranhos → **Clientes** → editar.
6. **Código de barras** com letras ou tamanho fora do padrão → campo **Código de barras** do produto.

Às vezes a mensagem traz o nome do campo (ex.: "IE", "CFOP", "cEAN", "CEST"); isso ajuda a achar o cadastro certo.

Dá para reemitir? Sim, depois de corrigir. Se não encontrar a causa, mande ao suporte o número da venda e o texto completo da mensagem.

## Rejeição 778 — NCM inexistente (e "NCM inválido")
Palavras-chave: 778, ncm inexistente, ncm invalido, ncm errado, ncm 00000000, classificacao fiscal, codigo ncm

Texto típico: **"Rejeição 778: Informado NCM inexistente"**. Antes de enviar, o próprio ERP pode avisar **"Item #N (produto): NCM inválido."**.

O que significa: o código NCM do produto (8 dígitos que classificam a mercadoria) não existe na tabela oficial, está vazio, é 00000000 ou foi extinto numa atualização da tabela.

Onde corrigir no ERP:
1. Abra **Produtos** e edite o produto citado.
2. Vá à etapa **Dados Fiscais**.
3. No campo **NCM**, digite parte do nome do produto (ex.: "camiseta", "arroz") e escolha o código na lista, ou digite o código que o contador passou.
4. Clique em **Atualizar Produto**.
5. Volte à venda e emita a nota de novo.

Quem define o NCM certo é o **contador** (ou o NCM que vem na nota de compra do fornecedor); o lojista só grava no produto. NCM errado também pode mudar impostos, então não escolha "um parecido" sem confirmar.

Dica: se vários produtos dão o mesmo erro, o **Dashboard Fiscal** mostra os erros mais repetidos.

## Rejeição 529 — NCM obrigatório / NCM incompatível
Palavras-chave: 529, ncm obrigatorio, ncm ipi, ncm incompativel, ncm nao informado

Texto típico: mensagem com o código **529** falando de **NCM** (por exemplo, NCM de informação obrigatória para produto tributado pelo IPI, ou NCM não aceito para a operação).

O que significa: o NCM informado no item não serve para o que está sendo declarado — falta o NCM completo de 8 dígitos ou o produto tem IPI e o NCM não combina.

Onde corrigir no ERP:
1. **Produtos** → editar o produto → etapa **Dados Fiscais**.
2. Confira o **NCM** (8 dígitos, existente na tabela).
3. Se você é comércio e não indústria, confira com o contador se a alíquota de **IPI** do produto deve mesmo estar preenchida — normalmente comércio não destaca IPI e o campo fica 0.
4. Clique em **Atualizar Produto** e emita de novo pela venda.

O NCM e o IPI corretos são definidos pelo **contador**; o lojista grava no produto.

Dá para reemitir? Sim, depois de corrigir o cadastro.

## Rejeição 302 — Uso denegado: irregularidade fiscal do destinatário
Palavras-chave: 302, uso denegado, denegada, irregularidade fiscal do destinatario, destinatario irregular, ie suspensa, 301 emitente

Texto típico: **"Rejeição 302: Uso Denegado: Irregularidade fiscal do destinatário"**. (A **301** é a mesma coisa para o **emitente**: a sua empresa.)

O que significa: a SEFAZ consultou o cadastro do **cliente** e a Inscrição Estadual dele está irregular (suspensa, cancelada, baixada, inapta). Nota **denegada** é diferente de rejeitada: o número fica registrado como "uso denegado" e **não pode ser reaproveitado nem cancelado**.

O que fazer:
1. Confira a situação do cliente no Sintegra/SEFAZ (ou peça ao contador).
2. Se o cliente na verdade não é contribuinte de ICMS ou está com IE irregular, apague a IE no cadastro (**Clientes** → editar → **Inscrição Estadual** vazio) — a nota sai como "não contribuinte". Faça isso só com orientação do contador.
3. Se a IE estava digitada errada, corrija.
4. Emita uma nova NF-e pela venda.

Para a **301** (sua empresa irregular), o problema é no cadastro da sua empresa na SEFAZ: só o contador resolve; não adianta mudar nada no ERP.

Dá para reemitir? Sim, com nova nota (novo número), depois de resolver a causa.

## Rejeição 209 / 210 — IE do emitente ou do destinatário inválida
Palavras-chave: 209, 210, ie invalida, inscricao estadual invalida, ie do emitente, ie do destinatario, digito ie

Texto típico: **"Rejeição 209: IE do emitente inválida"** ou **"Rejeição 210: IE do destinatário inválida"**.

O que significa: o número da Inscrição Estadual não passou na conferência da SEFAZ (dígito verificador errado, tamanho errado para o estado, ou IE de outro estado).

Onde corrigir:
- **209 (emitente = sua loja)**: **Gestão → Minhas Lojas → Editar → Inscrição Estadual**. Copie a IE do cadastro oficial da empresa (o contador tem). Confira também a **UF** da loja.
- **210 (destinatário = cliente)**: **Clientes** → editar → **Inscrição Estadual**. Copie a IE do cartão do cliente. Confira se a **UF** do endereço do cliente é a mesma da IE. Se o cliente não tem IE, deixe vazio; se é isento, escreva **ISENTO**.

Depois de salvar, emita de novo pela venda.

Dá para reemitir? Sim, depois de corrigir o cadastro.

## Rejeição 232 / 233 / 805 — IE do destinatário não informada, não cadastrada ou isento não aceito
Palavras-chave: 232, 233, ie nao informada, ie nao cadastrada, nfe sem tag ie do destinatario, cliente contribuinte, sintegra, 805, contribuinte isento, isento nao aceito, indicador ie 2, isento sp mg

Textos típicos: **"Rejeição 233: IE do destinatário não cadastrada"**, **"Rejeição 232: IE do destinatário não informada"** ou **"NF-e sem tag IE do destinatario"**.

O que significa:
- **233**: a IE digitada no cliente não existe no cadastro da SEFAZ do estado dele (número errado, IE baixada, ou de outro estado).
- **232 / sem tag IE**: o cliente foi enviado como contribuinte, mas sem IE — por exemplo um texto no campo que não é número.

Onde corrigir:
1. **Clientes** → editar o cliente → **Inscrição Estadual**.
2. Escolha uma das três situações:
   - contribuinte: digite a IE correta (confira no Sintegra);
   - isento: escreva **ISENTO**;
   - não contribuinte (pessoa física, clínica, prestador de serviço): deixe **vazio**.
3. Confira também a **UF** do endereço do cliente.
4. Salve e emita de novo pela venda (**Emitir NF-e**).

Na dúvida se o cliente é contribuinte de ICMS, pergunte ao contador. Dá para reemitir? Sim.

**Variação: rejeição 805 — estado do cliente não aceita "contribuinte isento"**
Texto típico: mensagem com **805** dizendo que a UF do destinatário **não permite contribuinte isento de inscrição**.

O que significa: o cliente foi enviado como "contribuinte isento" (IE = ISENTO), mas o estado dele não usa essa categoria.

O ERP já trata isso: para clientes de AM, BA, CE, GO, MG, MS, MT, PE, RN, SE e SP com "ISENTO", a nota sai automaticamente como **não contribuinte**. Se mesmo assim aparecer a 805:
1. Confira a **UF** do cliente em **Clientes** → editar (pode estar errada).
2. Se o cliente não tem IE, deixe o campo **Inscrição Estadual** vazio em vez de "ISENTO".
3. Salve e emita de novo pela venda.

Dá para reemitir? Sim.

## Rejeição 694 — Não informado o grupo de ICMS para a UF de destino (DIFAL)
Palavras-chave: 694, difal, icms uf destino, partilha icms, consumidor final outro estado, diferencial de aliquota, ec 87

Texto típico: **"Rejeição 694: Não informado o grupo de ICMS para a UF de destino"**.

O que significa: é uma **venda para outro estado**, para **consumidor final que não tem IE** (pessoa física, clínica, empresa não contribuinte). Nessa situação a SEFAZ exige a partilha do ICMS entre os estados (o "DIFAL"), num bloco próprio da nota.

O que fazer:
1. Confira se o cliente realmente não é contribuinte (**Clientes** → **Inscrição Estadual**). Se ele tem IE válida, preencha — a venda deixa de ser "consumidor final" e a exigência muda.
2. Se o cliente é mesmo consumidor final de outro estado, o ERP hoje **não monta esse bloco de partilha** automaticamente. Leve o caso ao **contador** (alíquotas e se a sua empresa deve recolher o DIFAL) e ao **suporte**.
3. Enquanto isso não for resolvido, não há correção de cadastro que faça a nota passar.

Dá para reemitir? Só depois do ajuste; nota rejeitada não precisa ser cancelada.

## Rejeição 696 — Operação com não contribuinte deve indicar consumidor final
Palavras-chave: 696, nao contribuinte, consumidor final, operacao com nao contribuinte, indfinal

Texto típico: **"Rejeição 696: Operação com não contribuinte deve indicar operação com consumidor final"**.

O que significa: o cliente foi enviado como **não contribuinte** (sem IE), mas a nota disse que **não** era venda para consumidor final. Para quem não tem IE, a SEFAZ exige "consumidor final".

O ERP já marca consumidor final automaticamente quando o cliente não tem IE ou é ISENTO. Se aparecer esta rejeição:
1. Abra **Clientes** → editar o cliente e confira o campo **Inscrição Estadual**: se tem texto estranho, corrija (IE válida, **ISENTO** ou vazio).
2. Salve e emita de novo pela venda.
3. Se continuar, chame o suporte com o número da venda.

Dá para reemitir? Sim.

## Rejeição de CFOP incompatível com a operação
Palavras-chave: cfop invalido, cfop incompativel, cfop de operacao interna, cfop interestadual, iddest, cfop de entrada, cfop 5102, cfop 6102

Textos típicos: **"CFOP de operação interestadual e idDest diferente de 2"**, **"CFOP de operação interna e idDest diferente de 1"**, **"CFOP não permitido para NFC-e"** ou **"CFOP de entrada para NF-e de saída"** (números variam: 518, 520, 521, 523, 725 e parecidos).

O que significa: o CFOP do item não combina com o tipo de venda. Venda dentro do estado usa CFOP começando com **5**; para outro estado, com **6**; NFC-e só aceita alguns CFOPs de venda (como 5102, 5405); CFOP começando com 1 ou 2 é de **entrada** e não serve para venda.

O ERP já troca 5xxx ↔ 6xxx sozinho conforme o estado do cliente. Por isso, quando acontece:
1. Confira a **UF** do cliente (**Clientes** → editar) e da loja (**Gestão → Minhas Lojas → Editar**).
2. Confira o **CFOP** do produto em **Produtos** → editar → **Dados Fiscais** (para revenda normalmente 5102; com ST, 5405). Não use CFOP de entrada.
3. Salve e emita de novo pela venda.

O CFOP certo para cada produto é definido pelo **contador**; o lojista grava no produto. Dá para reemitir? Sim.

## Rejeição "NFC-e para operação interestadual"
Palavras-chave: nfce interestadual, nfce outro estado, nfce para operacao interestadual, cliente de outra uf

Texto típico: **"Rejeicao: NFC-e para operacao interestadual"** (no ERP também pode aparecer antes: "Cliente ... é de XX e a loja emite por YY: NFC-e não vale para outra UF — emita NF-e (modelo 55).").

O que significa: o cupom fiscal (NFC-e) só vale para venda presencial **dentro do estado** da loja. O cliente informado na venda tem endereço em outro estado.

O que fazer:
1. Se o cliente está de fato comprando na loja mas o cadastro tem a UF errada, corrija em **Clientes** → editar → **UF** e **Cidade**, salve e emita a NFC-e de novo pela venda.
2. Se o cliente é mesmo de outro estado, emita **NF-e**: abra a venda e clique em **Emitir NF-e** (o cliente precisa de CPF/CNPJ e endereço completo).

Dá para reemitir? Sim, como NF-e (ou como NFC-e depois de corrigir a UF errada).

## Rejeições 462, 463 e 464 — CSC da NFC-e inválido ou "CSC não configurado"
Palavras-chave: 462, 463, 464, 465, csc invalido, qr code, hash do qr code, id csc, token csc, csc revogado, csc nao configurado, csc ausente, id do csc nao configurado, nfce sem csc

Textos típicos: **"Rejeição 464: Código de Hash no QR-Code difere do calculado"**, **"Rejeição 462: Código Identificador do CSC no QR-Code não cadastrado na SEFAZ"**, **"Rejeição 463: Código Identificador do CSC no QR-Code foi revogado"** (e variações com 465, sobre o QR Code).

O que significa: o **CSC** ou o **ID CSC** informados no ERP não batem com os que a SEFAZ tem para o seu CNPJ — código digitado errado, ID trocado, CSC antigo revogado porque um novo foi gerado, ou CSC de homologação usado em produção (e vice-versa).

Onde corrigir:
1. Entre no portal da SEFAZ do seu estado (ou peça ao contador) e consulte o **CSC ativo** e o **ID** dele.
2. No ERP, abra **Fiscal → Configuração Fiscal** → cartão **NFC-e (Cupom Fiscal)**.
3. Cole o código em **CSC (Código de Segurança SEFAZ)** e o número em **ID CSC** (ex.: 1 ou 000001), sem espaços.
4. Clique em **Salvar** — a mensagem deve terminar com "Sincronizado com Focus NFe ✓". Se disser "falhou ao sincronizar", chame o suporte.
5. Emita a NFC-e de novo pela venda.

Dá para reemitir? Sim.

**Quando a mensagem é "CSC não configurado"**
O aviso do ERP "CSC / ID do CSC não configurados..." ou a resposta "CSC não configurado" significam que o código está vazio no ERP ou não chegou ao emissor. Preencha os dois campos como acima e salve. Se já estavam preenchidos, apague e cole o CSC de novo e salve (isso força o reenvio). Lojas com o mesmo CNPJ usam o mesmo CSC.

## Certificado digital vencido, revogado ou ausente
Palavras-chave: certificado vencido, certificado revogado, certificado expirado, e necessario informar o certificado digital, 280, 281, 284, 290, 291, 294, certificado invalido

Mensagens típicas: **"É necessário informar o certificado digital"**, **"Certificado digital inválido ou expirado"**, **"Rejeição 281: Certificado Transmissor Data Validade"**, **"Rejeição 284: Certificado Transmissor revogado"** (ou 280–286 / 290–295, que tratam do certificado de transmissão e de assinatura).

O que significa: o certificado A1 que assina as notas está **vencido**, foi **revogado** pela certificadora (por exemplo, quando se emite um novo e o antigo é cancelado), é inválido ou **não foi enviado** para esta loja.

Onde corrigir:
1. Abra **Fiscal → Configuração Fiscal** → cartão **Certificado Digital A1** e veja a etiqueta (**Válido**, **Expira em**, **VENCIDO** ou **Necessário**).
2. Se venceu ou foi revogado, compre/renove o A1 na certificadora.
3. Envie o arquivo .pfx com a senha e clique em **Enviar** (só o dono).
4. Emita de novo as notas que falharam, pela tela de cada venda.

No PDV as vendas continuam saindo com recibo enquanto o certificado não for trocado. Dá para reemitir? Sim, depois do certificado novo.

## Rejeição 213 — CNPJ do certificado diferente do emitente
Palavras-chave: 213, cnpj base, certificado de outro cnpj, certificado de outra empresa, cnpj do certificado difere

Texto típico: **"Rejeição 213: CNPJ-Base do Emitente difere do CNPJ-Base do Certificado Digital"**.

O que significa: a nota está sendo emitida por um CNPJ, mas assinada com o certificado de **outra empresa** (outra raiz de CNPJ). Filiais com a mesma raiz podem usar o certificado da matriz; empresas diferentes, não.

Onde corrigir:
1. Confira o **CNPJ** da loja em **Gestão → Minhas Lojas → Editar**.
2. Em **Fiscal → Configuração Fiscal → Certificado Digital A1**, envie o certificado do CNPJ correto. O próprio ERP recusa o envio quando o certificado é de outro CNPJ ("O certificado é do CNPJ ..., diferente do CNPJ desta unidade ...").
3. Emita de novo pela venda.

Se as lojas são de empresas (CNPJs) diferentes, cada uma precisa do seu próprio certificado. Dá para reemitir? Sim.

## Rejeição 590 / 591 — CST ou CSOSN incompatível com o regime tributário
Palavras-chave: 590, 591, csosn, cst, crt, regime tributario, simples nacional, informado csosn para emissor que nao e do simples

Textos típicos: **"Rejeição 591: Informado CSOSN para emissor que não é do Simples Nacional (CRT diferente de 1)"** ou **"Rejeição 590: Informado CST para emissor do Simples Nacional (CRT=1)"**.

O que significa: o regime tributário da empresa e o código fiscal do produto não combinam. Empresa do **Simples Nacional** usa **CSOSN** (3 dígitos: 102, 103, 300, 400, 500...); empresa do **Lucro Presumido/Real** usa **CST** (2 dígitos: 00, 10, 20, 40, 41, 60...).

Onde corrigir:
1. Confira o **Regime tributário da empresa** em **Fiscal → Configuração Fiscal** (cartão do topo). Ele tem que ser igual ao da empresa na Receita — confirme com o contador. Só o dono altera (**Salvar regime**).
2. Se o regime está certo, corrija o produto: **Produtos** → editar → **Dados Fiscais** → campo **CSOSN** (Simples) ou **CST** (regime normal). A tela já mostra a lista certa para o seu regime.
3. Clique em **Atualizar Produto** e emita de novo pela venda.

Se você mudou de regime recentemente, todos os produtos precisam ser revisados. O código certo é definido pelo **contador**. Dá para reemitir? Sim.

## Rejeição 703 / 704 / 228 — Data e hora de emissão
Palavras-chave: 703, 704, 228, data de emissao, data hora posterior, data atrasada, relogio, fuso horario, nota com data antiga

Textos típicos: **"Rejeição 703: Data-Hora de Emissão posterior ao horário de recebimento"**, **"Rejeição 704: NFC-e com Data-Hora de emissão atrasada"** ou **"Rejeição 228: Data de Emissão muito atrasada"**.

O que significa: a data/hora da nota está no futuro ou atrasada demais em relação ao relógio da SEFAZ. Na NFC-e a tolerância de atraso é curta (minutos), por isso cupom guardado para emitir muito tempo depois, ou relógio/fuso errado, causa a rejeição.

O que fazer:
1. Tente emitir de novo agora pela venda (**Emitir NFC-e novamente** / **Emitir NF-e**): a nova tentativa usa a hora atual.
2. Se a rejeição se repete em todas as notas, pode ser horário/fuso da loja ou do servidor: chame o suporte informando o horário que aparece na nota e o horário real.
3. Venda antiga (de outro dia) que ficou sem cupom: a NFC-e sairá com a data de hoje. Se a data precisa ser a original, fale com o contador.

Dá para reemitir? Sim.

## Rejeição 220 / 501 — Prazo de cancelamento vencido
Palavras-chave: 220, 501, prazo de cancelamento, cancelamento intempestivo, autorizada ha mais de, nao consigo cancelar

Textos típicos: **"Rejeição 220: NF-e autorizada há mais de 7 dias (168 horas)"**, **"Rejeição 501: Prazo de cancelamento superior ao previsto na Legislação"** ou, no ERP, **"O prazo para cancelamento desta NFC-e foi excedido (máximo 30 minutos após autorização). Emita uma nota de devolução."**.

O que significa: o prazo para cancelar a nota acabou. Em geral: **NFC-e 30 minutos** e **NF-e 24 horas** depois da autorização (alguns estados têm regras diferentes).

O que fazer:
1. Não tente cancelar de novo pelo ERP: a resposta será a mesma e a nota continua **Autorizada**.
2. Fale com o **contador**: a saída normal é uma nota de **devolução** (entrada) que anula a operação, ou pedido de cancelamento extemporâneo na SEFAZ, conforme o estado.
3. Para desfazer a venda no sistema (estoque, caixa), use **Cancelar Venda** na tela da venda e confirme que quer cancelar só a venda.

Dá para "reemitir"? Não é o caso: a nota original continua válida.

## Rejeição 999 — Erro não catalogado
Palavras-chave: 999, erro nao catalogado, erro desconhecido, erro generico sefaz

Texto típico: **"Rejeição 999: Erro não catalogado"**.

O que significa: a própria SEFAZ teve um erro interno e não soube dizer o motivo. Na maioria das vezes **não é problema do seu cadastro**: é instabilidade do serviço.

O que fazer:
1. Veja o status da SEFAZ em **Fiscal → Configuração Fiscal** (faixa **SEFAZ (UF)**).
2. Espere alguns minutos e emita de novo pela venda (**Emitir NFC-e novamente** / **Emitir NF-e**).
3. Se a mesma venda volta com 999 várias vezes seguidas e outras vendas passam, o problema pode estar em algum dado incomum dessa venda (ex.: caractere especial na descrição, valor zerado). Chame o suporte com o número da venda.

Dá para reemitir? Sim.

## Rejeição 108 / 109 — Serviço da SEFAZ paralisado
Palavras-chave: 108, 109, servico paralisado, sefaz fora do ar, sefaz offline, sem previsao, instabilidade, 999

Textos típicos: **"Rejeição 108: Serviço Paralisado Momentaneamente (curto prazo)"** ou **"Rejeição 109: Serviço Paralisado sem Previsão"**. O ERP também pode mostrar "A SEFAZ está instável no momento. Aguarde alguns minutos e tente novamente.".

O que significa: o serviço de autorização da SEFAZ do seu estado está fora do ar. Não há nada errado na nota.

O que fazer:
1. Confira a faixa **SEFAZ (UF)** em **Fiscal → Configuração Fiscal** (Online / Instável / Offline).
2. Continue vendendo: no PDV a venda não trava e sai o recibo.
3. Quando a SEFAZ voltar, emita as notas que faltaram pela tela de cada venda (**Emitir NFC-e novamente** ou **Emitir NF-e**). A NFC-e sairá com a data/hora da nova emissão.

Dá para reemitir? Sim, quando o serviço voltar.

## Rejeições de total da nota (531, 532, 564, 537, 610)
Palavras-chave: 531, 532, 564, 537, 535, 610, total difere, somatorio dos itens, total da nf, total do desconto, total do produto, arredondamento

Textos típicos: **"Total da BC ICMS difere do somatório dos itens"** (531), **"Total do ICMS difere do somatório dos itens"** (532), **"Total do Produto / Serviço difere do somatório dos itens"** (564), **"Total do Desconto difere do somatorio dos itens"** (537), **"Total da NF difere do somatorio dos Valores compoe o valor Total da NF"** (610).

O que significa: a soma dos itens não bate com o total da nota. A conta que a SEFAZ faz é: produtos − desconto + frete + outras despesas = total.

O ERP já divide desconto geral, frete do pedido e juros/acréscimo de cartão entre os itens para a conta fechar. Quando ainda aparece:
1. Confira a venda: o total é igual a produtos − desconto + frete + juros? Venda editada depois de fechada pode ficar com números que não fecham.
2. Confira os produtos com **alíquota de ICMS** preenchida no regime normal (531/532): alíquota com muitas casas ou base diferente pode gerar diferença de centavos.
3. Emita de novo pela venda.
4. Persistindo, chame o suporte com o número da venda e o texto exato da mensagem.

Dá para reemitir? Sim.

## Rejeição de município ou endereço (272 a 275, "Município do destinatário inválido")
Palavras-chave: 272, 273, 274, 275, municipio invalido, codigo ibge, cidade invalida, municipio do destinatario, endereco errado, uf errada

Textos típicos: **"Município do destinatário inválido: Nome: - - UF: SP"**, **"Código Município do Destinatário: dígito inválido"**, **"Código Município do Emitente: difere da UF do emitente"** (272 a 275).

O que significa: a cidade não foi encontrada ou não pertence à UF informada — cidade em branco, escrita diferente do nome oficial, ou UF trocada. Pode ser no cadastro do **cliente** (destinatário) ou da **loja** (emitente).

Onde corrigir:
- **Destinatário**: **Clientes** → editar → **CEP**, **Cidade** e **UF**. Digite o CEP para o sistema preencher a cidade oficial; escreva o nome completo e com acento como no IBGE (ex.: "São José dos Campos", não "S. J. Campos").
- **Emitente**: **Gestão → Minhas Lojas → Editar** → **CEP**, **Cidade** e **UF** da loja.

Depois de salvar, emita de novo pela venda. A mensagem "Nome: - -" indica cidade **vazia**. Dá para reemitir? Sim.

## Rejeição 237 / 208 — CPF ou CNPJ do destinatário inválido
Palavras-chave: 237, 208, cpf invalido, cnpj invalido, documento do cliente, cpf na nota errado, digito verificador

Textos típicos: **"Rejeição 237: CPF do destinatário inválido"** ou **"Rejeição 208: CNPJ do destinatário inválido"**.

O que significa: o CPF/CNPJ informado tem dígito verificador errado ou número de tamanho incorreto.

Onde corrigir:
- Se veio do **cadastro do cliente**: **Clientes** → editar → **CPF** (ou CNPJ). Corrija e salve.
- Se foi digitado no **PDV** em **CPF/CNPJ na nota**: o número digitado estava errado. Na tela da venda, a NFC-e só pode ser reemitida com o cliente cadastrado — então cadastre o cliente com o CPF certo e ligue-o à venda, ou emita sem CPF se o cliente concordar.

Depois, emita de novo pela venda (**Emitir NFC-e novamente** / **Emitir NF-e**).

Dica: confira o número com o documento do cliente; trocar dois dígitos é o erro mais comum. Dá para reemitir? Sim.

## Rejeição 806 — CEST não informado (substituição tributária)
Palavras-chave: 806, cest, cest obrigatorio, cest nao informado, substituicao tributaria, cest invalido

Texto típico: **"Rejeição 806: Operação com ICMS-ST sem informação do CEST"** (ou mensagens dizendo CEST inválido / incompatível com o NCM).

O que significa: o produto está com código de **substituição tributária** (ex.: CSOSN 500, CST 10 ou 60) ou tem NCM que está na lista de ST, e a nota não trouxe o **CEST** (código de 7 dígitos) — ou trouxe um CEST que não combina com o NCM.

Onde corrigir:
1. **Produtos** → editar → etapa **Dados Fiscais**.
2. Preencha o campo **CEST** (formato 00.000.00) com o código que o contador indicar para aquele NCM.
3. Confira se o **CST/CSOSN** de ST está correto para o produto.
4. Clique em **Atualizar Produto** e emita de novo pela venda.

O CEST e o enquadramento em ST são definidos pelo **contador**; o lojista grava no produto. Dá para reemitir? Sim.

## Rejeição de GTIN / código de barras (611, 612, 882 a 885)
Palavras-chave: 611, 612, 882, 883, 884, 885, gtin, ean, cean, codigo de barras invalido, sem gtin, cadastro centralizado de gtin

Textos típicos: **"Rejeição 611: cEAN inválido"**, **"Rejeição 612: cEANTrib inválido"**, ou mensagens 882–885 sobre o **GTIN** não existir no Cadastro Centralizado de GTIN, ou não combinar com o NCM.

O que significa: o ERP envia o **Código de barras** do produto como GTIN/EAN na nota. Se o código não é um EAN oficial (por exemplo, código interno criado pela loja, código com letras, ou de tamanho diferente de 8, 12, 13 ou 14 dígitos), ou se o EAN não está registrado, a SEFAZ rejeita. Produto **sem** código de barras sai como "SEM GTIN", o que é aceito.

Onde corrigir:
1. **Produtos** → editar → campo **Código de barras**.
2. Se o código é o EAN da embalagem, confira dígito a dígito.
3. Se é um código interno da loja (etiqueta própria), apague-o desse campo — o produto sai como "SEM GTIN". Atenção: sem o código, o leitor do PDV deixa de achar o produto por ele; use o **SKU** ou o código interno para buscar, e fale com o suporte se precisar manter a etiqueta própria.
4. Clique em **Atualizar Produto** e emita de novo pela venda.

Dá para reemitir? Sim.

## Rejeição 972 — Responsável técnico obrigatório
Palavras-chave: 972, responsavel tecnico, informacoes do responsavel tecnico, csrt, nt 2018 003

Texto típico: **"Rejeição 972: Obrigatória as informações do responsável técnico"** (ou mensagem parecida citando o responsável técnico). O ERP costuma barrar antes: "Responsável técnico não cadastrado — obrigatório desde a NT 2018/003.".

O que significa: a SEFAZ exige o bloco de responsável técnico (CNPJ, contato, e-mail e telefone) na NF-e.

Onde corrigir:
1. Abra **Fiscal → Configuração Fiscal** (dono).
2. No cartão **Responsável técnico** preencha **CNPJ/CPF**, **Nome do contato**, **E-mail** e **Telefone**. O botão **Usar meus dados** ajuda.
3. Clique em **Salvar** e emita de novo pela venda.

Observação: a tela de configuração cita o código 778 para esse caso, mas a 778 é a rejeição de NCM inexistente; para responsável técnico procure a mensagem que fala em "responsável técnico". Dá para reemitir? Sim.

## Rejeição por falta de IBS/CBS (Reforma Tributária)
Palavras-chave: ibs, cbs, grupo ibs cbs, reforma tributaria rejeicao, cclasstrib invalido, cst ibs cbs, nt 2025 002

Mensagens típicas: rejeições citando **IBS**, **CBS**, **IBSCBS**, **cClassTrib** ou **CST do IBS/CBS** (grupo obrigatório, código de classificação inválido, CST incompatível).

O que significa: desde 03/08/2026 a SEFAZ exige o grupo IBS/CBS nas notas de empresas do **Lucro Presumido e Real**. O ERP envia sozinho para essas empresas; o problema costuma ser:
1. **Regime errado no ERP**: a empresa é do regime normal, mas está cadastrada como Simples (então o grupo não vai). Confira em **Fiscal → Configuração Fiscal → Regime tributário da empresa** (só o dono altera; confirme com o contador).
2. **Código do produto digitado errado**: em **Produtos** → editar → **Dados Fiscais**, os campos **CST IBS/CBS** e **Classificação (cClassTrib)** devem ficar em branco (usa 000 / 000001) ou ter o código exato que o contador passou.

Depois de corrigir, emita de novo pela venda. Para empresa do **Simples Nacional**, a exigência começa em 04/01/2027; até lá só vai o grupo se você ligou IBS/CBS no cartão **Reforma Tributária**. Dá para reemitir? Sim.

## NFC-e de valor alto sem CPF do consumidor
Palavras-chave: limite nfce, nfce valor alto, identificacao do consumidor, cpf obrigatorio, nfce acima do limite, consumidor nao identificado

Mensagens típicas: rejeição dizendo que é **obrigatória a identificação do consumidor** para NFC-e acima de determinado valor, ou que o **valor da NFC-e excede o limite** permitido.

O que significa: cada estado define um valor a partir do qual o cupom fiscal precisa ter o CPF/CNPJ do comprador, e alguns estados têm um valor máximo para NFC-e. Acima disso, a venda deve sair com identificação ou como NF-e.

O que fazer:
1. Abra a venda em **Vendas**.
2. Para NFC-e: cadastre o cliente (com CPF/CNPJ) e ligue-o à venda, depois **Emitir NFC-e novamente**. Nas próximas vendas, informe o **CPF/CNPJ na nota** no PDV antes de finalizar.
3. Para valores acima do máximo do estado: emita **NF-e** (botão **Emitir NF-e**), com cliente e endereço completos.

O valor exato depende do seu estado — o contador confirma. Dá para reemitir? Sim.

## Token da Focus NFe inválido, não configurado ou rate limit
Palavras-chave: token invalido, token focus, 401, nao autorizado, token focus nfe nao configurado, permissao negada, focus nfe, rate limit, muitas requisicoes, 429, aguarde segundos, limite de envio, focus recusou

Mensagens típicas: **"Token Focus NFe inválido. Verifique as configurações fiscais."**, **"Token invalido ou sem permissao."**, **"Token Focus NFe não configurado para esta unidade (ambiente: ...)"** ou **"Ative a emissão fiscal e informe o token Focus NFe antes de sincronizar."**.

O que significa: o ERP não conseguiu se identificar no emissor de notas (Focus NFe) para esta loja e este ambiente. Não é rejeição da SEFAZ.

O que fazer:
1. Abra **Fiscal → Configuração Fiscal**.
2. Se aparece **"Empresa gerenciada pela plataforma."**, confira se o status mostra **homologação ✓** e **produção ✓** — o ambiente escolhido precisa ter o seu ✓. Se faltar, chame o suporte.
3. Se aparece **"Ainda não conectada."**, clique em **Salvar** e aguarde alguns minutos.
4. Em contas com campo **Token Focus NFe**, cole o token do ambiente certo e clique em **Testar**.
5. Confira se **"Sim, emitimos"** está marcado.

Depois, emita de novo pela venda. Dá para reemitir? Sim.

**Outra mensagem do emissor: "Focus NFe recusou (rate limit)"**
Mensagem típica: **"Focus NFe recusou (rate limit). Aguarde N s antes de novas requisições."**.

O que significa: foram feitos muitos pedidos ao emissor de notas em pouco tempo (por exemplo, muitas emissões, consultas ou sincronizações seguidas). O emissor pede uma pausa. Não é rejeição e não há nada errado na nota.

O que fazer:
1. Espere o tempo indicado (normalmente 1 minuto).
2. Emita ou consulte de novo.
3. Evite clicar várias vezes em **Emitir**, **Consultar Status** ou **Sincronizar agora** seguidas.

Se acontecer com frequência no movimento normal da loja, avise o suporte. Dá para reemitir? Sim, depois da pausa.

## "Empresa não habilitada" ou "unidade não provisionada" no emissor
Palavras-chave: empresa nao habilitada, nao habilitada para emitir, habilita nfce, nao provisionada, saude focus, empresa nao encontrada focus

Mensagens típicas: **"Empresa não habilitada para emitir NFC-e"** (ou NF-e / NFS-e), **"Esta unidade ainda não foi provisionada na Focus NFe..."**, **"A unidade não foi encontrada na Focus NFe..."**.

O que significa: o cadastro da loja no emissor de notas ainda não tem aquele tipo de nota liberado, ou a loja ainda não foi criada lá. Não é rejeição da SEFAZ.

O que fazer:
1. Abra **Fiscal → Configuração Fiscal** (dono).
2. Confira se a chave **Habilitar** do tipo de nota está ligada (NF-e, NFC-e ou NFS-e).
3. Clique em **Salvar**: a configuração é reenviada ao emissor e a mensagem deve terminar com "Sincronizado com Focus NFe ✓" (ou "Provisionamento na Focus NFe em segundo plano.").
4. Aguarde alguns minutos e emita de novo pela venda.
5. Se a mensagem de sucesso disser "Configuração salva, mas falhou ao sincronizar com Focus: ...", ou o erro continuar, chame o suporte.

Dá para reemitir? Sim, depois da habilitação.

## Nota parada em "Pendente" há muito tempo
Palavras-chave: nota pendente, processando, nao autoriza, demora para autorizar, processando autorizacao, nfe pendente, nfse pendente

O que significa: a nota foi enviada e ainda não chegou resposta da SEFAZ ou da prefeitura. NF-e costuma resolver em segundos; NFS-e pode levar minutos (depende da prefeitura).

O que fazer:
1. Abra a nota em **Fiscal → Notas Fiscais** e clique em **Consultar Status** (na lista, o botão de seta circular). Se mudar para **Autorizada** ou **Rejeitada**, a tela recarrega.
2. Se continuar **Pendente**, veja a faixa **SEFAZ (UF)** em **Fiscal → Configuração Fiscal**: SEFAZ instável deixa notas paradas.
3. **Não emita outra nota** para a mesma venda enquanto houver uma pendente: as duas podem ser autorizadas e você terá nota em dobro. Por isso a tela da venda não mostra botão de nova emissão enquanto existe nota pendente.
4. Se ficar pendente por mais de algumas horas, chame o suporte com o número da nota (campo **Focus Ref** em **Dados Tecnicos (Focus NFe)**).

Uma consulta com erro mostra "Erro: ..." ou "Erro ao consultar status." — tente de novo em alguns minutos.

## Erros ao cancelar nota (nota não autorizada, já cancelada, SEFAZ instável)
Palavras-chave: erro ao cancelar, nao foi possivel cancelar, nota nao autorizada, ja cancelada, somente notas autorizadas, cancelamento falhou

Mensagens que podem aparecer ao clicar em **Confirmar Cancelamento** e o que fazer:

- **"Somente notas autorizadas podem ser canceladas."** — a nota está Pendente, Rejeitada ou já Cancelada. Rejeitada não precisa cancelar; Pendente: clique em **Consultar Status** primeiro.
- **"Esta nota não está autorizada na SEFAZ e portanto não pode ser cancelada."** — a SEFAZ não tem essa nota como autorizada. Clique em **Consultar Status** para atualizar.
- **"Esta NFC-e já foi cancelada anteriormente."** — clique em **Consultar Status**; o status deve virar Cancelada.
- **"O prazo para cancelamento ... foi excedido"** — veja o tópico de prazo de cancelamento (rejeição 220/501).
- **"Certificado digital inválido ou expirado..."** — renove o certificado em **Configuração Fiscal**.
- **"A SEFAZ está instável no momento..."** ou **"Não foi possível conectar à SEFAZ para cancelar..."** — tente em alguns minutos, ainda dentro do prazo.
- **"Erro inesperado ao cancelar a nota..."** — chame o suporte.

Importante: depois de cancelar, confira se o status ficou **Cancelada** e se apareceu o quadro **Cancelamento** com protocolo. Se a mensagem disse sucesso mas a nota continua **Autorizada** após **Consultar Status**, ela segue válida na SEFAZ — chame o suporte ainda dentro do prazo.

## Erros ao emitir NFS-e (prefeitura ou Portal Nacional)
Palavras-chave: erro nfse, nfse rejeitada, prefeitura rejeitou, item lista servico, inscricao municipal, codigo do servico nacional, cserv

Mensagens do ERP antes de enviar:
- **"Informe a descrição do serviço (discriminação)..."** — preencha **Descrição / Discriminacao do Serviço**.
- **"Valor do serviço precisa ser maior que zero."**
- **"Informe o código do serviço no padrão nacional (cServ) ou o item da lista LC 116."** — aparece quando a loja usa o **Padrão da NFS-e = Nacional (Portal RFB)**. Confira o **Item LC 116** em **Configuração Fiscal**; se ele está preenchido e o erro continua, é uma limitação conhecida da tela de emissão no padrão nacional: chame o suporte.
- **"Emissao fiscal nao esta ativa para esta unidade."** — ligue em **Configuração Fiscal**.

Rejeições da prefeitura variam por cidade, mas as causas mais comuns são:
1. **Inscrição Municipal** da loja ausente ou errada → **Gestão → Minhas Lojas → Editar → Inscrição Municipal**.
2. **Item LC 116** ou **Código de Tributação Municipal** que a prefeitura não aceita para a sua empresa → **Configuração Fiscal → NFS-e (Serviços)** (o contador informa os códigos).
3. **Alíquota ISS** diferente da cadastrada na prefeitura.
4. **Regime Especial** ou opção pelo Simples incorretos.
5. Dados do tomador (cliente) incompletos.

Depois de corrigir, emita uma nova NFS-e pelo botão **Emitir NFS-e**.

## A tela da venda disse "emitida com sucesso", mas a nota não está autorizada
Palavras-chave: emitida com sucesso mas rejeitada, mensagem de sucesso, nota nao autorizou, conferir status, enviada para autorizacao

Ao emitir pela tela da venda, a mensagem verde (**"NF-e enviada para autorizacao com sucesso!"** ou **"NFC-e emitida com sucesso!"**) significa que o pedido foi **enviado**, não necessariamente que a SEFAZ aprovou. O resultado real é o **status** da nota.

Como conferir:
1. Na tela da nota que abriu, veja a etiqueta ao lado do número: **Autorizada**, **Pendente** ou **Rejeitada**.
2. Se **Pendente**, clique em **Consultar Status** depois de alguns segundos.
3. Se **Rejeitada**, abra **Dados Tecnicos (Focus NFe)** e leia **Focus Mensagem** para saber o motivo. Corrija e emita de novo pela venda.

Na tela da venda, o quadro **Nota Fiscal** também mostra o status de cada tentativa. Só entregue o DANFE ou o cupom ao cliente quando a nota estiver **Autorizada**. No PDV, quando a NFC-e é rejeitada, o aviso amarelo "Cupom fiscal (NFC-e) não emitido — saiu recibo." já aparece na hora.

## Nota emitida em homologação por engano: o que fazer?
Palavras-chave: nota em homologacao, nota de teste, sem valor fiscal, esqueci de mudar para producao, ambiente errado

Se a nota mostra a etiqueta **Homologacao** na tela de detalhes, ela foi para o ambiente de **testes** da SEFAZ: **não tem valor fiscal** e não existe para a Receita.

O que fazer:
1. Não é preciso cancelar a nota de homologação.
2. Abra **Fiscal → Configuração Fiscal** (dono), mude **Ambiente** para **Produção (real)** e clique em **Salvar**. Para produção o certificado A1 precisa estar enviado e válido.
3. Abra a venda e emita a nota de novo (**Emitir NFC-e** / **Emitir NF-e**). Se a venda já tem uma nota de homologação autorizada, os botões podem não aparecer: nesse caso chame o suporte para liberar a nova emissão.
4. Entregue ao cliente o documento novo; o cupom de homologação não vale.

Dica: as notas de homologação continuam aparecendo na lista de **Notas Fiscais**; use o detalhe da nota para ver o ambiente de cada uma.
