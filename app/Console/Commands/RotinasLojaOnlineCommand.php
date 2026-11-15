<?php

namespace App\Console\Commands;

use App\Mail\AvisoLoja;
use App\Models\EmpresaGateway;
use App\Models\LojaOnline;
use App\Models\Pedido;
use App\Models\PedidoCobranca;
use App\Services\LojaOnline\CatalogoLoja;
use App\Services\LojaOnline\PagamentoLoja;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Rotinas da loja online (15/11/2026), a cada 15 minutos:
 *
 *  1. Avise-me: o produto voltou ao estoque → 1 e-mail para quem pediu o aviso.
 *  2. Carrinho parado: 1 e-mail de lembrete (só nas lojas que LIGARAM a opção).
 *  3. Boleto/PIX do Asaas ainda em aberto: pergunta ao Asaas se pagou (rede de
 *     segurança do webhook — boleto compensa dias depois).
 *
 * `--seco` só mostra o que faria, sem enviar nem gravar.
 */
class RotinasLojaOnlineCommand extends Command
{
    protected $signature = 'loja:rotinas {--seco : só mostra o que faria}';

    protected $description = 'Loja online: avise-me, lembrete de carrinho e conferência de boleto/PIX do Asaas';

    public function handle(): int
    {
        $seco = (bool) $this->option('seco');
        $n = ['avise' => 0, 'carrinho' => 0, 'cobrancas' => 0];

        foreach (LojaOnline::where('ativo', true)->get() as $loja) {
            if (! $loja->noAr()) {
                continue;
            }
            $base = rtrim($loja->enderecoPublico(), '/');

            // 1. avise-me
            if ($loja->opcao('avise_me')) {
                $pendentes = DB::table('loja_avise_me')->where('empresa_id', $loja->empresa_id)->whereNull('avisado_em')->orderBy('id')->limit(300)->get();
                if ($pendentes->isNotEmpty()) {
                    $catalogo = new CatalogoLoja($loja);
                    $produtos = $catalogo->base()->whereIn('id', $pendentes->pluck('produto_id')->unique())->get()->keyBy('id');
                    $saldos = $catalogo->saldos($produtos->keys()->all());
                    foreach ($pendentes as $p) {
                        $produto = $produtos[$p->produto_id] ?? null;
                        if (! $produto || ($saldos[$produto->id] ?? 0) < 1) {
                            continue;
                        }
                        $n['avise']++;
                        if ($seco) {
                            continue;
                        }
                        Mail::to($p->email)->queue(new AvisoLoja($loja->id, 'Chegou: ' . $produto->descricao . ' — ' . $loja->nome, 'O produto que você queria chegou', [
                            '"' . $produto->descricao . '" está de volta em ' . $loja->nome . '.',
                            'As unidades são limitadas — este aviso não reserva o produto.',
                        ], 'Ver o produto', $base . '/p/' . $produto->id . '-' . (Str::slug($produto->descricao) ?: 'produto'),
                            'Você recebeu este e-mail porque pediu para ser avisado(a) em ' . $loja->nome . '. É um aviso único.'));
                        DB::table('loja_avise_me')->where('id', $p->id)->update(['avisado_em' => now(), 'updated_at' => now()]);
                    }
                }
            }

            // 2. lembrete do carrinho parado (um só por carrinho)
            if ($loja->opcao('carrinho_lembrete')) {
                $horas = max(1, (int) $loja->opcao('carrinho_lembrete_horas'));
                $linhas = DB::table('loja_carrinhos')->where('empresa_id', $loja->empresa_id)->whereNull('pedido_id')->whereNull('lembrete_em')
                    ->whereNotNull('email')->where('updated_at', '<=', now()->subHours($horas))->where('updated_at', '>', now()->subDays(7))->limit(200)->get();
                foreach ($linhas as $l) {
                    $n['carrinho']++;
                    if ($seco) {
                        continue;
                    }
                    $itens = collect((array) json_decode($l->itens, true))->map(fn ($i) => ((int) ($i['quantidade'] ?? 1)) . '× ' . ($i['nome'] ?? ''))->take(6)->implode(', ');
                    Mail::to($l->email)->queue(new AvisoLoja($loja->id, 'Você deixou produtos no carrinho — ' . $loja->nome,
                        ($l->nome ? Str::before($l->nome, ' ') . ', seu' : 'Seu') . ' carrinho está guardado', [
                            'Você começou uma compra em ' . $loja->nome . ' e não terminou: ' . $itens . '.',
                            'Pelo botão abaixo o carrinho volta pronto. Preço e estoque são os do momento da compra.',
                        ], 'Voltar ao meu carrinho', $base . '/carrinho/recuperar/' . $l->token,
                        'Você recebeu este e-mail porque informou o seu e-mail ao iniciar uma compra em ' . $loja->nome . '. É um lembrete único.'));
                    DB::table('loja_carrinhos')->where('id', $l->id)->update(['lembrete_em' => now()]);
                }
            }
        }

        // 3. cobranças do Asaas em aberto (boleto e PIX) dos pedidos da loja online
        $pagamento = app(PagamentoLoja::class);
        $abertas = PedidoCobranca::where('provedor', EmpresaGateway::PROVEDOR_ASAAS)->where('status', 'ATIVA')->whereNull('pago_em')
            ->where('created_at', '>', now()->subDays(12))->orderByDesc('id')->limit(150)->pluck('pedido_id')->unique();
        foreach ($abertas as $pedidoId) {
            $pedido = Pedido::withoutGlobalScopes()->whereNotNull('loja_token')->find($pedidoId);
            if (! $pedido) {
                continue;
            }
            $n['cobrancas']++;
            if (! $seco) {
                $pagamento->conferir($pedido);
            }
        }

        $this->info(($seco ? '[seco] ' : '') . "avise-me: {$n['avise']} · lembretes de carrinho: {$n['carrinho']} · cobranças conferidas: {$n['cobrancas']}");

        return self::SUCCESS;
    }
}
