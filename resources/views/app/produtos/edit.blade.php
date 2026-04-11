@extends('layouts.app')

@section('title', 'Editar Produto')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Editar Produto</h4>
    <a href="{{ route('app.produtos.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<form method="POST" action="{{ route('app.produtos.update', $produto) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')

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

    <div class="tab-content border border-top-0 rounded-bottom bg-white p-4 mb-4" id="produtoTabsContent">
        {{-- Tab: Dados Gerais --}}
        <div class="tab-pane fade show active" id="geral" role="tabpanel">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Código Interno</label>
                    <input type="text" class="form-control" value="{{ $produto->codigo_interno }}" readonly disabled>
                </div>
                <div class="col-md-3">
                    <label for="codigo_barras" class="form-label">Código de Barras</label>
                    <input type="text" name="codigo_barras" id="codigo_barras" class="form-control @error('codigo_barras') is-invalid @enderror" value="{{ old('codigo_barras', $produto->codigo_barras) }}">
                    @error('codigo_barras')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label for="sku" class="form-label">SKU</label>
                    <input type="text" name="sku" id="sku" class="form-control @error('sku') is-invalid @enderror" value="{{ old('sku', $produto->sku) }}">
                    @error('sku')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-5">
                    <label for="descricao" class="form-label fw-semibold">Descrição <span class="text-danger">*</span></label>
                    <input type="text" name="descricao" id="descricao" class="form-control @error('descricao') is-invalid @enderror" value="{{ old('descricao', $produto->descricao) }}" required>
                    @error('descricao')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-12">
                    <label for="descricao_detalhada" class="form-label">Descrição Detalhada</label>
                    <textarea name="descricao_detalhada" id="descricao_detalhada" class="form-control @error('descricao_detalhada') is-invalid @enderror" rows="2">{{ old('descricao_detalhada', $produto->descricao_detalhada) }}</textarea>
                    @error('descricao_detalhada')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label for="unidade_medida" class="form-label fw-semibold">Unidade <span class="text-danger">*</span></label>
                    <select name="unidade_medida" id="unidade_medida" class="form-select @error('unidade_medida') is-invalid @enderror" required>
                        <option value="">Selecione</option>
                        @foreach(['UN' => 'Unidade', 'KG' => 'Quilograma', 'L' => 'Litro', 'M' => 'Metro', 'M2' => 'Metro²', 'M3' => 'Metro³', 'CX' => 'Caixa', 'PC' => 'Peça', 'PCT' => 'Pacote', 'FD' => 'Fardo', 'PR' => 'Par'] as $val => $label)
                            <option value="{{ $val }}" {{ old('unidade_medida', $produto->unidade_medida) === $val ? 'selected' : '' }}>{{ $val }} - {{ $label }}</option>
                        @endforeach
                    </select>
                    @error('unidade_medida')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="categoria_id" class="form-label">Categoria</label>
                    <select name="categoria_id" id="categoria_id" class="form-select @error('categoria_id') is-invalid @enderror">
                        <option value="">Sem categoria</option>
                        @foreach($categorias as $cat)
                            <option value="{{ $cat->id }}" {{ old('categoria_id', $produto->categoria_id) == $cat->id ? 'selected' : '' }}>{{ $cat->nome }}</option>
                        @endforeach
                    </select>
                    @error('categoria_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                    <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                        <option value="ativo" {{ old('status', $produto->status) === 'ativo' ? 'selected' : '' }}>Ativo</option>
                        <option value="inativo" {{ old('status', $produto->status) === 'inativo' ? 'selected' : '' }}>Inativo</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <hr>
                    <h6 class="text-muted mb-3"><i class="bi bi-currency-dollar me-1"></i> Preços</h6>
                </div>
                <div class="col-md-3">
                    <label for="preco_custo" class="form-label">Preço de Custo (R$)</label>
                    <input type="number" name="preco_custo" id="preco_custo" class="form-control @error('preco_custo') is-invalid @enderror" value="{{ old('preco_custo', $produto->preco_custo) }}" step="0.01" min="0">
                    @error('preco_custo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="markup" class="form-label">Markup (%)</label>
                    <input type="number" name="markup" id="markup" class="form-control @error('markup') is-invalid @enderror" value="{{ old('markup', $produto->markup) }}" step="0.01" min="0">
                    @error('markup')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="preco_venda" class="form-label fw-semibold">Preço de Venda (R$) <span class="text-danger">*</span></label>
                    <input type="number" name="preco_venda" id="preco_venda" class="form-control @error('preco_venda') is-invalid @enderror" value="{{ old('preco_venda', $produto->preco_venda) }}" step="0.01" min="0" required>
                    @error('preco_venda')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Foto</label>
                    <input type="file" name="foto" class="form-control @error('foto') is-invalid @enderror" accept="image/*">
                    @error('foto')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    @if($produto->foto)
                        <small class="text-muted mt-1 d-block">Foto atual: {{ basename($produto->foto) }}</small>
                    @endif
                </div>
            </div>
        </div>

        {{-- Tab: Fiscal --}}
        <div class="tab-pane fade" id="fiscal" role="tabpanel">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="ncm" class="form-label">NCM</label>
                    <input type="text" name="ncm" id="ncm" class="form-control @error('ncm') is-invalid @enderror" value="{{ old('ncm', $produto->ncm) }}" maxlength="10">
                    @error('ncm')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="cest" class="form-label">CEST</label>
                    <input type="text" name="cest" id="cest" class="form-control @error('cest') is-invalid @enderror" value="{{ old('cest', $produto->cest) }}" maxlength="10">
                    @error('cest')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="origem" class="form-label">Origem</label>
                    <select name="origem" id="origem" class="form-select @error('origem') is-invalid @enderror">
                        <option value="">Selecione</option>
                        <option value="0" {{ old('origem', $produto->origem) === '0' ? 'selected' : '' }}>0 - Nacional</option>
                        <option value="1" {{ old('origem', $produto->origem) === '1' ? 'selected' : '' }}>1 - Estrangeira (importação direta)</option>
                        <option value="2" {{ old('origem', $produto->origem) === '2' ? 'selected' : '' }}>2 - Estrangeira (mercado interno)</option>
                        <option value="3" {{ old('origem', $produto->origem) === '3' ? 'selected' : '' }}>3 - Nacional (import. 40-70%)</option>
                        <option value="4" {{ old('origem', $produto->origem) === '4' ? 'selected' : '' }}>4 - Nacional (proc. básicos)</option>
                        <option value="5" {{ old('origem', $produto->origem) === '5' ? 'selected' : '' }}>5 - Nacional (import. < 40%)</option>
                        <option value="6" {{ old('origem', $produto->origem) === '6' ? 'selected' : '' }}>6 - Estrangeira (sem similar)</option>
                        <option value="7" {{ old('origem', $produto->origem) === '7' ? 'selected' : '' }}>7 - Estrangeira (c/ similar)</option>
                        <option value="8" {{ old('origem', $produto->origem) === '8' ? 'selected' : '' }}>8 - Nacional (import. > 70%)</option>
                    </select>
                    @error('origem')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="cfop" class="form-label">CFOP</label>
                    <input type="text" name="cfop" id="cfop" class="form-control @error('cfop') is-invalid @enderror" value="{{ old('cfop', $produto->cfop) }}" maxlength="10">
                    @error('cfop')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="cst_csosn" class="form-label">CST/CSOSN</label>
                    <input type="text" name="cst_csosn" id="cst_csosn" class="form-control @error('cst_csosn') is-invalid @enderror" value="{{ old('cst_csosn', $produto->cst_csosn) }}" maxlength="10">
                    @error('cst_csosn')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <hr>
                    <h6 class="text-muted mb-3"><i class="bi bi-percent me-1"></i> Alíquotas</h6>
                </div>

                <div class="col-md-3">
                    <label for="icms_aliquota" class="form-label">ICMS (%)</label>
                    <input type="number" name="icms_aliquota" id="icms_aliquota" class="form-control @error('icms_aliquota') is-invalid @enderror" value="{{ old('icms_aliquota', $produto->icms_aliquota) }}" step="0.01" min="0" max="100">
                    @error('icms_aliquota')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="pis_aliquota" class="form-label">PIS (%)</label>
                    <input type="number" name="pis_aliquota" id="pis_aliquota" class="form-control @error('pis_aliquota') is-invalid @enderror" value="{{ old('pis_aliquota', $produto->pis_aliquota) }}" step="0.01" min="0" max="100">
                    @error('pis_aliquota')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="cofins_aliquota" class="form-label">COFINS (%)</label>
                    <input type="number" name="cofins_aliquota" id="cofins_aliquota" class="form-control @error('cofins_aliquota') is-invalid @enderror" value="{{ old('cofins_aliquota', $produto->cofins_aliquota) }}" step="0.01" min="0" max="100">
                    @error('cofins_aliquota')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="ipi_aliquota" class="form-label">IPI (%)</label>
                    <input type="number" name="ipi_aliquota" id="ipi_aliquota" class="form-control @error('ipi_aliquota') is-invalid @enderror" value="{{ old('ipi_aliquota', $produto->ipi_aliquota) }}" step="0.01" min="0" max="100">
                    @error('ipi_aliquota')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Tab: Estoque --}}
        <div class="tab-pane fade" id="estoque" role="tabpanel">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="estoque_minimo" class="form-label">Estoque Mínimo</label>
                    <input type="number" name="estoque_minimo" id="estoque_minimo" class="form-control @error('estoque_minimo') is-invalid @enderror" value="{{ old('estoque_minimo', $produto->estoque_minimo) }}" step="0.001" min="0">
                    @error('estoque_minimo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="peso_bruto" class="form-label">Peso Bruto (kg)</label>
                    <input type="number" name="peso_bruto" id="peso_bruto" class="form-control @error('peso_bruto') is-invalid @enderror" value="{{ old('peso_bruto', $produto->peso_bruto) }}" step="0.001" min="0">
                    @error('peso_bruto')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="peso_liquido" class="form-label">Peso Líquido (kg)</label>
                    <input type="number" name="peso_liquido" id="peso_liquido" class="form-control @error('peso_liquido') is-invalid @enderror" value="{{ old('peso_liquido', $produto->peso_liquido) }}" step="0.001" min="0">
                    @error('peso_liquido')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('app.produtos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> Atualizar Produto
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

        function calcPrecoVenda() {
            const custo = parseFloat(precoCusto.value) || 0;
            const mk = parseFloat(markup.value) || 0;
            if (custo > 0 && mk > 0) {
                precoVenda.value = (custo * (1 + mk / 100)).toFixed(2);
            }
        }

        function calcMarkup() {
            const custo = parseFloat(precoCusto.value) || 0;
            const venda = parseFloat(precoVenda.value) || 0;
            if (custo > 0 && venda > 0) {
                markup.value = (((venda - custo) / custo) * 100).toFixed(2);
            }
        }

        precoCusto.addEventListener('input', calcPrecoVenda);
        markup.addEventListener('input', calcPrecoVenda);
        precoVenda.addEventListener('input', calcMarkup);
    });
</script>
@endpush
