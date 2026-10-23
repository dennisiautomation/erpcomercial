import sys
def chave(n): return ('3526101122233300014455001' + str(n).zfill(9) + '1').ljust(43, '0') + str(n % 10)
def item(n, cprod, ean, desc, ucom, q, vun, frete=0, desc_=0, ipi=0, st=0):
    vprod = round(q*vun, 2)
    x = f'<det nItem="{n}"><prod><cProd>{cprod}</cProd><cEAN>{ean}</cEAN><xProd>{desc}</xProd><NCM>61091000</NCM><CFOP>6102</CFOP><uCom>{ucom}</uCom><qCom>{q:.4f}</qCom><vUnCom>{vun:.10f}</vUnCom><vProd>{vprod:.2f}</vProd><cEANTrib>{ean}</cEANTrib><uTrib>{ucom}</uTrib><qTrib>{q:.4f}</qTrib><vUnTrib>{vun:.10f}</vUnTrib>'
    if frete: x += f'<vFrete>{frete:.2f}</vFrete>'
    if desc_: x += f'<vDesc>{desc_:.2f}</vDesc>'
    x += '<indTot>1</indTot></prod><imposto><ICMS>'
    x += f'<ICMS10><orig>0</orig><CST>10</CST><vICMSST>{st:.2f}</vICMSST></ICMS10>' if st else '<ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102>'
    x += '</ICMS>'
    if ipi: x += f'<IPI><cEnq>999</cEnq><IPITrib><CST>50</CST><vIPI>{ipi:.2f}</vIPI></IPITrib></IPI>'
    x += '</imposto></det>'
    return x, vprod
def nota(n, emit_cnpj, emit_nome, dest_cnpj, itens, dups, mod='55', proc=True):
    dets = ''.join(i[0] for i in itens); vprod = sum(i[1] for i in itens)
    ch = chave(n)
    inf = (f'<infNFe versao="4.00" Id="NFe{ch}"><ide><cUF>35</cUF><mod>{mod}</mod><serie>1</serie><nNF>{n}</nNF><dhEmi>2026-10-20T10:00:00-03:00</dhEmi><tpNF>1</tpNF></ide>'
           f'<emit><CNPJ>{emit_cnpj}</CNPJ><xNome>{emit_nome}</xNome><xFant>Fantasia {n}</xFant><enderEmit><xLgr>Rua A</xLgr><nro>10</nro><xBairro>Centro</xBairro><xMun>Sao Paulo</xMun><UF>SP</UF><CEP>01001000</CEP><fone>1133334444</fone></enderEmit><IE>1</IE><CRT>1</CRT></emit>'
           f'<dest><CNPJ>{dest_cnpj}</CNPJ><xNome>Demo</xNome></dest>{dets}'
           f'<total><ICMSTot><vProd>{vprod:.2f}</vProd><vNF>{TOTAL[n]:.2f}</vNF></ICMSTot></total>')
    if dups:
        inf += '<cobr>' + ''.join(f'<dup><nDup>{i+1:03d}</nDup><dVenc>{d}</dVenc><vDup>{v:.2f}</vDup></dup>' for i,(d,v) in enumerate(dups)) + '</cobr>'
    inf += '</infNFe>'
    nfe = f'<NFe xmlns="http://www.portalfiscal.inf.br/nfe">{inf}</NFe>'
    if proc:
        return f'<?xml version="1.0" encoding="UTF-8"?><nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">{nfe}<protNFe versao="4.00"><infProt><chNFe>{ch}</chNFe></infProt></protNFe></nfeProc>'
    return '<?xml version="1.0" encoding="UTF-8"?>' + nfe
out = sys.argv[1]
TOTAL = {}
# Nota 1001 — fornecedor já cadastrado (sem máscara), 3 itens
i1 = item(1, 'MX3-01', '7891234567895', 'MOUSE LOGITECH MX MASTER 3 GRAFITE', 'UN', 10, 300.00, frete=20.00, ipi=30.00)   # custo 3050 / 10 = 305,00
i2 = item(2, 'CAM-CX', 'SEM GTIN', 'CAMISETA BASICA PRETA M CX C/12', 'CX', 2, 240.00, desc_=30.00, st=6.00)             # custo 480-30+6=456 / (2*12) = 19,00
i3 = item(3, 'BRINDE', 'SEM GTIN', 'CHAVEIRO BRINDE', 'UN', 5, 1.00)                                                        # ignorado
TOTAL[1001] = 3050 + 456 + 5
open(f'{out}/n1001.xml','w').write(nota(1001, '11222333000144', 'Distribuidora Tech Ltda', '12345678000190', [i1,i2,i3], [('2026-11-20', 1755.50), ('2026-12-20', 1755.50)]))
# Nota 1002 — mesmo fornecedor, CAM-CX de novo (deve casar sozinho com fator 12), sem duplicatas, NFe solta (sem nfeProc)
j1 = item(1, 'CAM-CX', 'SEM GTIN', 'CAMISETA BASICA PRETA M CX C/12', 'CX', 1, 264.00)   # 264/12 = 22,00
TOTAL[1002] = 264
open(f'{out}/n1002.xml','w').write(nota(1002, '11222333000144', 'Distribuidora Tech Ltda', '12345678000190', [j1], [], proc=False))
# Nota 1003 — fornecedor NOVO e destinatário de OUTRO CNPJ (aviso)
k1 = item(1, 'X1', 'SEM GTIN', 'PRODUTO FORNECEDOR NOVO', 'UND', 3, 10.00)
TOTAL[1003] = 30
open(f'{out}/n1003.xml','w').write(nota(1003, '99888777000166', 'Fornecedor Novo SA', '55444333000122', [k1], []))
# Cupom NFC-e (mod 65) — recusado
TOTAL[1004] = 30
open(f'{out}/n1004-nfce.xml','w').write(nota(1004, '99888777000166', 'Fornecedor Novo SA', '12345678000190', [k1], [], mod='65'))
open(f'{out}/quebrado.xml','w').write('<nfeProc><NFe><infNFe Id="NFe123">')
open(f'{out}/nada.xml','w').write('isto nao e xml')
print('ok')
# Nota 1005 — para o gerente (sem permissão de financeiro)
TOTAL[1005] = 30
open(f'{out}/n1005.xml','w').write(nota(1005, '99888777000166', 'Fornecedor Novo SA', '12345678000190', [item(1, 'X1', 'SEM GTIN', 'PRODUTO FORNECEDOR NOVO', 'UND', 3, 10.00)], [('2026-11-30', 30.00)]))
# Nota 1006 — fica em rascunho (isolamento e descarte)
TOTAL[1006] = 50
open(f'{out}/n1006.xml','w').write(nota(1006, '11222333000144', 'Distribuidora Tech Ltda', '12345678000190', [item(1, 'Z9', 'SEM GTIN', 'ITEM RASCUNHO', 'UN', 5, 10.00)], []))
