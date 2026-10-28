@php $u = $usuario ?? null; @endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="campo-name" class="form-label fw-semibold">Nome *</label>
        <input id="campo-name" type="text" name="name" class="form-control {{ $errors && $errors->has('name') ? 'is-invalid' : '' }}" value="{{ old('name', $u?->name) }}" required>
    </div>
    <div class="col-md-6">
        <label for="campo-email" class="form-label fw-semibold">E-mail *</label>
        <input id="campo-email" type="email" name="email" class="form-control {{ $errors && $errors->has('email') ? 'is-invalid' : '' }}" value="{{ old('email', $u?->email) }}" required>
    </div>
    <div class="col-md-6">
        <label for="campo-password" class="form-label fw-semibold">Senha {{ $u ? '(deixe vazio para manter)' : '*' }}</label>
        <input id="campo-password" type="password" name="password" class="form-control" {{ $u ? '' : 'required' }}>
    </div>
    <div class="col-md-6">
        <label for="campo-password_confirmation" class="form-label fw-semibold">Confirmar Senha</label>
        <input id="campo-password_confirmation" type="password" name="password_confirmation" class="form-control">
    </div>
    <div class="col-md-4">
        <label for="campo-cpf" class="form-label fw-semibold">CPF</label>
        <input id="campo-cpf" type="text" name="cpf" class="form-control" value="{{ old('cpf', $u?->cpf) }}">
    </div>
    <div class="col-md-4">
        <label for="campo-telefone" class="form-label fw-semibold">Telefone</label>
        <input id="campo-telefone" type="text" name="telefone" class="form-control" value="{{ old('telefone', $u?->telefone) }}">
    </div>
    <div class="col-md-4">
        <label for="campo-comissao_percentual" class="form-label fw-semibold">Comissão (%)</label>
        <input id="campo-comissao_percentual" type="number" name="comissao_percentual" class="form-control" step="0.01" min="0" max="100" value="{{ old('comissao_percentual', $u?->comissao_percentual) }}">
    </div>
    <div class="col-md-4">
        <label for="campo-perfil" class="form-label fw-semibold">Perfil *</label>
        <select id="campo-perfil" name="perfil" class="form-select" required>
            @foreach($perfis as $p)
                <option value="{{ $p->value }}" {{ old('perfil', $u?->perfil?->value ?? $u?->perfil) == $p->value ? 'selected' : '' }}>{{ $p->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label for="campo-empresa_id" class="form-label fw-semibold">Empresa</label>
        <select id="campo-empresa_id" name="empresa_id" class="form-select">
            <option value="">— Sem empresa (Admin) —</option>
            @foreach($empresas as $emp)
                <option value="{{ $emp->id }}" {{ old('empresa_id', $u?->empresa_id) == $emp->id ? 'selected' : '' }}>{{ $emp->nome_fantasia ?: $emp->razao_social }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label for="campo-status" class="form-label fw-semibold">Status *</label>
        <select id="campo-status" name="status" class="form-select" required>
            <option value="ativo" {{ old('status', $u?->status) == 'ativo' ? 'selected' : '' }}>Ativo</option>
            <option value="inativo" {{ old('status', $u?->status) == 'inativo' ? 'selected' : '' }}>Inativo</option>
        </select>
    </div>
    <div class="col-md-12">
        <div class="form-check">
            <input type="hidden" name="is_admin" value="0">
            <input type="checkbox" name="is_admin" value="1" class="form-check-input" id="is_admin" {{ old('is_admin', $u?->is_admin) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_admin">Administrador da plataforma</label>
        </div>
    </div>
</div>
