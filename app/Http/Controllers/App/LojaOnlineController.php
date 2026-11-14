<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Estoque;
use App\Models\LojaOnline;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\Unidade;
use App\Models\User;
use App\Scopes\EmpresaScope;
use App\Scopes\UnidadeScope;
use App\Services\LojaOnline\CatalogoLoja;
use App\Services\LojaOnline\ModelosLoja;
use App\Services\LojaOnline\PagamentoLoja;
use App\Services\LojaOnline\TemaCss;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Painel do lojista da Loja online (14/11/2026) — menu Gestão → Loja online.
 *
 * Só existe para empresa com `lojas_online.ativo` (a plataforma liga; o lojista
 * não liga sozinho). Aqui ele escolhe de qual loja/estoque a vitrine vende, o
 * que aparece, a aparência (editor visual) e põe no ar. Pagamento e entrega NÃO
 * se configuram aqui: são os da aba Integrações, os mesmos do agente.
 */
class LojaOnlineController extends Controller
{
    private function empresaId(): int
    {
        $id = auth()->user()->empresa_id ?? session('empresa_id');
        abort_unless($id, 404);

        return (int) $id;
    }

    private function loja(): LojaOnline
    {
        $loja = LojaOnline::where('empresa_id', $this->empresaId())->where('ativo', true)->first();
        abort_unless($loja, 404, 'A loja online não está liberada para esta empresa.');

        return $loja;
    }

    private function unidades(int $empresaId)
    {
        return Unidade::withoutGlobalScope(EmpresaScope::class)->where('empresa_id', $empresaId)
            ->where('status', 'ativa')->orderBy('nome')->get(['id', 'nome', 'cidade', 'uf']);
    }

    public function index()
    {
        $loja = $this->loja();
        $catalogo = new CatalogoLoja($loja);
        $meios = PagamentoLoja::meios($loja);

        $pedidos = Pedido::withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', $loja->empresa_id)->whereNotNull('loja_token')
            ->with('cliente:id,nome_razao_social')->orderByDesc('id')->limit(10)->get();

        $noCatalogo = $catalogo->totalNaLoja();
        $semFoto = $catalogo->base()->whereNull('foto')->count();

        return view('app.loja-online.index', [
            'loja' => $loja,
            'pedidos' => $pedidos,
            'totalPedidos' => Pedido::withoutGlobalScope(UnidadeScope::class)->where('empresa_id', $loja->empresa_id)->whereNotNull('loja_token')->count(),
            'noCatalogo' => $noCatalogo,
            'semFoto' => $semFoto,
            'meios' => $meios,
            'entregaLigada' => \App\Services\Entrega\UberDirectService::ativoPara($loja->empresa_id) || \App\Services\Entrega\MelhorEnvioService::ativoPara($loja->empresa_id),
            'passos' => [
                ['feito' => (bool) $loja->unidade_id, 'titulo' => 'Escolher de qual loja a vitrine vende', 'rota' => route('app.loja-online.configuracoes')],
                ['feito' => $noCatalogo > 0, 'titulo' => 'Marcar os produtos que aparecem', 'rota' => route('app.loja-online.produtos')],
                ['feito' => filled($loja->blocos), 'titulo' => 'Escolher o modelo e montar a página inicial', 'rota' => route('app.loja-online.editor')],
                ['feito' => (bool) ($meios['pix'] || $meios['cartao']), 'titulo' => 'Ter PIX ou cartão ligado em Integrações', 'rota' => null],
                ['feito' => $loja->publicada, 'titulo' => 'Pôr a loja no ar', 'rota' => null],
            ],
        ]);
    }

    public function configuracoes()
    {
        $loja = $this->loja();
        $empresaId = (int) $loja->empresa_id;

        return view('app.loja-online.configuracoes', [
            'loja' => $loja,
            'unidades' => $this->unidades($empresaId),
            'estoques' => Estoque::withoutGlobalScopes()->where('empresa_id', $empresaId)->where('status', 'ativo')
                ->orderByDesc('is_padrao')->orderBy('nome')->get(['id', 'unidade_id', 'nome', 'permite_venda', 'is_padrao']),
            'vendedores' => User::withoutGlobalScope(EmpresaScope::class)->where('empresa_id', $empresaId)->where('status', 'ativo')->orderBy('name')->get(['id', 'name']),
            'meios' => PagamentoLoja::meios($loja),
        ]);
    }

    public function salvarConfiguracoes(Request $request)
    {
        $loja = $this->loja();
        $empresaId = (int) $loja->empresa_id;

        $dados = $request->validate([
            'nome' => ['required', 'string', 'min:2', 'max:120'],
            'unidade_id' => ['required', Rule::exists('unidades', 'id')->where('empresa_id', $empresaId)],
            'estoque_id' => ['nullable', Rule::exists('estoques', 'id')->where('empresa_id', $empresaId)],
            'vendedor_id' => ['nullable', Rule::exists('users', 'id')->where('empresa_id', $empresaId)],
            'pedido_minimo' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'instagram' => ['nullable', 'string', 'max:60', 'regex:/^@?[A-Za-z0-9._]+$/'],
            'email' => ['nullable', 'email', 'max:120'],
            'aviso_topo' => ['nullable', 'string', 'max:120'],
            'texto_rodape' => ['nullable', 'string', 'max:300'],
            'texto_trocas' => ['nullable', 'string', 'max:2000'],
            'descricao_busca' => ['nullable', 'string', 'max:160'],
        ], [
            'unidade_id.required' => 'Escolha de qual loja a loja online vende.',
            'instagram.regex' => 'Informe só o nome do perfil (ex.: @minhaloja).',
        ]);

        // O estoque escolhido tem de ser da loja escolhida
        $estoqueId = $dados['estoque_id'] ?? null;
        if ($estoqueId && ! Estoque::withoutGlobalScopes()->whereKey($estoqueId)->where('unidade_id', $dados['unidade_id'])->exists()) {
            return back()->withInput()->withErrors(['estoque_id' => 'Este estoque não é da loja escolhida.']);
        }

        $config = $loja->config ?? [];
        foreach (['vender_sem_estoque', 'mostrar_estoque', 'retirada', 'entrega', 'pix', 'cartao', 'combinar'] as $chave) {
            $config[$chave] = $request->boolean($chave);
        }
        if (! $config['retirada'] && ! $config['entrega']) {
            return back()->withInput()->withErrors(['retirada' => 'Deixe ligada pelo menos uma forma de receber: retirada ou entrega.']);
        }
        foreach (['whatsapp', 'instagram', 'email', 'aviso_topo', 'texto_rodape', 'texto_trocas', 'descricao_busca'] as $chave) {
            $config[$chave] = trim((string) ($dados[$chave] ?? ''));
        }
        $config['pedido_minimo'] = round((float) ($dados['pedido_minimo'] ?? 0), 2);

        $loja->update([
            'nome' => $dados['nome'],
            'unidade_id' => $dados['unidade_id'],
            'estoque_id' => $estoqueId,
            'vendedor_id' => $dados['vendedor_id'] ?? null,
            'config' => $config,
        ]);

        return redirect()->route('app.loja-online.configuracoes')->with('success', 'Configurações da loja online salvas.');
    }

    public function publicar(Request $request)
    {
        $loja = $this->loja();
        $ligar = $request->boolean('publicar');

        if ($ligar && ! $loja->unidade_id) {
            return back()->with('error', 'Antes de pôr no ar, escolha em Configurações de qual loja a loja online vende.');
        }
        if ($ligar && (new CatalogoLoja($loja))->totalNaLoja() === 0) {
            return back()->with('error', 'Antes de pôr no ar, marque pelo menos um produto para aparecer na loja online.');
        }

        $loja->update(['publicada' => $ligar, 'publicada_em' => $ligar ? now() : $loja->publicada_em]);

        return back()->with('success', $ligar ? 'A loja online está no ar.' : 'A loja online saiu do ar. Só quem está logado no ERP consegue ver.');
    }

    /* ---------------------------------------------------------------- */
    /*  Produtos que aparecem                                            */
    /* ---------------------------------------------------------------- */

    public function produtos(Request $request)
    {
        $loja = $this->loja();
        $empresaId = (int) $loja->empresa_id;

        $q = Produto::withoutGlobalScope(EmpresaScope::class)->where('empresa_id', $empresaId)->where('status', 'ativo');

        if (($termo = trim((string) $request->query('q'))) !== '') {
            $q->where(function ($w) use ($termo) {
                foreach (array_filter(preg_split('/\s+/', $termo)) as $palavra) {
                    $w->where('descricao', 'like', '%' . addcslashes($palavra, '%_\\') . '%');
                }
                $w->orWhere('codigo_interno', $termo)->orWhere('codigo_barras', $termo);
            });
        }
        if ($cat = (int) $request->query('categoria')) {
            $q->where('categoria_id', $cat);
        }
        match ($request->query('situacao')) {
            'na_loja' => $q->where('loja_online', true),
            'fora' => $q->where('loja_online', false),
            'destaque' => $q->where('loja_online_destaque', true),
            'sem_foto' => $q->where('loja_online', true)->whereNull('foto'),
            default => null,
        };

        $produtos = $q->orderBy('descricao')->paginate(50)->withQueryString();
        $catalogo = new CatalogoLoja($loja);

        return view('app.loja-online.produtos', [
            'loja' => $loja,
            'produtos' => $produtos,
            'saldos' => $catalogo->saldos($produtos->pluck('id')->all()),
            'categorias' => Categoria::withoutGlobalScopes()->where('empresa_id', $empresaId)->orderBy('nome')->get(['id', 'nome']),
            'noCatalogo' => $catalogo->totalNaLoja(),
            'totalAtivos' => Produto::withoutGlobalScope(EmpresaScope::class)->where('empresa_id', $empresaId)->where('status', 'ativo')->count(),
        ]);
    }

    public function marcarProdutos(Request $request)
    {
        $loja = $this->loja();
        $empresaId = (int) $loja->empresa_id;

        $dados = $request->validate([
            'acao' => ['required', Rule::in(['entrar', 'sair', 'destacar', 'tirar_destaque'])],
            'alcance' => ['required', Rule::in(['marcados', 'categoria', 'todos'])],
            'ids' => ['nullable', 'array', 'max:500'],
            'ids.*' => ['integer'],
            'categoria_id' => ['nullable', 'integer'],
        ]);

        $q = Produto::withoutGlobalScope(EmpresaScope::class)->where('empresa_id', $empresaId)->where('status', 'ativo');
        if ($dados['alcance'] === 'marcados') {
            if (empty($dados['ids'])) {
                return back()->with('error', 'Marque pelo menos um produto na lista.');
            }
            $q->whereIn('id', $dados['ids']);
        } elseif ($dados['alcance'] === 'categoria') {
            if (empty($dados['categoria_id'])) {
                return back()->with('error', 'Escolha a categoria no filtro antes de aplicar a ela.');
            }
            $q->where('categoria_id', $dados['categoria_id']);
        }

        $n = match ($dados['acao']) {
            'entrar' => $q->update(['loja_online' => true]),
            'sair' => $q->update(['loja_online' => false, 'loja_online_destaque' => false]),
            'destacar' => $q->update(['loja_online' => true, 'loja_online_destaque' => true]),
            'tirar_destaque' => $q->update(['loja_online_destaque' => false]),
        };

        $frase = ['entrar' => 'entraram na loja online', 'sair' => 'saíram da loja online',
            'destacar' => 'viraram destaque', 'tirar_destaque' => 'deixaram de ser destaque'][$dados['acao']];

        return back()->with('success', "{$n} produto(s) {$frase}.");
    }

    /* ---------------------------------------------------------------- */
    /*  Editor visual                                                    */
    /* ---------------------------------------------------------------- */

    public function editor()
    {
        $loja = $this->loja();

        return view('app.loja-online.editor', [
            'loja' => $loja,
            'rascunho' => $loja->rascunhoEfetivo(),
            'temRascunho' => filled($loja->rascunho),
            'modelos' => collect(ModelosLoja::todos())->map(fn ($m, $k) => [
                'chave' => $k, 'nome' => $m['nome'], 'ramo' => $m['ramo'], 'descricao' => $m['descricao'], 'tema' => $m['tema'],
                'fontes' => TemaCss::fontesUrl($m['tema']), 'variaveis' => TemaCss::variaveis($m['tema']),
                'banner' => collect($m['blocos'])->firstWhere('tipo', 'banner')['estilo'] ?? 'dividido',
                'titulo' => collect($m['blocos'])->firstWhere('tipo', 'banner')['titulo'] ?? $m['nome'],
            ])->values(),
            'tipos' => ModelosLoja::tiposDeBloco(),
            'fontes' => collect(ModelosLoja::FONTES)->map(fn ($f) => $f[0]),
            'opcoesTema' => ModelosLoja::OPCOES_TEMA,
            'cores' => ModelosLoja::CORES,
            'icones' => ModelosLoja::ICONES,
            'categorias' => Categoria::withoutGlobalScopes()->where('empresa_id', $loja->empresa_id)->orderBy('nome')->get(['id', 'nome']),
            'previa' => $loja->enderecoInterno() . '?rascunho=1',
        ]);
    }

    /** Salva o que o editor está montando (não muda a loja no ar). */
    public function salvarRascunho(Request $request)
    {
        $loja = $this->loja();
        $dados = $request->validate([
            'modelo' => ['required', 'string', Rule::in(array_keys(ModelosLoja::todos()))],
            'tema' => ['present', 'array'],
            'blocos' => ['present', 'array', 'max:30'],
        ]);

        $categorias = Categoria::withoutGlobalScopes()->where('empresa_id', $loja->empresa_id)->pluck('id')->map(fn ($i) => (int) $i)->all();

        $loja->update(['rascunho' => [
            'modelo' => $dados['modelo'],
            'tema' => ModelosLoja::limparTema($dados['tema']),
            'blocos' => ModelosLoja::limparBlocos($dados['blocos'], $categorias),
        ]]);

        return response()->json(['ok' => true, 'rascunho' => $loja->fresh()->rascunhoEfetivo()]);
    }

    /** Troca de modelo dentro do editor: tema e página inicial voltam aos do modelo. */
    public function aplicarModelo(Request $request)
    {
        $loja = $this->loja();
        $dados = $request->validate(['modelo' => ['required', 'string', Rule::in(array_keys(ModelosLoja::todos()))]]);

        $logo = $loja->rascunhoEfetivo()['tema']['logo'] ?? '';
        $loja->update(['rascunho' => [
            'modelo' => $dados['modelo'],
            'tema' => array_filter(['logo' => $logo]),
            'blocos' => ModelosLoja::blocos($dados['modelo']),
        ]]);

        return response()->json(['ok' => true, 'rascunho' => $loja->fresh()->rascunhoEfetivo()]);
    }

    public function publicarRascunho()
    {
        $loja = $this->loja();
        $r = $loja->rascunhoEfetivo();

        // Guarda só o que difere do modelo: se o modelo melhorar, a loja herda.
        $base = ModelosLoja::tema($r['modelo']);
        $tema = array_filter($r['tema'], fn ($v, $k) => ($base[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH);

        $loja->update(['modelo' => $r['modelo'], 'tema' => $tema ?: null, 'blocos' => $r['blocos'], 'rascunho' => null]);

        return response()->json(['ok' => true]);
    }

    public function descartarRascunho()
    {
        $loja = $this->loja();
        $loja->update(['rascunho' => null]);

        return response()->json(['ok' => true, 'rascunho' => $loja->fresh()->rascunhoEfetivo()]);
    }

    /** Imagem do editor (banner, logo, bloco). Fica em storage público, pasta da empresa. */
    public function enviarImagem(Request $request)
    {
        $loja = $this->loja();
        $request->validate(
            ['imagem' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192']],
            ['imagem.max' => 'A imagem pode ter até 8 MB.', 'imagem.mimes' => 'Envie uma imagem JPG, PNG ou WebP.', 'imagem.image' => 'O arquivo não é uma imagem.']
        );

        $arquivo = $request->file('imagem');
        $ext = strtolower($arquivo->guessExtension() ?: 'jpg');
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        $nome = Str::lower(Str::random(24)) . '.' . $ext;
        $caminho = $arquivo->storeAs('loja-online/' . $loja->empresa_id, $nome, 'public');

        return response()->json(['ok' => true, 'caminho' => $caminho, 'url' => Storage::url($caminho)]);
    }
}
