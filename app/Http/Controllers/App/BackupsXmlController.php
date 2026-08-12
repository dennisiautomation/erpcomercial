<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoFiscal;
use App\Models\Unidade;
use App\Services\FocusNFe\BackupXmlService;
use App\Support\Cnpj;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Pacotes mensais de XML — montados localmente a partir das cópias por nota.
 * O download serve o zip do NOSSO disco; a Focus não participa mais
 * (o /v2/backups dela nunca existiu — 404).
 */
class BackupsXmlController extends Controller
{
    public function index()
    {
        $config = $this->configAtiva();

        if (! $config) {
            return view('app.backups-xml.index', [
                'fiscalAtivo' => false,
                'meses' => [],
                'backups' => [],
            ]);
        }

        $service = new BackupXmlService();
        $cnpj = $this->cnpjDaSessao();
        $meses = $service->mesesDisponiveis();
        $backups = [];

        foreach ($meses as $mes) {
            $backups[$mes] = $service->info($cnpj, $mes);
        }

        return view('app.backups-xml.index', [
            'fiscalAtivo' => true,
            'meses' => $meses,
            'backups' => $backups,
        ]);
    }

    public function gerar(Request $request)
    {
        $request->validate([
            'mes' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ], [
            'mes.regex' => 'Mês inválido — use o formato YYYY-MM.',
        ]);

        $config = $this->configAtiva();

        if (! $config) {
            return back()->with('error', 'Configuração fiscal não encontrada ou inativa.');
        }

        try {
            $service = new BackupXmlService();
            $result = $service->gerar($config->empresa_id, $this->cnpjDaSessao(), $request->input('mes'));
        } catch (\Throwable $e) {
            Log::error('Erro ao gerar pacote de XMLs', [
                'mes' => $request->input('mes'),
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', $e->getMessage());
        }

        if ($result['status'] === 'vazio') {
            return back()->with('success', 'Nenhuma nota com XML nesse mês — não há pacote a gerar.');
        }

        $aviso = $result['sem_xml'] !== []
            ? ' Atenção: ' . count($result['sem_xml']) . ' nota(s) sem XML disponível ficaram fora (ver manifest.json).'
            : '';

        return back()->with('success', "Pacote gerado com {$result['arquivos']} XML(s). Já dá para baixar.{$aviso}");
    }

    /** Serve o zip do nosso disco — só do CNPJ da própria sessão. */
    public function download(string $mes)
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
            abort(404);
        }

        $config = $this->configAtiva();

        if (! $config) {
            abort(403);
        }

        $service = new BackupXmlService();
        $cnpj = $this->cnpjDaSessao();
        $zipRel = $service->caminhoZip($cnpj, $mes);

        if (! Storage::exists($zipRel)) {
            return redirect()->route('app.backups-xml.index')
                ->with('error', "O pacote de {$mes} ainda não foi gerado.");
        }

        return Storage::download($zipRel, "xmls-{$cnpj}-{$mes}.zip");
    }

    /** CNPJ efetivo da unidade da sessão (sem CNPJ próprio, vale o da empresa). */
    private function cnpjDaSessao(): string
    {
        $unidade = Unidade::withoutGlobalScopes()->with('empresa')->find(session('unidade_id'));

        return Cnpj::limpar((string) ($unidade?->cnpj ?: $unidade?->empresa?->cnpj));
    }

    private function configAtiva(): ?ConfiguracaoFiscal
    {
        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', session('empresa_id'))
            ->where('unidade_id', session('unidade_id'))
            ->first();

        if (! $config || ! $config->emissao_fiscal_ativa) {
            return null;
        }

        return $config;
    }
}
