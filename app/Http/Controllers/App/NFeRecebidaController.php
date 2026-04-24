<?php

namespace App\Http\Controllers\App;

use App\Enums\TipoManifestacao;
use App\Exceptions\ManifestacaoException;
use App\Http\Controllers\Controller;
use App\Jobs\SincronizarNFesRecebidasJob;
use App\Models\ConfiguracaoFiscal;
use App\Models\Empresa;
use App\Models\NFeRecebida;
use App\Models\Unidade;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\ManifestacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NFeRecebidaController extends Controller
{
    public function index(Request $request)
    {
        $query = NFeRecebida::query()
            ->with('manifestador:id,name')
            ->latest('data_emissao');

        if ($status = $request->input('status')) {
            if ($status === 'pendente') {
                $query->whereNull('tipo_ultima_manifestacao');
            } elseif ($status === 'manifestada') {
                $query->whereNotNull('tipo_ultima_manifestacao');
            } else {
                $query->where('tipo_ultima_manifestacao', $status);
            }
        }

        if ($emitente = $request->input('emitente')) {
            $query->where(function ($q) use ($emitente) {
                $q->where('nome_emitente', 'like', "%{$emitente}%")
                    ->orWhere('cnpj_emitente', 'like', '%' . preg_replace('/\D/', '', $emitente) . '%');
            });
        }

        if ($desde = $request->input('desde')) {
            $query->where('data_emissao', '>=', $desde);
        }
        if ($ate = $request->input('ate')) {
            $query->where('data_emissao', '<=', $ate);
        }

        $notas = $query->paginate(25)->withQueryString();

        $config = ConfiguracaoFiscal::firstOrNew([
            'empresa_id' => session('empresa_id'),
            'unidade_id' => session('unidade_id'),
        ]);

        return view('app.nfes-recebidas.index', [
            'notas'               => $notas,
            'tipos'               => TipoManifestacao::cases(),
            'sincronizacaoAtiva'  => (bool) $config->focus_token && $config->emissao_fiscal_ativa,
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

        if (! $config || ! $config->focus_token || ! $config->emissao_fiscal_ativa) {
            return back()->with('error', 'Ative a emissão fiscal e informe o token Focus NFe antes de sincronizar.');
        }

        // Execução síncrona quando disparada pela UI para feedback imediato.
        // O scheduler usa o job assíncrono.
        try {
            $empresa = Empresa::withoutGlobalScopes()->find($empresaId);
            $unidade = Unidade::withoutGlobalScopes()->find($unidadeId);
            $service = new ManifestacaoService(FocusNFeClient::fromConfig($config));
            $novas = $service->sincronizar($empresa, $unidade);
        } catch (ManifestacaoException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Erro inesperado ao sincronizar NFes recebidas', [
                'empresa_id' => $empresaId,
                'error'      => $e->getMessage(),
            ]);
            return back()->with('error', 'Erro inesperado ao sincronizar. Tente novamente em instantes.');
        }

        $mensagem = $novas > 0
            ? "{$novas} NF-e(s) recebida(s) foram sincronizadas."
            : 'Nenhuma NF-e nova na Receita.';

        return back()->with('success', $mensagem);
    }

    public function manifestar(Request $request, NFeRecebida $nfeRecebida)
    {
        $request->validate([
            'tipo'          => 'required|string|in:' . implode(',', array_column(TipoManifestacao::cases(), 'value')),
            'justificativa' => 'nullable|string|max:255',
        ]);

        $empresaId = session('empresa_id');
        $unidadeId = session('unidade_id');

        abort_unless(
            $nfeRecebida->empresa_id === $empresaId && $nfeRecebida->unidade_id === $unidadeId,
            403,
            'NF-e não pertence a esta unidade.'
        );

        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('unidade_id', $unidadeId)
            ->first();

        if (! $config || ! $config->focus_token) {
            return back()->with('error', 'Token Focus NFe não configurado.');
        }

        $tipo = TipoManifestacao::from($request->input('tipo'));

        try {
            $service = new ManifestacaoService(FocusNFeClient::fromConfig($config));
            $service->manifestar($nfeRecebida, $tipo, $request->input('justificativa'), auth()->id());
        } catch (ManifestacaoException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Erro inesperado na manifestação', [
                'nfe_id' => $nfeRecebida->id,
                'error'  => $e->getMessage(),
            ]);
            return back()->with('error', 'Erro inesperado ao manifestar. Nossa equipe foi notificada.');
        }

        return back()->with('success', 'Manifestação "' . $tipo->label() . '" registrada com sucesso.');
    }
}
