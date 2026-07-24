<?php

namespace App\Models;

use App\Traits\AuditableModel;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Model;

class ConfiguracaoLoja extends Model
{
    use BelongsToEmpresa, BelongsToUnidade, AuditableModel;

    protected $table = 'configuracoes_loja';

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'vendedor_responsavel_caixa',
        'regra_preco_split',
        'percentual_debito',
        'percentual_credito',
        'max_parcelas',
        'cupom_automatico_cartao',
        'cpf_emite_fiscal',
        'padrao_impressao',
    ];

    protected function casts(): array
    {
        return [
            'vendedor_responsavel_caixa' => 'boolean',
            'cupom_automatico_cartao'    => 'boolean',
            'cpf_emite_fiscal'           => 'boolean',
            'percentual_debito'          => 'decimal:2',
            'percentual_credito'         => 'decimal:2',
            'max_parcelas'               => 'integer',
        ];
    }

    /**
     * Config da unidade informada (ou da sessão). Quando não existe registro,
     * devolve instância NÃO persistida com os defaults — os fluxos (PDV, caixa,
     * etiquetas) podem ler direto sem se preocupar se a loja já configurou algo.
     */
    public static function daUnidade(?int $empresaId = null, ?int $unidadeId = null): self
    {
        $empresaId ??= (int) session('empresa_id');
        $unidadeId ??= (int) session('unidade_id');

        // Unique (empresa_id, unidade_id): buscar sem scopes, nunca updateOrCreate
        $config = static::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('unidade_id', $unidadeId)
            ->first();

        return $config ?? new static([
            'empresa_id' => $empresaId,
            'unidade_id' => $unidadeId,
        ]);
    }
}
