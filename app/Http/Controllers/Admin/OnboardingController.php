<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Perfil;
use App\Enums\RegimeTributario;
use App\Enums\StatusEmpresa;
use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoFiscal;
use App\Models\Empresa;
use App\Models\Plano;
use App\Models\Unidade;
use App\Models\User;
use App\Services\FocusNFe\FocusEmpresaService;
use App\Services\FocusNFe\FocusNFeClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    /* ------------------------------------------------------------------ */
    /*  Step 1 — Empresa                                                   */
    /* ------------------------------------------------------------------ */

    public function step1(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        $regimes = RegimeTributario::cases();
        $planos = Plano::ativo()->orderBy('ordem')->get();

        return view('admin.onboarding.step1', compact('regimes', 'planos'));
    }

    public function storeStep1(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'cnpj'              => ['required', 'string', 'size:18', 'unique:empresas,cnpj'],
            'razao_social'      => ['required', 'string', 'max:255'],
            'nome_fantasia'     => ['nullable', 'string', 'max:255'],
            'regime_tributario' => ['required', 'string'],
            // Campos NOT NULL no schema — todos required para bater com o banco
            'cep'               => ['required', 'string', 'max:10'],
            'logradouro'        => ['required', 'string', 'max:255'],
            'numero'            => ['required', 'string', 'max:20'],
            'complemento'       => ['nullable', 'string', 'max:100'],
            'bairro'            => ['required', 'string', 'max:100'],
            'cidade'            => ['required', 'string', 'max:100'],
            'uf'                => ['required', 'string', 'size:2'],
            'telefone'          => ['required', 'string', 'max:20'],
            'email'             => ['required', 'email', 'max:255'],
            'plano_id'          => ['required', 'exists:planos,id'],
        ], [
            'cnpj.required' => 'Informe o CNPJ da empresa.',
            'cnpj.size' => 'CNPJ deve ter 18 caracteres (com máscara: 00.000.000/0000-00).',
            'cnpj.unique' => 'Já existe uma empresa cadastrada com este CNPJ.',
            'razao_social.required' => 'Informe a razão social.',
            'regime_tributario.required' => 'Selecione o regime tributário.',
            'cep.required' => 'Informe o CEP.',
            'logradouro.required' => 'Informe o logradouro (rua/avenida).',
            'numero.required' => 'Informe o número do endereço.',
            'bairro.required' => 'Informe o bairro.',
            'cidade.required' => 'Informe a cidade.',
            'uf.required' => 'Selecione o estado (UF).',
            'telefone.required' => 'Informe um telefone para contato.',
            'email.required' => 'Informe um email para contato. Ele será usado para comunicação e emissão fiscal.',
            'email.email' => 'O email informado não é válido.',
            'plano_id.required' => 'Selecione o plano da empresa.',
            'plano_id.exists' => 'O plano selecionado não existe.',
        ]);

        $plano = Plano::findOrFail($validated['plano_id']);

        $empresa = Empresa::create(array_merge($validated, [
            'status'    => StatusEmpresa::Ativo,
            'plano'     => $plano->slug,
            'em_trial'  => true,
            'trial_inicio' => now(),
            'trial_fim'    => now()->addDays($plano->dias_trial ?? 14),
        ]));

        $request->session()->put('onboarding_empresa_id', $empresa->id);

        return redirect()->route('admin.onboarding.step2');
    }

    /* ------------------------------------------------------------------ */
    /*  Step 2 — Unidade                                                   */
    /* ------------------------------------------------------------------ */

    public function step2(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        $empresaId = $request->session()->get('onboarding_empresa_id');
        abort_unless($empresaId, 404, 'Inicie o onboarding pela etapa 1.');

        $empresa = Empresa::findOrFail($empresaId);

        return view('admin.onboarding.step2', compact('empresa'));
    }

    public function storeStep2(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $empresaId = $request->session()->get('onboarding_empresa_id');
        abort_unless($empresaId, 404);

        $validated = $request->validate([
            'nome'        => ['required', 'string', 'max:255'],
            // Todos os campos abaixo são NOT NULL em unidades
            'cnpj'        => ['required', 'string', 'max:18'],
            'cep'         => ['required', 'string', 'max:10'],
            'logradouro'  => ['required', 'string', 'max:255'],
            'numero'      => ['required', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:100'],
            'bairro'      => ['required', 'string', 'max:100'],
            'cidade'      => ['required', 'string', 'max:100'],
            'uf'          => ['required', 'string', 'size:2'],
            'telefone'    => ['required', 'string', 'max:20'],
        ], [
            'nome.required'       => 'Informe o nome da unidade (ex: Matriz, Filial Centro).',
            'cnpj.required'       => 'Informe o CNPJ da unidade. Use o mesmo da empresa se ela não tem CNPJ próprio.',
            'cep.required'        => 'Informe o CEP da unidade.',
            'logradouro.required' => 'Informe o logradouro.',
            'numero.required'     => 'Informe o número.',
            'bairro.required'     => 'Informe o bairro.',
            'cidade.required'     => 'Informe a cidade.',
            'uf.required'         => 'Selecione o estado (UF).',
            'telefone.required'   => 'Informe um telefone de contato.',
        ]);

        Unidade::withoutGlobalScopes()->create(array_merge($validated, [
            'empresa_id' => $empresaId,
            'status'     => 'ativa',
        ]));

        return redirect()->route('admin.onboarding.step3');
    }

    /* ------------------------------------------------------------------ */
    /*  Step 3 — Usuario Dono                                              */
    /* ------------------------------------------------------------------ */

    public function step3(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        $empresaId = $request->session()->get('onboarding_empresa_id');
        abort_unless($empresaId, 404, 'Inicie o onboarding pela etapa 1.');

        return view('admin.onboarding.step3');
    }

    public function storeStep3(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $empresaId = $request->session()->get('onboarding_empresa_id');
        abort_unless($empresaId, 404);

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'cpf'      => ['nullable', 'string', 'max:14'],
            'telefone' => ['nullable', 'string', 'max:20'],
        ]);

        DB::transaction(function () use ($validated, $empresaId) {
            $user = User::withoutGlobalScopes()->create([
                'name'       => $validated['name'],
                'email'      => $validated['email'],
                'password'   => Hash::make($validated['password']),
                'cpf'        => $validated['cpf'] ?? null,
                'telefone'   => $validated['telefone'] ?? null,
                'empresa_id' => $empresaId,
                'perfil'     => Perfil::Dono,
                'status'     => 'ativo',
            ]);

            // Link user to all empresa unidades
            $unidadeIds = Unidade::withoutGlobalScopes()
                ->where('empresa_id', $empresaId)
                ->pluck('id');

            $user->unidades()->sync($unidadeIds);
        });

        return redirect()->route('admin.onboarding.step4');
    }

    /* ------------------------------------------------------------------ */
    /*  Step 4 — Configuracao Fiscal (optional)                            */
    /* ------------------------------------------------------------------ */

    public function step4(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        $empresaId = $request->session()->get('onboarding_empresa_id');
        abort_unless($empresaId, 404, 'Inicie o onboarding pela etapa 1.');

        // Plataforma em modo revenda → cria empresa-filha automaticamente na Focus.
        // Sem master → fluxo legado (usuário cola token manualmente).
        $modoRevenda = FocusNFeClient::masterDisponivel();

        return view('admin.onboarding.step4', compact('modoRevenda'));
    }

    public function storeStep4(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $empresaId = $request->session()->get('onboarding_empresa_id');
        abort_unless($empresaId, 404);

        $emiteFiscal = $request->input('emite_fiscal') === '1';

        if (! $emiteFiscal) {
            return $this->finalizarOnboarding($request, $empresaId);
        }

        $modoRevenda = FocusNFeClient::masterDisponivel();

        if ($modoRevenda) {
            $this->configurarEmRevenda($request, $empresaId);
        } else {
            $this->configurarTokenManual($request, $empresaId);
        }

        return $this->finalizarOnboarding($request, $empresaId);
    }

    /**
     * Modo revenda: usa master token para criar a empresa na Focus automaticamente.
     * Usuário só escolhe o que vai emitir (NFe/NFCe/NFSe/manifestação).
     */
    private function configurarEmRevenda(Request $request, int $empresaId): void
    {
        $request->validate([
            'tipo_cupom_pdv' => ['required', 'in:fiscal,nao_fiscal'],
            'emite_nfe'      => ['nullable', 'boolean'],
            'emite_nfce'     => ['nullable', 'boolean'],
            'emite_nfse'     => ['nullable', 'boolean'],
            'habilita_manifestacao' => ['nullable', 'boolean'],
        ]);

        $flags = [
            'habilita_nfe'          => $request->boolean('emite_nfe'),
            'habilita_nfce'         => $request->boolean('emite_nfce'),
            'habilita_nfse'         => $request->boolean('emite_nfse'),
            'habilita_manifestacao' => $request->boolean('habilita_manifestacao'),
        ];

        // Se nada foi marcado, respeita (cria config com fiscal ativo mas sem tipos — usuário ajusta depois)
        if (! array_filter($flags)) {
            ConfiguracaoFiscal::withoutGlobalScopes()->updateOrCreate(
                ['empresa_id' => $empresaId, 'unidade_id' => Unidade::withoutGlobalScopes()->where('empresa_id', $empresaId)->value('id')],
                ['emissao_fiscal_ativa' => true, 'tipo_cupom_pdv' => $request->input('tipo_cupom_pdv')]
            );
            return;
        }

        $empresa = Empresa::findOrFail($empresaId);
        $unidades = Unidade::withoutGlobalScopes()->where('empresa_id', $empresaId)->get();

        $service = FocusEmpresaService::make();

        foreach ($unidades as $unidade) {
            try {
                $service->criar($empresa, $unidade, $flags);

                // Completa campos locais específicos do onboarding
                ConfiguracaoFiscal::withoutGlobalScopes()
                    ->where('empresa_id', $empresaId)
                    ->where('unidade_id', $unidade->id)
                    ->update(['tipo_cupom_pdv' => $request->input('tipo_cupom_pdv')]);
            } catch (\Throwable $e) {
                Log::error('[Onboarding] falha ao criar empresa na Focus', [
                    'empresa_id' => $empresaId,
                    'unidade_id' => $unidade->id,
                    'erro' => $e->getMessage(),
                ]);
                // Não aborta o onboarding: empresa existe localmente, admin pode retentar depois
                session()->flash(
                    'onboarding_fiscal_erro',
                    'Empresa criada no ERP, mas falhou ao criar na Focus NFe: ' . $e->getMessage()
                    . ' Você pode retentar em Configurações Fiscais.'
                );
            }
        }
    }

    /**
     * Modo legado: sem master token, usuário cola token manualmente.
     */
    private function configurarTokenManual(Request $request, int $empresaId): void
    {
        $request->validate([
            'focus_token'   => ['required', 'string', 'max:255'],
            'ambiente'      => ['required', 'in:homologacao,producao'],
            'tipo_cupom_pdv' => ['required', 'in:fiscal,nao_fiscal'],
        ]);

        $ambiente = $request->input('ambiente');
        $tokenCampo = $ambiente === 'producao' ? 'focus_token_producao' : 'focus_token_homologacao';

        $unidades = Unidade::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->get();

        foreach ($unidades as $unidade) {
            ConfiguracaoFiscal::withoutGlobalScopes()->updateOrCreate(
                ['empresa_id' => $empresaId, 'unidade_id' => $unidade->id],
                [
                    'focus_token'          => $request->input('focus_token'),
                    $tokenCampo            => $request->input('focus_token'),
                    'ambiente'             => $ambiente,
                    'tipo_cupom_pdv'       => $request->input('tipo_cupom_pdv'),
                    'emissao_fiscal_ativa' => true,
                ]
            );
        }
    }

    private function finalizarOnboarding(Request $request, int $empresaId): RedirectResponse
    {
        $empresa = Empresa::findOrFail($empresaId);
        $request->session()->forget('onboarding_empresa_id');
        return redirect()->route('admin.onboarding.concluido', $empresa);
    }

    /* ------------------------------------------------------------------ */
    /*  Concluido                                                          */
    /* ------------------------------------------------------------------ */

    public function concluido(Request $request, Empresa $empresa): View
    {
        abort_unless($request->user()->is_admin, 403);

        $empresa->load('unidades', 'users');

        return view('admin.onboarding.concluido', compact('empresa'));
    }
}
