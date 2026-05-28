<?php

namespace App\Jobs;

use App\Models\ConfiguracaoFiscal;
use App\Models\Empresa;
use App\Models\Notificacao;
use App\Models\Unidade;
use App\Services\FocusNFe\FocusEmpresaService;
use App\Services\FocusNFe\FocusNFeClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Provisiona uma empresa-filha na Focus NFe (modelo revenda) e cadastra
 * os webhooks aplicáveis ao perfil fiscal da unidade.
 *
 * Idempotente: se a empresa já tem focus_empresa_id, faz update via PUT
 * e re-sincroniza os webhooks (limpa órfãos + recria).
 *
 * Disparado por:
 *  - Admin/EmpresaController após criar empresa
 *  - Admin/UnidadeController após criar unidade
 *  - Página /admin/empresas/{id}/saude-focus → botão "Resincronizar"
 *
 * Notifica o usuário-dono da empresa (sino + activity log) ao terminar.
 */
class ProvisionarEmpresaFocusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 600];

    /**
     * @param  array<string, bool>  $flags  habilita_nfe, habilita_nfce, habilita_nfse, habilita_manifestacao
     */
    public function __construct(
        public int $empresaId,
        public int $unidadeId,
        public array $flags = [],
        public ?int $solicitadoPor = null,
    ) {}

    public function handle(): void
    {
        if (! FocusNFeClient::masterDisponivel()) {
            Log::warning('[ProvisionarFocus] master token ausente — pulando provisionamento', [
                'empresa_id' => $this->empresaId,
                'unidade_id' => $this->unidadeId,
            ]);
            return;
        }

        $empresa = Empresa::find($this->empresaId);
        $unidade = Unidade::withoutGlobalScopes()->find($this->unidadeId);

        if (! $empresa || ! $unidade) {
            Log::warning('[ProvisionarFocus] empresa/unidade não encontrada', [
                'empresa_id' => $this->empresaId,
                'unidade_id' => $this->unidadeId,
            ]);
            return;
        }

        $service = FocusEmpresaService::make();

        // 1. Criar (ou atualizar) empresa na Focus
        try {
            $service->criar($empresa, $unidade, $this->flags);
        } catch (\Throwable $e) {
            Log::error('[ProvisionarFocus] falha ao criar empresa', [
                'empresa_id' => $this->empresaId,
                'unidade_id' => $this->unidadeId,
                'erro' => $e->getMessage(),
            ]);
            $this->notificarFalha($empresa, "Não foi possível criar a empresa na Focus NFe: {$e->getMessage()}");
            throw $e; // permite retry
        }

        // 2. Sincronizar webhooks (só se a unidade emite algo)
        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $this->empresaId)
            ->where('unidade_id', $this->unidadeId)
            ->first();

        if (! $config) {
            return;
        }

        try {
            $mapa = $service->sincronizarWebhooks($config);
            $config->webhooks_sincronizados_em = now();
            $config->save();

            Log::info('[ProvisionarFocus] webhooks sincronizados', [
                'empresa_id' => $this->empresaId,
                'unidade_id' => $this->unidadeId,
                'eventos' => array_keys($mapa),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ProvisionarFocus] empresa criada mas falha em webhooks', [
                'empresa_id' => $this->empresaId,
                'unidade_id' => $this->unidadeId,
                'erro' => $e->getMessage(),
            ]);
            // Não relança — empresa foi criada, hooks podem ser recriados depois pela página de saúde
        }

        $this->notificarSucesso($empresa, $unidade);
    }

    private function notificarSucesso(Empresa $empresa, Unidade $unidade): void
    {
        $dono = $empresa->users()->where('perfil', 'dono')->first()?->id ?? $this->solicitadoPor;
        if (! $dono) {
            return;
        }
        Notificacao::create([
            'user_id' => $dono,
            'empresa_id' => $empresa->id,
            'tipo' => 'fiscal',
            'titulo' => 'Empresa configurada na Focus NFe',
            'mensagem' => "A unidade {$unidade->nome} foi provisionada e os webhooks estão ativos.",
            'url' => route('admin.empresas.saude-focus', $empresa->id, false),
        ]);
    }

    private function notificarFalha(Empresa $empresa, string $msg): void
    {
        $dono = $empresa->users()->where('perfil', 'dono')->first()?->id ?? $this->solicitadoPor;
        if (! $dono) {
            return;
        }
        Notificacao::create([
            'user_id' => $dono,
            'empresa_id' => $empresa->id,
            'tipo' => 'fiscal_erro',
            'titulo' => 'Falha ao provisionar empresa na Focus NFe',
            'mensagem' => $msg,
            'url' => route('admin.empresas.saude-focus', $empresa->id, false),
        ]);
    }
}
