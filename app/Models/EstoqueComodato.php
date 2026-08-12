<?php

namespace App\Models;

use App\Enums\StatusComodato;
use App\Traits\AuditableModel;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Peça que saiu em bonificação e deve voltar (influencer, showroom, editorial).
 *
 * O saldo de estoque já foi baixado pela movimentação de bonificação — este
 * registro NÃO mexe em saldo, só responde "com quem está e até quando".
 */
class EstoqueComodato extends Model
{
    use AuditableModel, BelongsToEmpresa, BelongsToUnidade;

    protected $auditFields = [
        'produto_id', 'quantidade', 'quantidade_devolvida', 'responsavel',
        'data_prevista_retorno', 'data_retorno', 'status',
    ];

    protected $table = 'estoque_comodatos';

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'estoque_movimentacao_id',
        'produto_id',
        'quantidade',
        'quantidade_devolvida',
        'responsavel',
        'contato',
        'data_saida',
        'data_prevista_retorno',
        'data_retorno',
        'status',
        'observacoes',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusComodato::class,
            'quantidade' => 'decimal:3',
            'quantidade_devolvida' => 'decimal:3',
            'data_saida' => 'date',
            'data_prevista_retorno' => 'date',
            'data_retorno' => 'date',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function movimentacao(): BelongsTo
    {
        return $this->belongsTo(EstoqueMovimentacao::class, 'estoque_movimentacao_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* ------------------------------------------------------------------ */
    /*  Scopes                                                             */
    /* ------------------------------------------------------------------ */

    /** Peças que ainda estão fora da loja. */
    public function scopeEmAberto(Builder $query): Builder
    {
        return $query->whereIn('status', StatusComodato::valoresEmAberto());
    }

    /** Em aberto e com a data prevista já vencida. */
    public function scopeAtrasado(Builder $query): Builder
    {
        return $query->emAberto()->whereDate('data_prevista_retorno', '<', now()->toDateString());
    }

    /* ------------------------------------------------------------------ */
    /*  Derivados                                                          */
    /* ------------------------------------------------------------------ */

    /** Quanto ainda falta voltar. */
    public function getQuantidadePendenteAttribute(): float
    {
        return max(0, (float) $this->quantidade - (float) $this->quantidade_devolvida);
    }

    public function getEstaAtrasadoAttribute(): bool
    {
        return $this->status->emAberto()
            && $this->data_prevista_retorno?->isBefore(now()->startOfDay());
    }

    /** Dias de atraso (0 quando está no prazo ou já voltou). */
    public function getDiasAtrasoAttribute(): int
    {
        if (! $this->esta_atrasado) {
            return 0;
        }

        return (int) $this->data_prevista_retorno->diffInDays(now()->startOfDay());
    }

    /**
     * Recalcula o status a partir do que já voltou. Não salva.
     */
    public function recalcularStatus(): StatusComodato
    {
        if ($this->quantidade_pendente <= 0) {
            return StatusComodato::Devolvido;
        }

        return (float) $this->quantidade_devolvida > 0
            ? StatusComodato::Parcial
            : StatusComodato::Pendente;
    }
}
