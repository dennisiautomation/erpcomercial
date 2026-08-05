<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Admin\UnidadeController as AdminUnidadeController;
use App\Http\Controllers\Controller;
use App\Jobs\ProvisionarEmpresaFocusJob;
use App\Models\ConfiguracaoFiscal;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Models\User;
use App\Services\FocusNFe\FocusNFeClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Minhas Lojas — cadastro de unidades pelo PRÓPRIO cliente (pedido do Dennis
 * 05/08: "tudo no admin fica ruim"). Dono cria/edita todas as lojas da empresa
 * (criação respeita o limite do plano); gerente edita as lojas às quais está
 * vinculado. Excluir loja continua exclusivo do admin da plataforma.
 *
 * Reusa validações do Admin\UnidadeController e o mesmo auto-provisionamento
 * Focus (loja nova com CNPJ de irmã herda a empresa Focus automaticamente).
 */
class LojaController extends Controller
{
    public function index()
    {
        $empresa = $this->empresaDaSessao();
        if (! $empresa) {
            return $this->redirectSemEmpresa();
        }

        $lojas = Unidade::withoutGlobalScopes()
            ->where('empresa_id', $empresa->id)
            ->orderBy('nome')
            ->get();

        $configs = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $empresa->id)
            ->get()
            ->keyBy('unidade_id');

        return view('app.lojas.index', [
            'lojas' => $lojas,
            'configs' => $configs,
            'empresa' => $empresa,
            'limiteAtingido' => $empresa->limiteAtingido('unidades'),
            'podeEditar' => fn (Unidade $loja) => $this->podeEditar($loja),
        ]);
    }

    public function create()
    {
        $empresa = $this->empresaDaSessao();
        if (! $empresa) {
            return $this->redirectSemEmpresa();
        }

        if ($empresa->limiteAtingido('unidades')) {
            return redirect()->route('app.lojas.index')
                ->with('warning', 'Sua empresa atingiu o limite de lojas do plano atual. Fale com a IA365 para ampliar.');
        }

        return view('app.lojas.create', [
            'empresa' => $empresa,
            'gerentes' => $this->gerentes($empresa),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresa = $this->empresaDaSessao();
        if (! $empresa) {
            return $this->redirectSemEmpresa();
        }

        if ($empresa->limiteAtingido('unidades')) {
            return redirect()->route('app.lojas.index')
                ->with('warning', 'Sua empresa atingiu o limite de lojas do plano atual. Fale com a IA365 para ampliar.');
        }

        $validated = $request->validate(
            AdminUnidadeController::validationRules(),
            AdminUnidadeController::validationMessages()
        );

        if ($this->nomeDuplicado($empresa->id, $validated['nome'])) {
            return back()->withInput()
                ->withErrors(['nome' => 'Já existe uma loja com este nome. Use um nome diferente (ex: "Filial Centro").']);
        }

        $validated['empresa_id'] = $empresa->id;
        $loja = Unidade::create($validated);

        if (FocusNFeClient::masterDisponivel()) {
            ProvisionarEmpresaFocusJob::dispatch(
                empresaId: $empresa->id,
                unidadeId: $loja->id,
                flags: [
                    'habilita_nfe' => true,
                    'habilita_nfce' => true,
                    'habilita_nfse' => false,
                    'habilita_manifestacao' => true,
                ],
                solicitadoPor: $request->user()->id,
            );
        }

        return redirect()->route('app.lojas.index')
            ->with('success', 'Loja cadastrada! O vínculo fiscal (Focus NFe) está sendo preparado automaticamente'
                . ' — lojas com o mesmo CNPJ compartilham certificado, CSC e numeração.');
    }

    public function edit(Unidade $loja)
    {
        $empresa = $this->empresaDaSessao();
        if (! $empresa) {
            return $this->redirectSemEmpresa();
        }

        abort_unless($loja->empresa_id === $empresa->id, 404);
        abort_unless($this->podeEditar($loja), 403, 'Você só pode editar as lojas às quais está vinculado.');

        return view('app.lojas.edit', [
            'loja' => $loja,
            'empresa' => $empresa,
            'gerentes' => $this->gerentes($empresa),
        ]);
    }

    public function update(Request $request, Unidade $loja): RedirectResponse
    {
        $empresa = $this->empresaDaSessao();
        if (! $empresa) {
            return $this->redirectSemEmpresa();
        }

        abort_unless($loja->empresa_id === $empresa->id, 404);
        abort_unless($this->podeEditar($loja), 403, 'Você só pode editar as lojas às quais está vinculado.');

        $validated = $request->validate(
            AdminUnidadeController::validationRules(),
            AdminUnidadeController::validationMessages()
        );

        if ($this->nomeDuplicado($empresa->id, $validated['nome'], $loja->id)) {
            return back()->withInput()
                ->withErrors(['nome' => 'Já existe outra loja com este nome. Use um nome diferente.']);
        }

        $loja->update($validated);

        // Endereço/IE/CNPJ mudaram? Propaga para a Focus (mesmo fluxo do admin)
        if (FocusNFeClient::masterDisponivel()) {
            $cfg = ConfiguracaoFiscal::withoutGlobalScopes()
                ->where('empresa_id', $empresa->id)
                ->where('unidade_id', $loja->id)
                ->whereNotNull('focus_empresa_id')
                ->first();
            if ($cfg) {
                ProvisionarEmpresaFocusJob::dispatch(
                    empresaId: $empresa->id,
                    unidadeId: $loja->id,
                    flags: [
                        'habilita_nfe' => (bool) $cfg->emite_nfe,
                        'habilita_nfce' => (bool) $cfg->emite_nfce,
                        'habilita_nfse' => (bool) $cfg->emite_nfse,
                        'habilita_manifestacao' => true,
                    ],
                    solicitadoPor: $request->user()->id,
                );
            }
        }

        return redirect()->route('app.lojas.index')
            ->with('success', "Loja \"{$loja->nome}\" atualizada.");
    }

    // ─── Helpers ────────────────────────────────────────────

    private function empresaDaSessao(): ?Empresa
    {
        return session('empresa_id') ? Empresa::find(session('empresa_id')) : null;
    }

    private function redirectSemEmpresa(): RedirectResponse
    {
        // Admin da plataforma gerencia lojas em Admin → Empresas (armadilha 25)
        return redirect()->route('admin.dashboard')
            ->with('warning', 'Lojas das empresas são gerenciadas em Admin → Empresas → Unidades.');
    }

    /**
     * Dono (e admin da empresa) editam qualquer loja; gerente só as lojas às
     * quais está vinculado (ou a loja da sessão, se não houver vínculo).
     */
    private function podeEditar(Unidade $loja): bool
    {
        $user = auth()->user();
        $perfil = $user->perfil instanceof \App\Enums\Perfil ? $user->perfil->value : $user->perfil;

        if ($user->is_admin || in_array($perfil, ['admin', 'dono'])) {
            return true;
        }

        $vinculadas = $user->unidades()->pluck('unidades.id');

        return $vinculadas->isNotEmpty()
            ? $vinculadas->contains($loja->id)
            : (int) session('unidade_id') === $loja->id;
    }

    private function nomeDuplicado(int $empresaId, string $nome, ?int $ignorarId = null): bool
    {
        return Unidade::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->whereRaw('LOWER(nome) = ?', [mb_strtolower(trim($nome))])
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->exists();
    }

    private function gerentes(Empresa $empresa)
    {
        return User::where('empresa_id', $empresa->id)
            ->where('status', 'ativo')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
