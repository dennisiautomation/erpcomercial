<?php

namespace App\Models;

use App\Traits\AuditableModel;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eventos avançados de NF-e:
 *   - ator_interessado  (marketplace, transportadora, seguradora, etc.)
 *   - insucesso_entrega (NT 2021.002)
 *   - epec              (contingência offline)
 *
 * Cada evento é persistido junto à NotaFiscal com o payload variável em JSON.
 * Carta de Correção (CC-e) continua em tabela própria — é legado.
 */
class NFeEvento extends Model
{
    use BelongsToEmpresa, BelongsToUnidade, AuditableModel;

    protected $table = 'nfe_eventos';

    public const TIPO_ATOR_INTERESSADO = 'ator_interessado';
    public const TIPO_INSUCESSO_ENTREGA = 'insucesso_entrega';
    public const TIPO_EPEC = 'epec';

    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_AUTORIZADO = 'autorizado';
    public const STATUS_REJEITADO = 'rejeitado';
    public const STATUS_CANCELADO = 'cancelado';

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'nota_fiscal_id',
        'tipo',
        'sequencia',
        'dados',
        'status',
        'focus_ref',
        'protocolo',
        'mensagem_retorno',
        'xml_url',
        'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'dados' => 'array',
        ];
    }

    public function notaFiscal(): BelongsTo
    {
        return $this->belongsTo(NotaFiscal::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function labelTipo(): string
    {
        return match ($this->tipo) {
            self::TIPO_ATOR_INTERESSADO => 'Ator Interessado',
            self::TIPO_INSUCESSO_ENTREGA => 'Insucesso de Entrega',
            self::TIPO_EPEC => 'EPEC (contingência)',
            default => ucfirst(str_replace('_', ' ', $this->tipo)),
        };
    }
}
