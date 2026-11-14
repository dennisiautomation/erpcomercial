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
            'facebook' => ['nullable', 'string', 'max:60', 'regex:/^@?[A-Za-z0-9._-]+$/'],
            'tiktok' => ['nullable', 'string', 'max:60', 'regex:/^@?[A-Za-z0-9._]+$/'],
            'youtube' => ['nullable', 'string', 'max:60', 'regex:/^@?[A-Za-z0-9._-]+$/'],
            'whatsapp_mensagem' => ['nullable', 'string', 'max:160'],
            'horario_atendimento' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:120'],
            'aviso_topo' => ['nullable', 'string', 'max:120'],
            'texto_rodape' => ['nullable', 'string', 'max:300'],
        ], [
            'unidade_id.required' => 'Escolha de qual loja a loja online vende.',
            'instagram.regex' => 'Informe só o nome do perfil (ex.: @minhaloja).',
            'facebook.regex' => 'Informe só o nome da página (ex.: minhaloja).', 'tiktok.regex' => 'Informe só o nome do perfil (ex.: @minhaloja).', 'youtube.regex' => 'Informe só o nome do canal (ex.: @minhaloja).',
        ]);

        // O estoque escolhido tem de ser da loja escolhida
        $estoqueId = $dados['estoque_id'] ?? null;
        if ($estoqueId && ! Estoque::withoutGlobalScopes()->whereKey($estoqueId)->where('unidade_id', $dados['unidade_id'])->exists()) {
            return back()->withInput()->withErrors(['estoque_id' => 'Este estoque não é da loja escolhida.']);
        }

        $config = $loja->config ?? [];
        foreach (['vender_sem_estoque', 'mostrar_estoque', 'retirada', 'entrega', 'pix', 'cartao', 'combinar', 'whatsapp_flutuante'] as $chave) {
            $config[$chave] = $request->boolean($chave);
        }
        if (! $config['retirada'] && ! $config['entrega']) {
            return back()->withInput()->withErrors(['retirada' => 'Deixe ligada pelo menos uma forma de receber: retirada ou entrega.']);
        }
        foreach (['whatsapp', 'instagram', 'facebook', 'tiktok', 'youtube', 'whatsapp_mensagem', 'horario_atendimento', 'email', 'aviso_topo', 'texto_rodape'] as $chave) {
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

    /* ---------------------------------------------------------------- */
    /*  2ª rodada (14/11/2026): promoções, avisos, políticas, rastreamento */
    /* ---------------------------------------------------------------- */

    /**
     * Campos de cada tela que moram em lojas_online.config: chave => regra.
     * 'bool' vem de interruptor (ausente = desligado); o resto é texto validado.
     */
    private const SECOES = [
        'promocoes' => [
            'frete_gratis_acima' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'popup_ativo' => 'bool', 'popup_capturar' => 'bool',
            'popup_titulo' => ['nullable', 'string', 'max:80'],
            'popup_texto' => ['nullable', 'string', 'max:240'],
            'popup_botao' => ['nullable', 'string', 'max:40'],
            'popup_link' => ['nullable', 'string', 'max:200'],
            'popup_cupom' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]*$/'],
            'popup_gatilho' => ['required', 'in:entrar,tempo,saida'],
            'popup_segundos' => ['nullable', 'integer', 'min:1', 'max:120'],
            'popup_dias' => ['nullable', 'integer', 'min:1', 'max:90'],
        ],
        'avisos' => [
            'email_pedidos' => ['nullable', 'email', 'max:120'],
            'email_remetente' => ['nullable', 'string', 'max:60'],
            'email_loja_novo' => 'bool', 'email_cliente_recebido' => 'bool', 'email_cliente_confirmado' => 'bool', 'email_cliente_andamento' => 'bool',
        ],
        'politicas' => [
            'politica_privacidade' => ['nullable', 'string', 'max:20000'],
            'texto_trocas' => ['nullable', 'string', 'max:20000'],
            'politica_entrega' => ['nullable', 'string', 'max:20000'],
            'politica_termos' => ['nullable', 'string', 'max:20000'],
            'cookies_aviso' => 'bool',
        ],
        'rastreamento' => [
            'meta_pixel' => ['nullable', 'regex:/^\d{6,20}$/'],
            'ga4' => ['nullable', 'regex:/^G-[A-Z0-9]{4,15}$/'],
            'google_ads' => ['nullable', 'regex:/^AW-\d{6,15}$/'],
            'google_ads_rotulo' => ['nullable', 'regex:/^[A-Za-z0-9_-]{4,40}$/'],
            'gtm' => ['nullable', 'regex:/^GTM-[A-Z0-9]{4,12}$/'],
            'tiktok_pixel' => ['nullable', 'regex:/^[A-Z0-9]{10,30}$/'],
            'verificacao_google' => ['nullable', 'regex:/^[A-Za-z0-9_-]{10,100}$/'],
            'verificacao_meta' => ['nullable', 'regex:/^[A-Za-z0-9]{10,100}$/'],
            'seo_titulo' => ['nullable', 'string', 'max:70'],
            'descricao_busca' => ['nullable', 'string', 'max:160'],
        ],
    ];

    private const MENSAGENS_SECAO = [
        'meta_pixel.regex' => 'O ID do Pixel da Meta tem só números (ex.: 1234567890123456).',
        'ga4.regex' => 'O ID do Google Analytics começa com G- (ex.: G-AB12CD34EF).',
        'google_ads.regex' => 'O ID do Google Ads começa com AW- e só números (ex.: AW-123456789).',
        'google_ads_rotulo.regex' => 'O rótulo de conversão é o trecho depois da barra em AW-123/AbCdEf (só letras, números, - e _).',
        'gtm.regex' => 'O ID do Tag Manager começa com GTM- (ex.: GTM-ABC1234).',
        'tiktok_pixel.regex' => 'O ID do Pixel do TikTok tem letras maiúsculas e números (ex.: C4ABCDE1234567890XYZ).',
        'verificacao_google.regex' => 'Cole só o código do content="…" da tag de verificação do Google.',
        'verificacao_meta.regex' => 'Cole só o código do content="…" da tag de verificação da Meta.',
        'popup_cupom.regex' => 'O cupom aceita só letras, números, hífen e sublinhado.',
        'email_pedidos.email' => 'Confira o e-mail que recebe os pedidos.',
    ];

    private function tela(string $secao, array $extra = [])
    {
        $loja = $this->loja();

        return view('app.loja-online.' . $secao, ['loja' => $loja] + $extra);
    }

    public function promocoes()
    {
        $loja = $this->loja();

        return $this->tela('promocoes', [
            'cupons' => \App\Models\LojaCupom::where('empresa_id', $loja->empresa_id)->orderByDesc('id')->get(),
            'contatos' => \App\Models\LojaContato::where('empresa_id', $loja->empresa_id)->orderByDesc('id')->limit(30)->get(),
            'totalContatos' => \App\Models\LojaContato::where('empresa_id', $loja->empresa_id)->count(),
            'promocoesErp' => (new CatalogoLoja($loja))->idsEmPromocao(),
            'podePromocaoErp' => \App\Http\Middleware\CheckPermission::canUser(auth()->user(), 'produtos', 'editar'),
        ]);
    }

    public function avisos()
    {
        return $this->tela('avisos');
    }

    public function politicas()
    {
        $loja = $this->loja();

        return $this->tela('politicas', [
            'modelos' => collect(\App\Services\LojaOnline\PoliticasLoja::TIPOS)->map(fn ($t, $k) => [
                'titulo' => $t[0], 'campo' => $t[1], 'modelo' => \App\Services\LojaOnline\PoliticasLoja::modelo($loja, $k),
            ]),
        ]);
    }

    public function rastreamento()
    {
        return $this->tela('rastreamento');
    }

    public function salvarSecao(Request $request, string $secao)
    {
        abort_unless(isset(self::SECOES[$secao]), 404);
        $loja = $this->loja();
        $campos = self::SECOES[$secao];

        if ($request->filled('frete_gratis_acima')) {
            $request->merge(['frete_gratis_acima' => str_replace(',', '.', (string) $request->input('frete_gratis_acima'))]);
        }
        $dados = $request->validate(array_filter($campos, 'is_array'), self::MENSAGENS_SECAO);

        $config = $loja->config ?? [];
        foreach ($campos as $chave => $regra) {
            if ($regra === 'bool') {
                $config[$chave] = $request->boolean($chave);
            } elseif (in_array($chave, ['frete_gratis_acima'], true)) {
                $config[$chave] = round((float) ($dados[$chave] ?? 0), 2);
            } elseif (in_array($chave, ['popup_segundos', 'popup_dias'], true)) {
                $config[$chave] = (int) ($dados[$chave] ?? LojaOnline::CONFIG_PADRAO[$chave]);
            } else {
                $config[$chave] = trim((string) ($dados[$chave] ?? ''));
            }
        }

        if ($secao === 'promocoes') {
            $config['popup_cupom'] = \App\Services\LojaOnline\CupomLoja::normalizar($config['popup_cupom']);
            $config['popup_link'] = ModelosLoja::limparLink($config['popup_link']);
            if ($config['popup_ativo'] && $config['popup_titulo'] === '') {
                return back()->withInput()->withErrors(['popup_titulo' => 'Para ligar o popup, escreva o título dele.']);
            }
            if ($config['popup_cupom'] !== '' && ! \App\Models\LojaCupom::where('empresa_id', $loja->empresa_id)->where('codigo', $config['popup_cupom'])->exists()) {
                return back()->withInput()->withErrors(['popup_cupom' => 'O cupom ' . $config['popup_cupom'] . ' não existe. Crie o cupom abaixo antes de mostrá-lo no popup.']);
            }
        }
        if ($secao === 'avisos' && $config['email_loja_novo'] && $config['email_pedidos'] === '') {
            return back()->withInput()->withErrors(['email_pedidos' => 'Para avisar a loja, informe o e-mail que recebe os pedidos.']);
        }

        // Imagens da seção (popup, imagem de compartilhar, ícone da aba): mesma pasta e regras do editor
        foreach (['promocoes' => ['popup_imagem'], 'rastreamento' => ['imagem_compartilhar', 'favicon']][$secao] ?? [] as $campoImagem) {
            if ($request->boolean('remover_' . $campoImagem)) {
                $config[$campoImagem] = '';
            }
            if ($request->hasFile($campoImagem)) {
                $request->validate([$campoImagem => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192']], [$campoImagem . '.*' => 'Envie uma imagem JPG, PNG ou WebP de até 8 MB.']);
                $arquivo = $request->file($campoImagem);
                $ext = strtolower($arquivo->guessExtension() ?: 'jpg');
                $config[$campoImagem] = $arquivo->storeAs('loja-online/' . $loja->empresa_id, Str::lower(Str::random(24)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext), 'public');
            }
        }

        $loja->update(['config' => $config]);

        return redirect()->route('app.loja-online.' . $secao)->with('success', 'Salvo.');
    }

    /* ----- cupons ----- */

    public function criarCupom(Request $request)
    {
        $loja = $this->loja();
        // 20,00 ou 1.234,56 → 20.00 / 1234.56 (quem digita usa vírgula)
        $numero = fn ($v) => $v === null || $v === '' ? null : (str_contains((string) $v, ',') ? str_replace(',', '.', str_replace('.', '', (string) $v)) : (string) $v);
        $request->merge([
            'codigo' => \App\Services\LojaOnline\CupomLoja::normalizar($request->input('codigo')),
            'valor' => $numero($request->input('valor')), 'minimo' => $numero($request->input('minimo')),
        ]);

        $dados = $request->validate([
            'codigo' => ['required', 'string', 'min:3', 'max:30', Rule::unique('loja_cupons', 'codigo')->where('empresa_id', $loja->empresa_id)],
            'tipo' => ['required', Rule::in(array_keys(\App\Models\LojaCupom::TIPOS))],
            'valor' => ['required_unless:tipo,frete_gratis', 'nullable', 'numeric', 'min:0.01', 'max:99999'],
            'minimo' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'inicio' => ['nullable', 'date'],
            'fim' => ['nullable', 'date', 'after_or_equal:inicio'],
            'limite_usos' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ], [
            'codigo.required' => 'Escreva o código do cupom (ex.: BEMVINDO10).', 'codigo.min' => 'O código precisa de pelo menos 3 letras ou números.',
            'codigo.unique' => 'Já existe um cupom com este código.', 'valor.required_unless' => 'Informe o valor do desconto.',
            'fim.after_or_equal' => 'A data final não pode ser antes da inicial.',
        ]);
        if ($dados['tipo'] === 'percentual' && (float) $dados['valor'] > 100) {
            return back()->withInput()->withErrors(['valor' => 'Desconto em % vai até 100.']);
        }

        \App\Models\LojaCupom::create([
            'empresa_id' => $loja->empresa_id, 'codigo' => $dados['codigo'], 'tipo' => $dados['tipo'],
            'valor' => $dados['tipo'] === 'frete_gratis' ? 0 : $dados['valor'], 'minimo' => $dados['minimo'] ?? 0,
            'inicio' => $dados['inicio'] ?? null, 'fim' => $dados['fim'] ?? null, 'limite_usos' => $dados['limite_usos'] ?? null, 'ativo' => true,
        ]);

        return redirect()->route('app.loja-online.promocoes')->with('success', 'Cupom ' . $dados['codigo'] . ' criado.');
    }

    private function cupomDaLoja(int $id): \App\Models\LojaCupom
    {
        return \App\Models\LojaCupom::where('empresa_id', $this->loja()->empresa_id)->findOrFail($id);
    }

    public function alternarCupom(int $cupom)
    {
        $c = $this->cupomDaLoja($cupom);
        $c->update(['ativo' => ! $c->ativo]);

        return back()->with('success', 'Cupom ' . $c->codigo . ($c->ativo ? ' ligado.' : ' desligado.'));
    }

    public function excluirCupom(int $cupom)
    {
        $c = $this->cupomDaLoja($cupom);
        $c->delete();

        return back()->with('success', 'Cupom ' . $c->codigo . ' excluído. Os pedidos que já usaram continuam com o desconto.');
    }

    public function exportarContatos()
    {
        $loja = $this->loja();
        $linhas = \App\Models\LojaContato::where('empresa_id', $loja->empresa_id)->orderByDesc('id')->limit(20000)->get()
            ->map(fn ($c) => [$c->nome, $c->email, $c->whatsapp, ['popup' => 'Popup', 'newsletter' => 'Bloco de novidades', 'checkout' => 'Fechamento do pedido'][$c->origem] ?? $c->origem,
                $c->aceitou_em?->format('d/m/Y H:i')])->all();

        return \App\Support\Planilha::download(['Nome', 'E-mail', 'WhatsApp', 'De onde veio', 'Aceitou em'], $linhas, 'contatos-loja-online-' . now()->format('Ymd') . '.xlsx', 'Contatos');
    }

    /** Manda para o e-mail do próprio usuário um exemplo do aviso, com o último pedido da loja online. */
    public function testarAviso(Request $request)
    {
        $loja = $this->loja();
        $pedido = Pedido::withoutGlobalScope(UnidadeScope::class)->where('empresa_id', $loja->empresa_id)->whereNotNull('loja_token')->orderByDesc('id')->first();
        if (! $pedido) {
            return back()->with('error', 'O teste usa um pedido de verdade como exemplo, e a loja online ainda não recebeu nenhum.');
        }
        $tipo = in_array($request->input('tipo'), ['recebido', 'confirmado', 'faturado', 'loja_novo'], true) ? $request->input('tipo') : 'recebido';
        $para = (string) $request->user()->email;
        $ok = \App\Services\LojaOnline\AvisosLoja::enviar($para, $loja, $pedido, $tipo);

        return back()->with($ok ? 'success' : 'error', $ok ? "E-mail de teste enviado para {$para} (com o pedido #{$pedido->numero} de exemplo)." : 'O e-mail de teste não saiu. Veja o registro de integração.');
    }
}
