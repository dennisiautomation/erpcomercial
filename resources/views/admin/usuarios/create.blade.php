@extends('layouts.app')

@section('title', 'Novo Usuario')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-person-plus me-2"></i>Novo Usuario</h4>
    <a href="{{ route('admin.usuarios.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<form action="{{ route('admin.usuarios.store') }}" method="POST">
    @csrf

    {{-- Dados Pessoais --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-person me-2"></i>Dados Pessoais</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Nome <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror"
                           id="name" name="name" value="{{ old('name') }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror"
                           id="email" name="email" value="{{ old('email') }}" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="cpf" class="form-label">CPF</label>
                    <input type="text" class="form-control @error('cpf') is-invalid @enderror"
                           id="cpf" name="cpf" value="{{ old('cpf') }}"
                           placeholder="000.000.000-00" data-mask="cpf" maxlength="14">
                    @error('cpf')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="telefone" class="form-label">Telefone</label>
                    <input type="text" class="form-control @error('telefone') is-invalid @enderror"
                           id="telefone" name="telefone" value="{{ old('telefone') }}"
                           placeholder="(00) 00000-0000" data-mask="telefone" maxlength="20">
                    @error('telefone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Senha --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Senha</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="password" class="form-label">Senha <span class="text-danger">*</span></label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror"
                           id="password" name="password" required>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="password_confirmation" class="form-label">Confirmar Senha <span class="text-danger">*</span></label>
                    <input type="password" class="form-control"
                           id="password_confirmation" name="password_confirmation" required>
                </div>
            </div>
        </div>
    </div>

    {{-- Perfil e Empresa --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-gear me-2"></i>Perfil e Empresa</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="perfil" class="form-label">Perfil <span class="text-danger">*</span></label>
                    <select class="form-select @error('perfil') is-invalid @enderror"
                            id="perfil" name="perfil" required>
                        <option value="">Selecione...</option>
                        @foreach($perfis as $p)
                            <option value="{{ $p->value }}" {{ old('perfil') === $p->value ? 'selected' : '' }}>
                                {{ $p->label() }}
                            </option>
                        @endforeach
                    </select>
                    @error('perfil')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="empresa_id" class="form-label">Empresa</label>
                    <select class="form-select @error('empresa_id') is-invalid @enderror"
                            id="empresa_id" name="empresa_id">
                        <option value="">Nenhuma (Admin)</option>
                        @foreach($empresas as $emp)
                            <option value="{{ $emp->id }}" {{ (int) old('empresa_id') === $emp->id ? 'selected' : '' }}>
                                {{ $emp->nome_fantasia ?: $emp->razao_social }}
                            </option>
                        @endforeach
                    </select>
                    @error('empresa_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="comissao_percentual" class="form-label">Comissao (%)</label>
                    <input type="number" class="form-control @error('comissao_percentual') is-invalid @enderror"
                           id="comissao_percentual" name="comissao_percentual"
                           value="{{ old('comissao_percentual') }}" min="0" max="100" step="0.01">
                    @error('comissao_percentual')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select class="form-select @error('status') is-invalid @enderror"
                            id="status" name="status" required>
                        <option value="ativo" {{ old('status', 'ativo') === 'ativo' ? 'selected' : '' }}>Ativo</option>
                        <option value="inativo" {{ old('status') === 'inativo' ? 'selected' : '' }}>Inativo</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label d-block">Administrador</label>
                    <div class="form-check form-switch mt-2">
                        <input type="hidden" name="is_admin" value="0">
                        <input class="form-check-input" type="checkbox" id="is_admin" name="is_admin" value="1"
                               {{ old('is_admin') ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_admin">Acesso administrativo a plataforma</label>
                    </div>
                    @error('is_admin')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Botoes --}}
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> Salvar
        </button>
        <a href="{{ route('admin.usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function applyMask(input, maskFn) {
        input.addEventListener('input', function () {
            this.value = maskFn(this.value);
        });
    }

    function maskCPF(v) {
        v = v.replace(/\D/g, '').substring(0, 11);
        v = v.replace(/^(\d{3})(\d)/, '$1.$2');
        v = v.replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3');
        v = v.replace(/\.(\d{3})(\d)/, '.$1-$2');
        return v;
    }

    function maskTelefone(v) {
        v = v.replace(/\D/g, '').substring(0, 11);
        if (v.length > 10) {
            v = v.replace(/^(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        } else if (v.length > 6) {
            v = v.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
        } else if (v.length > 2) {
            v = v.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
        }
        return v;
    }

    document.querySelectorAll('[data-mask="cpf"]').forEach(el => applyMask(el, maskCPF));
    document.querySelectorAll('[data-mask="telefone"]').forEach(el => applyMask(el, maskTelefone));
});
</script>
@endpush
