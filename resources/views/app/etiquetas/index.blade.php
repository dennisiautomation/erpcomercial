@extends('layouts.app')

@section('title', 'Gerar Etiquetas')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0"><i class="bi bi-upc me-2"></i>Gerar Etiquetas</h4>
        <small class="text-muted">Selecione os produtos e gere etiquetas com codigo de barras</small>
    </div>
</div>

<form id="formEtiquetas" method="POST" action="{{ route('app.etiquetas.gerar') }}" target="_blank">
    @csrf

    {{-- Formato --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-grid me-2"></i>Formato da Etiqueta</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="formato" id="formato2x5" value="2x5" checked>
                        <label class="form-check-label" for="formato2x5">
                            <strong>2 x 5</strong> — 10 etiquetas por pagina (grande)
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="formato" id="formato3x7" value="3x7">
                        <label class="form-check-label" for="formato3x7">
                            <strong>3 x 7</strong> — 21 etiquetas por pagina (media)
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="formato" id="formato4x10" value="4x10">
                        <label class="form-check-label" for="formato4x10">
                            <strong>4 x 10</strong> — 40 etiquetas por pagina (pequena)
                        </label>
                    </div>
                </div>
            </div>
            <hr class="my-3">
            <h6 class="mb-2"><i class="bi bi-printer me-1"></i>Impressora Térmica (bobina — Tomate, Elgin, Zebra...)</h6>
            <div class="row g-2">
                <div class="col-md-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="formato" id="formatoT4025" value="termica-40x25">
                        <label class="form-check-label" for="formatoT4025">
                            <strong>40 × 25 mm</strong> — 1 coluna
                        </label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="formato" id="formatoT5030" value="termica-50x30">
                        <label class="form-check-label" for="formatoT5030">
                            <strong>50 × 30 mm</strong> — 1 coluna
                        </label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="formato" id="formatoT6040" value="termica-60x40">
                        <label class="form-check-label" for="formatoT6040">
                            <strong>60 × 40 mm</strong> — 1 coluna
                        </label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="formato" id="formatoT3322" value="termica-33x22">
                        <label class="form-check-label" for="formatoT3322">
                            <strong>33 × 22 mm</strong> — 2 colunas
                        </label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="formato" id="formatoT3620" value="termica-36x20-2col">
                        <label class="form-check-label" for="formatoT3620">
                            <strong>36 × 20 mm</strong> — 2 colunas (bobina 74 mm, Argox)
                        </label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="formato" id="formatoTag3560" value="termica-tag-35x60">
                        <label class="form-check-label" for="formatoTag3560">
                            <strong>Tag Roupa 35 × 60 mm</strong> — 3 colunas (bobina 105 mm, com furo)
                        </label>
                    </div>
                </div>
            </div>
            <p class="text-muted small mt-2 mb-0">
                <i class="bi bi-info-circle me-1"></i>Na impressão, selecione a impressora térmica e desative margens ("Margens: Nenhuma") no diálogo do navegador.
            </p>

            {{-- Formatos da própria empresa. Nasce recolhido: quem já imprime nos
                 formatos de sempre não vê diferença nenhuma na tela. --}}
            @if($formatosPersonalizados->isNotEmpty())
                <hr class="my-3">
                <h6 class="mb-2"><i class="bi bi-rulers me-1"></i>Meus formatos</h6>
                <div class="row g-2">
                    @foreach($formatosPersonalizados as $fmt)
                        <div class="col-md-6">
                            <div class="form-check d-flex align-items-start justify-content-between">
                                <div>
                                    <input class="form-check-input" type="radio" name="formato" id="formatoCustom{{ $fmt->id }}" value="{{ $fmt->chave }}">
                                    <label class="form-check-label" for="formatoCustom{{ $fmt->id }}">
                                        <strong>{{ $fmt->nome }}</strong><br>
                                        <span class="text-muted small">{{ $fmt->resumo }}</span>
                                    </label>
                                </div>
                                <button type="submit" form="formExcluirFormato{{ $fmt->id }}"
                                        class="btn btn-sm btn-link text-danger p-0 ms-2"
                                        data-confirm="Excluir o formato &quot;{{ $fmt->nome }}&quot;?"
                                        title="Excluir formato">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <hr class="my-3">
            <a class="small text-decoration-none" data-bs-toggle="collapse" href="#novoFormatoEtiqueta" role="button">
                <i class="bi bi-plus-circle me-1"></i>Cadastrar o formato da minha bobina
            </a>
            @php
                // Erro de validação do cadastro reabre o painel — senão a mensagem
                // apareceria dentro de um bloco recolhido e o lojista não veria o motivo.
                // $errors pode ser null fora de um request HTTP (armadilha 10).
                $bag = $errors ?? new \Illuminate\Support\ViewErrorBag();
                $errosFormato = collect(['nome', 'largura_cm', 'altura_cm', 'colunas', 'espaco_cm', 'bobina_cm'])
                    ->filter(fn ($campo) => $bag->has($campo));
            @endphp
            <div class="collapse mt-3 {{ $errosFormato->isNotEmpty() ? 'show' : '' }}" id="novoFormatoEtiqueta">
                <div class="border rounded p-3 bg-light">
                    @if($errosFormato->isNotEmpty())
                        <div class="alert alert-danger py-2 small mb-3">
                            <ul class="mb-0 ps-3">
                                @foreach($errosFormato as $campo)
                                    <li>{{ $bag->first($campo) }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <p class="small text-muted mb-3">
                        Meça a etiqueta com uma régua e informe <strong>em centímetros</strong>.
                        O <em>espaço entre colunas</em> é a folga entre uma etiqueta e a vizinha
                        (0 se elas se encostam). Imprima 1 etiqueta de teste antes de rodar o lote —
                        se sair desalinhada, é só corrigir a medida aqui.
                    </p>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Nome do formato <span class="text-danger">*</span></label>
                            <input type="text" name="nome" form="formNovoFormato" class="form-control form-control-sm"
                                   maxlength="60" placeholder="Ex.: Elgin 3 colunas" value="{{ old('nome') }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Largura (cm) <span class="text-danger">*</span></label>
                            <input type="text" name="largura_cm" id="fmtLargura" form="formNovoFormato" class="form-control form-control-sm"
                                   inputmode="decimal" placeholder="3,2" value="{{ old('largura_cm') }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Altura (cm) <span class="text-danger">*</span></label>
                            <input type="text" name="altura_cm" form="formNovoFormato" class="form-control form-control-sm"
                                   inputmode="decimal" placeholder="2,5" value="{{ old('altura_cm') }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Colunas <span class="text-danger">*</span></label>
                            <input type="number" name="colunas" id="fmtColunas" form="formNovoFormato" class="form-control form-control-sm"
                                   min="1" max="6" value="{{ old('colunas', 1) }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Espaço entre colunas (cm)</label>
                            <input type="text" name="espaco_cm" id="fmtEspaco" form="formNovoFormato" class="form-control form-control-sm"
                                   inputmode="decimal" placeholder="0,2" value="{{ old('espaco_cm', '0,2') }}">
                        </div>
                    </div>
                    {{-- A conta que ninguém faz de cabeça: colunas × largura + espaços
                         TEM que caber na bobina. Formato mais largo que o papel faz a
                         impressora encolher, cortar ou girar a etiqueta. --}}
                    <div class="row g-2 mt-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small mb-1">
                                Largura da bobina (cm)
                                <i class="bi bi-question-circle text-muted" title="A medida do papel, não da etiqueta. Está nas preferências da impressora, em 'Papel de etiquetas'."></i>
                            </label>
                            <input type="text" name="bobina_cm" id="fmtBobina" form="formNovoFormato"
                                   class="form-control form-control-sm @error('bobina_cm') is-invalid @enderror"
                                   inputmode="decimal" placeholder="7,0" value="{{ old('bobina_cm') }}">
                            @error('bobina_cm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-9">
                            <div id="fmtConferencia" class="alert alert-secondary py-2 px-3 mb-0 small">
                                Preencha as medidas para eu conferir se cabem na bobina.
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="mostrar_empresa" value="1"
                                   form="formNovoFormato" id="mostrarEmpresaFormato" {{ old('mostrar_empresa') ? 'checked' : '' }}>
                            <label class="form-check-label small" for="mostrarEmpresaFormato">
                                Imprimir nome/logo da empresa (só cabe a partir de 2,2 cm de altura)
                            </label>
                        </div>
                        <button type="submit" form="formNovoFormato" class="btn btn-sm btn-primary">
                            <i class="bi bi-check2 me-1"></i>Salvar formato
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Busca e selecao --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-box-seam me-2"></i>Produtos</h6>
            <div class="d-flex gap-2 align-items-center">
                <input type="text" id="buscaProduto" class="form-control form-control-sm" placeholder="Filtrar produtos..." style="width: 300px;">
                <button type="button" id="btnSelecionarTodos" class="btn btn-sm btn-outline-secondary">Selecionar Todos</button>
                <button type="button" id="btnLimparSelecao" class="btn btn-sm btn-outline-secondary">Limpar</button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th style="width: 50px;" class="text-center">
                                <i class="bi bi-check2-square"></i>
                            </th>
                            <th style="width: 100px;">Codigo</th>
                            <th>Descricao</th>
                            <th style="width: 150px;">Cod. Barras</th>
                            <th class="text-end" style="width: 120px;">Preco</th>
                            <th class="text-center" style="width: 120px;">Qtd. Etiquetas</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaProdutos">
                        @foreach($produtos as $index => $produto)
                            <tr class="produto-row" data-descricao="{{ strtolower($produto->descricao) }}" data-codigo="{{ strtolower($produto->codigo_interno ?? '') }}" data-barras="{{ strtolower($produto->codigo_barras ?? '') }}">
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input produto-check" data-index="{{ $index }}" value="{{ $produto->id }}">
                                </td>
                                <td><code class="fw-bold">{{ $produto->codigo_interno }}</code></td>
                                <td>{{ $produto->descricao }}</td>
                                <td>
                                    @if($produto->codigo_barras)
                                        <small class="text-muted">{{ $produto->codigo_barras }}</small>
                                    @else
                                        <small class="text-muted">-</small>
                                    @endif
                                </td>
                                <td class="text-end fw-bold text-success">R$ {{ number_format($produto->preco_venda, 2, ',', '.') }}</td>
                                <td class="text-center">
                                    <input type="number" class="form-control form-control-sm text-center qtd-input" min="1" max="100" value="1" disabled style="width: 80px; margin: 0 auto;">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Resumo e botao --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <span class="text-muted">Produtos selecionados: <strong id="contadorProdutos">0</strong></span>
                <span class="text-muted ms-3">Total de etiquetas: <strong id="contadorEtiquetas">0</strong></span>
            </div>
            <button type="submit" class="btn btn-primary btn-lg" id="btnGerar" disabled>
                <i class="bi bi-printer me-2"></i>Gerar Etiquetas
            </button>
        </div>
    </div>

    {{-- Hidden inputs dinamicos --}}
    <div id="hiddenInputs"></div>
</form>

{{-- Forms dos formatos personalizados. Ficam FORA do #formEtiquetas (HTML não
     aceita form aninhado) — os campos lá em cima apontam para cá via form="". --}}
<form id="formNovoFormato" method="POST" action="{{ route('app.etiquetas.formatos.store') }}" class="d-none">
    @csrf
</form>
@foreach($formatosPersonalizados as $fmt)
    <form id="formExcluirFormato{{ $fmt->id }}" method="POST" action="{{ route('app.etiquetas.formatos.destroy', $fmt) }}" class="d-none">
        @csrf
        @method('DELETE')
    </form>
@endforeach

@if($produtos->isEmpty())
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>Nenhum produto ativo encontrado. <a href="{{ route('app.produtos.create') }}">Cadastre um produto</a> primeiro.
    </div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const buscaInput = document.getElementById('buscaProduto');
    const rows = document.querySelectorAll('.produto-row');
    const btnGerar = document.getElementById('btnGerar');
    const contadorProdutos = document.getElementById('contadorProdutos');
    const contadorEtiquetas = document.getElementById('contadorEtiquetas');
    const hiddenInputs = document.getElementById('hiddenInputs');
    const btnSelecionarTodos = document.getElementById('btnSelecionarTodos');
    const btnLimparSelecao = document.getElementById('btnLimparSelecao');

    // Filtro de busca
    buscaInput.addEventListener('input', function() {
        const termo = this.value.toLowerCase();
        rows.forEach(row => {
            const desc = row.dataset.descricao;
            const cod = row.dataset.codigo;
            const barras = row.dataset.barras;
            const match = desc.includes(termo) || cod.includes(termo) || barras.includes(termo);
            row.style.display = match ? '' : 'none';
        });
    });

    // Selecionar todos visiveis
    btnSelecionarTodos.addEventListener('click', function() {
        rows.forEach(row => {
            if (row.style.display !== 'none') {
                const check = row.querySelector('.produto-check');
                const qtd = row.querySelector('.qtd-input');
                check.checked = true;
                qtd.disabled = false;
            }
        });
        atualizarContadores();
    });

    // Limpar selecao
    btnLimparSelecao.addEventListener('click', function() {
        document.querySelectorAll('.produto-check').forEach(check => {
            check.checked = false;
        });
        document.querySelectorAll('.qtd-input').forEach(qtd => {
            qtd.disabled = true;
            qtd.value = 1;
        });
        atualizarContadores();
    });

    // Toggle checkbox
    document.querySelectorAll('.produto-check').forEach(check => {
        check.addEventListener('change', function() {
            const row = this.closest('tr');
            const qtd = row.querySelector('.qtd-input');
            qtd.disabled = !this.checked;
            if (!this.checked) qtd.value = 1;
            atualizarContadores();
        });
    });

    // Atualizar ao mudar quantidade
    document.querySelectorAll('.qtd-input').forEach(input => {
        input.addEventListener('input', atualizarContadores);
    });

    function atualizarContadores() {
        const checks = document.querySelectorAll('.produto-check:checked');
        let totalEtiquetas = 0;

        checks.forEach(check => {
            const row = check.closest('tr');
            const qtd = parseInt(row.querySelector('.qtd-input').value) || 1;
            totalEtiquetas += qtd;
        });

        contadorProdutos.textContent = checks.length;
        contadorEtiquetas.textContent = totalEtiquetas;
        btnGerar.disabled = checks.length === 0;
    }

    // Montar hidden inputs antes de submeter
    document.getElementById('formEtiquetas').addEventListener('submit', function(e) {
        hiddenInputs.innerHTML = '';
        const checks = document.querySelectorAll('.produto-check:checked');

        if (checks.length === 0) {
            e.preventDefault();
            alert('Selecione pelo menos um produto.');
            return;
        }

        // 1 input por produto (produtos[<id>] = qtd) — o formato antigo com 2 inputs
        // estourava o max_input_vars do PHP ao selecionar todos os produtos.
        checks.forEach((check) => {
            const row = check.closest('tr');
            const qtd = parseInt(row.querySelector('.qtd-input').value, 10) || 1;

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `produtos[${check.value}]`;
            input.value = Math.min(100, Math.max(1, qtd));
            hiddenInputs.appendChild(input);
        });
    });
});
</script>

/* --- Conferência da bobina: colunas x largura + espaços TEM que caber ------
   Formato mais largo que o papel faz a impressora encolher, cortar ou girar.
   Mostrar a conta enquanto digita evita descobrir isso na etiqueta impressa. */
(function () {
    const elLargura = document.getElementById('fmtLargura');
    const elColunas = document.getElementById('fmtColunas');
    const elEspaco  = document.getElementById('fmtEspaco');
    const elBobina  = document.getElementById('fmtBobina');
    const saida     = document.getElementById('fmtConferencia');
    if (!elLargura || !saida) return;

    const num = el => parseFloat(String(el?.value ?? '').replace(',', '.')) || 0;
    const br  = v => v.toFixed(1).replace('.', ',');

    function conferir() {
        const largura = num(elLargura), colunas = parseInt(elColunas.value, 10) || 0;
        const espaco = num(elEspaco), bobina = num(elBobina);

        if (!largura || !colunas) {
            saida.className = 'alert alert-secondary py-2 px-3 mb-0 small';
            saida.innerHTML = 'Preencha as medidas para eu conferir se cabem na bobina.';
            return;
        }

        const exigido = colunas * largura + Math.max(0, colunas - 1) * espaco;
        const conta = `${colunas} × ${br(largura)} cm` + (espaco > 0 && colunas > 1 ? ` + ${colunas - 1} espaço(s) de ${br(espaco)} cm` : '');

        if (!bobina) {
            saida.className = 'alert alert-info py-2 px-3 mb-0 small';
            saida.innerHTML = `${conta} = <strong>bobina de ${br(exigido)} cm</strong>. Informe a largura da sua bobina para eu conferir.`;
            return;
        }

        if (exigido > bobina + 0.001) {
            const cabem = Math.max(1, Math.floor((bobina + espaco) / (largura + espaco)));
            saida.className = 'alert alert-danger py-2 px-3 mb-0 small';
            saida.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i>${conta} = <strong>${br(exigido)} cm</strong>, mas a bobina tem <strong>${br(bobina)} cm</strong>. Não cabe — a impressora vai encolher ou cortar. Nessa bobina cabem <strong>${cabem} coluna(s)</strong>.`;
        } else {
            const sobra = bobina - exigido;
            saida.className = 'alert alert-success py-2 px-3 mb-0 small';
            saida.innerHTML = `<i class="bi bi-check-circle me-1"></i>${conta} = <strong>${br(exigido)} cm</strong> — cabe na bobina de ${br(bobina)} cm` + (sobra > 0.05 ? ` (sobra ${br(sobra)} cm).` : '.');
        }
    }

    [elLargura, elColunas, elEspaco, elBobina].forEach(el => el && el.addEventListener('input', conferir));
    conferir();
})();

@endpush
