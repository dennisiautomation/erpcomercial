<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Imagem enviada pelo lojista para usar nas etiquetas.
 *
 * O item do layout guarda só o id; quem resolve o arquivo é sempre o servidor,
 * conferindo a empresa dona. Ver a migration para o porquê.
 */
class EtiquetaImagem extends Model
{
    use BelongsToEmpresa;

    protected $table = 'etiqueta_imagens';

    protected $fillable = ['empresa_id', 'nome', 'caminho'];

    /** URL pública (depende do `php artisan storage:link`). */
    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->caminho);
    }

    /** Apaga o arquivo junto com o registro — galeria não guarda órfão. */
    protected static function booted(): void
    {
        static::deleting(function (self $imagem) {
            Storage::disk('public')->delete($imagem->caminho);
        });
    }
}
