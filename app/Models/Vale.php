<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Crédito na loja gerado por uma troca/devolução (03/09/2026).
 *
 * O cliente devolve R$ 150 e leva R$ 100: os R$ 50 viram um vale com código
 * impresso, saldo e validade. O PDV aceita o código como forma de pagamento
 * (uso parcial permitido — o que sobra continua no vale).
 *
 * Sem BelongsToUnidade de propósito: o vale é da EMPRESA — emitido numa loja,
 * vale em qualquer loja da mesma empresa.
 */
class Vale extends Model
{
    use BelongsToEmpresa;

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'cliente_id',
        'devolucao_id',
        'user_id',
        'codigo',
        'valor',
        'saldo',
        'validade',
        'status',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'valor'    => 'decimal:2',
            'saldo'    => 'decimal:2',
            'validade' => 'date',
        ];
    }

    /** Letras/números sem os ambíguos (0/O, 1/I/L) — o caixa digita o que lê. */
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function gerarCodigo(): string
    {
        do {
            $bruto = '';
            for ($i = 0; $i < 8; $i++) {
                $bruto .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
            }
            $codigo = 'VT-' . substr($bruto, 0, 4) . '-' . substr($bruto, 4);
        } while (static::withoutGlobalScopes()->where('codigo', $codigo)->exists());

        return $codigo;
    }

    /** Normaliza o que o caixa digitou/bipou: caixa alta, sem espaços, com os traços. */
    public static function normalizarCodigo(string $codigo): string
    {
        $limpo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codigo));
        if (Str::startsWith($limpo, 'VT') && strlen($limpo) === 10) {
            return 'VT-' . substr($limpo, 2, 4) . '-' . substr($limpo, 6);
        }

        return strtoupper(trim($codigo));
    }

    public function expirado(): bool
    {
        return $this->validade !== null && $this->validade->lt(today());
    }

    /** Motivo pelo qual NÃO pode ser usado agora — null quando pode. */
    public function motivoIndisponivel(): ?string
    {
        if ($this->status === 'cancelado') {
            return 'Este vale foi cancelado.';
        }
        if ($this->status === 'utilizado' || (float) $this->saldo <= 0) {
            return 'Este vale já foi totalmente utilizado.';
        }
        if ($this->status === 'expirado' || $this->expirado()) {
            return 'Este vale venceu em ' . $this->validade?->format('d/m/Y') . '.';
        }

        return null;
    }

    public function podeUsar(): bool
    {
        return $this->motivoIndisponivel() === null;
    }

    /** Abate um valor do saldo e registra o uso. Chamar dentro de transação, com lock. */
    public function abater(float $valor, string $tipo, ?int $vendaId, ?int $userId = null): ValeUso
    {
        $valor = round($valor, 2);
        if ($valor <= 0 || $valor > (float) $this->saldo + 0.001) {
            throw new \DomainException('Valor maior que o saldo do vale.');
        }

        $this->saldo = round((float) $this->saldo - $valor, 2);
        if ($this->saldo <= 0) {
            $this->saldo = 0;
            $this->status = 'utilizado';
        }
        $this->save();

        return $this->usos()->create([
            'venda_id' => $vendaId,
            'user_id'  => $userId ?? auth()->id(),
            'tipo'     => $tipo,
            'valor'    => $valor,
        ]);
    }

    public function statusLabel(): string
    {
        if ($this->status === 'ativo' && $this->expirado()) {
            return 'Vencido';
        }

        return match ($this->status) {
            'ativo'     => 'Ativo',
            'utilizado' => 'Utilizado',
            'expirado'  => 'Vencido',
            'cancelado' => 'Cancelado',
            default     => ucfirst($this->status),
        };
    }

    public function statusColor(): string
    {
        if ($this->status === 'ativo' && $this->expirado()) {
            return 'warning';
        }

        return match ($this->status) {
            'ativo'     => 'success',
            'utilizado' => 'secondary',
            'expirado'  => 'warning',
            'cancelado' => 'danger',
            default     => 'secondary',
        };
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class)->withoutGlobalScopes();
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function devolucao(): BelongsTo
    {
        return $this->belongsTo(Devolucao::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function usos(): HasMany
    {
        return $this->hasMany(ValeUso::class);
    }
}
