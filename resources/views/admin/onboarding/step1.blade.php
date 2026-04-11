@extends('admin.onboarding.layout', ['step' => 1])

@section('title', 'Onboarding - Empresa')

@section('step-content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-building me-2"></i>Dados da Empresa</h5>
                <p class="text-muted mb-0 small mt-1">Preencha os dados principais da empresa cliente</p>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.onboarding.step1.store') }}" class="erp-form">
                    @csrf

                    <div class="row g-3">
                        {{-- CNPJ --}}
                        <div class="col-md-4">
                            <label for="cnpj" class="form-label fw-semibold">CNPJ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('cnpj') is-invalid @enderror"
                                   id="cnpj" name="cnpj" value="{{ old('cnpj') }}"
                                   data-mask="cnpj" data-cnpj-lookup placeholder="00.000.000/0000-00" required>
                            @error('cnpj')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Razao Social --}}
                        <div class="col-md-8">
                            <label for="razao_social" class="form-label fw-semibold">Razao Social <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('razao_social') is-invalid @enderror"
                                   id="razao_social" name="razao_social" value="{{ old('razao_social') }}" required>
                            @error('razao_social')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Nome Fantasia --}}
                        <div class="col-md-6">
                            <label for="nome_fantasia" class="form-label fw-semibold">Nome Fantasia</label>
                            <input type="text" class="form-control @error('nome_fantasia') is-invalid @enderror"
                                   id="nome_fantasia" name="nome_fantasia" value="{{ old('nome_fantasia') }}">
                            @error('nome_fantasia')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Regime Tributario --}}
                        <div class="col-md-6">
                            <label for="regime_tributario" class="form-label fw-semibold">Regime Tributario <span class="text-danger">*</span></label>
                            <select class="form-select @error('regime_tributario') is-invalid @enderror"
                                    id="regime_tributario" name="regime_tributario" required>
                                <option value="">Selecione...</option>
                                @foreach($regimes as $regime)
                                    <option value="{{ $regime->value }}" {{ old('regime_tributario') === $regime->value ? 'selected' : '' }}>
                                        {{ $regime->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('regime_tributario')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <hr class="my-2">

                        {{-- CEP --}}
                        <div class="col-md-3">
                            <label for="cep" class="form-label fw-semibold">CEP</label>
                            <input type="text" class="form-control @error('cep') is-invalid @enderror"
                                   id="cep" name="cep" value="{{ old('cep') }}"
                                   data-cep placeholder="00000-000">
                            @error('cep')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Logradouro --}}
                        <div class="col-md-6">
                            <label for="logradouro" class="form-label fw-semibold">Logradouro</label>
                            <input type="text" class="form-control @error('logradouro') is-invalid @enderror"
                                   id="logradouro" name="logradouro" value="{{ old('logradouro') }}">
                            @error('logradouro')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Numero --}}
                        <div class="col-md-3">
                            <label for="numero" class="form-label fw-semibold">Numero</label>
                            <input type="text" class="form-control @error('numero') is-invalid @enderror"
                                   id="numero" name="numero" value="{{ old('numero') }}">
                            @error('numero')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Complemento --}}
                        <div class="col-md-3">
                            <label for="complemento" class="form-label fw-semibold">Complemento</label>
                            <input type="text" class="form-control @error('complemento') is-invalid @enderror"
                                   id="complemento" name="complemento" value="{{ old('complemento') }}">
                            @error('complemento')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Bairro --}}
                        <div class="col-md-3">
                            <label for="bairro" class="form-label fw-semibold">Bairro</label>
                            <input type="text" class="form-control @error('bairro') is-invalid @enderror"
                                   id="bairro" name="bairro" value="{{ old('bairro') }}">
                            @error('bairro')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Cidade --}}
                        <div class="col-md-4">
                            <label for="cidade" class="form-label fw-semibold">Cidade</label>
                            <input type="text" class="form-control @error('cidade') is-invalid @enderror"
                                   id="cidade" name="cidade" value="{{ old('cidade') }}">
                            @error('cidade')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- UF --}}
                        <div class="col-md-2">
                            <label for="uf" class="form-label fw-semibold">UF</label>
                            <input type="text" class="form-control @error('uf') is-invalid @enderror"
                                   id="uf" name="uf" value="{{ old('uf') }}" maxlength="2" style="text-transform:uppercase">
                            @error('uf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <hr class="my-2">

                        {{-- Telefone --}}
                        <div class="col-md-4">
                            <label for="telefone" class="form-label fw-semibold">Telefone</label>
                            <input type="text" class="form-control @error('telefone') is-invalid @enderror"
                                   id="telefone" name="telefone" value="{{ old('telefone') }}"
                                   data-mask="telefone" placeholder="(00) 00000-0000">
                            @error('telefone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Email --}}
                        <div class="col-md-4">
                            <label for="email" class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror"
                                   id="email" name="email" value="{{ old('email') }}">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Plano --}}
                        <div class="col-md-4">
                            <label for="plano_id" class="form-label fw-semibold">Plano <span class="text-danger">*</span></label>
                            <select class="form-select @error('plano_id') is-invalid @enderror"
                                    id="plano_id" name="plano_id" required>
                                <option value="">Selecione o plano...</option>
                                @foreach($planos as $plano)
                                    <option value="{{ $plano->id }}" {{ old('plano_id') == $plano->id ? 'selected' : '' }}>
                                        {{ $plano->nome }} — R$ {{ number_format($plano->preco_mensal, 2, ',', '.') }}/mes
                                    </option>
                                @endforeach
                            </select>
                            @error('plano_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                        <a href="{{ route('admin.empresas.index') }}" class="btn btn-outline-secondary me-2">
                            <i class="bi bi-x-lg me-1"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            Proximo: Unidade <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
