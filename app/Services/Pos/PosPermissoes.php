<?php

namespace App\Services\Pos;

use App\Http\Middleware\CheckPermission;
use App\Models\User;

/**
 * O que cada perfil pode fazer no app da maquininha — derivado da MESMA matriz
 * do ERP web (CheckPermission::canUser), para não divergir (armadilha 59).
 *
 * - vender no PDV: vendas,criar (vendedor, caixa, gerente, dono, admin)
 * - emitir pedido: pedidos,criar (o perfil caixa NÃO emite pedido, como na web)
 * - cancelar venda: vendas,excluir (gerente, dono, admin). Vendedor/caixa cancelam
 *   só a PRÓPRIA venda com o caixa da venda ainda aberto (desfazer o erro na hora).
 */
class PosPermissoes
{
    public static function podeUsarApp(User $user): bool
    {
        return self::pode($user, 'vendas', 'criar') || self::pode($user, 'pedidos', 'criar');
    }

    public static function pode(User $user, string $modulo, string $acao): bool
    {
        return $user->is_admin || CheckPermission::canUser($user, $modulo, $acao);
    }

    public static function doUsuario(User $user): array
    {
        return [
            'vender' => self::pode($user, 'vendas', 'criar'),
            'emitir_pedido' => self::pode($user, 'pedidos', 'criar'),
            'faturar_pedido' => self::pode($user, 'pedidos', 'editar') && self::pode($user, 'vendas', 'criar'),
            'cadastrar_cliente' => self::pode($user, 'clientes', 'criar'),
            'cancelar_qualquer_venda' => self::pode($user, 'vendas', 'excluir'),
            'cancelar_propria_venda' => self::pode($user, 'vendas', 'criar'),
            'caixa' => self::pode($user, 'vendas', 'criar'),
        ];
    }
}
