#!/usr/bin/env bash
# Validação ✅/❌ da Entrada por NF-e (23/10/2026).
# Roda contra um ambiente DESCARTÁVEL (nunca produção): app em $BASE, MySQL acessível
# por `docker exec $DB_CT mysql`. XMLs de teste: python3 scripts/validar-entrada-nfe-xmls.py <pasta>; zerar entre rodadas: scripts/validar-entrada-nfe-reset.sql.
#   BASE=http://127.0.0.1:8097 DB_CT=ent-mysql XML=/caminho/dos/xmls ./validar-entrada-nfe.sh
set -u
BASE=${BASE:-http://127.0.0.1:8097}
DB_CT=${DB_CT:-ent-mysql}
XML=${XML:?informe XML=pasta dos xmls de teste}
TMP=$(mktemp -d)
OK=0; FAIL=0

check() { # descrição, condição (string "1" = passou)
    if [ "$2" = "1" ]; then OK=$((OK+1)); echo "✅ $1"; else FAIL=$((FAIL+1)); echo "❌ $1  (obtido: ${3:-})"; fi
}
sql() { docker exec "$DB_CT" mysql -uerp -perp erp_teste -N -B -e "$1" 2>/dev/null; }
login() { # cookiejar email senha
    rm -f "$1"
    local t; t=$(curl -s -c "$1" -b "$1" "$BASE/login" | grep -o 'name="_token" value="[^"]*"' | head -1 | cut -d'"' -f4)
    curl -s -c "$1" -b "$1" -o /dev/null -d "_token=$t&email=$2&password=$3" "$BASE/login"
}
token() { curl -s -c "$1" -b "$1" "$BASE/app/estoque/entradas-nfe" | grep -o 'name="_token" value="[^"]*"' | head -1 | cut -d'"' -f4; }
upload() { # cookiejar arquivo → imprime "codigo url"
    local t; t=$(token "$1")
    curl -s -c "$1" -b "$1" -o /dev/null -w "%{http_code} %{redirect_url}" -F "_token=$t" -F "xml=@$2" "$BASE/app/estoque/entradas-nfe"
}
flash() { curl -s -c "$1" -b "$1" "$2" | tr '\n' ' ' | grep -oE "$3" | head -1; }
itens_ids() { curl -s -c "$1" -b "$1" "$BASE/app/estoque/entradas-nfe/$2" | grep -oE 'name="itens\[[0-9]+\]\[acao\]"' | grep -oE '[0-9]+' ; }
confirmar() { # cookiejar entrada_id "campos extras" → código e redirect
    local t; t=$(token "$1")
    curl -s -c "$1" -b "$1" -o /dev/null -w "%{http_code} %{redirect_url}" -d "_token=$t&$3" "$BASE/app/estoque/entradas-nfe/$2/confirmar"
}

DONO=$TMP/dono; GER=$TMP/ger; CX=$TMP/cx; VEN=$TMP/ven; D2=$TMP/d2
login "$DONO" dono@demo.com dono123
login "$GER" gerente@demo.com gerente123
login "$CX" caixa@demo.com caixa123
login "$VEN" vendedor@demo.com vendedor123
login "$D2" dono2@demo.com dono123

echo "== Acesso"
r=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/app/estoque/entradas-nfe")
check "sem login não entra (302)" "$([ "$r" = 302 ] && echo 1)" "$r"
r=$(curl -s -b "$CX" -o /dev/null -w "%{http_code}" "$BASE/app/estoque/entradas-nfe")
check "caixa não entra (403)" "$([ "$r" = 403 ] && echo 1)" "$r"
r=$(curl -s -b "$VEN" -o /dev/null -w "%{http_code}" "$BASE/app/estoque/entradas-nfe")
check "vendedor não entra (403/302)" "$([[ "$r" = 403 || "$r" = 302 ]] && echo 1)" "$r"
r=$(curl -s -b "$DONO" -o /dev/null -w "%{http_code}" "$BASE/app/estoque/entradas-nfe")
check "dono abre a tela (200)" "$([ "$r" = 200 ] && echo 1)" "$r"
m=$(curl -s -b "$DONO" "$BASE/app/dashboard" | grep -c 'Entrada por NF-e')
check "menu do dono tem 'Entrada por NF-e'" "$([ "$m" -ge 1 ] && echo 1)" "$m"

echo "== Arquivo ruim"
upload "$DONO" "$XML/nada.xml" >/dev/null
f=$(flash "$DONO" "$BASE/app/estoque/entradas-nfe" "não é um XML de nota fiscal")
check "texto qualquer → 'não é um XML'" "$([ -n "$f" ] && echo 1)"
upload "$DONO" "$XML/quebrado.xml" >/dev/null
f=$(flash "$DONO" "$BASE/app/estoque/entradas-nfe" "corrompido ou incompleto")
check "XML quebrado → 'corrompido'" "$([ -n "$f" ] && echo 1)"
upload "$DONO" "$XML/n1004-nfce.xml" >/dev/null
f=$(flash "$DONO" "$BASE/app/estoque/entradas-nfe" "modelo 65")
check "NFC-e (mod 65) recusada" "$([ -n "$f" ] && echo 1)"
check "nada foi gravado com arquivo ruim" "$([ "$(sql 'select count(*) from nfe_entradas')" = 0 ] && echo 1)"

echo "== Nota 1001 (fornecedor cadastrado, 3 itens, 2 duplicatas)"
r=$(upload "$DONO" "$XML/n1001.xml")
E1=$(echo "$r" | grep -oE '[0-9]+$')
check "upload redireciona para a conferência" "$([ -n "$E1" ] && echo 1)" "$r"
row=$(sql "select status, fornecedor_id, valor_total, json_length(duplicatas) from nfe_entradas where id=$E1")
check "rascunho, fornecedor 1 (CNPJ sem máscara), total 3511, 2 duplicatas" "$([ "$row" = $'rascunho\t1\t3511.00\t2' ] && echo 1)" "$row"
i=$(sql "select concat_ws('|',numero_item,acao,ifnull(produto_id,'-'),custo_total,ifnull(ean,'-')) from nfe_entrada_itens where nfe_entrada_id=$E1 order by numero_item" | tr '\n' ' ')
check "itens: 1 casou pelo EAN com o produto 2 (custo 3050 = 3000+frete+IPI); 2 e 3 'criar'; SEM GTIN vira nulo" \
    "$([ "$i" = "1|vincular|2|3050.00|7891234567895 2|criar|-|456.00|- 3|criar|-|5.00|- " ] && echo 1)" "$i"
r2=$(upload "$DONO" "$XML/n1001.xml")
check "reenviar o mesmo XML volta ao MESMO rascunho" "$([ "$(echo "$r2" | grep -oE '[0-9]+$')" = "$E1" ] && [ "$(sql 'select count(*) from nfe_entradas')" = 1 ] && echo 1)" "$r2"
pg=$(curl -s -b "$DONO" "$BASE/app/estoque/entradas-nfe/$E1")
check "conferência mostra item, 'Já cadastrado' e as 2 parcelas" "$(echo "$pg" | grep -q 'MOUSE LOGITECH' && echo "$pg" | grep -q 'Já cadastrado' && echo "$pg" | grep -q '2 parcela(s)' && echo 1)"
check "sem aviso de destinatário (nota é desta loja)" "$(echo "$pg" | grep -q 'não é o desta loja' || echo 1)"

read -r I1 I2 I3 <<<"$(itens_ids "$DONO" "$E1" | sort -n | uniq | tr '\n' ' ')"
BASEF="estoque_id=2&atualizar_custo=1&gerar_conta_pagar=1&itens[$I1][acao]=vincular&itens[$I1][produto_id]=2&itens[$I1][fator_conversao]=1&itens[$I2][acao]=criar&itens[$I2][fator_conversao]=12&itens[$I3][acao]=ignorar"
confirmar "$DONO" "$E1" "$BASEF&itens[$I2][preco_venda]=" >/dev/null
f=$(flash "$DONO" "$BASE/app/estoque/entradas-nfe/$E1" "informe o preço de venda do produto novo")
check "produto novo sem preço de venda é barrado" "$([ -n "$f" ] && echo 1)"
check "...e nada foi lançado (rollback)" "$([ "$(sql "select count(*) from estoque_movimentacoes where origem_tipo like '%NFeEntrada%'")" = 0 ] && [ "$(sql "select status from nfe_entradas where id=$E1")" = rascunho ] && echo 1)"

custo_antes=$(sql "select preco_custo from produtos where id=2")
r=$(confirmar "$DONO" "$E1" "$BASEF&itens[$I2][preco_venda]=49,90")
check "confirmar redireciona para o resumo" "$(echo "$r" | grep -q "entradas-nfe/$E1$" && echo 1)" "$r"
check "entrada concluída, 2 contas, custo atualizado" "$([ "$(sql "select concat_ws('|',status,contas_pagar_geradas,atualizou_custo,estoque_id) from nfe_entradas where id=$E1")" = "concluida|2|1|2" ] && echo 1)" "$(sql "select concat_ws('|',status,contas_pagar_geradas,atualizou_custo,estoque_id) from nfe_entradas where id=$E1")"
m=$(sql "select concat_ws('|',tipo,quantidade,quantidade_anterior,quantidade_posterior,custo_unitario,estoque_id) from estoque_movimentacoes where origem_tipo like '%NFeEntrada%' and produto_id=2")
check "produto 2: +10 no Depósito a R\$ 305,00" "$([ "$m" = "entrada|10.000|0.000|10.000|305.00|2" ] && echo 1)" "$m"
check "custo do produto 2: $custo_antes → 305.00" "$([ "$(sql 'select preco_custo from produtos where id=2')" = 305.00 ] && echo 1)" "$(sql 'select preco_custo from produtos where id=2')"
NP=$(sql "select id from produtos where descricao like 'CAMISETA BASICA%'")
p=$(sql "select concat_ws('|',preco_venda,preco_custo,unidade_medida,fornecedor_id,ncm,status,cst_csosn,left(codigo_barras,1),length(codigo_barras)) from produtos where id=${NP:-0}")
check "produto novo: venda 49,90, custo 19,00 (456/24), UN, fornecedor 1, NCM, CSOSN 102, EAN interno" "$([ "$p" = "49.90|19.00|UN|1|61091000|ativo|102|2|13" ] && echo 1)" "$p"
m=$(sql "select concat_ws('|',quantidade,custo_unitario) from estoque_movimentacoes where origem_tipo like '%NFeEntrada%' and produto_id=${NP:-0}")
check "produto novo: +24 (2 CX × 12) a R\$ 19,00" "$([ "$m" = "24.000|19.00" ] && echo 1)" "$m"
check "item ignorado não criou produto nem movimento" "$([ "$(sql "select count(*) from produtos where descricao='CHAVEIRO BRINDE'")" = 0 ] && [ "$(sql "select count(*) from estoque_movimentacoes where origem_tipo like '%NFeEntrada%'")" = 2 ] && echo 1)"
c=$(sql "select concat_ws('|',valor,vencimento,fornecedor_id,status,parcela,total_parcelas,unidade_id) from contas_pagar order by id" | tr '\n' ' ')
check "contas a pagar: 2 × 1755,50 em 20/11 e 20/12, fornecedor 1, pendentes" "$([ "$c" = "1755.50|2026-11-20|1|pendente|1|2|1 1755.50|2026-12-20|1|pendente|2|2|1 " ] && echo 1)" "$c"
v=$(sql "select concat_ws('|',codigo_fornecedor,fator_conversao) from produto_fornecedor_codigos order by codigo_fornecedor" | tr '\n' ' ')
check "códigos do fornecedor lembrados (CAM-CX ×12, MX3-01 ×1)" "$([ "$v" = "CAM-CX|12.0000 MX3-01|1.0000 " ] && echo 1)" "$v"
confirmar "$DONO" "$E1" "$BASEF&itens[$I2][preco_venda]=49,90" >/dev/null
check "confirmar de novo não lança duas vezes" "$([ "$(sql "select count(*) from estoque_movimentacoes where origem_tipo like '%NFeEntrada%'")" = 2 ] && [ "$(sql 'select count(*) from contas_pagar')" = 2 ] && echo 1)"
upload "$DONO" "$XML/n1001.xml" >/dev/null
f=$(flash "$DONO" "$BASE/app/estoque/entradas-nfe" "já deu entrada no estoque")
check "reenviar nota já lançada → 'já deu entrada'" "$([ -n "$f" ] && echo 1)"
pg=$(curl -s -b "$DONO" "$BASE/app/estoque/entradas-nfe/$E1")
check "resumo mostra 'no estoque', 'novo' e 'não lançado'" "$(echo "$pg" | grep -q 'no estoque' && echo "$pg" | grep -q '>novo<' && echo "$pg" | grep -q 'não lançado' && echo 1)"
t=$(token "$DONO"); curl -s -b "$DONO" -o /dev/null -d "_token=$t&_method=DELETE" "$BASE/app/estoque/entradas-nfe/$E1"
check "entrada concluída não pode ser descartada" "$([ "$(sql "select count(*) from nfe_entradas where id=$E1")" = 1 ] && echo 1)"

echo "== Nota 1002 (mesmo fornecedor, NFe sem nfeProc, sem duplicatas)"
r=$(upload "$DONO" "$XML/n1002.xml"); E2=$(echo "$r" | grep -oE '[0-9]+$')
i=$(sql "select concat_ws('|',acao,produto_id,fator_conversao) from nfe_entrada_itens where nfe_entrada_id=${E2:-0}")
check "CAM-CX casou sozinho com o produto novo e fator 12" "$([ "$i" = "vincular|$NP|12.0000" ] && echo 1)" "$i"
J1=$(itens_ids "$DONO" "$E2" | head -1)
confirmar "$DONO" "$E2" "estoque_id=1&atualizar_custo=0&gerar_conta_pagar=0&itens[$J1][acao]=vincular&itens[$J1][produto_id]=$NP&itens[$J1][fator_conversao]=12" >/dev/null
m=$(sql "select concat_ws('|',quantidade,custo_unitario,estoque_id) from estoque_movimentacoes where origem_tipo like '%NFeEntrada%' and origem_id=${E2:-0}")
check "+12 no Principal a R\$ 22,00" "$([ "$m" = "12.000|22.00|1" ] && echo 1)" "$m"
check "custo NÃO atualizado (chave desligada): segue 19,00" "$([ "$(sql "select preco_custo from produtos where id=${NP:-0}")" = 19.00 ] && echo 1)"
check "sem conta a pagar nova (chave desligada)" "$([ "$(sql 'select count(*) from contas_pagar')" = 2 ] && echo 1)"

echo "== Nota 1003 (fornecedor novo, destinatário de outro CNPJ)"
r=$(upload "$DONO" "$XML/n1003.xml"); E3=$(echo "$r" | grep -oE '[0-9]+$')
pg=$(curl -s -b "$DONO" "$BASE/app/estoque/entradas-nfe/$E3")
check "aviso de destinatário diferente aparece" "$(echo "$pg" | grep -q 'não é o desta loja' && echo 1)"
check "fornecedor 'Será cadastrado ao confirmar'" "$(echo "$pg" | grep -q 'Será cadastrado' && echo 1)"
K1=$(itens_ids "$DONO" "$E3" | head -1)
confirmar "$DONO" "$E3" "estoque_id=1&atualizar_custo=1&gerar_conta_pagar=1&vencimento=2026-12-01&itens[$K1][acao]=criar&itens[$K1][preco_venda]=15&itens[$K1][fator_conversao]=1" >/dev/null
f=$(sql "select concat_ws('|',cpf_cnpj,razao_social,cidade,uf) from fornecedores where razao_social='Fornecedor Novo SA'")
check "fornecedor criado com CNPJ mascarado e endereço do XML" "$([ "$f" = "99.888.777/0001-66|Fornecedor Novo SA|Sao Paulo|SP" ] && echo 1)" "$f"
c=$(sql "select concat_ws('|',valor,vencimento,total_parcelas) from contas_pagar where descricao like 'NF-e 1003%'")
check "nota sem duplicata → 1 conta de R\$ 30 no vencimento escolhido" "$([ "$c" = "30.00|2026-12-01|1" ] && echo 1)" "$c"
check "UND → UN no produto novo" "$([ "$(sql "select unidade_medida from produtos where descricao='PRODUTO FORNECEDOR NOVO'")" = UN ] && echo 1)"

echo "== Gerente (estoque sim, financeiro não)"
r=$(upload "$GER" "$XML/n1005.xml"); E5=$(echo "$r" | grep -oE '[0-9]+$')
pg=$(curl -s -b "$GER" "$BASE/app/estoque/entradas-nfe/$E5")
i=$(sql "select concat_ws('|',acao,ifnull(produto_id,'-')) from nfe_entrada_itens where nfe_entrada_id=${E5:-0}")
PX=$(sql "select id from produtos where descricao='PRODUTO FORNECEDOR NOVO'")
check "gerente: X1 do fornecedor novo já casou pelo código lembrado" "$([ "$i" = "vincular|$PX" ] && echo 1)" "$i"
check "gerente não vê a chave de contas a pagar" "$(echo "$pg" | grep -q 'name="gerar_conta_pagar"' || echo 1)"
L1=$(itens_ids "$GER" "$E5" | head -1)
confirmar "$GER" "$E5" "estoque_id=1&atualizar_custo=1&gerar_conta_pagar=1&itens[$L1][acao]=vincular&itens[$L1][produto_id]=$PX&itens[$L1][fator_conversao]=1" >/dev/null
check "gerente lança o estoque, mas a conta forjada no POST não nasce" "$([ "$(sql "select status from nfe_entradas where id=${E5:-0}")" = concluida ] && [ "$(sql "select count(*) from contas_pagar where descricao like 'NF-e 1005%'")" = 0 ] && echo 1)"

echo "== Isolamento entre empresas e descarte"
r=$(upload "$DONO" "$XML/n1006.xml"); E6=$(echo "$r" | grep -oE '[0-9]+$')
r=$(curl -s -b "$D2" -o /dev/null -w "%{http_code}" "$BASE/app/estoque/entradas-nfe/$E6")
check "outra empresa não abre o rascunho (404)" "$([ "$r" = 404 ] && echo 1)" "$r"
t=$(token "$D2"); r=$(curl -s -b "$D2" -o /dev/null -w "%{http_code}" -d "_token=$t&estoque_id=3&itens[1][acao]=ignorar" "$BASE/app/estoque/entradas-nfe/$E6/confirmar")
check "outra empresa não confirma o rascunho (404)" "$([ "$r" = 404 ] && echo 1)" "$r"
r=$(curl -s -b "$D2" -o /dev/null -w "%{http_code}" "$BASE/app/estoque/entradas-nfe/$E1")
check "outra empresa não vê entrada concluída (404)" "$([ "$r" = 404 ] && echo 1)" "$r"
l=$(curl -s -b "$D2" "$BASE/app/estoque/entradas-nfe" | grep -c 'nº 100')
check "lista da outra empresa vem vazia" "$([ "$l" = 0 ] && echo 1)" "$l"
t=$(token "$DONO"); curl -s -b "$DONO" -o /dev/null -d "_token=$t&_method=DELETE" "$BASE/app/estoque/entradas-nfe/$E6"
check "descartar rascunho apaga a entrada e os itens" "$([ "$(sql "select count(*) from nfe_entradas where id=${E6:-0}")" = 0 ] && [ "$(sql "select count(*) from nfe_entrada_itens where nfe_entrada_id=${E6:-0}")" = 0 ] && echo 1)"

echo "== NFes Recebidas"
CH=35261099888777000166550010000077771000000007
sql "insert into nfes_recebidas (empresa_id,unidade_id,chave_acesso,cnpj_emitente,nome_emitente,numero,valor_total,data_emissao,created_at,updated_at) values (1,1,'$CH','99888777000166','Fornecedor Recebido','7777',10,'2026-10-20',now(),now())"
sql "insert into nfes_recebidas (empresa_id,unidade_id,chave_acesso,cnpj_emitente,nome_emitente,numero,valor_total,data_emissao,created_at,updated_at) select 1,1,chave_acesso,cnpj_emitente,nome_emitente,numero,valor_total,data_emissao,now(),now() from nfe_entradas where id=$E1"
pg=$(curl -s -b "$DONO" "$BASE/app/nfes-recebidas")
check "NFes Recebidas: botão 'Entrada por XML' no topo" "$(echo "$pg" | grep -q 'Entrada por XML' && echo 1)"
check "NFes Recebidas: nota já lançada mostra 'No estoque'" "$(echo "$pg" | grep -q 'No estoque' && echo 1)"
NR=$(sql "select id from nfes_recebidas where chave_acesso='$CH'")
check "NFes Recebidas: nota nova tem o botão de dar entrada" "$(echo "$pg" | grep -q "entradas-nfe/recebida/$NR" && echo 1)"
t=$(token "$DONO"); curl -s -b "$DONO" -c "$DONO" -o /dev/null -d "_token=$t" "$BASE/app/estoque/entradas-nfe/recebida/$NR"
f=$(flash "$DONO" "$BASE/app/estoque/entradas-nfe" "Ciência da operação")
check "sem XML da Focus → orienta Ciência ou envio do arquivo" "$([ -n "$f" ] && echo 1)"

echo "== Saldo pela regra única (SaldoEstoque)"
s=$(sql "select quantidade_posterior from estoque_movimentacoes where produto_id=${NP:-0} order by id desc limit 1")
check "camiseta: Principal 12 (última da cadeia do estoque 1)" "$([ "$s" = 12.000 ] && echo 1)" "$s"
s=$(sql "select sum(q) from (select quantidade_posterior q from estoque_movimentacoes where id in (select max(id) from estoque_movimentacoes where produto_id=${NP:-0} group by estoque_id)) t")
check "camiseta: loja = 24 (Depósito) + 12 (Principal) = 36" "$([ "$s" = 36.000 ] && echo 1)" "$s"

echo
echo "Resultado: $OK ✅  $FAIL ❌"
rm -rf "$TMP"
[ "$FAIL" = 0 ]
