<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comissao extends Model
{
    use HasFactory, SoftDeletes, BelongsToEmpresa, BelongsToUnidade;

    protected $table = 'comissoes';

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'user_id',
        'venda_id',
        'valor_venda',
        'percentual',
        'valor_comissao',
        'status',
        'pago_em',
    ];

    protected function casts(): array
    {
        return [
            'valor_venda' => 'decimal:2',
            'percentual' => 'decimal:2',
            'valor_comissao' => 'decimal:2',
            'pago_em' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    /**
     * % de comissão do CADASTRO do funcionário (`users.comissao_percentual`).
     * Vazio ou 0 = sem comissão (11/11/2026). Antes o PDV gravava 5% fixo e a
     * venda de balcão usava 5% quando o campo estava vazio — ninguém tinha 5%
     * cadastrado e todas as 9.932 comissões do sistema estavam a 5%.
     */
    public static function percentualDoVendedor(int $userId): float
    {
        $pct = \App\Models\User::query()->whereKey($userId)->value('comissao_percentual');

        return max(0.0, (float) ($pct ?? 0));
    }
}
