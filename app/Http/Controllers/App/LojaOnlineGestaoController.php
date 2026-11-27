<?php

namespace App\Http\Controllers\App;

use App\Enums\StatusPedido;
use App\Http\Controllers\Controller;
use App\Mail\PedidoLoja;
use App\Models\LojaOnline;
use App\Models\Pedido;
use App\Models\Produto;
use App\Scopes\EmpresaScope;
use App\Scopes\UnidadeScope;
use App\Services\LojaOnline\AvisosLoja;
use App\Services\LojaOnline\DominioLoja;
use App\Services\LojaOnline\GradeLoja;
use App\Services\LojaOnline\MetricasLoja;
use App\Services\LojaOnline\PagamentoLoja;
use App\Services\LojaOnline\ReservaLoja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Dia a dia da loja online (15/11/2026): pedidos (informar envio, cancelar,
 * devolver o dinheiro), grade de tamanho/cor, avaliações, carrinhos abandonados
 * e os números da loja.
 *
 * Onde cada coisa vive: o pedido é o do ERP (Comercial → Pedidos) — aqui só se
 * faz o que é próprio da venda online. FATURAR continua lá: é o faturamento que
 * baixa o estoque e lança o caixa. Devolver dinheiro é só do dono (e do admin).
 */
class LojaOnlineGestaoController extends Controller
{
    private function loja(): LojaOnline
    {
        $id = auth()->user()->empresa_id ?? session('empresa_id');
        abort_unless($id, 404);
        $loja = LojaOnline::where('empresa_id', (int) $id)->where('ativo', true)->first();
        abort_unless($loja, 404, 'A loja online não está liberada para esta empresa.');

        return $loja;
    }

    private function pedidosDaLoja(LojaOnline $loja)
    {
        return Pedido::withoutGlobalScope(UnidadeScope::class)->withoutGlobalScope(EmpresaScope::class)
            ->where('empresa_id', $loja->empresa_id)->whereNotNull('loja_token');
    }

    private function pedido(LojaOnline $loja, int $id): Pedido
    {
        return $this->pedidosDaLoja($loja)->findOrFail($id);
    }

    /* ----- pedidos ----- */

    public function pedidos(Request $request)
    {
        $loja = $this->loja();
        $filtro = (string) $request->query('ver', 'separar');
        $minutos = max(0, (int) $loja->opcao('reservar_minutos'));
        $q = $this->pedidosDaLoja($loja)->with('cliente:id,nome_razao_social,telefone,whatsapp,email,cidade,uf');

        match ($filtro) {
            'aguardando' => $q->where('status', StatusPedido::Rascunho->value),
            'enviados' => $q->whereNotNull('loja_enviado_em')->where('status', '!=', StatusPedido::Cancelado->value),
            'cancelados' => $q->where('status', StatusPedido::Cancelado->value),
            'todos' => $q,
            default => $q->whereIn('status', [StatusPedido::Confirmado->value, StatusPedido::Faturado->value])->whereNull('loja_enviado_em'),
        };
        $pedidos = $q->orderByDesc('id')->paginate(20)->withQueryString();
        $pedidos->getCollection()->transform(function (Pedido $p) {
            $p->situacaoLoja = PagamentoLoja::situacao($p);
            $p->itensResumo = DB::table('pedido_itens')->where('pedido_id', $p->id)->whereNull('deleted_at')->orderBy('id')->get(['descricao', 'quantidade']);

            return $p;
        });

        $contar = fn ($f) => $f($this->pedidosDaLoja($loja))->count();
        $reservado = ReservaLoja::porProduto($loja);

        return view('app.loja-online.pedidos', [
            'loja' => $loja, 'pedidos' => $pedidos, 'filtro' => $filtro,
            'totais' => [
                'separar' => $contar(fn ($q) => $q->whereIn('status', ['confirmado', 'faturado'])->whereNull('loja_enviado_em')),
                'aguardando' => $contar(fn ($q) => $q->where('status', 'rascunho')),
                'enviados' => $contar(fn ($q) => $q->whereNotNull('loja_enviado_em')->where('status', '!=', 'cancelado')),
                'cancelados' => $contar(fn ($q) => $q->where('status', 'cancelado')),
            ],
            'reservado' => $reservado === [] ? collect() : DB::table('produtos')->whereIn('id', array_keys($reservado))->orderBy('descricao')
                ->get(['id', 'codigo_interno', 'descricao'])->map(fn ($p) => ['codigo' => $p->codigo_interno, 'nome' => $p->descricao, 'qtd' => $reservado[$p->id]]),
            'minutos' => $minutos,
            'temAsaas' => \App\Services\Pagamento\AsaasService::ativoPara((int) $loja->empresa_id) !== null,
            'podeDevolver' => auth()->user()->isDono() || auth()->user()->isAdmin(),
        ]);
    }

    /** A loja postou/entregou ao motoboy: guarda transportadora e rastreio e avisa o comprador. */
    public function informarEnvio(Request $request, int $pedido)
    {
        $loja = $this->loja();
        $p = $this->pedido($loja, $pedido);
        if (! in_array($p->status, [StatusPedido::Confirmado, StatusPedido::Faturado], true)) {
            return back()->with('error', 'Só dá para informar o envio de pedido confirmado ou faturado.');
        }
        $dados = $request->validate([
            'transportadora' => ['nullable', 'string', 'max:60'],
            'codigo' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9 ._\-\/]*$/'],
            'link' => ['nullable', 'url', 'max:255', 'starts_with:https://,http://'],
        ], ['codigo.regex' => 'O código de rastreio aceita só letras e números.', 'link.url' => 'Confira o link de rastreio (precisa começar com https://).']);

        $codigo = mb_strtoupper(preg_replace('/\s+/', '', (string) ($dados['codigo'] ?? '')));
        $link = $dados['link'] ?? null;
        // Código no formato dos Correios (AA123456789BR) ganha o link de rastreio sozinho.
        if (! $link && preg_match('/^[A-Z]{2}\d{9}[A-Z]{2}$/', $codigo)) {
            $link = 'https://rastreamento.correios.com.br/app/index.php?objetos=' . $codigo;
        }
        $jaTinha = $p->loja_enviado_em !== null;
        $p->forceFill([
            'loja_transportadora' => filled($dados['transportadora'] ?? null) ? trim($dados['transportadora']) : null,
            'loja_rastreio_codigo' => $codigo !== '' ? $codigo : null,
            'loja_rastreio_url' => $link,
            'loja_enviado_em' => $p->loja_enviado_em ?? now(),
        ])->save();

        if ($loja->opcao('email_cliente_andamento')) {
            $email = DB::table('clientes')->where('id', $p->cliente_id)->value('email');
            if (filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                AvisosLoja::enviar($email, $loja, $p, 'enviado');
            }
        }

        return back()->with('success', $jaTinha ? 'Dados do envio atualizados.' : 'Envio informado. O cliente vê o rastreio na página do pedido.');
    }

    /** Cancela pedido que ainda NÃO foi faturado (faturado se cancela pela venda, no ERP). */
    public function cancelarPedido(Request $request, int $pedido)
    {
        $loja = $this->loja();
        $p = $this->pedido($loja, $pedido);
        if (! in_array($p->status, [StatusPedido::Rascunho, StatusPedido::Confirmado], true)) {
            return back()->with('error', $p->status === StatusPedido::Cancelado ? 'Este pedido já está cancelado.'
                : 'Pedido faturado se cancela pela venda (Comercial → Vendas), para o estoque e o caixa voltarem.');
        }
        $dados = $request->validate(['motivo' => ['required', 'string', 'min:3', 'max:200']], ['motivo.required' => 'Escreva o motivo do cancelamento.']);

        DB::transaction(function () use ($p, $dados) {
            $p->observacoes_internas = trim(($p->observacoes_internas ?? '') . "\nCANCELADO pela loja online em " . now()->format('d/m/Y H:i')
                . ' por ' . auth()->user()->name . '. Motivo: ' . $dados['motivo']);
            $p->status = StatusPedido::Cancelado;
            $p->save();
            // O cupom usado volta a valer (o pedido não aconteceu)
            if ($p->loja_cupom) {
                DB::table('loja_cupons')->where('empresa_id', $p->empresa_id)->where('codigo', $p->loja_cupom)->where('usos', '>', 0)->decrement('usos');
            }
        });
        $pago = PagamentoLoja::situacao($p)['pago'];

        return back()->with($pago ? 'warning' : 'success', $pago ? 'Pedido cancelado. Ele estava PAGO: falta devolver o dinheiro ao cliente (botão "Devolver o dinheiro").' : 'Pedido cancelado. O estoque reservado foi liberado.');
    }

    /** Devolve o dinheiro de pedido cancelado e pago. Só o dono (ou o admin da plataforma). */
    public function devolverPedido(Request $request, int $pedido, PagamentoLoja $pagamento)
    {
        abort_unless(auth()->user()->isDono() || auth()->user()->isAdmin(), 403, 'Só o dono da empresa devolve dinheiro.');
        $loja = $this->loja();
        $p = $this->pedido($loja, $pedido);
        if ($p->status !== StatusPedido::Cancelado) {
            return back()->with('error', 'Cancele o pedido antes de devolver o dinheiro.');
        }
        $dados = $request->validate([
            'motivo' => ['required', 'string', 'min:3', 'max:150'],
            'confirmo' => ['accepted'],
            'ja_devolvi' => ['nullable', 'boolean'],
        ], ['motivo.required' => 'Escreva o motivo da devolução.', 'confirmo.accepted' => 'Marque a confirmação para devolver.']);

        $r = $pagamento->estornar($p, $dados['motivo'], $request->boolean('ja_devolvi'));

        return back()->with($r['ok'] ? 'success' : 'error', $r['mensagem']);
    }

    /* ----- grade ----- */

    private function produtoDaEmpresa(LojaOnline $loja, string $codigoOuId): ?Produto
    {
        $termo = trim($codigoOuId);
        if ($termo === '') {
            return null;
        }
        $base = Produto::withoutGlobalScope(EmpresaScope::class)->where('empresa_id', $loja->empresa_id);

        return (clone $base)->where('codigo_interno', str_pad($termo, 6, '0', STR_PAD_LEFT))->first()
            ?? (clone $base)->where('codigo_interno', $termo)->first()
            ?? (clone $base)->where('codigo_barras', $termo)->first()
            ?? (clone $base)->where('sku', $termo)->first();
    }

    public function grade(Request $request)
    {
        $loja = $this->loja();
        $base = fn () => Produto::withoutGlobalScope(EmpresaScope::class)->where('empresa_id', $loja->empresa_id);

        $principais = $base()->whereColumn('grade_id', 'id')->orderBy('descricao')->get();
        $variacoes = $principais->isEmpty() ? collect() : $base()->whereIn('grade_id', $principais->pluck('id'))->orderBy('id')->get()->groupBy('grade_id');
        $saldos = [];
        if ($loja->unidade_id && $variacoes->isNotEmpty()) {
            $saldos = (new \App\Services\LojaOnline\CatalogoLoja($loja))->saldos($variacoes->flatten(1)->pluck('id')->all());
        }

        $busca = trim((string) $request->query('q'));
        $achados = $busca === '' ? collect() : $base()->whereNull('grade_id')->where('status', 'ativo')
            ->where(fn ($w) => $w->where('descricao', 'like', '%' . addcslashes($busca, '%_\\') . '%')->orWhere('codigo_interno', 'like', '%' . addcslashes($busca, '%_\\'))
                ->orWhere('codigo_barras', $busca))->orderBy('descricao')->limit(15)->get(['id', 'codigo_interno', 'descricao', 'preco_venda', 'loja_online']);

        return view('app.loja-online.grade', [
            'loja' => $loja, 'principais' => $principais, 'variacoes' => $variacoes, 'saldos' => $saldos,
            'busca' => $busca, 'achados' => $achados,
        ]);
    }

    public function gerarGrade(Request $request)
    {
        $loja = $this->loja();
        $dados = $request->validate([
            'produto_id' => ['required', 'integer'],
            'atributo' => ['required', 'array', 'min:1', 'max:' . GradeLoja::MAXIMO_ATRIBUTOS],
            'atributo.*' => ['nullable', 'string', 'max:20'],
            'valores' => ['required', 'array'],
            'valores.*' => ['nullable', 'string', 'max:400'],
        ]);
        $produto = Produto::withoutGlobalScope(EmpresaScope::class)->where('empresa_id', $loja->empresa_id)->findOrFail((int) $dados['produto_id']);

        $atributos = GradeLoja::atributosDaTela($dados['atributo'], $dados['valores']);
        try {
            $criadas = GradeLoja::gerar($produto, $atributos);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('app.loja-online.grade')->with('success', "Grade criada: {$criadas} produtos novos, com estoque zero. Dê entrada no estoque de cada um como em qualquer produto.");
    }

    public function juntarGrade(Request $request)
    {
        $loja = $this->loja();
        $dados = $request->validate(['principal_id' => ['required', 'integer'], 'codigo' => ['required', 'string', 'max:50'], 'opcoes' => ['required', 'array'], 'opcoes.*' => ['nullable', 'string', 'max:30']]);
        $principal = Produto::withoutGlobalScope(EmpresaScope::class)->where('empresa_id', $loja->empresa_id)->findOrFail((int) $dados['principal_id']);
        $outro = $this->produtoDaEmpresa($loja, $dados['codigo']);
        if (! $outro) {
            return back()->with('error', 'Não achei produto com o código ' . $dados['codigo'] . '.');
        }
        try {
            GradeLoja::juntar($principal, $outro, $dados['opcoes']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Produto ' . $outro->codigo_interno . ' entrou na grade.');
    }

    public function tirarGrade(Request $request)
    {
        $loja = $this->loja();
        $produto = Produto::withoutGlobalScope(EmpresaScope::class)->where('empresa_id', $loja->empresa_id)->findOrFail((int) $request->input('produto_id'));
        $eraPrincipal = (int) $produto->grade_id === (int) $produto->id;
        GradeLoja::tirar($produto);

        return back()->with('success', $eraPrincipal ? 'Grade desfeita. Os produtos continuam cadastrados, cada um por si.' : 'Produto tirado da grade (ele continua cadastrado).');
    }

    /* ----- avaliações ----- */

    public function avaliacoes(Request $request)
    {
        $loja = $this->loja();
        $ver = in_array($request->query('ver'), ['pendente', 'aprovada', 'oculta'], true) ? $request->query('ver') : 'pendente';

        return view('app.loja-online.avaliacoes', [
            'loja' => $loja, 'ver' => $ver,
            'avaliacoes' => DB::table('loja_avaliacoes as a')->join('produtos as p', 'p.id', '=', 'a.produto_id')
                ->where('a.empresa_id', $loja->empresa_id)->where('a.status', $ver)->orderByDesc('a.id')
                ->select('a.*', 'p.descricao as produto', 'p.codigo_interno')->paginate(20)->withQueryString(),
            'totais' => DB::table('loja_avaliacoes')->where('empresa_id', $loja->empresa_id)->groupBy('status')->selectRaw('status, COUNT(*) n')->pluck('n', 'status'),
        ]);
    }

    public function moderarAvaliacao(Request $request, int $avaliacao)
    {
        $loja = $this->loja();
        $dados = $request->validate(['status' => ['required', 'in:aprovada,oculta,pendente'], 'resposta' => ['nullable', 'string', 'max:600']]);
        $n = DB::table('loja_avaliacoes')->where('empresa_id', $loja->empresa_id)->where('id', $avaliacao)
            ->update(['status' => $dados['status'], 'resposta' => filled($dados['resposta'] ?? null) ? trim($dados['resposta']) : null, 'updated_at' => now()]);
        abort_unless($n, 404);

        return back()->with('success', $dados['status'] === 'aprovada' ? 'Avaliação publicada na loja.' : 'Avaliação fora da loja.');
    }

    /* ----- carrinhos abandonados ----- */

    public function carrinhos()
    {
        $loja = $this->loja();
        $linhas = DB::table('loja_carrinhos')->where('empresa_id', $loja->empresa_id)->whereNull('pedido_id')
            ->where('updated_at', '<', now()->subMinutes(30))->where('updated_at', '>', now()->subDays(30))
            ->orderByDesc('updated_at')->paginate(25);

        return view('app.loja-online.carrinhos', [
            'loja' => $loja, 'linhas' => $linhas,
            'recuperados' => DB::table('loja_carrinhos')->where('empresa_id', $loja->empresa_id)->whereNotNull('recuperado_em')->whereNotNull('pedido_id')
                ->where('updated_at', '>', now()->subDays(30))->count(),
            'base' => rtrim($loja->enderecoPublico(), '/'),
        ]);
    }

    /* ----- resultados ----- */

    public function resultados(Request $request)
    {
        $loja = $this->loja();
        $dias = in_array((int) $request->query('dias'), [7, 30, 90], true) ? (int) $request->query('dias') : 30;

        return view('app.loja-online.resultados', ['loja' => $loja, 'r' => MetricasLoja::resumo($loja, $dias)]);
    }

    /** Quantos estão com a loja aberta agora (a tela Resultados pergunta a cada 30 s). */
    public function resultadosAgora()
    {
        return response()->json(['agora' => MetricasLoja::agora($this->loja())]);
    }

    /* ----- domínio próprio (16/11/2026) ----- */

    public function dominio()
    {
        $loja = $this->loja();

        return view('app.loja-online.dominio', [
            'loja' => $loja,
            'registros' => $loja->dominio ? DominioLoja::registros($loja->dominio) : [],
            'situacao' => DominioLoja::SITUACOES[$loja->dominio_status] ?? null,
        ]);
    }

    public function salvarDominio(Request $request)
    {
        $loja = $this->loja();
        $dominio = DominioLoja::normalizar($request->input('dominio'));
        if ($erro = DominioLoja::erro($dominio, $loja)) {
            return back()->withInput()->withErrors(['dominio' => $erro]);
        }
        if ($dominio !== (string) $loja->dominio) {
            $loja->update(['dominio' => $dominio, 'dominio_status' => 'aguardando', 'dominio_obs' => null, 'dominio_conferido_em' => null]);
        }

        return redirect()->route('app.loja-online.dominio')->with('success', 'Domínio guardado. Agora crie o registro do passo 2 e depois clique em Conferir.');
    }

    public function conferirDominio()
    {
        $loja = $this->loja();
        abort_unless($loja->dominio, 404);
        $r = DominioLoja::conferir($loja);

        return redirect()->route('app.loja-online.dominio')->with($r['status'] === 'aguardando' ? 'warning' : 'success', $r['mensagem']);
    }

    public function tirarDominio()
    {
        $loja = $this->loja();
        $loja->update(['dominio' => null, 'dominio_status' => null, 'dominio_obs' => null, 'dominio_conferido_em' => null]);

        return redirect()->route('app.loja-online.dominio')->with('success', 'Domínio removido. A loja continua abrindo pelo endereço de sempre.');
    }

    /* ---------------------------------------------------------------- */
    /*  Categorias da vitrine (27/11/2026) — regra em CategoriasLoja     */
    /* ---------------------------------------------------------------- */

    /** Produtos que a loja mostra (um por grade), para escolher o que vai em cada categoria. */
    private function produtosDaVitrine(LojaOnline $loja)
    {
        return (new \App\Services\LojaOnline\CatalogoLoja($loja))->vitrine()->orderBy('descricao')
            ->get(['id', 'descricao', 'codigo_interno', 'categoria_id', 'foto']);
    }

    public function categorias(Request $request)
    {
        $loja = $this->loja();
        $empresaId = (int) $loja->empresa_id;
        $produtos = $this->produtosDaVitrine($loja);
        $porCategoria = $produtos->groupBy('categoria_id')->map->count();

        $lista = \App\Models\LojaCategoria::where('empresa_id', $empresaId)->orderBy('ordem')->orderBy('id')->get();
        $regra = new \App\Services\LojaOnline\CategoriasLoja($loja);
        $totais = $lista->mapWithKeys(fn ($c) => [$c->id => $produtos->filter(
            fn ($p) => in_array((int) $p->id, $c->idsProdutos(), true) || ($p->categoria_id && in_array((int) $p->categoria_id, $c->idsCategorias(), true)))->count()]);
        $semCategoria = $regra->emUso() ? $produtos->filter(fn ($p) => ! $regra->doProduto((int) $p->id, $p->categoria_id))->count() : 0;

        $editar = $request->query('editar') ? $lista->firstWhere('id', (int) $request->query('editar')) : null;

        return view('app.loja-online.categorias', [
            'loja' => $loja,
            'lista' => $lista,
            'totais' => $totais,
            'emUso' => $regra->emUso(),
            'semCategoria' => $semCategoria,
            'editar' => $editar,
            'produtos' => $produtos,
            'cadastro' => \App\Models\Categoria::withoutGlobalScopes()->where('empresa_id', $empresaId)->orderBy('nome')->get(['id', 'nome'])
                ->map(fn ($c) => ['id' => (int) $c->id, 'nome' => $c->nome, 'total' => (int) ($porCategoria[$c->id] ?? 0)])
                // primeiro as que têm produto na loja (o cadastro pode ter dezenas sem nenhum), depois o resto, em ordem alfabética
                ->sortBy(fn ($c) => [$c['total'] > 0 ? 0 : 1, mb_strtolower($c['nome'])])->values(),
            'nomesCadastro' => \App\Models\Categoria::withoutGlobalScopes()->where('empresa_id', $empresaId)->pluck('nome', 'id'),
        ]);
    }

    public function salvarCategoria(Request $request, ?int $categoria = null)
    {
        $loja = $this->loja();
        $empresaId = (int) $loja->empresa_id;
        $registro = $categoria ? \App\Models\LojaCategoria::where('empresa_id', $empresaId)->findOrFail($categoria) : null;

        $dados = $request->validate([
            'nome' => ['required', 'string', 'min:2', 'max:60'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'tirar_foto' => ['nullable', 'boolean'],
            'categorias_erp' => ['nullable', 'array', 'max:500'],
            'categorias_erp.*' => ['integer'],
            'produtos' => ['nullable', 'array', 'max:3000'],
            'produtos.*' => ['integer'],
        ], [
            'nome.required' => 'Dê um nome à categoria.', 'foto.max' => 'A foto pode ter até 8 MB.',
            'foto.mimes' => 'Envie uma imagem JPG, PNG ou WebP.', 'foto.image' => 'O arquivo não é uma imagem.',
        ]);

        // Só entra o que é da própria empresa: ids de fora são descartados em silêncio.
        $cats = \App\Models\Categoria::withoutGlobalScopes()->where('empresa_id', $empresaId)
            ->whereIn('id', $dados['categorias_erp'] ?? [])->pluck('id')->map(fn ($i) => (int) $i)->values()->all();
        $prods = Produto::withoutGlobalScope(EmpresaScope::class)->where('empresa_id', $empresaId)
            ->whereIn('id', $dados['produtos'] ?? [])->pluck('id')->map(fn ($i) => (int) $i)->values()->all();
        if ($cats === [] && $prods === []) {
            return back()->withInput()->withErrors(['conteudo' => 'Escolha o que vai dentro: pelo menos uma categoria do cadastro ou um produto.']);
        }

        $foto = $registro?->foto;
        if ($request->boolean('tirar_foto')) {
            $foto = null;
        }
        if ($arquivo = $request->file('foto')) {
            $ext = strtolower($arquivo->guessExtension() ?: 'jpg');
            $foto = $arquivo->storeAs('loja-online/' . $empresaId, 'cat-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(20)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext), 'public');
        }

        $campos = ['nome' => trim($dados['nome']), 'foto' => $foto, 'categorias_erp' => $cats, 'produtos' => $prods];
        if ($registro) {
            $registro->update($campos);
        } else {
            $ordem = (int) \App\Models\LojaCategoria::where('empresa_id', $empresaId)->max('ordem') + 1;
            \App\Models\LojaCategoria::create($campos + ['empresa_id' => $empresaId, 'ordem' => $ordem, 'ativa' => true]);
        }

        return redirect()->route('app.loja-online.categorias')->with('success', $registro
            ? 'Categoria "' . $campos['nome'] . '" atualizada. A vitrine já mostra a mudança.'
            : 'Categoria "' . $campos['nome'] . '" criada. A partir de agora a loja mostra só as categorias desta tela.');
    }

    private function categoriaDaLoja(int $id): \App\Models\LojaCategoria
    {
        return \App\Models\LojaCategoria::where('empresa_id', (int) $this->loja()->empresa_id)->findOrFail($id);
    }

    public function alternarCategoria(int $categoria)
    {
        $c = $this->categoriaDaLoja($categoria);
        $c->update(['ativa' => ! $c->ativa]);

        return back()->with('success', $c->ativa ? 'Categoria "' . $c->nome . '" ligada.' : 'Categoria "' . $c->nome . '" desligada: não aparece na loja.');
    }

    /** Sobe ou desce uma posição, renumerando a lista inteira (a ordem nunca fica com buraco nem empate). */
    public function moverCategoria(Request $request, int $categoria)
    {
        $c = $this->categoriaDaLoja($categoria);
        $dados = $request->validate(['direcao' => ['required', 'in:subir,descer']]);
        $ids = \App\Models\LojaCategoria::where('empresa_id', $c->empresa_id)->orderBy('ordem')->orderBy('id')->pluck('id')->all();
        $i = array_search($c->id, $ids, true);
        $j = $dados['direcao'] === 'subir' ? $i - 1 : $i + 1;
        if ($i !== false && isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        }
        foreach ($ids as $pos => $id) {
            \App\Models\LojaCategoria::whereKey($id)->update(['ordem' => $pos + 1]);
        }

        return back();
    }

    public function excluirCategoria(int $categoria)
    {
        $c = $this->categoriaDaLoja($categoria);
        $nome = $c->nome;
        $c->delete();

        return redirect()->route('app.loja-online.categorias')->with('success', 'Categoria "' . $nome . '" excluída. Os produtos continuam na loja.');
    }
}
