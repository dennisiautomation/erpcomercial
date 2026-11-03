import json, sys
from openpyxl import Workbook
from openpyxl.styles import Font, PatternFill, Alignment

D = "/home/ubuntu/stilo-gc-import/"
gc = json.load(open(D + "gc-produtos-20261103-0921.json"))
erp = {e["id"]: e for e in json.load(open(D + "erp-stilo-antes.json")) if not e["deleted_at"]}
m = json.load(open(D + "casamento.json"))["match"]
uni = {}
for v in gc["porLoja"].values():
    for p in v["produtos"]:
        uni.setdefault(p["id"], p)


def val(p, t):
    for x in p.get("valores", []):
        if x["tipo_id"] == t:
            return float(x["valor_venda"])


def precos(p):
    pix, car = val(p, "319751"), val(p, "319752")
    tab = "principal"
    if not pix or not car:
        pix, car, tab = val(p, "516689"), val(p, "667559"), "bazar/outlet"
    return pix or 0.0, car or 0.0, tab


ORD = [("283450", "01 Matriz JS", 9), ("283447", "02 JS FILIAL", 13), ("288052", "03 JS FILIAL", 14),
       ("288046", "04 PRIME MATRIZ", 10), ("554774", "05 PRIME FILIAL", 15), ("290819", "06 PRIME FILIAL", 16),
       ("563234", "STILO VINTE OUTLET JS", 18), ("278246", "Loja barroso JS (inativa)", 17)]
JS, PRIME = "23.237.062/0001-17", "25.105.231/0001-90"
cnpj = {9: JS, 13: JS, 14: JS, 18: JS, 17: JS, 10: PRIME, 15: PRIME, 16: PRIME}
est = {lid: {p["id"]: float(p["estoque"]) for p in v["produtos"]} for lid, v in gc["porLoja"].items()}

wb = Workbook()
H = Font(bold=True, color="FFFFFF")
F = PatternFill("solid", fgColor="1F4E78")
A = PatternFill("solid", fgColor="FFF2CC")


def letra(i):
    s = ""
    i += 1
    while i:
        i, r = divmod(i - 1, 26)
        s = chr(65 + r) + s
    return s


def sheet(ws, cab, linhas, larg):
    ws.append(cab)
    for c in ws[1]:
        c.font, c.fill = H, F
        c.alignment = Alignment(wrap_text=True, vertical="center")
    for l in linhas:
        ws.append(l)
    for i, w in enumerate(larg):
        ws.column_dimensions[letra(i)].width = w
    ws.freeze_panes = "A2"


ws = wb.active
ws.title = "Lojas"
lin = []
for lid, en, eid in ORD:
    v = gc["porLoja"][lid]["produtos"]
    pos = [float(p["estoque"]) for p in v if float(p["estoque"]) > 0]
    vai = "SIM" if eid not in (17, 18) else "NÃO (fica zerada) — confirmar"
    lin.append([gc["porLoja"][lid]["nome"], lid, len(pos), int(sum(pos)),
                sum(1 for p in v if float(p["estoque"]) < 0), en, cnpj[eid], 0, vai])
sheet(ws, ["Loja no Gestão Click", "Código GC", "Produtos com saldo", "Peças (saldo > 0)",
           "Produtos com saldo negativo", "Loja no ERP", "CNPJ da loja no ERP", "Peças hoje no ERP",
           "Recebe o estoque?"], lin, [24, 11, 12, 12, 14, 26, 20, 10, 28])
for r in ws.iter_rows(min_row=2, max_row=9):
    if r[0].value.startswith("03") or "NÃO" in r[8].value:
        for c in r:
            c.fill = A
ws.append([])
ws.append(["Atenção: no GC a loja 03 se chama '03 PRIME FILIAL'; no ERP é '03 JS FILIAL' com CNPJ da JS. Confirmar se é a mesma loja."])

cols = [n for _, n, _ in ORD[:6]]
ws = wb.create_sheet("Produtos")
lin = []
for gid, p in sorted(uni.items(), key=lambda x: x[1]["nome"]):
    pix, car, tab = precos(p)
    e = erp.get(m[gid][0]) if gid in m else None
    ep = float(e["preco_venda"]) if e else None
    ec = float(e["precos"].get("credito") or ep) if e else None
    sit = "já existe no ERP" if e else "NOVO (vai ser cadastrado)"
    muda = []
    if e and abs(pix - ep) > 0.004:
        muda.append("preço PIX")
    if e and abs(car - ec) > 0.004:
        muda.append("preço cartão")
    if e and (p["fiscal"] or {}).get("ncm") and p["fiscal"]["ncm"] != e["ncm"]:
        muda.append("NCM")
    lin.append([p["nome"], p["codigo_interno"], sit, e["descricao"] if e else "",
                e["codigo_interno"] if e else "(automático)", pix, car, tab, ep, ec,
                (p["fiscal"] or {}).get("ncm") or "", e["ncm"] if e else "",
                ", ".join(muda) or ("—" if e else "novo")]
               + [max(0, int(est[lid].get(gid, 0))) for lid, _, _ in ORD[:6]]
               + [int(est["563234"].get(gid, 0)), int(est["278246"].get(gid, 0))])
sheet(ws, ["Produto no Gestão Click", "Código de barras (GC)", "Situação", "Produto no ERP",
           "Código interno ERP", "GC Dinheiro/PIX", "GC Cartão", "Tabela de preço usada",
           "ERP Dinheiro/PIX hoje", "ERP Cartão hoje", "NCM no GC", "NCM no ERP", "O que muda"]
      + [f"Estoque {c}" for c in cols] + ["Outlet (GC, não entra)", "Barroso (GC, não entra)"],
      lin, [40, 16, 22, 40, 12, 10, 10, 12, 10, 10, 11, 11, 22] + [9] * 8)

ws = wb.create_sheet("Só no ERP")
casados = {v[0] for v in m.values()}
sheet(ws, ["Produto no ERP", "Código interno", "Dinheiro/PIX", "Cartão", "O que acontece"],
      [[e["descricao"], e["codigo_interno"], float(e["preco_venda"]), float(e["precos"].get("credito") or 0),
        "não existe no GC: fica cadastrado, estoque 0"]
       for e in sorted(erp.values(), key=lambda e: e["descricao"]) if e["id"] not in casados],
      [42, 12, 12, 10, 45])

ws = wb.create_sheet("Conferir com o cliente")
lin = []
for p in sorted(uni.values(), key=lambda p: p["nome"]):
    pix, car, tab = precos(p)
    if pix == 0 or car == 0:
        lin.append([p["nome"], p["codigo_interno"], pix, car, "preço zero no GC"])
    elif car > 1.5 * pix:
        lin.append([p["nome"], p["codigo_interno"], pix, car, "cartão mais que 1,5× o PIX (promoção no PIX?)"])
    if not (p.get("fiscal") or {}).get("ncm"):
        lin.append([p["nome"], p["codigo_interno"], pix, car, "sem NCM no GC (mantém o do ERP)"])
sheet(ws, ["Produto no GC", "Código de barras", "Dinheiro/PIX", "Cartão", "Por quê"], lin, [44, 16, 12, 10, 48])

out = D + "comparacao-stilo-gc-x-erp-03nov.xlsx"
wb.save(out)
print(out, len(uni), "produtos;", len(lin), "para conferir")
