@extends('layouts.app')

@section('title', 'Editar Funcionário')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Editar Funcionário</h4>
    <a href="{{ route('app.funcionarios.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<form method="POST" action="{{ route('app.funcionarios.update', $funcionario) }}">
    @csrf
    @method('PUT')

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-person me-2"></i>Dados Pessoais</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label fw-semibold">Nome Completo <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $funcionario->name) }}" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label fw-semibold">E-mail <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $funcionario->email) }}" required>
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label for="cpf" class="form-label">CPF</label>
                    <input type="text" name="cpf" id="cpf" class="form-control @error('cpf') is-invalid @enderror" value="{{ old('cpf', $funcionario->cpf) }}" maxlength="14">
                    @error('cpf') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label for="telefone" class="form-label">Telefone</label>
                    <input type="text" name="telefone" id="telefone" class="form-control @error('telefone') is-invalid @enderror" value="{{ old('telefone', $funcionario->telefone) }}">
                    @error('telefone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Acesso e Perfil</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="password" class="form-label">Nova Senha <small class="text-muted">(deixe em branco para manter)</small></label>
                    <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror">
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label for="password_confirmation" class="form-label">Confirmar Nova Senha</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" class="form-control">
                </div>
                <div class="col-md-4">
                    <label for="perfil" class="form-label fw-semibold">Perfil <span class="text-danger">*</span></label>
                    <select name="perfil" id="perfil" class="form-select @error('perfil') is-invalid @enderror" required>
                        @foreach($perfis as $p)
                            <option value="{{ $p->value }}" {{ old('perfil', $funcionario->perfil?->value) === $p->value ? 'selected' : '' }}>{{ $p->label() }}</option>
                        @endforeach
                    </select>
                    @error('perfil') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="comissao_percentual" class="form-label">Comissão (%)</label>
                    <input type="number" name="comissao_percentual" id="comissao_percentual" class="form-control @error('comissao_percentual') is-invalid @enderror" value="{{ old('comissao_percentual', $funcionario->comissao_percentual) }}" step="0.01" min="0" max="100">
                    @error('comissao_percentual') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                    <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                        <option value="ativo" {{ old('status', $funcionario->status) === 'ativo' ? 'selected' : '' }}>Ativo</option>
                        <option value="inativo" {{ old('status', $funcionario->status) === 'inativo' ? 'selected' : '' }}>Inativo</option>
                    </select>
                    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-building me-2"></i>Unidades de Acesso</h6>
        </div>
        <div class="card-body">
            @if($unidades->isEmpty())
                <p class="text-muted">Nenhuma unidade disponível</p>
            @else
                @php
                    $funcUnidades = $funcionario->unidades->pluck('id')->toArray();
                @endphp
                <div class="row g-2">
                    @foreach($unidades as $unidade)
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="unidades[]" value="{{ $unidade->id }}" id="unidade_{{ $unidade->id }}"
                                    {{ in_array($unidade->id, old('unidades', $funcUnidades)) ? 'checked' : '' }}>
                                <label class="form-check-label" for="unidade_{{ $unidade->id }}">
                                    {{ $unidade->nome }}
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
            @error('unidades') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('app.funcionarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> Atualizar Funcionário
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const cpfInput = document.getElementById('cpf');
        cpfInput.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '');
            v = v.replace(/(\d{3})(\d)/, '$1.$2');
            v = v.replace(/(\d{3})(\d)/, '$1.$2');
            v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            this.value = v;
        });
    });
</script>
@endpush
