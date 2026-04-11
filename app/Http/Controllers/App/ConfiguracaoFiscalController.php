<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoFiscal;
use App\Services\FocusNFe\FocusNFeClient;
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

        return view('app.configuracao-fiscal.edit', compact('config'));
    }

    /* ------------------------------------------------------------------ */
    /*  Atualizar                                                          */
    /* ------------------------------------------------------------------ */

    public function update(Request $request)
    {
        $validated = $request->validate([
            'ambiente'             => 'required|in:homologacao,producao',
            'focus_token'          => 'nullable|string|max:255',
            'serie_nfe'            => 'nullable|integer|min:1|max:999',
            'serie_nfce'           => 'nullable|integer|min:1|max:999',
            'csc_nfce'             => 'nullable|string|max:255',
            'csc_id_nfce'          => 'nullable|string|max:10',
            'emissao_fiscal_ativa' => 'boolean',
            'tipo_cupom_pdv'       => 'required|in:fiscal,nao_fiscal',
        ]);

        $validated['emissao_fiscal_ativa'] = $request->boolean('emissao_fiscal_ativa');

        $config = ConfiguracaoFiscal::updateOrCreate(
            [
                'empresa_id' => session('empresa_id'),
                'unidade_id' => session('unidade_id'),
            ],
            $validated,
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
}
