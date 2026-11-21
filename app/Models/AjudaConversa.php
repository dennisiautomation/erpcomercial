<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conversa do botão "Ajuda". Sem EmpresaScope de propósito: o dono da conversa
 * é o USUÁRIO (toda consulta filtra user_id na mão), e o admin da IA365 — que não
 * tem empresa — também conversa.
 */
class AjudaConversa extends Model
{
    protected $table = 'ajuda_conversas';

    protected $fillable = [
        'empresa_id', 'unidade_id', 'user_id', 'titulo',
        'chamado_protocolo', 'chamado_aberto_em', 'avaliacao',
    ];

    protected function casts(): array
    {
        return ['chamado_aberto_em' => 'datetime'];
    }

    public function mensagens(): HasMany
    {
        return $this->hasMany(AjudaMensagem::class, 'conversa_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
