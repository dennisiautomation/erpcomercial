<?php

namespace App\Enums;

/**
 * Canal por onde a venda foi fechada com o cliente (05/09/2026).
 *
 * Diferente de `vendas.tipo` (pdv/balcao/pedido/importada = de QUAL TELA a
 * venda nasceu no sistema), o canal diz COMO o cliente comprou: no balcão,
 * pelo WhatsApp ou por outro meio online. É o que o Gersen usa para separar
 * a conversão presencial da conversão por WhatsApp de cada vendedor.
 *
 * Valores em minúsculas e fixos — são o contrato da API de integração
 * (`GET /api/integracao/v1/canais`): o Gersen mapeia cada valor para o
 * `SaleOrigin` dele uma única vez.
 */
enum CanalVenda: string
{
    case Presencial = 'presencial';
    case Whatsapp = 'whatsapp';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::Presencial => 'Presencial',
            self::Whatsapp => 'WhatsApp',
            self::Online => 'Online',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Presencial => 'secondary',
            self::Whatsapp => 'success',
            self::Online => 'info',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Presencial => 'bi-shop',
            self::Whatsapp => 'bi-whatsapp',
            self::Online => 'bi-globe',
        };
    }

    /** @return array<string, string> valor => rótulo, para selects */
    public static function opcoes(): array
    {
        $out = [];
        foreach (self::cases() as $c) {
            $out[$c->value] = $c->label();
        }

        return $out;
    }

    /** Regra de validação `in:` com os valores válidos. */
    public static function regraIn(): string
    {
        return 'in:' . implode(',', array_map(fn (self $c) => $c->value, self::cases()));
    }
}
