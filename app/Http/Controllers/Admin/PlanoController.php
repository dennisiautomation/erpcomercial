<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plano;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanoController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        $planos = Plano::orderBy('ordem')->get();
        $contratacaoSite = \App\Services\Plataforma\ContratacaoSite::ativa();
        $cadastrosSite = \App\Models\Empresa::withoutGlobalScopes()->where('origem_cadastro', 'site')->count();

        $emailsAviso = \App\Services\Plataforma\ContratacaoSite::emailsAvisoGravados();
        $emailAvisoPadrao = config('mail.from.address');

        return view('admin.planos.index', compact('planos', 'contratacaoSite', 'cadastrosSite', 'emailsAviso', 'emailAvisoPadrao'));
    }

    /**
     * Inclui um ou vários e-mails (separados por vírgula) na lista de quem recebe o aviso de
     * cada cadastro novo feito pelo site. Os que já estavam continuam.
     */
    public function contratacaoSiteEmail(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $servico = \App\Services\Plataforma\ContratacaoSite::class;
        $novos = $servico::separar((string) $request->input('email_aviso'));

        if (! $novos) {
            return back()->withErrors(['email_aviso' => 'Digite pelo menos um e-mail para incluir.']);
        }

        $ruins = array_filter($novos, fn ($e) => mb_strlen($e) > 160 || ! filter_var($e, FILTER_VALIDATE_EMAIL));
        if ($ruins) {
            return back()->withInput()->withErrors([
                'email_aviso' => 'Confira ' . (count($ruins) > 1 ? 'estes endereços' : 'este endereço') . ': ' . implode(', ', $ruins) . '. Nada foi incluído.',
            ]);
        }

        $atuais = $servico::emailsAvisoGravados();
        $incluir = array_values(array_diff($novos, $atuais));

        if (! $incluir) {
            return back()->with('warning', count($novos) > 1 ? 'Esses e-mails já estavam na lista.' : 'Esse e-mail já estava na lista.');
        }

        if (count($atuais) + count($incluir) > $servico::MAX_EMAILS_AVISO) {
            return back()->withInput()->withErrors([
                'email_aviso' => 'A lista aceita até ' . $servico::MAX_EMAILS_AVISO . ' e-mails. Remova algum antes de incluir.',
            ]);
        }

        $servico::definirEmailsAviso(array_merge($atuais, $incluir));

        return back()->with('success', (count($incluir) > 1 ? 'Incluídos: ' : 'Incluído: ') . implode(', ', $incluir) . '.');
    }

    /** Tira UM e-mail da lista do aviso. Os outros continuam. */
    public function contratacaoSiteEmailRemover(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $servico = \App\Services\Plataforma\ContratacaoSite::class;
        $alvo = mb_strtolower(trim((string) $request->input('email')));
        $atuais = $servico::emailsAvisoGravados();

        if (! in_array($alvo, $atuais, true)) {
            return back()->with('warning', 'Esse e-mail não está mais na lista.');
        }

        $servico::definirEmailsAviso(array_values(array_diff($atuais, [$alvo])));

        return back()->with('success', 'Removido: ' . $alvo . '.' . (count($atuais) === 1
            ? ' A lista ficou vazia: o aviso volta a ir para o remetente do sistema.' : ''));
    }

    /**
     * Liga/desliga a contratação pelo site (20/11/2026): preços na página inicial e o
     * cadastro de teste grátis em /teste-gratis. Desligada, o site mostra "Sob consulta".
     */
    public function contratacaoSite(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $ligar = $request->boolean('ativa');
        \App\Services\Plataforma\ContratacaoSite::definir($ligar);

        return back()->with('success', $ligar
            ? 'Contratação pelo site LIGADA: a página inicial mostra os preços e o botão de teste grátis.'
            : 'Contratação pelo site DESLIGADA: a página inicial mostra os planos como "Sob consulta".');
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);

        return view('admin.planos.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate($this->rules());

        if (array_key_exists('open_finance_contas', $validated)) {
            $validated['open_finance_contas'] = (int) ($validated['open_finance_contas'] ?? 0);
        }

        Plano::create($validated);

        return redirect()->route('admin.planos.index')
            ->with('success', 'Plano criado com sucesso.');
    }

    public function edit(Request $request, Plano $plano): View
    {
        abort_unless($request->user()->is_admin, 403);

        return view('admin.planos.edit', compact('plano'));
    }

    public function update(Request $request, Plano $plano): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate($this->rules($plano->id));

        if (array_key_exists('open_finance_contas', $validated)) {
            $validated['open_finance_contas'] = (int) ($validated['open_finance_contas'] ?? 0);
        }

        $plano->update($validated);

        return redirect()->route('admin.planos.index')
            ->with('success', 'Plano atualizado com sucesso.');
    }

    public function destroy(Request $request, Plano $plano): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        if ($plano->empresas()->count() > 0) {
            return back()->with('error', 'Nao e possivel excluir um plano com empresas vinculadas.');
        }

        $plano->delete();

        return redirect()->route('admin.planos.index')
            ->with('success', 'Plano excluido com sucesso.');
    }

    protected function rules(?int $ignoreId = null): array
    {
        return [
            'nome'                   => ['required', 'string', 'max:255'],
            'slug'                   => ['required', 'string', 'max:255', 'unique:planos,slug' . ($ignoreId ? ",{$ignoreId}" : '')],
            'descricao'              => ['nullable', 'string'],
            'preco_mensal'           => ['required', 'numeric', 'min:0'],
            'preco_anual'            => ['required', 'numeric', 'min:0'],
            'max_unidades'           => ['required', 'integer', 'min:1'],
            'max_usuarios'           => ['required', 'integer', 'min:1'],
            'max_produtos'           => ['required', 'integer', 'min:1'],
            'max_notas_mes'          => ['required', 'integer', 'min:1'],
            'pdv_habilitado'         => ['boolean'],
            'fiscal_habilitado'      => ['boolean'],
            'multilojas_habilitado'  => ['boolean'],
            'os_habilitado'          => ['boolean'],
            'contratos_habilitado'   => ['boolean'],
            'conciliacao_habilitada' => ['boolean'],
            'dre_habilitado'         => ['boolean'],
            'boletos_habilitado'     => ['boolean'],
            'api_habilitada'         => ['boolean'],
            'open_finance_habilitado' => ['boolean'],
            'open_finance_contas'    => ['nullable', 'integer', 'min:0', 'max:999'],
            'dias_trial'             => ['required', 'integer', 'min:0'],
            'ativo'                  => ['boolean'],
            'ordem'                  => ['required', 'integer', 'min:0'],
        ];
    }
}
