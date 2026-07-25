<?php

namespace App\Services\FocusNFe;

use App\Exceptions\CertificadoDigitalException;
use App\Models\ConfiguracaoFiscal;
use App\Models\Empresa;
use Illuminate\Support\Facades\Log;

/**
 * Instala o certificado digital A1 (.pfx) da empresa na Focus NFe.
 *
 * O arquivo NÃO é persistido localmente — vai em base64 no payload da empresa-filha
 * e só os metadados (validade, CNPJ titular, nome do arquivo, data de envio) ficam
 * no banco.
 *
 * ⚠️ A Focus NÃO tem endpoint dedicado de certificado. A instalação é feita pela API
 * de empresas (modelo revenda), com TOKEN MASTER em api.focusnfe.com.br:
 *     PUT /v2/empresas/{focus_empresa_id}
 *       { "arquivo_certificado_base64": "...", "senha_certificado": "..." }
 * A versão anterior chamava POST /v2/empresas/{cnpj}/certificado com o token DA EMPRESA
 * no host de homologação — rota inexistente, sempre 404 ("Erro desconhecido" na tela).
 */
class CertificadoDigitalService
{
    /** O client recebido não é usado no envio (mantido por compatibilidade de assinatura). */
    public function __construct(private ?FocusNFeClient $client = null) {}

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
        $cnpjEmpresa = \App\Support\Cnpj::limpar(
            $config->unidade?->cnpj ?: $empresa->cnpj ?? ''
        );

        if (strlen($cnpjEmpresa) !== 14) {
            throw new CertificadoDigitalException(
                'A empresa não tem CNPJ válido cadastrado. Preencha o CNPJ em Dados da Empresa antes de enviar o certificado.'
            );
        }

        if (empty($senha)) {
            throw new CertificadoDigitalException('Informe a senha do certificado digital.');
        }

        // Validação local antes de gastar chamada na Focus: senha, validade e titular.
        $dados = $this->lerCertificado($pfxBinaryContents, $senha);

        if ($dados['valido_ate'] && $dados['valido_ate']->isPast()) {
            throw new CertificadoDigitalException(
                'Este certificado venceu em ' . $dados['valido_ate']->format('d/m/Y')
                . '. Adquira um novo certificado digital A1 antes de prosseguir.'
            );
        }

        if ($dados['cnpj'] && $dados['cnpj'] !== $cnpjEmpresa) {
            throw new CertificadoDigitalException(
                'O certificado é do CNPJ ' . \App\Support\Cnpj::formatar($dados['cnpj'])
                . ', diferente do CNPJ desta unidade (' . \App\Support\Cnpj::formatar($cnpjEmpresa) . ').'
            );
        }

        if (empty($config->focus_empresa_id)) {
            throw new CertificadoDigitalException(
                'Esta unidade ainda não foi provisionada na Focus NFe. Peça ao administrador para '
                . 'ressincronizar em Admin › Empresas › Saúde Focus e tente de novo.'
            );
        }

        if (! FocusNFeClient::masterDisponivel()) {
            throw new CertificadoDigitalException(
                'Token master da Focus NFe não configurado na plataforma (FOCUS_MASTER_TOKEN). Avise o suporte.'
            );
        }

        Log::info('Certificado: instalando via API de empresas', [
            'empresa_id'       => $empresa->id,
            'unidade_id'       => $config->unidade_id,
            'focus_empresa_id' => $config->focus_empresa_id,
            'cnpj'             => $cnpjEmpresa,
            'arquivo'          => $nomeArquivo,
            'tamanho'          => strlen($pfxBinaryContents),
            'valido_ate'       => $dados['valido_ate']?->toDateString(),
        ]);

        try {
            $response = FocusNFeClient::master()->put("/v2/empresas/{$config->focus_empresa_id}", [
                'arquivo_certificado_base64' => base64_encode($pfxBinaryContents),
                'senha_certificado'          => $senha,
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
            // A Focus manda o detalhe em erros[].mensagem; a raiz traz só "Erro de validação".
            $rawMsg = $data['erros'][0]['mensagem']
                ?? $data['mensagem']
                ?? $data['erro']
                ?? $data['arquivo_certificado_base64']
                ?? $data['senha_certificado']
                ?? 'Erro desconhecido.';
            $rawMsg = is_array($rawMsg) ? implode(' ', $rawMsg) : (string) $rawMsg;

            Log::warning('Certificado: rejeitado pela Focus', [
                'empresa_id'       => $empresa->id,
                'focus_empresa_id' => $config->focus_empresa_id,
                'status'           => $response->status(),
                'response'         => $data,
            ]);

            throw new CertificadoDigitalException($this->translateError($rawMsg, $response->status()));
        }

        // Validade: prioriza o que a Focus devolve; cai no que lemos do próprio .pfx.
        $validade = null;
        foreach (['certificado_valido_ate', 'expiracao', 'validade', 'certificado_validade', 'data_expiracao'] as $key) {
            if (! empty($data[$key])) {
                try {
                    $validade = \Carbon\Carbon::parse($data[$key]);
                    break;
                } catch (\Throwable $e) { /* tenta próximo */ }
            }
        }
        $validade ??= $dados['valido_ate'];

        $config->certificado_enviado_em = now();
        $config->certificado_cnpj       = $data['certificado_cnpj'] ?? $dados['cnpj'] ?? $cnpjEmpresa;
        $config->certificado_nome       = $nomeArquivo;
        if ($validade) {
            $config->certificado_validade = $validade->toDateString();
        }
        $config->save();

        Log::info('Certificado: instalado com sucesso', [
            'empresa_id' => $empresa->id,
            'validade'   => $config->certificado_validade?->toDateString(),
        ]);

        return $config->fresh();
    }

    /**
     * Abre o .pfx localmente (openssl) para conferir senha, validade e CNPJ do titular.
     * Nada é gravado em disco.
     *
     * @return array{valido_ate: ?\Carbon\Carbon, cnpj: ?string, titular: ?string}
     *
     * @throws CertificadoDigitalException  se a senha estiver errada ou o arquivo não for PKCS#12
     */
    private function lerCertificado(string $pfx, string $senha): array
    {
        $certs = [];

        if (! @openssl_pkcs12_read($pfx, $certs, $senha)) {
            // PKCS#12 é DER: começa com SEQUENCE (0x30). Se a estrutura está certa e mesmo
            // assim não abriu, o problema é a senha — não o arquivo.
            $arquivoOk = strlen($pfx) > 200 && ord($pfx[0]) === 0x30;

            throw new CertificadoDigitalException(
                $arquivoOk
                    ? 'Senha do certificado incorreta. Verifique e tente novamente.'
                    : 'O arquivo enviado não parece ser um certificado A1 (.pfx/.p12) válido.'
            );
        }

        $info = @openssl_x509_parse($certs['cert'] ?? '') ?: [];

        $validoAte = isset($info['validTo_time_t'])
            ? \Carbon\Carbon::createFromTimestamp($info['validTo_time_t'])
            : null;

        // O CNPJ do titular sai no CN ("EMPRESA LTDA:12345678000190") ou no subjectAltName
        $texto = ($info['subject']['CN'] ?? '') . ' ' . ($info['extensions']['subjectAltName'] ?? '');
        preg_match('/(\d{14})(?!\d)/', $texto, $m);

        return [
            'valido_ate' => $validoAte,
            'cnpj'       => $m[1] ?? null,
            'titular'    => $info['subject']['CN'] ?? null,
        ];
    }

    private function translateError(string $raw, int $httpStatus): string
    {
        $lower = mb_strtolower($raw);

        if (str_contains($lower, 'senha') && (str_contains($lower, 'incorre') || str_contains($lower, 'invalid') || str_contains($lower, 'correto'))) {
            return 'Senha do certificado incorreta. Verifique e tente novamente.';
        }
        if (str_contains($lower, 'cnpj') || str_contains($lower, 'titular') || str_contains($lower, 'pertence')) {
            return 'O CNPJ do certificado não bate com o CNPJ cadastrado na empresa. Verifique se enviou o certificado correto.';
        }
        if (str_contains($lower, 'expir') || str_contains($lower, 'vencid') || str_contains($lower, 'validade')) {
            return 'Este certificado já está vencido. Adquira um novo certificado digital A1 antes de prosseguir.';
        }
        if (str_contains($lower, 'formato') || str_contains($lower, 'pfx') || str_contains($lower, 'p12') || str_contains($lower, 'corromp')) {
            return 'O arquivo enviado não parece ser um certificado A1 (.pfx) válido. Verifique o arquivo.';
        }
        if ($httpStatus === 401 || $httpStatus === 403) {
            return 'Token master Focus NFe inválido ou sem permissão para esta empresa. Avise o suporte.';
        }
        if ($httpStatus === 404) {
            return 'A unidade não foi encontrada na Focus NFe. Ressincronize em Admin › Empresas › Saúde Focus e tente de novo.';
        }
        if ($httpStatus >= 500) {
            return 'O serviço Focus NFe está instável. Tente novamente em alguns minutos.';
        }

        return "Não foi possível enviar o certificado: {$raw}";
    }
}
