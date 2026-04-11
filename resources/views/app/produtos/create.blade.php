@extends('layouts.app')

@section('title', 'Novo Produto')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0"><i class="bi bi-plus-square me-2"></i>Novo Produto</h4>
        <small class="text-muted">Cadastre um novo produto no catalogo</small>
    </div>
    <a href="{{ route('app.produtos.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<form method="POST" action="{{ route('app.produtos.store') }}" enctype="multipart/form-data" id="formProduto" novalidate>
    @csrf

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-0" id="produtoTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="geral-tab" data-bs-toggle="tab" data-bs-target="#geral" type="button" role="tab">
                <i class="bi bi-info-circle me-1"></i> Dados Gerais
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="fiscal-tab" data-bs-toggle="tab" data-bs-target="#fiscal" type="button" role="tab">
                <i class="bi bi-file-earmark-text me-1"></i> Fiscal
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="estoque-tab" data-bs-toggle="tab" data-bs-target="#estoque" type="button" role="tab">
                <i class="bi bi-boxes me-1"></i> Estoque
            </button>
        </li>
    </ul>

    <div class="tab-content border border-top-0 rounded-bottom bg-white shadow-sm p-4 mb-4" id="produtoTabsContent">

        {{-- Tab: Dados Gerais --}}
        <div class="tab-pane fade show active" id="geral" role="tabpanel">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label text-muted">Codigo Interno</label>
                    <input type="text" class="form-control bg-light" value="Automatico" readonly disabled>
                    <div class="form-text">Gerado automaticamente</div>
                </div>
                <div class="col-md-3">
                    <label for="codigo_barras" class="form-label">Codigo de Barras</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-upc"></i></span>
                        <input type="text" name="codigo_barras" id="codigo_barras" class="form-control @error('codigo_barras') is-invalid @enderror" value="{{ old('codigo_barras') }}" placeholder="EAN-13, UPC, etc.">
                        @error('codigo_barras') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="sku" class="form-label">SKU</label>
                    <input type="text" name="sku" id="sku" class="form-control @error('sku') is-invalid @enderror" value="{{ old('sku') }}" placeholder="SKU-001">
                    @error('sku') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-5">
                    <label for="descricao" class="form-label fw-semibold">Descricao <span class="text-danger">*</span></label>
                    <input type="text" name="descricao" id="descricao" class="form-control @error('descricao') is-invalid @enderror" value="{{ old('descricao') }}" placeholder="Nome do produto" required>
                    @error('descricao') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-12">
                    <label for="descricao_detalhada" class="form-label">Descricao Detalhada</label>
                    <textarea name="descricao_detalhada" id="descricao_detalhada" class="form-control @error('descricao_detalhada') is-invalid @enderror" rows="2" placeholder="Detalhes adicionais do produto (opcional)">{{ old('descricao_detalhada') }}</textarea>
                    @error('descricao_detalhada') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="unidade_medida" class="form-label fw-semibold">Unidade de Medida <span class="text-danger">*</span></label>
                    <select name="unidade_medida" id="unidade_medida" class="form-select @error('unidade_medida') is-invalid @enderror" required>
                        <option value="">Selecione</option>
                        @foreach(['UN' => 'Unidade', 'KG' => 'Quilograma', 'CX' => 'Caixa', 'PCT' => 'Pacote', 'LT' => 'Litro', 'MT' => 'Metro', 'M2' => 'Metro Quadrado', 'M3' => 'Metro Cubico', 'PAR' => 'Par', 'JG' => 'Jogo'] as $val => $label)
                            <option value="{{ $val }}" {{ old('unidade_medida') === $val ? 'selected' : '' }}>{{ $val }} - {{ $label }}</option>
                        @endforeach
                    </select>
                    @error('unidade_medida') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label for="categoria_id" class="form-label">Categoria</label>
                    <select name="categoria_id" id="categoria_id" class="form-select @error('categoria_id') is-invalid @enderror">
                        <option value="">Sem categoria</option>
                        @foreach($categorias as $cat)
                            <option value="{{ $cat->id }}" {{ old('categoria_id') == $cat->id ? 'selected' : '' }}>{{ $cat->nome }}</option>
                        @endforeach
                    </select>
                    @error('categoria_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-5">
                    <label class="form-label">Foto do Produto</label>
                    <input type="file" name="foto" id="foto" class="form-control @error('foto') is-invalid @enderror" accept="image/*">
                    @error('foto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">JPG, PNG ou WEBP. Max 2MB.</div>
                </div>

                {{-- Precos --}}
                <div class="col-12">
                    <hr>
                    <h6 class="text-muted mb-3"><i class="bi bi-currency-dollar me-1"></i> Precos e Markup</h6>
                </div>
                <div class="col-md-3">
                    <label for="preco_custo" class="form-label">Preco de Custo (R$)</label>
                    <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="number" name="preco_custo" id="preco_custo" class="form-control @error('preco_custo') is-invalid @enderror" value="{{ old('preco_custo', '0.00') }}" step="0.01" min="0">
                        @error('preco_custo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="markup" class="form-label">Markup (%)</label>
                    <div class="input-group">
                        <input type="number" name="markup" id="markup" class="form-control @error('markup') is-invalid @enderror" value="{{ old('markup', '0.00') }}" step="0.01" min="0">
                        <span class="input-group-text">%</span>
                        @error('markup') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-text">Preco venda = custo x (1 + markup/100)</div>
                </div>
                <div class="col-md-3">
                    <label for="preco_venda" class="form-label fw-semibold">Preco de Venda (R$) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text text-success fw-bold">R$</span>
                        <input type="number" name="preco_venda" id="preco_venda" class="form-control fw-bold @error('preco_venda') is-invalid @enderror" value="{{ old('preco_venda', '0.00') }}" step="0.01" min="0" required>
                        @error('preco_venda') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted">Margem de Lucro</label>
                    <div class="input-group">
                        <input type="text" id="margem_lucro" class="form-control bg-light" readonly disabled value="R$ 0,00">
                        <span class="input-group-text bg-light" id="margem_percentual">0%</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab: Fiscal --}}
        <div class="tab-pane fade" id="fiscal" role="tabpanel">
            <div class="alert alert-info border-0 mb-4">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Importante:</strong> Os dados fiscais sao obrigatorios para emissao de NF-e e NFC-e via Focus NFe.
            </div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="ncm" class="form-label fw-semibold">NCM</label>
                    <input type="text" name="ncm" id="ncm" class="form-control @error('ncm') is-invalid @enderror" value="{{ old('ncm') }}" maxlength="10" placeholder="0000.00.00">
                    @error('ncm') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Nomenclatura Comum do Mercosul</div>
                </div>
                <div class="col-md-3">
                    <label for="cest" class="form-label">CEST</label>
                    <input type="text" name="cest" id="cest" class="form-control @error('cest') is-invalid @enderror" value="{{ old('cest') }}" maxlength="10" placeholder="00.000.00">
                    @error('cest') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Codigo Especificador da Sub. Trib.</div>
                </div>
                <div class="col-md-6">
                    <label for="origem" class="form-label fw-semibold">Origem</label>
                    <select name="origem" id="origem" class="form-select @error('origem') is-invalid @enderror">
                        <option value="">Selecione a origem</option>
                        <option value="0" {{ old('origem') === '0' ? 'selected' : '' }}>0 - Nacional, exceto as indicadas nos codigos 3, 4, 5 e 8</option>
                        <option value="1" {{ old('origem') === '1' ? 'selected' : '' }}>1 - Estrangeira - Importacao direta</option>
                        <option value="2" {{ old('origem') === '2' ? 'selected' : '' }}>2 - Estrangeira - Adquirida no mercado interno</option>
                        <option value="3" {{ old('origem') === '3' ? 'selected' : '' }}>3 - Nacional - Conteudo de importacao superior a 40% e inferior ou igual a 70%</option>
                        <option value="4" {{ old('origem') === '4' ? 'selected' : '' }}>4 - Nacional - Processos produtivos basicos</option>
                        <option value="5" {{ old('origem') === '5' ? 'selected' : '' }}>5 - Nacional - Conteudo de importacao inferior ou igual a 40%</option>
                        <option value="6" {{ old('origem') === '6' ? 'selected' : '' }}>6 - Estrangeira - Importacao direta, sem similar nacional</option>
                        <option value="7" {{ old('origem') === '7' ? 'selected' : '' }}>7 - Estrangeira - Adquirida no mercado interno, sem similar nacional</option>
                        <option value="8" {{ old('origem') === '8' ? 'selected' : '' }}>8 - Nacional - Conteudo de importacao superior a 70%</option>
                    </select>
                    @error('origem') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-3">
                    <label for="cfop" class="form-label fw-semibold">CFOP</label>
                    <input type="text" name="cfop" id="cfop" class="form-control @error('cfop') is-invalid @enderror" value="{{ old('cfop') }}" maxlength="4" placeholder="5102">
                    @error('cfop') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Ex: 5102 (venda merc. adquirida)</div>
                </div>
                <div class="col-md-3">
                    <label for="cst_csosn" class="form-label fw-semibold">CST/CSOSN</label>
                    <input type="text" name="cst_csosn" id="cst_csosn" class="form-control @error('cst_csosn') is-invalid @enderror" value="{{ old('cst_csosn') }}" maxlength="4" placeholder="102">
                    @error('cst_csosn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Codigo de Sit. Tributaria / CSOSN</div>
                </div>

                {{-- Aliquotas --}}
                <div class="col-12">
                    <hr>
                    <h6 class="text-muted mb-3"><i class="bi bi-percent me-1"></i> Aliquotas de Impostos</h6>
                </div>
                <div class="col-md-3">
                    <label for="icms_aliquota" class="form-label">ICMS (%)</label>
                    <div class="input-group">
                        <input type="number" name="icms_aliquota" id="icms_aliquota" class="form-control @error('icms_aliquota') is-invalid @enderror" value="{{ old('icms_aliquota', '0.00') }}" step="0.01" min="0" max="100">
                        <span class="input-group-text">%</span>
                        @error('icms_aliquota') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="pis_aliquota" class="form-label">PIS (%)</label>
                    <div class="input-group">
                        <input type="number" name="pis_aliquota" id="pis_aliquota" class="form-control @error('pis_aliquota') is-invalid @enderror" value="{{ old('pis_aliquota', '0.00') }}" step="0.01" min="0" max="100">
                        <span class="input-group-text">%</span>
                        @error('pis_aliquota') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="cofins_aliquota" class="form-label">COFINS (%)</label>
                    <div class="input-group">
                        <input type="number" name="cofins_aliquota" id="cofins_aliquota" class="form-control @error('cofins_aliquota') is-invalid @enderror" value="{{ old('cofins_aliquota', '0.00') }}" step="0.01" min="0" max="100">
                        <span class="input-group-text">%</span>
                        @error('cofins_aliquota') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="ipi_aliquota" class="form-label">IPI (%)</label>
                    <div class="input-group">
                        <input type="number" name="ipi_aliquota" id="ipi_aliquota" class="form-control @error('ipi_aliquota') is-invalid @enderror" value="{{ old('ipi_aliquota', '0.00') }}" step="0.01" min="0" max="100">
                        <span class="input-group-text">%</span>
                        @error('ipi_aliquota') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab: Estoque --}}
        <div class="tab-pane fade" id="estoque" role="tabpanel">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="estoque_minimo" class="form-label">Estoque Minimo</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-exclamation-triangle"></i></span>
                        <input type="number" name="estoque_minimo" id="estoque_minimo" class="form-control @error('estoque_minimo') is-invalid @enderror" value="{{ old('estoque_minimo', '0') }}" step="0.001" min="0">
                        @error('estoque_minimo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-text">Alerta quando o estoque ficar abaixo deste valor</div>
                </div>
                <div class="col-md-4">
                    <label for="peso_bruto" class="form-label">Peso Bruto (kg)</label>
                    <div class="input-group">
                        <input type="number" name="peso_bruto" id="peso_bruto" class="form-control @error('peso_bruto') is-invalid @enderror" value="{{ old('peso_bruto', '0.000') }}" step="0.001" min="0">
                        <span class="input-group-text">kg</span>
                        @error('peso_bruto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="peso_liquido" class="form-label">Peso Liquido (kg)</label>
                    <div class="input-group">
                        <input type="number" name="peso_liquido" id="peso_liquido" class="form-control @error('peso_liquido') is-invalid @enderror" value="{{ old('peso_liquido', '0.000') }}" step="0.001" min="0">
                        <span class="input-group-text">kg</span>
                        @error('peso_liquido') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Botoes --}}
    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="{{ route('app.produtos.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg me-1"></i> Cancelar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> Salvar Produto
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const precoCusto = document.getElementById('preco_custo');
    const markup = document.getElementById('markup');
    const precoVenda = document.getElementById('preco_venda');
    const margemLucro = document.getElementById('margem_lucro');
    const margemPercentual = document.getElementById('margem_percentual');

    function calcPrecoVenda() {
        const custo = parseFloat(precoCusto.value) || 0;
        const mk = parseFloat(markup.value) || 0;
        if (custo > 0 && mk > 0) {
            precoVenda.value = (custo * (1 + mk / 100)).toFixed(2);
        }
        updateMargem();
    }

    function calcMarkup() {
        const custo = parseFloat(precoCusto.value) || 0;
        const venda = parseFloat(precoVenda.value) || 0;
        if (custo > 0 && venda > custo) {
            markup.value = (((venda - custo) / custo) * 100).toFixed(2);
        } else if (custo === 0 || venda <= custo) {
            markup.value = '0.00';
        }
        updateMargem();
    }

    function updateMargem() {
        const custo = parseFloat(precoCusto.value) || 0;
        const venda = parseFloat(precoVenda.value) || 0;
        const lucro = venda - custo;
        margemLucro.value = 'R$ ' + lucro.toFixed(2).replace('.', ',');
        if (venda > 0) {
            margemPercentual.textContent = ((lucro / venda) * 100).toFixed(1) + '%';
        } else {
            margemPercentual.textContent = '0%';
        }
    }

    precoCusto.addEventListener('input', calcPrecoVenda);
    markup.addEventListener('input', calcPrecoVenda);
    precoVenda.addEventListener('input', calcMarkup);

    // NCM Mask: 0000.00.00
    const ncmInput = document.getElementById('ncm');
    ncmInput.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').substring(0, 8);
        if (v.length > 4) v = v.substring(0, 4) + '.' + v.substring(4);
        if (v.length > 7) v = v.substring(0, 7) + '.' + v.substring(7);
        this.value = v;
    });

    // CEST Mask: 00.000.00
    const cestInput = document.getElementById('cest');
    cestInput.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').substring(0, 7);
        if (v.length > 2) v = v.substring(0, 2) + '.' + v.substring(2);
        if (v.length > 6) v = v.substring(0, 6) + '.' + v.substring(6);
        this.value = v;
    });

    // Show tab with validation errors
    @if($errors->any())
        const errorFields = {!! json_encode($errors->keys()) !!};
        const fiscalFields = ['ncm', 'cest', 'origem', 'cfop', 'cst_csosn', 'icms_aliquota', 'pis_aliquota', 'cofins_aliquota', 'ipi_aliquota'];
        const estoqueFields = ['estoque_minimo', 'peso_bruto', 'peso_liquido'];

        if (errorFields.some(f => fiscalFields.includes(f))) {
            new bootstrap.Tab(document.getElementById('fiscal-tab')).show();
        } else if (errorFields.some(f => estoqueFields.includes(f))) {
            new bootstrap.Tab(document.getElementById('estoque-tab')).show();
        }
    @endif

    // Initial margin calculation
    updateMargem();
});
</script>
@endpush
