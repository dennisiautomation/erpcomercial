<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AjudaMensagem extends Model
{
    protected $table = 'ajuda_mensagens';

    protected $fillable = [
        'conversa_id', 'empresa_id', 'papel', 'conteudo', 'ferramentas',
        'tool_call_id', 'pagina', 'tokens',
    ];

    protected function casts(): array
    {
        return ['ferramentas' => 'array'];
    }

    public function conversa(): BelongsTo
    {
        return $this->belongsTo(AjudaConversa::class, 'conversa_id');
    }
}
