<?php

namespace App\Services\LojaOnline\Exclusivos;

use App\Models\LojaOnline;
use App\Models\Unidade;
use App\Scopes\EmpresaScope;
use App\Services\LojaOnline\LojaContexto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Modelo EXCLUSIVO da MISS MERLINDA (17/11/2026) — pago pela cliente, não aparece
 * para nenhuma outra loja (ver ModelosLoja::exclusivos / paraLoja).
 *
 * Onde vive: a definição (tema + página inicial) mora aqui; o visual próprio em
 * resources/views/loja/modelos/merlinda.blade.php (só carrega com este modelo);
 * as imagens de partida em resources/loja-modelos/merlinda/ e são COPIADAS para a
 * pasta da loja (storage público, loja-online/{empresa}/merlinda-*) na instalação,
 * para o editor poder trocá-las como qualquer outra imagem da loja.
 *
 * As fotos de partida são de banco de imagens livre (Unsplash) — servem até a loja
 * mandar as dela. Os produtos de exemplo só aparecem na PRÉVIA, enquanto nenhum
 * produto real estiver marcado (VitrineController::home).
 */
class Merlinda
{
    public const CHAVE = 'merlinda';

    /** Arquivos de partida: nome em resources/loja-modelos/merlinda => nome na pasta da loja. */
    private const ARQUIVOS = [
        'logo.png', 'logo-clara.png', 'favicon.png',
        'capa-1.jpg', 'capa-2.jpg', 'capa-3.jpg',
        'colecao-vestidos.jpg', 'colecao-blusas.jpg', 'colecao-conjuntos.jpg', 'lojas.jpg',
        'ex-1.jpg', 'ex-2.jpg', 'ex-3.jpg', 'ex-4.jpg', 'ex-5.jpg', 'ex-6.jpg', 'ex-7.jpg', 'ex-8.jpg',
    ];

    /** Produtos de exemplo da prévia: [nome, categoria, preço, preço normal (0 = sem promoção), foto]. */
    private const EXEMPLOS = [
        ['Vestido midi de babados rosê', 'Vestidos', 189.90, 0, 'ex-1.jpg'],
        ['Vestido poá com fenda', 'Vestidos', 159.90, 199.90, 'ex-2.jpg'],
        ['Vestido curto ombro a ombro', 'Vestidos', 139.90, 0, 'ex-3.jpg'],
        ['Chemise jeans com botões', 'Vestidos', 169.90, 0, 'ex-4.jpg'],
        ['Macaquinho de alça verde-oliva', 'Conjuntos', 129.90, 0, 'ex-5.jpg'],
        ['Calça jogger rosê', 'Calças', 119.90, 149.90, 'ex-6.jpg'],
        ['Vestido longo vermelho', 'Vestidos', 229.90, 0, 'ex-7.jpg'],
        ['Camisa branca com laço', 'Blusas', 99.90, 0, 'ex-8.jpg'],
    ];

    public static function modelo(): array
    {
        return [
            'nome' => 'Miss Merlinda',
            'ramo' => 'Moda feminina',
            'descricao' => 'Feito para a Miss Merlinda: rosa da marca, fotos em arco de provador, o coração do logo e as suas lojas físicas.',
            'classe' => self::class,
            'fontes' => ['gilda'],
            'tema' => self::tema(),
            // Versão sem imagens (amostra no editor). A loja recebe a completa por blocos($loja).
            'blocos' => self::montarBlocos(fn (string $a) => '', 'nossas lojas'),
            'tipos' => [
                'lojas_fisicas' => [
                    'nome' => 'Nossas lojas', 'icone' => 'shop', 'descricao' => 'As lojas físicas, com endereço e WhatsApp (vêm do cadastro do ERP)',
                    'campos' => [['titulo', 'Título', 'texto'], ['texto', 'Texto de apoio', 'texto']],
                    'inicial' => ['titulo' => 'Nossas lojas', 'texto' => 'Prefere provar? Passe numa das nossas lojas.'],
                ],
            ],
        ];
    }

    public static function tema(): array
    {
        return [
            'movimento' => 'suave', 'faixa' => 'correndo', 'logo' => '',
            'cor_primaria' => '#2a282c', 'cor_fundo' => '#ffffff', 'cor_superficie' => '#fdeef2', 'cor_texto' => '#2a282c',
            'cor_cabecalho' => '#fedbe5', 'cor_cabecalho_texto' => '#2a282c',
            'cor_rodape' => '#fedbe5', 'cor_rodape_texto' => '#2a282c', 'cor_destaque' => '#a8174b',
            'fonte_titulo' => 'gilda', 'fonte_texto' => 'jost',
            'raio' => '20', 'botao' => 'pilula', 'cartao' => 'limpo', 'cabecalho' => 'centralizado',
            'titulos' => 'normal', 'preco' => 'simples', 'foto' => 'retrato',
        ];
    }

    /** Página inicial da loja, com as imagens já copiadas para a pasta dela. */
    public static function blocos(LojaOnline $loja): array
    {
        self::copiarArquivos($loja);
        $lojas = self::unidades($loja);

        return self::montarBlocos(fn (string $a) => self::caminho($loja, $a), self::cidades($lojas));
    }

    /**
     * Aplica o modelo na loja: modelo, logo, página inicial publicada e as opções
     * que ainda estão em branco (nada que a loja já escreveu é sobrescrito).
     */
    public static function instalar(LojaOnline $loja): void
    {
        $blocos = \App\Services\LojaOnline\ModelosLoja::blocos(self::CHAVE, $loja);
        $lojas = self::unidades($loja);
        $empresa = $loja->empresa;
        $tel = preg_replace('/\D/', '', (string) ($empresa?->telefone ?: ($loja->unidade?->telefone ?? '')));
        $whats = strlen($tel) === 11 ? sprintf('(%s) %s-%s', substr($tel, 0, 2), substr($tel, 2, 5), substr($tel, 7))
            : (strlen($tel) === 10 ? sprintf('(%s) %s-%s', substr($tel, 0, 2), substr($tel, 2, 4), substr($tel, 6)) : '');
        $qtd = self::porExtenso($lojas->count());
        $onde = self::cidades($lojas);

        $sugestoes = [
            'whatsapp' => $whats,
            'whatsapp_mensagem' => 'Olá, Miss Merlinda! Vim pela loja online.',
            'texto_rodape' => 'Moda feminina' . ($lojas->count() > 1 ? " com {$qtd} lojas em {$onde}." : '.'),
            'seo_titulo' => 'Miss Merlinda — moda feminina',
            'descricao_busca' => 'Vestidos, blusas, bodies, calças e conjuntos da Miss Merlinda' . ($lojas->count() > 1 ? ", {$qtd} lojas em {$onde}" : '') . ' — agora também online.',
            'favicon' => self::caminho($loja, 'favicon.png'),
            'imagem_compartilhar' => self::caminho($loja, 'capa-1.jpg'),
        ];
        $config = $loja->config ?? [];
        foreach ($sugestoes as $k => $v) {
            if (blank($config[$k] ?? null) && filled($v)) {
                $config[$k] = $v;
            }
        }

        $loja->update([
            'modelo' => self::CHAVE,
            'tema' => ['logo' => self::caminho($loja, 'logo.png')],
            'blocos' => $blocos,
            'rascunho' => null,
            'config' => $config,
        ]);
    }

    /**
     * Cartões de exemplo (mesmo formato de CatalogoLoja::paraVitrine). `grade` = true
     * faz o cartão mostrar "Ver opções" em vez do botão de pôr no carrinho.
     */
    public static function exemplos(LojaOnline $loja, int $quantos = 8): Collection
    {
        self::copiarArquivos($loja);

        return collect(self::EXEMPLOS)->take($quantos)->map(fn (array $e, int $i) => [
            'id' => -($i + 1), 'nome' => $e[0], 'grade' => true, 'a_partir' => false, 'nota' => null, 'avaliacoes' => 0,
            'caminho' => 'produtos', 'foto' => LojaContexto::arquivo(self::caminho($loja, $e[4])), 'foto2' => null,
            'novo' => $i < 3 && $e[3] == 0, 'frete_gratis' => false, 'parcelas' => null, 'codigo' => 'EXEMPLO',
            'categoria' => $e[1], 'destaque' => false, 'saldo' => 0, 'disponivel' => true, 'exemplo' => true,
            'preco' => $e[2], 'normal' => $e[3] ?: $e[2], 'promocao' => $e[3] > 0,
        ]);
    }

    /** Lojas físicas ativas da empresa (para o bloco "Nossas lojas"). */
    public static function unidades(LojaOnline $loja): Collection
    {
        return Unidade::withoutGlobalScope(EmpresaScope::class)->where('empresa_id', $loja->empresa_id)
            ->where('status', 'ativa')->orderBy('id')
            ->get(['id', 'nome', 'logradouro', 'numero', 'bairro', 'cidade', 'uf', 'telefone']);
    }

    /* ---------------------------------------------------------------- */

    private static function montarBlocos(callable $img, string $onde): array
    {
        $slide = fn (string $a, string $titulo, string $texto, string $botao, string $link) => [
            'imagem' => $img($a), 'titulo' => $titulo, 'texto' => $texto, 'botao_texto' => $botao, 'botao_link' => $link, 'tom' => 'escuro', 'lado' => 'esquerda',
        ];
        $banner = fn (string $a, string $titulo, string $texto, string $link) => ['imagem' => $img($a), 'titulo' => $titulo, 'texto' => $texto, 'botao_link' => $link];
        $vitrine = fn (string $titulo, string $origem, int $qtd, string $formato) => ['tipo' => 'vitrine', 'titulo' => $titulo, 'origem' => $origem,
            'categoria_id' => null, 'quantidade' => $qtd, 'colunas' => 4, 'formato' => $formato];

        return [
            ['tipo' => 'faixa', 'texto' => 'Moda feminina · ' . ucfirst($onde) . ' · agora também online'],
            ['tipo' => 'carrossel', 'altura' => 'alta', 'lista' => [
                $slide('capa-1.jpg', 'Leve como o verão', 'Vestidos, blusas e conjuntos que acabaram de chegar às lojas.', 'Ver as novidades', 'produtos?ordem=novos'),
                $slide('capa-2.jpg', 'Blusas para o dia todo', 'Do trabalho ao fim de tarde, sem trocar de roupa.', 'Ver blusas', 'produtos?q=blusa'),
                $slide('capa-3.jpg', 'O vestido da ocasião', 'Escolha com calma e tire as dúvidas com a gente no WhatsApp.', 'Ver vestidos', 'produtos?q=vestido'),
            ]],
            ['tipo' => 'categorias', 'titulo' => 'Compre por categoria', 'estilo' => 'circulos'],
            $vitrine('Acabou de chegar', 'novidades', 8, 'carrossel'),
            ['tipo' => 'banners', 'titulo' => 'Escolha por peça', 'arranjo' => 'destaque', 'lista' => [
                $banner('colecao-vestidos.jpg', 'Vestidos', 'Do casual à festa', 'produtos?q=vestido'),
                $banner('colecao-blusas.jpg', 'Blusas e bodies', 'Para combinar com tudo', 'produtos?q=blusa'),
                $banner('colecao-conjuntos.jpg', 'Calças e conjuntos', 'Prontos para sair', 'produtos?q=conjunto'),
            ]],
            $vitrine('As queridinhas', 'destaques', 8, 'grade'),
            ['tipo' => 'lojas_fisicas', 'titulo' => 'Nossas lojas', 'texto' => 'Prefere provar antes? Passe numa das nossas lojas — o estoque do site é o mesmo delas.'],
            ['tipo' => 'imagem_texto', 'titulo' => 'Do provador para a sua casa',
                'texto' => 'Agora você escolhe as peças da Miss Merlinda pelo site, com o mesmo estoque das lojas, e combina com a gente a entrega ou a retirada.',
                'imagem' => $img('lojas.jpg'), 'lado' => 'direita', 'botao_texto' => 'Ver todos os produtos', 'botao_link' => 'produtos'],
            ['tipo' => 'newsletter', 'titulo' => 'Novidades da Miss Merlinda primeiro', 'texto' => 'Lançamentos e promoções no seu e-mail.', 'botao_texto' => 'Quero receber'],
        ];
    }

    private static function caminho(LojaOnline $loja, string $arquivo): string
    {
        return 'loja-online/' . (int) $loja->empresa_id . '/merlinda-' . $arquivo;
    }

    /** Copia as imagens de partida para a pasta da loja (só o que ainda não está lá). */
    private static function copiarArquivos(LojaOnline $loja): void
    {
        $disco = Storage::disk('public');
        foreach (self::ARQUIVOS as $a) {
            $destino = self::caminho($loja, $a);
            $origem = resource_path('loja-modelos/merlinda/' . $a);
            if (! $disco->exists($destino) && is_file($origem)) {
                $disco->put($destino, file_get_contents($origem));
            }
        }
    }

    /** "Teresina, Timon e Caxias" (cidades das lojas ativas, sem repetir). */
    private static function cidades(Collection $lojas): string
    {
        $cidades = $lojas->pluck('cidade')->filter()->map(fn ($c) => mb_convert_case(mb_strtolower(trim($c)), MB_CASE_TITLE))->unique()->values();
        if ($cidades->isEmpty()) {
            return 'nossas lojas';
        }

        return $cidades->count() === 1 ? $cidades[0] : $cidades->slice(0, -1)->implode(', ') . ' e ' . $cidades->last();
    }

    private static function porExtenso(int $n): string
    {
        return [2 => 'duas', 3 => 'três', 4 => 'quatro', 5 => 'cinco', 6 => 'seis', 7 => 'sete', 8 => 'oito', 9 => 'nove', 10 => 'dez'][$n] ?? (string) $n;
    }
}
