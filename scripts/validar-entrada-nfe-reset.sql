SET FOREIGN_KEY_CHECKS=0;
DELETE FROM nfe_entrada_itens; DELETE FROM nfe_entradas; DELETE FROM produto_fornecedor_codigos;
DELETE FROM estoque_movimentacoes WHERE origem_tipo LIKE '%NFeEntrada%';
DELETE FROM contas_pagar; DELETE FROM nfes_recebidas;
DELETE FROM produtos WHERE id > 3; DELETE FROM fornecedores WHERE id > 1;
UPDATE produtos SET preco_custo = 350.00 WHERE id = 2;
SET FOREIGN_KEY_CHECKS=1;
