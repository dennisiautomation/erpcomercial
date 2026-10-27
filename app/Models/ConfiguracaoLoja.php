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
        // Preço cadastrado = preço do cartão; PIX/dinheiro com desconto (25/10/2026)
        'preco_base',
        'desconto_avista_percentual',
        'percentual_debito',
        'percentual_credito',
        'max_parcelas',
        'juros_por_parcela',
        'pdv_mostrar_valor_parcelas',
        'cupom_automatico_cartao',
        'cpf_emite_fiscal',
        'padrao_impressao',
        // PDV escolhe de qual estoque a venda sai (22/10/2026)
        'pdv_escolher_estoque',
        'os_cabecalho',
        'os_termos_garantia',
        'os_texto_legal',
        'os_rodape',
        'os_mostrar_assinatura',
        'os_mostrar_laudo',
        'os_mostrar_valores',
        // Impressão do orçamento (22/10/2026)
        'orcamento_endereco',
        'orcamento_endereco_comercial',
        'orcamento_telefone',
        'orcamento_whatsapp',
        'orcamento_email',
        'orcamento_site',
        'orcamento_condicoes',
        'orcamento_observacoes',
        'orcamento_rodape',
        'orcamento_parcelas',
        // Trocas (03/09/2026)
        'troca_prazo_dias',
        'troca_sobra',
        'troca_vale_validade_dias',
        'troca_senha_gerente',
    ];

    /**
     * Blocos da OS impressa aparecem por padrão — loja que nunca abriu a tela de
     * configuração continua imprimindo a OS completa, como antes.
     */
    protected $attributes = [
        'os_mostrar_assinatura' => true,
        'os_mostrar_laudo'      => true,
        'os_mostrar_valores'    => true,
        // Orçamento sai com o endereço da própria loja até alguém escolher outro
        'orcamento_endereco'    => 'loja',
        'pdv_escolher_estoque'  => false,
        'preco_base'            => 'avista',
        'desconto_avista_percentual' => 0,
        // Trocas: loja que nunca abriu a tela troca em até 30 dias, sobra vira
        // vale de 90 dias e fora da política pede o gerente.
        'troca_prazo_dias'         => 30,
        'troca_sobra'              => 'vale',
        'troca_vale_validade_dias' => 90,
        'troca_senha_gerente'      => true,
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
            'pdv_mostrar_valor_parcelas' => 'boolean',
            'os_mostrar_assinatura'      => 'boolean',
            'os_mostrar_laudo'           => 'boolean',
            'os_mostrar_valores'         => 'boolean',
            'troca_prazo_dias'           => 'integer',
            'troca_vale_validade_dias'   => 'integer',
            'troca_senha_gerente'        => 'boolean',
            'pdv_escolher_estoque'       => 'boolean',
            'desconto_avista_percentual' => 'decimal:2',
            'orcamento_parcelas'         => 'integer',
        ];
    }

    /**
     * O preço cadastrado no produto é o do cartão (25/10/2026)? Então Dinheiro/PIX
     * leva `desconto_avista_percentual` e débito/crédito cobram o preço cheio.
     */
    public function precoBaseCartao(): bool
    {
        return ($this->preco_base ?? 'avista') === 'cartao';
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

    /**
     * O que vai no topo do orçamento impresso (22/10/2026). Um lugar só, usado
     * pela folha e pela pré-visualização em Configurações da Loja.
     *
     * Endereço: 'loja' = cadastro da unidade (cai no da empresa campo a campo);
     * 'comercial' = o texto digitado aqui. Telefone/e-mail digitados aqui valem
     * nos dois casos; em branco, saem os do cadastro. Logo: o da loja, senão o da empresa.
     *
     * @return array{nome:string, razao:?string, cnpj:?string, logo:?string, endereco:array<int,string>, contatos:array<int,string>}
     */
    public function cabecalhoOrcamento(Empresa $empresa, ?Unidade $unidade): array
    {
        $campo = fn (string $c) => filled($unidade?->{$c}) ? $unidade->{$c} : $empresa->{$c};

        if ($this->orcamento_endereco === 'comercial' && filled($this->orcamento_endereco_comercial)) {
            $endereco = array_values(array_filter(array_map('trim', preg_split('/\R/', $this->orcamento_endereco_comercial))));
        } else {
            $rua = trim(implode(', ', array_filter([$campo('logradouro'), $campo('numero')])));
            $rua = trim(implode(' — ', array_filter([$rua, $campo('complemento')])));
            $cidade = trim(implode(' - ', array_filter([$campo('bairro'), trim(implode('/', array_filter([$campo('cidade'), $campo('uf')])))])));
            $cep = preg_replace('/\D/', '', (string) $campo('cep'));
            $endereco = array_values(array_filter([
                $rua,
                trim(implode(' · ', array_filter([$cidade, strlen($cep) === 8 ? 'CEP ' . substr($cep, 0, 5) . '-' . substr($cep, 5) : null]))),
            ]));
        }

        $telefone = $this->orcamento_telefone ?: $campo('telefone');
        $contatos = array_values(array_filter([
            filled($telefone) ? 'Tel.: ' . self::formatarTelefone($telefone) : null,
            filled($this->orcamento_whatsapp) ? 'WhatsApp: ' . self::formatarTelefone($this->orcamento_whatsapp) : null,
            $this->orcamento_email ?: $empresa->email,
            $this->orcamento_site,
        ]));

        $logo = $unidade?->logo ?: $empresa->logo;

        return [
            'nome'     => $empresa->nome_fantasia ?: $empresa->razao_social,
            'razao'    => $empresa->nome_fantasia && $empresa->razao_social !== $empresa->nome_fantasia ? $empresa->razao_social : null,
            'cnpj'     => self::formatarDocumento($unidade?->cnpj ?: $empresa->cnpj),
            'logo'     => $logo ? asset('storage/' . $logo) : null,
            'endereco' => $endereco,
            'contatos' => $contatos,
        ];
    }

    /** CPF/CNPJ só com dígitos ganha pontuação; o resto (CNPJ alfanumérico, já formatado) volta como veio. */
    public static function formatarDocumento(?string $doc): ?string
    {
        $d = preg_replace('/\D/', '', (string) $doc);
        if (strlen($d) === 14 && $d === preg_replace('/[^0-9]/', '', (string) $doc) && ! preg_match('/[A-Za-z]/', (string) $doc)) {
            return vsprintf('%s.%s.%s/%s-%s', [substr($d, 0, 2), substr($d, 2, 3), substr($d, 5, 3), substr($d, 8, 4), substr($d, 12)]);
        }
        if (strlen($d) === 11 && ! preg_match('/[A-Za-z]/', (string) $doc)) {
            return vsprintf('%s.%s.%s-%s', [substr($d, 0, 3), substr($d, 3, 3), substr($d, 6, 3), substr($d, 9)]);
        }

        return $doc ?: null;
    }

    /** "8681751869" → "(86) 8175-1869"; o que não for 10/11 dígitos volta como veio. */
    public static function formatarTelefone(string $telefone): string
    {
        $d = preg_replace('/\D/', '', $telefone);
        if (strlen($d) === 11) {
            return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
        }
        if (strlen($d) === 10) {
            return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
        }

        return $telefone;
    }
}
