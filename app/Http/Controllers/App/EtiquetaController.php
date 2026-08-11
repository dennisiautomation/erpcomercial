<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoLoja;
use App\Models\EtiquetaFormato;
use App\Models\Produto;
use App\Services\TabelaPrecoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EtiquetaController extends Controller
{
    /** Formatos fixos (folha A4 + bobinas já calibradas em campo). */
    private const FORMATOS_FIXOS = [
        '2x5', '3x7', '4x10',
        'termica-40x25', 'termica-50x30', 'termica-60x40',
        'termica-33x22', 'termica-36x20-2col', 'termica-tag-35x60',
    ];

    /**
     * Show label generation page with product selection.
     */
    public function index(Request $request)
    {
        $produtos = Produto::where('empresa_id', auth()->user()->empresa_id)
            ->where('status', 'ativo')
            ->orderBy('descricao')
            ->get();

        $formatosPersonalizados = $this->formatosDaEmpresa();

        return view('app.etiquetas.index', compact('produtos', 'formatosPersonalizados'));
    }

    /**
     * Formatos cadastrados pela empresa. Admin da plataforma não tem empresa
     * (armadilha 25) — devolve coleção vazia em vez de estourar.
     */
    private function formatosDaEmpresa()
    {
        $empresaId = auth()->user()->empresa_id;

        if (! $empresaId) {
            return collect();
        }

        return EtiquetaFormato::where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get();
    }

    /**
     * Cadastra um formato próprio. O lojista digita em CENTÍMETROS (é o que ele
     * mede com a régua); o banco e o CSS trabalham em milímetros.
     */
    public function formatoStore(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        if (! $empresaId) {
            return redirect()->route('app.etiquetas.index')
                ->with('error', 'Selecione uma empresa para cadastrar formatos de etiqueta.');
        }

        $request->merge([
            'largura_cm' => $this->numeroBr($request->input('largura_cm')),
            'altura_cm'  => $this->numeroBr($request->input('altura_cm')),
            'espaco_cm'  => $this->numeroBr($request->input('espaco_cm')),
        ]);

        $dados = $request->validate([
            'nome'       => [
                'required', 'string', 'max:60',
                Rule::unique('etiqueta_formatos')->where('empresa_id', $empresaId),
            ],
            'largura_cm' => 'required|numeric|min:1|max:30',
            'altura_cm'  => 'required|numeric|min:0.8|max:30',
            'colunas'    => 'required|integer|min:1|max:6',
            'espaco_cm'  => 'nullable|numeric|min:0|max:2',
        ], [], [
            'largura_cm' => 'largura',
            'altura_cm'  => 'altura',
            'espaco_cm'  => 'espaço entre colunas',
        ]);

        EtiquetaFormato::create([
            'empresa_id'      => $empresaId,
            'nome'            => $dados['nome'],
            'largura_mm'      => round($dados['largura_cm'] * 10, 1),
            'altura_mm'       => round($dados['altura_cm'] * 10, 1),
            'colunas'         => $dados['colunas'],
            'espaco_mm'       => round(($dados['espaco_cm'] ?? 0) * 10, 1),
            'mostrar_empresa' => $request->boolean('mostrar_empresa'),
        ]);

        return redirect()->route('app.etiquetas.index')
            ->with('success', 'Formato cadastrado! Imprima 1 etiqueta de teste e ajuste as medidas se precisar.');
    }

    public function formatoDestroy(EtiquetaFormato $etiquetaFormato)
    {
        if ($etiquetaFormato->empresa_id !== auth()->user()->empresa_id) {
            abort(403);
        }

        $etiquetaFormato->delete();

        return redirect()->route('app.etiquetas.index')
            ->with('success', 'Formato removido.');
    }

    /** Aceita "3,2" e "3.2" — o lojista digita com vírgula. */
    private function numeroBr($valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return str_replace(',', '.', (string) $valor);
    }

    /**
     * Generate printable labels.
     */
    public function gerar(Request $request, TabelaPrecoService $tabelaPrecos)
    {
        // Formato compacto do form: produtos[<id>] = quantidade (1 input por produto).
        // O formato antigo produtos[i][id]/[quantidade] gastava 2 inputs por produto e
        // estourava o max_input_vars do PHP com "Selecionar Todos" (515 produtos = 1030
        // campos, PHP corta em 1000) — o corte silencioso chegava aqui como
        // "validation.required" sem culpado aparente.
        // Sem regra em produtos.*: o valor pode ser int (formato novo) OU array
        // {id, quantidade} (abas abertas antes do deploy) — a normalização abaixo
        // aceita os dois e a quantidade é clampada em 1..100.
        $request->validate([
            'produtos' => 'required|array|min:1',
            // Fixos OU "termica-custom-<id>" de um formato da própria empresa (validado abaixo).
            'formato'  => ['required', 'string', function ($attr, $valor, $fail) {
                $padrao = '/^' . preg_quote(EtiquetaFormato::PREFIXO_CHAVE, '/') . '\d+$/';
                if (! in_array($valor, self::FORMATOS_FIXOS, true) && ! preg_match($padrao, $valor)) {
                    $fail('Formato de etiqueta inválido.');
                }
            }],
        ]);

        // Formato personalizado: resolve e confere que é da empresa do usuário.
        $formatoCustom = null;
        if (str_starts_with($request->formato, EtiquetaFormato::PREFIXO_CHAVE)) {
            $formatoCustom = EtiquetaFormato::where('empresa_id', auth()->user()->empresa_id)
                ->find((int) substr($request->formato, strlen(EtiquetaFormato::PREFIXO_CHAVE)));

            if (! $formatoCustom) {
                return back()->with('error', 'Formato de etiqueta não encontrado.');
            }
        }

        // Compat: aceita também o formato antigo produtos[][id]/[quantidade]
        $selecao = [];
        foreach ($request->produtos as $chave => $valor) {
            if (is_array($valor)) {
                $selecao[(int) ($valor['id'] ?? 0)] = (int) ($valor['quantidade'] ?? 1);
            } else {
                $selecao[(int) $chave] = (int) $valor;
            }
        }
        unset($selecao[0]);

        $validos = Produto::whereIn('id', array_keys($selecao))->pluck('id')->all();
        if ($validos === []) {
            return back()->with('error', 'Selecione ao menos um produto válido.');
        }

        $configLoja = ConfiguracaoLoja::daUnidade();
        $maxParcelas = max(1, (int) ($configLoja->max_parcelas ?? 6));

        $produtosMap = Produto::with('precos')->whereIn('id', $validos)->get()->keyBy('id');

        $itens = [];
        $precosEtiqueta = [];
        foreach ($selecao as $produtoId => $quantidade) {
            $produto = $produtosMap[$produtoId] ?? null;
            if (! $produto) {
                continue;
            }
            $quantidade = max(1, min(100, $quantidade));

            // Etiqueta dupla ("6x R$ X ou R$ Y no PIX") só quando a tabela
            // crédito difere da base — sem configuração, etiqueta fica como antes.
            if (! isset($precosEtiqueta[$produto->id])) {
                $precos = $tabelaPrecos->precosDoProduto($produto, $configLoja);
                $precosEtiqueta[$produto->id] = [
                    'dual'          => $precos['credito'] > $precos['dinheiro_pix'],
                    'base'          => $precos['dinheiro_pix'],
                    'credito'       => $precos['credito'],
                    'parcelas'      => $maxParcelas,
                    'parcela_valor' => round($precos['credito'] / $maxParcelas, 2),
                ];
            }

            for ($i = 0; $i < $quantidade; $i++) {
                $itens[] = $produto;
            }
        }

        $formato = $request->formato;

        return view('app.etiquetas.print', compact('itens', 'formato', 'precosEtiqueta', 'formatoCustom'));
    }
}
