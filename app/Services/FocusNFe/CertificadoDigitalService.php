<?php

namespace App\Services\FocusNFe;

use App\Exceptions\CertificadoDigitalException;
use App\Models\ConfiguracaoFiscal;
use App\Models\Empresa;
use Illuminate\Support\Facades\Log;

/**
 * Envia um certificado digital (.pfx) para a Focus NFe vinculada
 * ao CNPJ da empresa. O arquivo NÃO é persistido localmente — apenas
 * encaminhado via multipart e descartado. Armazenamos só os metadados
 * (data de envio, validade, CNPJ, nome do arquivo).
 */
class CertificadoDigitalService
{
    public function __construct(private FocusNFeClient $client) {}

    /**
     * @throws CertificadoDigitalException
     */
    public function enviar(
        Empresa $empresa,
        ConfiguracaoFiscal $config,
        string $pfxBinaryContents,
        string $senha,
        string $nomeArquivo = 'certificado.pfx'
    ): ConfiguracaoFiscal {
        $cnpj = preg_replace('/\D/', '', $empresa->cnpj ?? '');

        if (strlen($cnpj) !== 14) {
            throw new CertificadoDigitalException(
                'A empresa não tem CNPJ válido cadastrado. Preencha o CNPJ em Dados da Empresa antes de enviar o certificado.'
            );
        }

        if (empty($senha)) {
            throw new CertificadoDigitalException('Informe a senha do certificado digital.');
        }

        Log::info('Certificado: enviando para Focus NFe', [
            'empresa_id' => $empresa->id,
            'cnpj'       => $cnpj,
            'arquivo'    => $nomeArquivo,
            'tamanho'    => strlen($pfxBinaryContents),
        ]);

        try {
            $response = $this->client->postMultipart("/v2/empresas/{$cnpj}/certificado", [
                ['name' => 'arquivo', 'contents' => $pfxBinaryContents, 'filename' => $nomeArquivo],
                ['name' => 'senha', 'contents' => $senha],
            ]);
        } catch (\Throwable $e) {
            Log::error('Certificado: erro de comunicação com Focus', [
                'empresa_id' => $empresa->id,
                'error'      => $e->getMessage(),
            ]);
            throw new CertificadoDigitalException(
                'Não foi possível conectar ao Focus NFe para enviar o certificado. Tente novamente em alguns minutos.',
                0, $e
            );
        }

        $data = $response->json() ?? [];

        if (! $response->successful()) {
            $rawMsg = $data['mensagem'] ?? $data['erro'] ?? 'Erro desconhecido.';
            $friendly = $this->translateError($rawMsg, $response->status());
            Log::warning('Certificado: rejeitado pela Focus', [
                'empresa_id' => $empresa->id,
                'status'     => $response->status(),
                'response'   => $data,
            ]);
            throw new CertificadoDigitalException($friendly);
        }

        // Data de expiração pode vir em vários formatos dependendo do endpoint;
        // tentamos os mais comuns.
        $validade = null;
        foreach (['expiracao', 'validade', 'certificado_validade', 'data_expiracao'] as $key) {
            if (! empty($data[$key])) {
                try {
                    $validade = \Carbon\Carbon::parse($data[$key]);
                    break;
                } catch (\Throwable $e) { /* tenta próximo */ }
            }
        }

        $config->certificado_enviado_em = now();
        $config->certificado_cnpj       = $cnpj;
        $config->certificado_nome       = $nomeArquivo;
        if ($validade) {
            $config->certificado_validade = $validade->toDateString();
        }
        $config->save();

        Log::info('Certificado: enviado com sucesso', [
            'empresa_id' => $empresa->id,
            'validade'   => $config->certificado_validade?->toDateString(),
        ]);

        return $config->fresh();
    }

    private function translateError(string $raw, int $httpStatus): string
    {
        $lower = mb_strtolower($raw);

        if (str_contains($lower, 'senha') && (str_contains($lower, 'incorre') || str_contains($lower, 'invalid'))) {
            return 'Senha do certificado incorreta. Verifique e tente novamente.';
        }
        if (str_contains($lower, 'cnpj') || str_contains($lower, 'titular')) {
            return 'O CNPJ do certificado não bate com o CNPJ cadastrado na empresa. Verifique se enviou o certificado correto.';
        }
        if (str_contains($lower, 'expir') || str_contains($lower, 'vencid')) {
            return 'Este certificado já está vencido. Adquira um novo certificado digital A1 antes de prosseguir.';
        }
        if (str_contains($lower, 'formato') || str_contains($lower, 'pfx') || str_contains($lower, 'corromp')) {
            return 'O arquivo enviado não parece ser um certificado A1 (.pfx) válido. Verifique o arquivo.';
        }
        if ($httpStatus === 401) {
            return 'Token Focus NFe inválido. Verifique as configurações fiscais.';
        }
        if ($httpStatus >= 500) {
            return 'O serviço Focus NFe está instável. Tente novamente em alguns minutos.';
        }

        return "Não foi possível enviar o certificado: {$raw}";
    }
}
