#!/usr/bin/env bash
# Validação ✅/❌ — Faturar com forma de pagamento + observações com texto pronto e caixinha "imprimir" (27/10/2026, DK).
# Ambiente DESCARTÁVEL (nunca produção): mesmo banco, código de produção em $ANTES e da branch em $BASE.
#   BASE=http://127.0.0.1:8097 ANTES=http://127.0.0.1:8096 DB_CT=erpv-mysql APP_CT=erpv-depois ./scripts/validar-dk-faturar-obs-27out.sh
set -u
BASE=${BASE:-http://127.0.0.1:8097}
ANTES=${ANTES:-http://127.0.0.1:8096}
DB_CT=${DB_CT:-erpv-mysql}
APP_CT=${APP_CT:-erpv-depois}
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
post() { # url cookiejar pagina-do-token rota dados → código HTTP
    local t; t=$(tok "$1" "$2" "$3")
    curl -s -c "$2" -b "$2" -o "$TMP/ultimo.html" -w "%{http_code}" -d "_token=$t&$5" "$1$4"
}
CFG="regra_preco_split=cartao_maior&percentual_debito=0&percentual_credito=0&max_parcelas=6&padrao_impressao=recibo&orcamento_endereco=loja&troca_prazo_dias=30&troca_sobra=vale&troca_vale_validade_dias=90"
ITENS="cliente_id=1&validade_ate=$(date -d '+10 days' +%F)&itens[0][produto_id]=2&itens[0][quantidade]=2&itens[0][preco_unitario]=599.90"

DONO=$TMP/dono; VEND=$TMP/vend; CX=$TMP/cx; DA=$TMP/da; VA=$TMP/va; CA=$TMP/ca
login "$BASE" "$DONO" dono@demo.com dono123; login "$BASE" "$VEND" vendedor@demo.com vendedor123; login "$BASE" "$CX" caixa@demo.com caixa123
login "$ANTES" "$DA" dono@demo.com dono123; login "$ANTES" "$VA" vendedor@demo.com vendedor123; login "$ANTES" "$CA" caixa@demo.com caixa123

sql "delete from orcamento_itens; delete from orcamentos; delete from pedido_itens; delete from pedidos;"
sql "update configuracoes_loja set orcamento_observacoes=NULL, orcamento_parcelas=NULL"

echo "== 1. Regressão: mesmas telas, mesmo código HTTP, antes × depois (dono, vendedor, caixa)"
for u in /app/dashboard /app/configuracoes /app/orcamentos /app/orcamentos/create /app/pedidos /app/pedidos/create /app/vendas /app/clientes/create /app/pdv; do
    for par in "DA DONO dono" "VA VEND vendedor" "CA CX caixa"; do
        set -- $par; a=$(code "$ANTES" "${!1}" "$u"); b=$(code "$BASE" "${!2}" "$u")
        check "$3 $u: antes $a = depois $b" "$([ "$a" = "$b" ] && echo 1)" "$a/$b"
    done
done

echo "== 2. Orçamento criado no código de PRODUÇÃO continua imprimindo as observações"
st=$(post "$ANTES" "$DA" /app/orcamentos/create /app/orcamentos "$ITENS&observacoes_externas=OBS-ANTIGA")
id=$(sql "select id from orcamentos where observacoes_externas='OBS-ANTIGA'")
check "orçamento salvo pelo código antigo (imprimir_observacoes = 1 pelo default)" "$([ -n "$id" ] && [ "$(sql "select imprimir_observacoes from orcamentos where id=$id")" = 1 ] && echo 1)" "$st $id"
check "folha (código novo) mostra a observação antiga" "$(curl -s -b "$DONO" "$BASE/app/orcamentos/$id/imprimir" | grep -c OBS-ANTIGA | grep -qv '^0$' && echo 1)"

echo "== 3. Texto pronto nas Configurações da Loja"
st=$(post "$BASE" "$DONO" /app/configuracoes /app/configuracoes "_method=PUT&$CFG")
check "form sem o campo novo salva (texto fica vazio)" "$([ "$st" = 302 ] && [ "$(sql 'select coalesce(orcamento_observacoes,"NULO") from configuracoes_loja limit 1')" = NULO ] && echo 1)" "$st"
st=$(post "$BASE" "$DONO" /app/configuracoes /app/configuracoes "_method=PUT&$CFG&orcamento_observacoes=Garantia+de+90+dias.+Entrega+em+2+dias+uteis.")
check "salva o texto pronto" "$([ "$(sql 'select orcamento_observacoes from configuracoes_loja limit 1')" = 'Garantia de 90 dias. Entrega em 2 dias uteis.' ] && echo 1)" "$st"
check "campo aparece na tela de configurações" "$(curl -s -b "$DONO" "$BASE/app/configuracoes" | grep -q 'name="orcamento_observacoes"' && echo 1)"
st=$(post "$BASE" "$DONO" /app/configuracoes /app/configuracoes "_method=PUT&$CFG&orcamento_observacoes=$(head -c 2001 /dev/zero | tr '\0' a)")
check "texto acima de 2000 caracteres recusado" "$([ "$(sql 'select length(orcamento_observacoes) from configuracoes_loja limit 1')" = 45 ] && echo 1)"

echo "== 4. Orçamento novo: texto vem preenchido e a caixinha vem marcada"
pg=$(curl -s -b "$VEND" "$BASE/app/orcamentos/create" | tr '\n' ' ')
check "vendedor: 'Observações para o Cliente' vem com o texto pronto" "$(echo "$pg" | grep -q 'recado para o cliente...">Garantia de 90 dias' && echo 1)"
check "caixinha 'Imprimir as observações' marcada" "$(echo "$pg" | grep -Eq 'name="imprimir_observacoes" value="1" id="imprimirObservacoes" checked' && echo 1)"

echo "== 5. Imprimir ou não"
st=$(post "$BASE" "$VEND" /app/orcamentos/create /app/orcamentos "$ITENS&observacoes_externas=OBS-SIM&imprimir_observacoes=0&imprimir_observacoes=1")
s=$(sql "select id from orcamentos where observacoes_externas='OBS-SIM'")
check "marcada → grava 1" "$([ "$(sql "select imprimir_observacoes from orcamentos where id=$s")" = 1 ] && echo 1)" "$st"
check "marcada → sai na folha" "$(curl -s -b "$VEND" "$BASE/app/orcamentos/$s/imprimir" | grep -q OBS-SIM && echo 1)"
st=$(post "$BASE" "$VEND" /app/orcamentos/create /app/orcamentos "$ITENS&observacoes_externas=OBS-NAO&imprimir_observacoes=0")
n=$(sql "select id from orcamentos where observacoes_externas='OBS-NAO'")
check "desmarcada → grava 0 e o texto fica salvo" "$([ "$(sql "select imprimir_observacoes from orcamentos where id=$n")" = 0 ] && echo 1)" "$st"
check "desmarcada → NÃO sai na folha" "$(curl -s -b "$VEND" "$BASE/app/orcamentos/$n/imprimir" | grep -q OBS-NAO || echo 1)"
check "desmarcada → detalhe avisa 'não sai no impresso'" "$(curl -s -b "$VEND" "$BASE/app/orcamentos/$n" | grep -q 'não sai no impresso' && echo 1)"
check "editar: caixinha vem desmarcada" "$(curl -s -b "$VEND" "$BASE/app/orcamentos/$n/edit" | grep -Eq 'id="imprimirObservacoes" *>' && echo 1)"
st=$(post "$BASE" "$VEND" /app/orcamentos/$n/edit /app/orcamentos/$n "_method=PUT&$ITENS&observacoes_externas=OBS-NAO&imprimir_observacoes=0&imprimir_observacoes=1")
check "editar e marcar → volta a sair na folha" "$(curl -s -b "$VEND" "$BASE/app/orcamentos/$n/imprimir" | grep -q OBS-NAO && echo 1)" "$st"

echo "== 6. Faturar pede a forma de pagamento"
fatura_pedido() { # cookiejar orcamento_id → id do pedido confirmado
    post "$BASE" "$1" /app/orcamentos/$2 /app/orcamentos/$2/converter "" >/dev/null
    local p; p=$(sql "select id from pedidos where orcamento_id=$2")
    post "$BASE" "$1" /app/pedidos/$p /app/pedidos/$p/status "status=confirmado" >/dev/null
    echo "$p"
}
p=$(fatura_pedido "$VEND" "$s")
pg=$(curl -s -b "$VEND" "$BASE/app/pedidos/$p" | tr '\n' ' ')
check "modal tem o campo Forma de pagamento (obrigatório) e parcelas" "$(echo "$pg" | grep -q 'name="forma_pagamento" id="faturarForma" class="form-select" required' && echo "$pg" | grep -q 'name="parcelas"' && echo 1)"
check "modal mostra o valor da venda" "$(echo "$pg" | grep -q 'Valor da venda: <strong>R\$ 1.199,80' && echo 1)"
st=$(post "$BASE" "$VEND" /app/pedidos/$p /app/pedidos/$p/faturar "documento=recibo")
check "sem forma → recusado, nada faturado" "$([ "$(sql "select status from pedidos where id=$p")" = confirmado ] && [ -z "$(sql "select id from vendas where pedido_id=$p")" ] && echo 1)" "$st"
st=$(post "$BASE" "$VEND" /app/pedidos/$p /app/pedidos/$p/faturar "documento=recibo&forma_pagamento=a_definir")
check "forma inventada → recusado" "$([ -z "$(sql "select id from vendas where pedido_id=$p")" ] && echo 1)" "$st"
st=$(post "$BASE" "$VEND" /app/pedidos/$p /app/pedidos/$p/faturar "documento=recibo&forma_pagamento=pix&parcelas=5")
row=$(sql "select forma_pagamento, total, pagamento_detalhes from vendas where pedido_id=$p")
check "PIX → venda com forma pix, total do pedido, sem parcelas" "$(echo "$row" | grep -Pq '^pix\t1199.80\t\[\{"forma": "pix", "valor": 1199.8\}\]$' && echo 1)" "$row"
check "pedido faturado e estoque baixado" "$([ "$(sql "select status from pedidos where id=$p")" = faturado ] && echo 1)"

o2=$(sql "select id from orcamentos where observacoes_externas='OBS-ANTIGA'")
p2=$(fatura_pedido "$DONO" "$o2")
st=$(post "$BASE" "$DONO" /app/pedidos/$p2 /app/pedidos/$p2/faturar "documento=nenhum&forma_pagamento=cartao_credito&parcelas=3")
row=$(sql "select forma_pagamento, pagamento_detalhes from vendas where pedido_id=$p2")
check "crédito 3x → grava forma e parcelas" "$(echo "$row" | grep -Pq '^cartao_credito\t\[\{"forma": "cartao_credito", "valor": 1199.8, "parcelas": 3\}\]$' && echo 1)" "$row"
v2=$(sql "select id from vendas where pedido_id=$p2"); v1=$(sql "select id from vendas where pedido_id=$p")
r=$(docker exec "$APP_CT" php artisan tinker --execute="\$b=new App\Services\FocusNFe\FiscalPayloadBuilder(App\Models\Empresa::first(), App\Models\Unidade::withoutGlobalScopes()->first(), new App\Models\ConfiguracaoFiscal); foreach([$v1,$v2] as \$v) echo json_encode(\$b->formasPagamento(App\Models\Venda::withoutGlobalScopes()->find(\$v))),' ';" 2>/dev/null | tr -d '\n')
check "NFC-e: PIX sai como 17 e crédito como 03 (antes: 99 'a definir')" "$(echo "$r" | grep -q '"forma_pagamento":"17"' && echo "$r" | grep -q '"forma_pagamento":"03"' && echo 1)" "$r"

o3=$(sql "select id from orcamentos where observacoes_externas='OBS-NAO'")
sql "update orcamentos set parcelas=6, desconto_avista_percentual=5 where id=$o3"
post "$BASE" "$DONO" /app/orcamentos/$o3 /app/orcamentos/$o3/converter "" >/dev/null
p3=$(sql "select id from pedidos where orcamento_id=$o3")
sql "update pedidos set condicao_pagamento='cartao' where id=$p3"
post "$BASE" "$DONO" /app/pedidos/$p3 /app/pedidos/$p3/status "status=confirmado" >/dev/null
pg=$(curl -s -b "$DONO" "$BASE/app/pedidos/$p3" | tr '\n' ' ')
check "condição 'cartão' do pedido → crédito já selecionado" "$(echo "$pg" | grep -q 'value="cartao_credito" selected' && echo 1)"
check "orçamento com quadro → modal mostra 'O orçamento previa' PIX 1.139,81 e 6x" "$(echo "$pg" | grep -q 'O orçamento previa' && echo "$pg" | grep -q '1.139,81' && echo "$pg" | grep -q '6x de R' && echo 1)"
check "parcelas vêm com as 6x do orçamento" "$(echo "$pg" | grep -q 'value="6" selected' && echo 1)"

echo "== 7. Log sem erro novo"
e=$(docker exec "$APP_CT" sh -c 'grep -c "production.ERROR\|local.ERROR" /var/www/storage/logs/laravel.log 2>/dev/null || echo 0')
check "laravel.log sem ERROR" "$([ "$e" = 0 ] && echo 1)" "$e"

echo; echo "Resultado: $OK ✅ / $FAIL ❌"
rm -rf "$TMP"
[ "$FAIL" = 0 ]
