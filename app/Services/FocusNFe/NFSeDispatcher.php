<?php

namespace App\Services\FocusNFe;

use App\Models\Cliente;
use App\Models\ConfiguracaoFiscal;
use App\Models\NotaFiscal;
use App\Models\Unidade;

/**
 * Fachada fina que decide entre NFS-e municipal (legado) e NFS-e Nacional.
 *
 * A escolha vem de `ConfiguracaoFiscal.nfse_padrao`:
 *   - 'municipal' (default) → NFSeService
 *   - 'nacional'            → NFSeNacionalService
 *
 * Toda a UI + controllers continuam falando com o dispatcher; só os services
 * especializados sabem detalhes do leiaute. Isso deixa trocar o padrão por
 * unidade em um único toggle.
 */
class NFSeDispatcher
{
    public function __construct(
        private readonly NFSeService $municipal,
        private readonly NFSeNacionalService $nacional,
        private readonly ConfiguracaoFiscal $config,
    ) {}

    public static function forUnidade(Unidade $unidade): static
    {
        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $unidade->empresa_id)
            ->where('unidade_id', $unidade->id)
            ->firstOrFail();

        return new static(
            NFSeService::forUnidade($unidade),
            NFSeNacionalService::forUnidade($unidade),
            $config,
        );
    }

    public static function forConfig(ConfiguracaoFiscal $config): static
    {
        return self::forUnidade($config->unidade);
    }

    public function padrao(): string
    {
        return $this->config->nfse_padrao ?? 'municipal';
    }

    /**
     * @param  array<string, mixed>  $dadosServico
     */
    public function emitir(array $dadosServico, ?Cliente $cliente = null): NotaFiscal
    {
        return $this->padrao() === 'nacional'
            ? $this->nacional->emitir($dadosServico, $this->config, $cliente)
            : $this->municipal->emitir($dadosServico, $this->config, $cliente);
    }

    public function consultar(NotaFiscal $nota): NotaFiscal
    {
        return $this->isNacional($nota)
            ? $this->nacional->consultar($nota)
            : $this->municipal->consultar($nota);
    }

    public function cancelar(NotaFiscal $nota, string $justificativa): NotaFiscal
    {
        return $this->isNacional($nota)
            ? $this->nacional->cancelar($nota, $justificativa)
            : $this->municipal->cancelar($nota, $justificativa);
    }

    /**
     * Notas emitidas pelo padrão nacional recebem prefixo "nfse-nac-" no focus_ref.
     * Usamos isso para rotear consulta/cancelamento de notas antigas mesmo se
     * o padrão da unidade mudou depois.
     */
    private function isNacional(NotaFiscal $nota): bool
    {
        if (str_starts_with((string) $nota->focus_ref, 'nfse-nac-')) {
            return true;
        }
        return $this->padrao() === 'nacional';
    }
}
