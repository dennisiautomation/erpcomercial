@extends('layouts.app')

@section('title', 'Nova Conta a Receber')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Nova Conta a Receber</h4>
    <a href="{{ route('app.contas-receber.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('app.contas-receber.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="cliente_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                        <select name="cliente_id" id="cliente_id" class="form-select @error('cliente_id') is-invalid @enderror" required>
                            <option value="">Selecione o cliente...</option>
                            @foreach($clientes as $cliente)
                                <option value="{{ $cliente->id }}" {{ old('cliente_id') == $cliente->id ? 'selected' : '' }}>
                                    {{ $cliente->nome_razao_social }}
                                </option>
                            @endforeach
                        </select>
                        @error('cliente_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descricao <span class="text-danger">*</span></label>
                        <input type="text" name="descricao" id="descricao"
                            class="form-control @error('descricao') is-invalid @enderror"
                            value="{{ old('descricao') }}" required>
                        @error('descricao')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="valor" class="form-label">Valor Total <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" name="valor" id="valor" step="0.01" min="0.01"
                                    class="form-control @error('valor') is-invalid @enderror"
                                    value="{{ old('valor') }}" required>
                            </div>
                            @error('valor')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="parcelas" class="form-label">Parcelas <span class="text-danger">*</span></label>
                            <input type="number" name="parcelas" id="parcelas" min="1" max="48"
                                class="form-control @error('parcelas') is-invalid @enderror"
                                value="{{ old('parcelas', 1) }}" required>
                            @error('parcelas')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="primeiro_vencimento" class="form-label">Primeiro Vencimento <span class="text-danger">*</span></label>
                            <input type="date" name="primeiro_vencimento" id="primeiro_vencimento"
                                class="form-control @error('primeiro_vencimento') is-invalid @enderror"
                                value="{{ old('primeiro_vencimento') }}" required>
                            @error('primeiro_vencimento')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Preview parcelas --}}
                    <div id="parcelas-preview" class="alert alert-light d-none mb-3">
                        <h6><i class="bi bi-list-ol me-1"></i>Previa das Parcelas</h6>
                        <div id="parcelas-list"></div>
                    </div>

                    <div class="mb-3">
                        <label for="forma_pagamento" class="form-label">Forma de Pagamento</label>
                        <select name="forma_pagamento" id="forma_pagamento" class="form-select">
                            <option value="">Selecione...</option>
                            <option value="boleto" {{ old('forma_pagamento') == 'boleto' ? 'selected' : '' }}>Boleto</option>
                            <option value="pix" {{ old('forma_pagamento') == 'pix' ? 'selected' : '' }}>PIX</option>
                            <option value="cartao_credito" {{ old('forma_pagamento') == 'cartao_credito' ? 'selected' : '' }}>Cartao de Credito</option>
                            <option value="transferencia" {{ old('forma_pagamento') == 'transferencia' ? 'selected' : '' }}>Transferencia</option>
                            <option value="dinheiro" {{ old('forma_pagamento') == 'dinheiro' ? 'selected' : '' }}>Dinheiro</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="observacoes" class="form-label">Observacoes</label>
                        <textarea name="observacoes" id="observacoes" rows="3"
                            class="form-control @error('observacoes') is-invalid @enderror">{{ old('observacoes') }}</textarea>
                        @error('observacoes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Salvar
                        </button>
                        <a href="{{ route('app.contas-receber.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function updatePreview() {
        const valor = parseFloat(document.getElementById('valor').value) || 0;
        const parcelas = parseInt(document.getElementById('parcelas').value) || 1;
        const primeiroVenc = document.getElementById('primeiro_vencimento').value;
        const preview = document.getElementById('parcelas-preview');
        const list = document.getElementById('parcelas-list');

        if (valor > 0 && parcelas > 0 && primeiroVenc) {
            const valorParcela = (valor / parcelas).toFixed(2);
            let html = '<table class="table table-sm mb-0"><thead><tr><th>Parcela</th><th>Vencimento</th><th class="text-end">Valor</th></tr></thead><tbody>';

            let date = new Date(primeiroVenc + 'T12:00:00');
            for (let i = 1; i <= parcelas; i++) {
                const vp = i === parcelas ? (valor - (valorParcela * (parcelas - 1))).toFixed(2) : valorParcela;
                html += `<tr><td>${i}/${parcelas}</td><td>${date.toLocaleDateString('pt-BR')}</td><td class="text-end">R$ ${parseFloat(vp).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td></tr>`;
                date.setMonth(date.getMonth() + 1);
            }
            html += '</tbody></table>';
            list.innerHTML = html;
            preview.classList.remove('d-none');
        } else {
            preview.classList.add('d-none');
        }
    }

    document.getElementById('valor').addEventListener('input', updatePreview);
    document.getElementById('parcelas').addEventListener('input', updatePreview);
    document.getElementById('primeiro_vencimento').addEventListener('change', updatePreview);
</script>
@endpush
