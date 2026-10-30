<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uma opção do grupo = um produto do tipo adicional (preço próprio ou o do adicional). */
class GrupoOpcaoItem extends Model
{
    use BelongsToEmpresa;

    protected $table = 'grupo_opcao_itens';

    protected $fillable = ['empresa_id', 'grupo_opcao_id', 'adicional_id', 'preco', 'ordem'];

    protected function casts(): array
    {
        return ['preco' => 'decimal:2'];
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(GrupoOpcao::class, 'grupo_opcao_id');
    }

    public function adicional(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'adicional_id');
    }

    /** Quanto a opção soma ao item: o preço do grupo ou, sem ele, o do adicional. */
    public function precoEfetivo(): float
    {
        return $this->preco !== null ? (float) $this->preco : (float) ($this->adicional?->preco_venda ?? 0);
    }
}
