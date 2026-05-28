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

        if ($regime = $request->input('regime_cobranca')) {
            if ($regime === 'gratuitas') {
                $query->whereIn('regime_cobranca', ['cortesia', 'parceiro', 'pos_pago']);
            } else {
                $query->where('regime_cobranca', $regime);
            }
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
            'regime_cobranca'   => ['nullable', 'in:padrao,cortesia,parceiro,pos_pago'],
            'cortesia_motivo'   => ['nullable', 'string', 'max:255', 'required_unless:regime_cobranca,padrao'],
            'cortesia_concedida_em' => ['nullable', 'date', 'required_unless:regime_cobranca,padrao'],
            'cortesia_revisar_em' => [
                'nullable', 'date',
                'after_or_equal:today',
                'after_or_equal:cortesia_concedida_em',
            ],
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

        // Auditar quem concedeu a cortesia
        if (! empty($validated['regime_cobranca'])
            && $validated['regime_cobranca'] !== 'padrao'
            && $empresa->regime_cobranca?->value !== $validated['regime_cobranca']) {
            $validated['cortesia_concedida_por'] = $request->user()->id;
            if (empty($validated['cortesia_concedida_em'])) {
                $validated['cortesia_concedida_em'] = now()->format('Y-m-d');
            }
        }
        // Limpar campos de cortesia ao voltar para padrão
        if (($validated['regime_cobranca'] ?? null) === 'padrao') {
            $validated['cortesia_motivo'] = null;
            $validated['cortesia_concedida_em'] = null;
            $validated['cortesia_revisar_em'] = null;
            $validated['cortesia_concedida_por'] = null;
        }

        $empresa->update($validated);

        // Sincroniza com Focus para todas unidades já provisionadas
        // (mudança de endereço/IE/IM/razão_social/regime → afeta o cadastro
        // na Focus). Despacha jobs em background para não travar o save.
        if (FocusNFeClient::masterDisponivel()) {
            $configs = \App\Models\ConfiguracaoFiscal::withoutGlobalScopes()
                ->where('empresa_id', $empresa->id)
                ->whereNotNull('focus_empresa_id')
                ->get();
            foreach ($configs as $cfg) {
                ProvisionarEmpresaFocusJob::dispatch(
                    empresaId: $empresa->id,
                    unidadeId: $cfg->unidade_id,
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

        return redirect()
            ->route('admin.empresas.show', $empresa)
            ->with('success', 'Empresa atualizada com sucesso.');
    }

    /**
     * Estende o trial de uma empresa em N dias (default 30).
     * Útil para o admin dar "mais um tempo" sem mexer no regime de cobrança.
     */
    public function estenderTrial(Request $request, Empresa $empresa): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $dias = (int) $request->input('dias', 30);
        if ($dias <= 0 || $dias > 365) {
            return back()->with('error', 'Período inválido (1-365 dias).');
        }

        try {
            $empresa->estenderTrial($dias);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $fim = $empresa->assinatura_fim?->isFuture()
            ? $empresa->assinatura_fim
            : $empresa->trial_fim;
        $tipo = $empresa->assinatura_fim?->isFuture() ? 'assinatura' : 'trial';

        return back()->with('success',
            ucfirst($tipo) . " estendida em {$dias} dia(s). Novo término: "
            . $fim->format('d/m/Y') . '.'
        );
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
