<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\FormaPagamento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Formas de pagamento da empresa (14/11/2026) — ponto único.
 *
 * Onde cada coisa vive: o CADASTRO das formas (nome, ícone, cor, ordem, largura,
 * ligada/desligada) mora em `formas_pagamento`, por empresa; a VENDA continua
 * gravando em `pagamento_detalhes.forma` o código de sempre (dinheiro, pix,
 * cartao_credito...) e só ganha `forma_nome`/`forma_chave` ao lado.
 *
 * Empresa sem linha nenhuma = os botões de antes, na ordem de antes
 * (padrao()). Nada é gravado até o lojista salvar a tela.
 */
class FormaPagamentoService
{
    /** Como a forma se comporta. A chave é o código gravado na venda. */
    public const TIPOS = [
        'dinheiro'       => 'Dinheiro',
        'cartao_credito' => 'Cartão de crédito',
        'cartao_debito'  => 'Cartão de débito',
        'pix'            => 'PIX',
        'boleto'         => 'Boleto',
        'crediario'      => 'Crediário',
        'transferencia'  => 'Transferência',
        'vale'           => 'Vale (crédito de troca)',
    ];

    /** Tipos que uma forma CRIADA pode ter. Vale não: ele exige o código impresso na troca. */
    public const TIPOS_FORMA_NOVA = [
        'dinheiro', 'cartao_credito', 'cartao_debito', 'pix', 'boleto', 'crediario', 'transferencia',
    ];

    /** Rótulo de cada código gravado — para venda antiga, sem forma_nome. */
    public const ROTULOS = [
        'dinheiro'       => 'Dinheiro',
        'cartao_credito' => 'Cartão de crédito',
        'cartao_debito'  => 'Cartão de débito',
        'pix'            => 'PIX',
        'boleto'         => 'Boleto',
        'crediario'      => 'Crediário',
        'transferencia'  => 'Transferência',
        'vale'           => 'Vale',
        'credishop'      => 'Credishop',
        'misto'          => 'Misto',
    ];

    public const ICONES = [
        'bi-cash-stack', 'bi-cash', 'bi-coin', 'bi-wallet2', 'bi-piggy-bank',
        'bi-credit-card', 'bi-credit-card-2-front', 'bi-credit-card-fill', 'bi-credit-card-2-back',
        'bi-qr-code', 'bi-upc-scan', 'bi-bank', 'bi-journal-text', 'bi-ticket-perforated',
        'bi-receipt', 'bi-phone', 'bi-whatsapp', 'bi-shop', 'bi-gift', 'bi-star', 'bi-lightning-charge',
    ];

    public const CORES = [
        '#22c55e', '#eab308', '#06b6d4', '#a855f7', '#fd7e14', '#ef4444',
        '#3b82f6', '#20c997', '#6f42c1', '#ec4899', '#64748b', '#f59e0b',
    ];

    /**
     * Os botões de hoje, exatamente como o PDV desenhava até 13/11/2026:
     * mesma ordem, mesmo texto, mesmo ícone, Credishop em linha inteira e só
     * para a empresa com a chave `pdv_credishop`.
     */
    public function padrao(?Empresa $empresa): array
    {
        $linhas = [
            ['chave' => 'dinheiro',       'nome' => 'Dinheiro',  'icone' => 'bi-cash-stack',          'cor' => '#22c55e'],
            ['chave' => 'cartao_credito', 'nome' => 'Crédito',   'icone' => 'bi-credit-card',         'cor' => '#eab308'],
            ['chave' => 'cartao_debito',  'nome' => 'Débito',    'icone' => 'bi-credit-card-2-front', 'cor' => '#06b6d4'],
            ['chave' => 'pix',            'nome' => 'PIX',       'icone' => 'bi-qr-code',             'cor' => '#a855f7'],
            ['chave' => 'boleto',         'nome' => 'Boleto',    'icone' => 'bi-upc-scan',            'cor' => '#fd7e14'],
            ['chave' => 'crediario',      'nome' => 'Crediario', 'icone' => 'bi-journal-text',        'cor' => '#ef4444'],
            ['chave' => 'transferencia',  'nome' => 'Transf.',   'icone' => 'bi-bank',                'cor' => '#3b82f6'],
            ['chave' => 'vale',           'nome' => 'Vale',      'icone' => 'bi-ticket-perforated',   'cor' => '#20c997'],
        ];
        if ($empresa?->pdvCredishop()) {
            $linhas[] = ['chave' => 'credishop', 'nome' => 'Credishop', 'icone' => 'bi-credit-card-fill', 'cor' => '#6f42c1', 'largura' => 2];
        }

        foreach ($linhas as $i => $l) {
            $linhas[$i] = $l + [
                // Credishop se comporta como crédito (tabela de preço e acréscimo), mas grava 'credishop'
                'tipo'    => $l['chave'] === 'credishop' ? 'cartao_credito' : $l['chave'],
                'sistema' => true,
                'largura' => 1,
                'ordem'   => $i + 1,
                'ativo'   => true,
            ];
        }

        return $linhas;
    }

    /** Todas as formas da empresa (ligadas e desligadas), na ordem da tela. */
    public function todas(int $empresaId): Collection
    {
        $empresa = Empresa::find($empresaId);
        $padrao = collect($this->padrao($empresa))->keyBy('chave');

        $gravadas = FormaPagamento::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->orderBy('ordem')->orderBy('id')
            ->get();

        if ($gravadas->isEmpty()) {
            return $padrao->values()->map(fn ($a) => new FormaPagamento($a + ['empresa_id' => $empresaId]));
        }

        // Credishop desligado no admin some de todo lugar, mesmo que tenha sido gravado
        $lista = $gravadas->filter(fn (FormaPagamento $f) => $f->chave !== 'credishop' || $padrao->has('credishop'))->values();

        // Forma do sistema que passou a existir depois do save (ex.: Credishop ligado
        // depois) entra no fim, ligada — senão ninguém a encontraria.
        $max = (int) $lista->max('ordem');
        foreach ($padrao as $chave => $a) {
            if (! $lista->contains('chave', $chave)) {
                $lista->push(new FormaPagamento(array_merge($a, ['empresa_id' => $empresaId, 'ordem' => ++$max])));
            }
        }

        return $lista->values();
    }

    /** Só as ligadas — o que o PDV e as telas de venda mostram. */
    public function ativas(int $empresaId): Collection
    {
        return $this->todas($empresaId)->where('ativo', true)->values();
    }

    /** Forma ligada pela chave do botão/select, ou null. */
    public function resolver(int $empresaId, ?string $chave): ?FormaPagamento
    {
        if ($chave === null || $chave === '') {
            return null;
        }

        return $this->ativas($empresaId)->firstWhere('chave', $chave);
    }

    /**
     * Normaliza a lista de pagamentos que chega de uma tela de venda:
     * `forma` vira o código gravado e entram `forma_chave` e `forma_nome`.
     *
     * Aceita payload antigo (aba aberta antes do deploy: só `forma`, sem
     * `chave`) — aí a chave é a própria forma, que é como as do sistema se chamam.
     *
     * @throws \DomainException forma desligada ou inexistente
     */
    public function normalizarPagamentos(int $empresaId, array $pagamentos): array
    {
        foreach ($pagamentos as $i => $pgto) {
            $chave = (string) ($pgto['chave'] ?? $pgto['forma_chave'] ?? $pgto['forma'] ?? '');
            $forma = $this->resolver($empresaId, $chave);
            if (! $forma) {
                $nome = self::ROTULOS[$chave] ?? $chave;
                throw new \DomainException("A forma de pagamento \"{$nome}\" está desligada ou não existe nesta empresa. Recarregue a tela.");
            }
            unset($pgto['chave'], $pgto['nome']);
            $pgto['forma'] = $forma->codigoGravado();
            $pgto['forma_chave'] = $forma->chave;
            $pgto['forma_nome'] = $forma->nome;
            $pagamentos[$i] = $pgto;
        }

        return $pagamentos;
    }

    // ------------------------------------------------------------------
    // Cruzamento com relatórios e filtros (14/11/2026)
    //
    // Regra única: cada pagamento gravado pertence a UMA "linha de forma":
    //   - forma CRIADA pelo lojista → a chave dela (fp_xxxxxxxx), linha própria;
    //   - forma do sistema (mesmo renomeada) e venda antiga → o código gravado.
    // Filtro, quadro "por forma" e rateio usam esta mesma chave.
    // ------------------------------------------------------------------

    /** Linha de forma de um pagamento gravado. */
    public static function chaveDoPagamento(array $pg): string
    {
        $chave = (string) ($pg['forma_chave'] ?? '');
        if (str_starts_with($chave, 'fp_')) {
            return $chave;
        }

        return (string) ($pg['forma'] ?? 'outros') ?: 'outros';
    }

    /**
     * Nome de cada linha de forma da empresa: as configuradas (ligadas E desligadas,
     * com o nome de hoje) + os códigos de sempre que não têm botão (misto, outros...).
     * Forma criada e depois excluída não está aqui — quem lista usa o forma_nome gravado.
     *
     * @return array<string, string> chave => nome
     */
    public function rotulos(int $empresaId): array
    {
        $r = [];
        foreach ($this->todas($empresaId) as $f) {
            $r[$f->sistema ? $f->codigoGravado() : $f->chave] = $f->nome . ($f->ativo ? '' : ' (desligada)');
        }

        return $r + self::ROTULOS;
    }

    /** Opções do filtro "Forma de pagamento" dos relatórios: todas as configuradas, na ordem da tela. */
    public function opcoesFiltro(int $empresaId): array
    {
        $r = [];
        foreach ($this->todas($empresaId) as $f) {
            $r[$f->sistema ? $f->codigoGravado() : $f->chave] = $f->nome . ($f->ativo ? '' : ' (desligada)');
        }

        return $r;
    }

    /** Nome de uma linha de forma num relatório. */
    public function rotuloDaChave(int $empresaId, string $chave, ?string $nomeGravado = null): string
    {
        static $cache = [];
        $cache[$empresaId] ??= $this->rotulos($empresaId);

        return $cache[$empresaId][$chave] ?? ($nomeGravado ?: self::rotulo($chave));
    }

    // ------------------------------------------------------------------
    // Condição de pagamento do PEDIDO (14/11/2026)
    //
    // O select do pedido passa a listar as formas da empresa (valor = chave do
    // botão) + os prazos. Valores antigos (a_vista, cartao) continuam valendo:
    // são os que o agente IA e pedidos velhos gravaram.
    // ------------------------------------------------------------------

    public const CONDICOES_LEGADO = [
        'a_vista' => ['À vista', 'dinheiro'],
        'pix'     => ['PIX', 'pix'],
        'cartao'  => ['Cartão', 'cartao_credito'],
        'boleto'  => ['Boleto', 'boleto'],
    ];

    public const PRAZOS = [
        '30_dias'       => '30 dias',
        '30_60_dias'    => '30/60 dias',
        '30_60_90_dias' => '30/60/90 dias',
    ];

    /**
     * Opções do select "Condição de pagamento" do pedido.
     *
     * @return array{formas: array<string,string>, prazos: array<string,string>, legado: array<string,string>}
     */
    public function opcoesCondicao(int $empresaId, ?string $atual = null): array
    {
        $formas = $this->ativas($empresaId)->mapWithKeys(fn ($f) => [$f->chave => $f->nome])->all();
        $legado = [];
        // Pedido antigo com valor que não é chave de botão (a_vista, cartao) ou com forma desligada: mantém a opção
        if ($atual !== null && $atual !== '' && ! isset($formas[$atual]) && ! isset(self::PRAZOS[$atual])) {
            $legado[$atual] = $this->rotuloCondicao($empresaId, $atual);
        }

        return ['formas' => $formas, 'prazos' => self::PRAZOS, 'legado' => $legado];
    }

    /** Valores aceitos no POST da condição (o select inteiro + os antigos). */
    public function condicoesValidas(int $empresaId, ?string $atual = null): array
    {
        $o = $this->opcoesCondicao($empresaId, $atual);

        return array_keys($o['formas'] + $o['prazos'] + $o['legado'] + self::CONDICOES_LEGADO);
    }

    /** Código gravado (dinheiro, pix, cartao_credito...) que a condição representa, ou null (prazo/vazio). */
    public function codigoDaCondicao(int $empresaId, ?string $condicao): ?string
    {
        if ($condicao === null || $condicao === '' || isset(self::PRAZOS[$condicao])) {
            return null;
        }
        $f = $this->todas($empresaId)->firstWhere('chave', $condicao);
        if ($f) {
            return $f->codigoGravado();
        }

        return self::CONDICOES_LEGADO[$condicao][1] ?? null;
    }

    /** Botão LIGADO que corresponde à condição (sugestão do Faturar), ou null. */
    public function formaDaCondicao(int $empresaId, ?string $condicao): ?FormaPagamento
    {
        if ($condicao === null || $condicao === '') {
            return null;
        }
        $ativas = $this->ativas($empresaId);
        if ($f = $ativas->firstWhere('chave', $condicao)) {
            return $f;
        }
        $codigo = $this->codigoDaCondicao($empresaId, $condicao);
        if ($codigo === null) {
            return null;
        }

        return $ativas->firstWhere('chave', $codigo) ?? $ativas->first(fn ($f) => $f->codigoGravado() === $codigo);
    }

    /** Nome da condição para tela e impresso. */
    public function rotuloCondicao(int $empresaId, ?string $condicao): string
    {
        if ($condicao === null || $condicao === '') {
            return '-';
        }
        if (isset(self::PRAZOS[$condicao])) {
            return self::PRAZOS[$condicao];
        }
        if ($f = $this->todas($empresaId)->firstWhere('chave', $condicao)) {
            return $f->nome;
        }

        return self::CONDICOES_LEGADO[$condicao][0] ?? ucfirst(str_replace('_', ' ', $condicao));
    }

    /** Nome a mostrar de um pagamento gravado (venda nova tem forma_nome; antiga, o rótulo do código). */
    public static function rotulo(array|string|null $pgto): string
    {
        if (is_array($pgto)) {
            if (! empty($pgto['forma_nome'])) {
                return (string) $pgto['forma_nome'];
            }
            $pgto = $pgto['forma'] ?? '';
        }
        $pgto = (string) $pgto;

        return self::ROTULOS[$pgto] ?? ucfirst(str_replace('_', ' ', $pgto));
    }

    /**
     * Grava a configuração inteira da empresa (a tela manda todas as linhas, na ordem).
     *
     * Regras: do sistema não muda chave nem tipo e não é apagada (só desligada);
     * criada pode ter qualquer tipo de TIPOS_FORMA_NOVA; criada que sumiu da lista
     * é apagada (as vendas guardam o nome, nada se perde); pelo menos uma ligada.
     *
     * @param array<int, array{chave?:string,nome:string,tipo?:string,icone?:string,cor?:string,largura?:int,ativo?:bool}> $linhas
     * @throws \DomainException
     */
    public function salvar(int $empresaId, array $linhas): void
    {
        $atuais = $this->todas($empresaId)->keyBy('chave');
        $novas = [];
        $nomes = [];

        foreach (array_values($linhas) as $i => $l) {
            $nome = trim((string) ($l['nome'] ?? ''));
            if ($nome === '') {
                throw new \DomainException('Toda forma de pagamento precisa de um nome.');
            }
            $nome = Str::limit($nome, 40, '');
            $chaveNome = Str::lower(Str::ascii($nome));
            if (isset($nomes[$chaveNome])) {
                throw new \DomainException("Há duas formas com o nome \"{$nome}\". Use nomes diferentes.");
            }
            $nomes[$chaveNome] = true;

            $chave = (string) ($l['chave'] ?? '');
            $existente = $chave !== '' ? $atuais->get($chave) : null;

            if ($existente && $existente->sistema) {
                $tipo = $existente->tipo;
                $sistema = true;
            } else {
                $tipo = (string) ($l['tipo'] ?? '');
                if (! in_array($tipo, self::TIPOS_FORMA_NOVA, true)) {
                    throw new \DomainException("Escolha como \"{$nome}\" funciona (dinheiro, cartão, PIX...).");
                }
                $sistema = false;
                if (! $existente) {
                    $chave = 'fp_' . Str::lower(Str::random(8));
                }
            }

            $icone = (string) ($l['icone'] ?? '');
            if (! in_array($icone, self::ICONES, true)) {
                $icone = $existente->icone ?? 'bi-wallet2';
            }
            $cor = (string) ($l['cor'] ?? '');
            if (! preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
                $cor = $existente->cor ?? '#64748b';
            }

            $novas[] = [
                'chave'   => $chave,
                'nome'    => $nome,
                'tipo'    => $tipo,
                'sistema' => $sistema,
                'icone'   => $icone,
                'cor'     => strtolower($cor),
                'largura' => ((int) ($l['largura'] ?? 1)) === 2 ? 2 : 1,
                'ordem'   => $i + 1,
                'ativo'   => filter_var($l['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        // Forma do sistema que não veio na tela continua existindo, como estava (não se apaga do sistema)
        $enviadas = collect($novas)->pluck('chave');
        $ordem = count($novas);
        foreach ($atuais as $chave => $f) {
            if ($f->sistema && ! $enviadas->contains($chave)) {
                $novas[] = [
                    'chave' => $f->chave, 'nome' => $f->nome, 'tipo' => $f->tipo, 'sistema' => true,
                    'icone' => $f->icone, 'cor' => $f->cor, 'largura' => $f->largura,
                    'ordem' => ++$ordem, 'ativo' => (bool) $f->ativo,
                ];
            }
        }

        if (! collect($novas)->contains('ativo', true)) {
            throw new \DomainException('Deixe pelo menos uma forma de pagamento ligada.');
        }

        DB::transaction(function () use ($empresaId, $novas) {
            $chaves = array_column($novas, 'chave');
            FormaPagamento::withoutGlobalScopes()
                ->where('empresa_id', $empresaId)
                ->whereNotIn('chave', $chaves)
                ->where('sistema', false)
                ->delete();

            foreach ($novas as $n) {
                FormaPagamento::withoutGlobalScopes()->updateOrCreate(
                    ['empresa_id' => $empresaId, 'chave' => $n['chave']],
                    $n + ['empresa_id' => $empresaId]
                );
            }
        });
    }

    /** Volta para os botões de fábrica: apaga a configuração da empresa. */
    public function restaurarPadrao(int $empresaId): void
    {
        FormaPagamento::withoutGlobalScopes()->where('empresa_id', $empresaId)->delete();
    }
}
