<?php

namespace App\Services\LojaOnline;

use App\Enums\CanalVenda;
use App\Models\LojaOnline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        if ($campo === 'carrinhos') {
            self::marcar($loja, ['carrinho' => 1], 'c');
        } elseif ($campo === 'fechamentos') {
            self::marcar($loja, ['fechamento' => 1], 'f');
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
        self::marcar($loja, ['viu_produto' => 1], 'p');
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

    /* ───── rastreio por visitante (16/11/2026) ───── */

    /** Rótulos das origens conhecidas; o que não está aqui aparece como veio na campanha. */
    public const ORIGENS = [
        'direto' => 'Direto (digitou ou link salvo)', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'google' => 'Google',
        'whatsapp' => 'WhatsApp', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'bing' => 'Bing', 'email' => 'E-mail', 'outros' => 'Outros sites',
    ];

    public const APARELHOS = ['celular' => 'Celular', 'computador' => 'Computador', 'tablet' => 'Tablet'];

    private static function chaveSessao(LojaOnline $loja): string
    {
        return "loja_s.{$loja->id}";
    }

    /** Sessão de rastreio de hoje, ou null (equipe da loja e robôs nunca têm). */
    private static function sessaoAtual(LojaOnline $loja): ?array
    {
        $s = (array) session(self::chaveSessao($loja), []);

        return ! empty($s['token']) && ($s['dia'] ?? '') === now()->toDateString() ? $s : null;
    }

    /**
     * Abre (ou mantém viva) a linha do visitante em loja_sessoes. Chamada pelo
     * middleware só para gente de fora, em página. A origem é a da PRIMEIRA
     * página do dia; depois disso só o "última vez visto" anda (1× por minuto).
     */
    public static function sessao(LojaOnline $loja, Request $request): void
    {
        try {
            $s = self::sessaoAtual($loja);
            if (! $s) {
                [$origem, $pago, $campanha] = self::origem($request);
                $s = ['dia' => now()->toDateString(), 'token' => Str::random(40), 'toque' => time(), 'f' => []];
                DB::table('loja_sessoes')->insert([
                    'empresa_id' => $loja->empresa_id, 'token' => $s['token'], 'dia' => $s['dia'],
                    'origem' => $origem, 'pago' => $pago, 'campanha' => $campanha, 'aparelho' => self::aparelho($request),
                    'ultima_em' => now(), 'created_at' => now(),
                ]);
                session([self::chaveSessao($loja) => $s]);

                return;
            }
            if (time() - (int) ($s['toque'] ?? 0) >= 60) {
                DB::table('loja_sessoes')->where('token', $s['token'])->update(['ultima_em' => now()]);
                $s['toque'] = time();
                session([self::chaveSessao($loja) => $s]);
            }
        } catch (\Throwable) {
            // número de painel nunca derruba a vitrine
        }
    }

    /** Marca uma etapa na linha do visitante — uma vez por sessão ($marca evita regravar). */
    private static function marcar(LojaOnline $loja, array $dados, string $marca): void
    {
        $s = self::sessaoAtual($loja);
        if (! $s || in_array($marca, (array) ($s['f'] ?? []), true)) {
            return;
        }
        $s['f'][] = $marca;
        $s['toque'] = time();
        session([self::chaveSessao($loja) => $s]);
        try {
            DB::table('loja_sessoes')->where('token', $s['token'])->update($dados + ['ultima_em' => now()]);
        } catch (\Throwable) {
        }
    }

    /** O visitante fechou um pedido: liga o pedido à visita (é o que dá a venda por origem). */
    public static function pedido(LojaOnline $loja, int $pedidoId): void
    {
        self::marcar($loja, ['pedido_id' => $pedidoId, 'carrinho' => 1, 'fechamento' => 1], 'o' . $pedidoId);
    }

    /** Produto posto no carrinho — uma vez por visitante por dia (para os "mais abandonados"). */
    public static function produtoNoCarrinho(LojaOnline $loja, int $produtoId): void
    {
        if (! self::sessaoAtual($loja)) {
            return;
        }
        $hoje = now()->toDateString();
        $chave = "loja_m.{$loja->id}.postos";
        $postos = (array) session($chave, []);
        if (($postos['dia'] ?? '') !== $hoje) {
            $postos = ['dia' => $hoje, 'ids' => []];
        }
        if (in_array($produtoId, $postos['ids'], true) || count($postos['ids']) >= 60) {
            return;
        }
        $postos['ids'][] = $produtoId;
        session([$chave => $postos]);
        try {
            DB::statement('INSERT INTO loja_produto_vistas (empresa_id, produto_id, dia, vistas, carrinhos) VALUES (?, ?, ?, 0, 1) ON DUPLICATE KEY UPDATE carrinhos = carrinhos + 1',
                [$loja->empresa_id, $produtoId, $hoje]);
        } catch (\Throwable) {
        }
    }

    /** Busca feita na loja — uma vez por visitante por termo por dia. */
    public static function buscou(LojaOnline $loja, ?string $termo, bool $achou): void
    {
        $termo = mb_substr(Str::squish(mb_strtolower((string) $termo)), 0, 60);
        if (mb_strlen($termo) < 2 || ! self::sessaoAtual($loja)) {
            return;
        }
        $hoje = now()->toDateString();
        $chave = "loja_m.{$loja->id}.buscas";
        $feitas = (array) session($chave, []);
        if (($feitas['dia'] ?? '') !== $hoje) {
            $feitas = ['dia' => $hoje, 'termos' => []];
        }
        if (in_array($termo, $feitas['termos'], true) || count($feitas['termos']) >= 30) {
            return;
        }
        $feitas['termos'][] = $termo;
        session([$chave => $feitas]);
        try {
            DB::statement('INSERT INTO loja_buscas (empresa_id, dia, termo, vezes, sem_resultado) VALUES (?, ?, ?, 1, ?) ON DUPLICATE KEY UPDATE vezes = vezes + 1, sem_resultado = sem_resultado + VALUES(sem_resultado)',
                [$loja->empresa_id, $hoje, $termo, $achou ? 0 : 1]);
        } catch (\Throwable) {
        }
    }

    /**
     * De onde o visitante veio: [origem, é anúncio pago?, campanha].
     * Vale, nesta ordem: utm_source do link, o clique de anúncio (gclid/ttclid),
     * o site de onde veio (Referer). Sem nada disso, "direto".
     */
    public static function origem(Request $request): array
    {
        $utm = mb_strtolower(trim((string) $request->query('utm_source')));
        $meio = mb_strtolower(trim((string) $request->query('utm_medium')));
        $campanha = mb_substr(trim((string) $request->query('utm_campaign')), 0, 80) ?: null;
        $cliqueGoogle = $request->filled('gclid') || $request->filled('gbraid') || $request->filled('wbraid');
        $pago = $cliqueGoogle || $request->filled('ttclid')
            || in_array($meio, ['cpc', 'ppc', 'paid', 'pago', 'ads', 'anuncio', 'anúncio', 'paid_social', 'paidsocial', 'display'], true);

        $ref = mb_strtolower((string) parse_url((string) $request->headers->get('referer'), PHP_URL_HOST));
        $deFora = $ref !== '' && $ref !== mb_strtolower($request->getHost());

        if ($utm !== '') {
            $origem = self::nomeDaOrigem($utm) ?? (mb_substr(Str::slug($utm), 0, 20) ?: 'outros');
        } elseif ($cliqueGoogle) {
            $origem = 'google';
        } elseif ($request->filled('ttclid')) {
            $origem = 'tiktok';
        } elseif ($deFora) {
            $origem = self::nomeDaOrigem($ref) ?? 'outros';
        } elseif ($request->filled('fbclid')) {
            $origem = 'facebook';
        } else {
            $origem = 'direto';
        }

        return [$origem, $pago, $campanha];
    }

    private static function nomeDaOrigem(string $texto): ?string
    {
        return match (true) {
            str_contains($texto, 'instagram') || $texto === 'ig' => 'instagram',
            str_contains($texto, 'facebook') || str_contains($texto, 'fb.') || in_array($texto, ['fb', 'meta'], true) => 'facebook',
            str_contains($texto, 'whatsapp') || str_contains($texto, 'wa.me') || $texto === 'wa' => 'whatsapp',
            str_contains($texto, 'tiktok') => 'tiktok',
            str_contains($texto, 'youtube') || str_contains($texto, 'youtu.be') => 'youtube',
            str_contains($texto, 'google') => 'google',
            str_contains($texto, 'bing') => 'bing',
            str_contains($texto, 'mail') || $texto === 'newsletter' => 'email',
            default => null,
        };
    }

    public static function aparelho(Request $request): string
    {
        $ua = (string) $request->userAgent();

        return match (true) {
            (bool) preg_match('/ipad|tablet/i', $ua) => 'tablet',
            (bool) preg_match('/mobi|android|iphone/i', $ua) => 'celular',
            default => 'computador',
        };
    }

    /** Quantas pessoas estão com a loja aberta agora (vistas nos últimos 5 minutos). */
    public static function agora(LojaOnline $loja): int
    {
        try {
            return (int) DB::table('loja_sessoes')->where('empresa_id', $loja->empresa_id)->where('ultima_em', '>=', now()->subMinutes(5))->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function rotuloOrigem(string $origem): string
    {
        return self::ORIGENS[$origem] ?? ucfirst(str_replace('-', ' ', $origem));
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

        // ── rastreio por visitante (loja_sessoes, desde 16/11/2026) ──
        $statusPagos = ['confirmado', 'faturado', 'entregue'];
        $sessoes = fn () => DB::table('loja_sessoes as s')->where('s.empresa_id', $empresa)->where('s.dia', '>=', $desde->toDateString());
        $comPedido = fn () => $sessoes()->leftJoin('pedidos as p', function ($j) use ($statusPagos) {
            $j->on('p.id', '=', 's.pedido_id')->whereNull('p.deleted_at')->whereIn('p.status', $statusPagos);
        });

        $f = $comPedido()->selectRaw('COUNT(*) v, COALESCE(SUM(s.viu_produto),0) p, COALESCE(SUM(s.carrinho),0) c, COALESCE(SUM(s.fechamento),0) f,
            COALESCE(SUM(s.pedido_id IS NOT NULL),0) o, COUNT(p.id) g')->first();
        $etapas = [['Entraram na loja', (int) $f->v], ['Viram um produto', (int) $f->p], ['Puseram no carrinho', (int) $f->c],
            ['Começaram a fechar', (int) $f->f], ['Fizeram o pedido', (int) $f->o], ['Pagaram', (int) $f->g]];
        $funil = [];
        foreach ($etapas as $i => [$nome, $n]) {
            $antes = $i > 0 ? $etapas[$i - 1][1] : null;
            $funil[] = ['nome' => $nome, 'n' => $n,
                'do_total' => (int) $f->v > 0 ? round($n / (int) $f->v * 100) : 0,
                'perda' => $antes !== null && $antes > 0 ? max(0, $antes - $n) : null,
                'perda_pct' => $antes !== null && $antes > 0 ? max(0, round(($antes - $n) / $antes * 100)) : null];
        }

        $origens = $comPedido()->groupBy('s.origem')
            ->selectRaw('s.origem, COUNT(*) visitas, COALESCE(SUM(s.pago),0) pagas, COUNT(p.id) pedidos, COALESCE(SUM(p.total),0) valor')
            ->orderByDesc('visitas')->limit(12)->get()
            ->map(fn ($o) => ['origem' => $o->origem, 'rotulo' => self::rotuloOrigem($o->origem), 'visitas' => (int) $o->visitas,
                'pagas' => (int) $o->pagas, 'pedidos' => (int) $o->pedidos, 'valor' => (float) $o->valor,
                'conversao' => (int) $o->visitas > 0 ? round((int) $o->pedidos / (int) $o->visitas * 100, 1) : 0.0])->all();

        $campanhas = $comPedido()->whereNotNull('s.campanha')->groupBy('s.campanha', 's.origem')
            ->selectRaw('s.campanha, s.origem, COUNT(*) visitas, COUNT(p.id) pedidos, COALESCE(SUM(p.total),0) valor')
            ->orderByDesc('visitas')->limit(8)->get()
            ->map(fn ($c) => ['campanha' => $c->campanha, 'rotulo' => self::rotuloOrigem($c->origem), 'visitas' => (int) $c->visitas,
                'pedidos' => (int) $c->pedidos, 'valor' => (float) $c->valor])->all();

        $aparelhos = $comPedido()->groupBy('s.aparelho')
            ->selectRaw('s.aparelho, COUNT(*) visitas, COUNT(p.id) pedidos, COALESCE(SUM(p.total),0) valor')->orderByDesc('visitas')->get()
            ->map(fn ($a) => ['rotulo' => self::APARELHOS[$a->aparelho] ?? ucfirst($a->aparelho), 'visitas' => (int) $a->visitas,
                'pedidos' => (int) $a->pedidos, 'valor' => (float) $a->valor,
                'parte' => (int) $f->v > 0 ? round((int) $a->visitas / (int) $f->v * 100) : 0])->all();

        // Onde moram os compradores: endereço do cadastro do cliente do pedido pago.
        $cidades = DB::table('pedidos as p')->join('clientes as c', 'c.id', '=', 'p.cliente_id')
            ->where('p.empresa_id', $empresa)->where('p.canal', CanalVenda::Online->value)->whereNotNull('p.loja_token')
            ->whereNull('p.deleted_at')->where('p.created_at', '>=', $desde)->whereIn('p.status', $statusPagos)
            ->whereNotNull('c.cidade')->where('c.cidade', '!=', '')
            ->groupBy('c.cidade', 'c.uf')->selectRaw('c.cidade, c.uf, COUNT(*) pedidos, SUM(p.total) valor')
            ->orderByDesc('valor')->limit(8)->get();

        // Clientes novos × que voltaram: voltou quem já tinha pedido pago na loja antes do período, ou comprou 2+ vezes nele.
        $compradores = $pagos()->whereNotNull('cliente_id')->groupBy('cliente_id')->selectRaw('cliente_id, COUNT(*) n')->pluck('n', 'cliente_id');
        $antigos = $compradores->isEmpty() ? collect() : DB::table('pedidos')->where('empresa_id', $empresa)->where('canal', CanalVenda::Online->value)
            ->whereNotNull('loja_token')->whereNull('deleted_at')->whereIn('status', $statusPagos)->where('created_at', '<', $desde)
            ->whereIn('cliente_id', $compradores->keys())->distinct()->pluck('cliente_id')->flip();
        $voltaram = $compradores->filter(fn ($n, $id) => $n > 1 || $antigos->has($id))->count();

        // Mais abandonados: posto no carrinho × comprado (variação conta no produto principal da grade).
        $postos = DB::table('loja_produto_vistas as v')->join('produtos as pr', 'pr.id', '=', 'v.produto_id')
            ->where('v.empresa_id', $empresa)->where('v.dia', '>=', $desde->toDateString())->where('v.carrinhos', '>', 0)
            ->groupBy('v.produto_id', 'pr.descricao')->selectRaw('v.produto_id, pr.descricao, SUM(v.carrinhos) carrinhos')->get();
        $comprados = $postos->isEmpty() ? collect() : DB::table('pedido_itens as i')->join('pedidos as p', 'p.id', '=', 'i.pedido_id')
            ->join('produtos as pr', 'pr.id', '=', 'i.produto_id')
            ->where('p.empresa_id', $empresa)->where('p.canal', CanalVenda::Online->value)->whereNotNull('p.loja_token')
            ->whereNull('p.deleted_at')->whereNull('i.deleted_at')->where('p.created_at', '>=', $desde)->whereIn('p.status', $statusPagos)
            ->groupByRaw('COALESCE(pr.grade_id, pr.id)')->selectRaw('COALESCE(pr.grade_id, pr.id) principal, COUNT(DISTINCT p.id) n')->pluck('n', 'principal');
        $abandonados = $postos->map(fn ($l) => ['descricao' => $l->descricao, 'carrinhos' => (int) $l->carrinhos,
            'comprados' => min((int) $l->carrinhos, (int) ($comprados[$l->produto_id] ?? 0))])
            ->map(fn ($l) => $l + ['abandonos' => $l['carrinhos'] - $l['comprados']])
            ->filter(fn ($l) => $l['abandonos'] > 0)->sortByDesc('abandonos')->take(8)->values()->all();

        $buscas = fn () => DB::table('loja_buscas')->where('empresa_id', $empresa)->where('dia', '>=', $desde->toDateString())
            ->groupBy('termo')->selectRaw('termo, SUM(vezes) vezes, SUM(sem_resultado) sem');

        return [
            'funil' => $funil, 'origens' => $origens, 'campanhas' => $campanhas, 'aparelhos' => $aparelhos,
            'cidades' => $cidades, 'clientesNovos' => $compradores->count() - $voltaram, 'clientesVoltaram' => $voltaram,
            'abandonados' => $abandonados,
            'buscas' => $buscas()->orderByDesc('vezes')->limit(8)->get(),
            'buscasSemResultado' => $buscas()->havingRaw('SUM(sem_resultado) > 0')->orderByDesc('sem')->limit(8)->get(),
            'agora' => self::agora($loja),
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
