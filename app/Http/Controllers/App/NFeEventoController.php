<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoFiscal;
use App\Models\NotaFiscal;
use App\Services\FocusNFe\AtorInteressadoService;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\InsucessoEntregaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NFeEventoController extends Controller
{
    /**
     * Registrar Ator Interessado na NFe.
     */
    public function atorInteressado(Request $request, NotaFiscal $notaFiscal)
    {
        $request->validate([
            'cnpj_ator' => 'required|string',
            'tipo_ator' => 'required|integer|in:1,2,3,4',
            'razao_social_ator' => 'nullable|string|max:255',
        ], [
            'cnpj_ator.required' => 'Informe o CNPJ do ator interessado.',
            'tipo_ator.in' => 'Tipo de ator inválido.',
        ]);

        $service = $this->fazerService(AtorInteressadoService::class, $notaFiscal);
        if (! $service) {
            return back()->with('error', 'Configuração fiscal inválida ou sem token Focus.');
        }

        try {
            $evento = $service->registrar($notaFiscal, $request->only([
                'cnpj_ator', 'tipo_ator', 'razao_social_ator',
            ]), auth()->id());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $mensagem = $evento->status === 'autorizado'
            ? 'Ator Interessado registrado com sucesso na SEFAZ.'
            : 'Evento registrado, mas a SEFAZ retornou erro: ' . ($evento->mensagem_retorno ?? 'sem detalhes');

        return back()->with($evento->status === 'autorizado' ? 'success' : 'error', $mensagem);
    }

    /**
     * Registrar Insucesso de Entrega.
     */
    public function insucessoEntrega(Request $request, NotaFiscal $notaFiscal)
    {
        $request->validate([
            'motivo'         => 'required|integer|in:1,2,3,4',
            'data_tentativa' => 'nullable|date',
            'justificativa'  => 'nullable|string|max:500',
            'latitude'       => 'nullable|numeric',
            'longitude'      => 'nullable|numeric',
        ], [
            'motivo.in' => 'Motivo inválido (1=sem funcionamento, 2=recusa, 3=endereço não encontrado, 4=outros).',
        ]);

        $service = $this->fazerService(InsucessoEntregaService::class, $notaFiscal);
        if (! $service) {
            return back()->with('error', 'Configuração fiscal inválida ou sem token Focus.');
        }

        try {
            $evento = $service->registrar($notaFiscal, $request->only([
                'motivo', 'data_tentativa', 'justificativa', 'latitude', 'longitude',
            ]), auth()->id());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $mensagem = $evento->status === 'autorizado'
            ? 'Insucesso de Entrega registrado com sucesso na SEFAZ.'
            : 'Evento registrado, mas a SEFAZ retornou erro: ' . ($evento->mensagem_retorno ?? 'sem detalhes');

        return back()->with($evento->status === 'autorizado' ? 'success' : 'error', $mensagem);
    }

    /**
     * Instancia o service correto com o cliente Focus da unidade da nota.
     *
     * @template T
     * @param  class-string<T>  $serviceClass
     * @return T|null
     */
    private function fazerService(string $serviceClass, NotaFiscal $nota)
    {
        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $nota->empresa_id)
            ->where('unidade_id', $nota->unidade_id)
            ->first();

        if (! $config || ! $config->tokenFocusAmbienteAtual()) {
            Log::warning('[NFeEvento] tentativa sem config válida', [
                'nota_id' => $nota->id,
                'service' => $serviceClass,
            ]);
            return null;
        }

        return new $serviceClass(FocusNFeClient::fromConfig($config));
    }
}
