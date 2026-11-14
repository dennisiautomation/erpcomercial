<?php

namespace App\Services\LojaOnline;

use App\Models\LojaCupom;
use App\Models\LojaOnline;

/**
 * Promoções próprias da loja online (14/11/2026): cupom de desconto e frete grátis
 * acima de um valor. As promoções de PREÇO continuam sendo as do ERP (Promoções por
 * período) — a vitrine só as mostra.
 *
 * O cupom vive na sessão do visitante até fechar o pedido; a conta é sempre
 * refeita no servidor (CheckoutLoja), nunca vem da tela.
 */
class CupomLoja
{
    private static function chave(LojaOnline $loja): string
    {
        return 'loja_cupom.' . $loja->id;
    }

    public static function naSessao(LojaOnline $loja): ?string
    {
        $c = session(self::chave($loja));

        return is_string($c) && $c !== '' ? $c : null;
    }

    public static function guardar(LojaOnline $loja, ?string $codigo): void
    {
        $codigo ? session([self::chave($loja) => $codigo]) : session()->forget(self::chave($loja));
    }

    public static function normalizar(?string $codigo): string
    {
        return mb_strtoupper(preg_replace('/[^A-Za-z0-9\-_]/', '', (string) $codigo));
    }

    /**
     * Confere o cupom contra o subtotal de agora.
     *
     * @return array{ok: bool, mensagem: string, cupom: ?LojaCupom, desconto: float, percentual: float, frete_gratis: bool}
     */
    public static function avaliar(LojaOnline $loja, ?string $codigo, float $subtotal): array
    {
        $nao = fn (string $m) => ['ok' => false, 'mensagem' => $m, 'cupom' => null, 'desconto' => 0.0, 'percentual' => 0.0, 'frete_gratis' => false];
        $codigo = self::normalizar($codigo);
        if ($codigo === '') {
            return $nao('Digite o código do cupom.');
        }

        $cupom = LojaCupom::where('empresa_id', $loja->empresa_id)->where('codigo', $codigo)->first();
        if (! $cupom || ! $cupom->ativo) {
            return $nao('Este cupom não existe.');
        }
        if ($cupom->inicio && $cupom->inicio->isFuture()) {
            return $nao('Este cupom ainda não começou a valer.');
        }
        if ($cupom->fim && $cupom->fim->endOfDay()->isPast()) {
            return $nao('Este cupom venceu.');
        }
        if ($cupom->limite_usos !== null && $cupom->usos >= $cupom->limite_usos) {
            return $nao('Este cupom já foi usado todas as vezes que podia.');
        }
        if ($subtotal < (float) $cupom->minimo) {
            return $nao('Este cupom vale para compras a partir de R$ ' . number_format((float) $cupom->minimo, 2, ',', '.') . '.');
        }

        $desconto = match ($cupom->tipo) {
            'percentual' => round($subtotal * min(100, (float) $cupom->valor) / 100, 2),
            'valor' => min($subtotal, (float) $cupom->valor),
            default => 0.0,
        };

        return [
            'ok' => true, 'mensagem' => 'Cupom ' . $cupom->codigo . ' aplicado: ' . $cupom->descricao() . '.', 'cupom' => $cupom,
            'desconto' => round($desconto, 2),
            'percentual' => $cupom->tipo === 'percentual' ? (float) $cupom->valor : 0.0,
            'frete_gratis' => $cupom->tipo === 'frete_gratis',
        ];
    }

    /** O frete sai de graça? Por cupom ou pela regra "acima de R$ X" (sobre o subtotal já com desconto). */
    public static function freteGratis(LojaOnline $loja, float $subtotalComDesconto, bool $cupomDeFrete = false): bool
    {
        $acima = (float) $loja->opcao('frete_gratis_acima');

        return $cupomDeFrete || ($acima > 0 && $subtotalComDesconto >= $acima);
    }
}
