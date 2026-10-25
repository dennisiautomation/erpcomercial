#!/usr/bin/env bash
# Validação ✅/❌ da branch unificacao-25out (25/10/2026). Ambiente DESCARTÁVEL: mesmo banco,
# código de produção em $ANTES e da unificação em $BASE.
#   BASE=http://127.0.0.1:8097 ANTES=http://127.0.0.1:8096 DB_CT=uni-mysql ROTAS=rotas.txt ./scripts/validar-unificacao-25out.sh
set -u
BASE=${BASE:-http://127.0.0.1:8097}; ANTES=${ANTES:-http://127.0.0.1:8096}; DB_CT=${DB_CT:-uni-mysql}; ROTAS=${ROTAS:?lista de rotas GET}
TMP=$(mktemp -d); OK=0; FAIL=0
check() { if [ "$2" = "1" ]; then OK=$((OK+1)); echo "✅ $1"; else FAIL=$((FAIL+1)); echo "❌ $1  (obtido: ${3:-})"; fi; }
sql() { docker exec "$DB_CT" mysql -uerp -perp erp_teste -N -B -e "$1" 2>/dev/null; }
login() { rm -f "$2"; local t; t=$(curl -s -c "$2" -b "$2" "$1/login" | grep -o 'name="_token" value="[^"]*"' | head -1 | cut -d'"' -f4); curl -s -c "$2" -b "$2" -o /dev/null -d "_token=$t&email=$3&password=$4" "$1/login"; }
code() { curl -s -b "$2" -o /dev/null -w "%{http_code}" "$1$3"; }
tok() { curl -s -c "$2" -b "$2" "$1$3" | grep -o 'name="_token" value="[^"]*"' | head -1 | cut -d'"' -f4; }

echo "== 1. Regressão: $(wc -l < "$ROTAS") telas × 5 perfis, mesmo código HTTP antes × depois"
for p in "admin@ia365.com.br admin123" "dono@demo.com dono123" "gerente@demo.com gerente123" "vendedor@demo.com vendedor123" "caixa@demo.com caixa123"; do
    set -- $p; A=$TMP/a; B=$TMP/b; login "$ANTES" "$A" $1 $2; login "$BASE" "$B" $1 $2
    dif=""; n=0
    while read -r u; do n=$((n+1)); a=$(code "$ANTES" "$A" "$u"); b=$(code "$BASE" "$B" "$u"); [ "$a" = "$b" ] || dif="$dif $u($a→$b)"; done < "$ROTAS"
    check "$1: $n telas com o mesmo código" "$([ -z "$dif" ] && echo 1)" "$dif"
done
D=$TMP/d; DA=$TMP/da; login "$BASE" "$D" dono@demo.com dono123; login "$ANTES" "$DA" dono@demo.com dono123
c500=$(while read -r u; do c=$(code "$BASE" "$D" "$u"); [ "$c" = 500 ] && echo -n "$u "; done < "$ROTAS")
check "nenhuma tela 500 para o dono na unificação" "$([ -z "$c500" ] && echo 1)" "$c500"

echo "== 2. O que veio de cada branch"
P1=$TMP/p1; P2=$TMP/p2; login "$ANTES" "$P1" dono@demo.com dono123; login "$BASE" "$P2" dono@demo.com dono123
for u in /app/pdv /app/pdv/produto/PROD002 /app/pdv/produto/Mouse; do
    x=$(curl -s -b "$P1" -H 'Accept: application/json' "$ANTES$u" | sed -E 's/[A-Za-z0-9]{40}//g; s/127.0.0.1:809[67]//g' | md5sum)
    y=$(curl -s -b "$P2" -H 'Accept: application/json' "$BASE$u" | sed -E 's/[A-Za-z0-9]{40}//g; s/127.0.0.1:809[67]//g' | md5sum)
    check "PDV igual à produção: $u" "$([ "$x" = "$y" ] && echo 1)"
done
check "PdvController e pdv/index idênticos aos de produção (git)" "$(cd "$(dirname "$0")/.." && git diff --quiet ac0d83a -- app/Http/Controllers/App/PdvController.php resources/views/app/pdv/index.blade.php && echo 1)"
pag=$(cd "$(dirname "$0")/.." && git diff --name-only ac0d83a HEAD -- app resources routes config database | grep -i -E "infinit|sicredi|gateway|pix|cartao|juros|caixa|pagamento|asaas|tabelapreco|agente|pdv" )
check "nenhum arquivo de pagamento/PDV/caixa/integração diferente da produção" "$([ -z "$pag" ] && echo 1)" "$pag"
v=$(sql "select id from vendas order by id desc limit 1")
check "Vendas: coluna Caixa na lista" "$(curl -s -b "$D" "$BASE/app/vendas" | grep -q '>Caixa</th>' && echo 1)"
[ -n "$v" ] && check "Venda: campo Loja no detalhe" "$(curl -s -b "$D" "$BASE/app/vendas/$v" | grep -q 'bi-shop me-1' && echo 1)"
check "Comissões: filtro por loja (200 e select de lojas)" "$([ "$(code "$BASE" "$D" '/app/comissoes?unidade_id=1')" = 200 ] && curl -s -b "$D" "$BASE/app/comissoes" | grep -q 'name="unidade_id"' && echo 1)"
check "Multilojas: colunas Comissão e Vendedores" "$(curl -s -b "$D" "$BASE/app/multilojas" | grep -q '>Vendedores</th>' && echo 1)"
check "Funcionários: sem o campo morto e-mail pessoal" "$(curl -s -b "$D" "$BASE/app/funcionarios/create" | grep -q 'email_pessoal' || echo 1)"
check "Abrir caixa: SEM o banner do Asaas (fica para o Dennis decidir)" "$(curl -s -b "$D" "$BASE/app/caixa/abrir" | grep -q -i 'asaas' || echo 1)"
pg=$(curl -s -b "$D" "$BASE/app/financeiro/contas-pagar/create")
check "Contas a pagar: tela explica o fornecedor" "$(echo "$pg" | grep -q -i 'Escolha o fornecedor na lista\|cadastre' && echo 1)"
t=$(tok "$BASE" "$D" /app/financeiro/contas-pagar/create)
r=$(curl -s -c "$D" -b "$D" -o /dev/null -w "%{redirect_url}" -d "_token=$t&descricao=ALUGUEL&valor=3000&vencimento=$(date +%F)" "$BASE/app/financeiro/contas-pagar")
m=$(curl -s -b "$D" "$BASE/app/financeiro/contas-pagar/create" | grep -o 'Escolha o fornecedor[^<]*' | head -1)
check "Contas a pagar: sem fornecedor → mensagem em português" "$([ -n "$m" ] && echo 1)" "$r"
l=$(curl -s "$BASE/" | grep -c 'Paulista, 1337'); la=$(curl -s "$ANTES/" | grep -c 'Paulista, 1337')
check "Site: endereço novo (Av. Paulista, 1337) na landing" "$([ "$l" -ge 1 ] && echo 1)" "antes $la depois $l"
rm -f $TMP/x; t=$(curl -s -c $TMP/x -b $TMP/x "$BASE/login" | grep -o 'name="_token" value="[^"]*"' | head -1 | cut -d'"' -f4)
msg=$(curl -s -c $TMP/x -b $TMP/x -L -d "_token=$t&email=dono@demo.com&password=errada" "$BASE/login" | grep -o 'E-mail ou senha incorretos[^<]*\|These credentials[^<]*' | head -1)
check "Login errado continua com mensagem em português" "$(echo "$msg" | grep -q -v 'These credentials' && [ -n "$msg" ] && echo 1)" "$msg"
check "Traduções: resources/lang segue valendo (validação em português)" "$(curl -s -b "$D" "$BASE/app/financeiro/contas-pagar/create" | grep -q 'These\|The .* field' || echo 1)"
docker exec uni-mysql mysql -uerp -perp erp_teste -e "update login_tentativas set falhas=0" >/dev/null 2>&1
echo; echo "Resultado: $OK ✅  $FAIL ❌"; rm -rf "$TMP"; [ "$FAIL" = 0 ]
