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

Route::get('/', [\App\Http\Controllers\SiteController::class, 'index'])->name('site.home');
Route::post('/agendar-demonstracao', [\App\Http\Controllers\SiteController::class, 'storeDemo'])
    ->middleware('throttle:10,1')
    ->name('site.demo.store');

/* ------------------------------------------------------------------ */
/*  Auth                                                               */
/* ------------------------------------------------------------------ */

// /app sem sub-rota caía em 404 — manda para o dashboard (auth redireciona p/ login)
Route::redirect('/app', '/app/dashboard');

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

/* ------ Acesso suspenso (pendência financeira da plataforma) ------ */
Route::middleware('auth')->get('/acesso-suspenso', function () {
    $empresa = auth()->user()->empresa;

    if (! $empresa || ! $empresa->estaSuspensa() || auth()->user()->is_admin) {
        return redirect('/app/dashboard');
    }

    $faturas = $empresa->plataformaFaturas()->pendentes()->orderBy('vencimento')->get();

    return view('auth.acesso-suspenso', compact('empresa', 'faturas'));
})->name('acesso-suspenso');

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
    // Cobrança / cortesias — antes do resource pra não bater com show
    Route::post('/empresas/{empresa}/estender-trial', [Admin\EmpresaController::class, 'estenderTrial'])
        ->name('empresas.estender-trial');
    Route::post('/empresas/{empresa}/reenviar-acesso', [Admin\EmpresaController::class, 'reenviarAcesso'])
        ->name('empresas.reenviar-acesso');
    // Saúde da integração Focus (antes do resource para não bater com show/{id})
    Route::get('/empresas/{empresa}/saude-focus', [Admin\SaudeFocusController::class, 'show'])
        ->name('empresas.saude-focus');
    Route::post('/empresas/{empresa}/saude-focus/resincronizar', [Admin\SaudeFocusController::class, 'resincronizar'])
        ->name('empresas.saude-focus.resincronizar');
    // Tokens da API de Integração (Gersen) — aba Integração da empresa
    Route::post('/empresas/{empresa}/integracao-tokens', [Admin\IntegracaoTokenController::class, 'store'])
        ->name('empresas.integracao-tokens.store');
    Route::post('/empresas/{empresa}/integracao-tokens/{token}/revogar', [Admin\IntegracaoTokenController::class, 'revogar'])
        ->name('empresas.integracao-tokens.revogar');
    Route::post('/empresas/{empresa}/agente-ia/toggle', [Admin\AgenteIaController::class, 'toggle'])
        ->name('empresas.agente-ia.toggle');
    Route::post('/empresas/{empresa}/agente-ia/reindexar', [Admin\AgenteIaController::class, 'reindexar'])
        ->name('empresas.agente-ia.reindexar');
    // Gateway PIX Sicredi (Agente IA) — aba Integração da empresa
    Route::post('/empresas/{empresa}/gateway-pix', [Admin\EmpresaGatewayController::class, 'store'])
        ->name('empresas.gateway-pix.store');
    Route::post('/empresas/{empresa}/gateway-pix/testar', [Admin\EmpresaGatewayController::class, 'testar'])
        ->name('empresas.gateway-pix.testar');
    Route::post('/empresas/{empresa}/gateway-pix/webhook', [Admin\EmpresaGatewayController::class, 'registrarWebhook'])
        ->name('empresas.gateway-pix.webhook');
    Route::resource('empresas', Admin\EmpresaController::class);
    Route::resource('empresas.unidades', Admin\UnidadeController::class)->shallow();
    Route::resource('usuarios', Admin\UsuarioController::class);
    Route::resource('planos', Admin\PlanoController::class)->except(['show']);

    // Financeiro da plataforma (cobrança direta — só admins com pode_ver_financeiro)
    Route::prefix('financeiro')->name('financeiro.')->group(function () {
        Route::get('/', [Admin\FinanceiroPlataformaController::class, 'index'])->name('index');
        Route::post('/faturas', [Admin\FinanceiroPlataformaController::class, 'store'])->name('faturas.store');
        Route::post('/faturas/{fatura}/pagar', [Admin\FinanceiroPlataformaController::class, 'marcarPaga'])->name('faturas.pagar');
        Route::post('/faturas/{fatura}/cancelar', [Admin\FinanceiroPlataformaController::class, 'cancelar'])->name('faturas.cancelar');
        Route::post('/empresas/{empresa}/suspender', [Admin\FinanceiroPlataformaController::class, 'suspender'])->name('empresas.suspender');
        Route::post('/empresas/{empresa}/reativar', [Admin\FinanceiroPlataformaController::class, 'reativar'])->name('empresas.reativar');
    });

    // Leads de demonstração (capturados pela landing pública)
    Route::get('/demonstracoes', [Admin\DemonstracaoController::class, 'index'])->name('demonstracoes.index');
    Route::patch('/demonstracoes/{demonstracao}', [Admin\DemonstracaoController::class, 'updateStatus'])->name('demonstracoes.status');

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

Route::middleware(['auth', 'suspensao', 'unidade'])->prefix('app')->name('app.')->group(function () {

    Route::get('/dashboard', [App\DashboardController::class, 'index'])->name('dashboard');

    /* ------ Minha Empresa & Minhas Lojas (dono/gerente — pedido 05/08) ------ */
    Route::get('/empresa', [App\MinhaEmpresaController::class, 'edit'])->name('empresa.edit');
    Route::put('/empresa', [App\MinhaEmpresaController::class, 'update'])->name('empresa.update');
    Route::prefix('lojas')->name('lojas.')->group(function () {
        Route::get('/', [App\LojaController::class, 'index'])->name('index')
            ->middleware('permission:unidades');
        Route::get('/nova', [App\LojaController::class, 'create'])->name('create')
            ->middleware('permission:unidades,criar');
        Route::post('/', [App\LojaController::class, 'store'])->name('store')
            ->middleware('permission:unidades,criar');
        Route::get('/{loja}/editar', [App\LojaController::class, 'edit'])->name('edit')
            ->middleware('permission:unidades,editar');
        Route::put('/{loja}', [App\LojaController::class, 'update'])->name('update')
            ->middleware('permission:unidades,editar');
    });

    /* ------ Plano / Assinatura ------ */
    Route::get('/plano', [App\PlanoController::class, 'index'])->name('plano.index');
    Route::get('/plano/expirado', [App\PlanoController::class, 'expirado'])->name('plano-expirado');
    Route::get('/plano/comparar', [App\PlanoController::class, 'comparar'])->name('plano.comparar');

    /* ------ Importação CSV ------ */
    Route::prefix('import')->name('import.')->group(function () {
        Route::post('/clientes', [App\ImportController::class, 'clientes'])->name('clientes')
            ->middleware('permission:clientes,criar');
        Route::post('/produtos', [App\ImportController::class, 'produtos'])->name('produtos')
            ->middleware('permission:produtos,criar');
        Route::post('/fornecedores', [App\ImportController::class, 'fornecedores'])->name('fornecedores')
            ->middleware('permission:produtos,criar');
        Route::post('/vendas', [App\ImportController::class, 'vendas'])->name('vendas')
            ->middleware('permission:vendas,criar');
        Route::post('/contas-receber', [App\ImportController::class, 'contasReceber'])->name('contas-receber')
            ->middleware('permission:financeiro,criar');
        Route::post('/estoque', [App\ImportController::class, 'estoque'])->name('estoque')
            ->middleware('permission:estoque,criar');
        Route::get('/template/{tipo}', [App\ImportController::class, 'template'])->name('template');
    });

    /* ------ Etiquetas ------ */
    Route::get('/etiquetas', [App\EtiquetaController::class, 'index'])->name('etiquetas.index')->middleware('permission:produtos');
    Route::post('/etiquetas/gerar', [App\EtiquetaController::class, 'gerar'])->name('etiquetas.gerar')->middleware('permission:produtos');
    // Formatos próprios da empresa. O parâmetro {etiquetaFormato} PRECISA casar com
    // o nome da variável tipada no controller — binding que não casa entrega model
    // VAZIO em silêncio (armadilha do route model binding, fix de 25/07).
    Route::post('/etiquetas/formatos', [App\EtiquetaController::class, 'formatoStore'])->name('etiquetas.formatos.store')->middleware('permission:produtos,criar');
    Route::delete('/etiquetas/formatos/{etiquetaFormato}', [App\EtiquetaController::class, 'formatoDestroy'])->name('etiquetas.formatos.destroy')->middleware('permission:produtos,criar');
    // Editor visual do layout. O formato FIXO entra pela rota /layout-fixo/{chave}:
    // ela cria (uma vez) o registro que guarda o desenho daquele fixo para esta
    // empresa e cai no mesmo editor — o fixo em si continua sendo constante.
    Route::get('/etiquetas/formatos/{etiquetaFormato}/editor', [App\EtiquetaController::class, 'editor'])->name('etiquetas.formatos.editor')->middleware('permission:produtos,criar');
    Route::put('/etiquetas/formatos/{etiquetaFormato}/layout', [App\EtiquetaController::class, 'layoutUpdate'])->name('etiquetas.formatos.layout')->middleware('permission:produtos,criar');
    Route::delete('/etiquetas/formatos/{etiquetaFormato}/layout', [App\EtiquetaController::class, 'layoutReset'])->name('etiquetas.formatos.layout.reset')->middleware('permission:produtos,criar');
    Route::get('/etiquetas/layout-fixo/{chave}', [App\EtiquetaController::class, 'editorFixo'])->name('etiquetas.formatos.editor-fixo')->middleware('permission:produtos,criar');
    // Galeria de imagens do editor — respondem JSON (o envio acontece com o
    // editor aberto e o desenho ainda não salvo na tela).
    Route::post('/etiquetas/imagens', [App\EtiquetaController::class, 'imagemStore'])->name('etiquetas.imagens.store')->middleware('permission:produtos,criar');
    Route::delete('/etiquetas/imagens/{etiquetaImagem}', [App\EtiquetaController::class, 'imagemDestroy'])->name('etiquetas.imagens.destroy')->middleware('permission:produtos,criar');

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
    Route::post('pedidos/{pedido}/faturar', [App\PedidoController::class, 'faturar'])
        ->name('pedidos.faturar')
        ->middleware('permission:pedidos');
    Route::post('orcamentos/{orcamento}/status', [App\OrcamentoController::class, 'updateStatus'])
        ->name('orcamentos.update-status')
        ->middleware('permission:orcamentos');
    Route::get('vendas/{venda}/recibo', [App\VendaController::class, 'recibo'])
        ->name('vendas.recibo')
        ->middleware('permission:vendas');
    Route::resource('vendas', App\VendaController::class)
        ->except(['edit', 'update']) // venda concluída não é editável (cancelar/devolver são os fluxos)
        ->middleware('permission:vendas');

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
    // Histórico de caixas (extrato + conferência) — leitura
    Route::get('/caixa', [App\CaixaController::class, 'index'])->name('caixa.index')
        ->middleware('permission:vendas');

    // permission:vendas,criar — mesmo gate do PDV (caixa/vendedor operam; consulta não)
    Route::match(['get', 'post'], '/caixa/abrir', [App\CaixaController::class, 'abrir'])->name('caixa.abrir')
        ->middleware('permission:vendas,criar');
    Route::match(['get', 'post'], '/caixa/fechar', [App\CaixaController::class, 'fechar'])->name('caixa.fechar')
        ->middleware('permission:vendas,criar');
    Route::post('/caixa/sangria', [App\CaixaController::class, 'sangria'])->name('caixa.sangria')
        ->middleware('permission:vendas,criar');
    Route::post('/caixa/suprimento', [App\CaixaController::class, 'suprimento'])->name('caixa.suprimento')
        ->middleware('permission:vendas,criar');
    Route::get('/caixa/anexo/{anexo}', [App\CaixaController::class, 'anexo'])->name('caixa.anexo')
        ->middleware('permission:vendas');
    // Wildcard por último para não capturar /caixa/abrir|fechar
    Route::get('/caixa/{caixa}', [App\CaixaController::class, 'show'])->name('caixa.show')
        ->middleware('permission:vendas');

    /* ------ Estoque ------ */
    Route::resource('estoque/movimentacoes', App\EstoqueMovimentacaoController::class)
        ->only(['index', 'create', 'store', 'show']) // movimentação é imutável (auditoria)
        ->parameters(['movimentacoes' => 'movimentacao']) // casar com $movimentacao (binding)
        ->middleware('permission:estoque');
    Route::resource('estoque/transferencias', App\TransferenciaEstoqueController::class)
        ->except(['edit', 'update']) // fluxo é aprovar/cancelar, não editar
        ->middleware('permission:estoque');
    Route::patch('estoque/transferencias/{transferencia}/aprovar', [App\TransferenciaEstoqueController::class, 'aprovar'])
        ->name('transferencias.aprovar')
        ->middleware('permission:estoque');
    Route::patch('estoque/transferencias/{transferencia}/cancelar', [App\TransferenciaEstoqueController::class, 'cancelar'])
        ->name('transferencias.cancelar')
        ->middleware('permission:estoque');

    // Peças em poder de terceiros (bonificação que deve voltar)
    // {comodato} casa com $comodato do controller — ver armadilha do binding
    Route::get('estoque/comodatos', [App\ComodatoController::class, 'index'])
        ->name('comodatos.index')
        ->middleware('permission:estoque');
    Route::post('estoque/comodatos/{comodato}/devolver', [App\ComodatoController::class, 'devolver'])
        ->name('comodatos.devolver')
        ->middleware('permission:estoque');
    Route::post('estoque/comodatos/{comodato}/perda', [App\ComodatoController::class, 'baixarPerda'])
        ->name('comodatos.perda')
        ->middleware('permission:estoque');

    /* ------ Financeiro ------ */
    Route::resource('financeiro/contas-receber', App\ContaReceberController::class)
        ->except(['edit', 'update']) // fluxo é baixar/estornar, não editar
        ->parameters(['contas-receber' => 'contaReceber']) // casar com $contaReceber (binding)
        ->middleware('permission:financeiro');
    Route::post('financeiro/contas-receber/{contaReceber}/baixar', [App\ContaReceberController::class, 'baixar'])
        ->name('contas-receber.baixar')
        ->middleware('permission:financeiro');
    Route::resource('financeiro/contas-pagar', App\ContaPagarController::class)
        ->except(['edit', 'update']) // fluxo é baixar/estornar, não editar
        ->parameters(['contas-pagar' => 'contaPagar']) // casar com $contaPagar (binding)
        ->middleware('permission:financeiro');
    Route::post('financeiro/contas-pagar/{contaPagar}/baixar', [App\ContaPagarController::class, 'baixar'])
        ->name('contas-pagar.baixar')
        ->middleware('permission:financeiro');
    /* ------ Adquirentes (máquinas de cartão: taxas e prazos) ------ */
    Route::prefix('adquirentes')->name('adquirentes.')->middleware('permission:financeiro')->group(function () {
        Route::get('/', [App\AdquirenteController::class, 'index'])->name('index');
        Route::get('/recebiveis', [App\AdquirenteController::class, 'recebiveis'])->name('recebiveis');
        Route::post('/', [App\AdquirenteController::class, 'store'])->name('store')
            ->middleware('permission:financeiro,criar');
        Route::put('/{adquirente}', [App\AdquirenteController::class, 'update'])->name('update')
            ->middleware('permission:financeiro,editar');
        Route::delete('/{adquirente}', [App\AdquirenteController::class, 'destroy'])->name('destroy')
            ->middleware('permission:financeiro,excluir');
    });

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
    Route::resource('plano-contas', App\PlanoContasController::class)
        ->except(['show'])
        ->parameters(['plano-contas' => 'planoContas']) // casar com $planoContas (binding)
        ->middleware('permission:financeiro');

    /* ------ Centro de Custos ------ */
    Route::resource('centros-custo', App\CentroCustoController::class)
        ->except(['show'])
        ->middleware('permission:financeiro');

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
        // pagar/configurar mexem em dinheiro e percentuais — só financeiro/dono
        Route::post('/pagar', [App\ComissaoController::class, 'pagar'])->name('pagar')
            ->middleware('permission:financeiro,editar');
        Route::get('/configurar', [App\ComissaoController::class, 'configurar'])->name('configurar')
            ->middleware('permission:financeiro,editar');
        Route::post('/configurar', [App\ComissaoController::class, 'salvarConfiguracao'])->name('configurar.store')
            ->middleware('permission:financeiro,editar');
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
        Route::post('/{notaFiscal}/ator-interessado', [App\NFeEventoController::class, 'atorInteressado'])->name('ator-interessado');
        Route::post('/{notaFiscal}/insucesso-entrega', [App\NFeEventoController::class, 'insucessoEntrega'])->name('insucesso-entrega');
        Route::get('/{notaFiscal}/xml', [App\NotaFiscalController::class, 'downloadXml'])->name('xml');
        Route::get('/{notaFiscal}/danfe', [App\NotaFiscalController::class, 'downloadDanfe'])->name('danfe');
    });

    /* ------ Configurações da Loja (operacional, por unidade) ------ */
    Route::prefix('configuracoes')->name('configuracoes.')->middleware('permission:configuracoes')->group(function () {
        Route::get('/', [App\ConfiguracaoLojaController::class, 'edit'])->name('edit');
        Route::put('/', [App\ConfiguracaoLojaController::class, 'update'])->name('update');
    });

    /* ------ Integrações (tokens da API — Gersen) ------ */
    // {token} casa com $token do controller (armadilha do route model binding)
    Route::prefix('configuracoes/integracao')->name('integracao.')->middleware('permission:configuracoes')->group(function () {
        Route::get('/', [App\IntegracaoTokenController::class, 'index'])->name('index');
        Route::post('/', [App\IntegracaoTokenController::class, 'store'])->name('store');
        Route::post('/{token}/revogar', [App\IntegracaoTokenController::class, 'revogar'])->name('revogar');
    });

    // Estoques da loja (salão, depósito, avaria) — mora dentro de Configurações,
    // não vira item de menu: loja com um estoque só não precisa saber que existe.
    Route::prefix('configuracoes/estoques')->name('estoques.')->middleware('permission:configuracoes')->group(function () {
        Route::get('/', [App\EstoqueController::class, 'index'])->name('index');
        Route::get('/novo', [App\EstoqueController::class, 'create'])->name('create');
        Route::post('/', [App\EstoqueController::class, 'store'])->name('store');
        Route::get('/{estoque}/editar', [App\EstoqueController::class, 'edit'])->name('edit');
        Route::put('/{estoque}', [App\EstoqueController::class, 'update'])->name('update');
        Route::post('/{estoque}/inativar', [App\EstoqueController::class, 'inativar'])->name('inativar');
    });

    /* ------ Configuração Fiscal ------ */
    Route::prefix('configuracao-fiscal')->name('configuracao-fiscal.')->middleware(['permission:configuracoes', 'plano:fiscal'])->group(function () {
        Route::get('/', [App\ConfiguracaoFiscalController::class, 'edit'])->name('edit');
        Route::put('/', [App\ConfiguracaoFiscalController::class, 'update'])->name('update');
        Route::post('/testar', [App\ConfiguracaoFiscalController::class, 'testarConexao'])->name('testar');
        Route::post('/certificado', [App\ConfiguracaoFiscalController::class, 'uploadCertificado'])->name('certificado');
        Route::get('/sefaz-status', [App\ConfiguracaoFiscalController::class, 'statusSefaz'])->name('sefaz-status');
        Route::put('/regime', [App\ConfiguracaoFiscalController::class, 'atualizarRegime'])->name('regime');
    });

    /* ------ NFes Recebidas (Manifestação do Destinatário) ------ */
    Route::prefix('nfes-recebidas')->name('nfes-recebidas.')
        ->middleware(['permission:notas_fiscais', 'plano:fiscal'])
        ->group(function () {
            Route::get('/', [App\NFeRecebidaController::class, 'index'])->name('index');
            Route::post('/sincronizar', [App\NFeRecebidaController::class, 'sincronizar'])->name('sincronizar');
            Route::post('/{nfeRecebida}/manifestar', [App\NFeRecebidaController::class, 'manifestar'])->name('manifestar');
        });

    /* ------ NFSes Recebidas (tomadas — serviços contratados) ------ */
    Route::prefix('nfses-recebidas')->name('nfses-recebidas.')
        ->middleware(['permission:notas_fiscais', 'plano:fiscal'])
        ->group(function () {
            Route::get('/', [App\NFSeRecebidaController::class, 'index'])->name('index');
            Route::post('/sincronizar', [App\NFSeRecebidaController::class, 'sincronizar'])->name('sincronizar');
            Route::get('/{nfseRecebida}', [App\NFSeRecebidaController::class, 'show'])->name('show');
        });

    /* ------ Emails bloqueados (Focus — bounces fiscais) ------ */
    Route::prefix('emails-bloqueados')->name('emails-bloqueados.')
        ->middleware(['permission:notas_fiscais', 'plano:fiscal'])
        ->group(function () {
            Route::get('/', [App\EmailsBloqueadosController::class, 'index'])->name('index');
            Route::delete('/{email}', [App\EmailsBloqueadosController::class, 'desbloquear'])->name('desbloquear');
        });

    /* ------ Backups XML (mensais) ------ */
    Route::prefix('backups-xml')->name('backups-xml.')
        ->middleware(['permission:notas_fiscais', 'plano:fiscal'])
        ->group(function () {
            Route::get('/', [App\BackupsXmlController::class, 'index'])->name('index');
            Route::post('/gerar', [App\BackupsXmlController::class, 'gerar'])->name('gerar');
            // Serve o zip do NOSSO storage (pacote montado localmente — o
            // /v2/backups da Focus não existe; ver BackupXmlService).
            Route::get('/download/{mes}', [App\BackupsXmlController::class, 'download'])->name('download');
        });

    /* ------ Relatorios ------ */
    Route::prefix('relatorios')->name('relatorios.')->middleware('permission:relatorios')->group(function () {
        Route::get('/vendas', [App\RelatorioController::class, 'vendas'])->name('vendas');
        Route::get('/estoque', [App\RelatorioController::class, 'estoque'])->name('estoque');
        // Folha de contagem física — sem o saldo do sistema, de propósito
        Route::get('/estoque-cego', [App\RelatorioController::class, 'estoqueCego'])->name('estoque-cego');
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
        Route::get('/estoque', [App\MultilojaController::class, 'estoque'])->name('estoque');
        Route::post('/estoque/ajustar', [App\MultilojaController::class, 'ajustarEstoque'])->name('estoque.ajustar');
    });

    /* ------ Fiscal — Dashboard ------ */
    Route::get('/fiscal/dashboard', [App\DashboardFiscalController::class, 'index'])
        ->middleware(['permission:notas_fiscais', 'plano:fiscal'])
        ->name('fiscal.dashboard');

    /* ------ Fiscal — ICMS ST Calculator (AJAX) ------ */
    Route::get('/fiscal/calcular-st', function (Request $request) {
        $request->validate([
            'uf_origem'  => ['required', 'string', 'size:2'],
            'uf_destino' => ['required', 'string', 'size:2'],
            'valor'      => ['required', 'numeric', 'min:0'],
        ]);

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

    /* ------ Autocomplete via Focus NFe (NCM/CFOP/CNAE/Municípios/CNPJ) ------ */
    Route::prefix('focus-autocomplete')->name('focus-autocomplete.')->group(function () {
        Route::get('/ncm',               [App\FocusAutocompleteController::class, 'ncm'])->name('ncm');
        Route::get('/cfop',              [App\FocusAutocompleteController::class, 'cfop'])->name('cfop');
        Route::get('/cnae',              [App\FocusAutocompleteController::class, 'cnae'])->name('cnae');
        Route::get('/municipios/{uf}',   [App\FocusAutocompleteController::class, 'municipios'])->name('municipios');
        Route::get('/cnpj/{cnpj}',       [App\FocusAutocompleteController::class, 'cnpj'])->name('cnpj');
    });

    /* ------ Exportacao CSV ------ */
    Route::prefix('export')->name('export.')->group(function () {
        Route::get('/clientes', [App\ExportController::class, 'clientes'])->name('clientes')
            ->middleware('permission:clientes');
        Route::get('/produtos', [App\ExportController::class, 'produtos'])->name('produtos')
            ->middleware('permission:produtos');
        Route::get('/fornecedores', [App\ExportController::class, 'fornecedores'])->name('fornecedores')
            ->middleware('permission:produtos');
        Route::get('/vendas', [App\ExportController::class, 'vendas'])->name('vendas')
            ->middleware('permission:vendas');
        Route::get('/contas-receber', [App\ExportController::class, 'contasReceber'])->name('contas-receber')
            ->middleware('permission:financeiro');
        Route::get('/contas-pagar', [App\ExportController::class, 'contasPagar'])->name('contas-pagar')
            ->middleware('permission:financeiro');
    });

    /* ------ Ordens de Servico ------ */
    Route::resource('ordens-servico', App\OrdemServicoController::class)
        ->parameters(['ordens-servico' => 'ordemServico']) // casar com $ordemServico (binding)
        ->middleware(['permission:vendas', 'plano:os']);
    Route::post('ordens-servico/{ordemServico}/status', [App\OrdemServicoController::class, 'updateStatus'])
        ->name('ordens-servico.update-status')
        ->middleware(['permission:vendas', 'plano:os']);
    Route::post('ordens-servico/{ordemServico}/converter-venda', [App\OrdemServicoController::class, 'converterEmVenda'])
        ->name('ordens-servico.converter-venda')
        ->middleware(['permission:vendas', 'plano:os']);
});

/* ------------------------------------------------------------------ */
/*  API de Integração v1 (somente leitura — Gersen)                    */
/* ------------------------------------------------------------------ */

// Autenticação por token Bearer gerado no /admin (aba Integração da empresa).
// O middleware entra POR CLASSE, sem alias: alias novo mora no bootstrap/app.php
// e bootstrap/ só chega em produção com rebuild da imagem (armadilha 46).
Route::prefix('api/integracao/v1')->name('api.integracao.')
    ->middleware([\App\Http\Middleware\IntegracaoApiToken::class, 'throttle:300,1'])
    ->group(function () {
        Route::get('/ping', [\App\Http\Controllers\Api\IntegracaoGersenController::class, 'ping'])->name('ping');
        Route::get('/lojas', [\App\Http\Controllers\Api\IntegracaoGersenController::class, 'lojas'])->name('lojas');
        Route::get('/vendedores', [\App\Http\Controllers\Api\IntegracaoGersenController::class, 'vendedores'])->name('vendedores');
        Route::get('/situacoes', [\App\Http\Controllers\Api\IntegracaoGersenController::class, 'situacoes'])->name('situacoes');
        Route::get('/vendas', [\App\Http\Controllers\Api\IntegracaoGersenController::class, 'vendas'])->name('vendas');

        // Agente IA (app.ia365) — exige módulo ativo na empresa do token
        // (exceto /agente/ativar, que é quem liga o módulo).
        // POST /pedidos é a única escrita de negócio: pedido RASCUNHO, humano confirma.
        Route::post('/agente/ativar', [\App\Http\Controllers\Api\IntegracaoAgenteController::class, 'ativarAgente'])->name('agente.ativar');
        Route::post('/produtos/buscar', [\App\Http\Controllers\Api\IntegracaoAgenteController::class, 'buscarProdutos'])->name('produtos.buscar');
        Route::get('/produtos/{id}', [\App\Http\Controllers\Api\IntegracaoAgenteController::class, 'produto'])->whereNumber('id')->name('produtos.show');
        Route::get('/produtos/{id}/estoque', [\App\Http\Controllers\Api\IntegracaoAgenteController::class, 'estoqueProduto'])->whereNumber('id')->name('produtos.estoque');
        Route::get('/pedidos', [\App\Http\Controllers\Api\IntegracaoAgenteController::class, 'pedidos'])->name('pedidos.index');
        Route::get('/pedidos/resumo', [\App\Http\Controllers\Api\IntegracaoAgenteController::class, 'resumoPedidos'])->name('pedidos.resumo');
        Route::get('/dashboard', [\App\Http\Controllers\Api\IntegracaoAgenteController::class, 'dashboard'])->name('dashboard');
        Route::get('/pedidos/{id}', [\App\Http\Controllers\Api\IntegracaoAgenteController::class, 'pedido'])->whereNumber('id')->name('pedidos.show');
        Route::post('/pedidos', [\App\Http\Controllers\Api\IntegracaoAgenteController::class, 'criarPedido'])->name('pedidos.store');
        Route::post('/pedidos/{id}/pix', [\App\Http\Controllers\Api\IntegracaoAgenteController::class, 'pixPedido'])->whereNumber('id')->name('pedidos.pix');
    });

// Webhook PIX Sicredi — SEM Bearer (o PSP chama), mas sob api/integracao/*
// para herdar a isenção de CSRF sem tocar no bootstrap/ (armadilha 46).
// O Sicredi registra {url} e entrega em {url}/pix — aceitamos os dois.
Route::post('/api/integracao/v1/webhooks/sicredi', [\App\Http\Controllers\Webhook\SicrediPixWebhookController::class, 'handle'])
    ->name('webhooks.sicredi');
Route::post('/api/integracao/v1/webhooks/sicredi/pix', [\App\Http\Controllers\Webhook\SicrediPixWebhookController::class, 'handle'])
    ->name('webhooks.sicredi.pix');

/* ------------------------------------------------------------------ */
/*  Webhooks (sem autenticação)                                        */
/* ------------------------------------------------------------------ */

Route::post('/webhooks/focusnfe', [\App\Http\Controllers\Webhook\FocusNFeWebhookController::class, 'handle'])
    ->name('webhooks.focusnfe');
