#!/usr/bin/env bash
# Validação ✅/❌ — integracao-28out (28/10/2026): branches da Mel `alteracoes-erp` (auditoria visual impeccable +
# DK Med: pedido A4, venda de pedido/balcão no caixa, loja de saída) e `edicao-preco` (preço na lista) + ferramental.
# Ambiente DESCARTÁVEL (nunca produção): mesmo banco, código de produção em $ANTES e da branch em $BASE.
#   BASE=http://127.0.0.1:8097 ANTES=http://127.0.0.1:8096 DB_CT=erpv-mysql APP_CT=erpv-depois OUT=<pasta> ./scripts/validar-integracao-28out.sh
# No `antes` (ANTES=BASE) os itens da feature FALHAM de propósito: é a reprodução do defeito na imagem do ar.
set -u
BASE=${BASE:-http://127.0.0.1:8097}
ANTES=${ANTES:-http://127.0.0.1:8096}
DB_CT=${DB_CT:-erpv-mysql}
APP_CT=${APP_CT:-erpv-depois}
OUT=${OUT:-$(mktemp -d)}
TMP=$(mktemp -d)
OK=0; FAIL=0
check() { if [ "$2" = "1" ]; then OK=$((OK+1)); echo "✅ $1"; else FAIL=$((FAIL+1)); echo "❌ $1  (obtido: ${3:-})"; fi; }
sql() { docker exec "$DB_CT" mysql -uerp -perp erp_teste -N -B -e "$1" 2>/dev/null; }
login() { # url cookiejar email senha
    rm -f "$2"
    local t; t=$(curl -s -c "$2" -b "$2" "$1/login" | grep -o 'name="_token" value="[^"]*"' | head -1 | cut -d'"' -f4)
    curl -s -c "$2" -b "$2" -o /dev/null -d "_token=$t&email=$3&password=$4" "$1/login"
}
tok() { curl -s -c "$2" -b "$2" "$1$3" | grep -o 'name="_token" value="[^"]*"' | head -1 | cut -d'"' -f4; }
code() { curl -s -b "$2" -o /dev/null -w "%{http_code}" "$1$3"; }
post() { # url cookiejar pagina-do-token rota dados → código HTTP (corpo em $TMP/ultimo.html, Location em $TMP/loc)
    local t; t=$(tok "$1" "$2" "$3")
    curl -s -c "$2" -b "$2" -o "$TMP/ultimo.html" -w "%{http_code}" -D "$TMP/hdr" -d "_token=$t&$5" "$1$4"
    grep -i '^location:' "$TMP/hdr" | tr -d '\r' | cut -d' ' -f2- > "$TMP/loc"
}
flash() { curl -s -b "$1" "$2" | tr '\n' ' ' | grep -o "$3" | head -1; }

DONO=$TMP/dono; VEND=$TMP/vend; CX=$TMP/cx; DA=$TMP/da; VA=$TMP/va; CA=$TMP/ca
login "$BASE" "$DONO" dono@demo.com dono123; login "$BASE" "$VEND" vendedor@demo.com vendedor123; login "$BASE" "$CX" caixa@demo.com caixa123
login "$ANTES" "$DA" dono@demo.com dono123; login "$ANTES" "$VA" vendedor@demo.com vendedor123; login "$ANTES" "$CA" caixa@demo.com caixa123

# estado limpo: nenhum caixa aberto, sem pedidos/vendas de rodadas anteriores (os caixas abertos voltam no fim: o smoke
# que roda depois abre /app/caixa/{caixa}/fechar e /app/pedidos/{pedido}/edit pelo menor id e precisa do mesmo estado do antes)
ABERTOS=$(sql "select coalesce(group_concat(id),'0') from caixas where status='aberto'")
CAIXA_MAX=$(sql "select coalesce(max(id),0) from caixas")
sql "update caixas set status='fechado', fechado_em=now() where status='aberto';"
sql "delete from movimentacoes_caixa; delete from contas_receber; delete from venda_itens; delete from vendas; delete from pedido_itens; delete from pedidos; delete from orcamento_itens; delete from orcamentos;"
DONO_ID=$(sql "select id from users where email='dono@demo.com'")
UNID=$(sql "select unidade_id from unidade_user where user_id=$DONO_ID order by unidade_id limit 1")
[ -n "$UNID" ] || UNID=$(sql "select min(id) from unidades")
PROD=$(sql "select id from produtos where preco_custo>0 and ativo=1 order by id limit 1"); [ -n "$PROD" ] || PROD=2
PRECO_ORIG=$(sql "select preco_venda from produtos where id=$PROD")

echo "== 1. Regressão: mesmas telas, mesmo código HTTP, antes × depois (dono, vendedor, caixa)"
for u in /app/dashboard /app/configuracoes /app/produtos /app/produtos/create /app/pedidos /app/pedidos/create /app/vendas /app/vendas/create /app/caixa /app/clientes /app/relatorios/vendas /app/relatorios/financeiro /app/financeiro/fluxo-caixa /app/estoque/movimentacoes /app/pdv; do
    for par in "DA DONO dono" "VA VEND vendedor" "CA CX caixa"; do
        set -- $par; a=$(code "$ANTES" "${!1}" "$u"); b=$(code "$BASE" "${!2}" "$u")
        check "$3 $u: antes $a = depois $b" "$([ "$a" = "$b" ] && echo 1)" "$a/$b"
    done
done
check "login (deslogado) 200 e sem exceção" "$(curl -s "$BASE/login" | grep -q 'name="password"' && echo 1)"

echo "== 2. Layout: SRI nos CDNs, Chart.js onde há gráfico, nada de Vite"
pg=$(curl -s -b "$DONO" "$BASE/app/dashboard")
check "dashboard carrega bootstrap com integrity=" "$(echo "$pg" | grep -q 'bootstrap.min.css" integrity=\|integrity="sha384' && echo 1)"
check "dashboard carrega chart.umd (tem gráfico)" "$(echo "$pg" | grep -q 'chart.umd' && echo 1)"
check "lista de produtos NÃO carrega chart.umd" "$(curl -s -b "$DONO" "$BASE/app/produtos" | grep -q 'chart.umd' || echo 1)"
check "nenhuma tela referencia @vite/build/manifest" "$(echo "$pg" | grep -q '/build/assets' || echo 1)"
check "lista de produtos tem h1 (page-header)" "$(curl -s -b "$DONO" "$BASE/app/produtos" | grep -q '<h1' && echo 1)"

echo "== 3. Pedido: criar, confirmar, faturar SEM caixa aberto → recusa (defeito antigo: faturava sem caixa)"
ITENS="cliente_id=1&itens[0][produto_id]=$PROD&itens[0][quantidade]=2&itens[0][preco_unitario]=150.00&condicao_pagamento=pix"
st=$(post "$BASE" "$DONO" /app/pedidos/create /app/pedidos "$ITENS")
PED=$(sql "select max(id) from pedidos"); NUM=$(sql "select numero from pedidos where id=$PED")
check "pedido criado ($st, id=$PED nº $NUM)" "$([ -n "$PED" ] && [ "$PED" != NULL ] && echo 1)" "$st"
st=$(post "$BASE" "$DONO" "/app/pedidos/$PED" "/app/pedidos/$PED/status" "status=confirmado")
check "pedido confirmado" "$([ "$(sql "select status from pedidos where id=$PED")" = confirmado ] && echo 1)" "$st"
CR=$(sql "select id from contas_receber where descricao='Pedido #$NUM' and empresa_id=(select empresa_id from pedidos where id=$PED)")
check "conta a receber 'Pedido #$NUM' criada na confirmação" "$([ -n "$CR" ] && echo 1)"
check "conta a receber já nasce com pedido_id (coluna nova)" "$([ "$(sql "select pedido_id from contas_receber where id=$CR")" = "$PED" ] && echo 1)" "$(sql "select pedido_id from contas_receber where id=$CR")"
st=$(post "$BASE" "$DONO" "/app/pedidos/$PED" "/app/pedidos/$PED/faturar" "documento=recibo&forma_pagamento=pix")
check "faturar sem caixa: pedido continua confirmado" "$([ "$(sql "select status from pedidos where id=$PED")" = confirmado ] && echo 1)" "$(sql "select status from pedidos where id=$PED")"
check "faturar sem caixa: aviso 'Abra o caixa nesta loja antes de faturar'" "$(flash "$DONO" "$BASE/app/pedidos/$PED" 'Abra o caixa nesta loja antes de faturar' | grep -q . && echo 1)"
check "faturar sem caixa: nenhuma venda criada" "$([ "$(sql 'select count(*) from vendas')" = 0 ] && echo 1)" "$(sql 'select count(*) from vendas')"

echo "== 4. Abrir caixa e faturar: venda no caixa, movimentação com venda_id, conta a receber baixada"
st=$(post "$BASE" "$DONO" /app/caixa/abrir /app/caixa/abrir "valor_abertura=100")
CAIXA=$(sql "select id from caixas where user_id=$DONO_ID and status='aberto' order by id desc limit 1")
check "caixa aberto pelo dono (id=$CAIXA)" "$([ -n "$CAIXA" ] && echo 1)" "$st"
st=$(post "$BASE" "$DONO" "/app/pedidos/$PED" "/app/pedidos/$PED/faturar" "documento=recibo&forma_pagamento=pix")
check "faturar com caixa: pedido faturado" "$([ "$(sql "select status from pedidos where id=$PED")" = faturado ] && echo 1)" "$st $(sql "select status from pedidos where id=$PED")"
VENDA=$(sql "select id from vendas where pedido_id=$PED order by id desc limit 1")
check "venda gerada com caixa_id = caixa aberto" "$([ -n "$VENDA" ] && [ "$(sql "select caixa_id from vendas where id=$VENDA")" = "$CAIXA" ] && echo 1)" "venda=$VENDA caixa=$(sql "select caixa_id from vendas where id=$VENDA")"
MOV=$(sql "select concat(count(*),'|',coalesce(sum(valor),0),'|',group_concat(forma_pagamento)) from movimentacoes_caixa where venda_id=$VENDA and caixa_id=$CAIXA and tipo='venda'")
check "movimentação de caixa: 1 linha, R$ 300,00, pix, com venda_id" "$([ "$MOV" = '1|300.00|pix' ] && echo 1)" "$MOV"
CRS=$(sql "select concat(status,'|',coalesce(venda_id,'-'),'|',coalesce(pedido_id,'-')) from contas_receber where id=$CR")
check "conta a receber do pedido baixada (paga, com venda_id e pedido_id)" "$(echo "$CRS" | grep -q "^pag[ao]|$VENDA|$PED$" && echo 1)" "$CRS"
check "faturar com 'Pedido impresso' redireciona para a folha do pedido" "$(grep -q "/app/pedidos/$PED/imprimir" "$TMP/loc" && echo 1)" "$(cat "$TMP/loc")"

echo "== 5. Folha A4 do pedido (pedido/protocolo de entrega, não orçamento) + loja de saída"
folha=$(curl -s -b "$DONO" "$BASE/app/pedidos/$PED/imprimir" | tr '\n' ' ')
check "GET pedidos/$PED/imprimir = 200" "$([ "$(code "$BASE" "$DONO" "/app/pedidos/$PED/imprimir")" = 200 ] && echo 1)"
check "folha diz 'Entregar para'" "$(echo "$folha" | grep -q 'Entregar para' && echo 1)"
check "folha NÃO é a do orçamento (sem título ORÇAMENTO; só a referência 'Orçamento nº')" "$(echo "$folha" | grep -q 'ORÇAMENTO' || echo 1)"
check "folha mostra 'Loja:' (loja de saída)" "$(echo "$folha" | grep -q 'Loja: <b>' && echo 1)"
check "folha mostra o pagamento (Pago)" "$(echo "$folha" | grep -q 'Pago' && echo 1)"
check "tela do pedido tem botões 'Imprimir pedido' e recibo da venda" "$(curl -s -b "$DONO" "$BASE/app/pedidos/$PED" | tr '\n' ' ' | grep -q "Imprimir pedido.*vendas/$VENDA/recibo" && echo 1)"
check "tela do pedido mostra 'Loja de saída'" "$(curl -s -b "$DONO" "$BASE/app/pedidos/$PED" | grep -q 'Loja de saída' && echo 1)"
check "recibo térmico da venda mostra 'Loja:'" "$(curl -s -b "$DONO" "$BASE/app/vendas/$VENDA/recibo" | grep -q 'Loja:' && echo 1)"
c=$(code "$BASE" "$VEND" "/app/pedidos/$PED/imprimir")
check "vendedor abre a folha do pedido: 200 ou 403, nunca 500" "$([ "$c" = 200 ] || [ "$c" = 403 ] && echo 1)" "$c"

echo "== 6. Venda de balcão: vendedor sem caixa é recusado; dono com caixa lança no caixa"
VB="itens[0][produto_id]=$PROD&itens[0][quantidade]=1&itens[0][preco_unitario]=80.00&forma_pagamento=dinheiro"
n0=$(sql 'select count(*) from vendas')
st=$(post "$BASE" "$VEND" /app/vendas/create /app/vendas "$VB")
check "vendedor sem caixa: venda NÃO criada ($st)" "$([ "$(sql 'select count(*) from vendas')" = "$n0" ] && echo 1)" "$(sql 'select count(*) from vendas')"
check "vendedor sem caixa: aviso 'Abra o caixa antes de registrar a venda'" "$(flash "$VEND" "$BASE/app/vendas/create" 'Abra o caixa antes de registrar a venda' | grep -q . && echo 1)"
st=$(post "$BASE" "$DONO" /app/vendas/create /app/vendas "$VB")
VB_ID=$(sql "select max(id) from vendas")
check "dono com caixa: venda criada com caixa_id" "$([ "$(sql 'select count(*) from vendas')" = $((n0+1)) ] && [ "$(sql "select caixa_id from vendas where id=$VB_ID")" = "$CAIXA" ] && echo 1)" "$st"
MOV=$(sql "select concat(count(*),'|',coalesce(sum(valor),0),'|',group_concat(forma_pagamento)) from movimentacoes_caixa where venda_id=$VB_ID")
check "movimentação da venda de balcão: 1 linha, R$ 80,00, dinheiro" "$([ "$MOV" = '1|80.00|dinheiro' ] && echo 1)" "$MOV"
TOT=$(sql "select sum(valor) from movimentacoes_caixa where caixa_id=$CAIXA and tipo='venda'")
check "caixa do dono soma as duas vendas (380,00)" "$([ "$TOT" = 380.00 ] && echo 1)" "$TOT"
st=$(post "$BASE" "$DONO" "/app/vendas/$VB_ID" "/app/vendas/$VB_ID" "_method=DELETE")
EST=$(sql "select concat(count(*),'|',coalesce(sum(valor),0)) from movimentacoes_caixa where venda_id=$VB_ID and tipo='estorno'")
check "cancelar a venda de balcão estorna o caixa (1 estorno de 80,00; saldo das vendas volta a 300,00)" "$([ "$EST" = '1|80.00' ] && [ "$(sql "select sum(if(tipo='estorno',-valor,valor)) from movimentacoes_caixa where caixa_id=$CAIXA and tipo in ('venda','estorno')")" = 300.00 ] && echo 1)" "$st est=$EST linhas=$(sql "select group_concat(concat(tipo,':',valor)) from movimentacoes_caixa where caixa_id=$CAIXA")"

echo "== 7. Preço de venda direto na lista (edicao-preco)"
csrf() { curl -s -b "$1" "$BASE/app/produtos" | grep -o 'name="csrf-token" content="[^"]*"' | head -1 | cut -d'"' -f4; }
patch() { # cookiejar produto preco → http
    curl -s -b "$1" -o "$TMP/patch.json" -w "%{http_code}" -X PATCH -H "X-CSRF-TOKEN: $(csrf "$1")" -H 'Content-Type: application/json' -H 'Accept: application/json' -d "{\"preco_venda\":\"$3\"}" "$BASE/app/produtos/$2/preco"
}
check "lista: dono vê o lápis (preco-inline)" "$(curl -s -b "$DONO" "$BASE/app/produtos" | grep -q 'class="preco-inline"' && echo 1)"
check "lista: vendedor NÃO vê o lápis" "$(curl -s -b "$VEND" "$BASE/app/produtos" | grep -q 'class="preco-inline"' || echo 1)"
st=$(patch "$DONO" "$PROD" 123.45)
check "dono PATCH preco → 200 e grava 123.45" "$([ "$st" = 200 ] && [ "$(sql "select preco_venda from produtos where id=$PROD")" = 123.45 ] && echo 1)" "$st $(sql "select preco_venda from produtos where id=$PROD")"
custo=$(sql "select preco_custo from produtos where id=$PROD"); mk=$(sql "select markup from produtos where id=$PROD"); esp=$(awk -v c="$custo" 'BEGIN{printf "%.2f",(123.45-c)/c*100}')
check "markup recalculado ($esp)" "$([ "$mk" = "$esp" ] && echo 1)" "$mk"
st=$(patch "$VEND" "$PROD" 1.00)
check "vendedor PATCH preco → 403 e não grava" "$([ "$st" = 403 ] && [ "$(sql "select preco_venda from produtos where id=$PROD")" = 123.45 ] && echo 1)" "$st"
st=$(patch "$DONO" "$PROD" abc)
check "preço inválido → 422" "$([ "$st" = 422 ] && echo 1)" "$st"
sql "update produtos set preco_venda=$PRECO_ORIG where id=$PROD"

echo "== 8. Configurações da Loja em abas: nenhum campo sumiu"
curl -s -b "$DA" "$ANTES/app/configuracoes" | grep -o 'name="[^"]*"' | sort -u > "$TMP/cfg-antes"
curl -s -b "$DONO" "$BASE/app/configuracoes" | grep -o 'name="[^"]*"' | sort -u > "$TMP/cfg-depois"
perdidos=$(comm -23 "$TMP/cfg-antes" "$TMP/cfg-depois" | tr '\n' ' ')
check "todos os campos name= da produção continuam ($(wc -l < "$TMP/cfg-antes") campos)" "$([ -z "$perdidos" ] && echo 1)" "perdidos: $perdidos"
check "6 abas (caixa, precos, cupom, trocas, estoque, impressos)" "$(curl -s -b "$DONO" "$BASE/app/configuracoes" | grep -c 'id="tab-\(caixa\|precos\|cupom\|trocas\|estoque\|impressos\)"' | grep -q '^6$' && echo 1)"
st=$(post "$BASE" "$DONO" /app/configuracoes /app/configuracoes "_method=PUT&regra_preco_split=cartao_maior&percentual_debito=0&percentual_credito=0&max_parcelas=6&padrao_impressao=recibo&orcamento_endereco=loja&troca_prazo_dias=30&troca_sobra=vale&troca_vale_validade_dias=90&orcamento_observacoes=Teste+abas")
check "salvar configurações (abas) grava" "$([ "$st" = 302 ] && [ "$(sql 'select orcamento_observacoes from configuracoes_loja limit 1')" = 'Teste abas' ] && echo 1)" "$st"

echo "== 9. Prints e cliques (puppeteer): preço inline, abas, responsivo, folha A4"
PUP=$(ls -d /home/ubuntu/.npm/_npx/*/node_modules/puppeteer 2>/dev/null | head -1)
mkdir -p "$OUT/prints"
if [ -n "$PUP" ] && BASE=$BASE ANTES=$ANTES OUT=$OUT/prints PED=$PED PUPPETEER=$PUP node "$(dirname "$0")/validar-integracao-28out.mjs" > "$OUT/prints/log.txt" 2>&1; then
    while IFS=$'\t' read -r r d; do check "$d" "$([ "$r" = OK ] && echo 1)"; done < "$OUT/prints/resultado.tsv"
else
    check "puppeteer rodou (ver $OUT/prints/log.txt)" 0 "$(tail -1 "$OUT/prints/log.txt" 2>/dev/null)"
fi

echo "== 10. Estado de volta para o smoke (caixas como estavam, 1 pedido rascunho)"
sql "delete from movimentacoes_caixa; delete from contas_receber; delete from venda_itens; delete from vendas; delete from pedido_itens; delete from pedidos;"
sql "delete from caixas where id>$CAIXA_MAX; update caixas set status='aberto', fechado_em=NULL where id in ($ABERTOS);"
post "$BASE" "$DONO" /app/pedidos/create /app/pedidos "cliente_id=1&itens[0][produto_id]=$PROD&itens[0][quantidade]=1&itens[0][preco_unitario]=10.00" >/dev/null
check "restaurado: caixas abertos = $ABERTOS, 1 pedido rascunho" "$([ "$(sql "select coalesce(group_concat(id),'0') from caixas where status='aberto'")" = "$ABERTOS" ] && [ "$(sql "select count(*) from pedidos where status='rascunho'")" = 1 ] && echo 1)"

echo; echo "TOTAL: ✅ $OK  ❌ $FAIL"; [ "$FAIL" = 0 ]
