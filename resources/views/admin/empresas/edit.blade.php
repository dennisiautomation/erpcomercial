@extends('layouts.app')

@section('title', 'Editar Empresa')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Editar Empresa</h4>
    <a href="{{ route('admin.empresas.show', $empresa) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<form action="{{ route('admin.empresas.update', $empresa) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    {{-- Dados da Empresa --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-building me-2"></i>Dados da Empresa</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="cnpj" class="form-label">CNPJ <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('cnpj') is-invalid @enderror"
                           id="cnpj" name="cnpj" value="{{ old('cnpj', $empresa->cnpj) }}"
                           placeholder="00.000.000/0000-00" data-mask="cnpj" required>
                    @error('cnpj')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="razao_social" class="form-label">Razao Social <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('razao_social') is-invalid @enderror"
                           id="razao_social" name="razao_social" value="{{ old('razao_social', $empresa->razao_social) }}" required>
                    @error('razao_social')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="nome_fantasia" class="form-label">Nome Fantasia</label>
                    <input type="text" class="form-control @error('nome_fantasia') is-invalid @enderror"
                           id="nome_fantasia" name="nome_fantasia" value="{{ old('nome_fantasia', $empresa->nome_fantasia) }}">
                    @error('nome_fantasia')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="ie" class="form-label">Inscricao Estadual</label>
                    <input type="text" class="form-control @error('ie') is-invalid @enderror"
                           id="ie" name="ie" value="{{ old('ie', $empresa->ie) }}">
                    @error('ie')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="im" class="form-label">Inscricao Municipal</label>
                    <input type="text" class="form-control @error('im') is-invalid @enderror"
                           id="im" name="im" value="{{ old('im', $empresa->im) }}">
                    @error('im')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                        @foreach(\App\Enums\StatusEmpresa::cases() as $status)
                            <option value="{{ $status->value }}" {{ old('status', $empresa->status->value) === $status->value ? 'selected' : '' }}>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Regime e Plano --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-gear me-2"></i>Regime e Plano</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="regime_tributario" class="form-label">Regime Tributario <span class="text-danger">*</span></label>
                    <select class="form-select @error('regime_tributario') is-invalid @enderror"
                            id="regime_tributario" name="regime_tributario" required>
                        <option value="">Selecione...</option>
                        @foreach(\App\Enums\RegimeTributario::cases() as $regime)
                            <option value="{{ $regime->value }}" {{ old('regime_tributario', $empresa->regime_tributario?->value) === $regime->value ? 'selected' : '' }}>
                                {{ $regime->label() }}
                            </option>
                        @endforeach
                    </select>
                    @error('regime_tributario')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="plano" class="form-label">Plano <span class="text-danger">*</span></label>
                    <select class="form-select @error('plano') is-invalid @enderror" id="plano" name="plano" required>
                        <option value="">Selecione...</option>
                        <option value="basico" {{ old('plano', $empresa->plano) === 'basico' ? 'selected' : '' }}>Basico</option>
                        <option value="intermediario" {{ old('plano', $empresa->plano) === 'intermediario' ? 'selected' : '' }}>Intermediario</option>
                        <option value="avancado" {{ old('plano', $empresa->plano) === 'avancado' ? 'selected' : '' }}>Avancado</option>
                    </select>
                    @error('plano')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Endereco --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Endereco</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="cep" class="form-label">CEP <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" class="form-control @error('cep') is-invalid @enderror"
                               id="cep" name="cep" value="{{ old('cep', $empresa->cep) }}"
                               placeholder="00000-000" data-mask="cep" required>
                        <button type="button" class="btn btn-outline-secondary" id="btn-buscar-cep" title="Buscar CEP">
                            <i class="bi bi-search"></i>
                        </button>
                        @error('cep')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="logradouro" class="form-label">Logradouro <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('logradouro') is-invalid @enderror"
                           id="logradouro" name="logradouro" value="{{ old('logradouro', $empresa->logradouro) }}" required>
                    @error('logradouro')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="numero" class="form-label">Numero <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('numero') is-invalid @enderror"
                           id="numero" name="numero" value="{{ old('numero', $empresa->numero) }}" required>
                    @error('numero')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="complemento" class="form-label">Complemento</label>
                    <input type="text" class="form-control @error('complemento') is-invalid @enderror"
                           id="complemento" name="complemento" value="{{ old('complemento', $empresa->complemento) }}">
                    @error('complemento')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="bairro" class="form-label">Bairro <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('bairro') is-invalid @enderror"
                           id="bairro" name="bairro" value="{{ old('bairro', $empresa->bairro) }}" required>
                    @error('bairro')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="cidade" class="form-label">Cidade <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('cidade') is-invalid @enderror"
                           id="cidade" name="cidade" value="{{ old('cidade', $empresa->cidade) }}" required>
                    @error('cidade')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label for="uf" class="form-label">UF <span class="text-danger">*</span></label>
                    <select class="form-select @error('uf') is-invalid @enderror" id="uf" name="uf" required>
                        <option value="">UF</option>
                        @foreach(['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $estado)
                            <option value="{{ $estado }}" {{ old('uf', $empresa->uf) === $estado ? 'selected' : '' }}>{{ $estado }}</option>
                        @endforeach
                    </select>
                    @error('uf')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Contato --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-telephone me-2"></i>Contato</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="telefone" class="form-label">Telefone</label>
                    <input type="text" class="form-control @error('telefone') is-invalid @enderror"
                           id="telefone" name="telefone" value="{{ old('telefone', $empresa->telefone) }}"
                           placeholder="(00) 00000-0000" data-mask="telefone">
                    @error('telefone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror"
                           id="email" name="email" value="{{ old('email', $empresa->email) }}">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Outros --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-three-dots me-2"></i>Outros</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="logo" class="form-label">Logo</label>
                    @if($empresa->logo)
                        <div class="mb-2">
                            <img src="{{ asset('storage/' . $empresa->logo) }}" alt="Logo" class="rounded" style="max-height: 60px;">
                        </div>
                    @endif
                    <input type="file" class="form-control @error('logo') is-invalid @enderror"
                           id="logo" name="logo" accept="image/*">
                    <div class="form-text">Deixe vazio para manter a logo atual.</div>
                    @error('logo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-8">
                    <label for="observacoes" class="form-label">Observacoes</label>
                    <textarea class="form-control @error('observacoes') is-invalid @enderror"
                              id="observacoes" name="observacoes" rows="3">{{ old('observacoes', $empresa->observacoes) }}</textarea>
                    @error('observacoes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Botoes --}}
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> Salvar Alteracoes
        </button>
        <a href="{{ route('admin.empresas.show', $empresa) }}" class="btn btn-outline-secondary">Cancelar</a>
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

    function maskCNPJ(v) {
        v = v.replace(/\D/g, '').substring(0, 14);
        v = v.replace(/^(\d{2})(\d)/, '$1.$2');
        v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
        v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
        v = v.replace(/(\d{4})(\d)/, '$1-$2');
        return v;
    }

    function maskCEP(v) {
        v = v.replace(/\D/g, '').substring(0, 8);
        v = v.replace(/^(\d{5})(\d)/, '$1-$2');
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

    document.querySelectorAll('[data-mask="cnpj"]').forEach(el => applyMask(el, maskCNPJ));
    document.querySelectorAll('[data-mask="cep"]').forEach(el => applyMask(el, maskCEP));
    document.querySelectorAll('[data-mask="telefone"]').forEach(el => applyMask(el, maskTelefone));

    const btnCep = document.getElementById('btn-buscar-cep');
    const cepInput = document.getElementById('cep');

    function buscarCEP() {
        const cep = cepInput.value.replace(/\D/g, '');
        if (cep.length !== 8) {
            alert('Informe um CEP valido com 8 digitos.');
            return;
        }

        btnCep.disabled = true;
        btnCep.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(res => res.json())
            .then(data => {
                if (data.erro) {
                    alert('CEP nao encontrado.');
                    return;
                }
                document.getElementById('logradouro').value = data.logradouro || '';
                document.getElementById('bairro').value = data.bairro || '';
                document.getElementById('cidade').value = data.localidade || '';
                document.getElementById('uf').value = data.uf || '';
                document.getElementById('numero').focus();
            })
            .catch(() => alert('Erro ao buscar CEP. Tente novamente.'))
            .finally(() => {
                btnCep.disabled = false;
                btnCep.innerHTML = '<i class="bi bi-search"></i>';
            });
    }

    btnCep.addEventListener('click', buscarCEP);
    cepInput.addEventListener('blur', function () {
        if (this.value.replace(/\D/g, '').length === 8) buscarCEP();
    });
});
</script>
@endpush
