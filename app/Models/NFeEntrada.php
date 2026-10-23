<?php

namespace App\Models;

use App\Traits\AuditableModel;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Entrada de mercadoria pela NF-e de compra (23/10/2026).
 *
 * Nasce como rascunho quando o XML é lido; vira concluída quando o estoque é
 * lançado (EntradaNFeService::concluir). A chave é única por empresa: a mesma
 * nota não entra duas vezes, nem por outra loja.
 */
class NFeEntrada extends Model
{
    use AuditableModel, BelongsToEmpresa, BelongsToUnidade;

    protected $table = 'nfe_entradas';

    protected $auditFields = ['status', 'estoque_id', 'fornecedor_id', 'atualizou_custo', 'contas_pagar_geradas'];

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'estoque_id',
        'nfe_recebida_id',
        'fornecedor_id',
        'chave_acesso',
        'numero',
        'serie',
        'cnpj_emitente',
        'nome_emitente',
        'cnpj_destinatario',
        'data_emissao',
        'valor_produtos',
        'valor_total',
        'duplicatas',
        'xml_path',
        'status',
        'atualizou_custo',
        'contas_pagar_geradas',
        'user_id',
        'concluida_por',
        'concluida_em',
    ];

    protected function casts(): array
    {
        return [
            'data_emissao'    => 'date',
            'valor_produtos'  => 'decimal:2',
            'valor_total'     => 'decimal:2',
            'duplicatas'      => 'array',
            'atualizou_custo' => 'boolean',
            'concluida_em'    => 'datetime',
        ];
    }

    public function concluida(): bool
    {
        return $this->status === 'concluida';
    }

    public function itens(): HasMany
    {
        return $this->hasMany(NFeEntradaItem::class, 'nfe_entrada_id')->orderBy('numero_item');
    }

    public function estoque(): BelongsTo
    {
        return $this->belongsTo(Estoque::class);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function nfeRecebida(): BelongsTo
    {
        return $this->belongsTo(NFeRecebida::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function concluidor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concluida_por');
    }
}
