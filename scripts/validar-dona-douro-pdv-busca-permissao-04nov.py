#!/usr/bin/env python3
"""Validação ✅/❌ — DONA DOURO (04/11/2026): (1) busca de produto do PDV/orçamento por código exato
e por palavras; (2) chave `pode_editar_produtos` por usuário (vendedor/caixa cadastra e edita produtos).

Ambiente DESCARTÁVEL (nunca produção), com a migration 2026_11_04_120000 aplicada:
    BASE=http://127.0.0.1:8297 DB_CT=cs-mysql APP_CT=cs-app python3 scripts/validar-dona-douro-pdv-busca-permissao-04nov.py
"""
import http.cookiejar
import json
import os
import re
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request

BASE = os.environ.get("BASE", "http://127.0.0.1:8297")
DB_CT = os.environ.get("DB_CT", "cs-mysql")
APP_CT = os.environ.get("APP_CT", "cs-app")
OK = FAIL = 0


def check(nome, cond, obtido=""):
    global OK, FAIL
    if cond:
        OK += 1
        print(f"✅ {nome}")
    else:
        FAIL += 1
        print(f"❌ {nome}  (obtido: {str(obtido)[:300]})")


def sql(q):
    r = subprocess.run(["docker", "exec", "-i", DB_CT, "mysql", "-uerp", "-perp", "erp_teste", "-N", "-B"],
                       input=q, capture_output=True, text=True)
    if r.returncode:
        print("SQL ERRO:", r.stderr.strip()[-300:])
    return r.stdout.strip()


class Sessao:
    def __init__(self, email, senha, unidade=None):
        self.cj = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cj))
        html = self.get("/login")
        self.post_form("/login", {"_token": self.token(html), "email": email, "password": senha})
        if unidade:
            html = self.get("/selecionar-unidade")
            self.post_form("/selecionar-unidade", {"_token": self.token(html), "unidade_id": unidade})

    def req(self, path, data=None, headers=None, method=None):
        r = urllib.request.Request(BASE + path, data=data, headers=headers or {}, method=method)
        try:
            with self.op.open(r) as resp:
                return resp.status, resp.read().decode("utf-8", "replace"), resp.geturl()
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8", "replace"), ""

    def get(self, path):
        return self.req(path)[1]

    def status(self, path):
        return self.req(path)[0]

    @staticmethod
    def token(html):
        m = re.search(r'name="_token" value="([^"]+)"', html) or re.search(r'name="csrf-token" content="([^"]+)"', html)
        return m.group(1) if m else ""

    def post_form(self, path, campos):
        return self.req(path, urllib.parse.urlencode(campos, doseq=True).encode())

    def json(self, path):
        st, txt, _ = self.req(path, headers={"X-Requested-With": "XMLHttpRequest", "Accept": "application/json"})
        try:
            return st, json.loads(txt)
        except ValueError:
            return st, txt[:200]


def pid(cod):
    return int(sql(f"select id from produtos where empresa_id=1 and codigo_interno='{cod}'"))


def preparar_produtos():
    sql("delete from produtos where empresa_id=1 and codigo_interno like 'DD%'")
    sql("update produtos set codigo_barras='7891234567895' where id=3")
    linhas = [
        ("DD1", "E3044", "ANEL 3 LINHA  ZIRCONIA 2 FILEIRA DE OURO"),   # espaço duplo, como na DONA DOURO
        ("DD2", "7671", "COLAR CRUZ MICRO ZIRCONIA DOURADO"),
        ("DD3", "X7671", "PRODUTO 7671 ESPECIAL"),                      # descrição contém o código do DD2
    ] + [(f"DD{10 + i}", f"BR{10 + i}", f"BRACELETE LISO BRANCO {i:02d}") for i in range(1, 26)]
    vals = ", ".join(f"(1, '{c}', '{b}', '{d}', 10, 'UN', 'ativo', now(), now())" for c, b, d in linhas)
    sql("insert into produtos (empresa_id, codigo_interno, codigo_barras, descricao, preco_venda, unidade_medida, status, created_at, updated_at) values " + vals)


def main():
    print("== 1. Busca do PDV")
    preparar_produtos()
    d = Sessao("dono@demo.com", "dono123", unidade=1)
    busca = lambda t: d.json("/app/pdv/produto/" + urllib.parse.quote(t))

    st, r = busca("7671")
    check("código exato (barras 7671) devolve SÓ esse produto — o leitor bipa e entra direto",
          st == 200 and [p["codigo_interno"] for p in r] == ["DD2"], (st, r))
    st, r = busca("DD1")
    check("código interno exato", st == 200 and [p["codigo_interno"] for p in r] == ["DD1"], (st, r))
    st, r = busca("7891234567895")
    check("EAN do seed continua achando 1 produto (fluxo do leitor igual)", st == 200 and len(r) == 1 and r[0]["id"] == 3, (st, r))
    st, r = busca("linha zirconia")
    check("'linha zirconia' acha a descrição com ESPAÇO DUPLO (antes não achava)", st == 200 and any(p["codigo_interno"] == "DD1" for p in r), (st, r))
    st, r = busca("zirconia colar dourado")
    check("palavras em QUALQUER ordem acham 'COLAR CRUZ MICRO ZIRCONIA DOURADO'", st == 200 and any(p["codigo_interno"] == "DD2" for p in r), (st, r))
    st, r = busca("bracelete liso")
    check("'bracelete liso' traz os 25 (antes cortava em 20)", st == 200 and sum(p["codigo_interno"].startswith("DD") for p in r) == 25, (st, len(r)))
    st, r = busca("BRACELETE LISO BRANCO 2")
    check("começa pelo termo vem primeiro (BRACELETE LISO BRANCO 2x)", st == 200 and r and r[0]["descricao"].startswith("BRACELETE LISO BRANCO 2"), (st, r[:2]))
    st, r = busca("xyzabc")
    check("termo sem produto = lista vazia", st == 200 and r == [], (st, r))
    st, r = busca("%")
    check("'%' não vira curinga (lista vazia, não a empresa inteira)", st == 200 and r == [], (st, len(r) if isinstance(r, list) else r))
    st, r = busca("   colar   cruz  ")
    check("espaços sobrando no termo são ignorados", st == 200 and any(p["codigo_interno"] == "DD2" for p in r), (st, r))
    st, r = busca("7671")
    check("resposta mantém precos/preco_venda (o PDV usa)", st == 200 and "precos" in r[0] and "preco_venda" in r[0], r)

    print("== 2. Busca do orçamento/pedido (SearchController)")
    st, r = d.json("/app/search/produtos?q=" + urllib.parse.quote("linha zirconia"))
    check("orçamento/pedido: 'linha zirconia' acha o DD1", st == 200 and any(p["codigo_interno"] == "DD1" for p in r), (st, r))
    st, r = d.json("/app/search/produtos?q=7671")
    check("orçamento/pedido: código exato só o DD2, com precos", st == 200 and [p["codigo_interno"] for p in r] == ["DD2"] and "precos" in r[0], (st, r))

    print("== 3. Chave pode_editar_produtos (vendedor)")
    sql("update users set pode_editar_produtos=0 where id in (4,5)")
    sql("update empresas set vendedor_apenas_pdv=0 where id=1")
    sql("delete from unidade_user where user_id in (4,5)")
    sql("insert into unidade_user (user_id, unidade_id, created_at, updated_at) values (4,1,now(),now()),(5,1,now(),now())")
    p1 = pid("DD1")
    v = Sessao("vendedor@demo.com", "vendedor123", unidade=1)
    html = v.get("/app/produtos/create")
    st, _, _ = v.req("/app/produtos", urllib.parse.urlencode({"_token": v.token(html), "codigo_interno": "DD98", "descricao": "SEM CHAVE", "unidade_medida": "UN", "preco_venda": "1"}).encode())
    check("vendedor SEM a chave: cadastrar produto = 403 (como antes)", st == 403 and sql("select count(*) from produtos where empresa_id=1 and descricao='SEM CHAVE'") == "0", st)
    st, html, _ = v.req(f"/app/produtos/{p1}/edit")
    check("vendedor SEM a chave: tela de editar abre (só foto, como antes)", st == 200, st)
    tok = v.token(html)
    st, _, _ = v.req(f"/app/produtos/{p1}", urllib.parse.urlencode({"_token": tok, "_method": "PUT", "descricao": "MUDOU SEM CHAVE", "unidade_medida": "UN", "preco_venda": "10", "status": "ativo"}).encode())
    check("vendedor SEM a chave: salvar sem foto não altera a descrição", sql(f"select descricao from produtos where id={p1}").startswith("ANEL 3 LINHA"), sql(f"select descricao from produtos where id={p1}"))

    sql("update users set pode_editar_produtos=1 where id=4")
    v = Sessao("vendedor@demo.com", "vendedor123", unidade=1)
    check("vendedor COM a chave: tela de novo produto = 200", v.status("/app/produtos/create") == 200, v.status("/app/produtos/create"))
    html = v.get("/app/produtos/create")
    st, _, _ = v.req("/app/produtos", urllib.parse.urlencode({"_token": v.token(html), "codigo_interno": "DD99", "descricao": "CADASTRADO PELA VENDEDORA", "unidade_medida": "UN", "preco_venda": "33"}).encode())
    check("vendedor COM a chave: cadastra produto", st in (200, 302) and sql("select count(*) from produtos where empresa_id=1 and descricao='CADASTRADO PELA VENDEDORA'") == "1", st)
    st, html, _ = v.req(f"/app/produtos/{p1}/edit")
    st, _, _ = v.req(f"/app/produtos/{p1}", urllib.parse.urlencode({"_token": v.token(html), "_method": "PUT", "descricao": "ANEL EDITADO PELA VENDEDORA", "unidade_medida": "UN", "preco_venda": "12.50", "status": "ativo"}).encode())
    check("vendedor COM a chave: edita descrição e preço (não só a foto)", sql(f"select descricao, preco_venda from produtos where id={p1}") == "ANEL EDITADO PELA VENDEDORA\t12.50", sql(f"select descricao, preco_venda from produtos where id={p1}"))
    st, _, _ = v.req(f"/app/produtos/{p1}", urllib.parse.urlencode({"_token": v.token(html), "_method": "DELETE"}).encode())
    check("vendedor COM a chave: excluir continua 403", st == 403 and sql(f"select count(*) from produtos where id={p1} and deleted_at is null") == "1", st)
    check("vendedor COM a chave: outros módulos iguais (funcionários 403)", v.status("/app/funcionarios") == 403, v.status("/app/funcionarios"))
    html = v.get("/app/produtos")
    check("lista de produtos mostra o botão Editar para ela", f"/app/produtos/{p1}/edit" in html)

    sql("update empresas set vendedor_apenas_pdv=1 where id=1")
    v = Sessao("vendedor@demo.com", "vendedor123", unidade=1)
    check("empresa em modo 'só PDV': vendedor COM a chave ainda abre produtos", v.status("/app/produtos") == 200, v.status("/app/produtos"))
    sql("update users set pode_editar_produtos=0 where id=4")
    v = Sessao("vendedor@demo.com", "vendedor123", unidade=1)
    st, _, url = v.req("/app/produtos")
    check("empresa em modo 'só PDV': vendedor SEM a chave segue bloqueado (403 ou mandado ao PDV)", st == 403 or "/app/pdv" in url, (st, url))
    sql("update empresas set vendedor_apenas_pdv=0 where id=1")

    print("== 4. Chave no caixa e no cadastro do funcionário")
    sql("update users set pode_editar_produtos=1 where id=5")
    c = Sessao("caixa@demo.com", "caixa123", unidade=1)
    check("caixa COM a chave: novo produto = 200", c.status("/app/produtos/create") == 200, c.status("/app/produtos/create"))
    sql("update users set pode_editar_produtos=0 where id=5")

    d = Sessao("dono@demo.com", "dono123", unidade=1)
    html = d.get("/app/funcionarios/4/edit")
    check("form de editar funcionário tem o switch 'Pode cadastrar e editar produtos'", 'name="pode_editar_produtos"' in html and "Pode cadastrar e editar produtos" in html)
    campos = {"_token": d.token(html), "_method": "PUT", "name": "Vendedor Demo", "email": "vendedor@demo.com", "perfil": "vendedor", "status": "ativo", "unidades[]": "1", "pode_editar_produtos": "1"}
    st, _, _ = d.req("/app/funcionarios/4", urllib.parse.urlencode(campos, doseq=True).encode())
    check("dono liga a chave pela tela", st in (200, 302) and sql("select pode_editar_produtos from users where id=4") == "1", (st, sql("select pode_editar_produtos from users where id=4")))
    campos["pode_editar_produtos"] = "0"
    st, _, _ = d.req("/app/funcionarios/4", urllib.parse.urlencode(campos, doseq=True).encode())
    check("dono desliga a chave pela tela (switch desmarcado grava 0)", st in (200, 302) and sql("select pode_editar_produtos from users where id=4") == "0", (st, sql("select pode_editar_produtos from users where id=4")))
    html = d.get("/app/funcionarios/create")
    check("form de novo funcionário tem o switch", 'name="pode_editar_produtos"' in html)
    sql("delete from users where email='novo.vendedor@teste.local'")
    st, _, _ = d.req("/app/funcionarios", urllib.parse.urlencode({"_token": d.token(html), "name": "Novo Vendedor", "email": "novo.vendedor@teste.local", "password": "Senha@12345", "password_confirmation": "Senha@12345", "perfil": "vendedor", "unidades[]": "1", "pode_editar_produtos": "1"}, doseq=True).encode())
    check("novo funcionário nasce com a chave marcada", st in (200, 302) and sql("select pode_editar_produtos from users where email='novo.vendedor@teste.local'") == "1", (st, sql("select pode_editar_produtos from users where email='novo.vendedor@teste.local'")))
    sql("delete from unidade_user where user_id in (select id from users where email='novo.vendedor@teste.local')")
    sql("delete from users where email='novo.vendedor@teste.local'")

    check("migration: coluna users.pode_editar_produtos existe e nasce 0", sql("select column_default from information_schema.columns where table_schema='erp_teste' and table_name='users' and column_name='pode_editar_produtos'") == "0")

    sql("delete from produtos where empresa_id=1 and (codigo_interno like 'DD%' or descricao='CADASTRADO PELA VENDEDORA')")

    print("== 5. Lint")
    for f in ["app/Http/Controllers/App/PdvController.php", "app/Http/Controllers/App/SearchController.php", "app/Http/Middleware/CheckPermission.php",
              "app/Http/Controllers/App/ProdutoController.php", "app/Models/User.php", "app/Http/Controllers/App/FuncionarioController.php"]:
        r = subprocess.run(["docker", "exec", APP_CT, "php", "-l", f"/var/www/{f}"], capture_output=True, text=True)
        check(f"php -l {f}", "No syntax errors" in r.stdout, r.stdout + r.stderr)
    r = subprocess.run(["docker", "exec", APP_CT, "sh", "-c",
                        "cd /var/www && php artisan view:clear >/dev/null && php artisan view:cache >/dev/null && "
                        "for f in storage/framework/views/*.php; do php -l $f | grep -v 'No syntax' ; done; echo FIM"],
                       capture_output=True, text=True)
    check("todas as views compiladas passam no php -l (armadilha 63)", r.stdout.strip() == "FIM", r.stdout[-400:])

    print(f"\nTOTAL {OK} ✅ / {FAIL} ❌")
    sys.exit(1 if FAIL else 0)


if __name__ == "__main__":
    main()
