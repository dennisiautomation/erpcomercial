@extends('layouts.app')

@section('title', 'Importar OFX')

@section('content')
<x-erp.page-header title="Importar Extrato OFX" icon="upload">
    <a href="{{ route('app.conciliacao.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

<form action="{{ route('app.conciliacao.store') }}" method="POST" enctype="multipart/form-data">
    @csrf

    <x-erp.form-section title="Dados do Extrato" icon="file-earmark-arrow-up">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="banco" class="form-label">Banco <span class="text-danger">*</span></label>
                <input type="text" name="banco" id="banco" class="form-control @error('banco') is-invalid @enderror" value="{{ old('banco') }}" placeholder="Ex: Banco do Brasil, Itau, Bradesco..." required>
                @error('banco') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label for="arquivo_ofx" class="form-label">Arquivo OFX <span class="text-danger">*</span></label>
                <input type="file" name="arquivo_ofx" id="arquivo_ofx" class="form-control @error('arquivo_ofx') is-invalid @enderror" accept=".ofx,.OFX" required>
                @error('arquivo_ofx') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">Arquivo no formato OFX exportado do seu banco. Tamanho maximo: 5MB.</div>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-erp-primary">
                <i class="bi bi-upload me-1"></i> Importar Extrato
            </button>
        </div>
    </x-erp.form-section>
</form>

<x-erp.card title="Como funciona" icon="info-circle" class="mt-4">
    <ol class="mb-0">
        <li>Acesse o Internet Banking do seu banco e exporte o extrato no formato OFX.</li>
        <li>Importe o arquivo aqui. O sistema ira extrair todas as transacoes automaticamente.</li>
        <li>Na tela de conciliacao, vincule cada transacao a uma conta a pagar ou receber.</li>
        <li>Use a conciliacao automatica para vincular transacoes por valor e data.</li>
    </ol>
</x-erp.card>
@endsection
