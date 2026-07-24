<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaixaAnexo extends Model
{
    use BelongsToEmpresa, BelongsToUnidade;

    protected $table = 'caixa_anexos';

    public const TIPOS_LABELS = [
        'maquina' => 'Fechamento da máquina',
        'credito' => 'Comprovante cartão de crédito',
        'debito'  => 'Comprovante cartão de débito',
    ];

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'caixa_id',
        'tipo',
        'arquivo',
        'nome_original',
        'user_id',
    ];

    public function caixa(): BelongsTo
    {
        return $this->belongsTo(Caixa::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tipoLabel(): string
    {
        return self::TIPOS_LABELS[$this->tipo] ?? ucfirst($this->tipo);
    }
}
