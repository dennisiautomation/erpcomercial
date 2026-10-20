#!/bin/bash
# Validação da branch treinamento: upload em pedaços, Range, perfil, concluir, admin.
T=$(dirname "$0"); B=http://127.0.0.1:8099; J=$T/jar; ok=0; falha=0
chk(){ if [ "$1" = "$2" ]; then echo "✅ $3 ($1)"; ok=$((ok+1)); else echo "❌ $3 (esperado $2, veio $1)"; falha=$((falha+1)); fi; }
token(){ curl -s -b $J -c $J "$1" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login(){ rm -f $J; local tk=$(token $B/login); curl -s -o /dev/null -w '%{http_code}' -b $J -c $J -X POST $B/login -d "_token=$tk&email=$1&password=$2"; }
csrf(){ curl -s -b $J -c $J $B/admin/treinamentos/create | grep -o 'name="csrf-token" content="[^"]*"' | sed 's/.*content="//;s/"//'; }

echo "== 1. tamanho do pedaço (php.ini da imagem nova e da antiga)"
P=$(docker exec erp-trein-app php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo App\Http\Controllers\Admin\TreinamentoController::tamanhoDoPedaco()/1048576;')
chk "$P" "39" "pedaço com 40M/48M"
P=$(docker exec erp-trein-app php -d upload_max_filesize=16M -d post_max_size=20M -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo App\Http\Controllers\Admin\TreinamentoController::tamanhoDoPedaco()/1048576;')
chk "$P" "15" "pedaço com 16M/20M (imagem antiga)"
chk "$(docker exec erp-trein-app php -r 'echo ini_get("upload_max_filesize"),"/",ini_get("post_max_size");')" "40M/48M" "php.ini na imagem"
chk "$(docker exec erp-trein-app grep -o 'client_max_body_size [0-9]*M' /etc/nginx/nginx.conf)" "client_max_body_size 50M" "nginx na imagem"

echo "== 2. login admin"
chk "$(login admin@ia365.com.br admin123)" "302" "login admin"
chk "$(curl -s -o /dev/null -w '%{http_code}' -b $J $B/admin/treinamentos)" "200" "GET /admin/treinamentos"
chk "$(curl -s -b $J $B/admin/treinamentos/create | grep -c 'const PEDACO = 40894464')" "1" "form recebe PEDACO=39 MB"

echo "== 3. vídeo de 300 MB em pedaços de 39 MB"
V=$T/video.mp4; [ -f $V ] || head -c 314572800 /dev/urandom > $V
SHA=$(sha256sum $V | cut -c1-16); PED=40894464; TOT=$(( (314572800 + PED - 1) / PED ))
UID_=$(cat /proc/sys/kernel/random/uuid); CS=$(csrf); ARQ=""
rm -rf $T/partes; mkdir -p $T/partes; split -b $PED -d -a 3 $V $T/partes/p
i=0; for f in $T/partes/p*; do
  R=$(curl -s -b $J -c $J -H "Accept: application/json" -H "X-CSRF-TOKEN: $CS" -F upload_id=$UID_ -F indice=$i -F total=$TOT -F nome=video.mp4 -F pedaco=@$f $B/admin/treinamentos/upload)
  code=$?; [ $code -ne 0 ] && echo "curl rc=$code na parte $i"
  echo "$R" | grep -q '"arquivo"' && ARQ=$(echo "$R" | sed 's/.*"arquivo":"\([^"]*\)".*/\1/')
  echo "$R" | grep -q -E '"ok"|"arquivo"' || echo "parte $i: $R"
  i=$((i+1)); done
chk "$i" "$TOT" "todas as $TOT partes enviadas"
[ -n "$ARQ" ] && echo "✅ último pedaço devolveu arquivo=$ARQ" || { echo "❌ sem nome de arquivo"; falha=$((falha+1)); }
chk "$(docker exec erp-trein-app sha256sum storage/app/private/treinamentos/$ARQ | cut -c1-16)" "$SHA" "hash igual no servidor"
chk "$(docker exec erp-trein-app sh -c 'ls storage/app/private/treinamentos/tmp | wc -l')" "0" "tmp vazio depois de juntar"

echo "== 4. salvar treinamento (perfil vendedor) e um 2º só para caixa"
chk "$(curl -s -o /dev/null -w '%{http_code}' -b $J -c $J -X POST $B/admin/treinamentos -d "_token=$CS&titulo=Como+vender+no+PDV&descricao=teste&ordem=1&ativo=1&perfis[]=vendedor&arquivo=$ARQ")" "302" "POST store"
ID=$(docker exec erp-trein-mysql mysql -uerp -perp erp_teste -N -e "select id from treinamentos where titulo='Como vender no PDV'" 2>/dev/null)
chk "$(docker exec erp-trein-mysql mysql -uerp -perp erp_teste -N -e "select tamanho from treinamentos where id=$ID" 2>/dev/null)" "314572800" "tamanho gravado"
chk "$(curl -s -o /dev/null -w '%{http_code}' -b $J -c $J -X POST $B/admin/treinamentos -d "_token=$CS&titulo=Arquivo+falso&ordem=2&ativo=1&perfis[]=caixa&arquivo=../../.env")" "302" "arquivo inválido é recusado (volta ao form)"
chk "$(docker exec erp-trein-mysql mysql -uerp -perp erp_teste -N -e "select count(*) from treinamentos where titulo='Arquivo falso'" 2>/dev/null)" "0" "…e não grava"
chk "$(curl -s -o /dev/null -w '%{http_code}' -r 0-99 -b $J $B/admin/treinamentos/$ID/video)" "206" "admin Range 206"
chk "$(curl -s -o /dev/null -w '%{http_code}' -b $J $B/admin/treinamentos)" "200" "lista admin"

echo "== 5. vendedor vê, toca, conclui"
chk "$(login vendedor@demo.com vendedor123)" "302" "login vendedor"
H=$(curl -s -b $J -c $J $B/app/treinamento); CS2=$(echo "$H" | grep -o 'name="csrf-token" content="[^"]*"' | sed 's/.*content="//;s/"//')
chk "$(echo "$H" | grep -c 'data-titulo="Como vender no PDV"')" "1" "aba lista o vídeo do perfil"
chk "$(echo "$H" | grep -c 'Pendente')" "1" "selo Pendente"
chk "$(curl -s -o /dev/null -w '%{http_code}' -r 1000000-1000099 -b $J $B/app/treinamento/$ID/video)" "206" "vendedor Range 206"
chk "$(curl -s -b $J -H "X-CSRF-TOKEN: $CS2" -H "Accept: application/json" -X POST $B/app/treinamento/$ID/concluir | grep -c concluido_em)" "1" "POST concluir"
chk "$(curl -s -b $J $B/app/treinamento | grep -c 'badge bg-success" title="Assistido em [0-9]')" "1" "selo Assistido"
chk "$(docker exec erp-trein-mysql mysql -uerp -perp erp_teste -N -e "select count(*) from treinamento_user" 2>/dev/null)" "1" "1 linha em treinamento_user"

echo "== 6. caixa não vê vídeo de vendedor"
chk "$(login caixa@demo.com caixa123)" "302" "login caixa"
chk "$(curl -s -b $J $B/app/treinamento | grep -c 'Como vender no PDV')" "0" "aba não lista"
chk "$(curl -s -o /dev/null -w '%{http_code}' -b $J $B/app/treinamento/$ID/video)" "404" "vídeo 404"

echo "== 7. admin vê o quadro da empresa"
login admin@ia365.com.br admin123 >/dev/null
EMP=$(docker exec erp-trein-mysql mysql -uerp -perp erp_teste -N -e "select empresa_id from users where email='vendedor@demo.com'" 2>/dev/null)
H=$(curl -s -b $J $B/admin/empresas/$EMP)
chk "$(curl -s -o /dev/null -w '%{http_code}' -b $J $B/admin/empresas/$EMP)" "200" "show empresa 200"
chk "$(echo "$H" | grep -c 'Acompanhamento do treinamento')" "1" "aba Treinamento no show"
echo; echo "RESULTADO: $ok ✅  $falha ❌"
