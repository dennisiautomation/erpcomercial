<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Estoque;
use App\Models\Unidade;
use App\Services\SaldoEstoque;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Estoques da loja (salão, depósito, avaria, vitrine).
 *
 * Toda loja nasce com o "Principal" criado na migration. Quem não cadastrar um
 * segundo estoque não vê diferença em tela nenhuma — por isso isto mora dentro
 * de Configurações da Loja e não vira item de menu.
 */
class EstoqueController extends Controller
{
    public function index()
    {
        if (! $unidadeId = $this->unidadeAtual()) {
            return $this->semLoja();
        }

        $estoques = Estoque::withoutGlobalScopes()
            ->where('unidade_id', $unidadeId)
            ->withCount('movimentacoes')
            ->orderByDesc('is_padrao')
            ->orderBy('nome')
            ->get();

        return view('app.estoque.locais.index', compact('estoques'));
    }

    public function create()
    {
        if (! $this->unidadeAtual()) {
            return $this->semLoja();
        }

        return view('app.estoque.locais.form', ['estoque' => new Estoque()]);
    }

    public function store(Request $request)
    {
        if (! $unidadeId = $this->unidadeAtual()) {
            return $this->semLoja();
        }

        $validated = $this->validar($request, $unidadeId);

        // A empresa vem da LOJA, não do usuário: o admin da plataforma tem
        // empresa_id NULL e criar por ele estourava (armadilha 25).
        $empresaId = Unidade::withoutGlobalScopes()->where('id', $unidadeId)->value('empresa_id');

        DB::transaction(function () use ($validated, $unidadeId, $empresaId) {
            $estoque = Estoque::create([
                'empresa_id'    => $empresaId,
                'unidade_id'    => $unidadeId,
                'nome'          => $validated['nome'],
                'codigo'        => $validated['codigo'] ?? null,
                'permite_venda' => (bool) ($validated['permite_venda'] ?? false),
                'is_padrao'     => (bool) ($validated['is_padrao'] ?? false),
                'status'        => $validated['status'],
                'observacoes'   => $validated['observacoes'] ?? null,
            ]);

            $this->garantirExclusividade($estoque);
        });

        return redirect()->route('app.estoques.index')
            ->with('success', 'Estoque criado.');
    }

    public function edit(Estoque $estoque)
    {
        abort_unless($estoque->unidade_id === $this->unidadeAtual(), 403);

        return view('app.estoque.locais.form', compact('estoque'));
    }

    public function update(Request $request, Estoque $estoque)
    {
        abort_unless($estoque->unidade_id === $this->unidadeAtual(), 403);

        $validated = $this->validar($request, $estoque->unidade_id, $estoque->id);

        // Não dá para deixar a loja sem lugar para vender.
        $ehUnicoDeVenda = $estoque->permite_venda
            && ! Estoque::withoutGlobalScopes()
                ->where('unidade_id', $estoque->unidade_id)
                ->where('id', '!=', $estoque->id)
                ->where('status', 'ativo')
                ->where('permite_venda', true)
                ->exists();

        if ($ehUnicoDeVenda && (! ($validated['permite_venda'] ?? false) || $validated['status'] === 'inativo')) {
            return back()->withInput()->with(
                'error',
                'Este é o único estoque de venda da loja. Marque outro como estoque de venda antes de mudar este.'
            );
        }

        DB::transaction(function () use ($validated, $estoque) {
            $estoque->update([
                'nome'          => $validated['nome'],
                'codigo'        => $validated['codigo'] ?? null,
                'permite_venda' => (bool) ($validated['permite_venda'] ?? false),
                'is_padrao'     => (bool) ($validated['is_padrao'] ?? false),
                'status'        => $validated['status'],
                'observacoes'   => $validated['observacoes'] ?? null,
            ]);

            $this->garantirExclusividade($estoque);
        });

        return redirect()->route('app.estoques.index')
            ->with('success', 'Estoque atualizado.');
    }

    /**
     * Estoque não se exclui: some com saldo e histórico junto. Inativa.
     */
    public function inativar(Estoque $estoque)
    {
        abort_unless($estoque->unidade_id === $this->unidadeAtual(), 403);

        if ($estoque->permite_venda || $estoque->is_padrao) {
            return back()->with('error', 'O estoque de venda/padrão da loja não pode ser inativado. Promova outro antes.');
        }

        $estoque->update(['status' => 'inativo']);

        return redirect()->route('app.estoques.index')
            ->with('success', 'Estoque inativado. O histórico dele continua no extrato.');
    }

    /**
     * Loja ativa da sessão, 0 quando não há nenhuma.
     *
     * O admin da plataforma passa batido pelo EnsureUnidadeSelected, então pode
     * chegar aqui sem loja escolhida — daí o guard em vez de confiar na sessão.
     */
    private function unidadeAtual(): int
    {
        return (int) session('unidade_id');
    }

    private function semLoja()
    {
        return redirect()->route('app.dashboard')
            ->with('error', 'Escolha uma loja no topo da tela antes de mexer nos estoques dela.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, int $unidadeId, ?int $ignorarId = null): array
    {
        return $request->validate([
            'nome' => [
                'required', 'string', 'max:80',
                Rule::unique('estoques', 'nome')
                    ->where(fn ($q) => $q->where('unidade_id', $unidadeId))
                    ->ignore($ignorarId),
            ],
            'codigo'        => 'nullable|string|max:20',
            'permite_venda' => 'nullable|boolean',
            'is_padrao'     => 'nullable|boolean',
            'status'        => 'required|in:ativo,inativo',
            'observacoes'   => 'nullable|string|max:500',
        ], [
            'nome.unique' => 'Esta loja já tem um estoque com esse nome.',
        ]);
    }

    /**
     * Exatamente 1 estoque de venda e 1 padrão por loja — o resto perde a marca.
     */
    private function garantirExclusividade(Estoque $estoque): void
    {
        foreach (['permite_venda', 'is_padrao'] as $flag) {
            if (! $estoque->{$flag}) {
                continue;
            }

            Estoque::withoutGlobalScopes()
                ->where('unidade_id', $estoque->unidade_id)
                ->where('id', '!=', $estoque->id)
                ->update([$flag => false]);
        }

        // Loja nunca pode ficar sem estoque de venda
        $temVenda = Estoque::withoutGlobalScopes()
            ->where('unidade_id', $estoque->unidade_id)
            ->where('status', 'ativo')
            ->where('permite_venda', true)
            ->exists();

        if (! $temVenda) {
            $fallback = SaldoEstoque::estoqueDeVenda($estoque->unidade_id);
            $fallback?->update(['permite_venda' => true]);
        }
    }
}
