#!/usr/bin/env bash
# Validação ✅/❌ da API do app de vendas na maquininha Stone (api/integracao/pos/v1, 28/10/2026).
# Ambiente DESCARTÁVEL do tools/validar (seed demo). No `antes` as rotas não existem → ❌ (é a entrega).
#   BASE=http://127.0.0.1:8097 DB_CT=erp-val-...-mysql ./scripts/validar-pos-stone-28out.sh
# Nada sai para a Stone: o "retorno da Stone" é montado aqui (ATK/valor), como o app manda.
set -u
BASE=${BASE:-http://127.0.0.1:8097}; DB_CT=${DB_CT:-posval-mysql}
U=$BASE/api/integracao/pos/v1; OK=0; FAIL=0
check() { if [ "$2" = "1" ]; then OK=$((OK+1)); echo "✅ $1"; else FAIL=$((FAIL+1)); echo "❌ $1  (obtido: ${3:-})"; fi; }
sql() { docker exec "$DB_CT" mysql -uerp -perp erp_teste -N -B -e "$1" 2>/dev/null; }
j() { python3 -c "import sys,json
try: d=json.load(sys.stdin)
except Exception: print(''); sys.exit()
for k in sys.argv[1].split('.'):
    d = d[int(k)] if isinstance(d,list) and k.isdigit() else (d.get(k) if isinstance(d,dict) else None)
    if d is None: break
print('' if d is None else (json.dumps(d) if isinstance(d,(dict,list,bool)) else d))" "$1"; }
post() { curl -s -X POST "$U$1" -H 'Content-Type: application/json' -H 'Accept: application/json' ${3:+-H "Authorization: Bearer $3"} -d "$2"; }
postc() { curl -s -o /dev/null -w '%{http_code}' -X POST "$U$1" -H 'Content-Type: application/json' -H 'Accept: application/json' ${3:+-H "Authorization: Bearer $3"} -d "$2"; }
get() { curl -s "$U$1" -H 'Accept: application/json' -H "Authorization: Bearer $2"; }
uuid() { python3 -c 'import uuid;print(uuid.uuid4())'; }

echo "== 1. Login do operador"
R=$(post /auth/login '{"email":"caixa@demo.com","senha":"caixa123","serial":"VAL-POS-1","modelo":"Validacao"}')
TC=$(echo "$R" | j token)
check "login caixa devolve token pos_" "$([[ "$TC" == pos_* ]] && echo 1)" "$R"
TG=$(post /auth/login '{"email":"gerente@demo.com","senha":"gerente123"}' | j token)
check "login gerente" "$([[ "$TG" == pos_* ]] && echo 1)"
check "senha errada → 422" "$([ "$(postc /auth/login '{"email":"caixa@demo.com","senha":"errada"}')" = 422 ] && echo 1)"
check "sem token → 401" "$([ "$(curl -s -o /dev/null -w '%{http_code}' "$U/me")" = 401 ] && echo 1)"
check "token só guardado como sha256" "$([ "$(sql "select count(*) from pos_tokens where token_hash='$TC'")" = 0 ] && [ "$(sql "select count(*) from pos_tokens where dispositivo_serial='VAL-POS-1'")" -ge 1 ] && echo 1)"
ME=$(get /me "$TC")
check "/me: caixa vende e NÃO emite pedido (matriz do ERP)" "$([ "$(echo "$ME" | j permissoes.vender)" = true ] && [ "$(echo "$ME" | j permissoes.emitir_pedido)" = false ] && echo 1)" "$ME"

echo "== 2. Catálogo e caixa"
P=$(get '/produtos' "$TC"); PID=$(echo "$P" | j produtos.0.id); PRECO=$(echo "$P" | j produtos.0.precos.dinheiro_pix)
check "produtos com preço por tabela e estoque" "$([ -n "$PID" ] && [ -n "$PRECO" ] && echo 1)" "$P"
# Catálogo com ordenação (29/10): menor preço em ordem crescente; ordem inválida → 422
PM=$(get '/produtos?ordem=menor_preco' "$TC" | python3 -c 'import sys,json;v=[p["preco"] for p in json.load(sys.stdin)["produtos"]];print(1 if v and v==sorted(v) else 0)')
check "produtos?ordem=menor_preco vem do mais barato ao mais caro" "$PM"
check "produtos?ordem=inválida → 422" "$([ "$(curl -s -o /dev/null -w '%{http_code}' "$U/produtos?ordem=xyz" -H 'Accept: application/json' -H "Authorization: Bearer $TC")" = 422 ] && echo 1)"
c=$(postc /caixa/abrir '{"valor_abertura":100}' "$TC")
check "abrir caixa pelo POS (200, ou 422 se já aberto)" "$([ "$c" = 200 ] || [ "$c" = 422 ] && echo 1)" "$c"
check "caixa atual com resumo" "$([ -n "$(get /caixa "$TC" | j caixa.resumo.esperado_dinheiro)" ] && echo 1)"

echo "== 3. Venda: cotação exata, Stone, idempotência"
ANT=$(sql "select count(*) from vendas")
CT=$(post /vendas/cotar "{\"itens\":[{\"produto_id\":$PID,\"quantidade\":2}],\"pagamento\":{\"forma\":\"cartao_debito\"}}" "$TC")
TOT=$(echo "$CT" | j total_centavos); BASEV=$(echo "$CT" | j valor_base)
check "cotar devolve o total em centavos" "$([ -n "$TOT" ] && [ "$TOT" -gt 0 ] && echo 1)" "$CT"
check "cotar não grava venda" "$([ "$(sql "select count(*) from vendas")" = "$ANT" ] && echo 1)"
K=$(uuid)
V="{\"chave\":\"$K\",\"itens\":[{\"produto_id\":$PID,\"quantidade\":2}],\"pagamento\":{\"forma\":\"cartao_debito\",\"valor_base\":$BASEV,\"stone\":{\"atk\":\"VALATK1\",\"autorizacao\":\"111111\",\"bandeira\":\"MASTERCARD\",\"valor_centavos\":$TOT}}}"
R1=$(post /vendas "$V" "$TC"); VID=$(echo "$R1" | j venda.id)
check "venda no débito registrada" "$([ -n "$VID" ] && echo 1)" "$R1"
R2=$(post /vendas "$V" "$TC")
check "reenvio com a mesma chave devolve a MESMA venda (repetida)" "$([ "$(echo "$R2" | j venda.id)" = "$VID" ] && [ "$(echo "$R2" | j repetida)" = true ] && echo 1)" "$R2"
check "uma venda só no banco" "$([ "$(sql "select count(*) from vendas")" = "$((ANT+1))" ] && echo 1)"
PG=$(sql "select json_unquote(json_extract(pagamento_detalhes,'\$[0].bandeira')), json_unquote(json_extract(pagamento_detalhes,'\$[0].cnpj_credenciadora')), json_unquote(json_extract(pagamento_detalhes,'\$[0].stone_atk')) from vendas where id=$VID")
check "venda guarda bandeira NFC-e (02), CNPJ da Stone e ATK" "$([ "$PG" = "$(printf '02\t16501555000157\tVALATK1')" ] && echo 1)" "$PG"
check "cupom em linhas para a térmica" "$(echo "$R1" | j cupom | grep -q 'CUPOM N' && echo 1)"
DIV="{\"chave\":\"$(uuid)\",\"itens\":[{\"produto_id\":$PID,\"quantidade\":2}],\"pagamento\":{\"forma\":\"cartao_debito\",\"valor_base\":$BASEV,\"stone\":{\"atk\":\"VALATK2\",\"valor_centavos\":1}}}"
RD=$(post /vendas "$DIV" "$TC")
check "Stone ≠ total → 409 com estornar=true e nada gravado" "$([ "$(echo "$RD" | j estornar)" = true ] && [ "$(sql "select count(*) from vendas")" = "$((ANT+1))" ] && echo 1)" "$RD"

echo "== 4. Cancelamento com estorno"
VC=$(post "/vendas/$VID/cancelamento/validar" '{}' "$TC")
check "validar cancelamento lista o estorno por ATK" "$([ "$(echo "$VC" | j estornos.0.atk)" = VALATK1 ] && echo 1)" "$VC"
RC=$(post "/vendas/$VID/cancelar" '{"motivo":"Validacao do roteiro","estornos":[{"atk":"VALATK1","ok":true}]}' "$TC")
check "venda cancelada no ERP" "$([ "$(echo "$RC" | j venda.status)" = cancelada ] && echo 1)" "$RC"
check "estorno lançado no caixa" "$([ "$(sql "select count(*) from movimentacoes_caixa where venda_id=$VID and tipo='estorno'")" -ge 1 ] && echo 1)"

echo "== 5. Pedido: perfil, emissão, recebimento"
CLI=$(sql "select id from clientes where empresa_id=1 order by id limit 1" | head -1 | tr -d "[:space:]")
C403=$(postc /pedidos "{\"chave\":\"$(uuid)\",\"cliente_id\":$CLI,\"itens\":[{\"produto_id\":$PID,\"quantidade\":1}]}" "$TC")
check "perfil caixa não emite pedido (403)" "$([ "$C403" = 403 ] && echo 1)" "$C403"
postc /caixa/abrir '{"valor_abertura":50}' "$TG" >/dev/null
RP=$(post /pedidos "{\"chave\":\"$(uuid)\",\"cliente_id\":$CLI,\"confirmar\":true,\"itens\":[{\"produto_id\":$PID,\"quantidade\":3}]}" "$TG")
PEDID=$(echo "$RP" | j pedido.id); PT=$(echo "$RP" | j pedido.total_centavos)
check "gerente emite pedido confirmado" "$([ "$(echo "$RP" | j pedido.status)" = confirmado ] && echo 1)" "$RP"
RF=$(post "/pedidos/$PEDID/faturar" "{\"chave\":\"$(uuid)\",\"pagamento\":{\"forma\":\"pix\",\"stone\":{\"atk\":\"VALPIX\",\"valor_centavos\":$PT}}}" "$TG")
check "pedido recebido no PIX → faturado, venda ligada" "$([ "$(sql "select status from pedidos where id=$PEDID")" = faturado ] && [ -n "$(echo "$RF" | j venda.id)" ] && echo 1)" "$RF"
# Pedido de balcão sem cliente (29/10): só o nome/mesa, cobrado depois
RB=$(post /pedidos "{\"chave\":\"$(uuid)\",\"identificacao\":\"Mesa 7\",\"confirmar\":true,\"itens\":[{\"produto_id\":$PID,\"quantidade\":1}]}" "$TG")
PB=$(echo "$RB" | j pedido.id)
check "pedido sem cliente com nome → cupom 'PARA: MESA 7', cliente vazio" "$(echo "$RB" | grep -q 'PARA: MESA 7' && [ "$(sql "select ifnull(cliente_id,'nulo') from pedidos where id=$PB")" = nulo ] && echo 1)" "$RB"
RBF=$(post "/pedidos/$PB/faturar" "{\"chave\":\"$(uuid)\",\"pagamento\":{\"forma\":\"dinheiro\",\"valor_recebido\":1000}}" "$TG")
check "pedido de balcão cobrado depois em dinheiro → faturado" "$([ "$(sql "select status from pedidos where id=$PB")" = faturado ] && echo 1)" "$RBF"
# Lista de pedidos por pagamento (29/10): o pedido recebido está em "pago", não em "a receber"; totais batem com a lista
LPG=$(get "/pedidos?pagamento=pago&q=Mesa%207" "$TG"); LAR=$(get "/pedidos?pagamento=a_receber&q=Mesa%207" "$TG")
check "pedidos?pagamento=pago traz o pedido recebido com pago=true" "$(echo "$LPG" | python3 -c "import sys,json;d=json.load(sys.stdin);print(1 if any(p['id']==$PB and p['pago'] for p in d['pedidos']) and d['totais']['pago']['qtd']==len(d['pedidos']) else 0)")" "$LPG"
check "pedidos?pagamento=a_receber não traz o pedido recebido" "$(echo "$LAR" | python3 -c "import sys,json;d=json.load(sys.stdin);print(0 if any(p['id']==$PB for p in d['pedidos']) else 1)")" "$LAR"

echo "== 6. Log de atividade (contrato Stone, 180 dias)"
RE=$(post /eventos '{"eventos":[{"tipo":"stone_pagamento_recusado","mensagem":"Senha invalida"}]}' "$TC")
check "eventos do app gravados" "$([ "$(echo "$RE" | j gravados)" = 1 ] && [ "$(sql "select count(*) from pos_eventos where tipo='app_stone_pagamento_recusado'")" -ge 1 ] && echo 1)" "$RE"
check "login e venda registrados no log" "$([ "$(sql "select count(*) from pos_eventos where tipo in ('login','venda_registrada','venda_cancelada')")" -ge 3 ] && echo 1)"

echo; echo "Resultado: $OK ✅  $FAIL ❌"; [ "$FAIL" = 0 ]
