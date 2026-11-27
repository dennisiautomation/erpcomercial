<?php

namespace App\Services\LojaOnline\Exclusivos;

use App\Models\LojaOnline;
use App\Models\Produto;
use App\Scopes\EmpresaScope;
use App\Services\LojaOnline\CatalogoLoja;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Modelo EXCLUSIVO da CELL MASTER — vendas no atacado (27/11/2026). Não aparece
 * para nenhuma outra loja (ver ModelosLoja::exclusivos / paraLoja).
 *
 * Onde vive: a definição (tema + página inicial) mora aqui; o visual próprio em
 * resources/views/loja/modelos/cellmaster.blade.php (só carrega com este modelo);
 * o logo em resources/loja-modelos/cellmaster/, COPIADO para a pasta da loja
 * (storage público, loja-online/{empresa}/cellmaster-*) na instalação.
 *
 * A REGRA do atacado não é do modelo: é da loja (`lojas_online.config`):
 * `pecas_minimo` (mínimo de peças no pedido inteiro) e `preco_atacado` (vitrine no
 * preço de atacado do cadastro). A instalação só as liga quando ainda estão de fábrica.
 *
 * Não há foto de banco de imagens: a página se desenha com o logo e com as fotos dos
 * produtos que a loja marcar. Na PRÉVIA, enquanto nenhum produto está marcado, as
 * fileiras mostram produtos DA PRÓPRIA EMPRESA que já têm foto, com o selo "Exemplo".
 */
class CellMaster
{
    public const CHAVE = 'cellmaster';

    public const PECAS_MINIMO = 6;

    private const ARQUIVOS = ['logo.png', 'logo-escura.png', 'marca.png', 'favicon.png', 'compartilhar.jpg'];

    public static function modelo(): array
    {
        return [
            'nome' => 'Cell Master Atacado',
            'ramo' => 'Venda no atacado',
            'descricao' => 'Feito para a Cell Master: o preto e branco do logo, o M inclinado como gesto da página e o pedido mínimo de peças sempre à vista.',
            'classe' => self::class,
            'fontes' => ['jostforte'],
            'tema' => self::tema(),
            'blocos' => self::montarBlocos(),
            'tipos' => [
                'atacado_passos' => [
                    'nome' => 'Como comprar no atacado', 'icone' => 'boxes',
                    'descricao' => 'O pedido mínimo de peças (vem de Loja online → Configurações) e os passos da compra',
                    'campos' => [
                        ['titulo', 'Título', 'texto'],
                        ['texto', 'Texto de apoio', 'texto'],
                        ['lista', 'Passos', 'lista', [['nome', 'Passo', 'texto'], ['texto', 'Explicação', 'texto']], 4],
                    ],
                    'inicial' => self::passos(),
                ],
            ],
        ];
    }

    public static function tema(): array
    {
        return [
            'movimento' => 'completo', 'faixa' => 'correndo', 'logo' => '',
            'cor_primaria' => '#0a0a0a', 'cor_fundo' => '#ffffff', 'cor_superficie' => '#f3f3f0', 'cor_texto' => '#0a0a0a',
            'cor_cabecalho' => '#0a0a0a', 'cor_cabecalho_texto' => '#ffffff',
            'cor_rodape' => '#0a0a0a', 'cor_rodape_texto' => '#d9d9d4', 'cor_destaque' => '#c8f31d',
            'fonte_titulo' => 'jostforte', 'fonte_texto' => 'jostforte',
            'raio' => '0', 'botao' => 'reto', 'cartao' => 'borda', 'cabecalho' => 'esquerda',
            'titulos' => 'normal', 'preco' => 'simples', 'foto' => 'quadrada',
        ];
    }

    /** Página inicial da loja (o logo já copiado para a pasta dela). */
    public static function blocos(LojaOnline $loja): array
    {
        self::copiarArquivos($loja);

        return self::montarBlocos();
    }

    /**
     * Aplica o modelo na loja: modelo, logo, página inicial publicada e as opções
     * que ainda estão em branco / de fábrica (nada que a loja já escreveu é sobrescrito).
     */
    public static function instalar(LojaOnline $loja): void
    {
        $blocos = \App\Services\LojaOnline\ModelosLoja::blocos(self::CHAVE, $loja);
        $tel = preg_replace('/\D/', '', (string) ($loja->unidade?->telefone ?: ($loja->empresa?->telefone ?? '')));
        $whats = strlen($tel) === 11 ? sprintf('(%s) %s-%s', substr($tel, 0, 2), substr($tel, 2, 5), substr($tel, 7))
            : (strlen($tel) === 10 ? sprintf('(%s) %s-%s', substr($tel, 0, 2), substr($tel, 2, 4), substr($tel, 6)) : '');

        $sugestoes = [
            'whatsapp' => $whats,
            'whatsapp_mensagem' => 'Olá, Cell Master! Vim pela loja de atacado.',
            'texto_rodape' => 'Vendas no atacado. Pedido mínimo de ' . self::PECAS_MINIMO . ' peças, misturando os produtos que quiser.',
            'seo_titulo' => 'Cell Master — vendas no atacado',
            'descricao_busca' => 'Compre no atacado na Cell Master: pedido mínimo de ' . self::PECAS_MINIMO . ' peças, pagamento por PIX ou cartão.',
            'favicon' => self::caminho($loja, 'favicon.png'),
            'imagem_compartilhar' => self::caminho($loja, 'compartilhar.jpg'),
        ];
        $config = $loja->config ?? [];
        foreach ($sugestoes as $k => $v) {
            if (blank($config[$k] ?? null) && filled($v)) {
                $config[$k] = $v;
            }
        }
        // Regra do atacado: só entra quando a loja ainda não decidiu (chave ausente).
        $config += ['pecas_minimo' => self::PECAS_MINIMO, 'preco_atacado' => true];

        $loja->update([
            'modelo' => self::CHAVE,
            'tema' => ['logo' => self::caminho($loja, 'logo.png')],
            'blocos' => $blocos,
            'rascunho' => null,
            'config' => $config,
        ]);
    }

    /**
     * Cartões de exemplo da PRÉVIA: produtos da própria empresa que já têm foto, no
     * preço que a loja vai praticar. Não há nada inventado; sem foto no cadastro, não há exemplo.
     */
    public static function exemplos(LojaOnline $loja, int $quantos = 8): Collection
    {
        if (! $loja->unidade_id) {
            return collect();
        }
        $produtos = Produto::withoutGlobalScope(EmpresaScope::class)
            ->where('empresa_id', $loja->empresa_id)->where('status', 'ativo')
            ->whereNotNull('foto')->where('foto', '!=', '')
            ->where(fn ($w) => $w->whereNull('grade_id')->orWhereColumn('grade_id', 'id'))
            ->orderByDesc('id')->limit($quantos)->get();

        return (new CatalogoLoja($loja))->paraVitrine($produtos)->values()->map(fn (array $p) => array_merge($p, [
            'grade' => true, 'a_partir' => false, 'caminho' => 'produtos', 'foto2' => null, 'exemplo' => true,
            'disponivel' => true, 'novo' => false, 'frete_gratis' => false, 'destaque' => false,
        ]));
    }

    /* ---------------------------------------------------------------- */

    private static function passos(): array
    {
        return [
            'titulo' => 'Como comprar no atacado',
            'texto' => 'Pode misturar os produtos que quiser para fechar o pedido.',
            'lista' => [
                ['nome' => 'Monte o pedido', 'texto' => 'Escolha os produtos e as quantidades. A sacola mostra quantas peças faltam para o mínimo.'],
                ['nome' => 'Pague por PIX ou cartão', 'texto' => 'O pagamento é feito na hora, em página segura, e o pedido já chega confirmado para a loja.'],
                ['nome' => 'Receba ou retire', 'texto' => 'Entrega no endereço, calculada pelo CEP no fechamento, ou retirada na loja.'],
            ],
        ];
    }

    private static function montarBlocos(): array
    {
        $min = self::PECAS_MINIMO;
        $slide = fn (string $titulo, string $texto, string $botao, string $link) => [
            'imagem' => '', 'titulo' => $titulo, 'texto' => $texto, 'botao_texto' => $botao, 'botao_link' => $link, 'tom' => 'claro', 'lado' => 'esquerda',
        ];
        $vitrine = fn (string $titulo, string $origem, int $qtd, string $formato) => ['tipo' => 'vitrine', 'titulo' => $titulo, 'origem' => $origem,
            'categoria_id' => null, 'quantidade' => $qtd, 'colunas' => 4, 'formato' => $formato];

        return [
            ['tipo' => 'faixa', 'texto' => "Pedido mínimo de {$min} peças · Pode misturar produtos · PIX ou cartão"],
            ['tipo' => 'carrossel', 'altura' => 'alta', 'lista' => [
                $slide('Preço de atacado para a sua revenda', "Monte o pedido com {$min} peças ou mais, misturando os produtos que quiser.", 'Ver os produtos', 'produtos'),
                $slide('Chegou agora no estoque', 'As novidades entram aqui primeiro. O estoque do site é o mesmo da loja.', 'Ver as novidades', 'produtos?ordem=novos'),
            ]],
            ['tipo' => 'atacado_passos'] + self::passos(),
            ['tipo' => 'categorias', 'titulo' => 'Compre por categoria', 'estilo' => 'cartoes'],
            $vitrine('Novidades', 'novidades', 8, 'carrossel'),
            $vitrine('Mais pedidos pelos revendedores', 'destaques', 8, 'grade'),
            ['tipo' => 'faq', 'titulo' => 'Dúvidas de quem compra no atacado', 'lista' => [
                ['nome' => 'Qual é o pedido mínimo?', 'texto' => "São {$min} peças no pedido inteiro. Não precisa ser do mesmo produto."],
                ['nome' => 'Posso misturar produtos diferentes?', 'texto' => 'Pode. O que conta é a soma das peças do pedido.'],
                ['nome' => 'Como eu pago?', 'texto' => 'Por PIX ou cartão de crédito, em página segura, no fechamento do pedido.'],
                ['nome' => 'Como eu recebo?', 'texto' => 'Você escolhe no fechamento: entrega no endereço, com o frete calculado pelo CEP, ou retirada na loja.'],
            ]],
        ];
    }

    public static function caminho(LojaOnline $loja, string $arquivo): string
    {
        return 'loja-online/' . (int) $loja->empresa_id . '/cellmaster-' . $arquivo;
    }

    /** Copia o logo e a marca para a pasta da loja (só o que ainda não está lá). */
    private static function copiarArquivos(LojaOnline $loja): void
    {
        $disco = Storage::disk('public');
        foreach (self::ARQUIVOS as $a) {
            $destino = self::caminho($loja, $a);
            $origem = resource_path('loja-modelos/cellmaster/' . $a);
            if (! $disco->exists($destino) && is_file($origem)) {
                $disco->put($destino, file_get_contents($origem));
            }
        }
    }
}
