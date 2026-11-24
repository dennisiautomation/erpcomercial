<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckPermission;
use App\Models\ConfiguracaoFiscal;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\NFeEntrada;
use App\Models\NFeRecebida;
use App\Models\Unidade;
use App\Services\EntradaNFe\EntradaNFeService;
use App\Services\FocusNFe\FocusNFeClient;
use App\Support\Cnpj;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Entrada de mercadoria pela NF-e de compra (23/10/2026).
 * Regra de negócio inteira em EntradaNFeService; aqui só tela e permissão.
 */
class EntradaNFeController extends Controller
{
    public function __construct(private EntradaNFeService $service) {}

    public function index()
    {
        $entradas = NFeEntrada::with(['fornecedor:id,razao_social', 'concluidor:id,name'])
            ->latest()
            ->paginate(20);

        return view('app.estoque.entradas-nfe.index', compact('entradas'));
    }

    /** Envio do arquivo XML. */
    public function store(Request $request)
    {
        $request->validate([
            'xml' => 'required|file|max:5120',
        ], [
            'xml.required' => 'Escolha o arquivo XML da nota.',
            'xml.max'      => 'O XML passa de 5 MB — confira se é mesmo o arquivo da nota.',
        ]);

        $arquivo = $request->file('xml');
        if (strtolower($arquivo->getClientOriginalExtension()) !== 'xml') {
            return back()->with('error', 'Envie o arquivo .xml da nota (não o PDF/DANFE).');
        }

        return $this->abrirRascunho((string) file_get_contents($arquivo->getRealPath()));
    }

    /** Botão "Dar entrada" na tela de NFes Recebidas: baixa o XML pela Focus. */
    public function daRecebida(NFeRecebida $nfeRecebida)
    {
        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $nfeRecebida->empresa_id)
            ->where('unidade_id', $nfeRecebida->unidade_id)
            ->first();

        $xml = null;
        try {
            if ($config) {
                $resp = FocusNFeClient::fromConfig($config)->get("/v2/nfes_recebidas/{$nfeRecebida->chave_acesso}.xml");
                if ($resp->successful() && str_contains($resp->body(), 'infNFe')) {
                    $xml = $resp->body();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[EntradaNFe] XML da nota recebida não veio da Focus', [
                'nfe_recebida_id' => $nfeRecebida->id,
                'error'           => $e->getMessage(),
            ]);
        }

        if (! $xml) {
            // Sem "Ciência da operação" a Receita só entrega o resumo, sem itens
            return redirect()->route('app.entradas-nfe.index')->with('error',
                'Não consegui baixar o XML completo desta nota. Faça a "Ciência da operação" em NFes Recebidas e tente de novo, '
                . 'ou envie aqui o arquivo XML que o fornecedor mandou.');
        }

        return $this->abrirRascunho($xml);
    }

    public function show(NFeEntrada $entrada)
    {
        $entrada->load(['itens.produto:id,descricao,codigo_interno,preco_custo,unidade_medida', 'fornecedor', 'estoque', 'concluidor:id,name']);

        if ($entrada->concluida()) {
            // Devolução ao fornecedor (24/11/2026)
            $devSvc = app(\App\Services\EntradaNFe\DevolucaoCompraService::class);
            $entrada->load(['devolucoes.itens', 'devolucoes.user:id,name']);

            return view('app.estoque.entradas-nfe.show', [
                'entrada'        => $entrada,
                'jaDevolvido'    => $devSvc->jaDevolvido($entrada),
                'contasAbertas'  => $devSvc->contasEmAberto($entrada),
                'podeFinanceiro' => CheckPermission::canUser(auth()->user(), 'financeiro', 'editar'),
            ]);
        }

        $estoques = Estoque::withoutGlobalScopes()
            ->where('unidade_id', $entrada->unidade_id)
            ->where('status', 'ativo')
            ->orderByDesc('is_padrao')
            ->orderBy('nome')
            ->get(['id', 'nome', 'is_padrao']);

        return view('app.estoque.entradas-nfe.conferir', [
            'entrada'           => $entrada,
            'estoques'          => $estoques,
            'podeContaPagar'    => CheckPermission::canUser(auth()->user(), 'financeiro', 'criar'),
            'avisoDestinatario' => $this->avisoDestinatario($entrada),
        ]);
    }

    public function confirmar(Request $request, NFeEntrada $entrada)
    {
        $request->validate([
            'estoque_id'               => 'required|integer',
            'atualizar_custo'          => 'nullable|boolean',
            'gerar_conta_pagar'        => 'nullable|boolean',
            'vencimento'               => 'nullable|date',
            'itens'                    => 'required|array',
            'itens.*.acao'             => 'required|in:vincular,criar,ignorar',
            'itens.*.produto_id'       => 'nullable|integer',
            'itens.*.fator_conversao'  => 'nullable|numeric|min:0.0001|max:100000',
            'itens.*.preco_venda'      => 'nullable|string|max:20',
            'itens.*.unidade_medida'   => 'nullable|string|max:5',
        ]);

        // Conta a pagar é do financeiro: quem não pode lançar lá não gera por aqui
        $gerarConta = $request->boolean('gerar_conta_pagar')
            && CheckPermission::canUser(auth()->user(), 'financeiro', 'criar');

        try {
            $entrada = $this->service->concluir($entrada, [
                'estoque_id'        => (int) $request->input('estoque_id'),
                'atualizar_custo'   => $request->boolean('atualizar_custo'),
                'gerar_conta_pagar' => $gerarConta,
                'vencimento'        => $request->input('vencimento'),
                'itens'             => $request->input('itens', []),
            ], (int) auth()->id());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $msg = 'Entrada lançada no estoque.';
        if ($entrada->contas_pagar_geradas > 0) {
            $msg .= " {$entrada->contas_pagar_geradas} conta(s) a pagar criada(s).";
        }

        return redirect()->route('app.entradas-nfe.show', $entrada)->with('success', $msg);
    }

    /**
     * Devolução de compra ao fornecedor (24/11/2026, DK): tira do estoque e abate
     * as contas a pagar da nota. Não emite nota (o documento, se houver, é anotado).
     */
    public function devolver(Request $request, NFeEntrada $entrada)
    {
        $request->validate([
            'motivo'       => 'required|string|min:3|max:500',
            'documento'    => 'nullable|string|max:60',
            'quantidades'  => 'required|array',
            'quantidades.*' => 'nullable|string|max:20',
        ], [
            'motivo.required' => 'Escreva o motivo da devolução.',
            'motivo.min'      => 'Escreva o motivo da devolução.',
        ]);

        $svc = app(\App\Services\EntradaNFe\DevolucaoCompraService::class);
        // Mexer em conta a pagar é do financeiro: sem a permissão, só devolve nota sem conta em aberto
        if ($svc->contasEmAberto($entrada)->isNotEmpty() && ! CheckPermission::canUser(auth()->user(), 'financeiro', 'editar')) {
            return back()->withInput()->with('error', 'Esta nota tem contas a pagar em aberto e a devolução abate o valor delas. Peça a quem cuida do financeiro (dono ou financeiro) para registrar a devolução.');
        }

        try {
            $dev = $svc->devolver($entrada, (array) $request->input('quantidades', []), (string) $request->input('motivo'), $request->input('documento'), (int) auth()->id());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $f = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
        $msg = "Devolução nº {$dev->numero} registrada: {$f($dev->valor_total)} saíram do estoque.";
        if ((float) $dev->valor_abatido > 0) {
            $msg .= " Contas a pagar abatidas em {$f($dev->valor_abatido)}.";
        }
        if ((float) $dev->credito_fornecedor > 0) {
            $msg .= " {$f($dev->credito_fornecedor)} já estavam pagos: ficam como crédito com o fornecedor.";
        }

        return redirect()->route('app.entradas-nfe.show', $entrada)->with('success', $msg);
    }

    /** Descarta um rascunho (a concluída é histórico de estoque e não sai). */
    public function destroy(NFeEntrada $entrada)
    {
        if ($entrada->concluida()) {
            return back()->with('error', 'Entrada concluída não pode ser descartada — o estoque já foi lançado.');
        }
        $entrada->delete();

        return redirect()->route('app.entradas-nfe.index')->with('success', 'Rascunho descartado.');
    }

    private function abrirRascunho(string $xml)
    {
        try {
            $entrada = $this->service->criarRascunho($xml, (int) session('unidade_id'), auth()->id());
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('app.entradas-nfe.index')->with('error', $e->getMessage());
        }

        return redirect()->route('app.entradas-nfe.show', $entrada);
    }

    /** A nota é de outro CNPJ? Avisa (não trava: loja sem CNPJ próprio usa o da empresa). */
    private function avisoDestinatario(NFeEntrada $entrada): ?string
    {
        $dest = (string) $entrada->cnpj_destinatario;
        if ($dest === '') {
            return null;
        }
        $unidade = Unidade::withoutGlobalScopes()->find($entrada->unidade_id);
        $empresaCnpj = Empresa::withoutGlobalScopes()->where('id', $entrada->empresa_id)->value('cnpj');
        $nossos = array_filter([Cnpj::limpar($unidade?->cnpj), Cnpj::limpar($empresaCnpj)]);

        if ($nossos === [] || in_array($dest, $nossos, true)) {
            return null;
        }

        return 'Esta nota foi emitida para o documento ' . (strlen($dest) === 14 ? Cnpj::formatar($dest) : $dest)
            . ', que não é o desta loja nem o da empresa. Confira se é mesmo uma compra sua antes de lançar.';
    }
}
