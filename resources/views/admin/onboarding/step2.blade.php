@extends('admin.onboarding.layout', ['step' => 2])

@section('title', 'Onboarding - Unidade')

@section('step-content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-shop me-2"></i>Primeira Unidade (Matriz)</h5>
                <p class="text-muted mb-0 small mt-1">Dados da unidade principal da empresa <strong>{{ $empresa->razao_social }}</strong></p>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.onboarding.step2.store') }}" class="erp-form">
                    @csrf

                    <div class="row g-3">
                        {{-- Nome --}}
                        <div class="col-md-6">
                            <label for="nome" class="form-label fw-semibold">Nome da Unidade <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('nome') is-invalid @enderror"
                                   id="nome" name="nome" value="{{ old('nome', 'Matriz') }}" required>
                            @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- CNPJ --}}
                        <div class="col-md-6">
                            <label for="cnpj" class="form-label fw-semibold">CNPJ</label>
                            <input type="text" class="form-control @error('cnpj') is-invalid @enderror"
                                   id="cnpj" name="cnpj" value="{{ old('cnpj', $empresa->cnpj) }}"
                                   data-mask="cnpj">
                            @error('cnpj')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <hr class="my-2">

                        {{-- CEP --}}
                        <div class="col-md-3">
                            <label for="cep" class="form-label fw-semibold">CEP</label>
                            <input type="text" class="form-control @error('cep') is-invalid @enderror"
                                   id="cep" name="cep" value="{{ old('cep', $empresa->cep) }}"
                                   data-cep placeholder="00000-000">
                            @error('cep')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Logradouro --}}
                        <div class="col-md-6">
                            <label for="logradouro" class="form-label fw-semibold">Logradouro</label>
                            <input type="text" class="form-control @error('logradouro') is-invalid @enderror"
                                   id="logradouro" name="logradouro" value="{{ old('logradouro', $empresa->logradouro) }}">
                            @error('logradouro')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Numero --}}
                        <div class="col-md-3">
                            <label for="numero" class="form-label fw-semibold">Numero</label>
                            <input type="text" class="form-control @error('numero') is-invalid @enderror"
                                   id="numero" name="numero" value="{{ old('numero', $empresa->numero) }}">
                            @error('numero')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Complemento --}}
                        <div class="col-md-3">
                            <label for="complemento" class="form-label fw-semibold">Complemento</label>
                            <input type="text" class="form-control @error('complemento') is-invalid @enderror"
                                   id="complemento" name="complemento" value="{{ old('complemento', $empresa->complemento) }}">
                            @error('complemento')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Bairro --}}
                        <div class="col-md-3">
                            <label for="bairro" class="form-label fw-semibold">Bairro</label>
                            <input type="text" class="form-control @error('bairro') is-invalid @enderror"
                                   id="bairro" name="bairro" value="{{ old('bairro', $empresa->bairro) }}">
                            @error('bairro')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Cidade --}}
                        <div class="col-md-4">
                            <label for="cidade" class="form-label fw-semibold">Cidade</label>
                            <input type="text" class="form-control @error('cidade') is-invalid @enderror"
                                   id="cidade" name="cidade" value="{{ old('cidade', $empresa->cidade) }}">
                            @error('cidade')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- UF --}}
                        <div class="col-md-2">
                            <label for="uf" class="form-label fw-semibold">UF</label>
                            <input type="text" class="form-control @error('uf') is-invalid @enderror"
                                   id="uf" name="uf" value="{{ old('uf', $empresa->uf) }}" maxlength="2" style="text-transform:uppercase">
                            @error('uf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Telefone --}}
                        <div class="col-md-4">
                            <label for="telefone" class="form-label fw-semibold">Telefone</label>
                            <input type="text" class="form-control @error('telefone') is-invalid @enderror"
                                   id="telefone" name="telefone" value="{{ old('telefone', $empresa->telefone) }}"
                                   data-mask="telefone" placeholder="(00) 00000-0000">
                            @error('telefone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <a href="{{ route('admin.onboarding.step1') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Voltar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            Proximo: Usuario <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
