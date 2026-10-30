<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grupo de opções reutilizável ("Tamanho", "Leite", "Extras"). minimo 0 =
 * opcional, minimo ≥ 1 = obrigatório; maximo 1 = escolha única.
 */
class GrupoOpcao extends Model
{
    use BelongsToEmpresa;

    protected $table = 'grupos_opcoes';

    protected $fillable = ['empresa_id', 'nome', 'minimo', 'maximo', 'status'];

    protected function casts(): array
    {
        return ['minimo' => 'integer', 'maximo' => 'integer'];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(GrupoOpcaoItem::class)->orderBy('ordem')->orderBy('id');
    }

    public function produtos(): BelongsToMany
    {
        return $this->belongsToMany(Produto::class, 'produto_grupo_opcao')->withPivot('ordem');
    }

    public function obrigatorio(): bool
    {
        return $this->minimo > 0;
    }

    /** "Escolha 1 (obrigatório)", "Até 3 (opcional)". */
    public function regraTexto(): string
    {
        $qtd = $this->maximo <= 1 ? 'Escolha 1' : ($this->minimo === $this->maximo ? "Escolha {$this->maximo}" : "Até {$this->maximo}");

        return $qtd . ($this->obrigatorio() ? ' (obrigatório)' : ' (opcional)');
    }
}
