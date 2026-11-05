<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Período de promoção (05/11/2026): vale nas lojas escolhidas, do dia data_inicio
 * ao dia data_fim (inclusive). O preço promocional é o preço À VISTA (Dinheiro/PIX);
 * o cartão leva o acréscimo da regra da loja por cima — ver TabelaPrecoService.
 *
 * Status (calculado): agendada → vigente → encerrada; "encerrada antes" quando
 * alguém encerrou no meio (encerrada_em), e aí vale só até o dia anterior.
 */
class Promocao extends Model
{
    use BelongsToEmpresa;

    protected $table = 'promocoes';

    protected $fillable = [
        'empresa_id', 'nome', 'situacao', 'rascunho', 'data_inicio', 'data_fim',
        'encerrada_em', 'encerrada_por', 'origem_id', 'criada_por', 'atualizada_por',
    ];

    public const RASCUNHO = 'rascunho';
    public const PUBLICADA = 'publicada';

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'encerrada_em' => 'datetime',
        'rascunho' => 'array',
    ];

    public function itens(): HasMany
    {
        return $this->hasMany(PromocaoItem::class, 'promocao_id');
    }

    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(Unidade::class, 'promocao_unidade', 'promocao_id', 'unidade_id');
    }

    public function origem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'origem_id');
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criada_por');
    }

    public function atualizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atualizada_por');
    }

    public function eRascunho(): bool
    {
        return $this->situacao === self::RASCUNHO;
    }

    /** Pode abrir no assistente para mudar: rascunho, ou publicada que ainda não começou. */
    public function podeEditar(): bool
    {
        return $this->eRascunho() || $this->status() === 'agendada';
    }

    /** Só a publicada vale para preço, conflito e "lista da última". */
    public function scopePublicadas(Builder $q): Builder
    {
        return $q->where('situacao', self::PUBLICADA);
    }

    /** Último dia em que a promoção vale (o dia anterior ao encerramento antecipado). */
    public function ultimoDia(): ?CarbonInterface
    {
        if (! $this->data_fim) {
            return null;
        }
        if ($this->encerrada_em) {
            $vespera = $this->encerrada_em->copy()->startOfDay()->subDay();

            return $vespera->lt($this->data_fim) ? $vespera : $this->data_fim;
        }

        return $this->data_fim;
    }

    public function status(?CarbonInterface $hoje = null): string
    {
        if ($this->eRascunho()) {
            return 'rascunho';
        }

        $hoje = ($hoje ?? now())->copy()->startOfDay();
        $ultimo = $this->ultimoDia();
        $antecipada = $this->encerrada_em && $ultimo->lt($this->data_fim);

        if ($hoje->lt($this->data_inicio)) {
            return $antecipada ? 'encerrada_antes' : 'agendada';
        }
        if ($hoje->lte($ultimo)) {
            return 'vigente';
        }

        return $antecipada ? 'encerrada_antes' : 'encerrada';
    }

    public function statusRotulo(): string
    {
        return match ($this->status()) {
            'agendada' => 'Agendada',
            'vigente' => 'Em andamento',
            'encerrada' => 'Concluída',
            'encerrada_antes' => 'Encerrada antes do fim',
            'rascunho' => 'Rascunho',
        };
    }

    /** Promoções que valem no dia (sem as encerradas antes daquele dia). */
    public function scopeValendoEm(Builder $q, CarbonInterface $dia): Builder
    {
        $d = $dia->toDateString();

        return $q->where('situacao', self::PUBLICADA)
            ->whereDate('data_inicio', '<=', $d)
            ->whereDate('data_fim', '>=', $d)
            ->where(fn ($w) => $w->whereNull('encerrada_em')->orWhereDate('encerrada_em', '>', $d));
    }
}
