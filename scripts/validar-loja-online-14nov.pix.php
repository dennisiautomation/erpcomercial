<?php
/**
 * Apoio do roteiro de navegador da LOJA ONLINE (14/11/2026) — ambiente DESCARTÁVEL.
 *
 *   docker exec -i ec-app php < scripts/validar-loja-online-14nov.pix.php            (liga)
 *   docker exec -i -e ACAO=desligar ec-app php < scripts/validar-loja-online-14nov.pix.php
 *
 * "Liga" um Sicredi de mentira na empresa demo (sem certificado de verdade) para a
 * loja oferecer PIX, e deixa pronta a cobrança de UM pedido de teste — assim a tela
 * mostra o QR Code sem nenhuma chamada externa (a cobrança ATIVA é reaproveitada).
 * Imprime o token do pedido. O navegador marca a cobrança como paga pelo banco.
 */
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EmpresaGateway;
use App\Models\LojaOnline;
use App\Models\PedidoCobranca;
use App\Models\Produto;
use App\Services\LojaOnline\CatalogoLoja;
use App\Services\LojaOnline\CheckoutLoja;

$loja = LojaOnline::where('slug', 'demo')->firstOrFail();

if (getenv('ACAO') === 'desligar') {
    EmpresaGateway::where('empresa_id', $loja->empresa_id)->delete();
    echo "desligado\n";
    exit;
}

EmpresaGateway::updateOrCreate(['empresa_id' => $loja->empresa_id, 'provedor' => EmpresaGateway::PROVEDOR_SICREDI_PIX], [
    'ativo' => true, 'client_id' => 'id', 'client_secret' => 'seg', 'chave_pix' => 'chave@pix', 'base_url' => 'https://sicredi.invalido',
    'cert_path' => 'x.crt', 'key_path' => 'x.key', 'expiracao_segundos' => 3600,
]);

$catalogo = new CatalogoLoja($loja);
$produto = Produto::withoutGlobalScopes()->where('empresa_id', $loja->empresa_id)->where('descricao', 'Café Especial 250g')->firstOrFail();
$v = $catalogo->paraVitrine(collect([$produto]))->first();
$r = app(CheckoutLoja::class)->criarPedido($loja, collect([$v + ['quantidade' => 2, 'total' => round(2 * $v['preco'], 2), 'produto' => $produto]]), [
    'nome' => 'Cliente do PIX', 'telefone' => '(86) 91111-2222', 'entrega' => 'retirada', 'pagamento' => 'pix',
]);
$pedido = $r['pedido'];

PedidoCobranca::create([
    'empresa_id' => $pedido->empresa_id, 'pedido_id' => $pedido->id, 'provedor' => EmpresaGateway::PROVEDOR_SICREDI_PIX,
    'txid' => 'teste' . substr(md5(uniqid()), 0, 26), 'valor' => $pedido->total, 'chave' => 'chave@pix', 'status' => 'ATIVA',
    'copia_cola' => '00020126580014br.gov.bcb.pix0136chave-de-teste-da-loja-online5204000053039865802BR5909LOJA DEMO6009SAO PAULO62070503***6304ABCD',
    'expira_em' => now()->addHour(),
]);

echo json_encode(['token' => $pedido->loja_token, 'pedido' => $pedido->id, 'numero' => $pedido->numero, 'total' => (float) $pedido->total]), "\n";
