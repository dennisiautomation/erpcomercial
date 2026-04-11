<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ConfiguracaoFiscal;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\Produto;
use App\Models\Venda;
use App\Models\VendaItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\NotificacaoService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $wizardDismissed = session('wizard_dismissed', false);
        $inicioMes = Carbon::now()->startOfMonth();
        $fimMes = Carbon::now()->endOfMonth();
        $unidadeId = session('unidade_id');
        $empresaId = auth()->user()->empresa_id;

        // Setup progress — 7 etapas cobrindo tudo que um negócio precisa
        $empresa = Empresa::find($empresaId);
        $temProdutos = Produto::where('empresa_id', $empresaId)->count();
        $temClientes = Cliente::where('empresa_id', $empresaId)->count();
        $temFornecedores = \App\Models\Fornecedor::where('empresa_id', $empresaId)->count();
        $temFuncionarios = \App\Models\User::where('empresa_id', $empresaId)->where('perfil', '!=', 'dono')->count();
        $temVendas = Venda::where('empresa_id', $empresaId)->exists();
        $configFiscal = ConfiguracaoFiscal::withoutGlobalScopes()->where('empresa_id', $empresaId)->exists();
        $temEstoque = \App\Models\EstoqueMovimentacao::where('empresa_id', $empresaId)->exists();

        $setupCompleto = [
            'produtos' => ['done' => $temProdutos >= 3, 'count' => $temProdutos, 'label' => 'Cadastrar produtos', 'desc' => 'Cadastre pelo menos 3 produtos ou importe via CSV', 'icon' => 'box-seam', 'action' => route('app.produtos.create'), 'action_label' => 'Cadastrar', 'import' => route('app.import.produtos')],
            'clientes' => ['done' => $temClientes >= 1, 'count' => $temClientes, 'label' => 'Cadastrar clientes', 'desc' => 'Cadastre seus clientes ou importe sua base', 'icon' => 'people', 'action' => route('app.clientes.create'), 'action_label' => 'Cadastrar', 'import' => route('app.import.clientes')],
            'fornecedores' => ['done' => $temFornecedores >= 1, 'count' => $temFornecedores, 'label' => 'Cadastrar fornecedores', 'desc' => 'Adicione seus fornecedores para controle de compras', 'icon' => 'truck', 'action' => route('app.fornecedores.create'), 'action_label' => 'Cadastrar', 'import' => route('app.import.fornecedores')],
            'funcionarios' => ['done' => $temFuncionarios >= 1, 'count' => $temFuncionarios, 'label' => 'Cadastrar equipe', 'desc' => 'Adicione vendedores, caixas e gerentes', 'icon' => 'person-badge', 'action' => route('app.funcionarios.create'), 'action_label' => 'Cadastrar'],
            'estoque' => ['done' => $temEstoque, 'label' => 'Dar entrada no estoque', 'desc' => 'Registre a quantidade inicial dos seus produtos', 'icon' => 'archive', 'action' => route('app.movimentacoes.create'), 'action_label' => 'Registrar entrada'],
            'fiscal' => ['done' => $configFiscal, 'label' => 'Configurar fiscal', 'desc' => 'Configure emissão de notas fiscais (opcional)', 'icon' => 'receipt', 'action' => route('app.configuracao-fiscal.edit'), 'action_label' => 'Configurar', 'optional' => true],
            'primeira_venda' => ['done' => $temVendas, 'label' => 'Fazer primeira venda', 'desc' => 'Registre sua primeira venda pelo PDV ou balcão', 'icon' => 'cart-check', 'action' => route('app.pdv.index'), 'action_label' => 'Abrir PDV', 'action2' => route('app.vendas.create'), 'action2_label' => 'Venda balcão'],
        ];
        $totalEtapas = count($setupCompleto);
        $etapasConcluidas = collect($setupCompleto)->filter(fn($s) => $s['done'])->count();
        $setupPercentual = (int)($etapasConcluidas / $totalEtapas * 100);

        // Alertas
        $estoqueBaixo = Produto::where('empresa_id', $empresaId)->whereColumn('estoque_minimo', '>', DB::raw('0'))->count();
        $contasVencidas = ContaReceber::where('empresa_id', $empresaId)->where('status', 'pendente')->where('vencimento', '<', now())->count();

        $empresa = Empresa::find($empresaId);
        $trialDias = $empresa ? $empresa->diasRestantesTrial() : 0;

        // Faturamento do mes
        $faturamentoMes = Venda::where('unidade_id', $unidadeId)
            ->whereBetween('created_at', [$inicioMes, $fimMes])
            ->where('status', 'concluida')
            ->sum('total');

        // Faturamento do mes anterior (para comparacao)
        $inicioMesAnterior = Carbon::now()->subMonth()->startOfMonth();
        $fimMesAnterior = Carbon::now()->subMonth()->endOfMonth();
        $faturamentoMesAnterior = Venda::where('unidade_id', $unidadeId)
            ->whereBetween('created_at', [$inicioMesAnterior, $fimMesAnterior])
            ->where('status', 'concluida')
            ->sum('total');

        // Variacao percentual
        $variacaoFaturamento = $faturamentoMesAnterior > 0
            ? round((($faturamentoMes - $faturamentoMesAnterior) / $faturamentoMesAnterior) * 100, 1)
            : 0;

        // Total de vendas no mes
        $totalVendasMes = Venda::where('unidade_id', $unidadeId)
            ->whereBetween('created_at', [$inicioMes, $fimMes])
            ->where('status', 'concluida')
            ->count();

        $totalVendasMesAnterior = Venda::where('unidade_id', $unidadeId)
            ->whereBetween('created_at', [$inicioMesAnterior, $fimMesAnterior])
            ->where('status', 'concluida')
            ->count();

        $variacaoVendas = $totalVendasMesAnterior > 0
            ? round((($totalVendasMes - $totalVendasMesAnterior) / $totalVendasMesAnterior) * 100, 1)
            : 0;

        // Ticket medio
        $ticketMedio = $totalVendasMes > 0 ? $faturamentoMes / $totalVendasMes : 0;

        // Total de clientes ativos
        $totalClientes = Cliente::where('status', 'ativo')->count();

        // Contas a receber vencidas (inadimplencia)
        $inadimplencia = ContaReceber::where('unidade_id', $unidadeId)
            ->where('status', 'pendente')
            ->where('vencimento', '<', Carbon::today())
            ->sum('valor');

        // Contas a pagar vencidas
        $contasPagarVencidas = ContaPagar::where('unidade_id', $unidadeId)
            ->where('status', 'pendente')
            ->where('vencimento', '<', Carbon::today())
            ->sum('valor');

        // Top 5 produtos vendidos no mes
        $topProdutos = VendaItem::whereHas('venda', function ($q) use ($unidadeId, $inicioMes, $fimMes) {
                $q->where('unidade_id', $unidadeId)
                  ->whereBetween('created_at', [$inicioMes, $fimMes])
                  ->where('status', 'concluida');
            })
            ->whereNotNull('produto_id')
            ->selectRaw('produto_id, SUM(quantidade) as total_quantidade, SUM(total) as total_valor')
            ->groupBy('produto_id')
            ->orderByDesc('total_quantidade')
            ->with('produto:id,descricao')
            ->limit(5)
            ->get();

        // Vendas por dia do mes (para grafico)
        $vendasPorDia = Venda::where('unidade_id', $unidadeId)
            ->whereBetween('created_at', [$inicioMes, $fimMes])
            ->where('status', 'concluida')
            ->selectRaw('DATE(created_at) as dia, SUM(total) as total_dia, COUNT(*) as qtd')
            ->groupBy('dia')
            ->orderBy('dia')
            ->get();

        // Ultimas 10 vendas
        $ultimasVendas = Venda::where('unidade_id', $unidadeId)
            ->with(['cliente:id,nome_razao_social', 'vendedor:id,name'])
            ->latest()
            ->limit(10)
            ->get();

        // Gerar alertas de notificação automaticamente
        try {
            NotificacaoService::gerarAlertas(auth()->id(), $empresaId);
        } catch (\Throwable $e) {
            // Silenciar erros de notificação para não quebrar o dashboard
        }

        return view('app.dashboard', compact(
            'faturamentoMes',
            'variacaoFaturamento',
            'totalVendasMes',
            'variacaoVendas',
            'ticketMedio',
            'totalClientes',
            'inadimplencia',
            'contasPagarVencidas',
            'topProdutos',
            'vendasPorDia',
            'ultimasVendas',
            'setupCompleto',
            'setupPercentual',
            'totalEtapas',
            'etapasConcluidas',
            'estoqueBaixo',
            'contasVencidas',
            'trialDias',
            'wizardDismissed',
        ));
    }
}
