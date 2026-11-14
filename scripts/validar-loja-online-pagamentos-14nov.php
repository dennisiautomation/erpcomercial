<?php
/**
 * Validação ✅/❌ — LOJA ONLINE: pagamentos e frete com os provedores SIMULADOS (14/11/2026).
 *
 * Roda DENTRO do app do ambiente descartável, depois do roteiro .py (que deixa a
 * loja "demo" liberada e no ar):
 *     docker exec -i ec-app php < scripts/validar-loja-online-pagamentos-14nov.php
 *
 * Nenhuma chamada sai para fora: Http::fake responde no lugar de Sicredi, Asaas
 * e InfinitePay, e a cotação de frete é trocada por um dublê. O que se prova é a
 * ORQUESTRAÇÃO da loja (qual provedor entra, o que fica gravado, quem confirma o
 * pedido) — não a API real de cada provedor, que continua sendo a do agente.
 */
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Enums\StatusPedido;
use App\Models\EmpresaGateway;
use App\Models\LojaOnline;
use App\Models\Pedido;
use App\Models\PedidoCobranca;
use App\Models\PedidoLinkCartao;
use App\Models\Produto;
use App\Services\Entrega\CotacaoEntregaService;
use App\Services\LojaOnline\CatalogoLoja;
use App\Services\LojaOnline\CheckoutLoja;
use App\Services\LojaOnline\PagamentoLoja;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

config(['queue.default' => 'sync', 'services.infinitepay.base_url' => 'https://infinitepay.falso/invoices/public/checkout']);

$ok = 0;
$falhas = 0;
function check(string $nome, bool $cond, mixed $extra = ''): void
{
    global $ok, $falhas;
    $cond ? $ok++ : $falhas++;
    echo ($cond ? '✅ ' : '❌ ') . $nome . ($cond ? '' : '  ' . mb_substr(is_string($extra) ? $extra : json_encode($extra, JSON_UNESCAPED_UNICODE), 0, 400)) . "\n";
}

$loja = LojaOnline::where('slug', 'demo')->firstOrFail();
$empresaId = (int) $loja->empresa_id;
$base = ['vender_sem_estoque' => false, 'retirada' => true, 'entrega' => true, 'pix' => true, 'cartao' => true, 'combinar' => true];
$loja->update(['config' => array_merge($loja->config ?? [], $base), 'estoque_id' => null]);

EmpresaGateway::where('empresa_id', $empresaId)->delete();
DB::table('agente_ia_configs')->where('empresa_id', $empresaId)->update(['provedor_cartao' => null]);

/** Chamadas HTTP que os serviços fizeram (método + URL). */
$chamadas = [];
/** Respostas controladas pelo teste. */
// 'rodada' deixa os ids do provedor falso únicos a cada execução (txid é único no banco)
$estado = ['sicredi_paga' => false, 'asaas_status' => 'PENDING', 'infinite_paga' => false, 'seq' => 0, 'rodada' => substr(uniqid(), -6)];

Http::fake(function (HttpRequest $req) use (&$chamadas, &$estado) {
    $url = $req->url();
    $chamadas[] = $req->method() . ' ' . $url;

    if (str_contains($url, 'sicredi.falso')) {
        if (str_contains($url, '/oauth/token')) {
            return Http::response(['access_token' => 'tok', 'expires_in' => 300]);
        }
        if ($req->method() === 'PUT') {
            return Http::response(['status' => 'ATIVA', 'pixCopiaECola' => '00020126SICREDI' . basename($url), 'location' => 'pix.falso/loc']);
        }

        return Http::response($estado['sicredi_paga']
            ? ['status' => 'CONCLUIDA', 'pix' => [['endToEndId' => 'E123', 'horario' => now()->toIso8601String(), 'valor' => '1.00']]]
            : ['status' => 'ATIVA']);
    }

    if (str_contains($url, 'asaas.com')) {
        if (str_contains($url, '/customers')) {
            return Http::response($req->method() === 'GET' ? ['data' => []] : ['id' => 'cus_1']);
        }
        if (str_contains($url, '/pixQrCode')) {
            return Http::response(['payload' => '00020126ASAAS-COPIA-E-COLA', 'encodedImage' => base64_encode('png-falso'), 'expirationDate' => now()->addDay()->toDateTimeString()]);
        }
        if ($req->method() === 'POST' && str_ends_with($url, '/payments')) {
            $estado['seq']++;
            $tipo = $req->data()['billingType'] ?? '';

            return Http::response(['id' => 'pay_' . strtolower($tipo) . '_' . $estado['rodada'] . '_' . $estado['seq'], 'invoiceUrl' => 'https://asaas.falso/i/' . $estado['seq']]);
        }

        return Http::response(['status' => $estado['asaas_status']]);
    }

    if (str_contains($url, 'infinitepay.falso')) {
        if (str_contains($url, '/payment_check')) {
            return Http::response($estado['infinite_paga']
                ? ['paid' => true, 'amount' => 999999, 'paid_amount' => 999999, 'installments' => 3, 'capture_method' => 'credit_card']
                : ['paid' => false]);
        }

        return Http::response(['url' => 'https://checkout.infinitepay.falso/l/abc' . (++$estado['seq'])]);
    }

    return Http::response(['erro' => 'URL não prevista no teste: ' . $url], 500);
});

/** Monta itens como o CarrinhoLoja entregaria. */
function itens(LojaOnline $loja, array $qtds): Illuminate\Support\Collection
{
    $catalogo = new CatalogoLoja($loja);
    $produtos = Produto::withoutGlobalScopes()->whereIn('descricao', array_keys($qtds))->where('empresa_id', $loja->empresa_id)->get();
    $vitrine = $catalogo->paraVitrine($produtos)->keyBy('nome');

    return $produtos->map(fn ($p) => $vitrine[$p->descricao] + [
        'quantidade' => $qtds[$p->descricao], 'total' => round($qtds[$p->descricao] * $vitrine[$p->descricao]['preco'], 2), 'produto' => $p,
    ])->values();
}

function novoPedido(LojaOnline $loja, array $extra = []): Pedido
{
    static $n = 0;
    $n++;
    $r = app(CheckoutLoja::class)->criarPedido($loja, itens($loja, ['Camisa Linho Areia' => 1, 'Fone Bluetooth Pro' => 2]), array_merge([
        'nome' => 'Cliente Pagamento ' . $n, 'telefone' => '(86) 9' . str_pad((string) (55550000 + $n), 8, '0', STR_PAD_LEFT), 'cpf_cnpj' => null,
        'entrega' => 'retirada', 'pagamento' => 'pix',
    ], $extra));

    return $r['pedido'];
}

function gateway(int $empresaId, string $provedor, array $dados): void
{
    EmpresaGateway::updateOrCreate(['empresa_id' => $empresaId, 'provedor' => $provedor], array_merge(['ativo' => true], $dados));
}

$pagamento = app(PagamentoLoja::class);

/* ─────────────────────────── 1. sem provedor nenhum ─────────────────────────── */
echo "\n== 1. Sem PIX nem cartão ligados ==\n";
$m = PagamentoLoja::meios($loja);
check('1.1 sem provedor: PIX e cartão indisponíveis, combinar disponível', $m['pix'] === null && $m['cartao'] === null && $m['combinar'] === true, $m);
$loja->update(['config' => array_merge($loja->config, ['combinar' => false])]);
check('1.2 lojista desligou "combinar" mas não há outro meio: combinar vale assim mesmo', PagamentoLoja::meios($loja->fresh())['combinar'] === true);
$loja->update(['config' => array_merge($loja->config, ['combinar' => true])]);
$p = novoPedido($loja);
check('1.3 pedido de teste: rascunho, canal online, total 179,70', $p->status === StatusPedido::Rascunho && $p->canal->value === 'online' && (float) $p->total === 179.70, [(string) $p->total]);
$r = $pagamento->pix($loja, $p);
check('1.4 pedir PIX sem provedor devolve erro, sem chamada externa', $r['ok'] === false && $chamadas === [], $chamadas);
check('1.5 situação: aguardando', PagamentoLoja::situacao($p)['situacao'] === 'aguardando');

/* ─────────────────────────── 2. Sicredi ─────────────────────────── */
echo "\n== 2. PIX pelo Sicredi (QR desenhado na tela) ==\n";
gateway($empresaId, EmpresaGateway::PROVEDOR_SICREDI_PIX, ['client_id' => 'id', 'client_secret' => 'seg', 'chave_pix' => 'chave@pix',
    'base_url' => 'https://sicredi.falso', 'cert_path' => 'x.crt', 'key_path' => 'x.key', 'expiracao_segundos' => 3600]);
$loja = $loja->fresh();
check('2.1 com Sicredi ligado, o PIX da loja é o Sicredi', PagamentoLoja::meios($loja)['pix'] === 'sicredi_pix');
$chamadas = [];
$r = $pagamento->pix($loja, $p);
check('2.2 PIX gerado: copia-e-cola do Sicredi, sem imagem (o navegador desenha o QR)', $r['ok'] && $r['tipo'] === 'qr' && str_starts_with($r['copia_cola'], '00020126SICREDI') && $r['qr_base64'] === null, $r);
$cob = PedidoCobranca::where('pedido_id', $p->id)->first();
check('2.3 cobrança gravada: provedor sicredi_pix, ATIVA, valor do pedido', $cob && $cob->provedor === 'sicredi_pix' && $cob->status === 'ATIVA' && (float) $cob->valor === 179.70, $cob?->toArray());
$antes = count($chamadas);
$r2 = $pagamento->pix($loja, $p);
check('2.4 pedir de novo reaproveita a mesma cobrança (nenhuma chamada nova)', $r2['copia_cola'] === $r['copia_cola'] && count($chamadas) === $antes && PedidoCobranca::where('pedido_id', $p->id)->count() === 1);
Cache::flush();
$pagamento->conferir($p);
check('2.5 conferir sem pagamento: continua aguardando', PagamentoLoja::situacao($p->fresh())['situacao'] === 'aguardando');
$n = count($chamadas);
$pagamento->conferir($p);
check('2.6 conferir de novo em menos de 8 s não consulta o provedor', count($chamadas) === $n);
$estado['sicredi_paga'] = true;
Cache::forget('loja_online_conferir_' . $p->id);
$pagamento->conferir($p);
$p->refresh();
check('2.7 pagamento caiu: pedido CONFIRMADO, situação "pago", anotação PIX PAGO', $p->status === StatusPedido::Confirmado && PagamentoLoja::situacao($p)['situacao'] === 'pago'
    && str_contains((string) $p->observacoes_internas, 'PIX PAGO'), [$p->status->value, $p->observacoes_internas]);
$estado['sicredi_paga'] = false;

/* ─────────────────────────── 3. Asaas ─────────────────────────── */
echo "\n== 3. PIX e cartão pelo Asaas ==\n";
EmpresaGateway::where('empresa_id', $empresaId)->delete();
gateway($empresaId, EmpresaGateway::PROVEDOR_ASAAS, ['client_secret' => '$aact_teste', 'config' => ['sandbox' => true, 'webhook_token' => 'segredo-webhook']]);
$loja = $loja->fresh();
$m = PagamentoLoja::meios($loja);
check('3.1 só Asaas ligado: PIX = Asaas, cartão = Asaas', $m['pix'] === 'asaas' && $m['cartao'] === 'asaas', $m);
$p = novoPedido($loja);
$chamadas = [];
$r = $pagamento->pix($loja, $p);
check('3.2 PIX do Asaas: copia-e-cola + imagem do QR prontos', $r['ok'] && $r['tipo'] === 'qr' && $r['copia_cola'] === '00020126ASAAS-COPIA-E-COLA' && $r['qr_base64'] === base64_encode('png-falso'), $r);
check('3.3 foram criados cliente, cobrança PIX e QR no Asaas (sandbox)', count(array_filter($chamadas, fn ($c) => str_contains($c, 'api-sandbox.asaas.com'))) === count($chamadas)
    && (bool) array_filter($chamadas, fn ($c) => str_ends_with($c, '/pixQrCode')), $chamadas);
$cob = PedidoCobranca::where('pedido_id', $p->id)->first();
check('3.4 cobrança gravada com provedor "asaas" e o id do pagamento', $cob && $cob->provedor === 'asaas' && str_starts_with($cob->txid, 'pay_pix_'), $cob?->toArray());
$n = count($chamadas);
$r2 = $pagamento->pix($loja, $p);
check('3.5 pedir de novo reaproveita (nenhuma chamada nova, mesma imagem)', count($chamadas) === $n && $r2['qr_base64'] === $r['qr_base64']);
// a rotina de 15 min do Sicredi não pode confundir a cobrança do Asaas
$n = count($chamadas);
Artisan::call('agente:pix-sincronizar');
check('3.6 rotina "agente:pix-sincronizar" não consulta o Sicredi por cobrança do Asaas', count($chamadas) === $n && PedidoCobranca::find($cob->id)->status === 'ATIVA', array_slice($chamadas, $n));
$estado['asaas_status'] = 'RECEIVED';
Cache::forget('loja_online_conferir_' . $p->id);
$pagamento->conferir($p);
$p->refresh();
check('3.7 Asaas recebeu: pedido CONFIRMADO, condição "pix", anotação "PIX PAGO via Asaas"', $p->status === StatusPedido::Confirmado && $p->condicao_pagamento === 'pix'
    && str_contains((string) $p->observacoes_internas, 'PIX PAGO via Asaas') && PedidoCobranca::find($cob->id)->paga(), [$p->status->value, $p->condicao_pagamento]);
Cache::forget('loja_online_conferir_' . $p->id);
$pagamento->conferir($p);
check('3.8 conferir de novo não duplica a anotação', substr_count((string) $p->fresh()->observacoes_internas, 'PIX PAGO via Asaas') === 1);
$estado['asaas_status'] = 'PENDING';

// webhook do Asaas (o caminho normal de confirmação)
$p2 = novoPedido($loja);
$r = $pagamento->pix($loja, $p2);
$cob2 = PedidoCobranca::where('pedido_id', $p2->id)->first();
$http = $app->make(Illuminate\Contracts\Http\Kernel::class);
$webhook = function (array $corpo, string $token) use ($http) {
    $req = Request::create('/api/integracao/v1/webhooks/asaas', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_ASAAS_ACCESS_TOKEN' => $token, 'HTTP_HOST' => parse_url(config('app.url'), PHP_URL_HOST)], json_encode($corpo));

    return $http->handle($req);
};
$resp = $webhook(['event' => 'PAYMENT_RECEIVED', 'payment' => ['id' => $cob2->txid, 'value' => 179.70, 'billingType' => 'PIX', 'externalReference' => 'pedido:' . $p2->id]], 'token-errado');
check('3.9 webhook com token errado é ignorado', $p2->fresh()->status === StatusPedido::Rascunho && ! PedidoCobranca::find($cob2->id)->paga(), $resp->getContent());
$resp = $webhook(['event' => 'PAYMENT_RECEIVED', 'payment' => ['id' => $cob2->txid, 'value' => 179.70, 'billingType' => 'PIX', 'externalReference' => 'pedido:' . $p2->id]], 'segredo-webhook');
$p2->refresh();
check('3.10 webhook PIX do Asaas: pedido CONFIRMADO, cobrança paga, anotação diz PIX (não cartão)', $resp->getStatusCode() === 200 && $p2->status === StatusPedido::Confirmado
    && PedidoCobranca::find($cob2->id)->paga() && str_contains((string) $p2->observacoes_internas, 'PIX PAGO via Asaas') && ! str_contains((string) $p2->observacoes_internas, 'CARTÃO'),
    [$resp->getContent(), $p2->observacoes_internas]);
check('3.11 depois de pago: situação "pago"', PagamentoLoja::situacao($p2)['situacao'] === 'pago');

// cartão por link
$p3 = novoPedido($loja, ['pagamento' => 'cartao']);
$r = $pagamento->cartao($p3);
$link = PedidoLinkCartao::where('pedido_id', $p3->id)->first();
check('3.12 cartão: link do Asaas gerado e guardado', $r['ok'] && $r['tipo'] === 'link' && str_starts_with($r['link'], 'https://asaas.falso/i/') && $link && $link->provedor === 'asaas', $r);
$resp = $webhook(['event' => 'PAYMENT_CONFIRMED', 'payment' => ['id' => $link->referencia, 'value' => 179.70, 'billingType' => 'CREDIT_CARD', 'externalReference' => 'pedido:' . $p3->id]], 'segredo-webhook');
$p3->refresh();
check('3.13 webhook de CARTÃO continua como era: confirma, anota "CARTÃO PAGO", não mexe na condição', $p3->status === StatusPedido::Confirmado
    && str_contains((string) $p3->observacoes_internas, 'CARTÃO PAGO via Asaas') && $p3->condicao_pagamento === null && PedidoLinkCartao::find($link->id)->pago(), [$p3->observacoes_internas, $p3->condicao_pagamento]);

// loja desliga PIX/cartão mesmo com provedor ligado
$loja->update(['config' => array_merge($loja->config, ['pix' => false, 'cartao' => false])]);
$m = PagamentoLoja::meios($loja->fresh());
check('3.14 lojista desligou PIX e cartão na loja: nenhum dos dois é oferecido (combinar sim)', $m['pix'] === null && $m['cartao'] === null && $m['combinar'] === true, $m);
$loja->update(['config' => array_merge($loja->config, ['pix' => true, 'cartao' => true])]);

/* ─────────────────────────── 4. InfinitePay ─────────────────────────── */
echo "\n== 4. InfinitePay (link que aceita cartão e PIX) ==\n";
EmpresaGateway::where('empresa_id', $empresaId)->delete();
gateway($empresaId, EmpresaGateway::PROVEDOR_INFINITEPAY, ['config' => ['handle' => 'lojademo']]);
$loja = $loja->fresh();
$m = PagamentoLoja::meios($loja);
check('4.1 só InfinitePay: PIX e cartão saem pelo link dela', $m['pix'] === 'infinitepay' && $m['cartao'] === 'infinitepay', $m);
$p4 = novoPedido($loja);
$r = $pagamento->pix($loja, $p4);
check('4.2 "PIX" devolve o LINK (não há QR próprio)', $r['ok'] && $r['tipo'] === 'link' && str_starts_with($r['link'], 'https://checkout.infinitepay.falso/'), $r);
$r2 = $pagamento->cartao($p4);
check('4.3 pedir o cartão reaproveita o mesmo link', $r2['ok'] && $r2['link'] === $r['link'] && PedidoLinkCartao::where('pedido_id', $p4->id)->count() === 1);
$estado['infinite_paga'] = true;
Cache::forget('loja_online_conferir_' . $p4->id);
$pagamento->conferir($p4);
$p4->refresh();
check('4.4 pagamento aprovado na consulta: pedido CONFIRMADO e link pago', $p4->status === StatusPedido::Confirmado && PedidoLinkCartao::where('pedido_id', $p4->id)->first()->pago()
    && PagamentoLoja::situacao($p4)['situacao'] === 'pago', [$p4->status->value]);
$estado['infinite_paga'] = false;

/* ─────────────────────────── 5. os dois ligados ─────────────────────────── */
echo "\n== 5. Sicredi + Asaas ligados juntos ==\n";
gateway($empresaId, EmpresaGateway::PROVEDOR_ASAAS, ['client_secret' => '$aact_teste', 'config' => ['sandbox' => true, 'webhook_token' => 'segredo-webhook']]);
gateway($empresaId, EmpresaGateway::PROVEDOR_SICREDI_PIX, ['client_id' => 'id', 'client_secret' => 'seg', 'chave_pix' => 'chave@pix',
    'base_url' => 'https://sicredi.falso', 'cert_path' => 'x.crt', 'key_path' => 'x.key', 'expiracao_segundos' => 3600]);
EmpresaGateway::where('empresa_id', $empresaId)->where('provedor', EmpresaGateway::PROVEDOR_INFINITEPAY)->delete();
$m = PagamentoLoja::meios($loja->fresh());
check('5.1 com os dois: PIX pelo Sicredi (QR na tela), cartão pelo Asaas', $m['pix'] === 'sicredi_pix' && $m['cartao'] === 'asaas', $m);

/* ─────────────────────────── 6. frete (cotação trocada por dublê) ─────────────────────────── */
echo "\n== 6. Frete com cotação disponível (dublê do serviço de cotação) ==\n";
$duble = new class extends CotacaoEntregaService {
    public array $resposta = [];

    public array $ultimaChamada = [];

    public function resolver(int $empresaId, \App\Models\Unidade $unidade, array $end, array $itens = [], ?string $servicoId = null): array
    {
        $this->ultimaChamada = compact('empresaId', 'end', 'itens', 'servicoId');
        $r = $this->resposta;
        if (($r['provedor'] ?? '') === 'melhor_envio' && $servicoId) {
            foreach ($r['opcoes'] as $o) {
                if ($o['servico_id'] === $servicoId) {
                    $r = array_merge($r, ['valor' => $o['valor'], 'prazo_dias' => $o['prazo_dias'], 'servico_id' => $o['servico_id'], 'servico_nome' => $o['nome'], 'transportadora' => $o['transportadora']]);
                }
            }
        }

        return $r;
    }
};
$app->instance(CotacaoEntregaService::class, $duble);
$app->forgetInstance(CheckoutLoja::class);
$checkout = $app->make(CheckoutLoja::class);
$opcoes = [
    ['servico_id' => '1', 'nome' => 'PAC', 'transportadora' => 'Correios', 'valor' => 22.50, 'prazo_dias' => 7],
    ['servico_id' => '2', 'nome' => 'SEDEX', 'transportadora' => 'Correios', 'valor' => 41.90, 'prazo_dias' => 2],
];
$duble->resposta = ['disponivel' => true, 'provedor' => 'melhor_envio', 'valor' => 22.50, 'prazo_dias' => 7, 'servico_id' => '1', 'servico_nome' => 'PAC', 'transportadora' => 'Correios', 'opcoes' => $opcoes];
$its = itens($loja, ['Camisa Linho Areia' => 1, 'Fone Bluetooth Pro' => 2]);
$c = $checkout->cotar($loja, $its, ['cep' => '01001-000']);
check('6.1 cotação com transportadoras: duas opções com nome, valor e prazo', $c['disponivel'] && count($c['opcoes']) === 2 && $c['opcoes'][1] === ['id' => '2', 'nome' => 'SEDEX · Correios', 'valor' => 41.90, 'prazo' => 'até 2 dias úteis'], $c['opcoes']);
check('6.2 a cotação recebe os PRODUTOS (medidas) e as quantidades do carrinho', count($duble->ultimaChamada['itens']) === 2 && $duble->ultimaChamada['itens'][0]['produto'] instanceof Produto
    && $duble->ultimaChamada['end']['cep'] === '01001000');
$r = $checkout->criarPedido($loja, $its, ['nome' => 'Cliente Frete', 'telefone' => '(11) 95555-7777', 'entrega' => 'entrega', 'cep' => '01001-000', 'logradouro' => 'Praça da Sé',
    'numero' => '1', 'bairro' => 'Sé', 'servico_id' => '2', 'pagamento' => 'pix']);
$pf = $r['pedido'];
check('6.3 pedido com SEDEX escolhido: frete 41,90 somado ao total (179,70 + 41,90)', ! $r['frete_combinar'] && (float) $pf->frete_valor === 41.90 && (float) $pf->total === 221.60
    && $pf->frete_provedor === 'melhor_envio' && $pf->frete_servico_nome === 'SEDEX' && (int) $pf->frete_prazo_dias === 2, [$pf->frete_valor, $pf->total, $pf->frete_servico_nome]);
$r = $checkout->criarPedido($loja, $its, ['nome' => 'Cliente Frete', 'telefone' => '(11) 95555-7777', 'entrega' => 'entrega', 'cep' => '01001-000', 'logradouro' => 'Praça da Sé',
    'numero' => '1', 'bairro' => 'Sé', 'servico_id' => '999', 'pagamento' => 'pix']);
check('6.4 serviço de frete forjado na tela não vale: fica o que a cotação do servidor devolver (PAC 22,50)', (float) $r['pedido']->frete_valor === 22.50 && (float) $r['pedido']->total === 202.20, [$r['pedido']->frete_valor]);
$duble->resposta = ['disponivel' => true, 'provedor' => 'uber_direct', 'valor' => 14.00, 'prazo_minutos' => 35];
$c = $checkout->cotar($loja, $its, ['cep' => '01001-000']);
check('6.5 entrega local (Uber): uma opção "Entrega na sua cidade" com o prazo em minutos', $c['disponivel'] && count($c['opcoes']) === 1 && $c['opcoes'][0]['id'] === 'local' && $c['opcoes'][0]['valor'] === 14.0
    && str_contains($c['opcoes'][0]['prazo'], '35 min'), $c['opcoes']);
$r = $checkout->criarPedido($loja, $its, ['nome' => 'Cliente Local', 'telefone' => '(11) 94444-8888', 'entrega' => 'entrega', 'cep' => '01001-000', 'logradouro' => 'Rua A',
    'numero' => '2', 'bairro' => 'Centro', 'servico_id' => 'local', 'pagamento' => 'pix']);
check('6.6 pedido com entrega local: frete 14,00, provedor uber_direct, total 193,70', (float) $r['pedido']->frete_valor === 14.0 && $r['pedido']->frete_provedor === 'uber_direct' && (float) $r['pedido']->total === 193.70);
$pix = $pagamento->pix($loja->fresh(), $r['pedido']);
check('6.7 o PIX cobra o total COM o frete', $pix['ok'] && (float) PedidoCobranca::where('pedido_id', $r['pedido']->id)->value('valor') === 193.70);
$duble->resposta = ['disponivel' => false, 'motivo' => 'cep_fora_da_area'];
$c = $checkout->cotar($loja, $its, ['cep' => '69900-000']);
check('6.8 CEP fora da área: indisponível, com orientação (retirar ou combinar)', ! $c['disponivel'] && str_contains($c['mensagem'], 'retirar na loja'), $c);
$r = $checkout->criarPedido($loja, $its, ['nome' => 'Cliente Longe', 'telefone' => '(68) 93333-9999', 'entrega' => 'entrega', 'cep' => '69900-000', 'logradouro' => 'Rua B',
    'numero' => '3', 'bairro' => 'Centro', 'pagamento' => 'pix']);
check('6.9 pedido para CEP sem frete: marcado "frete a combinar", total só dos produtos', $r['frete_combinar'] && $r['pedido']->frete_valor === null && (float) $r['pedido']->total === 179.70);
check('6.10 retirada nunca consulta a cotação', (function () use ($checkout, $loja, $its, $duble) {
    $duble->ultimaChamada = [];
    $checkout->criarPedido($loja, $its, ['nome' => 'Cliente Retira', 'telefone' => '(11) 92222-1111', 'entrega' => 'retirada', 'pagamento' => 'combinar']);

    return $duble->ultimaChamada === [];
})());

/* ─────────────────────────── 7. preço: promoção e estoque no pedido ─────────────────────────── */
echo "\n== 7. Pedido leva a loja de origem e o estoque escolhido ==\n";
$ecom = DB::table('estoques')->where('empresa_id', $empresaId)->where('nome', 'E-commerce')->value('id');
$loja->update(['estoque_id' => $ecom]);
$pe = novoPedido($loja->fresh(), ['entrega' => 'retirada']);
check('7.1 com estoque separado configurado, o pedido nasce com "Sai de" = esse estoque', (int) $pe->estoque_id === (int) $ecom && (int) $pe->unidade_id === (int) $loja->unidade_id, [$pe->estoque_id]);
$loja->update(['estoque_id' => null]);
$pe = novoPedido($loja->fresh(), ['entrega' => 'retirada']);
check('7.2 sem estoque separado, o pedido usa o estoque de venda da loja (estoque_id vazio)', $pe->estoque_id === null);
check('7.3 todo pedido da loja tem token único de 32 letras/números', Pedido::withoutGlobalScopes()->whereNotNull('loja_token')->get()->every(fn ($x) => preg_match('/^[a-z0-9]{32}$/', $x->loja_token) === 1)
    && Pedido::withoutGlobalScopes()->whereNotNull('loja_token')->distinct()->count('loja_token') === Pedido::withoutGlobalScopes()->whereNotNull('loja_token')->count());

// limpeza: a empresa volta a ficar sem provedor (o roteiro .py parte disso)
EmpresaGateway::where('empresa_id', $empresaId)->delete();

echo "\n" . ($falhas ? '❌' : '✅') . " {$ok} ok, {$falhas} falha(s)\n";
exit($falhas ? 1 : 0);
