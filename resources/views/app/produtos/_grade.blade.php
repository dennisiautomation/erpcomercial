{{-- Quadro "Tamanhos e cores" do cadastro do produto (16/11/2026).
     Cada tamanho/cor é um PRODUTO comum (código, preço e estoque próprios);
     a grade só amarra um ao outro. Fica FORA do formulário do produto
     (formulário dentro de formulário não existe em HTML). --}}
@php
    $G = \App\Services\LojaOnline\GradeLoja::class;
    $gPode = \App\Http\Middleware\CheckPermission::canUser(auth()->user(), 'produtos', 'editar');
    $gPrincipal = $G::principalDe($produto);
    $gVariacoes = $gPrincipal ? $G::variacoes($gPrincipal) : collect();
    $gNomes = $gPrincipal ? $G::nomes($gPrincipal) : [];
    $gUnidade = (int) session('unidade_id');
    $gSaldos = $gUnidade && $gVariacoes->isNotEmpty() ? \App\Services\SaldoEstoque::porProdutoDaUnidade($gUnidade, $gVariacoes->pluck('id')->all()) : [];
    $gNum = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
@endphp
<div class="card shadow-sm mb-3" id="grade" data-grade-quadro>
    <div class="card-body">
    @if(! $gPrincipal)
        <details @if(session('error') && old('atributo')) open @endif>
            <summary class="fw-semibold" style="cursor:pointer"><i class="bi bi-grid-3x3 me-1"></i> Este produto tem tamanhos ou cores? <span class="text-muted fw-normal small">Crie todos de uma vez</span></summary>
            @if($gPode)
            <form method="POST" action="{{ route('app.produtos.grade.gerar', $produto) }}" class="mt-3" data-grade-criar>
                @csrf
                <div class="row g-2">
                    @foreach([['Tamanho', 'P, M, G, GG'], ['Cor', 'Preto, Branco'], ['', '110V, 220V']] as $n => [$gNome, $gExemplo])
                        <div class="col-md-2 col-5"><label class="form-label small mb-1" for="gAt{{ $n }}">{{ $n === 2 ? 'Outra opção' : 'Opção ' . ($n + 1) }}</label><input class="form-control form-control-sm" id="gAt{{ $n }}" name="atributo[{{ $n }}]" maxlength="20" value="{{ old('atributo.' . $n, $n === 0 ? 'Tamanho' : '') }}" placeholder="{{ $gNome ?: 'ex.: Voltagem' }}"></div>
                        <div class="col-md-2 col-7"><label class="form-label small mb-1" for="gVl{{ $n }}">Valores, com vírgula</label><input class="form-control form-control-sm" id="gVl{{ $n }}" name="valores[{{ $n }}]" maxlength="400" value="{{ old('valores.' . $n) }}" placeholder="{{ $gExemplo }}"></div>
                    @endforeach
                </div>
                <div class="form-text mb-2">Ex.: Tamanho "P, M, G" e Cor "Preto, Branco" = 6 produtos: este vira o primeiro (P / Preto) e o sistema cadastra os outros 5 como cópias — mesmo preço, dados fiscais e fotos, cada um com seu código de barras e <strong>estoque zero</strong>. Limite: {{ $G::MAXIMO_VARIACOES }} combinações.</div>
                <button class="btn btn-sm btn-primary" data-confirm="Criar os tamanhos e cores? Este produto vira a primeira combinação e o sistema cadastra um produto novo para cada uma das outras.">Criar tamanhos e cores</button>
            </form>
            @else
                <p class="text-muted small mt-2 mb-0">Seu perfil não cria tamanhos e cores. Peça a quem edita produtos.</p>
            @endif
        </details>
    @else
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h6 class="mb-0"><i class="bi bi-grid-3x3 me-1"></i> Tamanhos e cores de <strong>{{ $G::nomeComum($gPrincipal) }}</strong> <span class="badge text-bg-secondary">{{ $gVariacoes->count() }}</span></h6>
            @if($gPode)
            <div class="d-flex flex-wrap gap-2">
                <form method="POST" action="{{ route('app.produtos.grade.preco', $produto) }}">@csrf
                    <button class="btn btn-sm btn-outline-primary" data-grade-preco data-confirm="Aplicar o preço DESTE produto (R$ {{ number_format((float) $produto->preco_venda, 2, ',', '.') }}, e os preços fixos por forma de pagamento) aos outros {{ $gVariacoes->count() - 1 }}? Salve antes se você acabou de mudar o preço.">Mesmo preço para todos</button></form>
                <form method="POST" action="{{ route('app.produtos.grade.foto', $produto) }}">@csrf
                    <button class="btn btn-sm btn-outline-primary" data-grade-foto @disabled(! $produto->foto) data-confirm="Copiar a foto principal DESTE produto para os outros {{ $gVariacoes->count() - 1 }}? A foto principal deles é trocada.">Mesma foto para todos</button></form>
            </div>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-2" data-grade-tabela>
                <thead class="table-light"><tr><th>Código</th>@foreach($gNomes as $n)<th>{{ $n }}</th>@endforeach<th class="text-end">Preço</th><th class="text-end">Estoque</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach($gVariacoes as $v)
                    @php $gOp = $G::opcoes($v); $gQtd = $gSaldos[$v->id] ?? 0; @endphp
                    <tr @class(['table-active' => $v->id === $produto->id]) data-grade-linha="{{ $v->id }}">
                        <td class="text-nowrap">
                            @if($v->id === $produto->id)<code class="fw-bold">{{ $v->codigo_interno }}</code> <span class="badge text-bg-info">este</span>
                            @else<a href="{{ route('app.produtos.edit', $v) }}#grade"><code>{{ $v->codigo_interno }}</code></a>@endif
                            @if($v->id === $gPrincipal->id) <span class="badge text-bg-primary">principal</span>@endif
                        </td>
                        @foreach($gNomes as $n)<td>{{ $gOp[$n] ?? '—' }}</td>@endforeach
                        <td class="text-end text-nowrap">R$ {{ number_format((float) $v->preco_venda, 2, ',', '.') }}</td>
                        <td class="text-end">@if($gUnidade)<span class="fw-bold {{ $gQtd <= 0 ? 'text-danger' : '' }}">{{ $gNum($gQtd) }}</span>@else — @endif</td>
                        <td><span class="badge bg-{{ $v->status === 'ativo' ? 'success' : 'secondary' }}">{{ ucfirst($v->status) }}</span></td>
                        <td class="text-end">
                            @if($gPode)
                            <form method="POST" action="{{ route('app.produtos.grade.tirar', $produto) }}" class="d-inline">@csrf<input type="hidden" name="variacao_id" value="{{ $v->id }}">
                                <button class="btn btn-sm btn-outline-secondary" data-confirm="{{ $v->id === $gPrincipal->id ? 'Desfazer a grade inteira? Os produtos continuam cadastrados, cada um por si.' : 'Tirar este produto da grade? Ele continua cadastrado.' }}">{{ $v->id === $gPrincipal->id ? 'Desfazer grade' : 'Tirar' }}</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
                @if($gUnidade)
                <tfoot><tr><td colspan="{{ count($gNomes) + 2 }}" class="text-end text-muted small">Total em estoque nesta loja</td><td class="text-end fw-bold" data-grade-total>{{ $gNum(array_sum(array_intersect_key($gSaldos, $gVariacoes->keyBy('id')->all()))) }}</td><td colspan="2"></td></tr></tfoot>
                @endif
            </table>
        </div>
        <p class="form-text mt-0">Cada linha é um produto do sistema, com código de barras, preço e estoque próprios — clique no código para abrir. Produto novo nasce com estoque zero: dê entrada como em qualquer outro.</p>
        @if($gPode)
        <div class="row g-3">
            <div class="col-lg-6">
                <form method="POST" action="{{ route('app.produtos.grade.acrescentar', $produto) }}" class="row g-2 align-items-end" data-grade-acrescentar>
                    @csrf
                    <div class="col-12 small fw-semibold">Chegou tamanho ou cor nova?</div>
                    <div class="col-4"><label class="visually-hidden" for="gAcAt">Opção</label><select class="form-select form-select-sm" id="gAcAt" name="atributo">@foreach($gNomes as $n)<option>{{ $n }}</option>@endforeach</select></div>
                    <div class="col-5"><label class="visually-hidden" for="gAcVl">Valores novos</label><input class="form-control form-control-sm" id="gAcVl" name="valores" maxlength="400" required placeholder="ex.: GG, XG"></div>
                    <div class="col-3"><button class="btn btn-sm btn-outline-primary w-100" data-confirm="Cadastrar os produtos que faltam para os valores novos (cópias do principal, estoque zero)?">Acrescentar</button></div>
                </form>
            </div>
            <div class="col-lg-6">
                <form method="POST" action="{{ route('app.produtos.grade.juntar', $produto) }}" class="row g-2 align-items-end" data-grade-juntar>
                    @csrf
                    <div class="col-12 small fw-semibold">Juntar um produto que já está cadastrado</div>
                    <div class="col"><label class="visually-hidden" for="gJnCod">Código</label><input class="form-control form-control-sm" id="gJnCod" name="codigo" required maxlength="50" placeholder="Código"></div>
                    @foreach($gNomes as $n)<div class="col"><label class="visually-hidden" for="gJn{{ $loop->index }}">{{ $n }}</label><input class="form-control form-control-sm" id="gJn{{ $loop->index }}" name="opcoes[{{ $n }}]" required maxlength="30" placeholder="{{ $n }}"></div>@endforeach
                    <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Juntar</button></div>
                </form>
            </div>
        </div>
        @endif
    @endif
    </div>
</div>
