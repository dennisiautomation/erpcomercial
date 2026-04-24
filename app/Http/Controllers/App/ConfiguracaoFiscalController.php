<?php

namespace App\Http\Controllers\App;

use App\Exceptions\CertificadoDigitalException;
use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoFiscal;
use App\Models\Empresa;
use App\Services\FocusNFe\CertificadoDigitalService;
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

        return view('app.configuracao-fiscal.edit', compact(
            'config',
            'ufSefaz',
            'modoRevenda',
            'gerenciadaPelaFocus'
        ));
    }

    /* ------------------------------------------------------------------ */
    /*  Atualizar                                                          */
    /* ------------------------------------------------------------------ */

    public function update(Request $request)
    {
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

        $empresaId = session('empresa_id');
        $unidadeId = session('unidade_id');
        $data = collect($validated)->except(['empresa_id', 'unidade_id'])->toArray();

        // updateOrCreate — atômico, sem race condition no unique (empresa_id, unidade_id).
        // withoutGlobalScopes garante que achamos o registro mesmo fora do escopo ativo
        // (ex: admin trocando de unidade que ele não "enxerga" pelo UnidadeScope).
        $config = ConfiguracaoFiscal::withoutGlobalScopes()->updateOrCreate(
            [
                'empresa_id' => $empresaId,
                'unidade_id' => $unidadeId,
            ],
            $data,
        );

        return redirect()
            ->route('app.configuracao-fiscal.edit')
            ->with('success', 'Configuracao fiscal salva com sucesso!');
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
            return response()->json([
                'situacao' => 'desconhecido',
                'mensagem' => 'Token Focus NFe não configurado.',
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
