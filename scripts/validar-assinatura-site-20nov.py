#!/usr/bin/env python3
"""
Assinatura pelo sistema (20/11/2026) — DE PONTA A PONTA entre o ERP e o caixa.ia365, em ambiente DESCARTÁVEL.

  ERP    : container as-app (branch assinatura-site-20nov), banco erp_assinatura no as-mysql
  caixa  : `next dev` da branch feat/assinatura-erp-20nov em 10.213.80.1:3999, banco caixa_assinatura
  banco  : NÃO existe aqui (Inter sem configuração) — o PIX é plantado no banco do caixa e a confirmação
           do banco é simulada por scripts/sim-banco-pagou.ts (o mesmo caminho do webhook)
  cartão : InfinitePay de mentira, servida por este roteiro em 10.213.80.1:3998

Recusa rodar se o ERP não for o as-app com o banco erp_assinatura. Nada daqui fala com produção.
Uso: python3 scripts/validar-assinatura-site-20nov.py
"""
import json, subprocess, sys, threading, time, urllib.request
from http.server import BaseHTTPRequestHandler, HTTPServer

CAIXA_DIR = '/home/ubuntu/apps/caixa-assinatura-erp'
CAIXA_ENV = {'DATABASE_URL': 'mysql://erp:erp@10.213.80.10:3306/caixa_assinatura', 'ERP_COMERCIAL_TOKEN': 'teste-segredo-caixa-erp'}
oks = falhas = 0


def ok(nome, real, esperado=True):
    global oks, falhas
    if real == esperado:
        oks += 1; print(f'  ✅ {nome}')
    else:
        falhas += 1; print(f'  ❌ {nome}\n     esperado: {esperado!r}\n     veio:     {real!r}')


def sh(cmd, **kw):
    return subprocess.run(cmd, capture_output=True, text=True, **kw)


def sql(banco, q):
    r = sh(['docker', 'exec', 'as-mysql', 'mysql', '--default-character-set=utf8mb4', '-uerp', '-perp', '-N', '-B', banco, '-e', q])
    return [l.split('\t') for l in r.stdout.strip().split('\n') if l]


def php(codigo):
    """Roda PHP dentro do ERP de teste e devolve o JSON da última linha."""
    r = sh(['docker', 'exec', '-u', 'www-data', '-e', 'HOME=/tmp', 'as-app', 'php', 'artisan', 'tinker', '--execute', codigo])
    linhas = [l for l in r.stdout.strip().split('\n') if l.startswith('{') or l.startswith('[')]
    if not linhas:
        raise RuntimeError('ERP não respondeu JSON:\n' + r.stdout[-800:] + r.stderr[-400:])
    return json.loads(linhas[-1])


# ── InfinitePay de mentira ──
ip = {'pago': False, 'pedidos': []}


class FakeIP(BaseHTTPRequestHandler):
    def log_message(self, *a): pass

    def do_POST(self):
        d = json.loads(self.rfile.read(int(self.headers.get('Content-Length', 0)) or 0) or b'{}')
        ip['pedidos'].append((self.path, d))
        if self.path == '/links':
            corpo = {'url': 'https://checkout.infinitepay.io/teste/' + d['order_nsu']}
        else:
            corpo = {'success': True, 'paid': ip['pago'], 'amount': 59000, 'paid_amount': 59000 if ip['pago'] else 0}
        b = json.dumps(corpo).encode()
        self.send_response(200); self.send_header('Content-Type', 'application/json'); self.send_header('Content-Length', str(len(b))); self.end_headers(); self.wfile.write(b)


def main():
    env = sh(['docker', 'exec', 'as-app', 'printenv', 'DB_DATABASE']).stdout.strip()
    if env != 'erp_assinatura':
        print('⛔ só roda contra o ERP de teste as-app (banco erp_assinatura)'); sys.exit(2)
    erp_ip = sh(['docker', 'inspect', 'as-app', '--format', '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}']).stdout.strip()
    CAIXA_ENV['ERP_COMERCIAL_URL'] = f'http://{erp_ip}'

    srv = HTTPServer(('10.213.80.1', 3998), FakeIP)
    threading.Thread(target=srv.serve_forever, daemon=True).start()

    # ── zera os dois lados ──
    for t in ('InterCobranca', 'Faturamento', 'Revenue', 'Client'):
        sql('caixa_assinatura', f'delete from {t}')
    php(r'''
        \DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['plataforma_faturas','notificacoes','users','unidades','empresas','planos','plataforma_configuracoes'] as $t) { \DB::table($t)->delete(); }
        \DB::statement('SET FOREIGN_KEY_CHECKS=1'); \Cache::flush();
        $p = \App\Models\Plano::create(['nome'=>'Basico','slug'=>'basico','preco_mensal'=>590,'preco_anual'=>5600,'max_unidades'=>1,'max_usuarios'=>5,'max_produtos'=>100,'max_notas_mes'=>100,'dias_trial'=>14,'ativo'=>true,'ordem'=>1]);
        foreach ([['11222333000181','Loja Pix'],['22333444000181','Loja Cartao']] as [$cnpj,$nome]) {
            $e = new \App\Models\Empresa(['cnpj'=>$cnpj,'razao_social'=>$nome.' LTDA','nome_fantasia'=>$nome,'regime_tributario'=>'simples_nacional','email'=>'dono@'.$cnpj.'.com','telefone'=>'(86) 99999-0000','cep'=>'64000-000','logradouro'=>'Rua A','numero'=>'10','bairro'=>'Centro','cidade'=>'Teresina','uf'=>'PI','status'=>'ativo','plano'=>'basico','plano_id'=>$p->id,'em_trial'=>true,'trial_inicio'=>now(),'trial_fim'=>now()->addDays(14)]);
            $e->origem_cadastro = 'site'; $e->save();
            $u = new \App\Models\User(['name'=>'Dono '.$nome,'email'=>'dono@'.$cnpj.'.com','empresa_id'=>$e->id,'perfil'=>'dono','status'=>'ativo']); $u->password = bcrypt('x'); $u->save();
        }
        \App\Services\Plataforma\AssinaturaSite::definirTolerancia(4);
        \App\Services\Plataforma\AssinaturaSite::definirHandle('ia365teste');
        echo json_encode(['ok'=>true]);
    ''')
    e1, e2 = [int(r[0]) for r in sql('erp_assinatura', 'select id from empresas order by id')]
    assinar = lambda eid, forma: php(rf'''$e=\App\Models\Empresa::withoutGlobalScopes()->find({eid}); $r=\App\Services\Plataforma\AssinaturaSite::assinar($e,'{forma}'); echo json_encode(['ok'=>$r['ok'],'erro'=>$r['erro'],'detalhe'=>$r['detalhe']??null,'fatura'=>$r['fatura']?->only(['id','caixa_ref','status','valor','pix_copia_cola','link','origem'])]);''')
    emp = lambda eid: php(rf'''$e=\App\Models\Empresa::withoutGlobalScopes()->find({eid}); echo json_encode(['forma'=>$e->assinatura_forma,'solicitada'=>(bool)$e->assinatura_solicitada_em,'ativada'=>(bool)$e->assinatura_ativada_em,'trial'=>(bool)$e->em_trial,'bloqueio'=>(bool)$e->cobranca_bloqueio_automatico,'tol'=>(int)$e->cobranca_tolerancia_dias,'cli'=>(int)$e->caixa_cliente_id,'ativa'=>$e->isAssinaturaAtiva()]);''')

    print('\nAssinar por PIX com o banco do caixa sem configuração (o erro do caixa chega ao ERP)')
    r = assinar(e1, 'pix')
    ok('o ERP recebe a recusa do caixa: o motivo vai para o log, a empresa lê um texto dela', [r['ok'], 'banco ainda não está configurado' in (r['detalhe'] or ''), r['erro']], [False, True, 'Não foi possível gerar a cobrança agora. Tente de novo em alguns minutos ou fale com a IA365.'])
    ok('a empresa NÃO fica marcada como assinante', [emp(e1)['solicitada'], emp(e1)['forma']], [False, None])
    cli = sql('caixa_assinatura', 'select id, name, cnpj, legalName, addressCity, addressState, addressZip, contactEmail, erpEmpresaId from Client')
    ok('o caixa criou UM cliente com os dados do ERP e o vínculo', [len(cli), cli[0][1:]] if cli else None, [1, ['Loja Pix', '11222333000181', 'Loja Pix LTDA', 'Teresina', 'PI', '64000-000', 'dono@11222333000181.com', str(e1)]])
    rev = sql('caixa_assinatura', 'select id, amount, status, erpAssinatura, isRecurring, date(dueDate) from Revenue')
    venc = sql('erp_assinatura', f'select date(trial_fim) from empresas where id={e1}')[0][0]
    ok('e lançou UMA 1ª mensalidade (R$ 590, vence no fim do teste), ainda sem recorrência', [len(rev), rev[0][1:]] if rev else None, [1, ['590.00', 'pendente', 'primeira:pix', '0', venc]])
    rid = int(rev[0][0])

    print('\nCom o PIX emitido (plantado: aqui não há banco), assinar de novo')
    sql('caixa_assinatura', f"insert into InterCobranca (revenueId,tipo,txid,valor,vencimento,pixCopiaECola,status,validoAte,modalidade,criadoEm,atualizadoEm) values ({rid},'pix','TXIDASSINA000000000000000001',590,'{venc}','000201PIXASSINATURA','ativa',date_add(now(), interval 30 day),'cobv',now(),now())")
    r = assinar(e1, 'pix')
    ok('o ERP recebe a fatura com o PIX copia e cola e o link da página', [r['ok'], r['fatura'] and [int(r['fatura']['caixa_ref']), r['fatura']['status'], r['fatura']['pix_copia_cola'], r['fatura']['origem'], r['fatura']['link'].startswith(f'https://caixa.ia365.com.br/f/{rid}.')]], [True, [rid, 'pendente', '000201PIXASSINATURA', 'caixa', True]])
    ok('sem duplicar: 1 cliente, 1 cobrança no caixa, 1 fatura no ERP', [len(sql('caixa_assinatura', 'select id from Client')), len(sql('caixa_assinatura', 'select id from Revenue')), len(sql('erp_assinatura', 'select id from plataforma_faturas'))], [1, 1, 1])
    s = emp(e1)
    ok('empresa: pediu a assinatura, bloqueio automático ligado com a tolerância do admin, segue em teste', [s['forma'], s['solicitada'], s['ativada'], s['trial'], s['bloqueio'], s['tol'], s['cli']], ['pix', True, False, True, True, 4, int(cli[0][0])])
    ok('a resposta já valeu como entrega (o caixa não manda a mesma fatura de novo)', sql('caixa_assinatura', f'select erpSync is not null from Revenue where id={rid}')[0][0], '1')

    print('\nO banco confirma o PIX (mesmo caminho do webhook do Inter)')
    b = sh(['npx', 'tsx', 'scripts/sim-banco-pagou.ts', 'TXIDASSINA000000000000000001', '590'], cwd=CAIXA_DIR, env={**__import__('os').environ, **CAIXA_ENV})
    ok('baixa aceita', '"ok":true' in b.stdout, True)
    serie = sql('caixa_assinatura', 'select status, isRecurring, erpAssinatura, count(*), count(distinct groupId), min(date(dueDate)), count(distinct day(dueDate)) from Revenue group by 1,2,3 order by 1')
    ok('no caixa: a paga + 12 meses pendentes, todos recorrentes, na MESMA série e no mesmo dia do mês', [[x[:5] for x in serie], len(sql('caixa_assinatura', 'select distinct groupId from Revenue'))], [[['pendente', '1', 'serie', '12', '1'], ['recebido', '1', 'serie', '1', '1']], 1])
    fat = sql('caixa_assinatura', 'select ativo, forma, diasAntes, enviar, marcarPronta from Faturamento')
    ok('faturamento programado ligado: PIX 5 dias antes, SEM mensagem automática', fat, [['1', 'pix', '5', '0', '0']])
    time.sleep(2)
    ok('no ERP: fatura paga', sql('erp_assinatura', f'select status from plataforma_faturas where caixa_ref={rid}'), [['paga']])
    s = emp(e1)
    ok('no ERP: a empresa deixou de ser teste e virou assinante', [s['ativada'], s['trial'], s['ativa']], [True, False, True])
    b = sh(['npx', 'tsx', 'scripts/sim-banco-pagou.ts', 'TXIDASSINA000000000000000001', '590'], cwd=CAIXA_DIR, env={**__import__('os').environ, **CAIXA_ENV})
    ok('aviso repetido do banco não cria outra série', len(sql('caixa_assinatura', 'select id from Revenue')), 13)
    r = assinar(e1, 'pix')
    ok('quem já assina não assina de novo', r['ok'], False)

    print('\nGerar boleto numa fatura (banco sem configuração: o motivo chega à tela)')
    sql('erp_assinatura', f"insert into plataforma_faturas (empresa_id,origem,caixa_ref,competencia,valor,vencimento,status,created_at,updated_at) select {e1},'caixa',id,'2026-12',590,dueDate,'pendente',now(),now() from caixa_assinatura.Revenue where status='pendente' order by dueDate limit 1")
    fid = sql('erp_assinatura', "select id from plataforma_faturas where status='pendente'")[0][0]
    r = php(rf'''$f=\App\Models\PlataformaFatura::find({fid}); echo json_encode(\App\Services\Plataforma\AssinaturaSite::emitir($f,'boleto'));''')
    ok('o motivo do caixa chega ao ERP (log) e a tela mostra texto para a empresa', [r['ok'], 'banco ainda não está configurado' in (r['detalhe'] or ''), 'Ajustes' in r['erro']], [False, True, False])
    r = php(rf'''$f=\App\Models\PlataformaFatura::find({fid}); $f->caixa_ref=999999; echo json_encode(\App\Services\Plataforma\AssinaturaSite::emitir($f,'pix'));''')
    ok('cobrança que não é da empresa: o caixa recusa', [r['ok'], 'não encontrada' in (r['detalhe'] or '')], [False, True])

    print('\nSegredo errado e corpo ruim no caixa')
    def post(caminho, corpo, token='teste-segredo-caixa-erp'):
        req = urllib.request.Request(f'http://10.213.80.1:3999/api/erp/maquina/{caminho}', data=json.dumps(corpo).encode(), headers={'Content-Type': 'application/json', 'Authorization': f'Bearer {token}'})
        try:
            with urllib.request.urlopen(req, timeout=60) as r: return r.status
        except urllib.error.HTTPError as e: return e.code
    antes = len(sql('caixa_assinatura', 'select id from Client'))
    ok('sem o segredo: 401 nas três rotas, nada criado', [post('assinatura', {}, 'errado'), post('emitir', {}, 'errado'), post('baixa', {}, ''), len(sql('caixa_assinatura', 'select id from Client'))], [401, 401, 401, antes])
    ok('corpo inválido: 400', [post('assinatura', {'empresa_id': 1}), post('emitir', {'empresa_id': 1}), post('baixa', {'ref': 1})], [400, 400, 400])
    ok('baixa de cobrança de OUTRA empresa: 404', post('baixa', {'empresa_id': e2, 'ref': rid}), 404)

    print('\nAssinar por CARTÃO (InfinitePay de mentira)')
    r = assinar(e2, 'cartao')
    rid2 = int(sql('caixa_assinatura', f'select r.id from Revenue r join Client c on c.id=r.clientId where c.erpEmpresaId={e2}')[0][0])
    ok('sem banco o PIX de reserva não sai e o caixa recusa', r['ok'], False)
    sql('caixa_assinatura', f"insert into InterCobranca (revenueId,tipo,txid,valor,vencimento,pixCopiaECola,status,validoAte,modalidade,criadoEm,atualizadoEm) values ({rid2},'pix','TXIDASSINA000000000000000002',590,'{venc}','000201PIXCARTAO','ativa',date_add(now(), interval 30 day),'cobv',now(),now())")
    r = assinar(e2, 'cartao')
    ok('com o PIX de reserva: fatura no ERP e forma = cartão', [r['ok'], emp(e2)['forma'], sql('caixa_assinatura', f'select erpAssinatura from Revenue where id={rid2}')[0][0]], [True, 'cartao', 'primeira:pix'])
    f2 = r['fatura']['id']
    l = php(rf'''$f=\App\Models\PlataformaFatura::find({f2}); $l=\App\Services\Plataforma\AssinaturaSite::linkCartao($f); echo json_encode($l+['nsu'=>$f->fresh()->cartao_nsu,'token'=>\App\Services\Plataforma\AssinaturaSite::webhookToken()]);''')
    pedido = ip['pedidos'][-1]
    ok('o link nasce na InfinitePay com a tag da IA365, o valor em centavos e o endereço de aviso', [l['ok'], l['link'].endswith(l['nsu']), pedido[0], pedido[1]['handle'], pedido[1]['items'][0]['price'], pedido[1]['webhook_url'].endswith('/infinitepay-plataforma/' + l['token'])], [True, True, '/links', 'ia365teste', 59000, True])

    def aviso(token, corpo):
        req = urllib.request.Request(f'http://{erp_ip}/api/integracao/v1/webhooks/infinitepay-plataforma/{token}', data=json.dumps(corpo).encode(), headers={'Content-Type': 'application/json', 'Accept': 'application/json'})
        with urllib.request.urlopen(req, timeout=60) as r: return json.loads(r.read())
    ok('aviso com token errado: ignorado', aviso('errado', {'order_nsu': l['nsu']}), {'ignored': 'nao_autorizado'})
    ok('aviso sem pagamento confirmado pela InfinitePay: não baixa', [aviso(l['token'], {'order_nsu': l['nsu']}).get('pago'), sql('erp_assinatura', f'select status from plataforma_faturas where id={f2}')[0][0]], [False, 'pendente'])
    ip['pago'] = True
    ok('aviso com pagamento confirmado: baixa', aviso(l['token'], {'order_nsu': l['nsu'], 'transaction_nsu': 'T-1', 'invoice_slug': 'S-1'}).get('pago'), True)
    ok('no ERP: fatura paga no cartão, baixa entregue ao caixa, empresa assinante', [sql('erp_assinatura', f'select status, forma_pagamento, caixa_baixa_pendente from plataforma_faturas where id={f2}')[0], emp(e2)['ativada']], [['paga', 'cartão (InfinitePay)', '0'], True])
    ok('no caixa: cobrança recebida, PIX de reserva cancelado, recorrência criada', [sql('caixa_assinatura', f'select status, amountReceived from Revenue where id={rid2}')[0], sql('caixa_assinatura', f'select status from InterCobranca where revenueId={rid2}')[0][0], sql('caixa_assinatura', f"select count(*) from Revenue r join Client c on c.id=r.clientId where c.erpEmpresaId={e2} and r.status='pendente' and r.isRecurring=1")[0][0]], [['recebido', '590.00'], 'cancelada', '12'])
    ok('aviso repetido: já processado', aviso(l['token'], {'order_nsu': l['nsu']}), {'ignored': 'ja_processado'})

    print(f'\n{oks} ✅ / {falhas} ❌')
    sys.exit(1 if falhas else 0)


if __name__ == '__main__':
    main()
