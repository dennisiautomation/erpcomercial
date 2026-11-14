<?php

namespace App\Services\LojaOnline;

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

    /** Opções de forma do tema (o editor e a validação leem daqui). */
    public const OPCOES_TEMA = [
        'raio' => ['0' => 'Retos', '6' => 'Leves', '12' => 'Arredondados', '20' => 'Bem arredondados'],
        'botao' => ['reto' => 'Reto', 'arredondado' => 'Arredondado', 'pilula' => 'Pílula'],
        'cartao' => ['limpo' => 'Só a foto', 'borda' => 'Com borda', 'sombra' => 'Com sombra', 'lista' => 'Lista (foto ao lado)'],
        'cabecalho' => ['esquerda' => 'Logo à esquerda', 'centralizado' => 'Logo no centro'],
        'titulos' => ['normal' => 'Normais', 'maiusculas' => 'MAIÚSCULAS espaçadas'],
        'preco' => ['simples' => 'Simples', 'etiqueta' => 'Etiqueta de preço'],
        'foto' => ['quadrada' => 'Quadrada', 'retrato' => 'Em pé (moda)', 'paisagem' => 'Deitada'],
        'movimento' => ['completo' => 'Completo', 'suave' => 'Suave', 'nenhum' => 'Sem movimento'],
        'faixa' => ['parada' => 'Parada', 'correndo' => 'Correndo (letreiro)'],
    ];

    /** @return array<string, array{nome: string, ramo: string, descricao: string, tema: array, blocos: array}> */
    public static function todos(): array
    {
        $vantagens = fn (array ...$itens) => ['tipo' => 'beneficios', 'itens' => array_map(
            fn ($i) => ['icone' => $i[0], 'titulo' => $i[1], 'texto' => $i[2]], $itens)];

        return [
            'vitrine' => [
                'nome' => 'Vitrine', 'ramo' => 'Qualquer ramo',
                'descricao' => 'Branco, direto e organizado. O produto é quem aparece.',
                'tema' => [
                    'cor_primaria' => '#1746d1', 'cor_fundo' => '#ffffff', 'cor_superficie' => '#f4f6fa', 'cor_texto' => '#131a26',
                    'cor_cabecalho' => '#ffffff', 'cor_cabecalho_texto' => '#131a26',
                    'cor_rodape' => '#131a26', 'cor_rodape_texto' => '#c9d1df', 'cor_destaque' => '#e11d48',
                    'fonte_titulo' => 'figtree', 'fonte_texto' => 'figtree',
                    'raio' => '12', 'botao' => 'arredondado', 'cartao' => 'borda', 'cabecalho' => 'esquerda',
                    'movimento' => 'completo', 'faixa' => 'parada', 'titulos' => 'normal', 'preco' => 'simples', 'foto' => 'quadrada', 'logo' => '',
                ],
                'blocos' => [
                    ['tipo' => 'banner', 'titulo' => 'Tudo da loja, agora online', 'subtitulo' => 'Escolha com calma, feche o pedido pelo site e combine tudo direto com a loja.',
                        'botao_texto' => 'Ver produtos', 'botao_link' => 'produtos', 'imagem' => '', 'estilo' => 'dividido', 'altura' => 'media'],
                    $vantagens(['bag-check', 'Pedido direto com a loja', 'Sem intermediário'], ['box-seam', 'Estoque de verdade', 'O mesmo da loja física'], ['chat-dots', 'Atendimento de gente', 'Fale com a loja quando precisar']),
                    ['tipo' => 'vitrine', 'titulo' => 'Destaques', 'origem' => 'destaques', 'categoria_id' => null, 'quantidade' => 8, 'colunas' => 4],
                    ['tipo' => 'categorias', 'titulo' => 'Compre por categoria', 'estilo' => 'cartoes'],
                    ['tipo' => 'vitrine', 'titulo' => 'Acabou de chegar', 'origem' => 'novidades', 'categoria_id' => null, 'quantidade' => 8, 'colunas' => 4],
                    ['tipo' => 'newsletter', 'titulo' => 'Receba as novidades primeiro', 'texto' => 'Lançamentos e ofertas no seu e-mail.', 'botao_texto' => 'Quero receber'],
                ],
            ],
            'atelie' => [
                'nome' => 'Ateliê', 'ramo' => 'Moda e acessórios',
                'descricao' => 'Preto no branco de revista de moda: título Bodoni, fotos em pé, cantos retos.',
                'tema' => [
                    'cor_primaria' => '#111111', 'cor_fundo' => '#ffffff', 'cor_superficie' => '#f1f0ee', 'cor_texto' => '#111111',
                    'cor_cabecalho' => '#ffffff', 'cor_cabecalho_texto' => '#111111',
                    'cor_rodape' => '#111111', 'cor_rodape_texto' => '#d9d6d1', 'cor_destaque' => '#d6005f',
                    'fonte_titulo' => 'bodoni', 'fonte_texto' => 'jost',
                    'raio' => '0', 'botao' => 'reto', 'cartao' => 'limpo', 'cabecalho' => 'centralizado',
                    'movimento' => 'completo', 'faixa' => 'parada', 'titulos' => 'maiusculas', 'preco' => 'simples', 'foto' => 'retrato', 'logo' => '',
                ],
                'blocos' => [
                    ['tipo' => 'faixa', 'texto' => 'Compre pelo site  ·  Pedido direto com a loja'],
                    ['tipo' => 'banner', 'titulo' => 'Nova coleção', 'subtitulo' => 'Peças escolhidas uma a uma.',
                        'botao_texto' => 'Ver a coleção', 'botao_link' => 'produtos?ordem=novos', 'imagem' => '', 'estilo' => 'editorial', 'altura' => 'alta'],
                    ['tipo' => 'vitrine', 'titulo' => 'Acabou de chegar', 'origem' => 'novidades', 'categoria_id' => null, 'quantidade' => 6, 'colunas' => 3],
                    ['tipo' => 'categorias', 'titulo' => 'Por categoria', 'estilo' => 'lista'],
                    ['tipo' => 'vitrine', 'titulo' => 'As preferidas', 'origem' => 'destaques', 'categoria_id' => null, 'quantidade' => 6, 'colunas' => 3],
                    ['tipo' => 'newsletter', 'titulo' => 'Receba as novidades primeiro', 'texto' => 'Lançamentos e ofertas no seu e-mail.', 'botao_texto' => 'Quero receber'],
                ],
            ],
            'circuito' => [
                'nome' => 'Circuito', 'ramo' => 'Celulares, eletrônicos e assistência',
                'descricao' => 'Azul-noite e amarelo, com etiqueta vermelha de preço. Feito para comparar.',
                'tema' => [
                    'cor_primaria' => '#ffc400', 'cor_fundo' => '#eef1f6', 'cor_superficie' => '#ffffff', 'cor_texto' => '#0d1330',
                    'cor_cabecalho' => '#0d1330', 'cor_cabecalho_texto' => '#ffffff',
                    'cor_rodape' => '#0d1330', 'cor_rodape_texto' => '#b9c0dc', 'cor_destaque' => '#ff3d2e',
                    'fonte_titulo' => 'archivo', 'fonte_texto' => 'archivo',
                    'raio' => '12', 'botao' => 'arredondado', 'cartao' => 'sombra', 'cabecalho' => 'esquerda',
                    'movimento' => 'completo', 'faixa' => 'parada', 'titulos' => 'normal', 'preco' => 'etiqueta', 'foto' => 'quadrada', 'logo' => '',
                ],
                'blocos' => [
                    ['tipo' => 'faixa', 'texto' => 'Preço de loja  ·  Pedido pelo site'],
                    ['tipo' => 'banner', 'titulo' => 'Ofertas que valem a troca', 'subtitulo' => 'O mesmo preço da loja física, sem sair de casa.',
                        'botao_texto' => 'Ver ofertas', 'botao_link' => 'produtos?ordem=menor', 'imagem' => '', 'estilo' => 'dividido', 'altura' => 'media'],
                    ['tipo' => 'vitrine', 'titulo' => 'Em promoção', 'origem' => 'promocoes', 'categoria_id' => null, 'quantidade' => 8, 'colunas' => 4],
                    ['tipo' => 'categorias', 'titulo' => 'Categorias', 'estilo' => 'cartoes'],
                    ['tipo' => 'vitrine', 'titulo' => 'Mais procurados', 'origem' => 'destaques', 'categoria_id' => null, 'quantidade' => 8, 'colunas' => 4],
                    $vantagens(['box-seam', 'Estoque de verdade', 'O mesmo da loja física'], ['bag-check', 'Pedido direto com a loja', 'Sem intermediário'], ['chat-dots', 'Dúvida? Chama', 'A loja responde você']),
                ],
            ],
            'feira' => [
                'nome' => 'Feira', 'ramo' => 'Mercado, cafeteria, alimentos e bebidas',
                'descricao' => 'Verde de banca com plaquinha amarela de preço. Colorido e apetitoso.',
                'tema' => [
                    'cor_primaria' => '#0f6b3c', 'cor_fundo' => '#f5f7ee', 'cor_superficie' => '#ffffff', 'cor_texto' => '#14251b',
                    'cor_cabecalho' => '#0f6b3c', 'cor_cabecalho_texto' => '#ffffff',
                    'cor_rodape' => '#0b4d2b', 'cor_rodape_texto' => '#cfe6d6', 'cor_destaque' => '#ffcf33',
                    'fonte_titulo' => 'bricolage', 'fonte_texto' => 'figtree',
                    'raio' => '20', 'botao' => 'pilula', 'cartao' => 'limpo', 'cabecalho' => 'esquerda',
                    'movimento' => 'completo', 'faixa' => 'parada', 'titulos' => 'normal', 'preco' => 'etiqueta', 'foto' => 'quadrada', 'logo' => '',
                ],
                'blocos' => [
                    ['tipo' => 'banner', 'titulo' => 'Fresquinho, direto pra sua casa', 'subtitulo' => 'Monte seu pedido pelo site e combine a entrega ou a retirada com a loja.',
                        'botao_texto' => 'Fazer pedido', 'botao_link' => 'produtos', 'imagem' => '', 'estilo' => 'cheio', 'altura' => 'media'],
                    ['tipo' => 'categorias', 'titulo' => 'O que você procura?', 'estilo' => 'pilulas'],
                    ['tipo' => 'vitrine', 'titulo' => 'Ofertas do dia', 'origem' => 'promocoes', 'categoria_id' => null, 'quantidade' => 8, 'colunas' => 4],
                    ['tipo' => 'vitrine', 'titulo' => 'Os mais pedidos', 'origem' => 'destaques', 'categoria_id' => null, 'quantidade' => 8, 'colunas' => 4],
                    $vantagens(['bag-check', 'Pedido direto com a loja', 'Sem intermediário'], ['box-seam', 'Só o que tem', 'Estoque da loja, atualizado'], ['chat-dots', 'Fale com a gente', 'A loja responde você']),
                ],
            ],
            'mimo' => [
                'nome' => 'Mimo', 'ramo' => 'Presentes, infantil, papelaria e beleza',
                'descricao' => 'Lilás com coral, tudo bem redondinho. Leve e alegre.',
                'tema' => [
                    'cor_primaria' => '#6b2fd6', 'cor_fundo' => '#f4f0ff', 'cor_superficie' => '#ffffff', 'cor_texto' => '#24163f',
                    'cor_cabecalho' => '#f4f0ff', 'cor_cabecalho_texto' => '#24163f',
                    'cor_rodape' => '#24163f', 'cor_rodape_texto' => '#d9cff2', 'cor_destaque' => '#ff5a5f',
                    'fonte_titulo' => 'fredoka', 'fonte_texto' => 'nunito',
                    'raio' => '20', 'botao' => 'pilula', 'cartao' => 'sombra', 'cabecalho' => 'centralizado',
                    'movimento' => 'completo', 'faixa' => 'parada', 'titulos' => 'normal', 'preco' => 'simples', 'foto' => 'quadrada', 'logo' => '',
                ],
                'blocos' => [
                    ['tipo' => 'faixa', 'texto' => 'Presentes escolhidos com carinho'],
                    ['tipo' => 'banner', 'titulo' => 'Um mimo para quem você gosta', 'subtitulo' => 'Escolha pelo site e combine a entrega com a loja.',
                        'botao_texto' => 'Escolher presente', 'botao_link' => 'produtos', 'imagem' => '', 'estilo' => 'centro', 'altura' => 'media'],
                    ['tipo' => 'categorias', 'titulo' => 'Para cada ocasião', 'estilo' => 'pilulas'],
                    ['tipo' => 'vitrine', 'titulo' => 'Queridinhos', 'origem' => 'destaques', 'categoria_id' => null, 'quantidade' => 8, 'colunas' => 4],
                    ['tipo' => 'vitrine', 'titulo' => 'Novidades', 'origem' => 'novidades', 'categoria_id' => null, 'quantidade' => 8, 'colunas' => 4],
                    ['tipo' => 'newsletter', 'titulo' => 'Receba as novidades primeiro', 'texto' => 'Lançamentos e ofertas no seu e-mail.', 'botao_texto' => 'Quero receber'],
                ],
            ],
            'balcao' => [
                'nome' => 'Balcão', 'ramo' => 'Distribuidora, materiais, peças e produtos técnicos',
                'descricao' => 'Lista com o código à vista, para quem compra pela referência.',
                'tema' => [
                    'cor_primaria' => '#0a6c74', 'cor_fundo' => '#ffffff', 'cor_superficie' => '#f2f6f6', 'cor_texto' => '#10242a',
                    'cor_cabecalho' => '#ffffff', 'cor_cabecalho_texto' => '#10242a',
                    'cor_rodape' => '#f2f6f6', 'cor_rodape_texto' => '#3d555b', 'cor_destaque' => '#c2410c',
                    'fonte_titulo' => 'manrope', 'fonte_texto' => 'manrope',
                    'raio' => '6', 'botao' => 'arredondado', 'cartao' => 'lista', 'cabecalho' => 'esquerda',
                    'movimento' => 'completo', 'faixa' => 'parada', 'titulos' => 'normal', 'preco' => 'simples', 'foto' => 'quadrada', 'logo' => '',
                ],
                'blocos' => [
                    ['tipo' => 'banner', 'titulo' => 'Peça pelo código, receba no prazo', 'subtitulo' => 'Catálogo com o estoque da loja. Busque pelo código ou pelo nome.',
                        'botao_texto' => 'Abrir o catálogo', 'botao_link' => 'produtos', 'imagem' => '', 'estilo' => 'cheio', 'altura' => 'baixa'],
                    ['tipo' => 'categorias', 'titulo' => 'Linhas de produto', 'estilo' => 'lista'],
                    ['tipo' => 'vitrine', 'titulo' => 'Mais pedidos', 'origem' => 'destaques', 'categoria_id' => null, 'quantidade' => 8, 'colunas' => 4],
                    $vantagens(['box-seam', 'Estoque real', 'O mesmo do balcão da loja'], ['upc-scan', 'Busca pelo código', 'Interno ou de barras'], ['chat-dots', 'Atendimento técnico', 'Fale com quem conhece o produto']),
                    ['tipo' => 'vitrine', 'titulo' => 'Entradas recentes', 'origem' => 'novidades', 'categoria_id' => null, 'quantidade' => 8, 'colunas' => 4],
                ],
            ],
        ];
    }

    public static function existe(?string $modelo): bool
    {
        return $modelo !== null && isset(self::todos()[$modelo]);
    }

    public static function tema(?string $modelo): array
    {
        return (self::todos()[$modelo] ?? self::todos()[self::PADRAO])['tema'];
    }

    /** Blocos do modelo, cada um com um id próprio. */
    public static function blocos(?string $modelo): array
    {
        $blocos = (self::todos()[$modelo] ?? self::todos()[self::PADRAO])['blocos'];

        // Passam pela mesma higiene do editor: o que sai daqui já está na forma em
        // que o editor grava (mesma ordem de chaves, opções como texto).
        return self::limparBlocos(array_map(fn (array $b, int $i) => ['id' => 'b' . ($i + 1)] + $b, $blocos, array_keys($blocos)));
    }

    /**
     * Cardápio de blocos do editor: tipo => rótulo, ícone, campos e valores iniciais.
     * Campo: [chave, rótulo, tipo de controle, opções?].
     */
    public static function tiposDeBloco(): array
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
                ],
                'inicial' => ['titulo' => 'Produtos', 'origem' => 'novidades', 'categoria_id' => null, 'quantidade' => 8, 'colunas' => 4],
            ],
            'categorias' => [
                'nome' => 'Categorias', 'icone' => 'tags', 'descricao' => 'Atalhos para as categorias que têm produto na loja',
                'campos' => [
                    ['titulo', 'Título', 'texto'],
                    ['estilo', 'Aparência', 'opcoes', ['cartoes' => 'Cartões com foto', 'pilulas' => 'Botões em linha', 'lista' => 'Lista de nomes']],
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
    public static function limparBlocos(array $blocos, array $categoriasDaEmpresa = []): array
    {
        $tipos = self::tiposDeBloco();
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
            foreach ($subcampos as [$chave, , $controle]) {
                $limpa[$chave] = mb_substr(trim((string) ($linha[$chave] ?? '')), 0, $controle === 'textolongo' ? 600 : 120);
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
            if (isset($tema[$f]) && isset(self::FONTES[$tema[$f]])) {
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
