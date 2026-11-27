<?php

namespace App\Services\LojaOnline;

use App\Models\LojaOnline;
use Illuminate\Support\Str;

/**
 * Modelos prontos da loja online e o cardápio de blocos do editor visual.
 *
 * Um modelo = tema (cores, fontes, cantos) + página inicial montada em blocos.
 * O lojista parte de um modelo e muda o que quiser; o que ele não mudou continua
 * vindo daqui (LojaOnline::temaEfetivo). Ponto ÚNICO da lista de blocos: o
 * editor, a validação e a vitrine leem a mesma definição.
 */
class ModelosLoja
{
    public const PADRAO = 'vitrine';

    /** Fontes oferecidas (Google Fonts): chave => [rótulo, família CSS, parâmetro da URL]. */
    public const FONTES = [
        'figtree' => ['Figtree (limpa)', "'Figtree', system-ui, sans-serif", 'Figtree:wght@400;500;600;700;800'],
        'manrope' => ['Manrope (técnica)', "'Manrope', system-ui, sans-serif", 'Manrope:wght@400;500;600;700;800'],
        'archivo' => ['Archivo (forte)', "'Archivo', system-ui, sans-serif", 'Archivo:wdth,wght@75..125,400..900'],
        'jost' => ['Jost (geométrica)', "'Jost', system-ui, sans-serif", 'Jost:wght@300;400;500;600'],
        'bodoni' => ['Bodoni Moda (revista de moda)', "'Bodoni Moda', 'Didot', Georgia, serif", 'Bodoni+Moda:opsz,wght@6..96,400..900'],
        'bricolage' => ['Bricolage Grotesque (com personalidade)', "'Bricolage Grotesque', system-ui, sans-serif", 'Bricolage+Grotesque:opsz,wght@12..96,400..800'],
        'fredoka' => ['Fredoka (arredondada)', "'Fredoka', system-ui, sans-serif", 'Fredoka:wght@400;500;600;700'],
        'nunito' => ['Nunito (amigável)', "'Nunito', system-ui, sans-serif", 'Nunito:wght@400;600;700;800'],
        'sistema' => ['Do aparelho (mais rápida)', "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif", ''],
    ];

    /**
     * Fontes que só entram com um modelo exclusivo (17/11/2026). A vitrine sabe
     * desenhá-las sempre (TemaCss lê todasAsFontes); o editor só as oferece à loja
     * que tem o modelo.
     */
    public const FONTES_EXCLUSIVAS = [
        'gilda' => ['Gilda Display (Miss Merlinda)', "'Gilda Display', 'Didot', Georgia, serif", 'Gilda+Display'],
        'jostforte' => ['Jost forte em itálico (Cell Master)', "'Jost', 'Futura', 'Century Gothic', system-ui, sans-serif", 'Jost:ital,wght@0,400..900;1,400..900'],
    ];

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function todasAsFontes(): array
    {
        return self::FONTES + self::FONTES_EXCLUSIVAS;
    }

    /** Fontes do seletor do editor desta loja: as públicas + as do modelo exclusivo dela. */
    public static function fontesDaLoja(?LojaOnline $loja): array
    {
        $extras = [];
        foreach ((array) (self::exclusivos()[$loja?->modelo_exclusivo]['fontes'] ?? []) as $chave) {
            $extras[$chave] = self::FONTES_EXCLUSIVAS[$chave];
        }

        return self::FONTES + $extras;
    }

    /** Opções de forma do tema (o editor e a validação leem daqui). */
    public const OPCOES_TEMA = [
        'raio' => ['0' => 'Retos', '6' => 'Leves', '12' => 'Arredondados', '20' => 'Bem arredondados'],
        'botao' => ['reto' => 'Reto', 'arredondado' => 'Arredondado', 'pilula' => 'Pílula'],
        'cartao' => ['limpo' => 'Só a foto', 'borda' => 'Com borda', 'sombra' => 'Com sombra', 'lista' => 'Lista (foto ao lado)'],
        'cabecalho' => ['esquerda' => 'Completo (logo à esquerda, busca grande)', 'centralizado' => 'Logo no centro', 'compacto' => 'Enxuto (uma linha só)'],
        'titulos' => ['normal' => 'Normais', 'maiusculas' => 'MAIÚSCULAS espaçadas'],
        'preco' => ['simples' => 'Simples', 'etiqueta' => 'Etiqueta de preço'],
        'foto' => ['quadrada' => 'Quadrada', 'retrato' => 'Em pé (moda)', 'paisagem' => 'Deitada'],
        'movimento' => ['completo' => 'Completo', 'suave' => 'Suave', 'nenhum' => 'Sem movimento'],
        'faixa' => ['parada' => 'Parada', 'correndo' => 'Correndo (letreiro)'],
    ];

    /**
     * Dicas do editor (16/11/2026): o que pôr em cada bloco, em linguagem de lojista.
     * tipo => [para que serve / o que escrever, tamanho de imagem ou null].
     */
    public const DICAS = [
        'banner' => ['É a primeira coisa que o cliente vê. Diga em poucas palavras o que a loja vende ou a oferta do momento ("Coleção de verão", "Tudo com 20% no PIX"). O botão deve levar direto aos produtos.', 'Imagem deitada, 1600 × 700 px ou maior. Deixe o assunto da foto para o lado oposto ao texto.'],
        'carrossel' => ['Use de 2 a 3 banners: um para a novidade, um para a oferta, um para um benefício (frete grátis, retirada na loja). Mais que isso quase ninguém vê.', 'Imagens deitadas, todas do mesmo tamanho: 1600 × 700 px ou maior.'],
        'banners' => ['Bons para levar a uma categoria ou a uma seleção ("Para presentear", "Até R$ 99"). Título curto, de 2 a 4 palavras.', 'Imagens deitadas, 800 × 600 px ou maior, todas no mesmo formato.'],
        'vitrine' => ['Uma fileira de produtos. Comece pelas ofertas e pelos destaques; para escolher quais são destaque, marque a estrela em Loja online → Produtos.', null],
        'categorias' => ['Atalhos para o cliente achar o que procura. Mostra as categorias que você montou em Loja online → Categorias (nome, ordem e foto); se não montou nenhuma, mostra as do cadastro que têm produto na loja.', null],
        'beneficios' => ['Três ou quatro motivos para comprar de você: prazo de entrega, retirada na loja, troca fácil, parcelamento. Frases curtas e verdadeiras.', null],
        'imagem_texto' => ['Conte a história da loja ou explique um diferencial: quem faz, de onde vem, há quanto tempo existe. Gente compra de quem conhece.', 'Foto real da loja, da equipe ou do produto em uso: 1000 × 800 px ou maior.'],
        'texto' => ['Um recado em destaque: horário de atendimento, prazo de produção, aviso de feriado. Até três linhas.', null],
        'galeria' => ['Fotos de clientes usando, da loja física ou dos bastidores. Funciona como prova de que a loja é de verdade.', 'De 4 a 8 fotos quadradas, 800 × 800 px ou maior.'],
        'oferta' => ['Uma promoção com data para acabar. O relógio dá urgência — use só quando a data for de verdade.', 'Imagem deitada, 1200 × 600 px ou maior (opcional).'],
        'depoimentos' => ['Copie elogios reais de clientes (WhatsApp, Instagram, Google), com o primeiro nome e a cidade. Nunca invente.', null],
        'faq' => ['Responda o que mais perguntam: prazo de entrega, como trocar, formas de pagamento, se tem loja física. Cada resposta evita uma mensagem no WhatsApp.', null],
        'newsletter' => ['Convide a deixar o e-mail em troca de algo: "Receba 10% na primeira compra" converte muito mais que "Assine a newsletter". Os contatos ficam em Loja online → Promoções.', null],
        'video' => ['Um vídeo curto do YouTube mostrando o produto ou a loja. Até 1 minuto funciona melhor.', null],
        'redes' => ['Mostra os botões das redes sociais cadastradas em Loja online → Configurações.', null],
        'lojas_fisicas' => ['Mostra as suas lojas físicas com endereço, "Como chegar" e WhatsApp. Os dados vêm do cadastro das lojas no ERP (Minhas Lojas): mudou lá, muda aqui.', null],
        'atacado_passos' => ['Explica a compra no atacado. O número grande é o mínimo de peças e vem de Loja online → Configurações; aqui você escreve os passos, em frases curtas.', null],
        'faixa' => ['Uma linha de aviso no meio da página: "Frete grátis acima de R$ 199", "Parcele em até 6x".', null],
    ];

    /** @return array<string, array{nome: string, ramo: string, descricao: string, tema: array, blocos: array}> */
    public static function todos(): array
    {
        $vantagens = fn (array ...$itens) => ['tipo' => 'beneficios', 'itens' => array_map(
            fn ($i) => ['icone' => $i[0], 'titulo' => $i[1], 'texto' => $i[2]], $itens)];
        $vitrine = fn (string $titulo, string $origem, int $qtd, int $colunas, string $formato = 'grade') => ['tipo' => 'vitrine', 'titulo' => $titulo, 'origem' => $origem,
            'categoria_id' => null, 'quantidade' => $qtd, 'colunas' => $colunas, 'formato' => $formato];
        $slide = fn (string $titulo, string $texto, string $botao, string $link, string $tom = 'claro', string $lado = 'esquerda') => ['imagem' => '', 'titulo' => $titulo, 'texto' => $texto,
            'botao_texto' => $botao, 'botao_link' => $link, 'tom' => $tom, 'lado' => $lado];
        $banner = fn (string $titulo, string $texto, string $link) => ['imagem' => '', 'titulo' => $titulo, 'texto' => $texto, 'botao_link' => $link];
        $novidades = ['tipo' => 'newsletter', 'titulo' => 'Receba as novidades primeiro', 'texto' => 'Lançamentos e ofertas no seu e-mail.', 'botao_texto' => 'Quero receber'];
        $confianca = $vantagens(['bag-check', 'Pedido direto com a loja', 'Sem intermediário'], ['box-seam', 'Estoque de verdade', 'O mesmo da loja física'],
            ['shield-check', 'Compra protegida', 'Pagamento por parceiros'], ['chat-dots', 'Atendimento de gente', 'Fale com a loja quando precisar']);
        $base = ['movimento' => 'completo', 'faixa' => 'parada', 'logo' => ''];

        return [
            'vitrine' => [
                'nome' => 'Vitrine', 'ramo' => 'Qualquer ramo',
                'descricao' => 'Loja completa e organizada: carrossel no topo, categorias em círculos, ofertas que deslizam.',
                'tema' => $base + [
                    'cor_primaria' => '#1746d1', 'cor_fundo' => '#ffffff', 'cor_superficie' => '#f4f6fa', 'cor_texto' => '#131a26',
                    'cor_cabecalho' => '#ffffff', 'cor_cabecalho_texto' => '#131a26',
                    'cor_rodape' => '#131a26', 'cor_rodape_texto' => '#c9d1df', 'cor_destaque' => '#e11d48',
                    'fonte_titulo' => 'figtree', 'fonte_texto' => 'figtree',
                    'raio' => '12', 'botao' => 'arredondado', 'cartao' => 'borda', 'cabecalho' => 'esquerda',
                    'titulos' => 'normal', 'preco' => 'simples', 'foto' => 'quadrada',
                ],
                'blocos' => [
                    ['tipo' => 'carrossel', 'altura' => 'media', 'lista' => [
                        $slide('Tudo da loja, agora online', 'Escolha com calma e feche o pedido pelo site.', 'Ver produtos', 'produtos'),
                        $slide('Ofertas da semana', 'Preços especiais por tempo limitado.', 'Ver ofertas', 'produtos?promocao=1'),
                    ]],
                    $confianca,
                    ['tipo' => 'categorias', 'titulo' => 'Compre por categoria', 'estilo' => 'circulos'],
                    $vitrine('Em oferta', 'promocoes', 8, 4, 'carrossel'),
                    ['tipo' => 'banners', 'titulo' => '', 'arranjo' => 'iguais', 'lista' => [$banner('Acabou de chegar', 'As novidades da semana', 'produtos?ordem=novos'), $banner('Os mais queridos', 'Escolhidos pela loja', 'produtos?destaque=1')]],
                    $vitrine('Destaques', 'destaques', 8, 4),
                    $vitrine('Novidades', 'novidades', 8, 4, 'carrossel'),
                    $novidades,
                ],
            ],
            'atelie' => [
                'nome' => 'Ateliê', 'ramo' => 'Moda e acessórios',
                'descricao' => 'Revista de moda: preto no branco, título Bodoni gigante, fotos em pé e banners de coleção.',
                'tema' => $base + [
                    'cor_primaria' => '#111111', 'cor_fundo' => '#ffffff', 'cor_superficie' => '#f1f0ee', 'cor_texto' => '#111111',
                    'cor_cabecalho' => '#ffffff', 'cor_cabecalho_texto' => '#111111',
                    'cor_rodape' => '#111111', 'cor_rodape_texto' => '#d9d6d1', 'cor_destaque' => '#d6005f',
                    'fonte_titulo' => 'bodoni', 'fonte_texto' => 'jost',
                    'raio' => '0', 'botao' => 'reto', 'cartao' => 'limpo', 'cabecalho' => 'centralizado',
                    'titulos' => 'maiusculas', 'preco' => 'simples', 'foto' => 'retrato',
                ],
                'blocos' => [
                    ['tipo' => 'faixa', 'texto' => 'Compre pelo site  ·  Pedido direto com a loja'],
                    ['tipo' => 'banner', 'titulo' => 'Nova coleção', 'subtitulo' => 'Peças escolhidas uma a uma.',
                        'botao_texto' => 'Ver a coleção', 'botao_link' => 'produtos?ordem=novos', 'imagem' => '', 'estilo' => 'editorial', 'altura' => 'alta'],
                    ['tipo' => 'banners', 'titulo' => '', 'arranjo' => 'destaque', 'lista' => [$banner('A estação', 'O que acabou de entrar', 'produtos?ordem=novos'), $banner('Acessórios', '', 'produtos'), $banner('Em oferta', '', 'produtos?promocao=1')]],
                    $vitrine('Acabou de chegar', 'novidades', 8, 4),
                    ['tipo' => 'categorias', 'titulo' => 'Por categoria', 'estilo' => 'lista'],
                    $vitrine('As preferidas', 'destaques', 8, 4, 'carrossel'),
                    $novidades,
                ],
            ],
            'circuito' => [
                'nome' => 'Circuito', 'ramo' => 'Celulares, eletrônicos e assistência',
                'descricao' => 'Azul-noite e amarelo, etiqueta vermelha de preço, cinco produtos por linha. Feito para comparar.',
                'tema' => $base + [
                    'cor_primaria' => '#ffc400', 'cor_fundo' => '#eef1f6', 'cor_superficie' => '#ffffff', 'cor_texto' => '#0d1330',
                    'cor_cabecalho' => '#0d1330', 'cor_cabecalho_texto' => '#ffffff',
                    'cor_rodape' => '#0d1330', 'cor_rodape_texto' => '#b9c0dc', 'cor_destaque' => '#ff3d2e',
                    'fonte_titulo' => 'archivo', 'fonte_texto' => 'archivo',
                    'raio' => '12', 'botao' => 'arredondado', 'cartao' => 'sombra', 'cabecalho' => 'esquerda',
                    'titulos' => 'normal', 'preco' => 'etiqueta', 'foto' => 'quadrada',
                ],
                'blocos' => [
                    ['tipo' => 'carrossel', 'altura' => 'media', 'lista' => [
                        $slide('Ofertas que valem a troca', 'O mesmo preço da loja física, sem sair de casa.', 'Ver ofertas', 'produtos?promocao=1', 'escuro'),
                        $slide('Chegou novidade', 'Lançamentos direto para a vitrine.', 'Ver lançamentos', 'produtos?ordem=novos', 'escuro'),
                    ]],
                    ['tipo' => 'categorias', 'titulo' => 'Categorias', 'estilo' => 'cartoes'],
                    $vitrine('Em promoção', 'promocoes', 10, 5, 'carrossel'),
                    ['tipo' => 'banners', 'titulo' => '', 'arranjo' => 'iguais', 'lista' => [$banner('Mais procurados', '', 'produtos?destaque=1'), $banner('Lançamentos', '', 'produtos?ordem=novos'), $banner('Menor preço', '', 'produtos?ordem=menor')]],
                    $vitrine('Mais procurados', 'destaques', 10, 5),
                    $confianca,
                ],
            ],
            'feira' => [
                'nome' => 'Feira', 'ramo' => 'Mercado, cafeteria, alimentos e bebidas',
                'descricao' => 'Verde de banca com plaquinha amarela de preço, categorias em círculos e gôndola cheia.',
                'tema' => $base + [
                    'cor_primaria' => '#0f6b3c', 'cor_fundo' => '#f5f7ee', 'cor_superficie' => '#ffffff', 'cor_texto' => '#14251b',
                    'cor_cabecalho' => '#0f6b3c', 'cor_cabecalho_texto' => '#ffffff',
                    'cor_rodape' => '#0b4d2b', 'cor_rodape_texto' => '#cfe6d6', 'cor_destaque' => '#ffcf33',
                    'fonte_titulo' => 'bricolage', 'fonte_texto' => 'figtree',
                    'raio' => '20', 'botao' => 'pilula', 'cartao' => 'sombra', 'cabecalho' => 'esquerda',
                    'titulos' => 'normal', 'preco' => 'etiqueta', 'foto' => 'quadrada',
                ],
                'blocos' => [
                    ['tipo' => 'banner', 'titulo' => 'Fresquinho, direto pra sua casa', 'subtitulo' => 'Monte seu pedido pelo site e combine a entrega ou a retirada com a loja.',
                        'botao_texto' => 'Fazer pedido', 'botao_link' => 'produtos', 'imagem' => '', 'estilo' => 'cheio', 'altura' => 'media'],
                    ['tipo' => 'categorias', 'titulo' => 'O que você procura?', 'estilo' => 'circulos'],
                    $vitrine('Ofertas do dia', 'promocoes', 10, 5, 'carrossel'),
                    $vitrine('Os mais pedidos', 'destaques', 10, 5),
                    ['tipo' => 'banners', 'titulo' => '', 'arranjo' => 'iguais', 'lista' => [$banner('Chegou hoje', '', 'produtos?ordem=novos'), $banner('Tudo em oferta', '', 'produtos?promocao=1')]],
                    $vitrine('Para completar a lista', 'todos', 10, 5),
                    $confianca,
                ],
            ],
            'mimo' => [
                'nome' => 'Mimo', 'ramo' => 'Presentes, infantil, papelaria e beleza',
                'descricao' => 'Lilás com coral, tudo redondinho: logo no centro, círculos de categoria e banners suaves.',
                'tema' => $base + [
                    'cor_primaria' => '#6b2fd6', 'cor_fundo' => '#f4f0ff', 'cor_superficie' => '#ffffff', 'cor_texto' => '#24163f',
                    'cor_cabecalho' => '#ffffff', 'cor_cabecalho_texto' => '#24163f',
                    'cor_rodape' => '#24163f', 'cor_rodape_texto' => '#d9cff2', 'cor_destaque' => '#ff5a5f',
                    'fonte_titulo' => 'fredoka', 'fonte_texto' => 'nunito',
                    'raio' => '20', 'botao' => 'pilula', 'cartao' => 'sombra', 'cabecalho' => 'centralizado',
                    'titulos' => 'normal', 'preco' => 'simples', 'foto' => 'quadrada',
                ],
                'blocos' => [
                    ['tipo' => 'faixa', 'texto' => 'Presentes escolhidos com carinho'],
                    ['tipo' => 'banner', 'titulo' => 'Um mimo para quem você gosta', 'subtitulo' => 'Escolha pelo site e combine a entrega com a loja.',
                        'botao_texto' => 'Escolher presente', 'botao_link' => 'produtos', 'imagem' => '', 'estilo' => 'centro', 'altura' => 'media'],
                    ['tipo' => 'categorias', 'titulo' => 'Para cada ocasião', 'estilo' => 'circulos'],
                    $vitrine('Queridinhos', 'destaques', 8, 4),
                    ['tipo' => 'banners', 'titulo' => '', 'arranjo' => 'iguais', 'lista' => [$banner('Novidades', 'Acabaram de chegar', 'produtos?ordem=novos'), $banner('Em oferta', 'Para presentear gastando menos', 'produtos?promocao=1')]],
                    $vitrine('Acabou de chegar', 'novidades', 8, 4, 'carrossel'),
                    $novidades,
                ],
            ],
            'balcao' => [
                'nome' => 'Balcão', 'ramo' => 'Distribuidora, materiais, peças e produtos técnicos',
                'descricao' => 'Catálogo enxuto: cabeçalho de uma linha, lista com o código à vista, para quem compra pela referência.',
                'tema' => $base + [
                    'cor_primaria' => '#0a6c74', 'cor_fundo' => '#ffffff', 'cor_superficie' => '#f2f6f6', 'cor_texto' => '#10242a',
                    'cor_cabecalho' => '#ffffff', 'cor_cabecalho_texto' => '#10242a',
                    'cor_rodape' => '#f2f6f6', 'cor_rodape_texto' => '#3d555b', 'cor_destaque' => '#c2410c',
                    'fonte_titulo' => 'manrope', 'fonte_texto' => 'manrope',
                    'raio' => '6', 'botao' => 'arredondado', 'cartao' => 'lista', 'cabecalho' => 'compacto',
                    'titulos' => 'normal', 'preco' => 'simples', 'foto' => 'quadrada',
                ],
                'blocos' => [
                    ['tipo' => 'banner', 'titulo' => 'Peça pelo código, receba no prazo', 'subtitulo' => 'Catálogo com o estoque da loja. Busque pelo código ou pelo nome.',
                        'botao_texto' => 'Abrir o catálogo', 'botao_link' => 'produtos', 'imagem' => '', 'estilo' => 'cheio', 'altura' => 'baixa'],
                    ['tipo' => 'categorias', 'titulo' => 'Linhas de produto', 'estilo' => 'lista'],
                    $vitrine('Mais pedidos', 'destaques', 8, 4),
                    $vantagens(['box-seam', 'Estoque real', 'O mesmo do balcão da loja'], ['upc-scan', 'Busca pelo código', 'Interno ou de barras'], ['chat-dots', 'Atendimento técnico', 'Fale com quem conhece o produto']),
                    $vitrine('Entradas recentes', 'novidades', 8, 4),
                ],
            ],
        ];
    }

    /**
     * Títulos e textos que vêm prontos nos banners dos modelos — o editor usa para
     * avisar "ainda com o texto do modelo".
     *
     * @return string[]
     */
    public static function textosDeExemplo(): array
    {
        $textos = ['Título do banner'];
        // Só os públicos: o texto do modelo exclusivo já é o da própria loja.
        foreach (self::todos() as $m) {
            foreach ($m['blocos'] as $b) {
                if (! in_array($b['tipo'], ['banner', 'carrossel', 'banners'], true)) {
                    continue;
                }
                foreach (array_merge([$b], $b['lista'] ?? []) as $item) {
                    foreach (['titulo', 'subtitulo', 'texto'] as $k) {
                        if (filled($item[$k] ?? null)) {
                            $textos[] = $item[$k];
                        }
                    }
                }
            }
        }

        return array_values(array_unique($textos));
    }

    /* ---------------------------------------------------------------- */
    /*  Modelos exclusivos (17/11/2026)                                  */
    /* ---------------------------------------------------------------- */

    /**
     * Modelos pagos por um cliente. NUNCA entram em todos(): só aparecem no editor
     * da loja cuja coluna `lojas_online.modelo_exclusivo` tem a chave (quem grava
     * é o admin). Cada um mora numa classe em Exclusivos/ e pode trazer fontes,
     * blocos e imagens próprias.
     *
     * @return array<string, array>
     */
    public static function exclusivos(): array
    {
        return [
            'merlinda' => Exclusivos\Merlinda::modelo(),
            'cellmaster' => Exclusivos\CellMaster::modelo(),
        ];
    }

    /** Todos os modelos que a vitrine sabe desenhar (públicos + exclusivos). Uso interno: NUNCA para listar a uma loja. */
    public static function catalogo(): array
    {
        return self::todos() + self::exclusivos();
    }

    /** O que ESTA loja pode escolher no editor: o exclusivo dela (primeiro) + os públicos. */
    public static function paraLoja(?LojaOnline $loja): array
    {
        $chave = $loja?->modelo_exclusivo;
        $exclusivo = $chave && isset(self::exclusivos()[$chave]) ? [$chave => self::exclusivos()[$chave]] : [];

        return $exclusivo + self::todos();
    }

    public static function ehExclusivo(?string $modelo): bool
    {
        return $modelo !== null && isset(self::exclusivos()[$modelo]);
    }

    public static function existe(?string $modelo): bool
    {
        return $modelo !== null && isset(self::catalogo()[$modelo]);
    }

    public static function tema(?string $modelo): array
    {
        return (self::catalogo()[$modelo] ?? self::todos()[self::PADRAO])['tema'];
    }

    /**
     * Blocos do modelo, cada um com um id próprio. O modelo exclusivo monta os
     * dele para a loja (copia as imagens próprias para a pasta da loja).
     */
    public static function blocos(?string $modelo, ?LojaOnline $loja = null): array
    {
        if (self::ehExclusivo($modelo) && $loja && isset(self::exclusivos()[$modelo]['classe'])) {
            $blocos = (self::exclusivos()[$modelo]['classe'])::blocos($loja);
        } else {
            $blocos = (self::catalogo()[$modelo] ?? self::todos()[self::PADRAO])['blocos'];
        }

        // Passam pela mesma higiene do editor: o que sai daqui já está na forma em
        // que o editor grava (mesma ordem de chaves, opções como texto).
        return self::limparBlocos(array_map(fn (array $b, int $i) => ['id' => 'b' . ($i + 1)] + $b, $blocos, array_keys($blocos)), [], $modelo);
    }

    /**
     * Cardápio de blocos do editor: tipo => rótulo, ícone, campos e valores iniciais.
     * Campo: [chave, rótulo, tipo de controle, opções?].
     */
    public static function tiposDeBloco(?string $modelo = null): array
    {
        return self::tiposPublicos() + (self::ehExclusivo($modelo) ? (self::exclusivos()[$modelo]['tipos'] ?? []) : []);
    }

    /** Blocos que toda loja tem. */
    private static function tiposPublicos(): array
    {
        return [
            'banner' => [
                'nome' => 'Banner', 'icone' => 'image', 'descricao' => 'Imagem grande com título e botão',
                'campos' => [
                    ['titulo', 'Título', 'texto'],
                    ['subtitulo', 'Texto de apoio', 'texto'],
                    ['imagem', 'Imagem de fundo', 'imagem'],
                    ['botao_texto', 'Texto do botão', 'texto'],
                    ['botao_link', 'O botão leva para', 'link'],
                    ['estilo', 'Composição', 'opcoes', ['dividido' => 'Texto de um lado, fotos do outro', 'cheio' => 'Cor ou imagem de ponta a ponta',
                        'centro' => 'Texto no centro', 'editorial' => 'Título gigante (revista)']],
                    ['altura', 'Altura', 'opcoes', ['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta']],
                ],
                'inicial' => ['titulo' => 'Título do banner', 'subtitulo' => '', 'imagem' => '', 'botao_texto' => 'Ver produtos',
                    'botao_link' => 'produtos', 'estilo' => 'dividido', 'altura' => 'media'],
            ],
            'vitrine' => [
                'nome' => 'Vitrine de produtos', 'icone' => 'grid-3x3-gap', 'descricao' => 'Uma fileira de produtos escolhida por regra',
                'campos' => [
                    ['titulo', 'Título', 'texto'],
                    ['origem', 'Quais produtos', 'opcoes', ['destaques' => 'Marcados como destaque', 'novidades' => 'Os mais novos',
                        'promocoes' => 'Em promoção', 'categoria' => 'De uma categoria', 'todos' => 'Todos (ordem alfabética)']],
                    ['categoria_id', 'Categoria', 'categoria'],
                    ['quantidade', 'Quantos mostrar', 'opcoes', ['4' => '4', '6' => '6', '8' => '8', '12' => '12']],
                    ['colunas', 'Por linha (no computador)', 'opcoes', ['3' => '3', '4' => '4', '5' => '5']],
                    ['formato', 'Formato', 'opcoes', ['grade' => 'Grade', 'carrossel' => 'Carrossel (desliza para o lado)']],
                ],
                'inicial' => ['titulo' => 'Produtos', 'origem' => 'novidades', 'categoria_id' => null, 'quantidade' => 8, 'colunas' => 4, 'formato' => 'grade'],
            ],
            'categorias' => [
                'nome' => 'Categorias', 'icone' => 'tags', 'descricao' => 'Atalhos para as categorias que têm produto na loja',
                'campos' => [
                    ['titulo', 'Título', 'texto'],
                    ['estilo', 'Aparência', 'opcoes', ['circulos' => 'Círculos com foto', 'cartoes' => 'Cartões com foto', 'pilulas' => 'Botões em linha', 'lista' => 'Lista de nomes']],
                ],
                'inicial' => ['titulo' => 'Categorias', 'estilo' => 'cartoes'],
            ],
            'beneficios' => [
                'nome' => 'Vantagens', 'icone' => 'patch-check', 'descricao' => 'Três frases curtas com ícone (entrega, pagamento, troca)',
                'campos' => [['itens', 'Vantagens', 'itens']],
                'inicial' => ['itens' => [
                    ['icone' => 'truck', 'titulo' => 'Entrega', 'texto' => 'Escreva aqui como você entrega'],
                    ['icone' => 'qr-code', 'titulo' => 'Pagamento', 'texto' => 'Escreva aqui como o cliente paga'],
                    ['icone' => 'arrow-repeat', 'titulo' => 'Troca', 'texto' => 'Escreva aqui a sua regra de troca'],
                ]],
            ],
            'imagem_texto' => [
                'nome' => 'Imagem com texto', 'icone' => 'layout-split', 'descricao' => 'Foto de um lado, história ou chamada do outro',
                'campos' => [
                    ['titulo', 'Título', 'texto'],
                    ['texto', 'Texto', 'textolongo'],
                    ['imagem', 'Imagem', 'imagem'],
                    ['lado', 'Imagem fica', 'opcoes', ['esquerda' => 'À esquerda', 'direita' => 'À direita']],
                    ['botao_texto', 'Texto do botão', 'texto'],
                    ['botao_link', 'O botão leva para', 'link'],
                ],
                'inicial' => ['titulo' => 'Sobre a loja', 'texto' => '', 'imagem' => '', 'lado' => 'esquerda', 'botao_texto' => '', 'botao_link' => 'produtos'],
            ],
            'texto' => [
                'nome' => 'Texto', 'icone' => 'text-paragraph', 'descricao' => 'Título e parágrafo',
                'campos' => [
                    ['titulo', 'Título', 'texto'],
                    ['texto', 'Texto', 'textolongo'],
                    ['alinhamento', 'Alinhamento', 'opcoes', ['esquerda' => 'Esquerda', 'centro' => 'Centro']],
                ],
                'inicial' => ['titulo' => 'Título', 'texto' => '', 'alinhamento' => 'centro'],
            ],
            'carrossel' => [
                'nome' => 'Carrossel de banners', 'icone' => 'images', 'descricao' => 'Vários banners grandes que passam sozinhos (até 5)',
                'campos' => [
                    ['lista', 'Banners', 'lista', [['imagem', 'Imagem', 'imagem'], ['titulo', 'Título', 'texto'], ['texto', 'Texto de apoio', 'texto'], ['botao_texto', 'Texto do botão', 'texto'],
                        ['botao_link', 'O botão leva para', 'link'], ['tom', 'Texto', 'opcoes', ['claro' => 'Claro (sobre imagem escura)', 'escuro' => 'Escuro (sobre imagem clara)']],
                        ['lado', 'Posição do texto', 'opcoes', ['esquerda' => 'Esquerda', 'centro' => 'Centro', 'direita' => 'Direita']]], 5],
                    ['altura', 'Altura', 'opcoes', ['media' => 'Média', 'alta' => 'Alta', 'baixa' => 'Baixa']],
                ],
                'inicial' => ['lista' => [['imagem' => '', 'titulo' => 'Título do banner', 'texto' => '', 'botao_texto' => 'Ver produtos', 'botao_link' => 'produtos', 'tom' => 'claro', 'lado' => 'esquerda']], 'altura' => 'media'],
            ],
            'banners' => [
                'nome' => 'Grade de banners', 'icone' => 'layout-three-columns', 'descricao' => 'Dois ou três banners lado a lado, cada um com seu link',
                'campos' => [
                    ['titulo', 'Título da seção (opcional)', 'texto'],
                    ['lista', 'Banners', 'lista', [['imagem', 'Imagem', 'imagem'], ['titulo', 'Título', 'texto'], ['texto', 'Texto de apoio', 'texto'], ['botao_link', 'Leva para', 'link']], 4],
                    ['arranjo', 'Arranjo', 'opcoes', ['iguais' => 'Lado a lado, iguais', 'destaque' => 'Um grande e os outros menores']],
                ],
                'inicial' => ['titulo' => '', 'lista' => [['imagem' => '', 'titulo' => 'Novidades', 'texto' => '', 'botao_link' => 'produtos?ordem=novos'], ['imagem' => '', 'titulo' => 'Ofertas', 'texto' => '', 'botao_link' => 'produtos?promocao=1']], 'arranjo' => 'iguais'],
            ],
            'galeria' => [
                'nome' => 'Galeria de fotos', 'icone' => 'grid', 'descricao' => 'Mosaico de fotos da loja ou do Instagram (até 8)',
                'campos' => [
                    ['titulo', 'Título', 'texto'],
                    ['lista', 'Fotos', 'lista', [['imagem', 'Imagem', 'imagem'], ['botao_link', 'Leva para (opcional)', 'link']], 8],
                ],
                'inicial' => ['titulo' => 'Da nossa loja', 'lista' => [['imagem' => '', 'botao_link' => '']]],
            ],
            'oferta' => [
                'nome' => 'Oferta com contagem', 'icone' => 'stopwatch', 'descricao' => 'Chamada de promoção com relógio regressivo e cupom',
                'campos' => [
                    ['titulo', 'Título', 'texto'],
                    ['texto', 'Texto de apoio', 'texto'],
                    ['termina_em', 'Termina em', 'datahora'],
                    ['cupom', 'Cupom para mostrar (opcional)', 'texto'],
                    ['botao_texto', 'Texto do botão', 'texto'],
                    ['botao_link', 'O botão leva para', 'link'],
                ],
                'inicial' => ['titulo' => 'Oferta por tempo limitado', 'texto' => '', 'termina_em' => '', 'cupom' => '', 'botao_texto' => 'Ver ofertas', 'botao_link' => 'produtos?promocao=1'],
            ],
            'depoimentos' => [
                'nome' => 'Depoimentos', 'icone' => 'chat-quote', 'descricao' => 'O que os clientes dizem (até 4)',
                'campos' => [
                    ['titulo', 'Título', 'texto'],
                    ['lista', 'Depoimentos', 'lista', [['texto', 'O que a pessoa disse', 'textolongo'], ['nome', 'Nome', 'texto'], ['detalhe', 'Cidade ou detalhe', 'texto']], 4],
                ],
                'inicial' => ['titulo' => 'Quem compra, recomenda', 'lista' => [['texto' => '', 'nome' => '', 'detalhe' => '']]],
            ],
            'faq' => [
                'nome' => 'Perguntas frequentes', 'icone' => 'question-circle', 'descricao' => 'Perguntas que abrem e fecham (até 8)',
                'campos' => [
                    ['titulo', 'Título', 'texto'],
                    ['lista', 'Perguntas', 'lista', [['nome', 'Pergunta', 'texto'], ['texto', 'Resposta', 'textolongo']], 8],
                ],
                'inicial' => ['titulo' => 'Perguntas frequentes', 'lista' => [['nome' => '', 'texto' => '']]],
            ],
            'newsletter' => [
                'nome' => 'Receber novidades', 'icone' => 'envelope-heart', 'descricao' => 'Campo de e-mail para o cliente se cadastrar',
                'campos' => [['titulo', 'Título', 'texto'], ['texto', 'Texto de apoio', 'texto'], ['botao_texto', 'Texto do botão', 'texto']],
                'inicial' => ['titulo' => 'Receba as novidades primeiro', 'texto' => 'Lançamentos e ofertas no seu e-mail.', 'botao_texto' => 'Quero receber'],
            ],
            'video' => [
                'nome' => 'Vídeo', 'icone' => 'play-btn', 'descricao' => 'Um vídeo do YouTube',
                'campos' => [['titulo', 'Título', 'texto'], ['video', 'Endereço do vídeo no YouTube', 'youtube']],
                'inicial' => ['titulo' => '', 'video' => ''],
            ],
            'redes' => [
                'nome' => 'Redes sociais', 'icone' => 'share', 'descricao' => 'Botões das redes cadastradas em Configurações',
                'campos' => [['titulo', 'Título', 'texto'], ['texto', 'Texto de apoio', 'texto']],
                'inicial' => ['titulo' => 'Acompanhe a loja', 'texto' => ''],
            ],
            'faixa' => [
                'nome' => 'Faixa de aviso', 'icone' => 'megaphone', 'descricao' => 'Linha fina colorida (frete, promoção, horário)',
                'campos' => [['texto', 'Aviso', 'texto']],
                'inicial' => ['texto' => 'Escreva seu aviso aqui'],
            ],
        ];
    }

    /** Ícones oferecidos no bloco de vantagens (Bootstrap Icons). */
    public const ICONES = ['truck', 'qr-code', 'credit-card', 'bag-check', 'arrow-repeat', 'shield-check', 'lightning-charge',
        'chat-dots', 'gift', 'star', 'heart', 'clock', 'geo-alt', 'percent', 'box-seam', 'whatsapp', 'upc-scan'];

    /**
     * Higieniza o que veio do editor: só tipos e campos conhecidos, tamanhos
     * limitados, opções dentro da lista. Nada de HTML — todo texto é impresso
     * escapado na vitrine.
     *
     * @param  array<int, mixed>  $blocos
     * @param  array<int, int>  $categoriasDaEmpresa
     */
    public static function limparBlocos(array $blocos, array $categoriasDaEmpresa = [], ?string $modelo = null): array
    {
        $tipos = self::tiposDeBloco($modelo);
        $out = [];

        foreach (array_slice($blocos, 0, 30) as $bloco) {
            if (! is_array($bloco) || ! isset($tipos[$bloco['tipo'] ?? ''])) {
                continue;
            }
            $def = $tipos[$bloco['tipo']];
            $limpo = [
                'id' => preg_match('/^[a-z0-9]{2,20}$/', (string) ($bloco['id'] ?? '')) ? $bloco['id'] : 'b' . Str::lower(Str::random(8)),
                'tipo' => $bloco['tipo'],
            ];

            foreach ($def['campos'] as $campo) {
                [$chave, , $controle] = $campo;
                $valor = $bloco[$chave] ?? $def['inicial'][$chave] ?? null;

                $limpo[$chave] = match ($controle) {
                    'texto' => mb_substr(trim((string) $valor), 0, 160),
                    'textolongo' => mb_substr(trim((string) $valor), 0, 2000),
                    'link' => self::limparLink((string) $valor),
                    'imagem' => self::limparImagem((string) $valor),
                    'opcoes' => array_key_exists((string) $valor, $campo[3]) ? (string) $valor : (string) ($def['inicial'][$chave] ?? array_key_first($campo[3])),
                    'categoria' => in_array((int) $valor, $categoriasDaEmpresa, true) ? (int) $valor : null,
                    'itens' => self::limparItens(is_array($valor) ? $valor : []),
                    'lista' => self::limparLista(is_array($valor) ? $valor : [], $campo[3], (int) ($campo[4] ?? 6)),
                    'datahora' => preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string) $valor) ? (string) $valor : '',
                    'youtube' => self::idDoYoutube((string) $valor),
                    default => null,
                };
            }

            $out[] = $limpo;
        }

        return $out;
    }

    /** Link interno da loja (caminho relativo) ou https externo. */
    public static function limparLink(string $link): string
    {
        $link = trim($link);
        if ($link === '') {
            return '';
        }
        if (preg_match('#^https://[^\s<>"\']{3,300}$#i', $link)) {
            return $link;
        }
        $link = ltrim($link, '/');

        return preg_match('#^[a-zA-Z0-9/_\-?=&%.]{1,200}$#', $link) && ! str_contains($link, '..') ? $link : '';
    }

    /** Só aceita arquivo enviado pela própria loja (storage público, pasta loja-online). */
    public static function limparImagem(string $caminho): string
    {
        $caminho = trim($caminho);

        return preg_match('#^loja-online/\d+/[A-Za-z0-9_\-]+\.(jpg|jpeg|png|webp)$#', $caminho) ? $caminho : '';
    }

    /** Lista de itens com subcampos de texto (depoimentos, perguntas). Linha toda vazia é descartada. */
    private static function limparLista(array $linhas, array $subcampos, int $maximo): array
    {
        $out = [];
        foreach (array_slice($linhas, 0, $maximo) as $linha) {
            if (! is_array($linha)) {
                continue;
            }
            $limpa = [];
            foreach ($subcampos as $sub) {
                [$chave, , $controle] = $sub;
                $valor = (string) ($linha[$chave] ?? '');
                $limpa[$chave] = match ($controle) {
                    'imagem' => self::limparImagem($valor),
                    'link' => self::limparLink($valor),
                    'opcoes' => array_key_exists($valor, $sub[3]) ? $valor : (string) array_key_first($sub[3]),
                    'textolongo' => mb_substr(trim($valor), 0, 600),
                    default => mb_substr(trim($valor), 0, 120),
                };
            }
            $out[] = $limpa;
        }

        return $out;
    }

    /** Aceita o endereço (ou o id) de um vídeo do YouTube e devolve só o id de 11 caracteres. */
    public static function idDoYoutube(string $valor): string
    {
        $valor = trim($valor);
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $valor)) {
            return $valor;
        }

        return preg_match('#(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $valor, $m) ? $m[1] : '';
    }

    private static function limparItens(array $itens): array
    {
        $out = [];
        foreach (array_slice($itens, 0, 4) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $out[] = [
                'icone' => in_array($item['icone'] ?? '', self::ICONES, true) ? $item['icone'] : 'star',
                'titulo' => mb_substr(trim((string) ($item['titulo'] ?? '')), 0, 60),
                'texto' => mb_substr(trim((string) ($item['texto'] ?? '')), 0, 120),
            ];
        }

        return $out;
    }

    /** Tema vindo do editor: só chaves conhecidas, cor em hexadecimal, opção dentro da lista. */
    public static function limparTema(array $tema): array
    {
        $out = [];
        foreach (self::CORES as $cor => $rotulo) {
            if (isset($tema[$cor]) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $tema[$cor])) {
                $out[$cor] = strtolower($tema[$cor]);
            }
        }
        foreach (['fonte_titulo', 'fonte_texto'] as $f) {
            if (isset($tema[$f]) && isset(self::todasAsFontes()[$tema[$f]])) {
                $out[$f] = $tema[$f];
            }
        }
        foreach (self::OPCOES_TEMA as $chave => $validos) {
            if (isset($tema[$chave]) && array_key_exists((string) $tema[$chave], $validos)) {
                $out[$chave] = (string) $tema[$chave];
            }
        }
        if (isset($tema['logo'])) {
            $out['logo'] = self::limparImagem((string) $tema['logo']);
        }

        return $out;
    }

    /** Cores do tema que o lojista escolhe: chave => rótulo na tela. */
    public const CORES = [
        'cor_primaria' => 'Cor da marca (botões)',
        'cor_destaque' => 'Cor de oferta (selos)',
        'cor_fundo' => 'Fundo da página',
        'cor_superficie' => 'Fundo dos quadros',
        'cor_texto' => 'Texto',
        'cor_cabecalho' => 'Fundo do cabeçalho',
        'cor_cabecalho_texto' => 'Texto do cabeçalho',
        'cor_rodape' => 'Fundo do rodapé',
        'cor_rodape_texto' => 'Texto do rodapé',
    ];
}
