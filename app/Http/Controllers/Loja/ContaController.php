<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Mail\AvisoLoja;
use App\Models\LojaConta;
use App\Services\LojaOnline\CarrinhoLoja;
use App\Services\LojaOnline\CatalogoLoja;
use App\Services\LojaOnline\LojaContexto;
use App\Services\LojaOnline\PagamentoLoja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Conta do comprador na loja online (15/11/2026): entrar, criar conta, meus
 * pedidos, meus dados, favoritos e redefinir a senha.
 *
 * Onde cada coisa vive: a conta é só o LOGIN (loja_contas). Os pedidos são os do
 * ERP — aparecem em "meus pedidos" os que foram fechados com a conta logada
 * (pedidos.loja_conta_id). E-mail digitado no fechamento sem login NÃO liga
 * pedido a conta: quem cria a conta depois não enxerga pedido de outra pessoa.
 */
class ContaController extends Controller
{
    public function __construct(private readonly LojaContexto $ctx)
    {
    }

    private function comum(): array
    {
        $catalogo = new CatalogoLoja($this->ctx->loja);

        return ['categoriasMenu' => $catalogo->categorias(), 'carrinhoTotal' => (new CarrinhoLoja($this->ctx->loja, $catalogo))->quantidadeTotal(),
            'temOfertas' => $catalogo->idsEmPromocao() !== []];
    }

    private function ligada(): void
    {
        abort_unless($this->ctx->loja->opcao('conta_cliente'), 404);
    }

    private function entrarNaSessao(LojaConta $conta): void
    {
        session()->regenerate();
        session([LojaConta::chaveSessao($this->ctx->loja) => $conta->id]);
        $conta->forceFill(['ultimo_acesso' => now()])->save();
    }

    public function entrar()
    {
        $this->ligada();
        if (LojaConta::atual($this->ctx->loja)) {
            return redirect($this->ctx->url('conta'));
        }

        return view('loja.entrar', $this->comum() + ['titulo' => 'Entrar — ' . $this->ctx->loja->nome, 'semIndice' => true]);
    }

    public function autenticar(Request $request)
    {
        $this->ligada();
        $loja = $this->ctx->loja;
        $dados = $request->validate(['email' => ['required', 'email', 'max:190'], 'senha' => ['required', 'string', 'max:200']],
            ['email.required' => 'Informe o seu e-mail.', 'senha.required' => 'Informe a sua senha.']);

        $conta = LojaConta::where('empresa_id', $loja->empresa_id)->where('email', mb_strtolower($dados['email']))->first();
        if (! $conta || ! Hash::check($dados['senha'], $conta->senha)) {
            return redirect($this->ctx->url('entrar'))->withInput(['email' => $dados['email']])->with('loja_erro', 'E-mail ou senha não conferem.');
        }
        $this->entrarNaSessao($conta);

        return redirect($this->ctx->url($request->input('volta') === 'finalizar' ? 'finalizar' : 'conta'));
    }

    public function criar(Request $request)
    {
        $this->ligada();
        $loja = $this->ctx->loja;
        $v = validator($request->all(), [
            'nome' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'telefone' => ['nullable', 'string', 'regex:/^\D*(\d\D*){10,13}$/'],
            'senha' => ['required', 'string', 'min:8', 'max:200'],
            'aceite' => ['accepted'],
        ], [
            'nome.required' => 'Diga o seu nome.', 'email.required' => 'Informe o seu e-mail.', 'email.email' => 'Confira o e-mail.',
            'telefone.regex' => 'Informe o telefone com DDD.', 'senha.min' => 'A senha precisa ter pelo menos 8 caracteres.',
            'senha.required' => 'Crie uma senha.', 'aceite.accepted' => 'Para criar a conta é preciso aceitar a política de privacidade.',
        ]);
        if ($v->fails()) {
            return redirect($this->ctx->url('entrar') . '#criar')->withErrors($v, 'criar')->withInput($request->except('senha'));
        }
        $dados = $v->validated();
        $email = mb_strtolower($dados['email']);

        if (LojaConta::where('empresa_id', $loja->empresa_id)->where('email', $email)->exists()) {
            return redirect($this->ctx->url('entrar'))->withInput(['email' => $email])
                ->with('loja_erro', 'Já existe uma conta com este e-mail. Entre com a sua senha ou use "Esqueci a senha".');
        }

        $conta = LojaConta::create([
            'empresa_id' => $loja->empresa_id, 'nome' => $dados['nome'], 'email' => $email,
            'telefone' => preg_replace('/\D/', '', (string) ($dados['telefone'] ?? '')) ?: null,
            'senha' => Hash::make($dados['senha']),
        ]);
        $this->entrarNaSessao($conta);

        return redirect($this->ctx->url($request->input('volta') === 'finalizar' ? 'finalizar' : 'conta'))->with('loja_ok', 'Conta criada. Bem-vindo(a)!');
    }

    public function sair()
    {
        session()->forget(LojaConta::chaveSessao($this->ctx->loja));

        return redirect($this->ctx->url())->with('loja_ok', 'Você saiu da sua conta.');
    }

    public function conta()
    {
        $this->ligada();
        $loja = $this->ctx->loja;
        $conta = LojaConta::atual($loja);
        if (! $conta) {
            return redirect($this->ctx->url('entrar'));
        }

        $pedidos = \App\Models\Pedido::withoutGlobalScopes()->where('empresa_id', $loja->empresa_id)
            ->where('loja_conta_id', $conta->id)->whereNotNull('loja_token')->orderByDesc('id')->limit(50)->get()
            ->map(fn ($p) => ['pedido' => $p, 'situacao' => PagamentoLoja::situacao($p),
                'itens' => (int) DB::table('pedido_itens')->where('pedido_id', $p->id)->whereNull('deleted_at')->sum('quantidade')]);

        return view('loja.conta', $this->comum() + [
            'conta' => $conta, 'pedidos' => $pedidos,
            'cliente' => $conta->cliente_id ? DB::table('clientes')->where('empresa_id', $loja->empresa_id)->where('id', $conta->cliente_id)
                ->first(['logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep']) : null,
            'titulo' => 'Minha conta — ' . $loja->nome, 'semIndice' => true,
        ]);
    }

    public function atualizar(Request $request)
    {
        $conta = LojaConta::atual($this->ctx->loja);
        if (! $conta) {
            return redirect($this->ctx->url('entrar'));
        }
        $v = validator($request->all(), [
            'nome' => ['required', 'string', 'min:3', 'max:120'],
            'telefone' => ['nullable', 'string', 'regex:/^\D*(\d\D*){10,13}$/'],
            'senha_atual' => ['nullable', 'string', 'max:200'],
            'senha_nova' => ['nullable', 'string', 'min:8', 'max:200'],
        ], ['senha_nova.min' => 'A senha nova precisa ter pelo menos 8 caracteres.', 'telefone.regex' => 'Informe o telefone com DDD.']);
        if ($v->fails()) {
            return redirect($this->ctx->url('conta') . '#dados')->withErrors($v);
        }
        $dados = $v->validated();
        $novo = ['nome' => $dados['nome'], 'telefone' => preg_replace('/\D/', '', (string) ($dados['telefone'] ?? '')) ?: null];
        if (filled($dados['senha_nova'] ?? null)) {
            if (! Hash::check((string) ($dados['senha_atual'] ?? ''), $conta->senha)) {
                return redirect($this->ctx->url('conta') . '#dados')->with('loja_erro', 'A senha atual não confere.');
            }
            $novo['senha'] = Hash::make($dados['senha_nova']);
        }
        $conta->forceFill($novo)->save();

        return redirect($this->ctx->url('conta') . '#dados')->with('loja_ok', 'Dados atualizados.');
    }

    /** Pede o link de redefinição. A resposta é a MESMA exista a conta ou não. */
    public function esqueci(Request $request)
    {
        $this->ligada();
        $loja = $this->ctx->loja;
        $email = mb_strtolower(trim((string) $request->input('email')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $conta = LojaConta::where('empresa_id', $loja->empresa_id)->where('email', $email)->first();
            if ($conta) {
                $token = Str::lower(Str::random(48));
                $conta->forceFill(['redefinir_token' => hash('sha256', $token), 'redefinir_expira' => now()->addHours(2)])->save();
                try {
                    Mail::to($conta->email)->queue(new AvisoLoja($loja->id, 'Redefinir a senha — ' . $loja->nome, 'Criar uma senha nova', [
                        'Recebemos um pedido para trocar a senha da sua conta em ' . $loja->nome . '.',
                        'O botão abaixo vale por 2 horas. Se não foi você, é só ignorar este e-mail: a senha continua a mesma.',
                    ], 'Criar senha nova', rtrim($loja->enderecoPublico(), '/') . '/redefinir/' . $token));
                } catch (\Throwable $e) {
                    Log::channel('integracao')->warning('Loja online: e-mail de redefinir senha não saiu', ['erro' => $e->getMessage()]);
                }
            }
        }

        return redirect($this->ctx->url('entrar'))->with('loja_ok', 'Se existir uma conta com este e-mail, enviamos o link para criar uma senha nova.');
    }

    private function contaDoToken(string $token): ?LojaConta
    {
        if (preg_match('/^[a-z0-9]{48}$/', $token) !== 1) {
            return null;
        }

        return LojaConta::where('empresa_id', $this->ctx->loja->empresa_id)->where('redefinir_token', hash('sha256', $token))
            ->where('redefinir_expira', '>', now())->first();
    }

    public function redefinir(string $token)
    {
        if (! $this->contaDoToken($token)) {
            return redirect($this->ctx->url('entrar'))->with('loja_erro', 'Este link venceu ou já foi usado. Peça um novo em "Esqueci a senha".');
        }

        return view('loja.redefinir', $this->comum() + ['token' => $token, 'titulo' => 'Senha nova — ' . $this->ctx->loja->nome, 'semIndice' => true]);
    }

    public function redefinirSalvar(Request $request, string $token)
    {
        $conta = $this->contaDoToken($token);
        if (! $conta) {
            return redirect($this->ctx->url('entrar'))->with('loja_erro', 'Este link venceu ou já foi usado. Peça um novo em "Esqueci a senha".');
        }
        $v = validator($request->all(), ['senha' => ['required', 'string', 'min:8', 'max:200']], ['senha.min' => 'A senha precisa ter pelo menos 8 caracteres.', 'senha.required' => 'Crie uma senha.']);
        if ($v->fails()) {
            return redirect($this->ctx->url('redefinir/' . $token))->withErrors($v);
        }
        $conta->forceFill(['senha' => Hash::make($request->input('senha')), 'redefinir_token' => null, 'redefinir_expira' => null])->save();
        $this->entrarNaSessao($conta);

        return redirect($this->ctx->url('conta'))->with('loja_ok', 'Senha nova gravada.');
    }

    /* ----- favoritos ----- */

    public function favoritos()
    {
        return view('loja.favoritos', $this->comum() + ['titulo' => 'Favoritos — ' . $this->ctx->loja->nome, 'semIndice' => true]);
    }

    /**
     * Junta os favoritos do navegador com os da conta (quando logado) e devolve
     * a lista final. Sem conta, devolve o que veio — eles ficam só no navegador.
     */
    public function favoritosSincronizar(Request $request)
    {
        $ids = collect((array) $request->input('ids'))->map(fn ($i) => (int) $i)->filter()->unique()->take(100)->values();
        $conta = LojaConta::atual($this->ctx->loja);
        if ($conta) {
            if ($request->boolean('substituir')) {
                $final = $ids;
            } else {
                $final = collect((array) $conta->favoritos)->map(fn ($i) => (int) $i)->merge($ids)->unique()->take(100)->values();
            }
            $conta->forceFill(['favoritos' => $final->all()])->save();
            $ids = $final;
        }

        return response()->json(['ids' => $ids->all(), 'conta' => (bool) $conta]);
    }
}
