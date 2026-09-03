<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Troca ou devolução de itens de uma venda (03/09/2026).
 *
 * `tipo`:
 *   - troca      → o cliente devolve e leva outro produto na hora (PDV, F6).
 *                  O valor devolvido vira crédito (vale) que o PDV abate na
 *                  venda nova; `venda_nova_id` liga as duas.
 *   - devolucao  → o cliente devolve e não leva nada agora. A sobra vira vale
 *                  ou dinheiro (Configurações da Loja → Trocas).
 *
 * `forma_sobra`: o que aconteceu com o valor devolvido depois de abater
 * parcelas em aberto — `vale`, `dinheiro`, `parcelas` (tudo foi abatido) ou
 * `nenhuma` (valor zero).
 */
class Devolucao extends Model
{
    use HasFactory, SoftDeletes, BelongsToEmpresa, BelongsToUnidade;

    protected $table = 'devolucoes';

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'venda_id',
        'tipo',
        'venda_nova_id',
        'vale_id',
        'caixa_id',
        'user_id',
        'motivo',
        'valor_estornado',
        'forma_sobra',
        'valor_sobra',
        'valor_abatido_parcelas',
        'fora_politica',
        'motivo_fora_politica',
        'aprovado_por',
        'status',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'valor_estornado'        => 'decimal:2',
            'valor_sobra'            => 'decimal:2',
            'valor_abatido_parcelas' => 'decimal:2',
            'fora_politica'          => 'boolean',
        ];
    }

    public const MOTIVOS = [
        'tamanho'       => 'Tamanho / numeração',
        'defeito'       => 'Defeito',
        'arrependimento'=> 'Desistência / arrependimento',
        'presente'      => 'Troca de presente',
        'outro'         => 'Outro',
    ];

    public function tipoLabel(): string
    {
        return $this->tipo === 'troca' ? 'Troca' : 'Devolução';
    }

    public function formaSobraLabel(): string
    {
        return match ($this->forma_sobra) {
            'vale'     => 'Crédito na loja (vale)',
            'dinheiro' => 'Dinheiro devolvido',
            'parcelas' => 'Abatido das parcelas',
            'nenhuma'  => '—',
            default    => '—',
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

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class)->withoutGlobalScopes();
    }

    public function vendaNova(): BelongsTo
    {
        return $this->belongsTo(Venda::class, 'venda_nova_id')->withoutGlobalScopes();
    }

    public function vale(): BelongsTo
    {
        return $this->belongsTo(Vale::class);
    }

    public function caixa(): BelongsTo
    {
        return $this->belongsTo(Caixa::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aprovador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovado_por');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(DevolucaoItem::class);
    }
}
