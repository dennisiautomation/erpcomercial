<?php

namespace App\Http\Controllers\App;

use App\Enums\TipoProduto;
use App\Http\Controllers\Controller;
use App\Models\GrupoOpcao;
use App\Models\Produto;
use App\Services\FiscalAutoConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Grupos de opções (Fase 1 cafeteria, 30/10/2026): "Tamanho", "Leite",
 * "Extras". Cada opção é um produto do tipo Adicional — que pode ser criado
 * aqui mesmo só com nome e preço, sem passar pelo cadastro completo.
 */
class GrupoOpcaoController extends Controller
{
    public function index(Request $request)
    {
        $this->exigirModulo();

        $grupos = GrupoOpcao::withCount(['itens', 'produtos'])
            ->with(['itens.adicional:id,descricao,preco_venda'])
            ->when($request->filled('busca'), fn ($q) => $q->where('nome', 'like', '%' . $request->busca . '%'))
            ->orderBy('nome')
            ->paginate(20)
            ->withQueryString();

        return view('app.grupos-opcoes.index', compact('grupos'));
    }

    public function create()
    {
        $this->exigirModulo();

        $grupo = new GrupoOpcao(['minimo' => 0, 'maximo' => 1, 'status' => 'ativo']);

        return view('app.grupos-opcoes.form', ['grupo' => $grupo, 'adicionais' => $this->adicionais()]);
    }

    public function store(Request $request)
    {
        $this->exigirModulo();
        $dados = $this->validar($request);

        $grupo = DB::transaction(function () use ($dados) {
            $grupo = GrupoOpcao::create($this->camposGrupo($dados));
            $this->salvarItens($grupo, $dados['itens'] ?? []);

            return $grupo;
        });

        return redirect()->route('app.grupos-opcoes.index')
            ->with('success', "Grupo \"{$grupo->nome}\" criado. Ligue-o aos produtos em Ficha técnica e opções.");
    }

    public function edit(GrupoOpcao $grupoOpcao)
    {
        $this->exigirModulo();
        $grupoOpcao->load('itens.adicional:id,descricao,preco_venda', 'produtos:id,descricao');

        return view('app.grupos-opcoes.form', ['grupo' => $grupoOpcao, 'adicionais' => $this->adicionais()]);
    }

    public function update(Request $request, GrupoOpcao $grupoOpcao)
    {
        $this->exigirModulo();
        $dados = $this->validar($request);

        DB::transaction(function () use ($grupoOpcao, $dados) {
            $grupoOpcao->update($this->camposGrupo($dados));
            $this->salvarItens($grupoOpcao, $dados['itens'] ?? []);
        });

        return redirect()->route('app.grupos-opcoes.index')
            ->with('success', "Grupo \"{$grupoOpcao->nome}\" atualizado.");
    }

    public function destroy(GrupoOpcao $grupoOpcao)
    {
        $this->exigirModulo();
        $nome = $grupoOpcao->nome;
        // O histórico das vendas guarda nome e preço das opções (venda_itens.opcoes):
        // apagar o grupo não muda venda nenhuma.
        $grupoOpcao->delete();

        return redirect()->route('app.grupos-opcoes.index')->with('success', "Grupo \"{$nome}\" excluído.");
    }

    /** A tela só existe para a empresa com "Usa ficha técnica" ligado. */
    private function exigirModulo(): void
    {
        $empresa = auth()->user()->empresa;
        if (! $empresa?->usaFichaTecnica()) {
            abort(404);
        }
    }

    private function adicionais()
    {
        return Produto::where('tipo', TipoProduto::Adicional->value)
            ->where('status', 'ativo')
            ->orderBy('descricao')
            ->get(['id', 'descricao', 'preco_venda']);
    }

    private function validar(Request $request): array
    {
        $empresaId = auth()->user()->empresa_id;

        $dados = $request->validate([
            'nome'                => 'required|string|max:100',
            'obrigatorio'         => 'nullable|boolean',
            'maximo'              => 'required|integer|min:1|max:20',
            'status'              => 'required|in:ativo,inativo',
            'itens'               => 'nullable|array|max:40',
            'itens.*.adicional_id'=> [
                'nullable',
                'integer',
                Rule::exists('produtos', 'id')->where(fn ($q) => $q->where('empresa_id', $empresaId)->where('tipo', TipoProduto::Adicional->value)),
            ],
            'itens.*.novo_nome'   => 'nullable|string|max:255',
            'itens.*.preco'       => 'nullable|numeric|min:0|max:99999',
        ], [
            'itens.*.adicional_id.exists' => 'Uma das opções não é um adicional desta empresa.',
        ]);

        $itens = collect($dados['itens'] ?? [])
            ->filter(fn ($i) => ! empty($i['adicional_id']) || trim((string) ($i['novo_nome'] ?? '')) !== '')
            ->values()
            ->all();

        $obrigatorio = (bool) ($dados['obrigatorio'] ?? false);
        if ($obrigatorio && $itens === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'itens' => 'Grupo obrigatório precisa de pelo menos uma opção.',
            ]);
        }

        $dados['itens'] = $itens;

        return $dados;
    }

    private function camposGrupo(array $dados): array
    {
        $maximo = (int) $dados['maximo'];

        return [
            'nome'   => $dados['nome'],
            'minimo' => ! empty($dados['obrigatorio']) ? 1 : 0,
            'maximo' => $maximo,
            'status' => $dados['status'],
        ];
    }

    /**
     * Regrava as opções do grupo na ordem da tela. Linha com "novo_nome" cria o
     * produto Adicional na hora (preço = o digitado; fiscal = padrão do regime).
     */
    private function salvarItens(GrupoOpcao $grupo, array $itens): void
    {
        $grupo->itens()->delete();

        $vistos = [];
        foreach (array_values($itens) as $ordem => $linha) {
            $preco = isset($linha['preco']) && $linha['preco'] !== '' ? round((float) $linha['preco'], 2) : null;

            if (! empty($linha['adicional_id'])) {
                $adicionalId = (int) $linha['adicional_id'];
            } else {
                $adicionalId = $this->criarAdicional(trim((string) $linha['novo_nome']), $preco ?? 0)->id;
                $preco = null; // o preço já ficou no próprio adicional
            }

            if (isset($vistos[$adicionalId])) {
                continue;
            }
            $vistos[$adicionalId] = true;

            $grupo->itens()->create([
                'empresa_id'   => $grupo->empresa_id,
                'adicional_id' => $adicionalId,
                'preco'        => $preco,
                'ordem'        => $ordem,
            ]);
        }
    }

    /** Adicional mínimo: nome, preço e os padrões fiscais do regime (como o cadastro faz). */
    private function criarAdicional(string $nome, float $preco): Produto
    {
        $empresa = auth()->user()->empresa;
        $regime = $empresa->regime_tributario instanceof \App\Enums\RegimeTributario
            ? $empresa->regime_tributario->value
            : $empresa->regime_tributario;
        $padrao = FiscalAutoConfig::defaults($regime);

        $ultimo = Produto::withoutGlobalScopes()->where('empresa_id', $empresa->id)->max('codigo_interno');
        $codigo = str_pad((string) ($ultimo ? intval($ultimo) + 1 : 1), 6, '0', STR_PAD_LEFT);

        return Produto::create([
            'empresa_id'     => $empresa->id,
            'codigo_interno' => $codigo,
            'sku'            => 'SKU-' . $codigo,
            'descricao'      => mb_substr($nome, 0, 255),
            'tipo'           => TipoProduto::Adicional->value,
            'unidade_medida' => 'UN',
            'preco_venda'    => $preco,
            'preco_custo'    => 0,
            'cst_csosn'      => $padrao['cst_csosn'] ?? null,
            'cfop'           => $padrao['cfop_venda_interna'] ?? null,
            'icms_aliquota'  => $padrao['icms_aliquota'] ?? 0,
            'pis_aliquota'   => $padrao['pis_aliquota'] ?? 0,
            'cofins_aliquota'=> $padrao['cofins_aliquota'] ?? 0,
            'origem'         => $padrao['origem'] ?? '0',
            'status'         => 'ativo',
        ]);
    }
}
