<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ContaBancaria;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\LancamentoBancario;
use App\Models\Unidade;
use App\Models\CartaoCredito;
use App\Models\CentroCusto;
use App\Models\PlanoContas;
use App\Services\OpenFinance\ClassificadorLancamentos;
use App\Services\OpenFinance\ConciliadorSugestoes;
use App\Services\OpenFinance\OpenFinanceException;
use App\Services\OpenFinance\SincronizadorExtrato;
use App\Services\OpenFinance\TecnospeedClient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Bancos (Open Finance, 17/11/2026): contas bancárias ligadas ao banco e a conciliação por
 * sugestão — o sistema cruza o extrato com as contas a receber e a pagar e a pessoa confirma.
 *
 * Quem pode: empresa com Open Finance no plano ou com contas extras. Sem liberação aparece o
 * convite; com "não mostrar" marcado pelo admin, a tela nem existe para a empresa (404).
 */
class BancoController extends Controller
{
    public function __construct(
        private readonly TecnospeedClient $api,
        private readonly SincronizadorExtrato $sincronizador,
        private readonly ConciliadorSugestoes $conciliador,
        private readonly ClassificadorLancamentos $classificador,
    ) {}

    public function index(Request $request)
    {
        $empresa = $this->empresaVisivel();

        if (! $empresa->openFinanceLiberado()) {
            return view('app.bancos.convite');
        }

        $contas = ContaBancaria::where('empresa_id', $empresa->id)
            ->where('situacao', '!=', 'desconectada')
            ->with('unidade')->orderBy('apelido')->get();

        $filtro = in_array($request->query('ver'), ['conferir', 'sem', 'nobanco', 'feitos', 'ignorados'], true) ? $request->query('ver') : 'conferir';
        $contaId = (int) $request->query('conta') ?: null;
        $cartaoId = (int) $request->query('cartao') ?: null;
        // Entradas e saídas separadas: 'entradas' = crédito, 'saidas' = débito, vazio = tudo.
        $lado = in_array($request->query('lado'), ['entradas', 'saidas'], true) ? $request->query('lado') : null;
        $cartoes = CartaoCredito::where('empresa_id', $empresa->id)->whereIn('conta_bancaria_id', $contas->pluck('id'))->orderBy('final')->get();

        $base = LancamentoBancario::where('empresa_id', $empresa->id)
            ->when($contaId, fn ($q) => $q->where('conta_bancaria_id', $contaId)->whereNull('cartao_credito_id'))
            ->when($cartaoId, fn ($q) => $q->where('cartao_credito_id', $cartaoId));

        // Quantos há de cada lado na aba atual (para os botões Entradas / Saídas).
        $statusDaAba = ['conferir' => 'sugerido', 'sem' => 'pendente', 'nobanco' => 'no_banco', 'feitos' => 'conciliado', 'ignorados' => 'ignorado'][$filtro];
        $porLado = [
            'tudo' => (clone $base)->where('status', $statusDaAba)->count(),
            'entradas' => (clone $base)->where('status', $statusDaAba)->where('tipo', 'credito')->count(),
            'saidas' => (clone $base)->where('status', $statusDaAba)->where('tipo', 'debito')->count(),
        ];

        $base->when($lado, fn ($q) => $q->where('tipo', $lado === 'entradas' ? 'credito' : 'debito'));

        $totais = [
            'conferir' => (clone $base)->where('status', 'sugerido')->count(),
            'sem' => (clone $base)->where('status', 'pendente')->count(),
            'nobanco' => (clone $base)->where('status', 'no_banco')->count(),
            'feitos' => (clone $base)->where('status', 'conciliado')->count(),
            'ignorados' => (clone $base)->where('status', 'ignorado')->count(),
            'certos' => (clone $base)->where('status', 'sugerido')->where('sugestao_confianca', '>=', LancamentoBancario::CONFIANCA_ALTA)->count(),
        ];

        $status = $statusDaAba;

        $lancamentos = (clone $base)->where('status', $status)
            ->with([
                'contaBancaria', 'cartao', 'sugestaoFaturaCartao', 'faturaCartao',
                'sugestaoContaReceber.cliente', 'sugestaoContaPagar.fornecedor', 'sugestaoContaPagar.funcionario', 'sugestaoContaPagar.centroCusto',
                'contaReceber.cliente', 'contaPagar.fornecedor', 'contaPagar.funcionario', 'contaPagar.centroCusto',
            ])
            ->orderByDesc('data')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        // Para escolher à mão quando o sistema não achou: títulos em aberto de cada lado.
        $abertasReceber = $filtro === 'sem' ? ContaReceber::where('empresa_id', $empresa->id)
            ->whereIn('status', ['pendente', 'vencida'])
            ->where(fn ($q) => $q->whereNull('forma_pagamento')->orWhere('forma_pagamento', '!=', \App\Services\FiadoService::FORMA))
            ->with('cliente')->orderBy('vencimento')->limit(300)->get() : collect();
        $abertasPagar = $filtro === 'sem' ? ContaPagar::where('empresa_id', $empresa->id)
            ->whereIn('status', ['pendente', 'vencida'])
            ->with(['fornecedor', 'funcionario'])->orderBy('vencimento')->limit(300)->get() : collect();

        // Saída sem conta cadastrada: proposta de despesa já classificada (regra aprendida ou histórico).
        $propostas = [];
        if ($filtro === 'sem') {
            foreach ($lancamentos as $l) {
                if (! $l->ehCredito()) {
                    $propostas[$l->id] = $this->classificador->propor($l);
                }
            }
        }
        $conhecidos = $filtro === 'sem' ? $this->pendentesConhecidos($empresa->id, $contaId)->count() : 0;

        // Painel: o que falta fazer e quanto há nas contas (tudo da empresa, sem o filtro de conta/cartão).
        $tudo = LancamentoBancario::where('empresa_id', $empresa->id);
        $mes = [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];
        $comSaldo = $contas->filter(fn ($c) => $c->saldo !== null);
        $painel = [
            'saldo' => $comSaldo->isEmpty() ? null : (float) $comSaldo->sum(fn ($c) => (float) $c->saldo),
            'saldo_aplicado' => (float) $comSaldo->sum(fn ($c) => (float) $c->saldo_aplicado),
            'saldo_em' => $comSaldo->min('saldo_atualizado_em'),
            'confirmar_qtd' => (clone $tudo)->where('status', 'sugerido')->count(),
            'confirmar_valor' => (float) (clone $tudo)->where('status', 'sugerido')->sum('valor'),
            'sem_qtd' => (clone $tudo)->where('status', 'pendente')->count(),
            'sem_valor' => (float) (clone $tudo)->where('status', 'pendente')->sum('valor'),
            // Só a CONTA: compra de cartão não é dinheiro que saiu do banco naquele dia.
            'entradas_mes' => (float) (clone $tudo)->whereNull('cartao_credito_id')->where('tipo', 'credito')->where('status', '!=', 'removido')->whereBetween('data', $mes)->sum('valor'),
            'saidas_mes' => (float) (clone $tudo)->whereNull('cartao_credito_id')->where('tipo', 'debito')->where('status', '!=', 'removido')->whereBetween('data', $mes)->sum('valor'),
            'feitos_mes' => (clone $tudo)->where('status', 'conciliado')->whereBetween('data', $mes)->count(),
            'total_mes' => (clone $tudo)->whereNotIn('status', ['removido', 'ignorado', 'no_banco'])->whereBetween('data', $mes)->count(),
        ];

        return view('app.bancos.index', [
            'painel' => $painel,
            'propostas' => $propostas,
            'conhecidos' => $conhecidos,
            'planoContas' => $filtro === 'sem' ? PlanoContas::where('empresa_id', $empresa->id)->analiticas()->ativas()
                ->whereIn('tipo', ['despesa', 'custo'])->orderBy('codigo')->get(['id', 'codigo', 'nome']) : collect(),
            'centrosCusto' => $filtro === 'sem' ? CentroCusto::where('empresa_id', $empresa->id)->where('ativo', true)
                ->orderBy('nome')->get(['id', 'codigo', 'nome']) : collect(),
            'empresa' => $empresa,
            'contas' => $contas,
            'limite' => $empresa->openFinanceLimiteContas(),
            'usadas' => $empresa->openFinanceContasUsadas(),
            'lancamentos' => $lancamentos,
            'totais' => $totais,
            'filtro' => $filtro,
            'contaId' => $contaId,
            'cartaoId' => $cartaoId,
            'lado' => $lado,
            'porLado' => $porLado,
            'cartoes' => $cartoes,
            'abertasReceber' => $abertasReceber,
            'abertasPagar' => $abertasPagar,
            'unidades' => Unidade::where('empresa_id', $empresa->id)->orderBy('nome')->get(),
            'bancos' => ContaBancaria::BANCOS,
        ]);
    }

    /** Cadastra a conta e já pede o link para o dono autorizar no banco. */
    public function store(Request $request)
    {
        $empresa = $this->empresaLiberada();

        $request->merge([
            'titular_documento' => preg_replace('/\D/', '', (string) $request->input('titular_documento')),
            'banco_codigo' => ($b = preg_replace('/\D/', '', (string) $request->input('banco_codigo'))) === '' ? '' : str_pad($b, 3, '0', STR_PAD_LEFT),
        ]);

        $dados = $request->validate([
            'apelido' => 'required|string|max:80',
            'banco_codigo' => 'required|digits:3',
            'agencia' => 'required|string|max:10|regex:/^[0-9]+$/',
            'agencia_digito' => 'nullable|string|max:2',
            'conta' => 'required|string|max:12|regex:/^[0-9]+$/',
            'conta_digito' => 'nullable|string|max:2',
            'titular_documento' => ['required', 'regex:/^(\d{11}|\d{14})$/'],
            'titular_nome' => 'nullable|string|max:120',
            'unidade_id' => ['nullable', Rule::exists('unidades', 'id')->where('empresa_id', $empresa->id)],
        ], [
            'titular_documento.regex' => 'Informe o CPF (11 números) ou o CNPJ (14 números) do dono da conta.',
            'banco_codigo.required' => 'Escolha o banco na lista ou digite os 3 números dele.',
            'banco_codigo.digits' => 'Escolha o banco na lista ou digite os 3 números dele.',
            'agencia.regex' => 'Na agência, só números (o dígito vai no campo ao lado).',
            'conta.regex' => 'Na conta, só números (o dígito vai no campo ao lado).',
        ]);

        if (! $empresa->openFinanceTemVaga()) {
            return back()->withInput()->with('error', 'Você já conectou todas as contas do seu plano (' . $empresa->openFinanceLimiteContas() . '). Para ligar mais uma, fale com a IA365.');
        }

        $repetida = ContaBancaria::where('empresa_id', $empresa->id)->where('situacao', '!=', 'desconectada')
            ->where('banco_codigo', $dados['banco_codigo'])->where('agencia', $dados['agencia'])->where('conta', $dados['conta'])->exists();
        if ($repetida) {
            return back()->withInput()->with('error', 'Esta conta já está cadastrada.');
        }

        try {
            $remota = $this->api->criarConta($dados);
        } catch (OpenFinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if (empty($remota['accountHash'])) {
            return back()->withInput()->with('error', 'O banco não devolveu o cadastro da conta. Tente de novo em instantes.');
        }

        ContaBancaria::create($dados + [
            'empresa_id' => $empresa->id,
            'banco_nome' => ContaBancaria::BANCOS[$dados['banco_codigo']] ?? null,
            'account_hash' => $remota['accountHash'],
            'link_conexao' => $remota['openfinanceLink'] ?? null,
            'status_banco' => $remota['statusOpenfinance'] ?? null,
            'situacao' => 'aguardando',
            'ultimo_erro' => $remota['openfinanceLinkUnavailableReason'] ?? null,
        ]);

        return redirect()->route('app.bancos.index')
            ->with('success', 'Conta cadastrada. Agora clique em "Conectar ao banco" e autorize — depois disso o extrato começa a chegar (pode levar até 24 horas).');
    }

    /** Confere se o dono já autorizou e, se já, busca o extrato agora. */
    public function atualizar(ContaBancaria $conta)
    {
        $this->empresaLiberada();
        $this->daEmpresa($conta->empresa_id);

        $resultado = $this->sincronizador->sincronizar($conta->fresh());
        if ($conta->fresh()->protocolo_pendente) {
            // O banco costuma responder em segundos: tenta buscar já.
            $resultado = $this->sincronizador->sincronizar($conta->fresh());
        }

        return back()->with(str_starts_with($resultado, 'erro') ? 'error' : 'success', 'Banco: ' . $resultado . '.');
    }

    /** Desliga a conta do banco: libera a vaga e tira a conta da fatura. Os lançamentos ficam. */
    public function desconectar(ContaBancaria $conta)
    {
        $this->empresaVisivel();
        $this->daEmpresa($conta->empresa_id);

        try {
            if ($conta->account_hash) {
                $this->api->removerConta($conta->account_hash);
            }
        } catch (OpenFinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        $conta->update([
            'situacao' => 'desconectada',
            'desconectada_em' => now(),
            'account_hash' => null,
            'link_conexao' => null,
            'protocolo_pendente' => null,
        ]);

        return back()->with('success', 'Conta desconectada. A vaga ficou livre e o que já foi conciliado continua como está.');
    }

    /** Pergunta ao banco quais cartões de crédito a conta tem. */
    public function buscarCartoes(ContaBancaria $conta)
    {
        $this->empresaLiberada();
        $this->daEmpresa($conta->empresa_id);
        abort_unless($conta->situacao === 'conectada', 422, 'Conecte a conta ao banco antes de buscar os cartões.');

        try {
            $novos = $this->sincronizador->buscarCartoes($conta);
        } catch (OpenFinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        $total = $conta->cartoes()->count();

        return back()->with('success', $total === 0
            ? 'O banco não informou nenhum cartão de crédito nesta conta.'
            : "{$total} cartão(ões) nesta conta" . ($novos ? " ({$novos} novo(s))" : '') . '. Ligue os que você quer acompanhar.');
    }

    /** Liga ou desliga a importação das compras de um cartão. Cartão não conta como conta extra. */
    public function alternarCartao(CartaoCredito $cartao)
    {
        $this->empresaLiberada();
        $this->daEmpresa($cartao->empresa_id);

        $cartao->update(['importar' => ! $cartao->importar]);

        return back()->with('success', $cartao->importar
            ? $cartao->nome() . ': as compras passam a entrar aqui. Clique em "Buscar extrato" na conta para trazer agora.'
            : $cartao->nome() . ': parou de trazer compras. O que já entrou continua aqui.');
    }

    /** "Esta saída da conta é o pagamento da fatura do cartão X" — não é despesa nova. */
    public function marcarFatura(Request $request, LancamentoBancario $lancamento)
    {
        $empresa = $this->empresaLiberada();
        $this->daEmpresa($lancamento->empresa_id);

        $dados = $request->validate([
            'cartao_id' => ['required', Rule::exists('cartoes_credito', 'id')->where('empresa_id', $empresa->id)],
        ]);

        try {
            $this->conciliador->marcarPagamentoDeFatura($lancamento, auth()->id(), (int) $dados['cartao_id']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Marcado como pagamento de fatura. Não entra como despesa: as compras do cartão já entram uma a uma.');
    }

    public function confirmar(Request $request, LancamentoBancario $lancamento)
    {
        $this->empresaLiberada();
        $this->daEmpresa($lancamento->empresa_id);

        $dados = $request->validate(['titulo_id' => 'nullable|integer']);

        try {
            $this->conciliador->confirmar($lancamento, auth()->id(), $dados['titulo_id'] ?? null);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $lancamento->refresh();
        if ($lancamento->fatura_cartao_id) {
            return back()->with('success', 'Marcado como pagamento de fatura. Não entra como despesa: as compras do cartão já entram uma a uma.');
        }

        // Aprende a classificação da conta confirmada, para propor igual na próxima saída deste favorecido.
        if (! $lancamento->ehCredito() && $lancamento->contaPagar) {
            $this->classificador->aprender($lancamento, $lancamento->contaPagar);
        }

        return back()->with('success', 'Conciliado: a conta foi baixada com a data e o valor do banco.');
    }

    /** Saída do banco sem conta cadastrada: cria a despesa já paga, classificada, e concilia. */
    public function lancar(Request $request, LancamentoBancario $lancamento)
    {
        $empresa = $this->empresaLiberada();
        $this->daEmpresa($lancamento->empresa_id);

        $dados = $request->validate([
            'descricao' => 'nullable|string|max:250',
            'plano_conta_id' => ['nullable', Rule::exists('plano_contas', 'id')->where('empresa_id', $empresa->id)],
            'centro_custo_id' => ['nullable', Rule::exists('centros_custo', 'id')->where('empresa_id', $empresa->id)],
            'lembrar' => 'nullable|boolean',
            'usar_proposta' => 'nullable|boolean',
            // cadastrar na hora, sem sair da tela
            'plano_conta_novo' => 'nullable|string|min:2|max:100',
            'centro_custo_novo' => 'nullable|string|min:2|max:100',
            'fornecedor_novo' => 'nullable|string|min:2|max:250',
            // 22/11/2026: escolher um fornecedor já cadastrado (só da própria empresa)
            'fornecedor_id' => ['nullable', Rule::exists('fornecedores', 'id')->where('empresa_id', $empresa->id)],
        ], [
            'fornecedor_id.exists' => 'Escolha um fornecedor da lista.',
            'fornecedor_novo.min' => 'Dê um nome para o fornecedor (ao menos 2 letras).',
            'plano_conta_novo.min' => 'Dê um nome para a conta nova (ao menos 2 letras).',
            'centro_custo_novo.min' => 'Dê um nome para o centro de custo novo (ao menos 2 letras).',
        ]);

        if (! $request->boolean('usar_proposta') && in_array($lancamento->status, ['pendente', 'sugerido'], true) && ! $lancamento->ehCredito()) {
            if (filled($dados['plano_conta_novo'] ?? null)) {
                $dados['plano_conta_id'] = $this->classificador->contaDoPlanoPeloNome($empresa->id, $dados['plano_conta_novo'])->id;
            }
            if (filled($dados['centro_custo_novo'] ?? null)) {
                $dados['centro_custo_id'] = $this->classificador->centroDeCustoPeloNome($empresa->id, $dados['centro_custo_novo'])->id;
            }
            if (filled($dados['fornecedor_novo'] ?? null) && empty($dados['fornecedor_id'])) {
                $dados['fornecedor_id'] =$this->classificador->fornecedorSimples($lancamento, $dados['fornecedor_novo'])->id;
            }
        }

        // Um clique: vale a proposta do sistema (regra já aprendida).
        if ($request->boolean('usar_proposta')) {
            $p = $this->classificador->propor($lancamento);
            $dados = ['descricao' => $p['descricao'], 'plano_conta_id' => $p['plano_conta_id'], 'centro_custo_id' => $p['centro_custo_id'],
                'fornecedor_id' => $p['fornecedor_id'], 'favorecido_nome' => $p['favorecido']];
        }
        $dados['lembrar'] = $request->has('lembrar') ? $request->boolean('lembrar') : true;

        try {
            $this->classificador->lancar($lancamento, auth()->id(), $dados, session('unidade_id'));
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Despesa lançada e conciliada com a data e o valor do banco.');
    }

    /** Lança de uma vez todas as saídas cuja classificação o sistema já aprendeu. */
    public function lancarConhecidos(Request $request)
    {
        $empresa = $this->empresaLiberada();

        $feitos = 0;
        foreach ($this->pendentesConhecidos($empresa->id, (int) $request->input('conta') ?: null) as [$l, $p]) {
            try {
                $this->classificador->lancar($l, auth()->id(), [
                    'descricao' => $p['descricao'], 'plano_conta_id' => $p['plano_conta_id'], 'centro_custo_id' => $p['centro_custo_id'],
                    'fornecedor_id' => $p['fornecedor_id'], 'favorecido_nome' => $p['favorecido'],
                ], session('unidade_id'));
                $feitos++;
            } catch (\DomainException) {
                // tratado por outro caminho nesse meio tempo
            }
        }

        return back()->with('success', "{$feitos} despesa(s) lançada(s) com a classificação já conhecida.");
    }

    /** Saídas pendentes para as quais existe regra aprendida. @return \Illuminate\Support\Collection<int, array{0: LancamentoBancario, 1: array}> */
    private function pendentesConhecidos(int $empresaId, ?int $contaId)
    {
        return LancamentoBancario::where('empresa_id', $empresaId)
            ->where('status', 'pendente')->where('tipo', 'debito')
            ->when($contaId, fn ($q) => $q->where('conta_bancaria_id', $contaId))
            ->orderBy('data')->limit(500)->get()
            ->map(fn (LancamentoBancario $l) => [$l, $this->classificador->propor($l)])
            ->filter(fn ($par) => $par[1]['origem'] === 'regra')
            ->values();
    }

    /** Confirma de uma vez tudo o que o sistema tem como certo (nota alta). */
    public function confirmarCertos(Request $request)
    {
        $empresa = $this->empresaLiberada();

        $feitos = 0;
        LancamentoBancario::where('empresa_id', $empresa->id)
            ->where('status', 'sugerido')
            ->where('sugestao_confianca', '>=', LancamentoBancario::CONFIANCA_ALTA)
            ->when((int) $request->input('conta'), fn ($q, $id) => $q->where('conta_bancaria_id', $id))
            ->when(in_array($request->input('lado'), ['entradas', 'saidas'], true), fn ($q) => $q->where('tipo', $request->input('lado') === 'entradas' ? 'credito' : 'debito'))
            ->orderBy('data')->get()
            ->each(function (LancamentoBancario $l) use (&$feitos) {
                try {
                    $this->conciliador->confirmar($l, auth()->id());
                    $feitos++;
                    $l->refresh();
                    if (! $l->ehCredito() && $l->contaPagar) {
                        $this->classificador->aprender($l, $l->contaPagar);
                    }
                } catch (\DomainException) {
                    // título baixado por outro caminho nesse meio tempo: fica para conferir à mão
                }
            });

        return back()->with('success', "{$feitos} lançamento(s) conciliado(s).");
    }

    /** "Não é isso": tira a sugestão e deixa o lançamento para escolher à mão. */
    public function recusar(LancamentoBancario $lancamento)
    {
        $this->empresaLiberada();
        $this->daEmpresa($lancamento->empresa_id);

        if ($lancamento->status === 'sugerido') {
            $lancamento->update([
                'status' => 'pendente', 'sugestao_conta_receber_id' => null, 'sugestao_conta_pagar_id' => null,
                'sugestao_fatura_cartao_id' => null, 'sugestao_confianca' => null, 'sugestao_motivo' => null,
            ]);
        }

        return back()->with('success', 'Sugestão retirada. O lançamento ficou em "Sem sugestão" para você escolher a conta.');
    }

    /** Lançamento que não é conta a pagar nem a receber (transferência entre contas próprias, por exemplo). */
    public function ignorar(LancamentoBancario $lancamento)
    {
        $this->empresaLiberada();
        $this->daEmpresa($lancamento->empresa_id);

        if (in_array($lancamento->status, ['pendente', 'sugerido'], true)) {
            $lancamento->update([
                'status' => 'ignorado', 'sugestao_conta_receber_id' => null, 'sugestao_conta_pagar_id' => null,
                'sugestao_fatura_cartao_id' => null, 'sugestao_confianca' => null, 'sugestao_motivo' => null,
            ]);
        }

        return back()->with('success', 'Lançamento ignorado.');
    }

    /** Desfaz conciliação ou volta um ignorado para a fila. */
    public function desfazer(LancamentoBancario $lancamento)
    {
        $this->empresaLiberada();
        $this->daEmpresa($lancamento->empresa_id);

        if ($lancamento->status === 'ignorado') {
            $lancamento->update(['status' => 'pendente']);
            $this->conciliador->sugerir($lancamento->fresh());

            return back()->with('success', 'Lançamento voltou para a fila.');
        }

        try {
            $this->conciliador->desfazer($lancamento);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Conciliação desfeita: a conta voltou a ficar em aberto.');
    }

    private function empresaVisivel()
    {
        $empresa = auth()->user()->empresa;
        abort_unless($empresa && $empresa->openFinanceVisivel(), 404);

        return $empresa;
    }

    private function empresaLiberada()
    {
        $empresa = $this->empresaVisivel();
        abort_unless($empresa->openFinanceLiberado(), 403, 'Sua empresa ainda não tem a ligação com o banco liberada.');

        return $empresa;
    }

    private function daEmpresa(int $empresaId): void
    {
        abort_unless($empresaId === (int) auth()->user()->empresa_id, 404);
    }
}
