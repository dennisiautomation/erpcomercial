<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ConciliacaoBancaria;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\ExtratoBancario;
use App\Services\OFXParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConciliacaoBancariaController extends Controller
{
    public function index()
    {
        $empresaId = auth()->user()->empresa_id;

        $conciliacoes = ConciliacaoBancaria::where('empresa_id', $empresaId)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('app.conciliacao.index', compact('conciliacoes'));
    }

    public function create()
    {
        return view('app.conciliacao.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'arquivo_ofx' => 'required|file|max:5120',
            'banco' => 'required|string|max:100',
        ]);

        $content = file_get_contents($request->file('arquivo_ofx')->getRealPath());

        $parser = new OFXParser();
        $data = $parser->parse($content);

        $empresaId = auth()->user()->empresa_id;

        $transacoes = $data['transacoes'];

        if (empty($transacoes)) {
            return back()->with('error', 'Nenhuma transacao encontrada no arquivo OFX.');
        }

        // Determine period
        $datas = array_filter(array_column($transacoes, 'data'));
        sort($datas);

        $conciliacao = ConciliacaoBancaria::create([
            'empresa_id' => $empresaId,
            'unidade_id' => session('unidade_id'),
            'banco' => $request->banco,
            'agencia' => $data['agencia'],
            'conta' => $data['conta'],
            'periodo_inicio' => $datas[0] ?? now()->toDateString(),
            'periodo_fim' => end($datas) ?: now()->toDateString(),
            'saldo_inicial' => 0,
            'saldo_final' => $data['saldo'] ?? 0,
            'total_lancamentos' => count($transacoes),
            'conciliados' => 0,
            'status' => 'pendente',
        ]);

        // Create extratos
        foreach ($transacoes as $transacao) {
            ExtratoBancario::create([
                'conciliacao_id' => $conciliacao->id,
                'data' => $transacao['data'],
                'descricao' => $transacao['descricao'],
                'valor' => $transacao['valor'],
                'tipo' => $transacao['tipo'],
                'documento' => $transacao['documento'],
                'conciliado' => false,
            ]);
        }

        return redirect()->route('app.conciliacao.show', $conciliacao)
            ->with('success', 'Arquivo OFX importado com sucesso! ' . count($transacoes) . ' transacoes encontradas.');
    }

    public function show(ConciliacaoBancaria $conciliacao)
    {
        $extratos = $conciliacao->extratos()->orderBy('data')->get();

        $empresaId = auth()->user()->empresa_id;

        // Get pending contas a receber and pagar for matching
        $contasReceber = ContaReceber::where('empresa_id', $empresaId)
            ->where('status', 'pendente')
            ->orderBy('vencimento')
            ->get();

        $contasPagar = ContaPagar::where('empresa_id', $empresaId)
            ->where('status', 'pendente')
            ->orderBy('vencimento')
            ->get();

        return view('app.conciliacao.show', compact('conciliacao', 'extratos', 'contasReceber', 'contasPagar'));
    }

    public function conciliar(Request $request, ExtratoBancario $extrato)
    {
        $validated = $request->validate([
            'conta_receber_id' => 'nullable|exists:contas_receber,id',
            'conta_pagar_id' => 'nullable|exists:contas_pagar,id',
        ]);

        if (!$validated['conta_receber_id'] && !$validated['conta_pagar_id']) {
            return back()->with('error', 'Selecione uma conta para conciliar.');
        }

        $extrato->update([
            'conta_receber_id' => $validated['conta_receber_id'] ?? null,
            'conta_pagar_id' => $validated['conta_pagar_id'] ?? null,
            'conciliado' => true,
        ]);

        // Update conciliation count
        $conciliacao = $extrato->conciliacao;
        $conciliacao->update([
            'conciliados' => $conciliacao->extratos()->where('conciliado', true)->count(),
            'status' => 'em_andamento',
        ]);

        return back()->with('success', 'Extrato conciliado com sucesso!');
    }

    public function conciliarAutomatico(ConciliacaoBancaria $conciliacao)
    {
        $empresaId = auth()->user()->empresa_id;
        $matched = 0;

        DB::transaction(function () use ($conciliacao, $empresaId, &$matched) {
            $extratos = $conciliacao->extratos()->where('conciliado', false)->get();

            foreach ($extratos as $extrato) {
                if ($extrato->tipo === 'credito') {
                    // Try to match with contas a receber
                    $conta = ContaReceber::where('empresa_id', $empresaId)
                        ->where('status', 'pendente')
                        ->where('valor', $extrato->valor)
                        ->whereBetween('vencimento', [
                            $extrato->data->copy()->subDays(5),
                            $extrato->data->copy()->addDays(5),
                        ])
                        ->whereDoesntHave('extratos')
                        ->first();

                    if ($conta) {
                        $extrato->update([
                            'conta_receber_id' => $conta->id,
                            'conciliado' => true,
                        ]);
                        $matched++;
                    }
                } else {
                    // Try to match with contas a pagar
                    $conta = ContaPagar::where('empresa_id', $empresaId)
                        ->where('status', 'pendente')
                        ->where('valor', $extrato->valor)
                        ->whereBetween('vencimento', [
                            $extrato->data->copy()->subDays(5),
                            $extrato->data->copy()->addDays(5),
                        ])
                        ->whereDoesntHave('extratos')
                        ->first();

                    if ($conta) {
                        $extrato->update([
                            'conta_pagar_id' => $conta->id,
                            'conciliado' => true,
                        ]);
                        $matched++;
                    }
                }
            }

            $conciliacao->update([
                'conciliados' => $conciliacao->extratos()->where('conciliado', true)->count(),
                'status' => 'em_andamento',
            ]);
        });

        return back()->with('success', "Conciliacao automatica finalizada! {$matched} lancamentos conciliados.");
    }

    public function finalizar(ConciliacaoBancaria $conciliacao)
    {
        $conciliacao->update(['status' => 'concluida']);

        return back()->with('success', 'Conciliacao finalizada com sucesso!');
    }
}
