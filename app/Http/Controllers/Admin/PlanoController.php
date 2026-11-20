<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plano;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanoController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        $planos = Plano::orderBy('ordem')->get();
        $contratacaoSite = \App\Services\Plataforma\ContratacaoSite::ativa();
        $cadastrosSite = \App\Models\Empresa::withoutGlobalScopes()->where('origem_cadastro', 'site')->count();

        return view('admin.planos.index', compact('planos', 'contratacaoSite', 'cadastrosSite'));
    }

    /**
     * Liga/desliga a contratação pelo site (20/11/2026): preços na página inicial e o
     * cadastro de teste grátis em /teste-gratis. Desligada, o site mostra "Sob consulta".
     */
    public function contratacaoSite(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $ligar = $request->boolean('ativa');
        \App\Services\Plataforma\ContratacaoSite::definir($ligar);

        return back()->with('success', $ligar
            ? 'Contratação pelo site LIGADA: a página inicial mostra os preços e o botão de teste grátis.'
            : 'Contratação pelo site DESLIGADA: a página inicial mostra os planos como "Sob consulta".');
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        return view('admin.planos.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate($this->rules());

        if (array_key_exists('open_finance_contas', $validated)) {
            $validated['open_finance_contas'] = (int) ($validated['open_finance_contas'] ?? 0);
        }

        Plano::create($validated);

        return redirect()->route('admin.planos.index')
            ->with('success', 'Plano criado com sucesso.');
    }

    public function edit(Request $request, Plano $plano): View
    {
        abort_unless($request->user()->is_admin, 403);

        return view('admin.planos.edit', compact('plano'));
    }

    public function update(Request $request, Plano $plano): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate($this->rules($plano->id));

        if (array_key_exists('open_finance_contas', $validated)) {
            $validated['open_finance_contas'] = (int) ($validated['open_finance_contas'] ?? 0);
        }

        $plano->update($validated);

        return redirect()->route('admin.planos.index')
            ->with('success', 'Plano atualizado com sucesso.');
    }

    public function destroy(Request $request, Plano $plano): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        if ($plano->empresas()->count() > 0) {
            return back()->with('error', 'Nao e possivel excluir um plano com empresas vinculadas.');
        }

        $plano->delete();

        return redirect()->route('admin.planos.index')
            ->with('success', 'Plano excluido com sucesso.');
    }

    protected function rules(?int $ignoreId = null): array
    {
        return [
            'nome'                   => ['required', 'string', 'max:255'],
            'slug'                   => ['required', 'string', 'max:255', 'unique:planos,slug' . ($ignoreId ? ",{$ignoreId}" : '')],
            'descricao'              => ['nullable', 'string'],
            'preco_mensal'           => ['required', 'numeric', 'min:0'],
            'preco_anual'            => ['required', 'numeric', 'min:0'],
            'max_unidades'           => ['required', 'integer', 'min:1'],
            'max_usuarios'           => ['required', 'integer', 'min:1'],
            'max_produtos'           => ['required', 'integer', 'min:1'],
            'max_notas_mes'          => ['required', 'integer', 'min:1'],
            'pdv_habilitado'         => ['boolean'],
            'fiscal_habilitado'      => ['boolean'],
            'multilojas_habilitado'  => ['boolean'],
            'os_habilitado'          => ['boolean'],
            'contratos_habilitado'   => ['boolean'],
            'conciliacao_habilitada' => ['boolean'],
            'dre_habilitado'         => ['boolean'],
            'boletos_habilitado'     => ['boolean'],
            'api_habilitada'         => ['boolean'],
            'open_finance_habilitado' => ['boolean'],
            'open_finance_contas'    => ['nullable', 'integer', 'min:0', 'max:999'],
            'dias_trial'             => ['required', 'integer', 'min:0'],
            'ativo'                  => ['boolean'],
            'ordem'                  => ['required', 'integer', 'min:0'],
        ];
    }
}
