@extends('layouts.app')

@section('title', 'Abrir Caixa')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow">
            <div class="card-header bg-success text-white text-center">
                <h5 class="mb-0"><i class="bi bi-unlock me-2"></i>Abrir Caixa</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('app.caixa.abrir') }}">
                    @csrf

                    <div class="text-center mb-4">
                        <i class="bi bi-cash-stack" style="font-size:3rem; color:#198754;"></i>
                        <p class="text-muted mt-2">Informe os dados para abertura do caixa</p>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Numero do Caixa</label>
                        <input type="number" name="numero_caixa" class="form-control form-control-lg text-center"
                            value="{{ old('numero_caixa', 1) }}" min="1" required autofocus>
                        @error('numero_caixa')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Valor de Abertura (Troco Inicial)</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">R$</span>
                            <input type="number" name="valor_abertura" class="form-control text-center"
                                value="{{ old('valor_abertura', '0.00') }}" step="0.01" min="0" required>
                        </div>
                        <small class="text-muted">Valor em dinheiro disponivel no caixa</small>
                        @error('valor_abertura')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <div class="bg-light rounded p-3">
                            <div class="d-flex justify-content-between small">
                                <span class="text-muted">Operador:</span>
                                <strong>{{ auth()->user()->name }}</strong>
                            </div>
                            <div class="d-flex justify-content-between small mt-1">
                                <span class="text-muted">Data/Hora:</span>
                                <strong>{{ now()->format('d/m/Y H:i') }}</strong>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success btn-lg w-100">
                        <i class="bi bi-unlock me-2"></i>Abrir Caixa
                    </button>

                    <div class="text-center mt-3">
                        <a href="{{ route('app.dashboard') }}" class="text-muted text-decoration-none">
                            <i class="bi bi-arrow-left me-1"></i>Voltar ao Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
