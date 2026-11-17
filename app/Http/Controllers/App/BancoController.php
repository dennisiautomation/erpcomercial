<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ContaBancaria;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\LancamentoBancario;
use App\Models\Unidade;
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

        $filtro = in_array($request->query('ver'), ['conferir', 'sem', 'feitos', 'ignorados'], true) ? $request->query('ver') : 'conferir';
        $contaId = (int) $request->query('conta') ?: null;

        $base = LancamentoBancario::where('empresa_id', $empresa->id)
            ->when($contaId, fn ($q) => $q->where('conta_bancaria_id', $contaId));

        $totais = [
            'conferir' => (clone $base)->where('status', 'sugerido')->count(),
            'sem' => (clone $base)->where('status', 'pendente')->count(),
            'feitos' => (clone $base)->where('status', 'conciliado')->count(),
            'ignorados' => (clone $base)->where('status', 'ignorado')->count(),
            'certos' => (clone $base)->where('status', 'sugerido')->where('sugestao_confianca', '>=', LancamentoBancario::CONFIANCA_ALTA)->count(),
        ];

        $status = ['conferir' => 'sugerido', 'sem' => 'pendente', 'feitos' => 'conciliado', 'ignorados' => 'ignorado'][$filtro];

        $lancamentos = (clone $base)->where('status', $status)
            ->with([
                'contaBancaria',
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

        return view('app.bancos.index', [
            'empresa' => $empresa,
            'contas' => $contas,
            'limite' => $empresa->openFinanceLimiteContas(),
            'usadas' => $empresa->openFinanceContasUsadas(),
            'lancamentos' => $lancamentos,
            'totais' => $totais,
            'filtro' => $filtro,
            'contaId' => $contaId,
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
            'banco_codigo' => str_pad(preg_replace('/\D/', '', (string) $request->input('banco_codigo')), 3, '0', STR_PAD_LEFT),
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

        return back()->with('success', 'Conciliado: a conta foi baixada com a data e o valor do banco.');
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
            ->orderBy('data')->get()
            ->each(function (LancamentoBancario $l) use (&$feitos) {
                try {
                    $this->conciliador->confirmar($l, auth()->id());
                    $feitos++;
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
                'sugestao_confianca' => null, 'sugestao_motivo' => null,
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
                'sugestao_confianca' => null, 'sugestao_motivo' => null,
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
