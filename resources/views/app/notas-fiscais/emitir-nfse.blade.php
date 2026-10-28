@extends('layouts.app')

@section('title', 'Emitir NFS-e')

@section('content')
<x-erp.page-header title="Emitir NFS-e" icon="file-earmark-plus">
    <a href="{{ route('app.notas-fiscais.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
</x-erp.page-header>

<form method="POST" action="{{ route('app.notas-fiscais.emitir-nfse.store') }}" id="form-nfse">
    @csrf

    <div class="row g-4">
        {{-- Dados do Servico --}}
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><i class="bi bi-tools me-1"></i> Dados do Serviço</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="campo-cliente_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                            <select id="campo-cliente_id" name="cliente_id" class="form-select @error('cliente_id') is-invalid @enderror" required>
                                <option value="">Selecione o cliente...</option>
                                @foreach(\App\Models\Cliente::where('empresa_id', session('empresa_id'))->orderBy('nome_razao_social')->get() as $cliente)
                                    <option value="{{ $cliente->id }}" {{ old('cliente_id') == $cliente->id ? 'selected' : '' }}>
                                        {{ $cliente->nome_razao_social }} - {{ $cliente->cpf_cnpj }}
                                    </option>
                                @endforeach
                            </select>
                            @error('cliente_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-12">
                            <label for="campo-descricao" class="form-label">Descrição / Discriminacao do Serviço <span class="text-danger">*</span></label>
                            <textarea id="campo-descricao" name="descricao" class="form-control @error('descricao') is-invalid @enderror" rows="4" required placeholder="Descreva o serviço prestado...">{{ old('descricao') }}</textarea>
                            @error('descricao')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="campo-valor_servico" class="form-label">Valor do Serviço (R$) <span class="text-danger">*</span></label>
                            <input id="campo-valor_servico" type="number" name="valor_servico" class="form-control @error('valor_servico') is-invalid @enderror"
                                   step="0.01" min="0.01" required value="{{ old('valor_servico') }}" placeholder="0,00">
                            @error('valor_servico')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="campo-aliquota_iss" class="form-label">Alíquota ISS (%) <span class="text-danger">*</span></label>
                            <input id="campo-aliquota_iss" type="number" name="aliquota_iss" class="form-control @error('aliquota_iss') is-invalid @enderror"
                                   step="0.01" min="0" max="100" required value="{{ old('aliquota_iss') }}" placeholder="0,00">
                            @error('aliquota_iss')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="valor_iss_display" class="form-label">Valor ISS (R$)</label>
                            <input type="text" class="form-control" id="valor_iss_display" readonly disabled placeholder="Calculado automaticamente">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Preview --}}
        <div class="col-md-4">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white"><i class="bi bi-eye me-1"></i> Resumo</div>
                <div class="card-body">
                    <div class="table-responsive">
                    <table class="table table-borderless table-sm mb-0">
                        <tr>
                            <th class="text-muted">Valor Serviço:</th>
                            <td class="text-end" id="preview-valor">R$ 0,00</td>
                        </tr>
                        <tr>
                            <th class="text-muted">Alíquota ISS:</th>
                            <td class="text-end" id="preview-aliquota">0,00%</td>
                        </tr>
                        <tr>
                            <th class="text-muted">Valor ISS:</th>
                            <td class="text-end" id="preview-iss">R$ 0,00</td>
                        </tr>
                        <tr class="border-top">
                            <th>Valor Liquido:</th>
                            <td class="text-end fw-bold text-success fs-5" id="preview-liquido">R$ 0,00</td>
                        </tr>
                    </table>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2 mt-3">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-send me-1"></i> Emitir NFS-e
                </button>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
function updatePreview() {
    const valor = parseFloat(document.querySelector('[name="valor_servico"]').value) || 0;
    const aliquota = parseFloat(document.querySelector('[name="aliquota_iss"]').value) || 0;
    const iss = valor * (aliquota / 100);
    const liquido = valor - iss;

    const fmt = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

    document.getElementById('preview-valor').textContent = fmt(valor);
    document.getElementById('preview-aliquota').textContent = aliquota.toFixed(2).replace('.', ',') + '%';
    document.getElementById('preview-iss').textContent = fmt(iss);
    document.getElementById('preview-liquido').textContent = fmt(liquido);
    document.getElementById('valor_iss_display').value = fmt(iss);
}

document.querySelector('[name="valor_servico"]')?.addEventListener('input', updatePreview);
document.querySelector('[name="aliquota_iss"]')?.addEventListener('input', updatePreview);
updatePreview();
</script>
@endpush
