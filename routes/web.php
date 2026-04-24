<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UnidadeSelecaoController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\App;
use Illuminate\Http\Request;
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

/* ------ Password Reset ------ */
Route::get('/esqueci-senha', [\App\Http\Controllers\Auth\PasswordResetController::class, 'showForgotForm'])->name('password.request');
Route::post('/esqueci-senha', [\App\Http\Controllers\Auth\PasswordResetController::class, 'sendReset'])->name('password.email');
Route::get('/reset-senha/{token}', [\App\Http\Controllers\Auth\PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-senha', [\App\Http\Controllers\Auth\PasswordResetController::class, 'reset'])->name('password.update');

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
    Route::resource('planos', Admin\PlanoController::class);

    // Onboarding
    Route::prefix('onboarding')->name('onboarding.')->group(function () {
        Route::get('/step1', [Admin\OnboardingController::class, 'step1'])->name('step1');
        Route::post('/step1', [Admin\OnboardingController::class, 'storeStep1'])->name('step1.store');
        Route::get('/step2', [Admin\OnboardingController::class, 'step2'])->name('step2');
        Route::post('/step2', [Admin\OnboardingController::class, 'storeStep2'])->name('step2.store');
        Route::get('/step3', [Admin\OnboardingController::class, 'step3'])->name('step3');
        Route::post('/step3', [Admin\OnboardingController::class, 'storeStep3'])->name('step3.store');
        Route::get('/step4', [Admin\OnboardingController::class, 'step4'])->name('step4');
        Route::post('/step4', [Admin\OnboardingController::class, 'storeStep4'])->name('step4.store');
        Route::get('/concluido/{empresa}', [Admin\OnboardingController::class, 'concluido'])->name('concluido');
    });
});

/* ------------------------------------------------------------------ */
/*  App (usuarios empresa, unidade selecionada)                       */
/* ------------------------------------------------------------------ */

Route::middleware(['auth', 'unidade'])->prefix('app')->name('app.')->group(function () {

    Route::get('/dashboard', [App\DashboardController::class, 'index'])->name('dashboard');

    /* ------ Plano / Assinatura ------ */
    Route::get('/plano', [App\PlanoController::class, 'index'])->name('plano.index');
    Route::get('/plano/expirado', [App\PlanoController::class, 'expirado'])->name('plano-expirado');
    Route::get('/plano/comparar', [App\PlanoController::class, 'comparar'])->name('plano.comparar');

    /* ------ Importação CSV ------ */
    Route::prefix('import')->name('import.')->group(function () {
        Route::post('/clientes', [App\ImportController::class, 'clientes'])->name('clientes');
        Route::post('/produtos', [App\ImportController::class, 'produtos'])->name('produtos');
        Route::post('/fornecedores', [App\ImportController::class, 'fornecedores'])->name('fornecedores');
        Route::get('/template/{tipo}', [App\ImportController::class, 'template'])->name('template');
    });

    /* ------ Etiquetas ------ */
    Route::get('/etiquetas', [App\EtiquetaController::class, 'index'])->name('etiquetas.index')->middleware('permission:produtos');
    Route::post('/etiquetas/gerar', [App\EtiquetaController::class, 'gerar'])->name('etiquetas.gerar')->middleware('permission:produtos');

    /* ------ Cadastros ------ */
    Route::post('clientes/quick', [App\ClienteController::class, 'quickStore'])
        ->name('clientes.quick')
        ->middleware('permission:clientes');
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
    Route::post('pedidos/{pedido}/status', [App\PedidoController::class, 'updateStatus'])
        ->name('pedidos.update-status')
        ->middleware('permission:pedidos');
    Route::post('orcamentos/{orcamento}/status', [App\OrcamentoController::class, 'updateStatus'])
        ->name('orcamentos.update-status')
        ->middleware('permission:orcamentos');
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
    Route::get('/pdv/cliente/{termo}', [App\PdvController::class, 'buscarCliente'])
        ->name('pdv.buscar-cliente')
        ->middleware('permission:vendas,criar');
    Route::get('/pdv/estoque/{produto}', [App\PdvController::class, 'verificarEstoque'])
        ->name('pdv.verificar-estoque')
        ->middleware('permission:vendas,criar');

    /* ------ Caixa ------ */
    Route::match(['get', 'post'], '/caixa/abrir', [App\CaixaController::class, 'abrir'])->name('caixa.abrir');
    Route::match(['get', 'post'], '/caixa/fechar', [App\CaixaController::class, 'fechar'])->name('caixa.fechar');
    Route::post('/caixa/sangria', [App\CaixaController::class, 'sangria'])->name('caixa.sangria');
    Route::post('/caixa/suprimento', [App\CaixaController::class, 'suprimento'])->name('caixa.suprimento');

    /* ------ Estoque ------ */
    Route::resource('estoque/movimentacoes', App\EstoqueMovimentacaoController::class)
        ->middleware('permission:estoque');
    Route::resource('estoque/transferencias', App\TransferenciaEstoqueController::class)
        ->middleware('permission:estoque');
    Route::patch('estoque/transferencias/{transferencia}/aprovar', [App\TransferenciaEstoqueController::class, 'aprovar'])
        ->name('transferencias.aprovar')
        ->middleware('permission:estoque');
    Route::patch('estoque/transferencias/{transferencia}/cancelar', [App\TransferenciaEstoqueController::class, 'cancelar'])
        ->name('transferencias.cancelar')
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
    Route::resource('contratos', App\ContratoController::class)->middleware(['permission:financeiro', 'plano:contratos']);
    Route::post('contratos/{contrato}/faturar', [App\ContratoController::class, 'faturar'])
        ->name('contratos.faturar')
        ->middleware(['permission:financeiro', 'plano:contratos']);

    /* ------ Boletos ------ */
    Route::prefix('boletos')->name('boletos.')->middleware(['permission:financeiro', 'plano:boletos'])->group(function () {
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
    Route::prefix('dre')->name('dre.')->middleware(['permission:financeiro', 'plano:dre'])->group(function () {
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
    Route::prefix('conciliacao')->name('conciliacao.')->middleware(['permission:financeiro', 'plano:conciliacao'])->group(function () {
        Route::get('/', [App\ConciliacaoBancariaController::class, 'index'])->name('index');
        Route::get('/create', [App\ConciliacaoBancariaController::class, 'create'])->name('create');
        Route::post('/', [App\ConciliacaoBancariaController::class, 'store'])->name('store');
        Route::get('/{conciliacao}', [App\ConciliacaoBancariaController::class, 'show'])->name('show');
        Route::post('/extrato/{extrato}/conciliar', [App\ConciliacaoBancariaController::class, 'conciliar'])->name('conciliar');
        Route::post('/{conciliacao}/auto', [App\ConciliacaoBancariaController::class, 'conciliarAutomatico'])->name('auto');
        Route::post('/{conciliacao}/finalizar', [App\ConciliacaoBancariaController::class, 'finalizar'])->name('finalizar');
    });

    /* ------ Notas Fiscais ------ */
    Route::prefix('notas-fiscais')->name('notas-fiscais.')->middleware(['permission:notas_fiscais', 'plano:fiscal'])->group(function () {
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
    Route::prefix('configuracao-fiscal')->name('configuracao-fiscal.')->middleware(['permission:configuracoes', 'plano:fiscal'])->group(function () {
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

    /* ------ Auditoria (Dono/Admin) ------ */
    Route::get('/auditoria', [App\AuditoriaController::class, 'index'])
        ->name('auditoria.index')
        ->middleware('permission:auditoria');

    /* ------ Multilojas (Dono/Admin only) ------ */
    Route::prefix('multilojas')->name('multilojas.')->middleware('plano:multilojas')->group(function () {
        Route::get('/', [App\MultilojaController::class, 'index'])->name('index');
        Route::get('/comparar', [App\MultilojaController::class, 'comparar'])->name('comparar');
    });

    /* ------ Fiscal — ICMS ST Calculator (AJAX) ------ */
    Route::get('/fiscal/calcular-st', function (Request $request) {
        $result = \App\Services\ICMSCalculator::calcular(
            $request->uf_origem,
            $request->uf_destino,
            (float) $request->valor,
            $request->mva,
        );

        return response()->json($result);
    })->name('fiscal.calcular-st');

    Route::get('/fiscal/tabela-st/{uf}', function (string $uf) {
        $tabela = \App\Services\ICMSCalculator::tabelaPorEstado(strtoupper($uf));

        return response()->json($tabela);
    })->name('fiscal.tabela-st');

    /* ------ Notificacoes ------ */
    Route::prefix('notificacoes')->name('notificacoes.')->group(function () {
        Route::get('/', [App\NotificacaoController::class, 'index'])->name('index');
        Route::post('/{notificacao}/lida', [App\NotificacaoController::class, 'marcarLida'])->name('lida');
        Route::post('/todas-lidas', [App\NotificacaoController::class, 'marcarTodasLidas'])->name('todas-lidas');
        Route::get('/contar', [App\NotificacaoController::class, 'contar'])->name('contar');
    });

    /* ------ Dismiss Wizard ------ */
    Route::post('/dismiss-wizard', function () {
        session(['wizard_dismissed' => true]);
        return response()->json(['ok' => true]);
    })->name('dismiss-wizard');

    /* ------ Search API (autocomplete) ------ */
    Route::prefix('search')->name('search.')->group(function () {
        Route::get('/clientes', [App\SearchController::class, 'clientes'])->name('clientes');
        Route::get('/produtos', [App\SearchController::class, 'produtos'])->name('produtos');
        Route::get('/fornecedores', [App\SearchController::class, 'fornecedores'])->name('fornecedores');
        Route::get('/vendedores', [App\SearchController::class, 'vendedores'])->name('vendedores');
        Route::get('/global', [App\SearchController::class, 'global'])->name('global');
    });

    /* ------ Exportacao CSV ------ */
    Route::prefix('export')->name('export.')->group(function () {
        Route::get('/clientes', [App\ExportController::class, 'clientes'])->name('clientes');
        Route::get('/produtos', [App\ExportController::class, 'produtos'])->name('produtos');
        Route::get('/fornecedores', [App\ExportController::class, 'fornecedores'])->name('fornecedores');
        Route::get('/vendas', [App\ExportController::class, 'vendas'])->name('vendas');
        Route::get('/contas-receber', [App\ExportController::class, 'contasReceber'])->name('contas-receber');
        Route::get('/contas-pagar', [App\ExportController::class, 'contasPagar'])->name('contas-pagar');
    });

    /* ------ Ordens de Servico ------ */
    Route::resource('ordens-servico', App\OrdemServicoController::class)->middleware(['permission:vendas', 'plano:os']);
    Route::post('ordens-servico/{ordemServico}/status', [App\OrdemServicoController::class, 'updateStatus'])
        ->name('ordens-servico.update-status')
        ->middleware(['permission:vendas', 'plano:os']);
    Route::post('ordens-servico/{ordemServico}/converter-venda', [App\OrdemServicoController::class, 'converterEmVenda'])
        ->name('ordens-servico.converter-venda')
        ->middleware(['permission:vendas', 'plano:os']);
});

/* ------------------------------------------------------------------ */
/*  Webhooks (sem autenticação)                                        */
/* ------------------------------------------------------------------ */

Route::post('/webhooks/focusnfe', [\App\Http\Controllers\Webhook\FocusNFeWebhookController::class, 'handle'])
    ->name('webhooks.focusnfe');
