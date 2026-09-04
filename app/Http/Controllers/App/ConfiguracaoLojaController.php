<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoLoja;
use App\Models\Empresa;
use Illuminate\Http\Request;

class ConfiguracaoLojaController extends Controller
{
    public function edit()
    {
        $config = ConfiguracaoLoja::daUnidade();

        // "Acesso do vendedor" é a única opção desta tela que vale para a EMPRESA
        // inteira, não só para a loja da sessão — por isso vem de outro model e a
        // tela diz isso em voz alta. Admin da plataforma não tem empresa própria
        // (armadilha 25): cai na empresa da sessão.
        $empresa = $this->empresaDaSessao();
        $podeMudarAcessoVendedor = $this->podeMudarAcessoVendedor();

        // Quantos usuários a chave alcança de verdade. Lição de 02/09 (tela do
        // juros): configuração sem número na tela é configuração que o lojista
        // não sabe se deve ligar.
        $vendedoresAtivos = $empresa
            ? \App\Models\User::where('empresa_id', $empresa->id)
                ->where('perfil', \App\Enums\Perfil::Vendedor->value)
                ->where('status', 'ativo')
                ->count()
            : 0;

        return view('app.configuracoes.edit', compact(
            'config', 'empresa', 'podeMudarAcessoVendedor', 'vendedoresAtivos'
        ));
    }

    /** Empresa do usuário logado, ou a da sessão quando é o admin da plataforma. */
    private function empresaDaSessao(): ?Empresa
    {
        $empresaId = auth()->user()->empresa_id ?? session('empresa_id');

        return $empresaId ? Empresa::find($empresaId) : null;
    }

    /**
     * Quem liga/desliga o modo "vendedor só opera o PDV".
     *
     * O gerente entra nesta tela desde 02/09, mas esta opção mexe no acesso de
     * OUTRO usuário e vale para todas as lojas — fica com o dono e com a IA365.
     * A guarda é aqui, no servidor: `@disabled` na view não impede POST forjado
     * (mesma pegadinha do switch travado dos juros).
     */
    private function podeMudarAcessoVendedor(): bool
    {
        $user = auth()->user();

        return (bool) ($user->is_admin || $user->isDono());
    }

    public function update(Request $request)
    {
        $dados = $request->validate([
            'vendedor_responsavel_caixa' => 'nullable|boolean',
            'regra_preco_split'          => 'required|in:cartao_maior,sempre_menor,sempre_maior',
            'percentual_debito'          => 'required|numeric|min:0|max:100',
            'percentual_credito'         => 'required|numeric|min:0|max:100',
            'max_parcelas'               => 'required|integer|min:1|max:24',
            'juros_por_parcela'          => 'nullable|array',
            'juros_por_parcela.*'        => 'nullable|numeric|min:0|max:100',
            'pdv_mostrar_valor_parcelas' => 'nullable|boolean',
            'cupom_automatico_cartao'    => 'nullable|boolean',
            'cpf_emite_fiscal'           => 'nullable|boolean',
            'padrao_impressao'           => 'required|in:recibo,cupom_fiscal',
            // Impressão da Ordem de Serviço
            // Limites ampliados em 02/09/2026: a Realiza Phone reportou "está
            // limitado a 500". Não havia limite de 500 em lugar nenhum (a coluna é
            // TEXT, ~65 mil) — o que faltava era a tela DIZER quanto cabe. Junto do
            // contador de caracteres, os tetos subiram para não encostar de novo:
            // um termo de garantia de assistência técnica passa fácil de 5 mil.
            'os_cabecalho'               => 'nullable|string|max:5000',
            'os_termos_garantia'         => 'nullable|string|max:15000',
            'os_texto_legal'             => 'nullable|string|max:15000',
            'os_rodape'                  => 'nullable|string|max:5000',
            'os_mostrar_assinatura'      => 'nullable|boolean',
            'os_mostrar_laudo'           => 'nullable|boolean',
            'os_mostrar_valores'         => 'nullable|boolean',
            // Trocas (03/09/2026)
            'troca_prazo_dias'           => 'required|integer|min:0|max:3650',
            'troca_sobra'                => 'required|in:vale,dinheiro',
            'troca_vale_validade_dias'   => 'required|integer|min:0|max:3650',
            'troca_senha_gerente'        => 'nullable|boolean',
            // Não é coluna de configuracoes_loja — é da empresa. Tratado à parte
            // logo abaixo e REMOVIDO do array antes do update, senão o Eloquent
            // descartaria a chave em silêncio (armadilha 53) e o switch pareceria
            // funcionar sem nunca gravar.
            'vendedor_apenas_pdv'        => 'nullable|boolean',
        ]);

        $vendedorApenasPdv = (bool) ($dados['vendedor_apenas_pdv'] ?? false);
        unset($dados['vendedor_apenas_pdv']);

        // Checkboxes desmarcados não vêm no request
        foreach ([
            'vendedor_responsavel_caixa',
            'cupom_automatico_cartao',
            'cpf_emite_fiscal',
            'os_mostrar_assinatura',
            'os_mostrar_laudo',
            'os_mostrar_valores',
            'pdv_mostrar_valor_parcelas',
            'troca_senha_gerente',
        ] as $flag) {
            $dados[$flag] = (bool) ($dados[$flag] ?? false);
        }

        // Tabela de juros: guarda só as parcelas que têm acréscimo de verdade.
        // Campo em branco ou zerado é parcela sem juros — não precisa de linha
        // no JSON, e assim a tabela some inteira quando a loja zera tudo.
        $dados['juros_por_parcela'] = collect($dados['juros_por_parcela'] ?? [])
            ->map(fn ($valor) => (float) $valor)
            ->filter(fn ($valor, $parcelas) => $valor > 0 && (int) $parcelas >= 2)
            ->all();

        // Unique (empresa_id, unidade_id): where()->first() + update()/create()
        $config = ConfiguracaoLoja::withoutGlobalScopes()
            ->where('empresa_id', session('empresa_id'))
            ->where('unidade_id', session('unidade_id'))
            ->first();

        if ($config) {
            $config->update($dados);
        } else {
            ConfiguracaoLoja::create($dados + [
                'empresa_id' => session('empresa_id'),
                'unidade_id' => session('unidade_id'),
            ]);
        }

        // Acesso do vendedor: empresa inteira, e só dono/admin mudam. Quem não
        // pode simplesmente não tem o campo aplicado — o valor salvo permanece.
        if ($this->podeMudarAcessoVendedor() && ($empresa = $this->empresaDaSessao())) {
            $empresa->update(['vendedor_apenas_pdv' => $vendedorApenasPdv]);
        }

        return redirect()->route('app.configuracoes.edit')
            ->with('success', 'Configurações da loja salvas com sucesso!');
    }
}
