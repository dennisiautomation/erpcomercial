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
        'juros_por_parcela',
        'cupom_automatico_cartao',
        'cpf_emite_fiscal',
        'padrao_impressao',
        'os_cabecalho',
        'os_termos_garantia',
        'os_texto_legal',
        'os_rodape',
        'os_mostrar_assinatura',
        'os_mostrar_laudo',
        'os_mostrar_valores',
    ];

    /**
     * Blocos da OS impressa aparecem por padrão — loja que nunca abriu a tela de
     * configuração continua imprimindo a OS completa, como antes.
     */
    protected $attributes = [
        'os_mostrar_assinatura' => true,
        'os_mostrar_laudo'      => true,
        'os_mostrar_valores'    => true,
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
            'juros_por_parcela'          => 'array',
            'os_mostrar_assinatura'      => 'boolean',
            'os_mostrar_laudo'           => 'boolean',
            'os_mostrar_valores'         => 'boolean',
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
