<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoLoja;
use App\Models\EtiquetaFormato;
use App\Models\EtiquetaImagem;
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

        return view('app.etiquetas.index', [
            'produtos'               => $produtos,
            'formatosPersonalizados' => $formatosPersonalizados,
            // Chave do formato fixo => registro que guarda o desenho dele.
            // A tela usa para marcar "layout personalizado" e abrir o editor.
            'layoutsDeFixos'         => $this->layoutsDeFixos(),
        ]);
    }

    /**
     * Formatos cadastrados pela empresa. Admin da plataforma não tem empresa
     * (armadilha 25) — devolve coleção vazia em vez de estourar.
     *
     * Personalizações de formato fixo (formato_base preenchido) ficam de fora:
     * elas não são uma opção de formato, são o desenho de uma opção que já existe.
     */
    private function formatosDaEmpresa()
    {
        $empresaId = auth()->user()->empresa_id;

        if (! $empresaId) {
            return collect();
        }

        return EtiquetaFormato::where('empresa_id', $empresaId)
            ->whereNull('formato_base')
            ->where('ativo', true)
            ->orderBy('nome')
            ->get();
    }

    /** Personalizações de formato fixo da empresa, indexadas pela chave do fixo. */
    private function layoutsDeFixos()
    {
        $empresaId = auth()->user()->empresa_id;

        if (! $empresaId) {
            return collect();
        }

        return EtiquetaFormato::where('empresa_id', $empresaId)
            ->whereNotNull('formato_base')
            ->get()
            ->keyBy('formato_base');
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
            'bobina_cm'  => $this->numeroBr($request->input('bobina_cm')),
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
            'bobina_cm'  => 'nullable|numeric|min:1|max:40',
            'estilo'     => 'nullable|in:padrao,nome_topo',
        ], [], [
            'largura_cm' => 'largura',
            'altura_cm'  => 'altura',
            'espaco_cm'  => 'espaço entre colunas',
            'bobina_cm'  => 'largura da bobina',
        ]);

        // Se a soma das colunas não cabe na bobina, o navegador manda uma página
        // mais larga que o papel e a impressora encolhe, corta ou gira — foi o
        // que aconteceu na MISS MERLINDA (formato de 10,3 cm em bobina de 7 cm).
        // Barrar aqui é mais barato que descobrir na etiqueta impressa.
        if (! empty($dados['bobina_cm'])) {
            $espaco = (float) ($dados['espaco_cm'] ?? 0);
            $exigido = $dados['colunas'] * $dados['largura_cm'] + max(0, $dados['colunas'] - 1) * $espaco;

            if (round($exigido, 2) > round((float) $dados['bobina_cm'], 2) + 0.001) {
                $cabem = max(1, (int) floor(
                    ((float) $dados['bobina_cm'] + $espaco) / ((float) $dados['largura_cm'] + $espaco)
                ));
                $n = fn ($v) => number_format($v, 1, ',', '');

                return back()->withInput()->withErrors(['colunas' => sprintf(
                    '%s colunas de %s cm com %s cm de espaço exigem uma bobina de %s cm, '
                    . 'mas a sua tem %s cm. Nessa bobina cabem %s coluna(s) — '
                    . 'use %s colunas, ou reduza a largura da etiqueta.',
                    $dados['colunas'], $n($dados['largura_cm']), $n($espaco),
                    $n($exigido), $n($dados['bobina_cm']), $cabem, $cabem
                )]);
            }
        }

        EtiquetaFormato::create([
            'empresa_id'      => $empresaId,
            'nome'            => $dados['nome'],
            'largura_mm'      => round($dados['largura_cm'] * 10, 1),
            'altura_mm'       => round($dados['altura_cm'] * 10, 1),
            'colunas'         => $dados['colunas'],
            'espaco_mm'       => round(($dados['espaco_cm'] ?? 0) * 10, 1),
            'estilo'          => $dados['estilo'] ?? 'padrao',
            // No estilo "nome no topo" o nome da loja É o layout — não faz
            // sentido cadastrar o estilo e deixar o nome escondido.
            'mostrar_empresa' => ($dados['estilo'] ?? 'padrao') === 'nome_topo'
                ? true
                : $request->boolean('mostrar_empresa'),
        ]);

        return redirect()->route('app.etiquetas.index')
            ->with('success', 'Formato cadastrado! Imprima 1 etiqueta de teste e ajuste as medidas se precisar.');
    }

    public function formatoDestroy(EtiquetaFormato $etiquetaFormato)
    {
        $this->autorizarFormato($etiquetaFormato);

        $etiquetaFormato->delete();

        return redirect()->route('app.etiquetas.index')
            ->with('success', 'Formato removido.');
    }

    /* ===================== EDITOR VISUAL DE LAYOUT ===================== */

    /**
     * Tela de arrastar-e-soltar do layout. Abre com o desenho atual: o layout
     * salvo, ou o automático convertido em itens posicionados (layoutInicial),
     * para o lojista ajustar em vez de começar de uma etiqueta vazia.
     */
    public function editor(EtiquetaFormato $etiquetaFormato)
    {
        $this->autorizarFormato($etiquetaFormato);

        $elementos = $etiquetaFormato->temLayoutLivre()
            ? $etiquetaFormato->elementosLayout()
            : $etiquetaFormato->layoutInicial();

        return view('app.etiquetas.editor', [
            'formato'   => $etiquetaFormato,
            'elementos' => $elementos,
            'exemplo'   => $this->produtoExemplo(),
            'imagens'   => EtiquetaImagem::where('empresa_id', $etiquetaFormato->empresa_id)
                ->orderByDesc('id')
                ->get()
                ->map(fn ($i) => ['id' => $i->id, 'nome' => $i->nome, 'url' => $i->url])
                ->values(),
        ]);
    }

    /**
     * Abre o editor em cima de um formato FIXO. O fixo continua intocado (é
     * constante compartilhada por todas as empresas): o que nasce aqui é um
     * registro só com o desenho, amarrado a ele e à empresa. Enquanto esse
     * registro não tiver layout salvo, a impressão segue no automático.
     */
    public function editorFixo(string $chave)
    {
        $empresaId = auth()->user()->empresa_id;

        if (! $empresaId) {
            return redirect()->route('app.etiquetas.index')
                ->with('error', 'Selecione uma empresa para editar o layout das etiquetas.');
        }

        if (! isset(EtiquetaFormato::MEDIDAS_FIXOS[$chave])) {
            abort(404);
        }

        $medidas = EtiquetaFormato::MEDIDAS_FIXOS[$chave];

        $formato = EtiquetaFormato::firstOrCreate(
            ['empresa_id' => $empresaId, 'formato_base' => $chave],
            [
                'nome'            => 'Layout · ' . $medidas['rotulo'],
                'largura_mm'      => $medidas['w'],
                'altura_mm'       => $medidas['h'],
                'colunas'         => 1,
                'espaco_mm'       => 0,
                'estilo'          => 'padrao',
                // O nome da loja já sai nos formatos fixos que têm altura para ele.
                'mostrar_empresa' => $medidas['h'] >= 22,
                'ativo'           => true,
            ]
        );

        return redirect()->route('app.etiquetas.formatos.editor', $formato);
    }

    /** Salva o desenho. O JSON do navegador é normalizado antes de encostar no banco. */
    public function layoutUpdate(Request $request, EtiquetaFormato $etiquetaFormato)
    {
        $this->autorizarFormato($etiquetaFormato);

        $bruto = json_decode((string) $request->input('layout'), true);

        if (! is_array($bruto) || ! is_array($bruto['elementos'] ?? null)) {
            return back()->with('error', 'Não consegui ler o layout enviado. Tente salvar de novo.');
        }

        $elementos = $this->sanitizarElementos($bruto['elementos'], $etiquetaFormato);

        if ($elementos === []) {
            return back()->with('error', 'A etiqueta precisa de pelo menos um item. Adicione um campo antes de salvar.');
        }

        $etiquetaFormato->update([
            'layout_json' => ['versao' => 1, 'elementos' => $elementos],
        ]);

        return redirect()->route('app.etiquetas.index')
            ->with('success', 'Layout salvo! Imprima 1 etiqueta de teste antes de rodar o lote.');
    }

    /** Volta o formato para o layout automático (derivado das medidas). */
    public function layoutReset(EtiquetaFormato $etiquetaFormato)
    {
        $this->autorizarFormato($etiquetaFormato);

        $etiquetaFormato->update(['layout_json' => null]);

        return redirect()->route('app.etiquetas.formatos.editor', $etiquetaFormato)
            ->with('success', 'Layout automático restaurado.');
    }

    /* ---------- Galeria de imagens da empresa (usadas no editor) ---------- */

    /**
     * Sobe uma imagem para a galeria. Responde JSON porque o envio acontece com
     * o editor aberto: um POST comum recarregaria a página e levaria junto o
     * desenho ainda não salvo.
     */
    public function imagemStore(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        if (! $empresaId) {
            return response()->json(['erro' => 'Selecione uma empresa antes de enviar imagens.'], 422);
        }

        $validador = validator($request->all(), [
            // 2 MB e só formatos que a impressora resolve. SVG fica de fora de
            // propósito: é XML executável, e o arquivo vai ser servido do nosso domínio.
            'arquivo' => 'required|file|mimes:png,jpg,jpeg,gif,webp|max:2048',
            'nome'    => 'nullable|string|max:60',
        ], [], ['arquivo' => 'imagem']);

        if ($validador->fails()) {
            return response()->json(['erro' => $validador->errors()->first()], 422);
        }

        if (EtiquetaImagem::where('empresa_id', $empresaId)->count() >= 30) {
            return response()->json(['erro' => 'Limite de 30 imagens na galeria. Apague alguma antes de enviar outra.'], 422);
        }

        $arquivo = $request->file('arquivo');
        // Pasta por empresa: o caminho nunca é montado com dado do cliente.
        $caminho = $arquivo->store('etiquetas/' . $empresaId, 'public');

        $imagem = EtiquetaImagem::create([
            'empresa_id' => $empresaId,
            'nome'       => mb_substr($request->input('nome') ?: $arquivo->getClientOriginalName(), 0, 60),
            'caminho'    => $caminho,
        ]);

        return response()->json([
            'id'   => $imagem->id,
            'nome' => $imagem->nome,
            'url'  => $imagem->url,
        ]);
    }

    /** Remove a imagem da galeria (e o arquivo do disco). */
    public function imagemDestroy(EtiquetaImagem $etiquetaImagem)
    {
        if ($etiquetaImagem->empresa_id !== auth()->user()->empresa_id) {
            abort(403);
        }

        $etiquetaImagem->delete();

        return response()->json(['ok' => true]);
    }

    private function autorizarFormato(EtiquetaFormato $formato): void
    {
        if (! $formato->exists || $formato->empresa_id !== auth()->user()->empresa_id) {
            abort(403);
        }
    }

    /**
     * Normaliza cada item: tipo na whitelist, medidas dentro da etiqueta, fonte
     * conhecida, cor em hex. Vale como segurança (o JSON vem do cliente e cai
     * direto num style=) e como garantia de que um item não nasce fora da área
     * imprimível — fora da etiqueta ele some no `overflow: hidden` e o lojista
     * fica procurando um campo que "não salvou".
     */
    private function sanitizarElementos(array $elementos, EtiquetaFormato $formato): array
    {
        $tipos = EtiquetaFormato::tiposValidos();
        $imagensDaEmpresa = EtiquetaImagem::where('empresa_id', $formato->empresa_id)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
        $limpos = [];

        foreach (array_slice($elementos, 0, EtiquetaFormato::MAX_ELEMENTOS) as $el) {
            if (! is_array($el) || ! in_array($el['tipo'] ?? null, $tipos, true)) {
                continue;
            }

            $tipo = $el['tipo'];
            $num = fn ($v, $min, $max, $padrao) => is_numeric($v)
                ? round(max($min, min($max, (float) $v)), 2)
                : $padrao;

            // Largura/altura primeiro: a posição máxima depende do tamanho do item.
            $w = $num($el['w'] ?? null, 0.5, $formato->largura_mm, min(10, $formato->largura_mm));
            $h = $num($el['h'] ?? null, 0.3, $formato->altura_mm, min(5, $formato->altura_mm));

            $item = [
                'tipo' => $tipo,
                'x'    => $num($el['x'] ?? null, 0, max(0, $formato->largura_mm - $w), 0),
                'y'    => $num($el['y'] ?? null, 0, max(0, $formato->altura_mm - $h), 0),
                'w'    => $w,
                'h'    => $h,
            ];

            if (in_array($tipo, EtiquetaFormato::TIPOS_TEXTO, true)) {
                $fonte = $el['fonte'] ?? null;
                $item['fonte'] = in_array($fonte, EtiquetaFormato::FONTES, true) ? $fonte : 'Arial';
                $item['tamanho'] = $num($el['tamanho'] ?? null, 3, 72, 8);
                $item['negrito'] = (bool) ($el['negrito'] ?? false);
                $item['italico'] = (bool) ($el['italico'] ?? false);
                $alinhamento = $el['alinhamento'] ?? null;
                $item['alinhamento'] = in_array($alinhamento, EtiquetaFormato::ALINHAMENTOS, true)
                    ? $alinhamento
                    : 'center';
            }

            if ($tipo === 'imagem') {
                // Só imagens da galeria DESTA empresa. O id vem do navegador;
                // sem esta conferência, um id chutado exibiria a arte de outro
                // lojista na etiqueta.
                $id = (int) ($el['imagem_id'] ?? 0);
                if (! in_array($id, $imagensDaEmpresa, true)) {
                    continue;
                }
                $item['imagem_id'] = $id;
            }

            if (in_array($tipo, ['retangulo', 'linha'], true)) {
                $item['espessura'] = $num($el['espessura'] ?? null, 0.1, 5, 0.3);
                $item['preenchido'] = $tipo === 'retangulo' && (bool) ($el['preenchido'] ?? false);
                // Cor só entra se for hex de 6 dígitos — o valor cai dentro de um style=.
                $cor = (string) ($el['cor'] ?? '');
                $item['cor'] = preg_match('/^#[0-9A-Fa-f]{6}$/', $cor) ? $cor : '#000000';
            }

            $limpos[] = $item;
        }

        // Campo do ERP duas vezes é o mesmo dado impresso em dois lugares —
        // sempre erro de arrastar. Já linha/moldura/imagem repetem à vontade:
        // é disso que desenho é feito.
        $vistos = [];
        $unicos = EtiquetaFormato::tiposUnicos();

        return array_values(array_filter($limpos, function (array $el) use (&$vistos, $unicos) {
            if (! in_array($el['tipo'], $unicos, true)) {
                return true;
            }
            if (in_array($el['tipo'], $vistos, true)) {
                return false;
            }
            $vistos[] = $el['tipo'];

            return true;
        }));
    }

    /**
     * Produto de mentira para a pré-visualização do editor. Usa um produto real
     * da empresa quando existe — nome curto demais no exemplo esconde justamente
     * o problema de descrição que estoura a caixa.
     */
    private function produtoExemplo(): array
    {
        $produto = Produto::where('empresa_id', auth()->user()->empresa_id)
            ->where('status', 'ativo')
            ->orderByDesc('id')
            ->first();

        $empresa = auth()->user()->empresa;

        return [
            'tem_logo'       => (bool) $empresa?->logo,
            'descricao'      => $produto->descricao ?? 'CAMISETA BÁSICA ALGODÃO PRETA M',
            'preco'          => number_format((float) ($produto->preco_venda ?? 89.90), 2, ',', '.'),
            'codigo_interno' => $produto->codigo_interno ?? 'PRD-0001',
            'codigo_barras'  => $produto->codigo_barras ?: ($produto->codigo_interno ?? '7891234567895'),
            'empresa_nome'   => $empresa->nome_fantasia ?: $empresa->razao_social ?: 'Minha Loja',
            'empresa_logo'   => $empresa?->logo ? asset('storage/' . $empresa->logo) : null,
        ];
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
                // Personalização de formato fixo não é um formato imprimível por si:
                // ela não tem a medida da bobina, e imprimir por ela sairia na
                // página errada. Só o formato próprio do lojista entra aqui.
                ->whereNull('formato_base')
                ->find((int) substr($request->formato, strlen(EtiquetaFormato::PREFIXO_CHAVE)));

            if (! $formatoCustom) {
                return back()->with('error', 'Formato de etiqueta não encontrado.');
            }
        }

        // Quem manda no DESENHO do miolo. Para formato próprio é ele mesmo; para
        // formato fixo é a personalização da empresa, se ela existir e tiver
        // layout salvo. Sem isso, cai no arranjo automático de sempre.
        $layoutFormato = $formatoCustom ?: EtiquetaFormato::where('empresa_id', auth()->user()->empresa_id)
            ->where('formato_base', $request->formato)
            ->first();

        if ($layoutFormato && ! $layoutFormato->temLayoutLivre()) {
            $layoutFormato = null;
        }

        // Só carrega a galeria se o desenho realmente usa imagem.
        $imagensLayout = $layoutFormato
            ? EtiquetaImagem::where('empresa_id', auth()->user()->empresa_id)->get()->keyBy('id')
            : collect();

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

        return view('app.etiquetas.print', compact(
            'itens', 'formato', 'precosEtiqueta', 'formatoCustom', 'layoutFormato', 'imagensLayout'
        ));
    }
}
