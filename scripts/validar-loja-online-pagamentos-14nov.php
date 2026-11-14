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

/* ─────────────────────────── 8. cupom e frete grátis na conta do pedido ─────────────────────────── */
echo "\n== 8. Cupom e frete grátis: a conta é do servidor ==\n";
\App\Models\LojaCupom::where('empresa_id', $empresaId)->whereIn('codigo', ['T10', 'TFRETE', 'TUM'])->delete();
$c10 = \App\Models\LojaCupom::create(['empresa_id' => $empresaId, 'codigo' => 'T10', 'tipo' => 'percentual', 'valor' => 10, 'ativo' => true]);
\App\Models\LojaCupom::create(['empresa_id' => $empresaId, 'codigo' => 'TFRETE', 'tipo' => 'frete_gratis', 'valor' => 0, 'ativo' => true]);
$cUm = \App\Models\LojaCupom::create(['empresa_id' => $empresaId, 'codigo' => 'TUM', 'tipo' => 'valor', 'valor' => 500, 'limite_usos' => 1, 'ativo' => true]);
$duble->resposta = ['disponivel' => true, 'provedor' => 'melhor_envio', 'valor' => 22.50, 'prazo_dias' => 7, 'servico_id' => '1', 'servico_nome' => 'PAC', 'transportadora' => 'Correios', 'opcoes' => $opcoes];
$entrega = ['telefone' => '(11) 95555-7777', 'entrega' => 'entrega', 'cep' => '01001-000', 'logradouro' => 'Praça da Sé', 'numero' => '1', 'bairro' => 'Sé', 'servico_id' => '1', 'pagamento' => 'pix'];
$r = $checkout->criarPedido($loja->fresh(), $its, ['nome' => 'Cliente Cupom'] + $entrega + ['cupom' => 't10']);
$pc = $r['pedido'];
check('8.1 cupom de 10% + frete: 179,70 − 17,97 + 22,50 = 184,23', (float) $pc->desconto_valor === 17.97 && (float) $pc->desconto_percentual === 10.0 && (float) $pc->total === 184.23 && $pc->loja_cupom === 'T10',
    [$pc->desconto_valor, $pc->total, $pc->loja_cupom]);
check('8.2 o uso do cupom é contado', (int) $c10->fresh()->usos === 1);
$pix = $pagamento->pix($loja->fresh(), $pc);
check('8.3 o PIX cobra o total com desconto e frete', $pix['ok'] && (float) PedidoCobranca::where('pedido_id', $pc->id)->value('valor') === 184.23);
$r = $checkout->criarPedido($loja->fresh(), $its, ['nome' => 'Cliente Frete Gratis'] + $entrega + ['cupom' => 'TFRETE']);
check('8.4 cupom de frete grátis: cliente paga frete zero, o serviço e o custo (R$ 22,50) ficam anotados', (float) $r['pedido']->frete_valor === 0.0 && (float) $r['pedido']->total === 179.70
    && $r['pedido']->frete_servico_nome === 'PAC' && str_contains((string) $r['pedido']->observacoes_internas, 'FRETE GRÁTIS') && str_contains((string) $r['pedido']->observacoes_internas, '22,50'),
    [$r['pedido']->frete_valor, $r['pedido']->observacoes_internas]);
$r = $checkout->criarPedido($loja->fresh(), $its, ['nome' => 'Cliente Sem Cupom'] + $entrega + ['cupom' => 'NAOEXISTE']);
check('8.5 cupom inexistente mandado direto no pedido é ignorado (sem desconto)', (float) $r['pedido']->desconto_valor === 0.0 && $r['pedido']->loja_cupom === null && (float) $r['pedido']->total === 202.20);
$r1 = $checkout->criarPedido($loja->fresh(), $its, ['nome' => 'Cliente Um'] + $entrega + ['cupom' => 'TUM']);
$r2 = $checkout->criarPedido($loja->fresh(), $its, ['nome' => 'Cliente Dois'] + $entrega + ['cupom' => 'TUM']);
check('8.6 cupom de R$ 500 numa compra de R$ 179,70: o desconto para no valor dos produtos (total = só o frete)', (float) $r1['pedido']->desconto_valor === 179.70 && (float) $r1['pedido']->total === 22.50);
check('8.7 cupom com 1 uso: o segundo pedido sai sem desconto e o contador não passa de 1', (float) $r2['pedido']->desconto_valor === 0.0 && (int) $cUm->fresh()->usos === 1);
$loja->update(['config' => array_merge($loja->fresh()->config, ['frete_gratis_acima' => 150])]);
$r = $checkout->criarPedido($loja->fresh(), $its, ['nome' => 'Cliente Acima'] + $entrega);
check('8.8 "frete grátis a partir de R$ 150": compra de R$ 179,70 sai com frete zero', (float) $r['pedido']->frete_valor === 0.0 && (float) $r['pedido']->total === 179.70);
$r = $checkout->criarPedido($loja->fresh(), $its, ['nome' => 'Cliente Abaixo'] + $entrega + ['cupom' => 'T10']);
check('8.9 a regra olha o valor JÁ com o cupom: 179,70 − 17,97 = 161,73 ainda ganha frete grátis', (float) $r['pedido']->frete_valor === 0.0 && (float) $r['pedido']->total === 161.73);
$loja->update(['config' => array_merge($loja->fresh()->config, ['frete_gratis_acima' => 175])]);
$r = $checkout->criarPedido($loja->fresh(), $its, ['nome' => 'Cliente Abaixo'] + $entrega + ['cupom' => 'T10']);
check('8.10 …e com o mínimo em R$ 175 o mesmo pedido (161,73) paga o frete', (float) $r['pedido']->frete_valor === 22.50 && (float) $r['pedido']->total === 184.23);
$c = $checkout->cotar($loja->fresh(), $its, ['cep' => '01001-000'], null, true);
check('8.11 cotação com frete grátis: opções saem a R$ 0,00 marcadas "grátis", guardando o custo', $c['opcoes'][0]['valor'] === 0.0 && $c['opcoes'][0]['gratis'] === true && $c['opcoes'][0]['custo'] === 22.50);
$loja->update(['config' => array_merge($loja->fresh()->config, ['frete_gratis_acima' => 0])]);
\App\Models\LojaCupom::where('empresa_id', $empresaId)->whereIn('codigo', ['T10', 'TFRETE', 'TUM'])->delete();

/* ─────────────────────────── 9. e-mails por mudança de situação ─────────────────────────── */
echo "\n== 9. E-mails ao comprador quando o pedido anda ==\n";
\Illuminate\Support\Facades\Mail::fake();
$enviados = fn (string $tipo, ?int $pedidoId = null) => \Illuminate\Support\Facades\Mail::queued(\App\Mail\PedidoLoja::class, fn ($m) => $m->tipo === $tipo && ($pedidoId === null || $m->pedidoId === $pedidoId))->count();
$loja->update(['config' => array_merge($loja->fresh()->config, ['email_pedidos' => 'pedidos@loja.test', 'email_loja_novo' => true, 'email_cliente_recebido' => true,
    'email_cliente_confirmado' => true, 'email_cliente_andamento' => true])]);
$loja = $loja->fresh();
$pm = novoPedido($loja, ['email' => 'comprador@teste.test', 'telefone' => '(86) 97000-1000']);
\App\Services\LojaOnline\AvisosLoja::pedidoFechado($loja, $pm);
check('9.1 pedido fechado: 1 e-mail "recebido" ao comprador e 1 "pedido novo" à loja', $enviados('recebido', $pm->id) === 1 && $enviados('loja_novo', $pm->id) === 1
    && \Illuminate\Support\Facades\Mail::queued(\App\Mail\PedidoLoja::class, fn ($m) => $m->tipo === 'loja_novo' && $m->hasTo('pedidos@loja.test'))->count() === 1);
$r = $pagamento->pix($loja, $pm);
$cob = PedidoCobranca::where('pedido_id', $pm->id)->first();
$webhook(['event' => 'PAYMENT_RECEIVED', 'payment' => ['id' => $cob->txid, 'value' => (float) $pm->total, 'billingType' => 'PIX', 'externalReference' => 'pedido:' . $pm->id]], 'segredo-webhook');
check('9.2 pagamento confirmado pelo webhook: sai o e-mail "pedido confirmado" ao comprador', $pm->fresh()->status === StatusPedido::Confirmado && $enviados('confirmado', $pm->id) === 1
    && \Illuminate\Support\Facades\Mail::queued(\App\Mail\PedidoLoja::class, fn ($m) => $m->tipo === 'confirmado' && $m->hasTo('comprador@teste.test'))->count() === 1);
$pm->refresh();
$pm->update(['observacoes_internas' => $pm->observacoes_internas . "\nanotação qualquer"]);
check('9.3 mexer no pedido sem mudar a situação não envia nada', $enviados('confirmado', $pm->id) === 1);
$pm->update(['status' => StatusPedido::Faturado]);
$pm->update(['status' => StatusPedido::Entregue]);
check('9.4 faturar e entregar no ERP: e-mails "pronto/a caminho" e "entregue"', $enviados('faturado', $pm->id) === 1 && $enviados('entregue', $pm->id) === 1);
$pcanc = novoPedido($loja, ['email' => 'comprador@teste.test', 'telefone' => '(86) 97000-1000']);
$pcanc->update(['status' => StatusPedido::Cancelado]);
check('9.5 cancelar: e-mail "cancelado"', $enviados('cancelado', $pcanc->id) === 1);
$semEmail = novoPedido($loja, ['telefone' => '(86) 97000-2000']);
$semEmail->update(['status' => StatusPedido::Confirmado]);
check('9.6 comprador que não deu e-mail: nada é enviado (ele acompanha pelo endereço do pedido)', $enviados('confirmado', $semEmail->id) === 0);
$loja->update(['config' => array_merge($loja->fresh()->config, ['email_cliente_andamento' => false, 'email_cliente_confirmado' => false])]);
$pd = novoPedido($loja->fresh(), ['email' => 'comprador@teste.test', 'telefone' => '(86) 97000-1000']);
$pd->update(['status' => StatusPedido::Confirmado]);
$pd->update(['status' => StatusPedido::Faturado]);
check('9.7 com os avisos desligados pelo lojista, a mudança de situação não envia', $enviados('confirmado', $pd->id) === 0 && $enviados('faturado', $pd->id) === 0);
$doErp = Pedido::withoutGlobalScopes()->where('empresa_id', $empresaId)->whereNull('loja_token')->first()
    ?? Pedido::withoutGlobalScopes()->create(['empresa_id' => $empresaId, 'unidade_id' => $loja->unidade_id, 'cliente_id' => $pm->cliente_id, 'numero' => 999999, 'subtotal' => 1, 'total' => 1, 'status' => StatusPedido::Rascunho]);
$antes = \Illuminate\Support\Facades\Mail::queued(\App\Mail\PedidoLoja::class)->count();
$doErp->update(['status' => StatusPedido::Confirmado]);
check('9.8 pedido comum do ERP (que não nasceu na loja online) nunca dispara e-mail da loja', \Illuminate\Support\Facades\Mail::queued(\App\Mail\PedidoLoja::class)->count() === $antes);
$html = (new \App\Mail\PedidoLoja($pm->id, $loja->id, 'recebido'))->render();
check('9.9 o e-mail traz itens, total, o botão com o endereço do pedido e a cor da loja', str_contains($html, 'Camisa Linho Areia') && str_contains($html, '/pedido/' . $pm->loja_token)
    && str_contains($html, 'Acompanhar o pedido') && str_contains($html, $loja->temaEfetivo()['cor_primaria']) && str_contains($html, 'R$ 179,70'));
check('9.10 assunto de cada aviso leva o número do pedido e o nome da loja', \App\Mail\PedidoLoja::assunto('faturado', 12, 'Loja X') === 'Seu pedido #12 está pronto — Loja X'
    && \App\Mail\PedidoLoja::assunto('loja_novo', 12, 'Loja X') === 'Novo pedido #12 na loja online');
$loja->update(['config' => array_merge($loja->fresh()->config, ['email_pedidos' => '', 'email_loja_novo' => true, 'email_cliente_andamento' => true, 'email_cliente_confirmado' => true])]);

// limpeza: a empresa volta a ficar sem provedor (o roteiro .py parte disso)
EmpresaGateway::where('empresa_id', $empresaId)->delete();

echo "\n" . ($falhas ? '❌' : '✅') . " {$ok} ok, {$falhas} falha(s)\n";
exit($falhas ? 1 : 0);
