<?php

namespace App\Http\Controllers\App;

use App\Exceptions\CertificadoDigitalException;
use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoFiscal;
use App\Models\Empresa;
use App\Services\FocusNFe\CertificadoDigitalService;
use App\Services\FocusNFe\FocusEmpresaService;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\SefazStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConfiguracaoFiscalController extends Controller
{
    /* ------------------------------------------------------------------ */
    /*  Formulario de edicao                                               */
    /* ------------------------------------------------------------------ */

    public function edit()
    {
        // Admin da plataforma não tem empresa (armadilha 21) — a config fiscal é
        // por unidade da empresa; admin usa /admin/empresas/{id}/saude-focus.
        if (! auth()->user()->empresa_id) {
            return redirect()->route('admin.dashboard')
                ->with('warning', 'O admin da plataforma não tem configuração fiscal própria. Acesse a Saúde Focus na empresa desejada (Admin → Empresas).');
        }

        $config = ConfiguracaoFiscal::firstOrNew([
            'empresa_id' => session('empresa_id'),
            'unidade_id' => session('unidade_id'),
        ]);

        // UF da unidade (ou empresa como fallback) — usada na consulta SEFAZ
        $unidade = \App\Models\Unidade::withoutGlobalScopes()->with('empresa')->find(session('unidade_id'));
        $ufSefaz = strtoupper($unidade?->uf ?: $unidade?->empresa?->uf ?: '');

        // Se a plataforma está em modo revenda E esta unidade foi criada via Focus,
        // escondemos os campos de token (gerenciados automaticamente).
        $modoRevenda = FocusNFeClient::masterDisponivel();
        $gerenciadaPelaFocus = $config->isGerenciadaPelaFocus();

        // Checklist de prontidão para emissão fiscal — calcula o que está
        // pronto e o que falta para cada tipo de nota.
        $checklist = $this->montarChecklist($config, $modoRevenda);

        return view('app.configuracao-fiscal.edit', compact(
            'config',
            'ufSefaz',
            'modoRevenda',
            'gerenciadaPelaFocus',
            'checklist'
        ));
    }

    /**
     * Lista de pré-requisitos para emitir cada tipo de nota, com status
     * (pronto/falta) — alimenta o widget de prontidão no topo da tela.
     */
    private function montarChecklist(ConfiguracaoFiscal $config, bool $modoRevenda): array
    {
        $temFocus = (bool) $config->focus_empresa_id;
        $temTokens = $config->focus_token_producao || $config->focus_token_homologacao || $config->focus_token;
        $temCert = $config->temCertificadoValido();
        $temRespTec = $config->responsavel_tecnico_cnpj && $config->responsavel_tecnico_nome
            && $config->responsavel_tecnico_email && $config->responsavel_tecnico_telefone;

        // Geral (pré-req de todos os tipos)
        $geral = [
            ['label' => 'Empresa conectada à Focus NFe',     'ok' => $temFocus,   'critico' => true,  'campo' => null],
            ['label' => 'Tokens emitidos (homolog/produção)', 'ok' => $temTokens,  'critico' => true,  'campo' => null],
            ['label' => 'Certificado digital A1 (.pfx)',      'ok' => $temCert,    'critico' => true,  'campo' => 'certificado'],
            ['label' => 'Responsável técnico (NT 2018/003)',  'ok' => $temRespTec, 'critico' => true,  'campo' => 'responsavel_tecnico_cnpj'],
        ];

        $checklist = [];

        if ($config->emite_nfe) {
            $checklist['nfe'] = [
                'titulo' => 'NF-e (modelo 55)',
                'icon' => 'file-earmark-text',
                'cor' => 'primary',
                'itens' => array_merge($geral, [
                    ['label' => 'Série NF-e definida',     'ok' => (bool) $config->serie_nfe,  'critico' => true,  'campo' => 'serie_nfe'],
                ]),
            ];
        }

        if ($config->emite_nfce) {
            $checklist['nfce'] = [
                'titulo' => 'NFC-e (cupom fiscal — PDV)',
                'icon' => 'receipt',
                'cor' => 'success',
                'itens' => array_merge($geral, [
                    ['label' => 'Série NFC-e definida',          'ok' => (bool) $config->serie_nfce,    'critico' => true, 'campo' => 'serie_nfce'],
                    ['label' => 'CSC (Código Segurança SEFAZ)',  'ok' => (bool) $config->csc_nfce,      'critico' => true, 'campo' => 'csc_nfce'],
                    ['label' => 'ID do CSC',                      'ok' => (bool) $config->csc_id_nfce,   'critico' => true, 'campo' => 'csc_id_nfce'],
                ]),
            ];
        }

        if ($config->emite_nfse) {
            $checklist['nfse'] = [
                'titulo' => 'NFS-e (serviços)',
                'icon' => 'briefcase',
                'cor' => 'info',
                'itens' => [
                    // NFS-e Municipal não exige A1 nem resp.técnico (usa login da prefeitura)
                    ['label' => 'Empresa conectada à Focus NFe', 'ok' => $temFocus, 'critico' => true, 'campo' => null],
                    ['label' => 'Tokens emitidos',                'ok' => $temTokens, 'critico' => true, 'campo' => null],
                    ['label' => 'Item da Lista LC 116',           'ok' => (bool) $config->nfse_item_lista_servico, 'critico' => true, 'campo' => 'nfse_item_lista_servico'],
                    ['label' => 'Código tributação municipal',     'ok' => (bool) $config->nfse_codigo_tributacao, 'critico' => false, 'campo' => 'nfse_codigo_tributacao'],
                ],
            ];
        }

        return $checklist;
    }

    /* ------------------------------------------------------------------ */
    /*  Atualizar                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Regime tributário da empresa — configurável pelo próprio dono.
     * Muda o comportamento fiscal inteiro (CST×CSOSN, IBS/CBS automático,
     * presets de alíquota), por isso é restrito ao perfil dono e vem com
     * aviso de impacto na tela.
     */
    public function atualizarRegime(Request $request)
    {
        $user = auth()->user();

        if (! $user->empresa_id) {
            return redirect()->route('admin.dashboard')
                ->with('warning', 'O admin da plataforma não tem empresa própria.');
        }

        // Só o dono da empresa (ou admin da plataforma dentro de uma empresa) muda o regime
        if (($user->perfil?->value ?? '') !== 'dono' && ! $user->is_admin) {
            return back()->with('error', 'Apenas o dono da empresa pode alterar o regime tributário.');
        }

        $validated = $request->validate([
            'regime_tributario' => ['required', 'in:simples_nacional,lucro_presumido,lucro_real'],
        ]);

        $empresa = $user->empresa;
        $anterior = $empresa->regime_tributario?->value;
        $empresa->update(['regime_tributario' => $validated['regime_tributario']]);

        $novo = \App\Enums\RegimeTributario::from($validated['regime_tributario'])->label();

        $aviso = $validated['regime_tributario'] === 'simples_nacional'
            ? 'Notas passam a usar CSOSN e o destaque de IBS/CBS deixa de ser automático (obrigação do Simples começa em 01/2027).'
            : 'Suas notas passam a destacar IBS/CBS automaticamente (obrigatório desde 03/08/2026) e a usar CST em vez de CSOSN. Revise os produtos cadastrados antes do regime anterior.';

        return redirect()->route('app.configuracao-fiscal.edit')
            ->with('success', "Regime tributário alterado para {$novo}. {$aviso}")
            ->with('warning', $anterior !== $validated['regime_tributario']
                ? 'Confirme a mudança com o seu contador — o regime altera CST/CSOSN e alíquotas dos produtos.'
                : null);
    }

    public function update(Request $request)
    {
        if (! auth()->user()->empresa_id) {
            return redirect()->route('admin.dashboard')
                ->with('warning', 'O admin da plataforma não tem configuração fiscal própria.');
        }

        $emissaoAtiva = $request->boolean('emissao_fiscal_ativa');

        $rules = [
            'emissao_fiscal_ativa' => 'nullable|boolean',
        ];

        if ($emissaoAtiva) {
            $rules += [
                'ambiente'             => 'required|in:homologacao,producao',
                'focus_token'          => 'nullable|string|max:255',
                'emite_nfe'            => 'nullable|boolean',
                'emite_nfce'           => 'nullable|boolean',
                'emite_nfse'           => 'nullable|boolean',
                'serie_nfe'            => 'nullable|integer|min:1|max:999',
                'serie_nfce'           => 'nullable|integer|min:1|max:999',
                'serie_nfse'           => 'nullable|string|max:10',
                'csc_nfce'             => 'nullable|string|max:255',
                'csc_id_nfce'          => 'nullable|string|max:10',
                'nfse_item_lista_servico'     => 'nullable|string|max:10',
                'nfse_codigo_tributacao'      => 'nullable|string|max:20',
                'nfse_regime_especial'        => 'nullable|string|max:50',
                'nfse_incentivador_cultural'  => 'nullable|boolean',
                'nfse_padrao'                 => 'nullable|in:municipal,nacional',
                // Reforma Tributária
                'ibs_ativo'                   => 'nullable|boolean',
                'cbs_ativo'                   => 'nullable|boolean',
                'is_ativo'                    => 'nullable|boolean',
                'ibs_aliquota_padrao'         => 'nullable|numeric|min:0|max:100',
                'cbs_aliquota_padrao'         => 'nullable|numeric|min:0|max:100',
                // Responsável técnico (NT 2018/003)
                'responsavel_tecnico_cnpj'     => 'nullable|string|max:18',
                'responsavel_tecnico_nome'     => 'nullable|string|max:60',
                'responsavel_tecnico_email'    => 'nullable|email|max:60',
                'responsavel_tecnico_telefone' => 'nullable|string|max:14',
                'tipo_cupom_pdv'       => 'required|in:fiscal,nao_fiscal',
            ];
        }

        $validated = $request->validate($rules);

        $validated['emissao_fiscal_ativa'] = $emissaoAtiva;
        $validated['emite_nfe'] = $request->boolean('emite_nfe');
        $validated['emite_nfce'] = $request->boolean('emite_nfce');
        $validated['emite_nfse'] = $request->boolean('emite_nfse');
        $validated['nfse_incentivador_cultural'] = $request->boolean('nfse_incentivador_cultural');
        $validated['ibs_ativo'] = $request->boolean('ibs_ativo');
        $validated['cbs_ativo'] = $request->boolean('cbs_ativo');
        $validated['is_ativo'] = $request->boolean('is_ativo');

        if (!$emissaoAtiva) {
            $validated['tipo_cupom_pdv'] = $request->input('tipo_cupom_pdv', 'nao_fiscal');
            $validated['ambiente'] = $request->input('ambiente', 'homologacao');
            $validated['emite_nfe'] = false;
            $validated['emite_nfce'] = false;
            $validated['emite_nfse'] = false;
        }

        // Fonte autoritativa: auth()->user(). session só como fallback para
        // unidade_id (admin pode estar em outra unidade). empresa_id sempre
        // vem do usuário autenticado — nunca de session, que pode estar NULL
        // (bug raiz deste 500: session('empresa_id') retornava NULL).
        $user = auth()->user();
        abort_unless($user, 403);

        $empresaId = $user->empresa_id ?? session('empresa_id');
        $unidadeId = session('unidade_id') ?? $user->unidade_id ?? null;

        if (! $empresaId || ! $unidadeId) {
            Log::error('[ConfigFiscal] empresa_id/unidade_id indisponíveis', [
                'user_id' => $user->id,
                'user_empresa_id' => $user->empresa_id,
                'session_empresa_id' => session('empresa_id'),
                'session_unidade_id' => session('unidade_id'),
            ]);
            return back()->with('error', 'Sua sessão perdeu a unidade ativa. Saia e entre novamente.');
        }

        // Re-sincroniza session com os valores confiáveis, evita repetir o bug
        session(['empresa_id' => $empresaId, 'unidade_id' => $unidadeId]);

        $data = collect($validated)->except(['empresa_id', 'unidade_id'])->toArray();

        // Find via DB::table (0 scopes, 0 events, comparação raw)
        $configId = \Illuminate\Support\Facades\DB::table('configuracoes_fiscais')
            ->where('empresa_id', $empresaId)
            ->where('unidade_id', $unidadeId)
            ->value('id');

        // Diff: capturar valores ANTES do save para detectar mudanças que
        // exigem sincronização com a Focus (habilita_*, CSC, ambiente).
        $configAntes = $configId
            ? ConfiguracaoFiscal::withoutGlobalScopes()->findOrFail($configId)
            : null;
        $flagsAntes = $configAntes ? [
            'emite_nfe' => (bool) $configAntes->emite_nfe,
            'emite_nfce' => (bool) $configAntes->emite_nfce,
            'emite_nfse' => (bool) $configAntes->emite_nfse,
            'csc_nfce' => $configAntes->csc_nfce,
            'csc_id_nfce' => $configAntes->csc_id_nfce,
        ] : null;

        if ($configId) {
            $config = $configAntes;
            $config->fill($data)->save();
        } else {
            $config = new ConfiguracaoFiscal();
            $config->empresa_id = $empresaId;
            $config->unidade_id = $unidadeId;
            $config->fill($data);
            $config->save();
        }

        // Auto-sincronização com Focus quando há master token + empresa já provisionada
        $msgExtra = '';
        if (FocusNFeClient::masterDisponivel() && $config->focus_empresa_id) {
            $mudouHabilita = $flagsAntes && (
                $flagsAntes['emite_nfe'] !== (bool) $config->emite_nfe
                || $flagsAntes['emite_nfce'] !== (bool) $config->emite_nfce
                || $flagsAntes['emite_nfse'] !== (bool) $config->emite_nfse
            );
            $mudouCsc = $flagsAntes && (
                $flagsAntes['csc_nfce'] !== $config->csc_nfce
                || $flagsAntes['csc_id_nfce'] !== $config->csc_id_nfce
            );

            if ($mudouHabilita || $mudouCsc || ! $configAntes) {
                try {
                    $svc = FocusEmpresaService::make();
                    $empresa = $user->empresa;
                    $unidade = \App\Models\Unidade::withoutGlobalScopes()->find($unidadeId);

                    $flags = [
                        'habilita_nfe' => (bool) $config->emite_nfe,
                        'habilita_nfce' => (bool) $config->emite_nfce,
                        'habilita_nfse' => (bool) $config->emite_nfse,
                        'habilita_manifestacao' => true,
                    ];

                    $extras = [];
                    if ($mudouCsc && $config->csc_nfce) {
                        $extras = [
                            'csc_nfce_producao' => $config->csc_nfce,
                            'id_token_nfce_producao' => $config->csc_id_nfce,
                            'csc_nfce_homologacao' => $config->csc_nfce,
                            'id_token_nfce_homologacao' => $config->csc_id_nfce,
                        ];
                    }

                    $svc->atualizar($empresa, $unidade, $flags, $extras);

                    // Webhooks só precisam ser recriados se os emite_* mudaram
                    if ($mudouHabilita) {
                        $svc->sincronizarWebhooks($config);
                        $config->webhooks_sincronizados_em = now();
                        $config->save();
                    }
                    $msgExtra = ' Sincronizado com Focus NFe ✓';
                } catch (\Throwable $e) {
                    Log::error('[ConfigFiscal] falha ao sincronizar com Focus', [
                        'config_id' => $config->id,
                        'erro' => $e->getMessage(),
                    ]);
                    $msgExtra = ' (Configuração salva, mas falhou ao sincronizar com Focus: ' . $e->getMessage() . ')';
                }
            }
        } elseif (FocusNFeClient::masterDisponivel() && ! $config->focus_empresa_id) {
            // Ainda não criada na Focus — dispara o job para criar agora.
            \App\Jobs\ProvisionarEmpresaFocusJob::dispatch(
                empresaId: $empresaId,
                unidadeId: $unidadeId,
                flags: [
                    'habilita_nfe' => (bool) $config->emite_nfe,
                    'habilita_nfce' => (bool) $config->emite_nfce,
                    'habilita_nfse' => (bool) $config->emite_nfse,
                    'habilita_manifestacao' => true,
                ],
                solicitadoPor: $user->id,
            );
            $msgExtra = ' Provisionamento na Focus NFe em segundo plano.';
        }

        return redirect()
            ->route('app.configuracao-fiscal.edit')
            ->with('success', 'Configuração fiscal salva com sucesso!' . $msgExtra);
    }

    /* ------------------------------------------------------------------ */
    /*  Testar conexao com Focus NFe                                       */
    /* ------------------------------------------------------------------ */

    public function testarConexao(Request $request)
    {
        $request->validate([
            'token'    => 'required|string',
            'ambiente' => 'required|in:homologacao,producao',
        ]);

        try {
            $client = new FocusNFeClient($request->token, $request->ambiente);
            $response = $client->get('/v2/nfse/provisorio');

            // Focus NFe retorna 403 se o token for invalido
            if ($response->status() === 403) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token invalido ou sem permissao.',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Conexao com Focus NFe realizada com sucesso!',
                'ambiente' => $request->ambiente,
            ]);
        } catch (\Throwable $e) {
            Log::error('Erro ao testar conexao Focus NFe', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao conectar: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Upload do Certificado Digital A1 (.pfx)                            */
    /* ------------------------------------------------------------------ */

    public function uploadCertificado(Request $request)
    {
        $request->validate([
            'certificado'       => 'required|file|mimetypes:application/x-pkcs12,application/octet-stream,application/pkcs12|max:2048',
            'certificado_senha' => 'required|string|min:1|max:255',
        ], [
            'certificado.required' => 'Escolha o arquivo .pfx do certificado A1.',
            'certificado.mimetypes' => 'O arquivo precisa ser um certificado .pfx (PKCS#12).',
            'certificado.max'      => 'O arquivo é grande demais (máximo 2MB). Certificados A1 típicos têm <100KB.',
            'certificado_senha.required' => 'Informe a senha do certificado.',
        ]);

        $empresaId = session('empresa_id');
        $unidadeId = session('unidade_id');

        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('unidade_id', $unidadeId)
            ->first();

        if (! $config || ! $config->tokenFocusAmbienteAtual()) {
            return back()->with('error', 'Informe o token Focus NFe antes de enviar o certificado.');
        }

        $empresa = Empresa::withoutGlobalScopes()->find($empresaId);
        if (! $empresa) {
            return back()->with('error', 'Empresa não encontrada na sessão atual.');
        }

        $file = $request->file('certificado');

        try {
            $service = new CertificadoDigitalService(FocusNFeClient::fromConfig($config));
            $service->enviar(
                $empresa,
                $config,
                file_get_contents($file->getRealPath()),
                $request->input('certificado_senha'),
                $file->getClientOriginalName()
            );
        } catch (CertificadoDigitalException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Erro inesperado no upload do certificado', [
                'empresa_id' => $empresaId,
                'error'      => $e->getMessage(),
            ]);
            return back()->with('error', 'Erro inesperado ao enviar certificado. Nossa equipe foi notificada.');
        }

        return redirect()
            ->route('app.configuracao-fiscal.edit')
            ->with('success', 'Certificado digital enviado com sucesso!');
    }

    /* ------------------------------------------------------------------ */
    /*  Status SEFAZ por UF (AJAX, cache global 2min)                      */
    /* ------------------------------------------------------------------ */

    public function statusSefaz(Request $request)
    {
        $request->validate([
            'uf' => 'required|string|size:2',
        ]);

        $empresaId = session('empresa_id');
        $unidadeId = session('unidade_id');

        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('unidade_id', $unidadeId)
            ->first();

        if (! $config || ! $config->tokenFocusAmbienteAtual()) {
            $mensagem = FocusNFeClient::masterDisponivel()
                ? 'Aguardando provisionamento Focus NFe — salve a configuração para conectar.'
                : 'Token Focus NFe ainda não configurado nesta unidade.';
            return response()->json([
                'situacao' => 'desconhecido',
                'mensagem' => $mensagem,
            ]);
        }

        $service = new SefazStatusService(FocusNFeClient::fromConfig($config));
        $status = $service->consultar($request->input('uf'));

        return response()->json([
            'situacao' => $status['situacao'],
            'mensagem' => $status['mensagem'],
            'tempo_resposta_ms' => $status['tempo_resposta_ms'],
            'consultado_em' => $status['consultado_em']?->format('H:i:s'),
        ]);
    }
}
