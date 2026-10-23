<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "O código X do fornecedor (CNPJ) é o meu produto Y, e 1 unidade dele = N minhas."
 * Gravado ao concluir uma entrada por NF-e; casa o item sozinho na próxima nota.
 */
class ProdutoFornecedorCodigo extends Model
{
    use BelongsToEmpresa;

    protected $table = 'produto_fornecedor_codigos';

    protected $fillable = [
        'empresa_id',
        'cnpj_emitente',
        'codigo_fornecedor',
        'produto_id',
        'fator_conversao',
    ];

    protected function casts(): array
    {
        return ['fator_conversao' => 'decimal:4'];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
