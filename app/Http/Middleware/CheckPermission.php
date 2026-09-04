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
            'gerente' => ['ver', 'criar', 'editar'], // cadastra lojas e edita as vinculadas (Minhas Lojas, 05/08)
            'consulta' => ['ver'],
        ],
        'funcionarios' => [
            'admin' => ['ver', 'criar', 'editar', 'excluir'],
            'dono' => ['ver', 'criar', 'editar', 'excluir'],
            'gerente' => ['ver', 'criar', 'editar', 'excluir'],
            'consulta' => ['ver'],
        ],
        'produtos' => [
            'admin' => ['ver', 'criar', 'editar', 'excluir', 'foto'],
            'dono' => ['ver', 'criar', 'editar', 'excluir', 'foto'],
            'gerente' => ['ver', 'criar', 'editar', 'excluir', 'foto'],
            'vendedor' => ['ver', 'foto'], // troca só a foto do produto, sem abrir preço/fiscal (25/08/2026)
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
            'vendedor' => ['ver', 'criar'],
            'caixa' => ['ver', 'criar'],
            'consulta' => ['ver'],
        ],
        // Trocas, devoluções e vales (03/09/2026). Vendedor e caixa registram
        // a troca no PDV; o que sai da política (prazo vencido, dinheiro) passa
        // pela senha do gerente dentro do próprio fluxo. Cancelar vale é edição.
        'trocas' => [
            'admin' => ['ver', 'criar', 'editar', 'excluir'],
            'dono' => ['ver', 'criar', 'editar', 'excluir'],
            'gerente' => ['ver', 'criar', 'editar'],
            'vendedor' => ['ver', 'criar'],
            'caixa' => ['ver', 'criar'],
            'financeiro' => ['ver'],
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
        // Configurações OPERACIONAIS da loja: juros, parcelas, impressão, textos
        // da OS, estoques (salão/depósito/avaria). É rotina de quem toca a loja —
        // por isso o gerente entra (02/09/2026). Excluir segue fora: quem apaga
        // configuração de loja é o dono.
        'configuracoes' => [
            'admin' => ['ver', 'criar', 'editar', 'excluir'],
            'dono' => ['ver', 'criar', 'editar'],
            'gerente' => ['ver', 'criar', 'editar'],
        ],
        // Configuração FISCAL vive separada de propósito (02/09/2026): certificado
        // A1, token da Focus, CSC, série e regime tributário. Errar aqui derruba a
        // emissão da loja inteira e é a IA365 que configura. Antes compartilhava o
        // módulo 'configuracoes' — liberar aquele para o gerente entregaria isto
        // junto, calado.
        'configuracoes_fiscais' => [
            'admin' => ['ver', 'criar', 'editar', 'excluir'],
            'dono' => ['ver', 'criar', 'editar'],
        ],
        // Tokens da API de integração: leem a empresa INTEIRA, todas as lojas,
        // sem passar pelo escopo de unidade. Mesmo motivo do split acima.
        'integracoes' => [
            'admin' => ['ver', 'criar', 'editar', 'excluir'],
            'dono' => ['ver', 'criar', 'editar'],
        ],
        // Visão consolidada das lojas (faturamento comparado, matriz de estoque).
        // O gerente entra, mas enxergando só as lojas às quais está VINCULADO —
        // o escopo mora no MultilojaController, não aqui.
        'multilojas' => [
            'admin' => ['ver', 'criar', 'editar'],
            'dono' => ['ver', 'criar', 'editar'],
            'gerente' => ['ver', 'criar', 'editar'],
        ],
        'auditoria' => [
            'admin' => ['ver'],
            'dono' => ['ver'],
        ],
    ];

    /**
     * Modo "vendedor só opera o PDV" (04/09/2026, `empresas.vendedor_apenas_pdv`):
     * os únicos módulos que sobram para o perfil `vendedor` quando o cliente liga
     * a chave. `vendas` cobre o PDV, a consulta de vale e abrir/fechar o caixa
     * (todos `permission:vendas,criar`); `trocas` cobre o F6.
     *
     * Isto é o que a MATRIZ passa a responder — e é a matriz que o menu pergunta.
     * A tranca de ROTA (o vendedor digitar /app/produtos na barra de endereço)
     * é do `RestringeVendedorAoPdv`, porque `vendas` e `trocas` também cobrem
     * telas que ficam fora do PDV (/app/vendas, /app/trocas, /app/caixa).
     */
    private const MODULOS_MODO_PDV = ['vendas', 'trocas'];

    public function handle(Request $request, Closure $next, string $modulo, string $acao = ''): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Não autenticado.');
        }

        // Admin da plataforma tem acesso total
        if ($user->is_admin) {
            return $next($request);
        }

        // Sem ação explícita na rota, deriva do verbo HTTP — assim rotas
        // resource com apenas `permission:modulo` exigem a ação certa em
        // POST/PUT/DELETE em vez de liberar tudo com "ver".
        if ($acao === '') {
            $acao = match ($request->method()) {
                'POST' => 'criar',
                'PUT', 'PATCH' => 'editar',
                'DELETE' => 'excluir',
                default => 'ver',
            };
        }

        // Fonte única: o middleware e o menu passam pela MESMA função, para não
        // repetir a regra em dois lugares e divergir (armadilha 59).
        if (! self::canUser($user, $modulo, $acao)) {
            abort(403, 'Você não tem permissão para acessar este recurso.');
        }

        return $next($request);
    }

    /**
     * O usuário está no modo "só PDV"? Só o perfil `vendedor` de uma empresa com
     * a chave ligada. Admin da plataforma nunca (e `?->` protege o `empresa`
     * null dele — armadilha 25).
     */
    public static function modoPdv(?\App\Models\User $user): bool
    {
        if (! $user || $user->is_admin) {
            return false;
        }

        $perfil = $user->perfil instanceof \App\Enums\Perfil ? $user->perfil->value : $user->perfil;

        if ($perfil !== Perfil::Vendedor->value) {
            return false;
        }

        return (bool) $user->empresa?->vendedorApenasPdv();
    }

    /**
     * Igual ao `can()`, mas resolvendo o perfil a partir do usuário e aplicando
     * as restrições que dependem do BANCO (hoje: o modo "só PDV" da empresa).
     * É esta que as views devem chamar — `can()` só conhece a matriz estática.
     */
    public static function canUser(?\App\Models\User $user, string $modulo, string $acao): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        if (self::modoPdv($user) && ! in_array($modulo, self::MODULOS_MODO_PDV, true)) {
            return false;
        }

        $perfil = $user->perfil instanceof \App\Enums\Perfil ? $user->perfil->value : $user->perfil;

        return self::can((string) $perfil, $modulo, $acao);
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
