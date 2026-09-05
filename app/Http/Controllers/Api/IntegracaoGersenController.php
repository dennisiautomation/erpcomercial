<?php

namespace App\Http\Controllers\Api;

use App\Enums\CanalVenda;
use App\Enums\StatusVenda;
use App\Http\Controllers\Controller;
use App\Models\IntegracaoToken;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;
use App\Scopes\EmpresaScope;
use App\Scopes\UnidadeScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * API de Integração v1 — somente leitura, consumida pelo Gersen
 * (app.gersen.com.br) para importar vendas, lojas e vendedores.
 *
 * Escopo de tenant: SEMPRE a empresa do token. Os global scopes
 * EmpresaScope/UnidadeScope dependem de sessão web e são removidos
 * explicitamente, um a um — o SoftDeletingScope FICA (armadilha 38:
 * withoutGlobalScopes() seco incluiria registros soft-deletados).
 *
 * A "data da venda" é created_at, como no resto do sistema (vendas
 * tipo=importada carregam created_at retroativo de propósito).
 */
class IntegracaoGersenController extends Controller
{
    private const VENDAS_POR_PAGINA = 100;
    private const JANELA_MAXIMA_DIAS = 366;

    public function ping(Request $request): JsonResponse
    {
        $empresa = $this->token($request)->empresa;

        return response()->json([
            'ok' => true,
            'versao' => 'v1',
            'empresa' => $empresa->nome_fantasia ?: $empresa->razao_social,
        ]);
    }

    public function lojas(Request $request): JsonResponse
    {
        $lojas = Unidade::withoutGlobalScope(EmpresaScope::class)
            ->where('empresa_id', $this->token($request)->empresa_id)
            ->orderBy('nome')
            ->get(['id', 'nome', 'cnpj', 'cidade', 'uf', 'status']);

        return response()->json([
            'dados' => $lojas->map(fn (Unidade $u) => [
                'id' => (string) $u->id,
                'nome' => $u->nome,
                'cnpj' => $u->cnpj,
                'cidade' => $u->cidade,
                'uf' => $u->uf,
                // unidades.status é FEMININO: ativa/inativa/em_implantacao (armadilha 5)
                'ativo' => $u->status === 'ativa',
            ]),
        ]);
    }

    public function vendedores(Request $request): JsonResponse
    {
        // users não usa SoftDeletes no model — o whereNull cobre o dia em que
        // alguém passar a preencher deleted_at.
        $vendedores = User::withoutGlobalScope(EmpresaScope::class)
            ->where('empresa_id', $this->token($request)->empresa_id)
            ->whereIn('perfil', ['dono', 'gerente', 'vendedor', 'caixa'])
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'status']);

        return response()->json([
            'dados' => $vendedores->map(fn (User $u) => [
                'id' => (string) $u->id,
                'nome' => $u->name,
                'email' => $u->email,
                'ativo' => $u->status === 'ativo',
            ]),
        ]);
    }

    public function situacoes(): JsonResponse
    {
        return response()->json([
            'dados' => array_map(fn (StatusVenda $s) => [
                'id' => $s->value,
                'nome' => $s->label(),
                'conta_como_venda' => $s === StatusVenda::Concluida,
            ], StatusVenda::cases()),
        ]);
    }

    /**
     * Canais de venda que o ERP conhece (05/09/2026) — lista FIXA para a tela
     * de mapeamento do Gersen nascer preenchida antes da 1ª venda.
     */
    public function canais(): JsonResponse
    {
        return response()->json([
            'dados' => array_map(fn (CanalVenda $c) => [
                'id' => $c->value,
                'nome' => $c->label(),
            ], CanalVenda::cases()),
        ]);
    }

    /**
     * Canal da venda para o Gersen. Gravado desde 05/09/2026; para venda
     * anterior à coluna, deriva 'presencial' quando o tipo é pdv/balcao (a
     * venda nasceu no balcão por definição) e devolve NULL para o resto
     * (pedido faturado sem canal, importada) — o Gersen decide pela cascata
     * dele (tipo do vendedor → canal padrão). Nada é gravado aqui.
     */
    private function canalParaGersen(Venda $v): ?string
    {
        if ($v->canal instanceof CanalVenda) {
            return $v->canal->value;
        }

        return in_array($v->tipo, ['pdv', 'balcao'], true) ? CanalVenda::Presencial->value : null;
    }

    public function vendas(Request $request): JsonResponse
    {
        $token = $this->token($request);

        $validated = $request->validate([
            'loja_id' => ['required', 'integer'],
            'inicio' => ['required', 'date_format:Y-m-d'],
            'fim' => ['required', 'date_format:Y-m-d', 'after_or_equal:inicio'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $inicio = Carbon::createFromFormat('Y-m-d', $validated['inicio'])->startOfDay();
        $fim = Carbon::createFromFormat('Y-m-d', $validated['fim'])->endOfDay();

        if ($inicio->diffInDays($fim) > self::JANELA_MAXIMA_DIAS) {
            return response()->json(['erro' => 'Janela máxima de ' . self::JANELA_MAXIMA_DIAS . ' dias.'], 422);
        }

        // A loja tem que ser da empresa do token — 404 idêntico ao inexistente
        // para não enumerar unidades alheias.
        $lojaExiste = Unidade::withoutGlobalScope(EmpresaScope::class)
            ->where('empresa_id', $token->empresa_id)
            ->whereKey($validated['loja_id'])
            ->exists();

        if (! $lojaExiste) {
            return response()->json(['erro' => 'Loja não encontrada.'], 404);
        }

        $pagina = (int) ($validated['pagina'] ?? 1);

        $vendas = Venda::withoutGlobalScope(EmpresaScope::class)
            ->withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', $token->empresa_id)
            ->where('unidade_id', $validated['loja_id'])
            ->whereBetween('created_at', [$inicio, $fim])
            ->withSum('itens as qtde_itens', 'quantidade')
            ->with(['vendedor:id,name', 'cliente:id,nome_razao_social'])
            ->orderBy('id')
            ->skip(($pagina - 1) * self::VENDAS_POR_PAGINA)
            ->take(self::VENDAS_POR_PAGINA + 1)
            ->get();

        $temMais = $vendas->count() > self::VENDAS_POR_PAGINA;

        return response()->json([
            'dados' => $vendas->take(self::VENDAS_POR_PAGINA)->map(fn (Venda $v) => [
                'id' => (string) $v->id,
                'numero' => $v->numero,
                'data' => $v->created_at->format('Y-m-d'),
                'total' => (float) $v->total,
                'vendedor_id' => $v->vendedor_id ? (string) $v->vendedor_id : null,
                'vendedor_nome' => $v->vendedor?->name,
                'cliente_nome' => $v->cliente?->nome_razao_social,
                'forma_pagamento' => $v->forma_pagamento,
                // vendas tipo=importada têm 1 item genérico → conta 1 peça
                'qtde_itens' => (int) round((float) ($v->qtde_itens ?? 0)),
                'situacao' => $v->status->value,
                'situacao_nome' => $v->status->label(),
                'tipo' => $v->tipo,
                // 05/09: canal presencial|whatsapp|online (NULL = desconhecido)
                'canal' => $this->canalParaGersen($v),
            ])->values(),
            'pagina' => $pagina,
            'tem_mais' => $temMais,
        ]);
    }

    private function token(Request $request): IntegracaoToken
    {
        return $request->attributes->get('integracao_token');
    }
}
