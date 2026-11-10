<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\PlanoContas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanoContasController extends Controller
{
    public function index()
    {
        $empresaId = auth()->user()->empresa_id;

        $contas = PlanoContas::where('empresa_id', $empresaId)
            ->whereNull('parent_id')
            ->with('children.children.children')
            ->orderBy('codigo')
            ->get();

        return view('app.plano-contas.index', compact('contas'));
    }

    public function create(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $parentId = $request->query('parent_id');

        $contasPai = PlanoContas::where('empresa_id', $empresaId)
            ->where('natureza', 'sintetica')
            ->orderBy('codigo')
            ->get();

        return view('app.plano-contas.create', compact('contasPai', 'parentId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:plano_contas,id',
            'codigo'    => 'required|string|max:20',
            'nome'      => 'required|string|max:255',
            'tipo'      => 'required|in:receita,despesa,custo',
            'natureza'  => 'required|in:sintetica,analitica',
        ]);

        $empresaId = auth()->user()->empresa_id;

        // Check unique code per empresa
        $exists = PlanoContas::where('empresa_id', $empresaId)
            ->where('codigo', $validated['codigo'])
            ->exists();

        if ($exists) {
            return back()->withInput()->withErrors(['codigo' => 'Este codigo ja existe para esta empresa.']);
        }

        PlanoContas::create([
            'empresa_id' => $empresaId,
            'parent_id'  => $validated['parent_id'],
            'codigo'     => $validated['codigo'],
            'nome'       => $validated['nome'],
            'tipo'       => $validated['tipo'],
            'natureza'   => $validated['natureza'],
        ]);

        return redirect()->route('app.plano-contas.index')
            ->with('success', 'Conta cadastrada com sucesso!');
    }

    public function edit(PlanoContas $planoContas)
    {
        $empresaId = auth()->user()->empresa_id;

        $contasPai = PlanoContas::where('empresa_id', $empresaId)
            ->where('natureza', 'sintetica')
            ->where('id', '!=', $planoContas->id)
            ->orderBy('codigo')
            ->get();

        return view('app.plano-contas.edit', compact('planoContas', 'contasPai'));
    }

    public function update(Request $request, PlanoContas $planoContas)
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:plano_contas,id',
            'codigo'    => 'required|string|max:20',
            'nome'      => 'required|string|max:255',
            'tipo'      => 'required|in:receita,despesa,custo',
            'natureza'  => 'required|in:sintetica,analitica',
            'ativo'     => 'nullable|boolean',
        ]);

        $empresaId = auth()->user()->empresa_id;

        // Check unique code per empresa (excluding self)
        $exists = PlanoContas::where('empresa_id', $empresaId)
            ->where('codigo', $validated['codigo'])
            ->where('id', '!=', $planoContas->id)
            ->exists();

        if ($exists) {
            return back()->withInput()->withErrors(['codigo' => 'Este codigo ja existe para esta empresa.']);
        }

        $planoContas->update([
            'parent_id' => $validated['parent_id'],
            'codigo'    => $validated['codigo'],
            'nome'      => $validated['nome'],
            'tipo'      => $validated['tipo'],
            'natureza'  => $validated['natureza'],
            'ativo'     => $request->boolean('ativo', true),
        ]);

        return redirect()->route('app.plano-contas.index')
            ->with('success', 'Conta atualizada com sucesso!');
    }

    /**
     * Plano de contas padrão: [codigo, nome, tipo, natureza]. O pai é o código sem o último nível.
     * Pessoal fica em três grupos (salários, encargos, benefícios) para o DRE mostrar separado.
     */
    public const PADRAO = [
        ['1', 'Receitas', 'receita', 'sintetica'],
        ['1.1', 'Vendas de mercadorias', 'receita', 'analitica'],
        ['1.2', 'Prestação de serviços', 'receita', 'analitica'],
        ['1.9', 'Outras receitas', 'receita', 'analitica'],
        ['2', 'Custos', 'custo', 'sintetica'],
        ['2.1', 'Compra de mercadorias', 'custo', 'analitica'],
        ['2.2', 'Frete sobre compras', 'custo', 'analitica'],
        ['3', 'Despesas', 'despesa', 'sintetica'],
        ['3.1', 'Pessoal — salários', 'despesa', 'sintetica'],
        ['3.1.01', 'Salários', 'despesa', 'analitica'],
        ['3.1.02', 'Adiantamento de salário (vale)', 'despesa', 'analitica'],
        ['3.1.03', '13º salário', 'despesa', 'analitica'],
        ['3.1.04', 'Férias', 'despesa', 'analitica'],
        ['3.1.05', 'Rescisões', 'despesa', 'analitica'],
        ['3.1.06', 'Comissões', 'despesa', 'analitica'],
        ['3.1.07', 'Pró-labore', 'despesa', 'analitica'],
        ['3.2', 'Pessoal — encargos', 'despesa', 'sintetica'],
        ['3.2.01', 'INSS', 'despesa', 'analitica'],
        ['3.2.02', 'FGTS', 'despesa', 'analitica'],
        ['3.2.03', 'IRRF sobre salários', 'despesa', 'analitica'],
        ['3.2.09', 'Outros encargos', 'despesa', 'analitica'],
        ['3.3', 'Pessoal — benefícios', 'despesa', 'sintetica'],
        ['3.3.01', 'Vale-transporte', 'despesa', 'analitica'],
        ['3.3.02', 'Vale-refeição / alimentação', 'despesa', 'analitica'],
        ['3.3.03', 'Plano de saúde', 'despesa', 'analitica'],
        ['3.3.09', 'Outros benefícios', 'despesa', 'analitica'],
        ['3.4', 'Ocupação', 'despesa', 'sintetica'],
        ['3.4.01', 'Aluguel', 'despesa', 'analitica'],
        ['3.4.02', 'Energia', 'despesa', 'analitica'],
        ['3.4.03', 'Água', 'despesa', 'analitica'],
        ['3.4.04', 'Internet e telefone', 'despesa', 'analitica'],
        ['3.5', 'Operacionais', 'despesa', 'sintetica'],
        ['3.5.01', 'Marketing', 'despesa', 'analitica'],
        ['3.5.02', 'Manutenção', 'despesa', 'analitica'],
        ['3.5.03', 'Frete e entregas', 'despesa', 'analitica'],
        ['3.5.04', 'Impostos e taxas', 'despesa', 'analitica'],
        ['3.5.05', 'Serviços de terceiros', 'despesa', 'analitica'],
        ['3.5.09', 'Outras despesas', 'despesa', 'analitica'],
    ];

    /**
     * Cria as contas padrão que ainda não existem (pelo código). Rodar de novo não duplica, e
     * conta que a empresa já criou com o mesmo código fica como está.
     */
    public function padrao()
    {
        $empresaId = auth()->user()->empresa_id;
        if (! $empresaId) {
            return back()->with('error', 'O plano de contas é da empresa — entre no sistema do cliente para criar.');
        }

        $criadas = DB::transaction(function () use ($empresaId) {
            // withTrashed: o único (empresa_id, codigo) do banco conta também as excluídas.
            $ids = PlanoContas::withTrashed()->where('empresa_id', $empresaId)->pluck('id', 'codigo');
            $criadas = 0;

            foreach (self::PADRAO as [$codigo, $nome, $tipo, $natureza]) {
                if ($ids->has($codigo)) {
                    continue;
                }
                $pos = strrpos($codigo, '.');
                $conta = PlanoContas::create([
                    'empresa_id' => $empresaId,
                    'parent_id'  => $pos === false ? null : ($ids[substr($codigo, 0, $pos)] ?? null),
                    'codigo'     => $codigo,
                    'nome'       => $nome,
                    'tipo'       => $tipo,
                    'natureza'   => $natureza,
                    'ativo'      => true,
                ]);
                $ids[$codigo] = $conta->id;
                $criadas++;
            }

            return $criadas;
        });

        return back()->with('success', $criadas > 0
            ? "Plano de contas padrão criado: {$criadas} contas novas (salários, encargos, benefícios e despesas fixas)."
            : 'O plano de contas padrão já estava todo criado — nada foi alterado.');
    }

    public function destroy(PlanoContas $planoContas)
    {
        // Check if has children
        if ($planoContas->children()->exists()) {
            return back()->with('error', 'Nao e possivel excluir conta com subcontas vinculadas.');
        }

        // Check if has movements
        if ($planoContas->contasReceber()->exists() || $planoContas->contasPagar()->exists()) {
            return back()->with('error', 'Nao e possivel excluir conta com movimentacoes vinculadas.');
        }

        $planoContas->delete();

        return redirect()->route('app.plano-contas.index')
            ->with('success', 'Conta excluida com sucesso!');
    }
}
