<?php

namespace App\Services\FocusNFe;

use App\Models\Empresa;
use App\Models\NotaFiscal;
use App\Models\Unidade;
use App\Support\Cnpj;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Pacote mensal de XMLs fiscais, montado LOCALMENTE.
 *
 * O endpoint /v2/backups da Focus NÃO existe — 404 em toda chamada, flagrado
 * em produção de 05 a 12/08/2026 (antes disso a armadilha 31 impedia o command
 * de sequer rodar, então o endpoint nunca tinha sido exercitado de verdade).
 * E ele não é necessário: todo XML transmitido já é copiado por nota para o
 * nosso disco (BaixarXmlNotaJob + fiscal:baixar-xmls-notas). Este serviço junta
 * essas cópias no ZIP mensal auditável, por CNPJ — lojas que compartilham CNPJ
 * (armadilha 35) entram no MESMO pacote.
 *
 * Arquivo: fiscal/backups/{cnpj}/{YYYY-MM}.zip (disco local = storage/app/private).
 * Conteúdo: {tipo}/{chave}.xml + manifest.json (número, série, status, valor,
 * ambiente, emissão — o índice que o contador confere). Retenção: 5 anos.
 */
class BackupXmlService
{
    /**
     * Monta (ou remonta) o pacote do mês para um CNPJ.
     * Remontar é barato e idempotente — o mês corrente cresce a cada dia.
     *
     * @return array{status: string, arquivos: int, sem_xml: array<int, string>, path: ?string}
     */
    public function gerar(int $empresaId, string $cnpj, string $mes): array
    {
        $mes = $this->normalizarMes($mes);
        $cnpj = Cnpj::limpar($cnpj);

        if (! $cnpj) {
            throw new RuntimeException('CNPJ vazio — não há de quem montar o pacote.');
        }

        $notas = $this->notasDoMes($empresaId, $cnpj, $mes);

        $arquivos = [];
        $manifest = [];
        $semXml = [];

        foreach ($notas as $nota) {
            // Garante a cópia local (idempotente; baixa da Focus na hora se faltar).
            if (! $nota->temXmlLocal() && ! $nota->salvarXmlLocal()) {
                $semXml[] = $nota->chave_acesso;
                continue;
            }

            // tipo/status são enums (armadilha 9) — sempre o ->value.
            $tipo = $nota->tipo instanceof \BackedEnum ? $nota->tipo->value : (string) $nota->tipo;
            $status = $nota->status instanceof \BackedEnum ? $nota->status->value : (string) $nota->status;

            $arquivos[$tipo . '/' . $nota->chave_acesso . '.xml'] = $nota->caminhoXmlLocal();
            $manifest[] = [
                'tipo'       => $tipo,
                'numero'     => $nota->numero,
                'serie'      => $nota->serie,
                'chave'      => $nota->chave_acesso,
                'status'     => $status,
                'valor'      => $nota->valor_total,
                'ambiente'   => $nota->ambiente,
                'emitida_em' => optional($nota->emitida_em ?? $nota->created_at)->toDateTimeString(),
            ];
        }

        if ($arquivos === []) {
            return [
                'status' => 'vazio',
                'arquivos' => 0,
                'sem_xml' => $semXml,
                'path' => null,
            ];
        }

        $zipRel = $this->caminhoZip($cnpj, $mes);
        Storage::makeDirectory(dirname($zipRel));
        $zipAbs = Storage::path($zipRel);
        $tmpAbs = $zipAbs . '.tmp';

        $zip = new ZipArchive();
        if ($zip->open($tmpAbs, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Não consegui criar o ZIP em {$zipRel}");
        }

        foreach ($arquivos as $nomeInterno => $caminhoRel) {
            $zip->addFile(Storage::path($caminhoRel), $nomeInterno);
        }

        $zip->addFromString('manifest.json', json_encode([
            'cnpj' => $cnpj,
            'mes' => $mes,
            'gerado_em' => now()->toIso8601String(),
            'total_notas' => count($manifest),
            'sem_xml' => $semXml,
            'notas' => $manifest,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $zip->close();
        // Troca atômica: quem baixar durante a remontagem recebe o zip anterior.
        rename($tmpAbs, $zipAbs);

        if ($semXml !== []) {
            Log::warning('[BackupXml] pacote gerado com XMLs faltando', [
                'cnpj' => $cnpj, 'mes' => $mes, 'chaves' => $semXml,
            ]);
        }

        return [
            'status' => 'concluido',
            'arquivos' => count($arquivos),
            'sem_xml' => $semXml,
            'path' => $zipRel,
        ];
    }

    /**
     * Situação do pacote de um mês (para a UI): existe? quantos XMLs? quando?
     *
     * @return array{status: string, arquivos: ?int, atualizado_em: ?string, path: ?string, mes: string}
     */
    public function info(string $cnpj, string $mes): array
    {
        $mes = $this->normalizarMes($mes);
        $cnpj = Cnpj::limpar($cnpj);
        $zipRel = $this->caminhoZip($cnpj, $mes);

        if (! Storage::exists($zipRel)) {
            return ['status' => 'indisponivel', 'arquivos' => null, 'atualizado_em' => null, 'path' => null, 'mes' => $mes];
        }

        $arquivos = null;
        $zip = new ZipArchive();
        if ($zip->open(Storage::path($zipRel)) === true) {
            $arquivos = max(0, $zip->numFiles - 1); // menos o manifest.json
            $zip->close();
        }

        return [
            'status' => 'concluido',
            'arquivos' => $arquivos,
            'atualizado_em' => Carbon::createFromTimestamp(Storage::lastModified($zipRel))->toDateTimeString(),
            'path' => $zipRel,
            'mes' => $mes,
        ];
    }

    /** Notas com chave do CNPJ no mês — todas as unidades que compartilham o CNPJ. */
    public function notasDoMes(int $empresaId, string $cnpj, string $mes): Collection
    {
        $mes = $this->normalizarMes($mes);
        $cnpj = Cnpj::limpar($cnpj);
        $inicio = Carbon::createFromFormat('Y-m', $mes)->startOfMonth();
        $fim = $inicio->copy()->endOfMonth();

        $empresaCnpj = Cnpj::limpar((string) Empresa::withoutGlobalScopes()->find($empresaId)?->cnpj);

        $unidadeIds = Unidade::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->get(['id', 'cnpj'])
            ->filter(fn ($u) => (Cnpj::limpar((string) $u->cnpj) ?: $empresaCnpj) === $cnpj)
            ->pluck('id');

        return NotaFiscal::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->whereIn('unidade_id', $unidadeIds)
            ->whereNotNull('chave_acesso')
            ->whereRaw('COALESCE(emitida_em, created_at) BETWEEN ? AND ?', [$inicio, $fim])
            ->orderBy('id')
            ->get();
    }

    public function caminhoZip(string $cnpj, string $mes): string
    {
        return 'fiscal/backups/' . Cnpj::limpar($cnpj) . '/' . $mes . '.zip';
    }

    /**
     * Últimos meses (YYYY-MM) do mais novo para o mais velho, começando no
     * corrente — o pacote do mês em andamento é parcial por natureza e é
     * remontado todo dia pelo cron.
     *
     * @return array<int, string>
     */
    public function mesesDisponiveis(int $quantidade = 12): array
    {
        $base = Carbon::now()->startOfMonth();
        $meses = [];

        for ($i = 0; $i < $quantidade; $i++) {
            $meses[] = $base->copy()->subMonths($i)->format('Y-m');
        }

        return $meses;
    }

    private function normalizarMes(string $mes): string
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
            throw new RuntimeException(
                "Mês inválido: '{$mes}'. Use o formato YYYY-MM (ex: 2026-03)."
            );
        }

        return $mes;
    }
}
