<?php

namespace App\Http\Middleware;

use App\Models\LojaOnline;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Loja online por subdomínio ou domínio próprio (14/11/2026).
 *
 * As rotas da vitrine existem UMA vez, em /loja/{slug}/... Quando o pedido chega
 * por `nome.{dominio_base}` ou pelo domínio do cliente, este middleware acha a
 * loja pelo host e reescreve o caminho para /loja/{slug}/... ANTES do roteador —
 * o resto do sistema nem fica sabendo. Host do ERP (ou host desconhecido) passa
 * intocado.
 *
 * É global e registrado no AppServiceProvider (não em bootstrap/app.php, que só
 * chega em produção com rebuild — armadilha 46).
 */
class ResolverLojaPorHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = mb_strtolower($request->getHost());

        if ($this->hostDoErp($host)) {
            return $next($request);
        }

        $loja = $this->lojaDoHost($host);
        if (! $loja) {
            return $next($request);
        }

        $caminho = '/' . ltrim($request->getPathInfo(), '/');
        $prefixo = '/loja/' . $loja->slug;
        if ($caminho !== $prefixo && ! str_starts_with($caminho, $prefixo . '/')) {
            $query = $request->getQueryString();
            $novo = rtrim($prefixo . $caminho, '/') . ($query !== null && $query !== '' ? '?' . $query : '');

            $request = $request->duplicate(null, null, null, null, null, array_merge($request->server->all(), ['REQUEST_URI' => $novo]));
            app()->instance('request', $request);
        }

        $request->attributes->set('loja_por_host', true);

        return $next($request);
    }

    private function hostDoErp(string $host): bool
    {
        $erp = array_map('mb_strtolower', (array) config('loja_online.hosts_erp', []));
        $app = parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($app) {
            $erp[] = mb_strtolower($app);
        }

        return in_array($host, $erp, true);
    }

    private function lojaDoHost(string $host): ?LojaOnline
    {
        $semWww = preg_replace('/^www\./', '', $host);

        $loja = LojaOnline::whereIn('dominio', array_unique([$host, $semWww]))->where('ativo', true)->first();
        if ($loja) {
            return $loja;
        }

        $base = mb_strtolower(LojaOnline::dominioBase());
        if ($base !== '' && str_ends_with($host, '.' . $base)) {
            $slug = substr($host, 0, -strlen('.' . $base));
            if (preg_match('/^[a-z0-9\-]+$/', $slug)) {
                return LojaOnline::where('slug', $slug)->where('ativo', true)->first();
            }
        }

        return null;
    }
}
