<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Conta bancária da empresa ligada ao banco pelo Open Finance (17/11/2026).
 * Uma chave só da IA365 na Tecnospeed: o que separa as empresas é o empresa_id daqui.
 */
class ContaBancaria extends Model
{
    use BelongsToEmpresa, SoftDeletes;

    protected $table = 'contas_bancarias';

    protected $fillable = [
        'empresa_id', 'unidade_id', 'apelido', 'banco_codigo', 'banco_nome', 'agencia', 'agencia_digito',
        'conta', 'conta_digito', 'titular_documento', 'titular_nome',
        'account_hash', 'link_conexao', 'status_banco', 'situacao', 'conectada_em', 'desconectada_em',
        'protocolo_pendente', 'protocolo_inicio', 'protocolo_fim',
        'ultima_sincronizacao_em', 'proxima_sincronizacao_em', 'ultimo_erro',
        'saldo', 'saldo_bloqueado', 'saldo_aplicado', 'saldo_atualizado_em', 'saldo_proxima_em',
    ];

    protected function casts(): array
    {
        return [
            'conectada_em' => 'datetime',
            'desconectada_em' => 'datetime',
            'protocolo_inicio' => 'date',
            'protocolo_fim' => 'date',
            'ultima_sincronizacao_em' => 'datetime',
            'proxima_sincronizacao_em' => 'datetime',
            'saldo' => 'decimal:2',
            'saldo_bloqueado' => 'decimal:2',
            'saldo_aplicado' => 'decimal:2',
            'saldo_atualizado_em' => 'datetime',
            'saldo_proxima_em' => 'datetime',
        ];
    }

    /**
     * Bancos da lista da tela (busca por número ou nome). A Tecnospeed aceita qualquer código
     * de 3 dígitos; quem não achar o banco aqui pode digitar o número.
     */
    public const BANCOS = [
        '001' => 'Banco do Brasil', '033' => 'Santander', '104' => 'Caixa Econômica Federal', '237' => 'Bradesco',
        '341' => 'Itaú', '748' => 'Sicredi', '756' => 'Sicoob', '077' => 'Inter', '260' => 'Nubank',
        '336' => 'C6 Bank', '208' => 'BTG Pactual', '422' => 'Safra', '041' => 'Banrisul', '136' => 'Unicred',
        '212' => 'Banco Original', '290' => 'PagBank (PagSeguro)', '323' => 'Mercado Pago', '197' => 'Stone',
        '403' => 'Cora', '380' => 'PicPay', '070' => 'BRB — Banco de Brasília', '004' => 'Banco do Nordeste',
        '085' => 'Ailos', '003' => 'Banco da Amazônia', '021' => 'Banestes', '047' => 'Banese', '037' => 'Banpará',
        '655' => 'Banco BV (Votorantim)', '623' => 'Banco Pan', '318' => 'Banco BMG', '389' => 'Mercantil do Brasil',
        '707' => 'Daycoval', '745' => 'Citibank', '746' => 'Banco Modal', '218' => 'Banco BS2', '364' => 'Efí (Gerencianet)',
        '536' => 'Neon', '335' => 'Banco Digio', '121' => 'Agibank', '633' => 'Banco Rendimento', '133' => 'Cresol',
        '097' => 'Credisis', '099' => 'Uniprime', '461' => 'Asaas', '280' => 'Will Bank', '348' => 'Banco XP',
        '654' => 'Banco Digimais', '643' => 'Banco Pine', '604' => 'Banco Industrial', '329' => 'QI Tech',
        '450' => 'Fitbank', '542' => 'Cloudwalk (InfinitePay)', '274' => 'BMP Money Plus', '332' => 'Acesso Soluções de Pagamento',
    ];


    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function lancamentos(): HasMany
    {
        return $this->hasMany(LancamentoBancario::class);
    }

    public function cartoes(): HasMany
    {
        return $this->hasMany(CartaoCredito::class);
    }

    /** Ocupa uma vaga do limite: tem cadastro na Tecnospeed e não foi desconectada. */
    public function ocupaVaga(): bool
    {
        return $this->account_hash !== null && $this->situacao !== 'desconectada';
    }

    public function titularFormatado(): string
    {
        $d = $this->titular_documento;

        return strlen($d) === 14
            ? preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d)
            : preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d);
    }

    public function descricaoConta(): string
    {
        return trim(($this->banco_nome ?: 'Banco ' . $this->banco_codigo)
            . ' · ag. ' . $this->agencia . ' · conta ' . $this->conta
            . ($this->conta_digito !== null && $this->conta_digito !== '' ? '-' . $this->conta_digito : ''));
    }
}
