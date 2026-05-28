<?php

namespace App\Enums;

/**
 * Como a empresa-cliente é (não) cobrada pela plataforma.
 *
 *  - padrao    : fluxo normal — trial expira, vira pago via assinatura
 *  - cortesia  : empresa não paga. Assinatura sempre ativa. Útil para
 *                clientes VIP, beta testers, amigos.
 *  - parceiro  : cortesia + acesso a TODAS as features (ignora plano).
 *                Útil para clientes-âncora / cases / parceiros estratégicos.
 *  - pos_pago  : não bloqueia hoje, mas fica registrado para cobrança
 *                manual posterior (faturamento mensal). Grandes contas.
 */
enum RegimeCobranca: string
{
    case Padrao = 'padrao';
    case Cortesia = 'cortesia';
    case Parceiro = 'parceiro';
    case PosPago = 'pos_pago';

    public function label(): string
    {
        return match ($this) {
            self::Padrao => 'Padrão (trial → pago)',
            self::Cortesia => 'Cortesia (gratuita)',
            self::Parceiro => 'Parceiro (gratuita + tudo liberado)',
            self::PosPago => 'Pós-pago (cobrança manual)',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::Padrao => 'Cliente segue o funil normal de trial e assinatura paga.',
            self::Cortesia => 'Cliente não é cobrado. Acesso vitalício ao plano atribuído.',
            self::Parceiro => 'Cliente não é cobrado e acessa todas as features (limites ignorados).',
            self::PosPago => 'Cliente não é bloqueado, mas IA365 fatura manualmente fora do sistema.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Padrao => 'primary',
            self::Cortesia => 'info',
            self::Parceiro => 'success',
            self::PosPago => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Padrao => 'credit-card',
            self::Cortesia => 'gift',
            self::Parceiro => 'stars',
            self::PosPago => 'receipt',
        };
    }

    /** Empresa neste regime nunca tem assinatura/trial bloqueando? */
    public function ehGratuito(): bool
    {
        return $this !== self::Padrao;
    }

    /** Empresa neste regime ignora limites/feature gating do plano? */
    public function ignoraLimitesPlano(): bool
    {
        return $this === self::Parceiro;
    }
}
