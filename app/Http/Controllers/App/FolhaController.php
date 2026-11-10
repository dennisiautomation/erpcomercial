<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\CentroCusto;
use App\Models\FolhaItem;
use App\Models\FuncionarioFicha;
use App\Models\PlanoContas;
use App\Models\Unidade;
use App\Models\User;
use App\Services\FolhaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Folha de pagamento: guarda salário, vale, guias e benefícios e LANÇA no Contas a Pagar.
 * Quem entra: dono e financeiro (e o admin da plataforma em "acessar como"). O gerente tem
 * `financeiro:ver`, mas salário dos colegas não é assunto dele — por isso a trava é aqui.
 */
class FolhaController extends Controller
{
    private const PERFIS = ['dono', 'financeiro'];

    public function __construct(private FolhaService $folha)
    {
    }

    /** Usado também pelo menu para decidir se mostra o link. */
    public static function podeAcessar(?User $user): bool
    {
        if (! $user || ! $user->empresa_id) {
            return false;
        }
        $perfil = $user->perfil instanceof \BackedEnum ? $user->perfil->value : $user->perfil;

        return (bool) $user->is_admin || in_array($perfil, self::PERFIS, true);
    }

    public function index(Request $request)
    {
        $empresaId = $this->empresa();
        [$inicio, $meses] = $this->periodo($request);

        $fichas = FuncionarioFicha::where('empresa_id', $empresaId)->with('centroCusto', 'unidade')->get()->keyBy('user_id');
        $funcionarios = User::where('empresa_id', $empresaId)->where('is_admin', false)->orderBy('name')->get()
            // inativo sem ficha não interessa; inativo COM ficha aparece para poder tirar da folha
            ->filter(fn ($u) => $u->status === 'ativo' || $fichas->has($u->id));

        $itens = FolhaItem::where('empresa_id', $empresaId)
            ->with(['funcionario', 'planoConta', 'centroCusto'])
            ->orderByDesc('ativo')->orderBy('tipo')->orderBy('nome')->get();

        $resumo = $this->folha->resumo($empresaId, $inicio, $meses);
        $linhasMes = $this->folha->linhas($empresaId, $inicio);

        return view('app.folha.index', compact('funcionarios', 'fichas', 'itens', 'resumo', 'linhasMes', 'inicio', 'meses'));
    }

    public function lancar(Request $request)
    {
        $empresaId = $this->empresa();
        $request->validate([
            'inicio' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'meses'  => 'required|integer|min:1|max:24',
        ]);
        [$inicio, $meses] = $this->periodo($request);

        $r = $this->folha->lancar($empresaId, $inicio, $meses, $request->boolean('atualizar_pendentes'));

        if ($r['criadas'] === 0 && $r['existentes'] === 0) {
            return back()->with('error', 'Nada para lançar: preencha o salário de pelo menos um funcionário ou cadastre uma guia/benefício.');
        }

        $msg = $r['criadas'] > 0
            ? "{$r['criadas']} " . ($r['criadas'] === 1 ? 'conta lançada' : 'contas lançadas') . ' no Contas a Pagar (R$ ' . number_format($r['valor'], 2, ',', '.') . ').'
            : 'Nenhuma conta nova: esse período já estava lançado.';
        if ($r['existentes'] > 0 && $r['criadas'] > 0) {
            $msg .= " {$r['existentes']} já existiam e ficaram como estavam.";
        }
        if ($r['atualizadas'] > 0) {
            $msg .= " {$r['atualizadas']} pendentes tiveram o valor atualizado.";
        }

        return redirect()->route('app.folha.index', ['inicio' => $inicio->format('Y-m'), 'meses' => $meses])
            ->with('success', $msg);
    }

    /* ---------------------------- Ficha ---------------------------- */

    public function fichaEdit(User $funcionario)
    {
        $empresaId = $this->empresa();
        abort_unless((int) $funcionario->empresa_id === $empresaId && ! $funcionario->is_admin, 404);

        $ficha = FuncionarioFicha::where('empresa_id', $empresaId)->where('user_id', $funcionario->id)->first()
            ?? new FuncionarioFicha(['dia_pagamento' => 5, 'vale_dia' => 20, 'paga_mes_seguinte' => true, 'na_folha' => true, 'salario' => 0, 'vale_valor' => 0]);

        return view('app.folha.ficha', array_merge(compact('funcionario', 'ficha'), $this->listas($empresaId)));
    }

    public function fichaUpdate(Request $request, User $funcionario)
    {
        $empresaId = $this->empresa();
        abort_unless((int) $funcionario->empresa_id === $empresaId && ! $funcionario->is_admin, 404);

        $dados = $request->validate([
            'cargo'             => 'nullable|string|max:100',
            'admissao'          => 'nullable|date',
            'demissao'          => 'nullable|date|after_or_equal:admissao',
            'salario'           => 'required|numeric|min:0|max:9999999999.99',
            'dia_pagamento'     => 'required|integer|min:1|max:31',
            'paga_mes_seguinte' => 'nullable|boolean',
            'vale_valor'        => 'nullable|numeric|min:0|lte:salario',
            'vale_dia'          => 'nullable|integer|min:1|max:31',
            'unidade_id'        => ['nullable', Rule::exists('unidades', 'id')->where('empresa_id', $empresaId)],
            'centro_custo_id'   => ['nullable', Rule::exists('centros_custo', 'id')->where('empresa_id', $empresaId)->whereNull('deleted_at')],
            'na_folha'          => 'nullable|boolean',
            'observacoes'       => 'nullable|string|max:500',
        ], [
            'vale_valor.lte'          => 'O vale não pode ser maior que o salário.',
            'demissao.after_or_equal' => 'A data de desligamento não pode ser antes da admissão.',
        ]);

        FuncionarioFicha::updateOrCreate(
            ['user_id' => $funcionario->id],
            [
                'empresa_id'        => $empresaId,
                'cargo'             => $dados['cargo'] ?? null,
                'admissao'          => $dados['admissao'] ?? null,
                'demissao'          => $dados['demissao'] ?? null,
                'salario'           => $dados['salario'],
                'dia_pagamento'     => $dados['dia_pagamento'],
                'paga_mes_seguinte' => $request->boolean('paga_mes_seguinte'),
                'vale_valor'        => $dados['vale_valor'] ?? 0,
                'vale_dia'          => $dados['vale_dia'] ?? 20,
                'unidade_id'        => $dados['unidade_id'] ?? null,
                'centro_custo_id'   => $dados['centro_custo_id'] ?? null,
                'na_folha'          => $request->boolean('na_folha'),
                'observacoes'       => $dados['observacoes'] ?? null,
            ]
        );

        return redirect()->route('app.folha.index')
            ->with('success', "Ficha de {$funcionario->name} salva. As contas já lançadas não mudam sozinhas — lance de novo marcando \"atualizar pendentes\" se o valor mudou.");
    }

    /* ---------------------------- Itens ---------------------------- */

    public function itemCreate()
    {
        $empresaId = $this->empresa();
        $item = new FolhaItem(['tipo' => 'encargo', 'dia_vencimento' => 20, 'vence_mes_seguinte' => true, 'ativo' => true]);

        return view('app.folha.item', array_merge(compact('item'), $this->listas($empresaId)));
    }

    public function itemStore(Request $request)
    {
        $empresaId = $this->empresa();
        FolhaItem::create(['empresa_id' => $empresaId] + $this->dadosItem($request, $empresaId));

        return redirect()->route('app.folha.index')->with('success', 'Item da folha cadastrado.');
    }

    public function itemEdit(FolhaItem $item)
    {
        $empresaId = $this->empresa();
        abort_unless((int) $item->empresa_id === $empresaId, 404);

        return view('app.folha.item', array_merge(compact('item'), $this->listas($empresaId)));
    }

    public function itemUpdate(Request $request, FolhaItem $item)
    {
        $empresaId = $this->empresa();
        abort_unless((int) $item->empresa_id === $empresaId, 404);
        $item->update($this->dadosItem($request, $empresaId));

        return redirect()->route('app.folha.index')
            ->with('success', 'Item atualizado. As contas já lançadas não mudam sozinhas — ajuste no Contas a Pagar ou lance de novo marcando "atualizar pendentes".');
    }

    public function itemDestroy(FolhaItem $item)
    {
        abort_unless((int) $item->empresa_id === $this->empresa(), 404);
        $item->delete();

        return redirect()->route('app.folha.index')
            ->with('success', 'Item excluído. As contas que ele já lançou continuam no Contas a Pagar.');
    }

    /* ---------------------------- Apoio ---------------------------- */

    private function empresa(): int
    {
        abort_unless(self::podeAcessar(auth()->user()), 403, 'A folha de pagamento é restrita ao dono e ao financeiro.');

        return (int) auth()->user()->empresa_id;
    }

    /** @return array{0: Carbon, 1: int} */
    private function periodo(Request $request): array
    {
        $inicio = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('inicio'))
            ? Carbon::parse($request->input('inicio') . '-01')
            : now()->startOfMonth();
        $meses = min(24, max(1, (int) $request->input('meses', 12)));

        return [$inicio->startOfMonth(), $meses];
    }

    private function listas(int $empresaId): array
    {
        return [
            'unidades'     => Unidade::where('empresa_id', $empresaId)->orderBy('nome')->get(['id', 'nome']),
            'centrosCusto' => CentroCusto::where('empresa_id', $empresaId)->where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
            'planoContas'  => PlanoContas::where('empresa_id', $empresaId)->analiticas()->ativas()
                ->where('tipo', 'despesa')->with('parent:id,nome')->orderBy('codigo')->get(),
            'funcionarios' => User::where('empresa_id', $empresaId)->where('is_admin', false)
                ->where('status', 'ativo')->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function dadosItem(Request $request, int $empresaId): array
    {
        $dados = $request->validate([
            'tipo'               => ['required', Rule::in(array_keys(FolhaItem::TIPOS))],
            'nome'               => 'required|string|max:100',
            'favorecido_nome'    => 'nullable|string|max:150',
            'funcionario_id'     => ['nullable', Rule::exists('users', 'id')->where('empresa_id', $empresaId)],
            'valor'              => 'required|numeric|min:0|max:9999999999.99',
            'dia_vencimento'     => 'required|integer|min:1|max:31',
            'vence_mes_seguinte' => 'nullable|boolean',
            'plano_conta_id'     => ['nullable', Rule::exists('plano_contas', 'id')->where('empresa_id', $empresaId)->where('natureza', 'analitica')->whereNull('deleted_at')],
            'centro_custo_id'    => ['nullable', Rule::exists('centros_custo', 'id')->where('empresa_id', $empresaId)->whereNull('deleted_at')],
            'unidade_id'         => ['nullable', Rule::exists('unidades', 'id')->where('empresa_id', $empresaId)],
            'ativo'              => 'nullable|boolean',
        ]);

        return [
            'tipo'               => $dados['tipo'],
            'nome'               => $dados['nome'],
            'favorecido_nome'    => $dados['favorecido_nome'] ?? null,
            'funcionario_id'     => $dados['funcionario_id'] ?? null,
            'valor'              => $dados['valor'],
            'dia_vencimento'     => $dados['dia_vencimento'],
            'vence_mes_seguinte' => $request->boolean('vence_mes_seguinte'),
            'plano_conta_id'     => $dados['plano_conta_id'] ?? null,
            'centro_custo_id'    => $dados['centro_custo_id'] ?? null,
            'unidade_id'         => $dados['unidade_id'] ?? null,
            'ativo'              => $request->boolean('ativo'),
        ];
    }
}
