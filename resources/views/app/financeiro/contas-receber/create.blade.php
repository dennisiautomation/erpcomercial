@extends('layouts.app')

@section('title', 'Nova Conta a Receber')

@section('content')
<x-erp.page-header title="Nova Conta a Receber" subtitle="Cadastre um novo recebivel com parcelas automaticas" icon="plus-circle">
    <a href="{{ route('app.contas-receber.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

<div class="row">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('app.contas-receber.store') }}">
            @csrf

            <x-erp.form-section title="Dados do Recebivel" icon="cash-stack">
                <div class="mb-4">
                    <label for="cliente_id" class="form-label fw-semibold">Cliente <span class="text-danger">*</span></label>
                    <select name="cliente_id" id="cliente_id" class="form-select form-select-lg @error('cliente_id') is-invalid @enderror" required>
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

                <div class="mb-4">
                    <label for="descricao" class="form-label fw-semibold">Descricao <span class="text-danger">*</span></label>
                    <input type="text" name="descricao" id="descricao"
                        class="form-control @error('descricao') is-invalid @enderror"
                        value="{{ old('descricao') }}" placeholder="Ex: Venda de produtos, Servico prestado..." required>
                    @error('descricao')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="valor" class="form-label fw-semibold">Valor Total <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">R$</span>
                            <input type="number" name="valor" id="valor" step="0.01" min="0.01"
                                class="form-control @error('valor') is-invalid @enderror"
                                value="{{ old('valor') }}" placeholder="0,00" required>
                        </div>
                        @error('valor')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="parcelas" class="form-label fw-semibold">Parcelas <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <input type="number" name="parcelas" id="parcelas" min="1" max="48"
                                class="form-control @error('parcelas') is-invalid @enderror"
                                value="{{ old('parcelas', 1) }}" required>
                            <span class="input-group-text">x</span>
                        </div>
                        @error('parcelas')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="primeiro_vencimento" class="form-label fw-semibold">1o Vencimento <span class="text-danger">*</span></label>
                        <input type="date" name="primeiro_vencimento" id="primeiro_vencimento"
                            class="form-control form-control-lg @error('primeiro_vencimento') is-invalid @enderror"
                            value="{{ old('primeiro_vencimento') }}" required>
                        @error('primeiro_vencimento')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </x-erp.form-section>

            <x-erp.form-section title="Pagamento" icon="credit-card">
                <div class="mb-4">
                    <label for="forma_pagamento" class="form-label fw-semibold">Forma de Pagamento</label>
                    <div class="row g-2" id="forma-pagamento-cards">
                        @php
                            $formas = [
                                'boleto' => ['icon' => 'bi-upc-scan', 'label' => 'Boleto'],
                                'pix' => ['icon' => 'bi-qr-code', 'label' => 'PIX'],
                                'cartao_credito' => ['icon' => 'bi-credit-card', 'label' => 'Cartao'],
                                'transferencia' => ['icon' => 'bi-bank', 'label' => 'TED/DOC'],
                                'dinheiro' => ['icon' => 'bi-cash', 'label' => 'Dinheiro'],
                            ];
                        @endphp
                        @foreach($formas as $value => $forma)
                        <div class="col">
                            <input type="radio" name="forma_pagamento" value="{{ $value }}" id="fp-{{ $value }}" class="btn-check" {{ old('forma_pagamento') == $value ? 'checked' : '' }}>
                            <label class="btn btn-outline-secondary w-100 py-2 text-center" for="fp-{{ $value }}">
                                <i class="bi {{ $forma['icon'] }} d-block mb-1"></i>
                                <span class="small">{{ $forma['label'] }}</span>
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="mb-4">
                    <label for="observacoes" class="form-label fw-semibold">Observacoes</label>
                    <textarea name="observacoes" id="observacoes" rows="3"
                        class="form-control @error('observacoes') is-invalid @enderror"
                        placeholder="Informacoes adicionais...">{{ old('observacoes') }}</textarea>
                    @error('observacoes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <hr class="my-4">

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-erp-primary btn-lg px-4">
                        <i class="bi bi-check-lg me-1"></i> Salvar Conta
                    </button>
                    <a href="{{ route('app.contas-receber.index') }}" class="btn btn-erp-outline btn-lg">Cancelar</a>
                </div>
            </x-erp.form-section>
        </form>
    </div>

    {{-- Parcelas Preview --}}
    <div class="col-lg-5">
        <div id="parcelas-preview" class="erp-card d-none sticky-top" style="top: 1rem;">
            <div class="card-header bg-primary text-white border-0">
                <h6 class="mb-0 fw-bold"><i class="bi bi-list-ol me-1"></i> Previa das Parcelas</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Parcela</th>
                                <th>Vencimento</th>
                                <th class="text-end pe-3">Valor</th>
                            </tr>
                        </thead>
                        <tbody id="parcelas-list"></tbody>
                        <tfoot class="bg-light">
                            <tr class="fw-bold">
                                <td class="ps-3" colspan="2">Total</td>
                                <td class="text-end pe-3" id="parcelas-total">R$ 0,00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div id="parcelas-placeholder" class="erp-card">
            <div class="card-body text-center py-5">
                <i class="bi bi-calculator fs-1 text-muted opacity-25 d-block mb-3"></i>
                <p class="text-muted mb-0">Preencha o valor, numero de parcelas e<br>primeiro vencimento para visualizar.</p>
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
        const placeholder = document.getElementById('parcelas-placeholder');
        const list = document.getElementById('parcelas-list');
        const totalEl = document.getElementById('parcelas-total');

        if (valor > 0 && parcelas > 0 && primeiroVenc) {
            const valorParcela = Math.floor((valor / parcelas) * 100) / 100;
            let html = '';
            let totalCheck = 0;
            let date = new Date(primeiroVenc + 'T12:00:00');

            for (let i = 1; i <= Math.min(parcelas, 48); i++) {
                const vp = i === parcelas ? (valor - (valorParcela * (parcelas - 1))) : valorParcela;
                totalCheck += vp;
                const dateStr = date.toLocaleDateString('pt-BR');
                const valorStr = parseFloat(vp).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});

                html += `<tr>
                    <td class="ps-3">
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill">${i}/${parcelas}</span>
                    </td>
                    <td>${dateStr}</td>
                    <td class="text-end pe-3 fw-semibold">R$ ${valorStr}</td>
                </tr>`;
                date.setMonth(date.getMonth() + 1);
            }

            list.innerHTML = html;
            totalEl.textContent = 'R$ ' + valor.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            preview.classList.remove('d-none');
            placeholder.classList.add('d-none');
        } else {
            preview.classList.add('d-none');
            placeholder.classList.remove('d-none');
        }
    }

    document.getElementById('valor').addEventListener('input', updatePreview);
    document.getElementById('parcelas').addEventListener('input', updatePreview);
    document.getElementById('primeiro_vencimento').addEventListener('change', updatePreview);

    // Trigger on load if values exist
    updatePreview();
</script>
@endpush
