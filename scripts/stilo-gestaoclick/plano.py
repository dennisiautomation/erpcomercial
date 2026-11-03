import json
D="/home/ubuntu/stilo-gc-import/"
gc=json.load(open(D+"gc-produtos-20261103-0921.json")); erp={e["id"]:e for e in json.load(open(D+"erp-stilo-antes.json")) if not e["deleted_at"]}
m=json.load(open(D+"casamento.json"))["match"]
uni={}
for v in gc["porLoja"].values():
    for p in v["produtos"]: uni.setdefault(p["id"],p)
def val(p,t):
    for x in p.get("valores",[]):
        if x["tipo_id"]==t: return float(x["valor_venda"])
def precos(p):
    pix,car=val(p,"319751"),val(p,"319752")
    if not pix or not car: pix,car=val(p,"516689"),val(p,"667559")
    return round(pix or 0,2), round(car or 0,2)
LOJAS={"283450":9,"283447":13,"288052":14,"288046":10,"554774":15,"290819":16}
est={lid:{p["id"]:float(p["estoque"]) for p in gc["porLoja"][lid]["produtos"]} for lid in LOJAS}
plano={"atualizar":[],"novos":[]}
for gid,p in sorted(uni.items(), key=lambda x:x[1]["nome"]):
    pix,car=precos(p)
    item={"gc_id":gid,"nome":p["nome"].strip(),"codigo_barras":p["codigo_interno"],"pix":pix,"cartao":car,
          "custo":round(float(p.get("valor_custo") or 0),2),"ncm":((p.get("fiscal") or {}).get("ncm") or None),
          "estoque":{str(LOJAS[l]):int(est[l][gid]) for l in LOJAS if est[l].get(gid,0)>0}}
    if gid in m: item["erp_id"]=m[gid][0]; plano["atualizar"].append(item)
    else: plano["novos"].append(item)
json.dump(plano, open(D+"plano-importacao.json","w"), ensure_ascii=False)
t=lambda k: sum(sum(i["estoque"].values()) for i in plano[k])
print("atualizar", len(plano["atualizar"]), "novos", len(plano["novos"]), "peças", t("atualizar")+t("novos"))
for u in sorted(set(LOJAS.values())): print(" loja", u, sum(i["estoque"].get(str(u),0) for k in plano for i in plano[k]))
cb=[i["codigo_barras"] for k in plano for i in plano[k]]; print("cód barras únicos", len(cb)==len(set(cb)), "todos 13 díg", all(len(c)==13 and c.isdigit() for c in cb))
print("cód fora do padrão:", [c for c in cb if not (len(c)==13 and c.isdigit())][:10])
