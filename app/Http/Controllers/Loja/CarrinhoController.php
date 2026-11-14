<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Scopes\EmpresaScope;
use App\Scopes\UnidadeScope;
use App\Services\LojaOnline\CarrinhoLoja;
use App\Services\LojaOnline\CatalogoLoja;
use App\Services\LojaOnline\AvisosLoja;
use App\Services\LojaOnline\CheckoutLoja;
use App\Services\LojaOnline\CupomLoja;
use App\Services\LojaOnline\LojaContexto;
use App\Services\LojaOnline\PagamentoLoja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Carrinho, frete, fechamento e acompanhamento do pedido da loja online
 * (14/11/2026). Tudo o que GRAVA passa pelo CheckoutLoja (pedido do ERP) e pelo
 * PagamentoLoja (gateways que a empresa já tem ligados).
 */
class CarrinhoController extends Controller
{
    public function __construct(private readonly LojaContexto $ctx)
    {
    }

    private function partes(): array
    {
        $catalogo = new CatalogoLoja($this->ctx->loja);

        return [$catalogo, new CarrinhoLoja($this->ctx->loja, $catalogo)];
    }

    private function comum(CatalogoLoja $catalogo, CarrinhoLoja $carrinho): array
    {
        return ['categoriasMenu' => $catalogo->categorias(), 'carrinhoTotal' => $carrinho->quantidadeTotal(), 'temOfertas' => $catalogo->idsEmPromocao() !== []];
    }

    /**
     * Conta do carrinho com as promoções da loja: cupom da sessão (reconferido
     * agora) e frete grátis acima de um valor.
     *
     * @return array{cupom: ?string, cupomOk: bool, cupomMensagem: ?string, desconto: float, freteGratis: bool, freteGratisAcima: float, faltaFreteGratis: float, cupomDeFrete: bool}
     */
    private function promocoes(float $subtotal): array
    {
        $loja = $this->ctx->loja;
        $codigo = CupomLoja::naSessao($loja);
        $r = $codigo ? CupomLoja::avaliar($loja, $codigo, $subtotal) : null;
        $desconto = $r && $r['ok'] ? $r['desconto'] : 0.0;
        $acima = (float) $loja->opcao('frete_gratis_acima');

        return [
            'cupom' => $codigo,
            'cupomOk' => (bool) ($r['ok'] ?? false),
            'cupomMensagem' => $r['mensagem'] ?? null,
            'desconto' => $desconto,
            'cupomDeFrete' => (bool) ($r && $r['ok'] && $r['frete_gratis']),
            'freteGratis' => CupomLoja::freteGratis($loja, $subtotal - $desconto, (bool) ($r && $r['ok'] && $r['frete_gratis'])),
            'freteGratisAcima' => $acima,
            'faltaFreteGratis' => $acima > 0 ? max(0, round($acima - ($subtotal - $desconto), 2)) : 0.0,
        ];
    }

    public function cupom(Request $request)
    {
        $loja = $this->ctx->loja;
        [, $carrinho] = $this->partes();
        $volta = $request->input('volta') === 'finalizar' ? 'finalizar' : 'carrinho';

        if ($request->boolean('remover')) {
            CupomLoja::guardar($loja, null);

            return redirect($this->ctx->url($volta))->with('loja_ok', 'Cupom retirado.');
        }

        $r = CupomLoja::avaliar($loja, (string) $request->input('codigo'), CarrinhoLoja::subtotal($carrinho->itens()));
        if (! $r['ok']) {
            return redirect($this->ctx->url($volta))->with('loja_erro', $r['mensagem'])->withInput();
        }
        CupomLoja::guardar($loja, $r['cupom']->codigo);

        return redirect($this->ctx->url($volta))->with('loja_ok', $r['mensagem']);
    }

    public function ver()
    {
        [$catalogo, $carrinho] = $this->partes();
        $itens = $carrinho->itens();

        return view('loja.carrinho', $this->comum($catalogo, $carrinho) + $this->promocoes(CarrinhoLoja::subtotal($itens)) + [
            'itens' => $itens,
            'subtotal' => CarrinhoLoja::subtotal($itens),
            'avisos' => $carrinho->avisos,
            'pedidoMinimo' => (float) $this->ctx->loja->opcao('pedido_minimo'),
            'titulo' => 'Carrinho — ' . $this->ctx->loja->nome,
        ]);
    }

    public function adicionar(Request $request)
    {
        $dados = $request->validate([
            'produto_id' => ['required', 'integer'],
            'quantidade' => ['nullable', 'integer', 'min:1', 'max:' . CarrinhoLoja::MAXIMO_POR_ITEM],
        ]);
        [$catalogo, $carrinho] = $this->partes();

        $produto = $catalogo->produto((int) $dados['produto_id']);
        $erro = null;
        if (! $produto) {
            $erro = 'Este produto não está mais disponível.';
        } elseif (! $this->ctx->loja->opcao('vender_sem_estoque') && $catalogo->saldo($produto->id) < 1) {
            $erro = 'Este produto esgotou.';
        } elseif ($carrinho->adicionar($produto->id, (int) ($dados['quantidade'] ?? 1)) === 0) {
            $erro = 'Seu carrinho já está no limite de itens diferentes.';
        }

        // Confere contra o saldo (pode ter reduzido a quantidade)
        $itens = $carrinho->itens();
        $total = $carrinho->quantidadeTotal();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => $erro === null,
                'erro' => $erro,
                'aviso' => $carrinho->avisos[0] ?? null,
                'total' => $total,
                'subtotal' => CarrinhoLoja::subtotal($itens),
                // para o evento "adicionou ao carrinho" do rastreamento
                'item' => $produto && ! $erro ? ['id' => $produto->id, 'nome' => $produto->descricao,
                    'preco' => (float) ($itens->firstWhere('id', $produto->id)['preco'] ?? $produto->preco_venda), 'quantidade' => (int) ($dados['quantidade'] ?? 1)] : null,
            ], $erro ? 422 : 200);
        }

        return $erro
            ? redirect($this->ctx->url('carrinho'))->with('loja_erro', $erro)
            : redirect($this->ctx->url('carrinho'))->with('loja_ok', 'Produto adicionado ao carrinho.');
    }

    /** Resumo do carrinho para a sacola lateral (abre ao adicionar um produto). */
    public function resumo()
    {
        [, $carrinho] = $this->partes();
        $itens = $carrinho->itens();
        $subtotal = CarrinhoLoja::subtotal($itens);
        $promo = $this->promocoes($subtotal);

        return response()->json([
            'itens' => $itens->map(fn ($i) => ['id' => $i['id'], 'nome' => $i['nome'], 'foto' => $i['foto'], 'url' => $this->ctx->url($i['caminho']),
                'quantidade' => $i['quantidade'], 'preco' => $i['preco'], 'total' => $i['total']])->values(),
            'quantidade' => $carrinho->quantidadeTotal(),
            'subtotal' => $subtotal - $promo['desconto'],
            'frete_gratis_acima' => $promo['freteGratisAcima'],
            'falta_frete_gratis' => $promo['faltaFreteGratis'],
            'entrega' => (bool) $this->ctx->loja->opcao('entrega'),
        ]);
    }

    public function atualizar(Request $request)
    {
        $dados = $request->validate([
            'produto_id' => ['required', 'integer'],
            'quantidade' => ['required', 'integer', 'min:0', 'max:' . CarrinhoLoja::MAXIMO_POR_ITEM],
        ]);
        [, $carrinho] = $this->partes();
        $carrinho->definir((int) $dados['produto_id'], (int) $dados['quantidade']);

        return redirect($this->ctx->url('carrinho'));
    }

    /** Cota o frete do carrinho (ou de UM produto, na página dele) para um CEP. */
    public function frete(Request $request, CheckoutLoja $checkout)
    {
        $dados = $request->validate([
            'cep' => ['required', 'string', 'max:9'],
            'produto_id' => ['nullable', 'integer'],
        ]);
        [$catalogo, $carrinho] = $this->partes();

        if (! empty($dados['produto_id'])) {
            $produto = $catalogo->produto((int) $dados['produto_id']);
            abort_unless($produto, 404);
            $v = $catalogo->paraVitrine(collect([$produto]))->first();
            $itens = collect([$v + ['quantidade' => 1, 'total' => $v['preco'], 'produto' => $produto]]);
        } else {
            $itens = $carrinho->itens();
        }

        if ($itens->isEmpty()) {
            return response()->json(['disponivel' => false, 'opcoes' => [], 'mensagem' => 'Seu carrinho está vazio.']);
        }

        if (! $this->ctx->loja->opcao('entrega')) {
            return response()->json(['disponivel' => false, 'opcoes' => [], 'mensagem' => 'Esta loja trabalha só com retirada.']);
        }

        // Na página do produto a conta é de UM item: o frete grátis só é prometido pelo carrinho de verdade.
        $gratis = empty($dados['produto_id']) && $this->promocoes(CarrinhoLoja::subtotal($itens))['freteGratis'];
        $r = $checkout->cotar($this->ctx->loja, $itens, ['cep' => $dados['cep']], null, $gratis);
        unset($r['bruto']);

        return response()->json($r);
    }

    public function finalizar()
    {
        [$catalogo, $carrinho] = $this->partes();
        $itens = $carrinho->itens();
        if ($itens->isEmpty()) {
            return redirect($this->ctx->url('carrinho'));
        }
        $loja = $this->ctx->loja;
        $subtotal = CarrinhoLoja::subtotal($itens);
        if ($subtotal < (float) $loja->opcao('pedido_minimo')) {
            return redirect($this->ctx->url('carrinho'));
        }

        return view('loja.finalizar', $this->comum($catalogo, $carrinho) + $this->promocoes($subtotal) + [
            'itens' => $itens,
            'subtotal' => $subtotal,
            'avisos' => $carrinho->avisos,
            'meios' => PagamentoLoja::meios($loja),
            'unidade' => $loja->unidade,
            'exigeDocumento' => (bool) $loja->empresa?->exigeDocumentoCadastro(),
            'titulo' => 'Finalizar compra — ' . $loja->nome,
        ]);
    }

    public function fechar(Request $request, CheckoutLoja $checkout, PagamentoLoja $pagamento)
    {
        $loja = $this->ctx->loja;
        [, $carrinho] = $this->partes();
        $meios = PagamentoLoja::meios($loja);

        $entregas = array_keys(array_filter(['retirada' => $loja->opcao('retirada'), 'entrega' => $loja->opcao('entrega')])) ?: ['retirada'];
        $pagamentos = array_keys(array_filter(['pix' => $meios['pix'], 'cartao' => $meios['cartao'], 'combinar' => $meios['combinar']]));
        $exigeDoc = (bool) $loja->empresa?->exigeDocumentoCadastro();

        $validador = Validator::make($request->all(), [
            'nome' => ['required', 'string', 'min:3', 'max:255'],
            'telefone' => ['required', 'string', 'regex:/^\D*(\d\D*){10,13}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'cpf_cnpj' => [$exigeDoc ? 'required' : 'nullable', 'string', 'regex:/^\D*(\d\D*){11}$|^[0-9A-Za-z.\/\-]{14,18}$/'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
            'entrega' => ['required', Rule::in($entregas)],
            'cep' => ['required_if:entrega,entrega', 'nullable', 'string', 'regex:/^\d{5}-?\d{3}$/'],
            'logradouro' => ['required_if:entrega,entrega', 'nullable', 'string', 'max:255'],
            'numero' => ['required_if:entrega,entrega', 'nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:100'],
            'bairro' => ['required_if:entrega,entrega', 'nullable', 'string', 'max:100'],
            'cidade' => ['nullable', 'string', 'max:100'],
            'uf' => ['nullable', 'string', 'size:2'],
            'servico_id' => ['nullable', 'string', 'max:20'],
            'pagamento' => ['required', Rule::in($pagamentos)],
            'novidades' => ['nullable', 'boolean'],
        ], [
            'nome.required' => 'Diga seu nome completo.',
            'telefone.required' => 'Informe um telefone com DDD.',
            'telefone.regex' => 'Informe o telefone com DDD (ex.: 86 99999-0000).',
            'cpf_cnpj.required' => 'Esta loja pede o CPF ou CNPJ para emitir a nota.',
            'cpf_cnpj.regex' => 'Confira o CPF ou CNPJ.',
            'cep.required_if' => 'Informe o CEP da entrega.',
            'cep.regex' => 'O CEP tem 8 números.',
            'logradouro.required_if' => 'Informe a rua da entrega.',
            'numero.required_if' => 'Informe o número (ou "s/n").',
            'bairro.required_if' => 'Informe o bairro.',
            'entrega.required' => 'Escolha entre retirar ou receber.',
            'pagamento.required' => 'Escolha como quer pagar.',
        ]);

        // Volta SEMPRE pelo endereço da loja (em domínio próprio o back() do
        // Laravel poderia devolver o caminho interno /loja/{slug}/...).
        if ($validador->fails()) {
            return redirect($this->ctx->url('finalizar'))->withErrors($validador)->withInput();
        }
        $dados = $validador->validated();

        $itens = $carrinho->itens();
        if ($itens->isEmpty()) {
            return redirect($this->ctx->url('carrinho'))->with('loja_erro', 'Seu carrinho ficou vazio.');
        }
        if ($carrinho->avisos !== []) {
            // O carrinho mudou desde a tela (esgotou / reduziu): o comprador confere antes de fechar.
            return redirect($this->ctx->url('carrinho'));
        }
        if (CarrinhoLoja::subtotal($itens) < (float) $loja->opcao('pedido_minimo')) {
            return redirect($this->ctx->url('carrinho'));
        }

        $r = $checkout->criarPedido($loja, $itens, $dados + ['cupom' => CupomLoja::naSessao($loja)]);
        $pedido = $r['pedido'];

        if ($r['frete_combinar'] && ! $meios['combinar'] && $dados['entrega'] === 'entrega') {
            // Sem frete calculado e a loja não combina: o pedido não pode ficar cobrando sem frete.
            DB::transaction(function () use ($pedido) {
                $pedido->itens()->forceDelete();
                $pedido->forceDelete();
            });

            return redirect($this->ctx->url('finalizar'))->withInput()->with('loja_erro', 'Não conseguimos calcular o frete para este endereço. Confira o CEP ou escolha retirar na loja.');
        }

        $carrinho->limpar();
        CupomLoja::guardar($loja, null);

        // Aceitou receber novidades: entra nos contatos da loja, com a data do aceite.
        if (! empty($dados['novidades']) && (filled($dados['email'] ?? null) || filled($dados['telefone']))) {
            $fone = preg_replace('/\D/', '', $dados['telefone']);
            $chave = filled($dados['email'] ?? null) ? ['email' => mb_strtolower($dados['email'])] : ['email' => null, 'whatsapp' => $fone];
            \App\Models\LojaContato::updateOrCreate(['empresa_id' => $loja->empresa_id] + $chave,
                ['nome' => $dados['nome'], 'whatsapp' => $fone, 'origem' => 'checkout', 'aceitou_em' => now()]);
        }

        AvisosLoja::pedidoFechado($loja, $pedido);
        session()->flash('loja_compra', $pedido->id); // a página do pedido dispara o evento de compra UMA vez

        $destino = $this->ctx->url('pedido/' . $pedido->loja_token);

        // Frete a combinar: não cobra agora (o total ainda vai mudar).
        if ($r['frete_combinar']) {
            return redirect($destino)->with('loja_ok', 'Pedido recebido! A loja vai falar com você para combinar a entrega.');
        }

        if ($dados['pagamento'] === 'cartao') {
            $link = $pagamento->cartao($pedido);
            if ($link['ok']) {
                session(['loja_pagar_' . $pedido->id => 'cartao']);
            }
        } elseif ($dados['pagamento'] === 'pix') {
            session(['loja_pagar_' . $pedido->id => 'pix']);
        }

        return redirect($destino);
    }

    private function pedido(string $token): Pedido
    {
        abort_unless(preg_match('/^[a-z0-9]{32}$/', $token) === 1, 404);

        $pedido = Pedido::withoutGlobalScope(EmpresaScope::class)->withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', $this->ctx->loja->empresa_id)
            ->where('loja_token', $token)
            ->first();
        abort_unless($pedido, 404);

        return $pedido;
    }

    public function pedidoVer(string $token, PagamentoLoja $pagamento)
    {
        $pedido = $this->pedido($token);
        $loja = $this->ctx->loja;
        [$catalogo, $carrinho] = $this->partes();

        $situacao = PagamentoLoja::situacao($pedido);
        $meios = PagamentoLoja::meios($loja);
        $freteCombinar = $pedido->metodo_entrega === 'entrega' && $pedido->frete_valor === null;
        $podePagar = $situacao['situacao'] === 'aguardando' && ! $freteCombinar;

        // PIX escolhido no fechamento: já chega com o QR na tela.
        $pix = null;
        $escolha = session('loja_pagar_' . $pedido->id);
        if ($podePagar && $meios['pix'] && $escolha === 'pix') {
            $pix = $pagamento->pix($loja, $pedido);
        }
        $linkCartao = null;
        if ($podePagar && $meios['cartao'] && $escolha === 'cartao') {
            $r = $pagamento->cartao($pedido);
            $linkCartao = $r['ok'] ? $r['link'] : null;
        }

        $itens = DB::table('pedido_itens')->where('pedido_id', $pedido->id)->whereNull('deleted_at')->orderBy('id')
            ->get(['produto_id', 'descricao', 'quantidade', 'preco_unitario', 'total']);
        $fotos = DB::table('produtos')->whereIn('id', $itens->pluck('produto_id')->filter())->pluck('foto', 'id');

        return view('loja.pedido', $this->comum($catalogo, $carrinho) + [
            'pedido' => $pedido,
            'itens' => $itens,
            'fotos' => $fotos,
            'situacao' => $situacao,
            'meios' => $meios,
            'podePagar' => $podePagar,
            'freteCombinar' => $freteCombinar,
            'pix' => $pix,
            'linkCartao' => $linkCartao,
            'escolha' => $escolha,
            'unidade' => $loja->unidade,
            'compraAgora' => (int) session('loja_compra') === (int) $pedido->id,
            'cliente' => DB::table('clientes')->where('id', $pedido->cliente_id)->first(['nome_razao_social', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep']),
            'titulo' => 'Pedido #' . $pedido->numero . ' — ' . $loja->nome,
        ]);
    }

    /** Gera o PIX (QR na tela) ou o link de cartão sob demanda. */
    public function pagar(Request $request, string $token, PagamentoLoja $pagamento)
    {
        $pedido = $this->pedido($token);
        $dados = $request->validate(['meio' => ['required', Rule::in(['pix', 'cartao'])]]);
        $meios = PagamentoLoja::meios($this->ctx->loja);

        $situacao = PagamentoLoja::situacao($pedido);
        if ($situacao['situacao'] !== 'aguardando' || ($pedido->metodo_entrega === 'entrega' && $pedido->frete_valor === null)) {
            return response()->json(['ok' => false, 'erro' => 'Este pedido não está aguardando pagamento.'], 422);
        }
        if (! $meios[$dados['meio']]) {
            return response()->json(['ok' => false, 'erro' => 'Esta forma de pagamento não está disponível.'], 422);
        }

        $r = $dados['meio'] === 'pix' ? $pagamento->pix($this->ctx->loja, $pedido) : $pagamento->cartao($pedido);
        if ($r['ok']) {
            session(['loja_pagar_' . $pedido->id => $dados['meio']]);
        }

        return response()->json($r, $r['ok'] ? 200 : 422);
    }

    /** A tela do pedido pergunta de tempos em tempos se o pagamento caiu. */
    public function situacao(string $token, PagamentoLoja $pagamento)
    {
        $pedido = $this->pedido($token);
        if (PagamentoLoja::situacao($pedido)['situacao'] === 'aguardando') {
            $pagamento->conferir($pedido);
            $pedido->refresh();
        }

        return response()->json(PagamentoLoja::situacao($pedido));
    }
}
