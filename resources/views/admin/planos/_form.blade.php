@php $p = $plano ?? null; @endphp

<div class="card shadow-sm mb-4">
    <div class="card-header"><h6 class="mb-0">Informacoes Basicas</h6></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('nome') is-invalid @enderror"
                       id="nome" name="nome" value="{{ old('nome', $p?->nome) }}" required>
                @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="slug" class="form-label">Slug <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('slug') is-invalid @enderror"
                       id="slug" name="slug" value="{{ old('slug', $p?->slug) }}" required>
                @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2">
                <label for="ordem" class="form-label">Ordem <span class="text-danger">*</span></label>
                <input type="number" class="form-control @error('ordem') is-invalid @enderror"
                       id="ordem" name="ordem" value="{{ old('ordem', $p?->ordem ?? 0) }}" min="0" required>
                @error('ordem') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2">
                <label for="ativo" class="form-label">Status</label>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1"
                           {{ old('ativo', $p?->ativo ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="ativo">Ativo</label>
                </div>
            </div>
            <div class="col-12">
                <label for="descricao" class="form-label">Descricao</label>
                <textarea class="form-control @error('descricao') is-invalid @enderror"
                          id="descricao" name="descricao" rows="2">{{ old('descricao', $p?->descricao) }}</textarea>
                @error('descricao') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header"><h6 class="mb-0">Precos</h6></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="preco_mensal" class="form-label">Preco Mensal (R$) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" class="form-control @error('preco_mensal') is-invalid @enderror"
                       id="preco_mensal" name="preco_mensal" value="{{ old('preco_mensal', $p?->preco_mensal ?? '0.00') }}" min="0" required>
                @error('preco_mensal') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="preco_anual" class="form-label">Preco Anual (R$) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" class="form-control @error('preco_anual') is-invalid @enderror"
                       id="preco_anual" name="preco_anual" value="{{ old('preco_anual', $p?->preco_anual ?? '0.00') }}" min="0" required>
                @error('preco_anual') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="dias_trial" class="form-label">Dias de Trial <span class="text-danger">*</span></label>
                <input type="number" class="form-control @error('dias_trial') is-invalid @enderror"
                       id="dias_trial" name="dias_trial" value="{{ old('dias_trial', $p?->dias_trial ?? 14) }}" min="0" required>
                @error('dias_trial') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header"><h6 class="mb-0">Limites</h6></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="max_unidades" class="form-label">Max. Unidades <span class="text-danger">*</span></label>
                <input type="number" class="form-control @error('max_unidades') is-invalid @enderror"
                       id="max_unidades" name="max_unidades" value="{{ old('max_unidades', $p?->max_unidades ?? 1) }}" min="1" required>
                @error('max_unidades') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label for="max_usuarios" class="form-label">Max. Usuarios <span class="text-danger">*</span></label>
                <input type="number" class="form-control @error('max_usuarios') is-invalid @enderror"
                       id="max_usuarios" name="max_usuarios" value="{{ old('max_usuarios', $p?->max_usuarios ?? 3) }}" min="1" required>
                @error('max_usuarios') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label for="max_produtos" class="form-label">Max. Produtos <span class="text-danger">*</span></label>
                <input type="number" class="form-control @error('max_produtos') is-invalid @enderror"
                       id="max_produtos" name="max_produtos" value="{{ old('max_produtos', $p?->max_produtos ?? 100) }}" min="1" required>
                @error('max_produtos') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label for="max_notas_mes" class="form-label">Max. Notas/Mes <span class="text-danger">*</span></label>
                <input type="number" class="form-control @error('max_notas_mes') is-invalid @enderror"
                       id="max_notas_mes" name="max_notas_mes" value="{{ old('max_notas_mes', $p?->max_notas_mes ?? 50) }}" min="1" required>
                @error('max_notas_mes') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header"><h6 class="mb-0">Funcionalidades</h6></div>
    <div class="card-body">
        <div class="row g-3">
            @php
                $features = [
                    'pdv_habilitado'         => 'PDV',
                    'fiscal_habilitado'      => 'Fiscal (NF-e, NFC-e, NFS-e)',
                    'multilojas_habilitado'  => 'Multilojas',
                    'os_habilitado'          => 'Ordens de Servico',
                    'contratos_habilitado'   => 'Contratos / Recorrencia',
                    'conciliacao_habilitada' => 'Conciliacao Bancaria',
                    'dre_habilitado'         => 'DRE',
                    'boletos_habilitado'     => 'Boletos',
                    'api_habilitada'         => 'API Externa',
                ];
            @endphp
            @foreach($features as $field => $label)
                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="{{ $field }}" name="{{ $field }}" value="1"
                               {{ old($field, $p?->$field ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="{{ $field }}">{{ $label }}</label>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2">
    <a href="{{ route('admin.planos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg me-1"></i> {{ $p ? 'Atualizar' : 'Criar' }} Plano
    </button>
</div>
