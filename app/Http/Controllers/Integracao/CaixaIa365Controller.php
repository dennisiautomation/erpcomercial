<?php

namespace App\Http\Controllers\Integracao;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Services\Plataforma\FaturasPlataforma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O que o caixa.ia365 chama no ERP (16/11/2026) — máquina a máquina, com o
 * segredo CAIXA_IA365_TOKEN no cabeçalho Authorization: Bearer.
 *
 * A cobrança nasce e é baixada no caixa.ia365; aqui só chega o espelho:
 *   GET  empresas  → lista para o campo "Empresa no ERP" do cliente no caixa
 *   POST vinculo   → liga/desliga o cliente do caixa à empresa
 *   POST fatura    → estado de UMA cobrança (aberta | paga | cancelada)
 *   POST conferir  → cobranças vivas por empresa (cancela aqui a que sumiu lá)
 *
 * Sem o segredo configurado, tudo responde 503 — a integração nasce desligada.
 */
class CaixaIa365Controller extends Controller
{
    private function autorizar(Request $request): void
    {
        $segredo = (string) config('services.caixa_ia365.token');
        abort_if($segredo === '', 503, 'Integração com o caixa.ia365 não configurada.');
        abort_unless(hash_equals($segredo, (string) $request->bearerToken()), 401, 'Token inválido.');
    }

    private function empresa(int $id): Empresa
    {
        $empresa = Empresa::withoutGlobalScopes()->find($id);
        abort_unless($empresa, 404, 'Empresa não encontrada no ERP.');

        return $empresa;
    }

    public function empresas(Request $request): JsonResponse
    {
        $this->autorizar($request);

        return response()->json(['empresas' => Empresa::withoutGlobalScopes()->orderBy('razao_social')->get()->map(fn (Empresa $e) => [
            'id' => $e->id,
            'razao_social' => $e->razao_social,
            'nome_fantasia' => $e->nome_fantasia,
            'cnpj' => preg_replace('/\D/', '', (string) $e->cnpj),
            'caixa_cliente_id' => $e->caixa_cliente_id,
            'suspensa' => $e->estaSuspensa(),
        ])->values()]);
    }

    public function vinculo(Request $request): JsonResponse
    {
        $this->autorizar($request);
        $d = $request->validate([
            'empresa_id' => ['required', 'integer'],
            'cliente_id' => ['nullable', 'integer', 'min:1'],
            'cliente_nome' => ['nullable', 'string', 'max:150'],
        ]);
        $empresa = $this->empresa((int) $d['empresa_id']);

        if (empty($d['cliente_id'])) {
            return response()->json(['ok' => true, 'vinculada' => false, 'canceladas' => FaturasPlataforma::desvincular($empresa)]);
        }
        FaturasPlataforma::vincular($empresa, (int) $d['cliente_id'], $d['cliente_nome'] ?? null);

        return response()->json(['ok' => true, 'vinculada' => true]);
    }

    public function fatura(Request $request): JsonResponse
    {
        $this->autorizar($request);
        $d = $request->validate([
            'empresa_id' => ['required', 'integer'],
            'ref' => ['required', 'integer', 'min:1'],
            'situacao' => ['required', 'in:aberta,paga,cancelada'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'vencimento' => ['required', 'date'],
            'descricao' => ['nullable', 'string', 'max:500'],
            'competencia' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'pago_em' => ['nullable', 'date'],
            'link' => ['nullable', 'url', 'max:500'],
            'pix_copia_cola' => ['nullable', 'string', 'max:2000'],
            'linha_digitavel' => ['nullable', 'string', 'max:60'],
            'tem_nota' => ['nullable', 'boolean'],
            'cliente_id' => ['nullable', 'integer'],
            'cliente_nome' => ['nullable', 'string', 'max:150'],
        ]);

        return response()->json(['ok' => true] + FaturasPlataforma::receberDoCaixa($this->empresa((int) $d['empresa_id']), $d));
    }

    public function conferir(Request $request): JsonResponse
    {
        $this->autorizar($request);
        $d = $request->validate([
            'empresas' => ['required', 'array'],
            'empresas.*.empresa_id' => ['required', 'integer'],
            'empresas.*.refs' => ['present', 'array'],
            'empresas.*.refs.*' => ['integer'],
        ]);
        $canceladas = 0;
        foreach ($d['empresas'] as $linha) {
            $empresa = Empresa::withoutGlobalScopes()->find($linha['empresa_id']);
            if ($empresa) {
                $canceladas += FaturasPlataforma::conferirVivas($empresa, $linha['refs']);
            }
        }

        return response()->json(['ok' => true, 'canceladas' => $canceladas]);
    }
}
