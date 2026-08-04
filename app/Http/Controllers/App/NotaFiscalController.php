<?php

namespace App\Http\Controllers\App;

use App\Enums\StatusNotaFiscal;
use App\Enums\TipoNotaFiscal;
use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoFiscal;
use App\Models\NotaFiscal;
use App\Models\Venda;
use App\Services\FocusNFe\NFCeService;
use App\Services\FocusNFe\NFeService;
use App\Services\FocusNFe\NFSeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotaFiscalController extends Controller
{
    /* ------------------------------------------------------------------ */
    /*  Listagem                                                           */
    /* ------------------------------------------------------------------ */

    public function index(Request $request)
    {
        $query = NotaFiscal::where('empresa_id', session('empresa_id'))
            ->where('unidade_id', session('unidade_id'))
            ->with(['cliente:id,nome_razao_social,cpf_cnpj', 'venda:id,numero']);

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate('emitida_em', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('emitida_em', '<=', $request->data_fim);
        }

        if ($request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('numero', 'like', "%{$busca}%")
                  ->orWhere('chave_acesso', 'like', "%{$busca}%")
                  ->orWhereHas('cliente', fn ($c) => $c->where('nome_razao_social', 'like', "%{$busca}%"));
            });
        }

        $notasFiscais = $query->latest('emitida_em')->paginate(20)->withQueryString();

        // Summary cards
        $mesAtual = now()->startOfMonth();
        $baseQuery = NotaFiscal::where('empresa_id', session('empresa_id'))
            ->where('unidade_id', session('unidade_id'))
            ->where('emitida_em', '>=', $mesAtual);

        $totalEmitidas = (clone $baseQuery)->count();
        $totalAutorizadas = (clone $baseQuery)->where('status', StatusNotaFiscal::Autorizada)->count();
        $totalCanceladas = (clone $baseQuery)->where('status', StatusNotaFiscal::Cancelada)->count();

        return view('app.notas-fiscais.index', compact(
            'notasFiscais',
            'totalEmitidas',
            'totalAutorizadas',
            'totalCanceladas',
        ));
    }

    /* ------------------------------------------------------------------ */
    /*  Detalhes                                                           */
    /* ------------------------------------------------------------------ */

    public function show(NotaFiscal $notaFiscal)
    {
        $notaFiscal->load(['cliente', 'venda', 'unidade', 'empresa', 'cartasCorrecao.user']);

        return view('app.notas-fiscais.show', compact('notaFiscal'));
    }

    /* ------------------------------------------------------------------ */
    /*  Emitir NF-e                                                        */
    /* ------------------------------------------------------------------ */

    public function emitirNFe(Request $request, Venda $venda)
    {
        if ($venda->itens()->count() === 0) {
            return back()->with('error', 'A venda nao possui itens para emitir NF-e.');
        }

        $config = $this->getConfigFiscal();

        if (! $config || ! $config->emissao_fiscal_ativa) {
            return back()->with('error', 'Emissao fiscal nao esta ativa para esta unidade.');
        }

        try {
            // NUNCA app(NFeService::class): o client exige token (armadilha 13) e o
            // container estoura BindingResolutionException antes de emitir.
            $service = NFeService::forUnidade($config->unidade);
            $nota = $service->emitir($venda, $config);

            return redirect()
                ->route('app.notas-fiscais.show', $nota)
                ->with('success', 'NF-e enviada para autorizacao com sucesso!');
        } catch (\Throwable $e) {
            Log::error('Erro ao emitir NF-e', [
                'venda_id' => $venda->id,
                'error'    => $e->getMessage(),
            ]);

            return back()->with('error', 'Erro ao emitir NF-e: ' . $e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Emitir NFC-e (PDV - JSON response)                                 */
    /* ------------------------------------------------------------------ */

    public function emitirNFCe(Request $request, Venda $venda)
    {
        if ($venda->itens()->count() === 0) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'A venda nao possui itens.'], 422)
                : back()->with('error', 'A venda não possui itens.');
        }

        $config = $this->getConfigFiscal();

        if (! $config || ! $config->emissao_fiscal_ativa) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Emissao fiscal nao esta ativa.'], 422)
                : back()->with('error', 'Emissão fiscal não está ativa para esta unidade.');
        }

        try {
            $service = NFCeService::forUnidade($config->unidade);
            $nota = $service->emitir($venda, $config);

            // Requisição de formulário (tela da venda) volta com flash; o PDV usa JSON.
            if (! $request->expectsJson()) {
                return redirect()
                    ->route('app.notas-fiscais.show', $nota)
                    ->with('success', 'NFC-e emitida com sucesso!');
            }

            return response()->json([
                'success'  => true,
                'message'  => 'NFC-e emitida com sucesso!',
                'nota'     => [
                    'id'           => $nota->id,
                    'numero'       => $nota->numero,
                    'chave_acesso' => $nota->chave_acesso,
                    'status'       => $nota->status->value,
                    'danfe_url'    => $nota->danfe_url_completa,
                    'xml_url'      => $nota->xml_url_completa,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Erro ao emitir NFC-e', [
                'venda_id' => $venda->id,
                'error'    => $e->getMessage(),
            ]);

            if (! $request->expectsJson()) {
                return back()->with('error', 'Erro ao emitir NFC-e: ' . $e->getMessage());
            }

            return response()->json(['success' => false, 'message' => 'Erro ao emitir NFC-e: ' . $e->getMessage()], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Emitir NFS-e (form)                                                */
    /* ------------------------------------------------------------------ */

    public function emitirNFSeForm()
    {
        return view('app.notas-fiscais.emitir-nfse');
    }

    /* ------------------------------------------------------------------ */
    /*  Emitir NFS-e (store)                                               */
    /* ------------------------------------------------------------------ */

    public function emitirNFSe(Request $request)
    {
        $request->validate([
            'cliente_id'    => 'required|exists:clientes,id',
            'descricao'     => 'required|string|max:2000',
            'valor_servico' => 'required|numeric|min:0.01',
            'aliquota_iss'  => 'required|numeric|min:0|max:100',
        ]);

        $config = $this->getConfigFiscal();

        if (! $config || ! $config->emissao_fiscal_ativa) {
            return back()->with('error', 'Emissao fiscal nao esta ativa para esta unidade.');
        }

        try {
            // O dispatcher escolhe entre NFS-e municipal e NFS-e nacional conforme config
            $dispatcher = \App\Services\FocusNFe\NFSeDispatcher::forConfig($config);
            $nota = $dispatcher->emitir($request->all(), $request->cliente_id
                ? \App\Models\Cliente::find($request->cliente_id)
                : null);

            // Se ficou pendente (Focus aceitou e está processando com a prefeitura/RFB),
            // encadeia o polling automático — mesma lógica da NF-e.
            if ($nota->status === \App\Enums\StatusNotaFiscal::Pendente) {
                \App\Jobs\ConsultarNotaFiscalJob::dispatch($nota)->delay(now()->addSeconds(10));

                $mensagemProcessamento = $dispatcher->padrao() === 'nacional'
                    ? 'NFS-e enviada! Aguardando autorização do Portal Nacional. Esta página atualiza automaticamente.'
                    : 'NFS-e enviada! Estamos aguardando a autorização da prefeitura. Esta página atualiza automaticamente.';

                return redirect()
                    ->route('app.notas-fiscais.show', $nota)
                    ->with('success', $mensagemProcessamento);
            }

            return redirect()
                ->route('app.notas-fiscais.show', $nota)
                ->with('success', 'NFS-e processada com sucesso!');
        } catch (\App\Exceptions\NotaFiscalEmissaoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Erro ao emitir NFS-e', ['error' => $e->getMessage()]);

            return back()->with('error', 'Erro ao emitir NFS-e: ' . $e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Consultar status                                                   */
    /* ------------------------------------------------------------------ */

    public function consultar(NotaFiscal $notaFiscal)
    {
        try {
            $service = $this->resolveService($notaFiscal->tipo, $notaFiscal);
            $nota = $service->consultar($notaFiscal);

            return response()->json([
                'success' => true,
                'status'  => $nota->status->value,
                'label'   => $nota->status->label(),
                'color'   => $nota->status->color(),
                'message' => $nota->focus_mensagem,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Cancelar                                                           */
    /* ------------------------------------------------------------------ */

    public function cancelar(Request $request, NotaFiscal $notaFiscal)
    {
        $request->validate([
            'justificativa' => 'required|string|min:15|max:255',
        ]);

        if ($notaFiscal->status !== StatusNotaFiscal::Autorizada) {
            return back()->with('error', 'Somente notas autorizadas podem ser canceladas.');
        }

        try {
            $service = $this->resolveService($notaFiscal->tipo, $notaFiscal);
            $service->cancelar($notaFiscal, $request->justificativa);

            return back()->with('success', 'Nota fiscal cancelada com sucesso!');
        } catch (\App\Exceptions\NotaFiscalCancelException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Erro ao cancelar nota', ['nota_id' => $notaFiscal->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Erro inesperado ao cancelar a nota. Nossa equipe foi notificada — tente novamente em instantes.');
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Carta de Correcao (NF-e only)                                      */
    /* ------------------------------------------------------------------ */

    public function cartaCorrecao(Request $request, NotaFiscal $notaFiscal)
    {
        $request->validate([
            'correcao' => 'required|string|min:15|max:1000',
        ]);

        if ($notaFiscal->tipo !== TipoNotaFiscal::NFe) {
            return back()->with('error', 'Carta de correcao disponivel apenas para NF-e.');
        }

        if ($notaFiscal->status !== StatusNotaFiscal::Autorizada) {
            return back()->with('error', 'Somente notas autorizadas podem receber carta de correcao.');
        }

        try {
            $service = NFeService::forUnidade($notaFiscal->unidade()->withoutGlobalScopes()->first());
            $carta = $service->cartaCorrecao($notaFiscal, $request->correcao, auth()->id());

            return back()->with('success', "Carta de Correção #{$carta->numero_sequencia} autorizada pela SEFAZ!");
        } catch (\App\Exceptions\CartaCorrecaoException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Erro inesperado na carta de correcao', ['nota_id' => $notaFiscal->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Erro inesperado ao emitir Carta de Correção. Nossa equipe foi notificada.');
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Inutilizar numeracao (form)                                        */
    /* ------------------------------------------------------------------ */

    public function inutilizarForm()
    {
        return view('app.notas-fiscais.inutilizar');
    }

    /* ------------------------------------------------------------------ */
    /*  Inutilizar numeracao (store)                                       */
    /* ------------------------------------------------------------------ */

    public function inutilizar(Request $request)
    {
        $request->validate([
            'tipo'            => 'required|in:nfe,nfce',
            'serie'           => 'required|integer|min:1',
            'numero_inicial'  => 'required|integer|min:1',
            'numero_final'    => 'required|integer|min:1|gte:numero_inicial',
            'justificativa'   => 'required|string|min:15|max:255',
        ]);

        $config = $this->getConfigFiscal();

        if (! $config || ! $config->emissao_fiscal_ativa) {
            return back()->with('error', 'Emissao fiscal nao esta ativa para esta unidade.');
        }

        try {
            $tipo = TipoNotaFiscal::from($request->tipo);
            $unidadeCfg = $config->unidade()->withoutGlobalScopes()->first();
            $service = match ($tipo) {
                TipoNotaFiscal::NFe  => NFeService::forUnidade($unidadeCfg),
                TipoNotaFiscal::NFCe => NFCeService::forUnidade($unidadeCfg),
                TipoNotaFiscal::NFSe => NFSeService::forUnidade($unidadeCfg),
            };
            $service->inutilizar($request->all(), $config);

            return redirect()
                ->route('app.notas-fiscais.index')
                ->with('success', 'Numeracao inutilizada com sucesso!');
        } catch (\Throwable $e) {
            Log::error('Erro ao inutilizar', ['error' => $e->getMessage()]);

            return back()->with('error', 'Erro ao inutilizar: ' . $e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Download XML                                                       */
    /* ------------------------------------------------------------------ */

    public function downloadXml(NotaFiscal $notaFiscal)
    {
        // Cópia local primeiro (não depende da Focus estar de pé);
        // se ainda não tem, tenta baixar na hora e cai no redirect se falhar.
        if ($notaFiscal->chave_acesso
            && ($notaFiscal->temXmlLocal() || $notaFiscal->salvarXmlLocal())) {
            return \Illuminate\Support\Facades\Storage::download(
                $notaFiscal->caminhoXmlLocal(),
                $notaFiscal->chave_acesso . '.xml'
            );
        }

        if (! $notaFiscal->xml_url) {
            return back()->with('error', 'XML nao disponivel para esta nota.');
        }

        return redirect($notaFiscal->xml_url_completa);
    }

    /* ------------------------------------------------------------------ */
    /*  Download DANFE/PDF                                                 */
    /* ------------------------------------------------------------------ */

    public function downloadDanfe(NotaFiscal $notaFiscal)
    {
        $url = $notaFiscal->danfe_url_completa;

        if (! $url) {
            return back()->with('error', 'Documento nao disponivel para esta nota.');
        }

        return redirect($url);
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function getConfigFiscal(): ?ConfiguracaoFiscal
    {
        return ConfiguracaoFiscal::where('empresa_id', session('empresa_id'))
            ->where('unidade_id', session('unidade_id'))
            ->first();
    }

    /**
     * Armadilha 13: os services Focus exigem client com token — nunca app(...).
     * Usa a unidade da PRÓPRIA nota (cancelar da tela de outra unidade funciona).
     */
    private function resolveService(TipoNotaFiscal $tipo, NotaFiscal $nota): NFeService|NFCeService|NFSeService
    {
        $unidade = $nota->unidade()->withoutGlobalScopes()->first();

        return match ($tipo) {
            TipoNotaFiscal::NFe  => NFeService::forUnidade($unidade),
            TipoNotaFiscal::NFCe => NFCeService::forUnidade($unidade),
            TipoNotaFiscal::NFSe => NFSeService::forUnidade($unidade),
        };
    }
}
