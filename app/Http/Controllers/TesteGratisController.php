<?php

namespace App\Http\Controllers;

use App\Enums\RegimeTributario;
use App\Mail\ConfirmarCadastroSite;
use App\Mail\NovoCadastroSite;
use App\Models\Empresa;
use App\Models\Plano;
use App\Models\User;
use App\Rules\CnpjValido;
use App\Services\Plataforma\ContratacaoSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Teste grátis pelo site (autocontratação) — 20/11/2026.
 * Tudo aqui só responde com a chave "Contratação pelo site" LIGADA (Admin → Planos);
 * desligada, as rotas devolvem a pessoa para a página inicial. Regras em ContratacaoSite.
 */
class TesteGratisController extends Controller
{
    public function __construct(private ContratacaoSite $contratacao) {}

    private function fechado(): ?RedirectResponse
    {
        return ContratacaoSite::ativa() ? null : redirect()->to(route('site.home') . '#demo');
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($r = $this->fechado()) {
            return $r;
        }

        $planos = Plano::ativo()->orderBy('ordem')->orderBy('preco_mensal')->get();
        if ($planos->isEmpty()) {
            return redirect()->to(route('site.home') . '#demo');
        }

        $escolhido = $planos->firstWhere('slug', $request->query('plano')) ?? $planos->first();

        return view('site.teste-gratis', [
            'planos'    => $planos,
            'escolhido' => $escolhido,
            'regimes'   => RegimeTributario::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($r = $this->fechado()) {
            return $r;
        }

        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'uf'    => mb_strtoupper(trim((string) $request->input('uf'))),
        ]);

        $data = $request->validate([
            'nome'              => ['required', 'string', 'min:3', 'max:120'],
            'email'             => ['required', 'email', 'max:160'],
            'whatsapp'          => ['required', 'string', 'min:10', 'max:20'],
            'senha'             => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
            'cnpj'              => ['required', 'string', 'size:18', new CnpjValido],
            'razao_social'      => ['required', 'string', 'max:255'],
            'nome_fantasia'     => ['nullable', 'string', 'max:255'],
            'regime_tributario' => ['required', Rule::enum(RegimeTributario::class)],
            'cep'               => ['required', 'string', 'max:10'],
            'logradouro'        => ['required', 'string', 'max:255'],
            'numero'            => ['required', 'string', 'max:20'],
            'complemento'       => ['nullable', 'string', 'max:100'],
            'bairro'            => ['required', 'string', 'max:100'],
            'cidade'            => ['required', 'string', 'max:100'],
            'uf'                => ['required', 'string', 'size:2'],
            'plano'             => ['required', Rule::exists('planos', 'slug')->where('ativo', true)],
            'aceite'            => ['accepted'],
            'site'              => ['nullable', 'size:0'], // isca contra robô: tem de vir vazio
        ], [
            'required'         => 'Preencha :attribute.',
            'email.email'      => 'Digite um e-mail válido.',
            'max'              => 'O campo :attribute ficou longo demais.',
            'nome.min'         => 'Digite o seu nome completo.',
            'whatsapp.min'     => 'Digite o WhatsApp com o DDD.',
            'uf.size'          => 'Use a sigla do estado, com 2 letras.',
            'regime_tributario.enum' => 'Escolha um regime tributário da lista.',
            'plano.required'   => 'Escolha um plano.',
            'senha.min'        => 'A senha precisa ter pelo menos 8 caracteres.',
            'senha.confirmed'  => 'As duas senhas não são iguais.',
            'cnpj.size'        => 'Digite o CNPJ completo, com 14 números.',
            'plano.exists'     => 'Escolha um dos planos da lista.',
            'aceite.accepted'  => 'Marque que você concorda para continuar.',
        ], [
            'nome' => 'o seu nome', 'email' => 'o e-mail', 'whatsapp' => 'o WhatsApp', 'senha' => 'a senha',
            'cnpj' => 'o CNPJ', 'razao_social' => 'a razão social', 'nome_fantasia' => 'o nome da loja',
            'regime_tributario' => 'o regime tributário', 'cep' => 'o CEP', 'logradouro' => 'o endereço',
            'numero' => 'o número', 'bairro' => 'o bairro', 'cidade' => 'a cidade', 'uf' => 'o estado',
        ]);

        // E-mail ou CNPJ que já é cliente: mesma resposta para os dois, dizendo o que fazer.
        $jaExiste = User::withoutGlobalScopes()->where('email', $data['email'])->exists()
            || Empresa::withoutGlobalScopes()->where('cnpj', $data['cnpj'])->exists();
        if ($jaExiste) {
            return back()->withInput($request->except('senha', 'senha_confirmation'))
                ->withErrors(['email' => 'Este e-mail ou este CNPJ já tem cadastro no sistema. Entre pela tela de login ou chame a nossa equipe no WhatsApp.']);
        }

        $plano = Plano::ativo()->where('slug', $data['plano'])->firstOrFail();

        $dados = collect($data)->except(['senha', 'plano', 'aceite', 'site'])->all();
        $dados['senha_hash'] = Hash::make($data['senha']);

        [$cadastro, $token] = $this->contratacao->registrarPedido($dados, $plano, $request->ip());

        $link = route('site.teste.confirmar', ['token' => $token]);

        try {
            Mail::to($cadastro->email)->send(new ConfirmarCadastroSite($dados['nome'], $plano, $link));
        } catch (\Throwable $e) {
            Log::error('Teste grátis: e-mail de confirmação não saiu', ['cadastro' => $cadastro->id, 'erro' => $e->getMessage()]);
            $cadastro->delete();

            return back()->withInput($request->except('senha', 'senha_confirmation'))
                ->withErrors(['email' => 'Não conseguimos enviar o e-mail de confirmação agora. Tente de novo em alguns minutos ou chame a nossa equipe no WhatsApp.']);
        }

        return redirect()->route('site.teste.enviado')->with('teste_email', $cadastro->email);
    }

    public function enviado(Request $request): View|RedirectResponse
    {
        if (! session('teste_email')) {
            return redirect()->route('site.home');
        }

        return view('site.teste-gratis-aviso', [
            'titulo' => 'Falta só confirmar o seu e-mail',
            'texto'  => 'Enviamos um link para ' . session('teste_email') . '. Abra o e-mail e clique em "Confirmar e entrar" para o seu teste grátis começar. O link vale por ' . ContratacaoSite::VALIDADE_HORAS . ' horas. Se não achar, olhe a caixa de spam.',
            'botao'  => null,
        ]);
    }

    public function confirmar(Request $request, string $token): View|RedirectResponse
    {
        $cadastro = $this->contratacao->buscarPorToken($token);

        $aviso = fn (string $titulo, string $texto, ?array $botao) => view('site.teste-gratis-aviso', compact('titulo', 'texto', 'botao'));

        if (! $cadastro) {
            return $aviso('Este link não é válido', 'Confira se o endereço foi copiado inteiro do e-mail. Se preferir, faça o cadastro de novo.', ['Voltar ao site', route('site.home')]);
        }
        if ($cadastro->confirmado_em) {
            return $aviso('Este cadastro já foi confirmado', 'A sua conta já existe. Entre com o e-mail e a senha que você escolheu.', ['Entrar no sistema', route('login')]);
        }
        if ($cadastro->vencido()) {
            return $aviso('Este link venceu', 'O link de confirmação vale por ' . ContratacaoSite::VALIDADE_HORAS . ' horas. Faça o cadastro de novo para receber outro.', ['Voltar ao site', route('site.home')]);
        }

        try {
            $user = $this->contratacao->confirmar($cadastro);
        } catch (\DomainException $e) {
            if ($e->getMessage() === 'ja_confirmado') {
                return $aviso('Este cadastro já foi confirmado', 'A sua conta já existe. Entre com o e-mail e a senha que você escolheu.', ['Entrar no sistema', route('login')]);
            }

            return $aviso('Este e-mail ou CNPJ já tem cadastro', 'Entre pela tela de login ou chame a nossa equipe no WhatsApp.', ['Entrar no sistema', route('login')]);
        }

        // Avisa a IA365 (falha de e-mail não derruba o cadastro que já deu certo).
        try {
            if ($destino = ContratacaoSite::emailsAviso()) {
                Mail::to($destino)->send(new NovoCadastroSite($user->empresa, $user));
            }
        } catch (\Throwable $e) {
            Log::warning('Teste grátis: aviso interno não saiu', ['empresa' => $user->empresa_id, 'erro' => $e->getMessage()]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        // já entra na loja Matriz, sem passar pela tela de escolher loja
        session(['empresa_id' => $user->empresa_id, 'unidade_id' => $user->unidades()->value('unidades.id')]);

        return redirect()->route('app.dashboard')
            ->with('success', 'Seu teste grátis começou. Você tem ' . $user->empresa->diasRestantesTrial() . ' dias para usar o sistema inteiro.');
    }
}
