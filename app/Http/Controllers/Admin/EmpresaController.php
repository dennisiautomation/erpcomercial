<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RegimeTributario;
use App\Enums\StatusEmpresa;
use App\Http\Controllers\Controller;
use App\Jobs\ProvisionarEmpresaFocusJob;
use App\Models\Empresa;
use App\Models\Plano;
use App\Services\FocusNFe\FocusNFeClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmpresaController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        $query = Empresa::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('razao_social', 'like', "%{$search}%")
                  ->orWhere('nome_fantasia', 'like', "%{$search}%")
                  ->orWhere('cnpj', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($plano = $request->input('plano')) {
            $query->where('plano', $plano);
        }

        $empresas = $query->withCount('unidades', 'users')
            ->orderBy('razao_social')
            ->paginate(15)
            ->withQueryString();

        $statusOptions = StatusEmpresa::cases();
        $planos = Plano::ativo()->orderBy('ordem')->get();

        return view('admin.empresas.index', compact('empresas', 'statusOptions', 'planos'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        $regimes = RegimeTributario::cases();
        $statusOptions = StatusEmpresa::cases();
        $planos = Plano::ativo()->orderBy('ordem')->get();

        return view('admin.empresas.create', compact('regimes', 'statusOptions', 'planos'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'cnpj'              => ['required', 'string', 'size:18', 'unique:empresas,cnpj'],
            'razao_social'      => ['required', 'string', 'max:255'],
            'nome_fantasia'     => ['nullable', 'string', 'max:255'],
            'ie'                => ['nullable', 'string', 'max:20'],
            'im'                => ['nullable', 'string', 'max:20'],
            'regime_tributario' => ['required', 'string'],
            'cep'               => ['nullable', 'string', 'max:10'],
            'logradouro'        => ['nullable', 'string', 'max:255'],
            'numero'            => ['nullable', 'string', 'max:20'],
            'complemento'       => ['nullable', 'string', 'max:100'],
            'bairro'            => ['nullable', 'string', 'max:100'],
            'cidade'            => ['nullable', 'string', 'max:100'],
            'uf'                => ['nullable', 'string', 'size:2'],
            'telefone'          => ['nullable', 'string', 'max:20'],
            'email'             => ['nullable', 'email', 'max:255'],
            'plano'             => ['nullable', 'string', 'max:50'],
            'status'            => ['required', 'string'],
            'observacoes'       => ['nullable', 'string'],
        ]);

        $empresa = Empresa::create($validated);

        // Auto-provisionar na Focus NFe se houver token master.
        // Despacha job para cada unidade já criada (em CRUD comum, normalmente nenhuma ainda
        // — a primeira chamada efetiva acontecerá no UnidadeController::store).
        if (FocusNFeClient::masterDisponivel()) {
            foreach ($empresa->unidades as $unidade) {
                ProvisionarEmpresaFocusJob::dispatch(
                    empresaId: $empresa->id,
                    unidadeId: $unidade->id,
                    flags: ['habilita_nfe' => true, 'habilita_nfce' => true, 'habilita_manifestacao' => true],
                    solicitadoPor: $request->user()->id,
                );
            }
        }

        return redirect()
            ->route('admin.empresas.show', $empresa)
            ->with('success', 'Empresa cadastrada com sucesso.'
                . (FocusNFeClient::masterDisponivel()
                    ? ' Provisionamento Focus NFe em segundo plano — você será notificado.'
                    : ''));
    }

    public function show(Request $request, Empresa $empresa): View
    {
        abort_unless($request->user()->is_admin, 403);

        $empresa->load('unidades', 'users');

        return view('admin.empresas.show', compact('empresa'));
    }

    public function edit(Request $request, Empresa $empresa): View
    {
        abort_unless($request->user()->is_admin, 403);

        $regimes = RegimeTributario::cases();
        $statusOptions = StatusEmpresa::cases();
        $planos = Plano::ativo()->orderBy('ordem')->get();

        return view('admin.empresas.edit', compact('empresa', 'regimes', 'statusOptions', 'planos'));
    }

    public function update(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'cnpj'              => ['required', 'string', 'size:18', 'unique:empresas,cnpj,' . $empresa->id],
            'razao_social'      => ['required', 'string', 'max:255'],
            'nome_fantasia'     => ['nullable', 'string', 'max:255'],
            'ie'                => ['nullable', 'string', 'max:20'],
            'im'                => ['nullable', 'string', 'max:20'],
            'regime_tributario' => ['required', 'string'],
            'politica_estoque_inter_unidade' => ['nullable', 'in:silos,ver_apenas,ver_e_vender'],
            'cep'               => ['nullable', 'string', 'max:10'],
            'logradouro'        => ['nullable', 'string', 'max:255'],
            'numero'            => ['nullable', 'string', 'max:20'],
            'complemento'       => ['nullable', 'string', 'max:100'],
            'bairro'            => ['nullable', 'string', 'max:100'],
            'cidade'            => ['nullable', 'string', 'max:100'],
            'uf'                => ['nullable', 'string', 'size:2'],
            'telefone'          => ['nullable', 'string', 'max:20'],
            'email'             => ['nullable', 'email', 'max:255'],
            'plano'             => ['nullable', 'string', 'max:50'],
            'status'            => ['required', 'string'],
            'observacoes'       => ['nullable', 'string'],
        ]);

        $empresa->update($validated);

        return redirect()
            ->route('admin.empresas.show', $empresa)
            ->with('success', 'Empresa atualizada com sucesso.');
    }

    public function destroy(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $empresa->delete(); // SoftDeletes

        return redirect()
            ->route('admin.empresas.index')
            ->with('success', 'Empresa removida com sucesso.');
    }
}
