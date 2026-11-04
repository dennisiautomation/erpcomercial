#!/usr/bin/env python3
"""Validação ✅/❌ — (1) relatório de vendas do GERENTE por loja vinculada e (2) editor de etiqueta
de formato fixo partindo do que o fixo imprime (04/11/2026, caso STILO VINTE).

(1) O UnidadeScope prendia o gerente à loja da sessão por cima do filtro "Loja" do relatório:
    escolher outra loja vinculada ou "Todas" mostrava sempre a loja atual.
(2) O editor de um formato FIXO (Tag 35×60) abria com um desenho por fórmula, diferente do que a
    impressora tirava (logo, 3 linhas, Cartão/PIX, 13pt); e o layout livre imprimia a razão social
    onde o editor mostra o nome fantasia.

Ambiente DESCARTÁVEL (nunca produção):
    BASE=http://127.0.0.1:8297 DB_CT=cs-mysql APP_CT=cs-app python3 scripts/validar-relatorio-gerente-etiqueta-04nov.py
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


def tinker(php):
    r = subprocess.run(["docker", "exec", APP_CT, "php", "/var/www/artisan", "tinker", "--execute", php],
                       capture_output=True, text=True)
    return (r.stdout + r.stderr).strip()


class Sessao:
    def __init__(self, email, senha, unidade=None):
        self.cj = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cj))
        html = self.get("/login")
        self.post_form("/login", {"_token": self.token(html), "email": email, "password": senha})
        if unidade:
            html = self.get("/selecionar-unidade")
            self.post_form("/selecionar-unidade", {"_token": self.token(html), "unidade_id": unidade})

    def req(self, path, data=None, headers=None):
        r = urllib.request.Request(BASE + path, data=data, headers=headers or {})
        try:
            with self.op.open(r) as resp:
                return resp.status, resp.read().decode("utf-8", "replace"), resp.geturl()
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8", "replace"), ""

    def get(self, path):
        return self.req(path)[1]

    @staticmethod
    def token(html):
        m = re.search(r'name="_token" value="([^"]+)"', html) or re.search(r'name="csrf-token" content="([^"]+)"', html)
        return m.group(1) if m else ""

    def post_form(self, path, campos):
        return self.req(path, urllib.parse.urlencode(campos, doseq=True).encode())


PERIODO = "data_inicio=2027-03-01&data_fim=2027-03-31"


def relatorio(s, loja=None):
    q = f"/app/relatorios/vendas?{PERIODO}" + (f"&loja={loja}" if loja else "")
    st, html, url = s.req(q)
    # Faturamento = primeiro "R$ x" depois do rótulo; basta saber quais valores aparecem
    valores = set(re.findall(r"R\$ (111,00|222,00|333,00)", html))
    return st, valores, html


def preparar_relatorio():
    sql("delete from vendas where numero in (990001, 990002)")
    sql("delete from unidades where empresa_id=1 and nome='Loja Norte (teste)'")
    sql("insert into unidades (empresa_id, nome, cnpj, cep, logradouro, numero, bairro, cidade, uf, telefone, status, created_at, updated_at) "
        "values (1, 'Loja Norte (teste)', '', '', '', '', '', '', 'SP', '', 'ativa', now(), now())")
    norte = int(sql("select id from unidades where empresa_id=1 and nome='Loja Norte (teste)'"))
    sql("delete from unidade_user where user_id in (3, 4)")
    sql(f"insert into unidade_user (user_id, unidade_id, created_at, updated_at) values (3, 1, now(), now()), (3, {norte}, now(), now()), (4, 1, now(), now())")
    sql("insert into vendas (empresa_id, unidade_id, vendedor_id, numero, subtotal, total, status, tipo, forma_pagamento, created_at, updated_at) values "
        "(1, 1, 3, 990001, 111, 111, 'concluida', 'pdv', 'dinheiro', '2027-03-15 10:00:00', '2027-03-15 10:00:00'), "
        f"(1, {norte}, 3, 990002, 222, 222, 'concluida', 'pdv', 'dinheiro', '2027-03-15 11:00:00', '2027-03-15 11:00:00')")
    return norte


def limpar_relatorio(norte):
    sql("delete from vendas where numero in (990001, 990002)")
    sql(f"delete from unidade_user where unidade_id={norte}")
    sql(f"delete from unidades where id={norte}")


def main():
    print("== 1. Relatório de vendas — gerente com 2 lojas vinculadas (sessão na loja 1)")
    norte = preparar_relatorio()
    g = Sessao("gerente@demo.com", "gerente123", unidade=1)
    st, v, html = relatorio(g)
    check("gerente: sem filtro = loja da sessão (111)", st == 200 and v == {"111,00"}, (st, v))
    check("gerente: o filtro de loja lista a loja vinculada", "Loja Norte (teste)" in html)
    st, v, _ = relatorio(g, "todas")
    check("gerente: 'Todas as lojas' soma as vinculadas (333) — o caso da STILO", st == 200 and "333,00" in v, (st, v))
    st, v, _ = relatorio(g, norte)
    check("gerente: outra loja vinculada mostra só ela (222)", st == 200 and v == {"222,00"}, (st, v))
    st, v, _ = relatorio(g, 1)
    check("gerente: loja 1 explícita (111)", st == 200 and v == {"111,00"}, (st, v))

    print("== 2. Gerente SEM vínculo com a outra loja")
    sql(f"delete from unidade_user where user_id=3 and unidade_id={norte}")
    g2 = Sessao("gerente@demo.com", "gerente123", unidade=1)
    st, v, _ = relatorio(g2, "todas")
    check("gerente só vinculado à loja 1: 'Todas' = 111", st == 200 and v == {"111,00"}, (st, v))
    st, v, _ = relatorio(g2, norte)
    check("gerente só vinculado à loja 1: loja não vinculada cai na da sessão (111)", st == 200 and v == {"111,00"}, (st, v))

    print("== 3. Vendedor continua preso à loja da sessão; dono vê tudo")
    vd = Sessao("vendedor@demo.com", "vendedor123", unidade=1)
    st, v, html = relatorio(vd, "todas")
    check("vendedor: 'Todas' não existe para ele = 111", st == 200 and v == {"111,00"}, (st, v))
    check("vendedor: sem seletor de loja", "Loja Norte (teste)" not in html)
    st, v, _ = relatorio(vd, norte)
    check("vendedor: loja forjada na URL = 111", st == 200 and v == {"111,00"}, (st, v))
    d = Sessao("dono@demo.com", "dono123", unidade=1)
    st, v, _ = relatorio(d, "todas")
    check("dono: 'Todas' = 333 (como antes)", st == 200 and "333,00" in v, (st, v))
    st, v, _ = relatorio(d)
    check("dono: sem filtro = loja da sessão (111, como antes)", st == 200 and v == {"111,00"}, (st, v))
    limpar_relatorio(norte)

    print("== 4. Etiqueta: editor do formato fixo parte do que ele imprime")
    sql("update empresas set logo='logos/empresa-1.png' where id=1")
    sql("delete from produto_precos where produto_id=3")
    sql("insert into produto_precos (empresa_id, produto_id, modalidade, valor, created_at, updated_at) values (1, 3, 'credito', 50, now(), now())")
    sql("update produtos set codigo_barras='2000000000011', descricao='Saia Chita', preco_venda=45 where id=3")
    sql("delete from etiqueta_formatos where empresa_id=1 and formato_base in ('termica-tag-35x60', 'termica-36x20-2col')")

    def inicial(chave, w, h):
        out = tinker(
            f'$f = new App\\Models\\EtiquetaFormato(["empresa_id" => 1, "formato_base" => "{chave}", "nome" => "x", '
            f'"largura_mm" => {w}, "altura_mm" => {h}, "colunas" => 1, "espaco_mm" => 0]); '
            'echo json_encode($f->layoutInicial());')
        try:
            return json.loads(out[out.index("["):])
        except ValueError:
            print("tinker:", out[-300:])
            return []

    tag = inicial("termica-tag-35x60", 35, 60)
    tipos = [e["tipo"] for e in tag]
    check("Tag 35×60 com logo e preço de cartão: logo → descrição → barras → números → Cartão → PIX → código",
          tipos == ["empresa_logo", "descricao", "codigo_barras", "digitos_barras", "preco_cartao", "preco_pix", "codigo_interno"], tipos)
    por = {e["tipo"]: e for e in tag}
    check("Tag: começa depois do furo (y = 7 mm) e logo com 7 mm de altura",
          por.get("empresa_logo", {}).get("y") == 7 and por["empresa_logo"]["h"] == 7, por.get("empresa_logo"))
    check("Tag: descrição 7pt negrito em 3 linhas, números 9pt Courier, código 5pt",
          por["descricao"]["tamanho"] == 7 and por["descricao"].get("negrito") and por["descricao"]["h"] > 8
          and por["digitos_barras"]["tamanho"] == 9 and por["digitos_barras"]["fonte"] == "Courier New"
          and por["codigo_interno"]["tamanho"] == 5, por)
    check("Tag: barras com 10 mm", por["codigo_barras"]["h"] == 10, por["codigo_barras"])
    check("Tag: Cartão/PIX no corpo do preço limitado à largura (≤ 13pt, cabe em 31 mm)",
          0 < por["preco_cartao"]["tamanho"] <= 13 and por["preco_cartao"]["tamanho"] * 3.3 <= 31.5, por["preco_cartao"])
    check("Tag: tudo dentro da etiqueta (35×60)",
          all(0 <= e["x"] and e["x"] + e["w"] <= 35.01 and 0 <= e["y"] and e["y"] + e["h"] <= 60.01 for e in tag), tag)

    sql("update empresas set logo=NULL where id=1")
    sql("delete from produto_precos where produto_id=3")
    tag2 = inicial("termica-tag-35x60", 35, 60)
    tipos2 = [e["tipo"] for e in tag2]
    check("Tag sem logo e sem preço de cartão: nome da loja 6pt e preço único 13pt",
          tipos2 == ["empresa_nome", "descricao", "codigo_barras", "digitos_barras", "preco", "codigo_interno"]
          and {e["tipo"]: e for e in tag2}["empresa_nome"]["tamanho"] == 6
          and {e["tipo"]: e for e in tag2}["preco"]["tamanho"] == 13, tipos2)

    argox = inicial("termica-36x20-2col", 36, 20)
    check("36×20 (Argox): ordem da Hiper — descrição → código → barras → números → preço, tudo em 20 mm",
          [e["tipo"] for e in argox] == ["descricao", "codigo_interno", "codigo_barras", "digitos_barras", "preco"]
          and all(e["y"] + e["h"] <= 20.01 for e in argox), argox)

    a4 = inicial("2x5", 98.5, 55)
    pa4 = {e["tipo"]: e for e in a4}
    # A pilha do 2×5 (8 + 2×9 + 28 mm + 16 + 7 pt) passa dos 47 mm úteis, como no papel:
    # entra comprimida na proporção, com o preço maior que a descrição e o código menor.
    check("A4 2×5: ordem do fixo, comprimido na proporção (preço > descrição > código) e dentro dos 55 mm",
          [e["tipo"] for e in a4] == ["empresa_nome", "descricao", "codigo_barras", "digitos_barras", "preco", "codigo_interno"]
          and pa4["preco"]["tamanho"] > pa4["descricao"]["tamanho"] > pa4["codigo_interno"]["tamanho"]
          and abs(pa4["preco"]["tamanho"] / pa4["descricao"]["tamanho"] - 16 / 9) < 0.1
          and all(e["y"] + e["h"] <= 55.01 for e in a4), a4)

    proprio = tinker('$f = new App\\Models\\EtiquetaFormato(["empresa_id" => 1, "nome" => "meu", "largura_mm" => 35, '
                     '"altura_mm" => 60, "colunas" => 1, "espaco_mm" => 0, "mostrar_empresa" => true]); '
                     'echo json_encode(array_column($f->layoutInicial(), "tipo"));')
    check("formato PRÓPRIO continua no arranjo por fórmula (não mudou)",
          proprio.endswith('["empresa_nome","descricao","codigo_barras","digitos_barras","preco","codigo_interno"]'), proprio[-200:])

    print("== 5. Etiqueta: telas")
    sql("update empresas set logo='logos/empresa-1.png' where id=1")
    sql("insert into produto_precos (empresa_id, produto_id, modalidade, valor, created_at, updated_at) values (1, 3, 'credito', 50, now(), now())")
    d = Sessao("dono@demo.com", "dono123", unidade=1)
    st, html, url = d.req("/app/etiquetas/layout-fixo/termica-tag-35x60")
    check("lápis do fixo abre o editor (200)", st == 200 and "/editor" in url, (st, url))
    m = re.search(r"let elementos = (\[.*?\]);", html)
    tipos_tela = [e["tipo"] for e in json.loads(m.group(1))] if m else []
    check("editor da Tag nasce com logo, Cartão e PIX (o que a impressora tira)",
          "empresa_logo" in tipos_tela and "preco_cartao" in tipos_tela and "preco_pix" in tipos_tela, tipos_tela)
    check("cabeçalho do editor mostra a medida do fixo (3,5 × 6,0 cm)", "3,5 × 6,0 cm" in html)

    html_idx = d.get("/app/etiquetas")
    tok = d.token(html_idx)
    st, html_auto, _ = d.req("/app/etiquetas/gerar", urllib.parse.urlencode({"_token": tok, "formato": "termica-tag-35x60", "produtos[3]": 3}).encode())
    check("impressão AUTOMÁTICA do fixo (sem layout salvo) continua igual: 3 colunas, logo, razão social",
          st == 200 and html_auto.count('class="etiqueta ') == 3 and "empresa-logo" in html_auto
          and "formato-termica-tag-35x60" in html_auto, (st, html_auto.count('class="etiqueta ')))

    fid = int(sql("select id from etiqueta_formatos where empresa_id=1 and formato_base='termica-tag-35x60'"))
    layout = json.dumps({"versao": 1, "elementos": [
        {"tipo": "empresa_nome", "x": 1, "y": 1, "w": 33, "h": 4, "tamanho": 7, "negrito": True, "alinhamento": "center", "fonte": "Arial"},
        {"tipo": "codigo_barras", "x": 0, "y": 10, "w": 35, "h": 12}]})
    html_ed = d.get(f"/app/etiquetas/formatos/{fid}/editor")
    st, _, _ = d.req(f"/app/etiquetas/formatos/{fid}/layout", urllib.parse.urlencode({"_token": d.token(html_ed), "_method": "PUT", "layout": layout}).encode())
    check("salvar layout livre", st in (200, 302) and sql(f"select json_length(layout_json,'$.elementos') from etiqueta_formatos where id={fid}") == "2", st)
    st, html_livre, _ = d.req("/app/etiquetas/gerar", urllib.parse.urlencode({"_token": tok, "formato": "termica-tag-35x60", "produtos[3]": 3}).encode())
    fantasia = sql("select coalesce(nome_fantasia, '') from empresas where id=1")
    razao = sql("select razao_social from empresas where id=1")
    check(f"impressão com layout livre usa o NOME FANTASIA ('{fantasia}') como o editor, não a razão social",
          st == 200 and html_livre.count(f">{fantasia}<") == 3 and razao not in html_livre, (st, html_livre.count(f">{fantasia}<")))
    sql(f"delete from etiqueta_formatos where id={fid}")
    sql("delete from produto_precos where produto_id=3")

    print("== 6. Lint")
    for f in ["app/Http/Controllers/App/RelatorioController.php", "app/Models/EtiquetaFormato.php"]:
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
