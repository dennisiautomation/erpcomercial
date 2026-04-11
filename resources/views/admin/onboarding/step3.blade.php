@extends('admin.onboarding.layout', ['step' => 3])

@section('title', 'Onboarding - Usuario Dono')

@section('step-content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2"></i>Usuario Dono</h5>
                <p class="text-muted mb-0 small mt-1">Crie o usuario principal (Dono) que administrara a empresa</p>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.onboarding.step3.store') }}" class="erp-form">
                    @csrf

                    <div class="row g-3">
                        {{-- Nome --}}
                        <div class="col-md-6">
                            <label for="name" class="form-label fw-semibold">Nome Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   id="name" name="name" value="{{ old('name') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Email --}}
                        <div class="col-md-6">
                            <label for="email" class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror"
                                   id="email" name="email" value="{{ old('email') }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Senha --}}
                        <div class="col-md-6">
                            <label for="password" class="form-label fw-semibold">Senha <span class="text-danger">*</span></label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror"
                                   id="password" name="password" required minlength="6">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Confirmar Senha --}}
                        <div class="col-md-6">
                            <label for="password_confirmation" class="form-label fw-semibold">Confirmar Senha <span class="text-danger">*</span></label>
                            <input type="password" class="form-control"
                                   id="password_confirmation" name="password_confirmation" required minlength="6">
                        </div>

                        {{-- CPF --}}
                        <div class="col-md-4">
                            <label for="cpf" class="form-label fw-semibold">CPF</label>
                            <input type="text" class="form-control @error('cpf') is-invalid @enderror"
                                   id="cpf" name="cpf" value="{{ old('cpf') }}"
                                   data-mask="cpf" placeholder="000.000.000-00">
                            @error('cpf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Telefone --}}
                        <div class="col-md-4">
                            <label for="telefone" class="form-label fw-semibold">Telefone</label>
                            <input type="text" class="form-control @error('telefone') is-invalid @enderror"
                                   id="telefone" name="telefone" value="{{ old('telefone') }}"
                                   data-mask="telefone" placeholder="(00) 00000-0000">
                            @error('telefone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Perfil (hidden) --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Perfil</label>
                            <input type="text" class="form-control bg-light" value="Dono" readonly>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <a href="{{ route('admin.onboarding.step2') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Voltar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            Proximo: Fiscal <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
