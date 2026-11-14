<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto adicional do produto (14/11/2026). A capa continua em `produtos.foto`;
 * aqui ficam as outras, na ordem em que aparecem na galeria da loja online.
 *
 * Sem BelongsToEmpresa: a vitrine pública lê sem sessão. O acesso é sempre
 * pelo produto (que já é da empresa) ou com empresa_id explícito.
 */
class ProdutoFoto extends Model
{
    protected $table = 'produto_fotos';

    protected $fillable = ['empresa_id', 'produto_id', 'caminho', 'ordem'];

    /** Teto de fotos adicionais por produto. */
    public const MAXIMO = 8;

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
