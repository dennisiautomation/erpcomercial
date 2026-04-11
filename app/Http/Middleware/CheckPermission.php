<?php

namespace App\Http\Middleware;

use App\Enums\Perfil;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Matriz de permissões por perfil e módulo.
     * Formato: 'modulo' => ['perfil' => ['acao1', 'acao2']]
     */
    private const PERMISSIONS = [
        'empresas' => [
            'admin' => ['ver', 'criar', 'editar', 'excluir'],
        ],
        'unidades' => [
            'admin' => ['ver', 'criar', 'editar', 'excluir'],
            'dono' => ['ver', 'criar', 'editar', 'excluir'],
            'gerente' => ['ver'],
            'consulta' => ['ver'],
        ],
        'funcionarios' => [
            'admin' => ['ver', 'criar', 'editar', 'excluir'],
            'dono' => ['ver', 'criar', 'editar', 'excluir'],
            'gerente' => ['ver', 'criar', 'editar', 'excluir'],
            'consulta' => ['ver'],
        ],
        'produtos' => [
            'admin' => ['ver', 'criar', 'editar', 'excluir'],
            'dono' => ['ver', 'criar', 'editar', 'excluir'],
            'gerente' => ['ver', 'criar', 'editar', 'excluir'],
            'vendedor' => ['ver'],
            'caixa' => ['ver'],
            'consulta' => ['ver'],
        ],
        'clientes' => [
            'admin' => ['ver', 'criar', 'editar', 'excluir'],
            'dono' => ['ver', 'criar', 'editar', 'excluir'],
            'gerente' => ['ver', 'criar', 'editar', 'excluir'],
            'vendedor' => ['ver', 'criar', 'editar', 'excluir'],
            'caixa' => ['ver'],
            'consulta' => ['ver'],
        ],
        'orcamentos' => [
            'admin' => ['ver', 'criar', 'editar', 'excluir'],
            'dono' => ['ver', 'criar', 'editar', 'excluir'],
            'gerente' => ['ver', 'criar', 'editar', 'excluir'],
            'vendedor' => ['ver', 'criar', 'editar', 'excluir'],
            'consulta' => ['ver'],
        ],
        'pedidos' => [
            'admin' => ['ver', 'criar', 'editar', 'excluir'],
            'dono' => ['ver', 'criar', 'editar', 'excluir'],
            'gerente' => ['ver', 'criar', 'editar', 'excluir'],
            'vendedor' => ['ver', 'criar', 'editar', 'excluir'],
            'consulta' => ['ver'],
        ],
        'vendas' => [
            'admin' => ['ver', 'criar', 'editar', 'excluir'],
            'dono' => ['ver', 'criar', 'editar', 'excluir'],
            'gerente' => ['ver', 'criar', 'editar', 'excluir'],
            'caixa' => ['ver', 'criar'],
            'consulta' => ['ver'],
        ],
        'estoque' => [
            'admin' => ['ver', 'criar', 'editar', 'excluir'],
            'dono' => ['ver', 'criar', 'editar', 'excluir'],
            'gerente' => ['ver', 'criar', 'editar', 'excluir'],
            'vendedor' => ['ver'],
            'consulta' => ['ver'],
        ],
        'financeiro' => [
            'admin' => ['ver', 'criar', 'editar', 'excluir'],
            'dono' => ['ver', 'criar', 'editar', 'excluir'],
            'gerente' => ['ver'],
            'financeiro' => ['ver', 'criar', 'editar', 'excluir'],
            'consulta' => ['ver'],
        ],
        'notas_fiscais' => [
            'admin' => ['ver', 'criar', 'editar', 'excluir'],
            'dono' => ['ver', 'criar', 'editar', 'excluir'],
            'gerente' => ['ver', 'criar'],
            'caixa' => ['ver', 'criar'],
            'financeiro' => ['ver'],
            'consulta' => ['ver'],
        ],
        'relatorios' => [
            'admin' => ['ver'],
            'dono' => ['ver'],
            'gerente' => ['ver'],
            'vendedor' => ['ver'],
            'caixa' => ['ver'],
            'financeiro' => ['ver'],
            'consulta' => ['ver'],
        ],
        'configuracoes' => [
            'admin' => ['ver', 'criar', 'editar', 'excluir'],
            'dono' => ['ver', 'criar', 'editar'],
        ],
    ];

    public function handle(Request $request, Closure $next, string $modulo, string $acao = 'ver'): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Não autenticado.');
        }

        // Admin da plataforma tem acesso total
        if ($user->is_admin) {
            return $next($request);
        }

        $perfil = $user->perfil instanceof \App\Enums\Perfil ? $user->perfil->value : $user->perfil;
        $permissions = self::PERMISSIONS[$modulo][$perfil] ?? [];

        if (! in_array($acao, $permissions)) {
            abort(403, 'Você não tem permissão para acessar este recurso.');
        }

        return $next($request);
    }

    /**
     * Verifica se um perfil tem permissão para uma ação em um módulo.
     */
    public static function can(string $perfil, string $modulo, string $acao): bool
    {
        if ($perfil === 'admin') {
            return true;
        }

        $permissions = self::PERMISSIONS[$modulo][$perfil] ?? [];

        return in_array($acao, $permissions);
    }
}
