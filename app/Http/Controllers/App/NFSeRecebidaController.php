<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoFiscal;
use App\Models\Empresa;
use App\Models\NFSeRecebida;
use App\Models\Unidade;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\NFSesRecebidasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NFSeRecebidaController extends Controller
{
    public function index(Request $request)
    {
        $query = NFSeRecebida::query()->latest('data_emissao');

        if ($prestador = $request->input('prestador')) {
            $query->where(function ($q) use ($prestador) {
                $q->where('nome_prestador', 'like', "%{$prestador}%")
                  ->orWhere('cnpj_prestador', 'like', '%' . preg_replace('/\D/', '', $prestador) . '%');
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($desde = $request->input('desde')) {
            $query->where('data_emissao', '>=', $desde);
        }
        if ($ate = $request->input('ate')) {
            $query->where('data_emissao', '<=', $ate);
        }

        $notas = $query->paginate(25)->withQueryString();

        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', session('empresa_id'))
            ->where('unidade_id', session('unidade_id'))
            ->first();

        return view('app.nfses-recebidas.index', [
            'notas' => $notas,
            'sincronizacaoAtiva' => (bool) ($config?->tokenFocusAmbienteAtual()) && $config?->emissao_fiscal_ativa && $config?->emite_nfse,
        ]);
    }

    public function sincronizar(Request $request)
    {
        $empresaId = session('empresa_id');
        $unidadeId = session('unidade_id');

        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('unidade_id', $unidadeId)
            ->first();

        if (! $config || ! $config->tokenFocusAmbienteAtual() || ! $config->emissao_fiscal_ativa) {
            return back()->with('error', 'Ative a emissão fiscal e configure o token Focus NFe antes de sincronizar.');
        }

        if (! $config->emite_nfse) {
            return back()->with('error', 'Esta unidade não está configurada para emitir/receber NFS-e.');
        }

        try {
            $empresa = Empresa::withoutGlobalScopes()->find($empresaId);
            $unidade = Unidade::withoutGlobalScopes()->find($unidadeId);
            $service = new NFSesRecebidasService(FocusNFeClient::fromConfig($config));
            $novas = $service->sincronizar($empresa, $unidade);
        } catch (\Throwable $e) {
            Log::error('Erro ao sincronizar NFS-es recebidas', [
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Erro inesperado ao sincronizar: ' . $e->getMessage());
        }

        $mensagem = $novas > 0
            ? "{$novas} NFS-e(s) tomada(s) sincronizada(s)."
            : 'Nenhuma NFS-e nova.';

        return back()->with('success', $mensagem);
    }

    public function show(NFSeRecebida $nfseRecebida)
    {
        abort_unless($nfseRecebida->empresa_id === session('empresa_id'), 403);

        return view('app.nfses-recebidas.show', compact('nfseRecebida'));
    }
}
