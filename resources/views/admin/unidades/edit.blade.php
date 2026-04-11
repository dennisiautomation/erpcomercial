@extends('layouts.app')

@section('title', 'Editar Unidade')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">
        <i class="bi bi-pencil-square me-2"></i>Editar Unidade
        <small class="text-muted fs-6">- {{ $empresa->razao_social }}</small>
    </h4>
    <a href="{{ route('admin.empresas.show', $empresa) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<form action="{{ route('admin.empresas.unidades.update', [$empresa, $unidade]) }}" method="POST">
    @csrf
    @method('PUT')

    {{-- Dados da Unidade --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="bi bi-shop me-2"></i>Dados da Unidade</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="nome" class="form-label">Nome da Unidade <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('nome') is-invalid @enderror"
                           id="nome" name="nome" value="{{ old('nome', $unidade->nome) }}" required>
                    @error('nome')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="cnpj" class="form-label">CNPJ</label>
                    <input type="text" class="form-control @error('cnpj') is-invalid @enderror"
                           id="cnpj" name="cnpj" value="{{ old('cnpj', $unidade->cnpj) }}"
                           placeholder="00.000.000/0000-00" data-mask="cnpj">
                    @error('cnpj')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                        <option value="ativa" {{ old('status', $unidade->status) === 'ativa' ? 'selected' : '' }}>Ativa</option>
                        <option value="inativa" {{ old('status', $unidade->status) === 'inativa' ? 'selected' : '' }}>Inativa</option>
                        <option value="em_implantacao" {{ old('status', $unidade->status) === 'em_implantacao' ? 'selected' : '' }}>Em Implantacao</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="ie" class="form-label">Inscricao Estadual</label>
                    <input type="text" class="form-control @error('ie') is-invalid @enderror"
                           id="ie" name="ie" value="{{ old('ie', $unidade->ie) }}">
                    @error('ie')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="im" class="form-label">Inscricao Municipal</label>
                    <input type="text" class="form-control @error('im') is-invalid @enderror"
                           id="im" name="im" value="{{ old('im', $unidade->im) }}">
                    @error('im')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="telefone" class="form-label">Telefone</label>
                    <input type="text" class="form-control @error('telefone') is-invalid @enderror"
                           id="telefone" name="telefone" value="{{ old('telefone', $unidade->telefone) }}"
                           placeholder="(00) 00000-0000" data-mask="telefone">
                    @error('telefone')
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
                    <label for="cep" class="form-label">CEP</label>
                    <div class="input-group">
                        <input type="text" class="form-control @error('cep') is-invalid @enderror"
                               id="cep" name="cep" value="{{ old('cep', $unidade->cep) }}"
                               placeholder="00000-000" data-mask="cep">
                        <button type="button" class="btn btn-outline-secondary" id="btn-buscar-cep" title="Buscar CEP">
                            <i class="bi bi-search"></i>
                        </button>
                        @error('cep')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="logradouro" class="form-label">Logradouro</label>
                    <input type="text" class="form-control @error('logradouro') is-invalid @enderror"
                           id="logradouro" name="logradouro" value="{{ old('logradouro', $unidade->logradouro) }}">
                    @error('logradouro')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="numero" class="form-label">Numero</label>
                    <input type="text" class="form-control @error('numero') is-invalid @enderror"
                           id="numero" name="numero" value="{{ old('numero', $unidade->numero) }}">
                    @error('numero')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="complemento" class="form-label">Complemento</label>
                    <input type="text" class="form-control @error('complemento') is-invalid @enderror"
                           id="complemento" name="complemento" value="{{ old('complemento', $unidade->complemento) }}">
                    @error('complemento')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label for="bairro" class="form-label">Bairro</label>
                    <input type="text" class="form-control @error('bairro') is-invalid @enderror"
                           id="bairro" name="bairro" value="{{ old('bairro', $unidade->bairro) }}">
                    @error('bairro')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label for="cidade" class="form-label">Cidade</label>
                    <input type="text" class="form-control @error('cidade') is-invalid @enderror"
                           id="cidade" name="cidade" value="{{ old('cidade', $unidade->cidade) }}">
                    @error('cidade')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label for="uf" class="form-label">UF</label>
                    <select class="form-select @error('uf') is-invalid @enderror" id="uf" name="uf">
                        <option value="">UF</option>
                        @foreach(['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $estado)
                            <option value="{{ $estado }}" {{ old('uf', $unidade->uf) === $estado ? 'selected' : '' }}>{{ $estado }}</option>
                        @endforeach
                    </select>
                    @error('uf')
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
        input.addEventListener('input', function () { this.value = maskFn(this.value); });
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
        if (v.length > 10) v = v.replace(/^(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        else if (v.length > 6) v = v.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
        else if (v.length > 2) v = v.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
        return v;
    }

    document.querySelectorAll('[data-mask="cnpj"]').forEach(el => applyMask(el, maskCNPJ));
    document.querySelectorAll('[data-mask="cep"]').forEach(el => applyMask(el, maskCEP));
    document.querySelectorAll('[data-mask="telefone"]').forEach(el => applyMask(el, maskTelefone));

    const btnCep = document.getElementById('btn-buscar-cep');
    const cepInput = document.getElementById('cep');

    function buscarCEP() {
        const cep = cepInput.value.replace(/\D/g, '');
        if (cep.length !== 8) { alert('Informe um CEP valido com 8 digitos.'); return; }

        btnCep.disabled = true;
        btnCep.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(res => res.json())
            .then(data => {
                if (data.erro) { alert('CEP nao encontrado.'); return; }
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
