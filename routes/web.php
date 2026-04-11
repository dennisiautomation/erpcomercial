<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UnidadeSelecaoController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\App;
use Illuminate\Support\Facades\Route;

/* ------------------------------------------------------------------ */
/*  Public                                                             */
/* ------------------------------------------------------------------ */

Route::get('/', fn () => redirect()->route('login'));

/* ------------------------------------------------------------------ */
/*  Auth                                                               */
/* ------------------------------------------------------------------ */

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/* ------------------------------------------------------------------ */
/*  Selecao de Unidade (pos-login, pre-sistema)                       */
/* ------------------------------------------------------------------ */

Route::middleware('auth')->group(function () {
    Route::get('/selecionar-unidade', [UnidadeSelecaoController::class, 'index'])
        ->name('selecionar-unidade');
    Route::post('/selecionar-unidade', [UnidadeSelecaoController::class, 'selecionar'])
        ->name('selecionar-unidade.store');
});

/* ------------------------------------------------------------------ */
/*  Admin (usuarios is_admin)                                         */
/* ------------------------------------------------------------------ */

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::resource('empresas', Admin\EmpresaController::class);
    Route::resource('empresas.unidades', Admin\UnidadeController::class)->shallow();
    Route::resource('usuarios', Admin\UsuarioController::class);
});

/* ------------------------------------------------------------------ */
/*  App (usuarios empresa, unidade selecionada)                       */
/* ------------------------------------------------------------------ */

Route::middleware(['auth', 'unidade'])->prefix('app')->name('app.')->group(function () {

    Route::get('/dashboard', [App\DashboardController::class, 'index'])->name('dashboard');

    /* ------ Cadastros ------ */
    Route::resource('clientes', App\ClienteController::class)->middleware('permission:clientes');
    Route::resource('produtos', App\ProdutoController::class)->middleware('permission:produtos');
    Route::resource('fornecedores', App\FornecedorController::class)->middleware('permission:produtos');
    Route::resource('categorias', App\CategoriaController::class)->middleware('permission:produtos');
    Route::resource('servicos', App\ServicoController::class)->middleware('permission:produtos');
    Route::resource('funcionarios', App\FuncionarioController::class)->middleware('permission:funcionarios');

    /* ------ Vendas ------ */
    Route::resource('orcamentos', App\OrcamentoController::class)->middleware('permission:orcamentos');
    Route::post('orcamentos/{orcamento}/converter', [App\OrcamentoController::class, 'converter'])
        ->name('orcamentos.converter')
        ->middleware('permission:orcamentos');
    Route::resource('pedidos', App\PedidoController::class)->middleware('permission:pedidos');
    Route::resource('vendas', App\VendaController::class)->middleware('permission:vendas');

    /* ------ PDV ------ */
    Route::get('/pdv', [App\PdvController::class, 'index'])
        ->name('pdv.index')
        ->middleware('permission:vendas,criar');
    Route::post('/pdv/venda', [App\PdvController::class, 'registrarVenda'])
        ->name('pdv.venda')
        ->middleware('permission:vendas,criar');
    Route::get('/pdv/produto/{codigo}', [App\PdvController::class, 'buscarProduto'])
        ->name('pdv.buscar-produto')
        ->middleware('permission:vendas,criar');

    /* ------ Caixa ------ */
    Route::post('/caixa/abrir', [App\CaixaController::class, 'abrir'])->name('caixa.abrir');
    Route::post('/caixa/fechar', [App\CaixaController::class, 'fechar'])->name('caixa.fechar');
    Route::post('/caixa/sangria', [App\CaixaController::class, 'sangria'])->name('caixa.sangria');
    Route::post('/caixa/suprimento', [App\CaixaController::class, 'suprimento'])->name('caixa.suprimento');

    /* ------ Estoque ------ */
    Route::resource('estoque/movimentacoes', App\EstoqueMovimentacaoController::class)
        ->middleware('permission:estoque');
    Route::resource('estoque/transferencias', App\TransferenciaEstoqueController::class)
        ->middleware('permission:estoque');

    /* ------ Financeiro ------ */
    Route::resource('financeiro/contas-receber', App\ContaReceberController::class)
        ->middleware('permission:financeiro');
    Route::post('financeiro/contas-receber/{contas_receber}/baixar', [App\ContaReceberController::class, 'baixar'])
        ->name('contas-receber.baixar')
        ->middleware('permission:financeiro');
    Route::resource('financeiro/contas-pagar', App\ContaPagarController::class)
        ->middleware('permission:financeiro');
    Route::post('financeiro/contas-pagar/{contas_pagar}/baixar', [App\ContaPagarController::class, 'baixar'])
        ->name('contas-pagar.baixar')
        ->middleware('permission:financeiro');
    Route::get('/financeiro/fluxo-caixa', [App\FluxoCaixaController::class, 'index'])
        ->name('financeiro.fluxo-caixa')
        ->middleware('permission:financeiro');

    /* ------ Contratos / Cobranças Recorrentes ------ */
    Route::resource('contratos', App\ContratoController::class)->middleware('permission:financeiro');
    Route::post('contratos/{contrato}/faturar', [App\ContratoController::class, 'faturar'])
        ->name('contratos.faturar')
        ->middleware('permission:financeiro');

    /* ------ Boletos ------ */
    Route::prefix('boletos')->name('boletos.')->middleware('permission:financeiro')->group(function () {
        Route::get('/', [App\BoletoController::class, 'index'])->name('index');
        Route::get('/{boleto}', [App\BoletoController::class, 'show'])->name('show');
        Route::post('/gerar', [App\BoletoController::class, 'gerar'])->name('gerar');
        Route::post('/carne', [App\BoletoController::class, 'gerarCarne'])->name('carne');
        Route::post('/{boleto}/cancelar', [App\BoletoController::class, 'cancelar'])->name('cancelar');
        Route::post('/{boleto}/baixar', [App\BoletoController::class, 'baixar'])->name('baixar');
    });

    /* ------ Plano de Contas ------ */
    Route::resource('plano-contas', App\PlanoContasController::class)->middleware('permission:financeiro');

    /* ------ Centro de Custos ------ */
    Route::resource('centros-custo', App\CentroCustoController::class)->middleware('permission:financeiro');

    /* ------ DRE ------ */
    Route::prefix('dre')->name('dre.')->middleware('permission:financeiro')->group(function () {
        Route::get('/', [App\DreController::class, 'index'])->name('index');
        Route::get('/por-unidade', [App\DreController::class, 'porUnidade'])->name('por-unidade');
        Route::get('/exportar', [App\DreController::class, 'exportar'])->name('exportar');
    });

    /* ------ Comissoes ------ */
    Route::prefix('comissoes')->name('comissoes.')->middleware('permission:vendas')->group(function () {
        Route::get('/', [App\ComissaoController::class, 'index'])->name('index');
        Route::get('/relatorio', [App\ComissaoController::class, 'relatorio'])->name('relatorio');
        Route::post('/pagar', [App\ComissaoController::class, 'pagar'])->name('pagar');
        Route::get('/configurar', [App\ComissaoController::class, 'configurar'])->name('configurar');
        Route::post('/configurar', [App\ComissaoController::class, 'salvarConfiguracao'])->name('configurar.store');
    });

    /* ------ Conciliação Bancária ------ */
    Route::prefix('conciliacao')->name('conciliacao.')->middleware('permission:financeiro')->group(function () {
        Route::get('/', [App\ConciliacaoBancariaController::class, 'index'])->name('index');
        Route::get('/create', [App\ConciliacaoBancariaController::class, 'create'])->name('create');
        Route::post('/', [App\ConciliacaoBancariaController::class, 'store'])->name('store');
        Route::get('/{conciliacao}', [App\ConciliacaoBancariaController::class, 'show'])->name('show');
        Route::post('/extrato/{extrato}/conciliar', [App\ConciliacaoBancariaController::class, 'conciliar'])->name('conciliar');
        Route::post('/{conciliacao}/auto', [App\ConciliacaoBancariaController::class, 'conciliarAutomatico'])->name('auto');
        Route::post('/{conciliacao}/finalizar', [App\ConciliacaoBancariaController::class, 'finalizar'])->name('finalizar');
    });

    /* ------ Notas Fiscais ------ */
    Route::prefix('notas-fiscais')->name('notas-fiscais.')->middleware('permission:notas_fiscais')->group(function () {
        Route::get('/', [App\NotaFiscalController::class, 'index'])->name('index');
        Route::get('/emitir-nfse', [App\NotaFiscalController::class, 'emitirNFSeForm'])->name('emitir-nfse');
        Route::post('/emitir-nfse', [App\NotaFiscalController::class, 'emitirNFSe'])->name('emitir-nfse.store');
        Route::get('/inutilizar', [App\NotaFiscalController::class, 'inutilizarForm'])->name('inutilizar');
        Route::post('/inutilizar', [App\NotaFiscalController::class, 'inutilizar'])->name('inutilizar.store');
        Route::post('/emitir-nfe/{venda}', [App\NotaFiscalController::class, 'emitirNFe'])->name('emitir-nfe');
        Route::post('/emitir-nfce/{venda}', [App\NotaFiscalController::class, 'emitirNFCe'])->name('emitir-nfce');
        Route::get('/{notaFiscal}', [App\NotaFiscalController::class, 'show'])->name('show');
        Route::get('/{notaFiscal}/consultar', [App\NotaFiscalController::class, 'consultar'])->name('consultar');
        Route::post('/{notaFiscal}/cancelar', [App\NotaFiscalController::class, 'cancelar'])->name('cancelar');
        Route::post('/{notaFiscal}/carta-correcao', [App\NotaFiscalController::class, 'cartaCorrecao'])->name('carta-correcao');
        Route::get('/{notaFiscal}/xml', [App\NotaFiscalController::class, 'downloadXml'])->name('xml');
        Route::get('/{notaFiscal}/danfe', [App\NotaFiscalController::class, 'downloadDanfe'])->name('danfe');
    });

    /* ------ Configuração Fiscal ------ */
    Route::prefix('configuracao-fiscal')->name('configuracao-fiscal.')->middleware('permission:configuracoes')->group(function () {
        Route::get('/', [App\ConfiguracaoFiscalController::class, 'edit'])->name('edit');
        Route::put('/', [App\ConfiguracaoFiscalController::class, 'update'])->name('update');
        Route::post('/testar', [App\ConfiguracaoFiscalController::class, 'testarConexao'])->name('testar');
    });

    /* ------ Relatorios ------ */
    Route::prefix('relatorios')->name('relatorios.')->middleware('permission:relatorios')->group(function () {
        Route::get('/vendas', [App\RelatorioController::class, 'vendas'])->name('vendas');
        Route::get('/estoque', [App\RelatorioController::class, 'estoque'])->name('estoque');
        Route::get('/financeiro', [App\RelatorioController::class, 'financeiro'])->name('financeiro');
    });

    /* ------ Multilojas (Dono/Admin only) ------ */
    Route::prefix('multilojas')->name('multilojas.')->group(function () {
        Route::get('/', [App\MultilojaController::class, 'index'])->name('index');
        Route::get('/comparar', [App\MultilojaController::class, 'comparar'])->name('comparar');
    });

    /* ------ Ordens de Servico ------ */
    Route::resource('ordens-servico', App\OrdemServicoController::class)->middleware('permission:vendas');
    Route::post('ordens-servico/{ordemServico}/status', [App\OrdemServicoController::class, 'updateStatus'])
        ->name('ordens-servico.update-status')
        ->middleware('permission:vendas');
    Route::post('ordens-servico/{ordemServico}/converter-venda', [App\OrdemServicoController::class, 'converterEmVenda'])
        ->name('ordens-servico.converter-venda')
        ->middleware('permission:vendas');
});

/* ------------------------------------------------------------------ */
/*  Webhooks (sem autenticação)                                        */
/* ------------------------------------------------------------------ */

Route::post('/webhooks/focusnfe', [\App\Http\Controllers\Webhook\FocusNFeWebhookController::class, 'handle'])
    ->name('webhooks.focusnfe');
