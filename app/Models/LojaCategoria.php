<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Categoria da VITRINE da loja online (27/11/2026). Sem BelongsToEmpresa: a vitrine
 * pública lê sem sessão do ERP — todo acesso filtra empresa_id explicitamente.
 * A regra (o que entra, contagem, filtro) mora em App\Services\LojaOnline\CategoriasLoja.
 */
class LojaCategoria extends Model
{
    protected $table = 'loja_categorias';

    protected $fillable = ['empresa_id', 'nome', 'ordem', 'foto', 'ativa', 'categorias_erp', 'produtos'];

    protected function casts(): array
    {
        return ['ativa' => 'boolean', 'categorias_erp' => 'array', 'produtos' => 'array', 'ordem' => 'integer'];
    }

    /** @return array<int, int> */
    public function idsCategorias(): array
    {
        return array_values(array_unique(array_map('intval', $this->categorias_erp ?? [])));
    }

    /** @return array<int, int> */
    public function idsProdutos(): array
    {
        return array_values(array_unique(array_map('intval', $this->produtos ?? [])));
    }
}
