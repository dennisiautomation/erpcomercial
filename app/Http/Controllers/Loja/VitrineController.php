<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Services\LojaOnline\CarrinhoLoja;
use App\Services\LojaOnline\CatalogoLoja;
use App\Services\LojaOnline\LojaContexto;
use App\Services\LojaOnline\PagamentoLoja;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Vitrine pública da loja online (14/11/2026): página inicial em blocos, lista
 * de produtos, página do produto e páginas de apoio. Só LEITURA do ERP.
 */
class VitrineController extends Controller
{
    public function __construct(private readonly LojaContexto $ctx)
    {
    }

    private function comum(CatalogoLoja $catalogo): array
    {
        return [
            'categoriasMenu' => $catalogo->categorias(),
            'carrinhoTotal' => (new CarrinhoLoja($this->ctx->loja, $catalogo))->quantidadeTotal(),
        ];
    }

    public function home()
    {
        $loja = $this->ctx->loja;
        $catalogo = new CatalogoLoja($loja);
        $blocos = $this->ctx->rascunho ? $loja->rascunhoEfetivo()['blocos'] : $loja->blocosEfetivos();
        $categorias = $catalogo->categorias();

        // Cada bloco recebe os dados de que precisa — a view só desenha.
        $montados = [];
        $mosaico = null;
        foreach ($blocos as $bloco) {
            if (($bloco['tipo'] ?? '') === 'vitrine') {
                $bloco['produtos'] = $catalogo->fileira((string) ($bloco['origem'] ?? 'novidades'),
                    isset($bloco['categoria_id']) ? (int) $bloco['categoria_id'] : null, (int) ($bloco['quantidade'] ?? 8));
                if ($bloco['produtos']->isEmpty() && ! $this->ctx->rascunho) {
                    continue; // fileira vazia não aparece para o público
                }
            } elseif (($bloco['tipo'] ?? '') === 'categorias') {
                $bloco['categorias'] = $categorias;
                if ($categorias->isEmpty() && ! $this->ctx->rascunho) {
                    continue;
                }
            } elseif (($bloco['tipo'] ?? '') === 'banner' && blank($bloco['imagem'] ?? '')) {
                // Banner sem imagem mostra produtos de verdade no lugar de enfeite
                $mosaico ??= $catalogo->paraVitrine(
                    $catalogo->base()->whereNotNull('foto')->orderByDesc('loja_online_destaque')->orderByDesc('id')->limit(3)->get()
                );
                $bloco['mosaico'] = $mosaico;
            }
            $montados[] = $bloco;
        }

        return view('loja.home', $this->comum($catalogo) + [
            'blocos' => $montados,
            'totalProdutos' => $catalogo->totalNaLoja(),
            'titulo' => $loja->nome,
        ]);
    }

    public function produtos(Request $request)
    {
        $catalogo = new CatalogoLoja($this->ctx->loja);
        $filtros = [
            'q' => $request->query('q'),
            'categoria' => $request->query('categoria'),
            'ordem' => $request->query('ordem'),
            'promocao' => $request->boolean('promocao'),
        ];

        $categoriaAtual = null;
        if ($id = (int) $filtros['categoria']) {
            $categoriaAtual = Categoria::withoutGlobalScopes()->where('empresa_id', $this->ctx->loja->empresa_id)->whereKey($id)->first(['id', 'nome']);
        }

        return view('loja.produtos', $this->comum($catalogo) + [
            'produtos' => $catalogo->listar($filtros)->withPath($this->ctx->caminho('produtos')),
            'filtros' => $filtros,
            'categoriaAtual' => $categoriaAtual,
            'titulo' => ($categoriaAtual->nome ?? (filled($filtros['q']) ? 'Busca: ' . $filtros['q'] : 'Produtos')) . ' — ' . $this->ctx->loja->nome,
        ]);
    }

    public function produto(string $produto)
    {
        $loja = $this->ctx->loja;
        $catalogo = new CatalogoLoja($loja);
        $model = $catalogo->produto((int) $produto);
        abort_unless($model, 404);

        $dados = $catalogo->paraVitrine(collect([$model]))->first();

        // Endereço canônico: id + nome do produto
        if ($produto !== substr($dados['caminho'], 2)) {
            return redirect($this->ctx->url($dados['caminho']), 301);
        }

        return view('loja.produto', $this->comum($catalogo) + [
            'p' => $dados,
            'model' => $model,
            'galeria' => $catalogo->galeria($model),
            'relacionados' => $catalogo->relacionados($model),
            'meios' => PagamentoLoja::meios($loja),
            'titulo' => $model->descricao . ' — ' . $loja->nome,
            'descricaoPagina' => Str::limit(trim((string) $model->descricao_detalhada), 155),
        ]);
    }

    public function informacoes()
    {
        $catalogo = new CatalogoLoja($this->ctx->loja);

        return view('loja.informacoes', $this->comum($catalogo) + [
            'unidade' => $this->ctx->loja->unidade,
            'titulo' => 'Informações — ' . $this->ctx->loja->nome,
        ]);
    }

    /** Mapa do site para os buscadores (só loja no ar). */
    public function sitemap()
    {
        abort_unless($this->ctx->loja->noAr(), 404);
        $catalogo = new CatalogoLoja($this->ctx->loja);
        $urls = [$this->ctx->urlAbsoluta(), $this->ctx->urlAbsoluta('produtos')];
        foreach ($catalogo->base()->orderByDesc('id')->limit(2000)->get(['id', 'descricao']) as $p) {
            $urls[] = $this->ctx->urlAbsoluta('p/' . $p->id . '-' . (Str::slug($p->descricao) ?: 'produto'));
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $u) {
            $xml .= '<url><loc>' . e($u) . '</loc></url>';
        }

        return response($xml . '</urlset>', 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
