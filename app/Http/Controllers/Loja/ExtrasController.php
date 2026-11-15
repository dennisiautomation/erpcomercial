<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Services\LojaOnline\CarrinhoLoja;
use App\Services\LojaOnline\CarrinhoSalvo;
use App\Services\LojaOnline\CatalogoLoja;
use App\Services\LojaOnline\LojaContexto;
use App\Services\LojaOnline\PagamentoLoja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Avise-me, avaliação de produto e carrinho salvo da loja online (15/11/2026).
 */
class ExtrasController extends Controller
{
    public function __construct(private readonly LojaContexto $ctx)
    {
    }

    /** "Avise-me quando chegar": guarda o e-mail para o produto esgotado. */
    public function aviseMe(Request $request)
    {
        $loja = $this->ctx->loja;
        abort_unless($loja->opcao('avise_me'), 404);
        $v = validator($request->all(), ['produto_id' => ['required', 'integer'], 'email' => ['required', 'email', 'max:190'], 'aceite' => ['accepted']]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'erro' => $v->errors()->has('aceite') ? 'Marque que aceita receber o aviso.' : 'Confira o e-mail.'], 422);
        }
        $produto = (new CatalogoLoja($loja))->produto((int) $request->input('produto_id'));
        abort_unless($produto, 404);

        DB::table('loja_avise_me')->updateOrInsert(
            ['empresa_id' => $loja->empresa_id, 'produto_id' => $produto->id, 'email' => mb_strtolower($request->input('email'))],
            ['avisado_em' => null, 'updated_at' => now(), 'created_at' => now()]
        );

        return response()->json(['ok' => true, 'mensagem' => 'Combinado! Avisamos por e-mail assim que chegar.']);
    }

    /** Avaliação de um produto do pedido — só de pedido PAGO (compra verificada). */
    public function avaliar(Request $request, string $token)
    {
        $loja = $this->ctx->loja;
        abort_unless($loja->opcao('avaliacoes') && preg_match('/^[a-z0-9]{32}$/', $token) === 1, 404);
        $pedido = Pedido::withoutGlobalScopes()->where('empresa_id', $loja->empresa_id)->where('loja_token', $token)->first();
        abort_unless($pedido, 404);
        $volta = $this->ctx->url('pedido/' . $token) . '#avaliar';

        $sit = PagamentoLoja::situacao($pedido);
        if (! $sit['pago'] && ! in_array($sit['situacao'], ['faturado', 'enviado', 'entregue'], true)) {
            return redirect($volta)->with('loja_erro', 'A avaliação abre depois que o pagamento é confirmado.');
        }
        $v = validator($request->all(), [
            'produto_id' => ['required', 'integer'], 'nota' => ['required', 'integer', 'min:1', 'max:5'],
            'texto' => ['nullable', 'string', 'max:1000'], 'nome' => ['nullable', 'string', 'max:80'],
        ], ['nota.required' => 'Escolha de 1 a 5 estrelas.']);
        if ($v->fails()) {
            return redirect($volta)->with('loja_erro', $v->errors()->first());
        }
        $dados = $v->validated();
        $item = DB::table('pedido_itens')->where('pedido_id', $pedido->id)->where('produto_id', $dados['produto_id'])->whereNull('deleted_at')->first();
        abort_unless($item, 404);

        // A grade inteira divide as avaliações: grava no produto principal.
        $alvo = (int) (DB::table('produtos')->where('id', $item->produto_id)->value('grade_id') ?: $item->produto_id);
        $nome = trim((string) ($dados['nome'] ?? '')) ?: (string) str((string) DB::table('clientes')->where('id', $pedido->cliente_id)->value('nome_razao_social'))->before(' ');

        DB::table('loja_avaliacoes')->updateOrInsert(
            ['pedido_id' => $pedido->id, 'produto_id' => $alvo],
            ['empresa_id' => $loja->empresa_id, 'nome' => mb_substr($nome ?: 'Cliente', 0, 80), 'nota' => (int) $dados['nota'],
                'texto' => filled($dados['texto'] ?? null) ? trim($dados['texto']) : null,
                'status' => $loja->opcao('avaliacoes_moderar') ? 'pendente' : 'aprovada', 'updated_at' => now(), 'created_at' => now()]
        );

        return redirect($volta)->with('loja_ok', $loja->opcao('avaliacoes_moderar') ? 'Obrigado! Sua avaliação aparece na loja assim que for conferida.' : 'Obrigado pela avaliação!');
    }

    /** O fechamento manda o contato assim que o comprador digita: é o que permite recuperar o carrinho. */
    public function lembrar(Request $request)
    {
        $loja = $this->ctx->loja;
        $email = mb_strtolower(trim((string) $request->input('email')));
        $fone = preg_replace('/\D/', '', (string) $request->input('telefone'));
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        $fone = strlen($fone) >= 10 && strlen($fone) <= 13 ? $fone : null;
        if (! $email && ! $fone) {
            return response()->json(['ok' => false]);
        }
        $catalogo = new CatalogoLoja($loja);
        $itens = (new CarrinhoLoja($loja, $catalogo))->itens();
        if ($itens->isEmpty()) {
            return response()->json(['ok' => false]);
        }
        CarrinhoSalvo::guardar($loja, $itens, mb_substr(trim((string) $request->input('nome')), 0, 120) ?: null, $email, $fone);

        return response()->json(['ok' => true]);
    }

    /** Link de recuperação: devolve o carrinho salvo para a sessão de quem abriu. */
    public function recuperar(string $token)
    {
        $loja = $this->ctx->loja;
        abort_unless(preg_match('/^[a-z0-9]{32}$/', $token) === 1, 404);
        $linha = DB::table('loja_carrinhos')->where('empresa_id', $loja->empresa_id)->where('token', $token)->first();
        if (! $linha || $linha->pedido_id) {
            return redirect($this->ctx->url('carrinho'))->with('loja_erro', 'Este carrinho já foi fechado ou não existe mais.');
        }
        $carrinho = new CarrinhoLoja($loja);
        $carrinho->limpar();
        foreach ((array) json_decode($linha->itens, true) as $i) {
            $carrinho->adicionar((int) ($i['id'] ?? 0), (int) ($i['quantidade'] ?? 1));
        }
        session([CarrinhoSalvo::chave($loja) => $token]);
        DB::table('loja_carrinhos')->where('id', $linha->id)->update(['recuperado_em' => now()]);

        return redirect($this->ctx->url('carrinho'))->with('loja_ok', 'Seu carrinho está de volta.');
    }
}
