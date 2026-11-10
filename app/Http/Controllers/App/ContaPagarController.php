<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\CentroCusto;
use App\Models\ContaPagar;
use App\Models\Fornecedor;
use App\Models\PlanoContas;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContaPagarController extends Controller
{
    /** Quantas contas uma recorrência lança de uma vez (os próximos 12 vencimentos). */
    private const GERACOES_RECORRENCIA = 12;

    public function index(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $query = ContaPagar::with(['fornecedor', 'funcionario', 'planoConta', 'centroCusto'])
            ->where('empresa_id', $empresaId);

        if ($request->filled('status')) {
            if ($request->status === 'vencida') {
                // "Vencida" é a pendente com vencimento passado — nada grava status=vencida.
                $query->where('status', 'pendente')->whereDate('vencimento', '<', now()->toDateString());
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('fornecedor_id')) {
            $query->where('fornecedor_id', $request->fornecedor_id);
        }

        if ($request->filled('funcionario_id')) {
            $query->where('funcionario_id', $request->funcionario_id);
        }

        if ($request->filled('plano_conta_id')) {
            $query->where('plano_conta_id', $request->plano_conta_id);
        }

        if ($request->filled('centro_custo_id')) {
            $query->where('centro_custo_id', $request->centro_custo_id);
        }

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        if ($request->filled('vencimento_inicio')) {
            $query->whereDate('vencimento', '>=', $request->vencimento_inicio);
        }

        if ($request->filled('vencimento_fim')) {
            $query->whereDate('vencimento', '<=', $request->vencimento_fim);
        }

        $contas = $query->orderBy('vencimento')->paginate(20)->withQueryString();

        // Totals
        $totalPendente = ContaPagar::where('empresa_id', $empresaId)
            ->where('status', 'pendente')
            ->sum('valor');

        $totalVencido = ContaPagar::where('empresa_id', $empresaId)
            ->where('status', 'pendente')
            ->where('vencimento', '<', now())
            ->sum('valor');

        $pagoMes = ContaPagar::where('empresa_id', $empresaId)
            ->where('status', 'paga')
            ->whereMonth('pago_em', now()->month)
            ->whereYear('pago_em', now()->year)
            ->sum('valor_pago');

        $fornecedores = Fornecedor::where('empresa_id', $empresaId)
            ->orderBy('razao_social')
            ->get(['id', 'razao_social']);

        return view('app.financeiro.contas-pagar.index', array_merge(
            compact('contas', 'totalPendente', 'totalVencido', 'pagoMes', 'fornecedores'),
            $this->listas($empresaId)
        ));
    }

    public function create()
    {
        return view('app.financeiro.contas-pagar.create', $this->listas(auth()->user()->empresa_id));
    }

    public function store(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $validated = $request->validate(
            $this->regras($request, $empresaId) + [
                'parcelas'         => 'nullable|integer|min:1|max:48',
                'recorrente'       => 'nullable|boolean',
                'recorrencia_tipo' => 'nullable|in:mensal,bimestral,trimestral,semestral,anual',
            ],
            $this->mensagens()
        );

        $classificacao = $this->classificacao($validated, $empresaId);
        $favorecido = $this->favorecido($validated);

        DB::transaction(function () use ($validated, $classificacao, $favorecido, $empresaId) {
            $parcelas = (int) ($validated['parcelas'] ?? 1);
            $valorParcela = round($validated['valor'] / $parcelas, 2);
            $vencimento = Carbon::parse($validated['vencimento']);
            $competencia = $this->competencia($validated['competencia'] ?? null);
            $recorrente = (bool) ($validated['recorrente'] ?? false);

            $meses = ! $recorrente ? 1 : match ($validated['recorrencia_tipo'] ?? 'mensal') {
                'bimestral'  => 2,
                'trimestral' => 3,
                'semestral'  => 6,
                'anual'      => 12,
                default      => 1,
            };

            // Recorrente: lança os próximos 12 vencimentos de uma vez (o valor de cada um se ajusta
            // depois, em Editar ou na própria baixa).
            $totalGeracoes = $recorrente ? self::GERACOES_RECORRENCIA : $parcelas;
            $serieId = $totalGeracoes > 1 ? (string) Str::uuid() : null;

            for ($i = 1; $i <= $totalGeracoes; $i++) {
                $valor = (! $recorrente && $i === $parcelas)
                    ? $validated['valor'] - ($valorParcela * ($parcelas - 1))
                    : ($recorrente ? $validated['valor'] : $valorParcela);

                // addMonthsNoOverflow: vencimento no dia 31 vira o último dia do mês curto,
                // em vez de pular para o mês seguinte.
                $passo = ($i - 1) * $meses;

                ContaPagar::create($favorecido + $classificacao + [
                    'empresa_id'       => $empresaId,
                    'unidade_id'       => session('unidade_id'),
                    'descricao'        => $validated['descricao'],
                    'valor'            => $valor,
                    'vencimento'       => $vencimento->copy()->addMonthsNoOverflow($passo),
                    'competencia'      => $competencia?->copy()->addMonthsNoOverflow($passo),
                    'forma_pagamento'  => $validated['forma_pagamento'] ?? null,
                    'parcela'          => $i,
                    'total_parcelas'   => $totalGeracoes,
                    'serie_id'         => $serieId,
                    'recorrente'       => $recorrente,
                    'recorrencia_tipo' => $recorrente ? ($validated['recorrencia_tipo'] ?? 'mensal') : null,
                    'status'           => 'pendente',
                    'observacoes'      => $validated['observacoes'] ?? null,
                ]);
            }
        });

        return redirect()->route('app.contas-pagar.index')
            ->with('success', 'Conta a pagar cadastrada com sucesso!');
    }

    public function show(ContaPagar $contaPagar)
    {
        $contaPagar->load(['fornecedor', 'funcionario', 'planoConta', 'centroCusto']);

        return view('app.financeiro.contas-pagar.show', [
            'contaPagar' => $contaPagar,
            'parcelas'   => $this->serie($contaPagar),
        ]);
    }

    public function edit(ContaPagar $contaPagar)
    {
        if ($contaPagar->status !== 'pendente') {
            return redirect()->route('app.contas-pagar.show', $contaPagar)
                ->with('error', 'Só dá para editar conta pendente. Para corrigir uma conta paga, estorne a baixa primeiro.');
        }

        $contaPagar->load(['fornecedor', 'funcionario']);

        $proximasPendentes = $this->proximasDaSerie($contaPagar)->count();

        return view('app.financeiro.contas-pagar.edit', array_merge(
            compact('contaPagar', 'proximasPendentes'),
            $this->listas($contaPagar->empresa_id)
        ));
    }

    public function update(Request $request, ContaPagar $contaPagar)
    {
        if ($contaPagar->status !== 'pendente') {
            return redirect()->route('app.contas-pagar.show', $contaPagar)
                ->with('error', 'Só dá para editar conta pendente. Para corrigir uma conta paga, estorne a baixa primeiro.');
        }

        $empresaId = $contaPagar->empresa_id;

        $validated = $request->validate(
            $this->regras($request, $empresaId) + ['aplicar_proximas' => 'nullable|boolean'],
            $this->mensagens()
        );

        $classificacao = $this->classificacao($validated, $empresaId);
        $favorecido = $this->favorecido($validated);

        $ajustadas = DB::transaction(function () use ($contaPagar, $validated, $classificacao, $favorecido) {
            $contaPagar->update($favorecido + $classificacao + [
                'descricao'       => $validated['descricao'],
                'valor'           => $validated['valor'],
                'vencimento'      => $validated['vencimento'],
                'competencia'     => $this->competencia($validated['competencia'] ?? null),
                'forma_pagamento' => $validated['forma_pagamento'] ?? null,
                'observacoes'     => $validated['observacoes'] ?? null,
            ]);

            if (empty($validated['aplicar_proximas'])) {
                return 0;
            }

            // Reajuste que vale daqui para a frente (salário novo, aluguel reajustado): leva valor,
            // favorecido, descrição e classificação para as próximas PENDENTES da mesma série.
            // Vencimento e competência de cada uma ficam como estão.
            $proximas = $this->proximasDaSerie($contaPagar)->get();
            foreach ($proximas as $proxima) {
                $proxima->update($favorecido + $classificacao + [
                    'descricao'       => $validated['descricao'],
                    'valor'           => $validated['valor'],
                    'forma_pagamento' => $validated['forma_pagamento'] ?? null,
                ]);
            }

            return $proximas->count();
        });

        $msg = 'Conta atualizada.';
        if ($ajustadas > 0) {
            $msg .= ' O mesmo ajuste foi aplicado em ' . $ajustadas
                . ($ajustadas === 1 ? ' conta pendente seguinte.' : ' contas pendentes seguintes.');
        }

        return redirect()->route('app.contas-pagar.show', $contaPagar)->with('success', $msg);
    }

    /**
     * Baixa com o que foi pago de fato: data, valor e forma. Sem nada no pedido, vale o
     * comportamento antigo (valor cheio, hoje).
     */
    public function baixar(Request $request, ContaPagar $contaPagar)
    {
        if ($contaPagar->status !== 'pendente') {
            return back()->with('error', 'Esta conta não está pendente.');
        }

        $validated = $request->validate([
            'pago_em'         => 'nullable|date|before_or_equal:today',
            'valor_pago'      => 'nullable|numeric|min:0.01|max:9999999999.99',
            'forma_pagamento' => 'nullable|string|max:50',
        ], [
            'pago_em.before_or_equal' => 'A data do pagamento não pode ser no futuro. Para programar, deixe a conta pendente com o vencimento certo.',
            'valor_pago.min'          => 'Informe o valor que foi pago.',
        ]);

        $dados = [
            'valor_pago' => $validated['valor_pago'] ?? $contaPagar->valor,
            'pago_em'    => $validated['pago_em'] ?? now(),
            'status'     => 'paga',
        ];
        if (! empty($validated['forma_pagamento'])) {
            $dados['forma_pagamento'] = $validated['forma_pagamento'];
        }

        $contaPagar->update($dados);

        return back()->with('success', 'Conta marcada como paga!');
    }

    /** Desfaz a baixa (lançada por engano, valor errado): a conta volta a pendente. */
    public function estornar(ContaPagar $contaPagar)
    {
        if ($contaPagar->status !== 'paga') {
            return back()->with('error', 'Só dá para estornar conta que está paga.');
        }

        $contaPagar->update([
            'valor_pago' => 0,
            'pago_em'    => null,
            'status'     => 'pendente',
        ]);

        return back()->with('success', 'Baixa estornada. A conta voltou para pendente.');
    }

    public function destroy(ContaPagar $contaPagar)
    {
        $contaPagar->delete();

        return redirect()->route('app.contas-pagar.index')
            ->with('success', 'Conta excluida com sucesso!');
    }

    /* ------------------------------------------------------------------ */
    /*  Apoio                                                              */
    /* ------------------------------------------------------------------ */

    /** Listas dos formulários e filtros: funcionários, plano de contas lançável e centros de custo. */
    private function listas(int $empresaId): array
    {
        return [
            'funcionarios' => User::where('empresa_id', $empresaId)
                ->where('is_admin', false)
                ->orderBy('name')
                ->get(['id', 'name', 'status']),
            'planoContas' => PlanoContas::where('empresa_id', $empresaId)
                ->analiticas()->ativas()
                ->whereIn('tipo', ['despesa', 'custo'])
                ->with('parent:id,nome')
                ->orderBy('codigo')
                ->get(),
            'centrosCusto' => CentroCusto::where('empresa_id', $empresaId)
                ->where('ativo', true)
                ->orderBy('nome')
                ->get(['id', 'codigo', 'nome']),
        ];
    }

    private function regras(Request $request, int $empresaId): array
    {
        // Tela antiga / integrações mandam só fornecedor_id: sem o tipo, vale "fornecedor".
        $tipo = $request->input('favorecido_tipo', 'fornecedor');

        return [
            'favorecido_tipo' => 'nullable|in:fornecedor,funcionario,outro',
            // Só fornecedor da própria empresa (o exists puro aceitava id de outra loja).
            'fornecedor_id' => [
                Rule::requiredIf($tipo === 'fornecedor'),
                'nullable',
                Rule::exists('fornecedores', 'id')
                    ->where('empresa_id', $empresaId)
                    ->whereNull('deleted_at'),
            ],
            'funcionario_id' => [
                Rule::requiredIf($tipo === 'funcionario'),
                'nullable',
                Rule::exists('users', 'id')->where('empresa_id', $empresaId),
            ],
            'favorecido_nome' => [
                Rule::requiredIf($tipo === 'outro'),
                'nullable', 'string', 'max:150',
            ],
            'descricao'       => 'required|string|max:255',
            'valor'           => 'required|numeric|min:0.01|max:9999999999.99',
            'vencimento'      => 'required|date',
            'competencia'     => ['nullable', 'regex:/^\d{4}-\d{2}(-\d{2})?$/'],
            'categoria'       => 'nullable|string|max:100',
            'centro_custo'    => 'nullable|string|max:100',
            'plano_conta_id'  => [
                'nullable',
                Rule::exists('plano_contas', 'id')
                    ->where('empresa_id', $empresaId)
                    ->where('natureza', 'analitica')
                    ->whereNull('deleted_at'),
            ],
            'centro_custo_id' => [
                'nullable',
                Rule::exists('centros_custo', 'id')
                    ->where('empresa_id', $empresaId)
                    ->whereNull('deleted_at'),
            ],
            'forma_pagamento' => 'nullable|string|max:50',
            'observacoes'     => 'nullable|string|max:500',
        ];
    }

    private function mensagens(): array
    {
        return [
            'fornecedor_id.required'   => 'Escolha o fornecedor na lista. Se ele não aparecer, cadastre o fornecedor primeiro (link abaixo do campo).',
            'fornecedor_id.exists'     => 'Esse fornecedor não foi encontrado nesta empresa. Busque de novo e escolha na lista.',
            'funcionario_id.required'  => 'Escolha o funcionário que vai receber.',
            'funcionario_id.exists'    => 'Esse funcionário não foi encontrado nesta empresa.',
            'favorecido_nome.required' => 'Escreva para quem é o pagamento (ex.: INSS, FGTS, Receita Federal).',
            'plano_conta_id.exists'    => 'Escolha uma conta do plano de contas desta empresa.',
            'centro_custo_id.exists'   => 'Escolha um centro de custo desta empresa.',
            'competencia.regex'        => 'Mês de competência inválido.',
        ];
    }

    /** Só UM favorecido fica gravado: escolher um tipo limpa os outros dois. */
    private function favorecido(array $validated): array
    {
        $tipo = $validated['favorecido_tipo'] ?? 'fornecedor';

        return [
            'fornecedor_id'   => $tipo === 'fornecedor' ? ($validated['fornecedor_id'] ?? null) : null,
            'funcionario_id'  => $tipo === 'funcionario' ? ($validated['funcionario_id'] ?? null) : null,
            'favorecido_nome' => $tipo === 'outro' ? trim((string) ($validated['favorecido_nome'] ?? '')) : null,
        ];
    }

    /**
     * Plano de contas e centro de custo gravam o VÍNCULO (é o que o DRE lê) e também o nome nos
     * campos de texto antigos (`categoria`, `centro_custo`), que a lista, a exportação e as contas
     * antigas continuam usando.
     */
    private function classificacao(array $validated, int $empresaId): array
    {
        $plano = ! empty($validated['plano_conta_id'])
            ? PlanoContas::where('empresa_id', $empresaId)->find($validated['plano_conta_id'])
            : null;
        $centro = ! empty($validated['centro_custo_id'])
            ? CentroCusto::where('empresa_id', $empresaId)->find($validated['centro_custo_id'])
            : null;

        return [
            'plano_conta_id'  => $plano?->id,
            'categoria'       => $plano ? Str::limit($plano->nome, 100, '') : ($validated['categoria'] ?? null),
            'centro_custo_id' => $centro?->id,
            'centro_custo'    => $centro ? Str::limit($centro->nome, 100, '') : ($validated['centro_custo'] ?? null),
        ];
    }

    /** `2026-11` (campo de mês) ou data → 1º dia do mês. */
    private function competencia(?string $valor): ?Carbon
    {
        if (! $valor) {
            return null;
        }

        return Carbon::parse(strlen($valor) === 7 ? $valor . '-01' : $valor)->startOfMonth();
    }

    /** Todas as contas geradas junto com esta (parcelas / recorrência). */
    private function serie(ContaPagar $conta)
    {
        $query = ContaPagar::where('empresa_id', $conta->empresa_id);

        if ($conta->serie_id) {
            $query->where('serie_id', $conta->serie_id);
        } else {
            // Conta sem série (lançada antes de 10/11/2026 e sozinha): casa pelo que identificava.
            $query->where('fornecedor_id', $conta->fornecedor_id)
                ->where('descricao', $conta->descricao)
                ->where('total_parcelas', $conta->total_parcelas);
        }

        return $query->orderBy('parcela')->get();
    }

    /** Pendentes da mesma série que vencem depois desta. */
    private function proximasDaSerie(ContaPagar $conta)
    {
        return ContaPagar::where('empresa_id', $conta->empresa_id)
            ->where('serie_id', $conta->serie_id ?: '-') // sem série não há "próximas"
            ->where('id', '!=', $conta->id)
            ->where('status', 'pendente')
            ->where('parcela', '>', $conta->parcela);
    }
}
