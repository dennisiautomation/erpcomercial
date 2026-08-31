<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoLoja;
use Illuminate\Http\Request;

class ConfiguracaoLojaController extends Controller
{
    public function edit()
    {
        $config = ConfiguracaoLoja::daUnidade();

        return view('app.configuracoes.edit', compact('config'));
    }

    public function update(Request $request)
    {
        $dados = $request->validate([
            'vendedor_responsavel_caixa' => 'nullable|boolean',
            'regra_preco_split'          => 'required|in:cartao_maior,sempre_menor,sempre_maior',
            'percentual_debito'          => 'required|numeric|min:0|max:100',
            'percentual_credito'         => 'required|numeric|min:0|max:100',
            'max_parcelas'               => 'required|integer|min:1|max:24',
            'cupom_automatico_cartao'    => 'nullable|boolean',
            'cpf_emite_fiscal'           => 'nullable|boolean',
            'padrao_impressao'           => 'required|in:recibo,cupom_fiscal',
            // Impressão da Ordem de Serviço
            'os_cabecalho'               => 'nullable|string|max:2000',
            'os_termos_garantia'         => 'nullable|string|max:5000',
            'os_texto_legal'             => 'nullable|string|max:5000',
            'os_rodape'                  => 'nullable|string|max:2000',
            'os_mostrar_assinatura'      => 'nullable|boolean',
            'os_mostrar_laudo'           => 'nullable|boolean',
            'os_mostrar_valores'         => 'nullable|boolean',
        ]);

        // Checkboxes desmarcados não vêm no request
        foreach ([
            'vendedor_responsavel_caixa',
            'cupom_automatico_cartao',
            'cpf_emite_fiscal',
            'os_mostrar_assinatura',
            'os_mostrar_laudo',
            'os_mostrar_valores',
        ] as $flag) {
            $dados[$flag] = (bool) ($dados[$flag] ?? false);
        }

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

        return redirect()->route('app.configuracoes.edit')
            ->with('success', 'Configurações da loja salvas com sucesso!');
    }
}
