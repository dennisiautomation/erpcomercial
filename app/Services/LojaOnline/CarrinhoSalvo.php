<?php

namespace App\Services\LojaOnline;

use App\Models\LojaOnline;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Carrinho salvo (15/11/2026) — base do "carrinho abandonado".
 *
 * O carrinho de verdade continua na SESSÃO. Quando o comprador chega ao
 * fechamento e digita e-mail ou telefone, uma FOTO do carrinho é guardada em
 * loja_carrinhos com o contato. Fechou o pedido → a linha recebe o pedido_id e
 * deixa de ser "abandonado". Quem não digitou contato nenhum não é guardado.
 */
class CarrinhoSalvo
{
    public static function chave(LojaOnline $loja): string
    {
        return 'loja_carrinho_token.' . $loja->id;
    }

    /** @param Collection<int, array<string, mixed>> $itens itens do CarrinhoLoja */
    public static function guardar(LojaOnline $loja, Collection $itens, ?string $nome, ?string $email, ?string $telefone): void
    {
        $token = (string) session(self::chave($loja));
        $linha = $token !== '' ? DB::table('loja_carrinhos')->where('empresa_id', $loja->empresa_id)->where('token', $token)->whereNull('pedido_id')->first() : null;

        $dados = [
            'itens' => json_encode($itens->map(fn ($i) => ['id' => $i['id'], 'nome' => $i['nome'], 'quantidade' => $i['quantidade'], 'preco' => $i['preco']])->values()->all()),
            'total' => CarrinhoLoja::subtotal($itens),
            'updated_at' => now(),
        ] + array_filter(['nome' => $nome, 'email' => $email, 'telefone' => $telefone]);

        if ($linha) {
            DB::table('loja_carrinhos')->where('id', $linha->id)->update($dados);

            return;
        }
        $token = Str::lower(Str::random(32));
        DB::table('loja_carrinhos')->insert($dados + ['empresa_id' => $loja->empresa_id, 'token' => $token, 'created_at' => now()]);
        session([self::chave($loja) => $token]);
    }

    /** O pedido foi fechado: o carrinho salvo desta sessão deixa de ser abandonado. */
    public static function fechou(LojaOnline $loja, int $pedidoId): void
    {
        $token = (string) session(self::chave($loja));
        if ($token !== '') {
            DB::table('loja_carrinhos')->where('empresa_id', $loja->empresa_id)->where('token', $token)->whereNull('pedido_id')
                ->update(['pedido_id' => $pedidoId, 'updated_at' => now()]);
            session()->forget(self::chave($loja));
        }
    }
}
