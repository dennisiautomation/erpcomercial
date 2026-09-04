<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modo "vendedor só opera o PDV" (04/09/2026) — a tranca de ROTA.
 *
 * Esconder item de menu não protege nada (armadilha 57): o vendedor digita
 * /app/relatorios/financeiro na barra de endereço e entra, porque a matriz dá
 * `relatorios => ver` ao perfil. Este middleware é o que fecha a porta.
 *
 * Registrado POR CLASSE no grupo /app, nunca por alias: alias mora em
 * `bootstrap/app.php`, que só chega à produção com rebuild da imagem
 * (armadilha 46) — e esta entrega vai por tar.
 */
class RestringeVendedorAoPdv
{
    /**
     * O que sobra do grupo /app quando a chave está ligada.
     *
     * `app.pdv.*` cobre venda, busca de produto/cliente, estoque, vale e o F6.
     * As 4 rotas de caixa são obrigatórias: o próprio PDV manda o vendedor
     * abrir o caixa quando não há um aberto, e sangria/suprimento são operação
     * de balcão. Histórico de caixas (`app.caixa.index`/`show`) fica de fora —
     * abrir o caixa é operação, ler o extrato é relatório.
     */
    private const ROTAS_LIBERADAS = [
        'app.pdv.*',
        'app.caixa.abrir',
        'app.caixa.fechar',
        'app.caixa.sangria',
        'app.caixa.suprimento',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! CheckPermission::modoPdv($request->user())) {
            return $next($request);
        }

        if ($request->routeIs(...self::ROTAS_LIBERADAS)) {
            return $next($request);
        }

        // Tela: redireciona para o PDV. No balcão, cair no PDV explica melhor
        // "seu acesso é o PDV" do que uma página de 403.
        // AJAX: 403 de verdade — devolver o HTML do PDV para um fetch que
        // espera JSON produz erro de parse, que é pior de diagnosticar.
        if ($request->expectsJson()) {
            abort(403, 'Este usuário opera somente o PDV.');
        }

        return redirect()->route('app.pdv.index');
    }
}
