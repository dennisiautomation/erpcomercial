<?php

namespace App\Services\Ajuda;

use App\Models\PlataformaConfiguracao;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Quem vê o assistente de ajuda (22/11/2026). Três níveis, nesta ordem:
 *  1. Servidor: AJUDA_ATIVO no .env (config ajuda.ativo). Desligado = ninguém.
 *  2. Exceção da empresa (empresas.ajuda_modo, Admin → Empresa):
 *     'ligado' / 'desligado' mandam; null = segue o geral.
 *  3. Geral (PlataformaConfiguracao 'ajuda_todas', Admin → Integrações):
 *     '1' ligado para todas (padrão), '0' desligado para todas.
 */
class AjudaLiberacao
{
    public const CHAVE_TODAS = 'ajuda_todas';

    public const MODOS = ['ligado', 'desligado'];

    public static function servidor(): bool
    {
        return (bool) config('ajuda.ativo');
    }

    /**
     * Lê SÓ a linha dela (não o PlataformaConfiguracao::get, que decifra todas):
     * o botão é desenhado em toda tela, e uma linha que não decifra (banco de
     * produção copiado para a homologação, outra APP_KEY) derrubava a página
     * inteira com "The MAC is invalid". Falhou = padrão (ligado).
     */
    public static function paraTodas(): bool
    {
        return Cache::remember(self::CHAVE_TODAS, 300, function () {
            try {
                $v = PlataformaConfiguracao::query()->whereKey(self::CHAVE_TODAS)->first()?->valor;
            } catch (\Throwable) {
                return true;
            }

            return $v === null || $v === '' || $v === '1';
        });
    }

    public static function definirParaTodas(bool $ligado): void
    {
        PlataformaConfiguracao::set(self::CHAVE_TODAS, $ligado ? '1' : '0');
        Cache::forget(self::CHAVE_TODAS);
    }

    public static function paraUsuario(?User $user): bool
    {
        if (! $user || ! self::servidor()) {
            return false;
        }
        $empresa = $user->empresa_id ? \App\Models\Empresa::withoutGlobalScopes()->find($user->empresa_id) : null;

        return $empresa ? $empresa->ajudaLigada() : self::paraTodas();
    }
}
