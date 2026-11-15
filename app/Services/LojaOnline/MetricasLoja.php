<?php

namespace App\Services\LojaOnline;

use App\Enums\CanalVenda;
use App\Models\LojaOnline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Números da loja online (15/11/2026): visitas, carrinhos, chegadas ao
 * fechamento e produtos mais vistos — contados por DIA, uma vez por visitante
 * (sessão). Pedidos e faturamento NÃO são contados aqui: saem dos pedidos do ERP.
 * Nada disso identifica pessoa: são só somas por dia.
 */
class MetricasLoja
{
    private const ROBOS = '/bot|crawl|spider|slurp|preview|headless|monitor|curl|wget|python|facebookexternalhit|whatsapp/i';

    public static function humano(Request $request): bool
    {
        $ua = (string) $request->userAgent();

        return $ua !== '' && ! preg_match(self::ROBOS, $ua);
    }

    /** Soma 1 em visitas | carrinhos | fechamentos — uma vez por sessão por dia. */
    public static function contar(LojaOnline $loja, string $campo): void
    {
        if (! in_array($campo, ['visitas', 'carrinhos', 'fechamentos'], true)) {
            return;
        }
        $hoje = now()->toDateString();
        $chave = "loja_m.{$loja->id}.{$campo}";
        if (session($chave) === $hoje) {
            return;
        }
        session([$chave => $hoje]);
        try {
            DB::statement("INSERT INTO loja_visitas (empresa_id, dia, {$campo}) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE {$campo} = {$campo} + 1",
                [$loja->empresa_id, $hoje]);
        } catch (\Throwable) {
            // número de painel nunca derruba a vitrine
        }
    }

    public static function produtoVisto(LojaOnline $loja, int $produtoId): void
    {
        $hoje = now()->toDateString();
        $chave = "loja_m.{$loja->id}.vistos";
        $vistos = (array) session($chave, []);
        if (($vistos['dia'] ?? '') !== $hoje) {
            $vistos = ['dia' => $hoje, 'ids' => []];
        }
        if (in_array($produtoId, $vistos['ids'], true) || count($vistos['ids']) >= 60) {
            return;
        }
        $vistos['ids'][] = $produtoId;
        session([$chave => $vistos]);
        try {
            DB::statement('INSERT INTO loja_produto_vistas (empresa_id, produto_id, dia, vistas) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE vistas = vistas + 1',
                [$loja->empresa_id, $produtoId, $hoje]);
        } catch (\Throwable) {
        }
    }

    /**
     * Resumo do período para o painel.
     *
     * @return array<string, mixed>
     */
    public static function resumo(LojaOnline $loja, int $dias): array
    {
        $desde = now()->subDays($dias - 1)->startOfDay();
        $empresa = (int) $loja->empresa_id;

        $v = DB::table('loja_visitas')->where('empresa_id', $empresa)->where('dia', '>=', $desde->toDateString())
            ->selectRaw('COALESCE(SUM(visitas),0) v, COALESCE(SUM(carrinhos),0) c, COALESCE(SUM(fechamentos),0) f')->first();

        $pedidos = fn () => DB::table('pedidos')->where('empresa_id', $empresa)->where('canal', CanalVenda::Online->value)
            ->whereNotNull('loja_token')->whereNull('deleted_at')->where('created_at', '>=', $desde);
        $pagos = fn () => $pedidos()->whereIn('status', ['confirmado', 'faturado', 'entregue']);

        $totalPedidos = (int) $pedidos()->count();
        $totalPagos = (int) $pagos()->count();
        $faturamento = (float) $pagos()->sum('total');

        $porDia = [];
        for ($d = $desde->copy(); $d->lte(now()); $d->addDay()) {
            $porDia[$d->toDateString()] = ['dia' => $d->format('d/m'), 'visitas' => 0, 'pedidos' => 0, 'valor' => 0.0];
        }
        foreach (DB::table('loja_visitas')->where('empresa_id', $empresa)->where('dia', '>=', $desde->toDateString())->get(['dia', 'visitas']) as $r) {
            if (isset($porDia[$r->dia])) {
                $porDia[$r->dia]['visitas'] = (int) $r->visitas;
            }
        }
        foreach ($pagos()->selectRaw('DATE(created_at) d, COUNT(*) n, SUM(total) t')->groupBy('d')->get() as $r) {
            if (isset($porDia[$r->d])) {
                $porDia[$r->d]['pedidos'] = (int) $r->n;
                $porDia[$r->d]['valor'] = (float) $r->t;
            }
        }

        $maisVendidos = DB::table('pedido_itens as i')->join('pedidos as p', 'p.id', '=', 'i.pedido_id')
            ->where('p.empresa_id', $empresa)->where('p.canal', CanalVenda::Online->value)->whereNotNull('p.loja_token')
            ->whereNull('p.deleted_at')->whereNull('i.deleted_at')->where('p.created_at', '>=', $desde)
            ->whereIn('p.status', ['confirmado', 'faturado', 'entregue'])
            ->groupBy('i.produto_id', 'i.descricao')->selectRaw('i.produto_id, i.descricao, SUM(i.quantidade) qtd, SUM(i.total) total')
            ->orderByDesc('total')->limit(8)->get();

        $maisVistos = DB::table('loja_produto_vistas as v')->join('produtos as pr', 'pr.id', '=', 'v.produto_id')
            ->where('v.empresa_id', $empresa)->where('v.dia', '>=', $desde->toDateString())
            ->groupBy('v.produto_id', 'pr.descricao')->selectRaw('v.produto_id, pr.descricao, SUM(v.vistas) vistas')
            ->orderByDesc('vistas')->limit(8)->get();

        return [
            'dias' => $dias,
            'visitas' => (int) $v->v, 'carrinhos' => (int) $v->c, 'fechamentos' => (int) $v->f,
            'pedidos' => $totalPedidos, 'pagos' => $totalPagos, 'faturamento' => $faturamento,
            'ticket' => $totalPagos > 0 ? round($faturamento / $totalPagos, 2) : 0.0,
            'conversao' => (int) $v->v > 0 ? round($totalPagos / (int) $v->v * 100, 1) : null,
            'porDia' => array_values($porDia),
            'maisVendidos' => $maisVendidos, 'maisVistos' => $maisVistos,
        ];
    }
}
