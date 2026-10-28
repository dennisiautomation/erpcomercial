#!/usr/bin/env bash
# Validação ✅/❌ — orçamento com taxa de entrega + quadro de pagamento e "preço cadastrado = cartão" (25/10/2026).
# Ambiente DESCARTÁVEL (nunca produção): mesmo banco, código de produção em $ANTES e da branch em $BASE.
#   BASE=http://127.0.0.1:8097 ANTES=http://127.0.0.1:8096 DB_CT=dko-mysql APP_CT=dko-app ./scripts/validar-dk-orcamento-preco-cartao.sh
set -u
BASE=${BASE:-http://127.0.0.1:8097}
ANTES=${ANTES:-http://127.0.0.1:8096}
DB_CT=${DB_CT:-dko-mysql}
APP_CT=${APP_CT:-dko-app}
TMP=$(mktemp -d)
OK=0; FAIL=0
check() { if [ "$2" = "1" ]; then OK=$((OK+1)); echo "✅ $1"; else FAIL=$((FAIL+1)); echo "❌ $1  (obtido: ${3:-})"; fi; }
sql() { docker exec "$DB_CT" mysql -uerp -perp erp_teste -N -B -e "$1" 2>/dev/null; }
php_() { docker exec "$APP_CT" php -r "$1"; }
login() { # url cookiejar email senha
    rm -f "$2"
    local t; t=$(curl -s -c "$2" -b "$2" "$1/login" | grep -o 'name="_token" value="[^"]*"' | head -1 | cut -d'"' -f4)
    curl -s -c "$2" -b "$2" -o /dev/null -d "_token=$t&email=$3&password=$4" "$1/login"
}
tok() { curl -s -c "$2" -b "$2" "$1$3" | grep -o 'name="_token" value="[^"]*"' | head -1 | cut -d'"' -f4; }
code() { curl -s -b "$2" -o /dev/null -w "%{http_code}" "$1$3"; }
limpa() { sed -E 's/name="_token" value="[^"]*"//g; s/content="[A-Za-z0-9]{40}"//g; s/Emitido em [0-9\/: ]+//g; s/"csrf[^"]*":"[^"]*"//g' ; }
CFG_PADRAO="regra_preco_split=cartao_maior&percentual_debito=0&percentual_credito=0&max_parcelas=6&padrao_impressao=recibo&orcamento_endereco=loja&troca_prazo_dias=30&troca_sobra=vale&troca_vale_validade_dias=90"
salvar_cfg() { # cookiejar campos
    local t; t=$(tok "$BASE" "$1" /app/configuracoes)
    curl -s -c "$1" -b "$1" -o /dev/null -w "%{http_code}" -d "_token=$t&_method=PUT&$2" "$BASE/app/configuracoes"
}
precos() { curl -s -b "$1" -H 'Accept: application/json' "$2/app/pdv/produto/$3" | python3 -c 'import sys,json; d=json.load(sys.stdin); d=d[0] if isinstance(d,list) else d.get("produto",d); p=d.get("precos") or {}; print(" ".join("%s=%.2f"%(k,float(p[k])) for k in ("dinheiro_pix","debito","credito")))'; }
etiqueta() { # url cookiejar → html
    local t; t=$(tok "$1" "$2" /app/etiquetas)
    curl -s -c "$2" -b "$2" -d "_token=$t&formato=termica-50x30&produtos[3]=1" "$1/app/etiquetas/gerar"
}

DONO=$TMP/dono; CX=$TMP/cx; DA=$TMP/donoantes; CA=$TMP/cxantes
login "$BASE" "$DONO" dono@demo.com dono123; login "$BASE" "$CX" caixa@demo.com caixa123
login "$ANTES" "$DA" dono@demo.com dono123; login "$ANTES" "$CA" caixa@demo.com caixa123

sql "delete from produto_precos; delete from orcamento_itens; delete from orcamentos; delete from pedido_itens; delete from pedidos;"
sql "update produtos set preco_venda=299.90 where id=3"
salvar_cfg "$DONO" "$CFG_PADRAO" >/dev/null

echo "== 1. Regressão: mesmas telas, mesmo código HTTP, antes × depois"
for u in /app/dashboard /app/configuracoes /app/produtos /app/produtos/create /app/produtos/3/edit /app/orcamentos /app/orcamentos/create /app/pdv /app/etiquetas /app/pedidos /app/vendas; do
    a=$(code "$ANTES" "$DA" "$u"); b=$(code "$BASE" "$DONO" "$u")
    check "dono $u: antes $a = depois $b" "$([ "$a" = "$b" ] && echo 1)" "$a/$b"
    a=$(code "$ANTES" "$CA" "$u"); b=$(code "$BASE" "$CX" "$u")
    check "caixa $u: antes $a = depois $b" "$([ "$a" = "$b" ] && echo 1)" "$a/$b"
done

echo "== 2. Loja na regra de sempre: preços iguais aos de produção (acréscimo no cartão)"
for regra in cartao_maior por_parte; do
    sql "update configuracoes_loja set percentual_debito=3, percentual_credito=8, regra_preco_split='$regra', juros_por_parcela='{\"6\": 10}'"
    a=$(precos "$DA" "$ANTES" PROD003); b=$(precos "$DONO" "$BASE" PROD003)
    check "[$regra] PDV: tabelas do produto iguais ($b)" "$([ -n "$b" ] && [ "$a" = "$b" ] && echo 1)" "$a | $b"
done
check "acréscimo 8% no crédito continua: 299,90 → 323,89" "$(echo "$b" | grep -q 'credito=323.89' && echo 1)" "$b"
sql "update configuracoes_loja set regra_preco_split='cartao_maior'"
# 28/10 (integracao-28out): título da etiqueta acentuado ("Impressão") — normalizado para comparar
a=$(etiqueta "$ANTES" "$DA" | limpa | sed 's/Impressao/Impressão/g' | md5sum); b=$(etiqueta "$BASE" "$DONO" | limpa | sed 's/Impressao/Impressão/g' | md5sum)
check "etiqueta idêntica antes × depois (fora o acento de Impressão)" "$([ "$a" = "$b" ] && echo 1)"
r=$(docker exec "$APP_CT" php artisan tinker --execute='$s=app(App\Services\TabelaPrecoService::class);$c=App\Models\ConfiguracaoLoja::withoutGlobalScopes()->first();foreach([["pix"],["cartao_credito"],["boleto"],["crediario"],["pix","cartao_debito"],[]] as $f) echo $s->modalidadeDosPagamentos($f,$c)," ";' 2>/dev/null | tr -d '\n')
check "modalidades na regra de sempre: pix→PIX, crédito, boleto/crediário→PIX, split→débito" "$([ "$r" = "dinheiro_pix credito dinheiro_pix dinheiro_pix debito dinheiro_pix " ] && echo 1)" "$r"
st=$(salvar_cfg "$DONO" "regra_preco_split=por_parte&percentual_debito=3&percentual_credito=8&max_parcelas=6&padrao_impressao=recibo&orcamento_endereco=loja&troca_prazo_dias=30&troca_sobra=vale&troca_vale_validade_dias=90")
row=$(sql "select preco_base, desconto_avista_percentual, regra_preco_split, percentual_credito from configuracoes_loja")
check "form antigo (sem os campos novos) salva e a loja segue 'avista'" "$([ "$row" = $'avista\t0.00\tpor_parte\t8.00' ] && echo 1)" "$st $row"

echo "== 3. Configuração da regra nova"
st=$(salvar_cfg "$DONO" "preco_base=cartao&desconto_avista_percentual=5&regra_preco_split=por_parte&percentual_debito=3&percentual_credito=8&max_parcelas=6&padrao_impressao=recibo&orcamento_endereco=loja&troca_prazo_dias=30&troca_sobra=vale&troca_vale_validade_dias=90")
row=$(sql "select preco_base, regra_preco_split from configuracoes_loja")
check "cartão + 'cada forma paga a sua' é recusado e nada muda" "$([ "$row" = $'avista\tpor_parte' ] && echo 1)" "$st $row"
f=$(curl -s -b "$DONO" "$BASE/app/configuracoes" | grep -c 'não se aplica')
check "a tela explica a recusa" "$([ "$f" -ge 1 ] && echo 1)" "$f"
st=$(salvar_cfg "$DONO" "preco_base=cartao&desconto_avista_percentual=60&regra_preco_split=cartao_maior&percentual_debito=3&percentual_credito=8&max_parcelas=6&padrao_impressao=recibo&orcamento_endereco=loja&troca_prazo_dias=30&troca_sobra=vale&troca_vale_validade_dias=90")
check "desconto acima de 50% recusado" "$([ "$(sql 'select preco_base from configuracoes_loja')" = avista ] && echo 1)"
st=$(salvar_cfg "$DONO" "preco_base=cartao&desconto_avista_percentual=5&regra_preco_split=cartao_maior&percentual_debito=3&percentual_credito=8&max_parcelas=6&padrao_impressao=recibo&orcamento_endereco=loja&orcamento_parcelas=6&troca_prazo_dias=30&troca_sobra=vale&troca_vale_validade_dias=90")
row=$(sql "select preco_base, desconto_avista_percentual, orcamento_parcelas from configuracoes_loja")
check "salva: cartão, 5% no PIX, orçamento em 6x" "$([ "$row" = $'cartao\t5.00\t6' ] && echo 1)" "$st $row"
pg=$(curl -s -b "$DONO" "$BASE/app/configuracoes" | tr "\n" " ")
check "tela: opção 'do cartão' marcada e aviso dos acréscimos" "$(echo "$pg" | grep -q 'id="preco_base_cartao"[^>]*checked' && echo "$pg" | grep -q 'não são usados' && echo 1)"

echo "== 4. Preços na regra nova (produto cadastrado a 299,90)"
PIX=$(php_ 'echo number_format(round(299.90*0.95,2),2,".","");')
b=$(precos "$DONO" "$BASE" PROD003)
check "PDV: PIX $PIX · débito 299,90 · crédito 299,90 (acréscimos ignorados)" "$([ "$b" = "dinheiro_pix=$PIX debito=299.90 credito=299.90" ] && echo 1)" "$b"
r=$(docker exec "$APP_CT" php artisan tinker --execute='$s=app(App\Services\TabelaPrecoService::class);$c=App\Models\ConfiguracaoLoja::withoutGlobalScopes()->first();foreach([["pix"],["dinheiro"],["cartao_credito"],["boleto"],["crediario"],["pix","cartao_debito"],[]] as $f) echo $s->modalidadeDosPagamentos($f,$c)," ";' 2>/dev/null | tr -d '\n')
check "modalidades: pix/dinheiro→PIX; crédito, boleto, crediário e vazio→cartão; PIX+débito→débito" "$([ "$r" = "dinheiro_pix dinheiro_pix credito credito credito debito credito " ] && echo 1)" "$r"
et=$(etiqueta "$BASE" "$DONO" | tr '\n' ' ')
PIXV=$(echo $PIX | tr . ,)
check "etiqueta: Cartão R\$ 299,90 e PIX R\$ $PIXV" "$(echo "$et" | grep -q 'Cartão R\$ 299,90' && echo "$et" | grep -q "PIX R\\\$ $PIXV" && echo 1)"
pdv=$(curl -s -b "$DONO" "$BASE/app/pdv")
check "PDV recebe preco_base=cartao e desconto 5" "$(echo "$pdv" | grep -q '"preco_base":"cartao"' && echo "$pdv" | grep -q '"desconto_avista":5' && echo 1)"

echo "== 5. Venda real no PDV (servidor reprecifica)"
t=$(tok "$BASE" "$DONO" /app/caixa/abrir)
curl -s -c "$DONO" -b "$DONO" -o /dev/null -d "_token=$t&valor_abertura=100" "$BASE/app/caixa/abrir"
venda() { # forma valor
    local t; t=$(curl -s -c "$DONO" -b "$DONO" "$BASE/app/pdv" | grep -o 'name="csrf-token" content="[^"]*"' | head -1 | cut -d'"' -f4)
    curl -s -c "$DONO" -b "$DONO" -H "X-CSRF-TOKEN: $t" -H 'Accept: application/json' -H 'Content-Type: application/json' \
      -d "{\"tabela_precos\":1,\"documento\":\"recibo\",\"itens\":[{\"produto_id\":3,\"quantidade\":1,\"preco_unitario\":299.90}],\"pagamentos\":[$1]}" "$BASE/app/pdv/venda"
}
venda "{\"forma\":\"pix\",\"valor\":$PIX}" >/dev/null
v=$(sql "select vi.preco_unitario from venda_itens vi order by vi.id desc limit 1")
check "venda no PIX grava o item a $PIX" "$([ "$v" = "$PIX" ] && echo 1)" "$v"
venda '{"forma":"cartao_credito","valor":299.90,"parcelas":3}' >/dev/null
v=$(sql "select vi.preco_unitario from venda_itens vi order by vi.id desc limit 1")
check "venda no crédito 3x grava o item a 299,90 (sem os 8%)" "$([ "$v" = "299.90" ] && echo 1)" "$v"
venda '{"forma":"pix","valor":100},{"forma":"cartao_credito","valor":199.90,"parcelas":1}' >/dev/null
v=$(sql "select vi.preco_unitario from venda_itens vi order by vi.id desc limit 1")
check "PIX + crédito (maior tabela) grava 299,90" "$([ "$v" = "299.90" ] && echo 1)" "$v"
r=$(venda '{"forma":"pix","valor":200}' | grep -o '"error":"[^"]*"' | head -1)
check "PIX pagando a menos é recusado (422 de sempre)" "$([ -n "$r" ] && echo 1)" "$r"

echo "== 6. Cadastro de produto na regra nova"
pg=$(curl -s -b "$DONO" "$BASE/app/produtos/3/edit" | tr "\n" " ")
check "rótulo 'Preço de venda (cartão)' e campo Preço no Dinheiro/PIX" "$(echo "$pg" | grep -q 'Preço de venda (cartão)' && echo "$pg" | grep -q 'name="preco_pix"' && echo 1)"
form_prod() { # campos extras
    local t; t=$(tok "$BASE" "$DONO" /app/produtos/3/edit)
    curl -s -c "$DONO" -b "$DONO" -o /dev/null -w "%{http_code}" -d "_token=$t&_method=PUT&descricao=Teclado Mecanico Redragon Kumara&unidade_medida=UN&preco_venda=299.90&preco_custo=150&status=ativo&$1" "$BASE/app/produtos/3"
}
form_prod "preco_cartao=&preco_pix=270" >/dev/null
b=$(precos "$DONO" "$BASE" PROD003)
check "PIX próprio 270 vale no PDV" "$([ "$b" = "dinheiro_pix=270.00 debito=299.90 credito=299.90" ] && echo 1)" "$b"
sql "update configuracoes_loja set preco_base='avista'"
form_prod "preco_cartao=" >/dev/null
check "salvar na regra de sempre (sem o campo) não apaga o PIX próprio" "$([ "$(sql "select count(*) from produto_precos where modalidade='dinheiro_pix'")" = 1 ] && echo 1)"
sql "update configuracoes_loja set preco_base='cartao'"
form_prod "preco_cartao=&preco_pix=" >/dev/null
check "PIX em branco apaga o preço próprio" "$([ "$(sql "select count(*) from produto_precos where modalidade='dinheiro_pix'")" = 0 ] && echo 1)"
sql "update produtos set preco_venda=1500 where id=3"

echo "== 7. Orçamento: taxa de entrega + quadro de pagamento"
pg=$(curl -s -b "$DONO" "$BASE/app/orcamentos/create" | tr "\n" " ")
check "novo orçamento já vem com 6x e 5%" "$(echo "$pg" | grep -q '<option value="6" selected' && echo "$pg" | grep -q 'id="descontoAvistaOrc"[^>]*value="5' && echo 1)"
orc() { # campos → id do orçamento criado
    local t; t=$(tok "$BASE" "$DONO" /app/orcamentos/create)
    curl -s -c "$DONO" -b "$DONO" -o /dev/null -d "_token=$t&cliente_id=1&validade_ate=$(date -d '+10 days' +%F)&itens[0][produto_id]=3&itens[0][quantidade]=1&itens[0][preco_unitario]=1500&$1" "$BASE/app/orcamentos"
    sql "select id from orcamentos order by id desc limit 1"
}
O1=$(orc "frete_valor=30&parcelas=5&desconto_avista_percentual=5")
row=$(sql "select subtotal, frete_valor, total, parcelas, desconto_avista_percentual from orcamentos where id=$O1")
check "grava subtotal 1500, entrega 30, total 1530, 5x, 5%" "$([ "$row" = $'1500.00\t30.00\t1530.00\t5\t5.00' ] && echo 1)" "$row"
pr=$(curl -s -b "$DONO" "$BASE/app/orcamentos/$O1/imprimir" | tr '\n' ' ')
check "folha: Taxa de entrega + R\$ 30,00 e TOTAL 1.530,00" "$(echo "$pr" | grep -q 'Taxa de entrega' && echo "$pr" | grep -q '1.530,00' && echo 1)"
check "folha (cartão): PIX R\$ 1.455,00 com 5% e 5x de R\$ 306,00 sem juros" "$(echo "$pr" | grep -q '1.455,00' && echo "$pr" | grep -q '5x de R\$ 306,00' && echo "$pr" | grep -q 'sem juros' && echo 1)"
sh=$(curl -s -b "$DONO" "$BASE/app/orcamentos/$O1" | tr '\n' ' ')
check "tela do orçamento mostra entrega e o quadro" "$(echo "$sh" | grep -q 'Taxa de entrega' && echo "$sh" | grep -q '5x de R\$ 306,00' && echo 1)"

sql "update configuracoes_loja set preco_base='avista', percentual_credito=8, juros_por_parcela='{\"5\": 10}'"
pr=$(curl -s -b "$DONO" "$BASE/app/orcamentos/$O1/imprimir" | tr '\n' ' ')
check "folha (regra de sempre, +8% e 10% de juros no 5x): cartão (1.620+30)×1,10 = 1.815 → 5x de 363,00 com juros" "$(echo "$pr" | grep -q '5x de R\$ 363,00' && echo "$pr" | grep -q 'com juros' && echo 1)"
sql "update configuracoes_loja set juros_por_parcela=NULL"

ed=$(curl -s -b "$DONO" "$BASE/app/orcamentos/$O1/edit" | tr "\n" " ")
check "editar traz 5x, 5% e entrega 30" "$(echo "$ed" | grep -q '<option value="5" selected' && echo "$ed" | grep -q 'name="frete_valor"[^>]*value="30' && echo 1)"
t=$(tok "$BASE" "$DONO" "/app/orcamentos/$O1/edit")
curl -s -c "$DONO" -b "$DONO" -o /dev/null -d "_token=$t&_method=PUT&cliente_id=1&validade_ate=$(date -d '+10 days' +%F)&itens[0][produto_id]=3&itens[0][quantidade]=2&itens[0][preco_unitario]=1500&desconto_valor=100&frete_valor=50&parcelas=&desconto_avista_percentual=5" "$BASE/app/orcamentos/$O1"
row=$(sql "select subtotal, desconto_valor, frete_valor, total, ifnull(parcelas,'-'), ifnull(desconto_avista_percentual,'-') from orcamentos where id=$O1")
check "editar: 2×1500 − 100 + 50 = 2950; desligar o quadro" "$([ "$row" = $'3000.00\t100.00\t50.00\t2950.00\t-\t-' ] && echo 1)" "$row"
pr=$(curl -s -b "$DONO" "$BASE/app/orcamentos/$O1/imprimir")
check "sem parcelas a folha não tem o quadro" "$(echo "$pr" | grep -q 'Formas de pagamento' || echo 1)"
t=$(tok "$BASE" "$DONO" "/app/orcamentos/$O1")
curl -s -c "$DONO" -b "$DONO" -o /dev/null -d "_token=$t" "$BASE/app/orcamentos/$O1/converter"
row=$(sql "select frete_valor, total from pedidos where orcamento_id=$O1")
check "converter em pedido leva a entrega (50) e o total (2950)" "$([ "$row" = $'50.00\t2950.00' ] && echo 1)" "$row"

O2=$(orc "parcelas=30")
check "30 parcelas é recusado" "$([ "$(sql 'select count(*) from orcamentos')" = 1 ] && echo 1)" "$O2"
O3=$(orc "frete_valor=0")
# 28/10 (integracao-28out): a folha ganhou a linha "Loja: X" (loja de saída) — retirada para comparar
texto() { limpa | tr '\n' ' ' | sed -E 's#<style>.*</style>##; s#<div class="loja">Loja: [^<]*</div>##g; s/[[:space:]]+/ /g; s/> </></g'; }
a=$(curl -s -b "$DA" "$ANTES/app/orcamentos/$O3/imprimir" | texto | md5sum); b=$(curl -s -b "$DONO" "$BASE/app/orcamentos/$O3/imprimir" | texto | md5sum)
check "orçamento sem entrega e sem parcelas: folha igual à de produção (fora o CSS novo e a linha Loja)" "$([ "$a" = "$b" ] && echo 1)"
sql "update configuracoes_loja set orcamento_parcelas=NULL"
pg=$(curl -s -b "$DONO" "$BASE/app/orcamentos/create" | tr "\n" " ")
check "loja sem 'orçamento em Nx': novo orçamento vem com 'Não mostrar'" "$(echo "$pg" | grep -q '<option value="" selected\|<option value="">Não mostrar' && ! echo "$pg" | grep -q '<option value="[0-9]*" selected' && echo 1)"

echo
echo "Resultado: $OK ✅  $FAIL ❌"
rm -rf "$TMP"
[ "$FAIL" = 0 ]
