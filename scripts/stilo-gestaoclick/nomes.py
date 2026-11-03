import json,re,collections
d=json.load(open("/home/ubuntu/stilo-gc-import/nomes-antes.json"))
VAL=r"(?:R\s*\$\s*\d[\d.,]*|\d[\d.,]*\s*\$)"
def limpa(s):
    t=re.sub(rf"\s*(?:-\s*)?{VAL}(?:\s+por\s+{VAL})?", " ", s, flags=re.I)
    t=re.sub(r"\s+"," ",t).strip(); t=re.sub(r"\s*[-–]+$","",t).strip()
    return t
mud=[(x["id"],x["descricao"],limpa(x["descricao"])) for x in d if limpa(x["descricao"])!=x["descricao"]]
print("produtos", len(d), "mudam", len(mud), "ficam iguais", len(d)-len(mud))
ainda=[n for _,_,n in mud if re.search(r"\$|\bR\b", n)] + [x["descricao"] for x in d if limpa(x["descricao"])==x["descricao"] and "$" in x["descricao"]]
print("ainda com $:", ainda[:20])
vazio=[(a,b) for _,a,b in mud if not b]; print("ficariam vazios:", vazio)
fim=collections.Counter(n.lower() for n in ([limpa(x["descricao"]) for x in d]))
dup={k:v for k,v in fim.items() if v>1}; print("nomes repetidos depois:", len(dup), "produtos envolvidos", sum(dup.values()))
print(list(dup.items())[:15])
import random; random.seed(1)
for a in random.sample(mud,12): print(" ", a[1], "→", a[2])
esp=[m for m in mud if re.search(r"por|promo|sale|\d\s*\$|,", m[1], re.I)]
for a in esp[:15]: print(" *", a[1], "→", a[2])
json.dump([{"id":i,"antes":a,"depois":b} for i,a,b in mud], open("/home/ubuntu/stilo-gc-import/renomear.json","w"), ensure_ascii=False)
