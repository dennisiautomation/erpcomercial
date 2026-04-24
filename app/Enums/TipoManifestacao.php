<?php

namespace App\Enums;

/**
 * Tipos de manifestação do destinatário previstos pela Receita Federal
 * (Anexo II da NT2012/003). Usados tanto no banco quanto no payload
 * que enviamos à Focus NFe.
 *
 *   210200 - Confirmação da operação
 *   210210 - Ciência da emissão (mais permissiva — não trava prazos)
 *   210220 - Desconhecimento (combate fraude)
 *   210240 - Operação não realizada
 */
enum TipoManifestacao: string
{
    case Ciencia = '210210';
    case Confirmacao = '210200';
    case NaoRealizada = '210240';
    case Desconhecimento = '210220';

    public function label(): string
    {
        return match ($this) {
            self::Ciencia        => 'Ciência da Emissão',
            self::Confirmacao    => 'Confirmação da Operação',
            self::NaoRealizada   => 'Operação Não Realizada',
            self::Desconhecimento => 'Desconhecimento da Operação',
        };
    }

    public function descricaoCurta(): string
    {
        return match ($this) {
            self::Ciencia        => 'Tenho conhecimento da emissão desta NF-e.',
            self::Confirmacao    => 'Confirmo que a operação foi realizada.',
            self::NaoRealizada   => 'A operação descrita não foi efetivamente realizada.',
            self::Desconhecimento => 'Desconheço esta operação — não autorizei a emissão.',
        };
    }

    public function severidade(): string
    {
        return match ($this) {
            self::Ciencia, self::Confirmacao => 'success',
            self::NaoRealizada               => 'warning',
            self::Desconhecimento            => 'danger',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Ciencia         => 'eye',
            self::Confirmacao     => 'check-circle',
            self::NaoRealizada    => 'slash-circle',
            self::Desconhecimento => 'shield-exclamation',
        };
    }

    /** Slug no padrão que a Focus aceita no endpoint de manifestação. */
    public function focusSlug(): string
    {
        return match ($this) {
            self::Ciencia         => 'ciencia',
            self::Confirmacao     => 'confirmacao',
            self::NaoRealizada    => 'nao_realizada',
            self::Desconhecimento => 'desconhecimento',
        };
    }
}
