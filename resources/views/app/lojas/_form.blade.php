{{-- Form compartilhado de loja (create/edit). Espera $loja (opcional) e $gerentes. --}}
@php($loja = $loja ?? null)

<x-erp.form-section title="Dados da Loja" icon="shop">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Nome da Loja <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('nome') is-invalid @enderror"
                   name="nome" value="{{ old('nome', $loja?->nome) }}" required
                   placeholder="Ex: Matriz, Filial Centro">
            @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">CNPJ <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('cnpj') is-invalid @enderror"
                   name="cnpj" value="{{ old('cnpj', $loja?->cnpj) }}" data-mask="cnpj" required
                   placeholder="00.000.000/0000-00">
            <div class="form-text">Lojas com o mesmo CNPJ compartilham certificado, CSC e numeração.</div>
            @error('cnpj')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">Status <span class="text-danger">*</span></label>
            <select class="form-select @error('status') is-invalid @enderror" name="status" required>
                @foreach(['ativa' => 'Ativa', 'inativa' => 'Inativa', 'em_implantacao' => 'Em implantação'] as $val => $label)
                    <option value="{{ $val }}" {{ old('status', $loja?->status ?? 'ativa') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">Inscrição Estadual</label>
            <input type="text" class="form-control @error('ie') is-invalid @enderror"
                   name="ie" value="{{ old('ie', $loja?->ie) }}">
            @error('ie')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">Inscrição Municipal</label>
            <input type="text" class="form-control @error('im') is-invalid @enderror"
                   name="im" value="{{ old('im', $loja?->im) }}">
            @error('im')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">Telefone <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('telefone') is-invalid @enderror"
                   name="telefone" value="{{ old('telefone', $loja?->telefone) }}" data-mask="telefone" required>
            @error('telefone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">Gerente responsável</label>
            <select class="form-select @error('gerente_id') is-invalid @enderror" name="gerente_id">
                <option value="">— Nenhum —</option>
                @foreach($gerentes as $g)
                    <option value="{{ $g->id }}" {{ (int) old('gerente_id', $loja?->gerente_id) === $g->id ? 'selected' : '' }}>{{ $g->name }}</option>
                @endforeach
            </select>
            @error('gerente_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</x-erp.form-section>

<x-erp.form-section title="Endereço" icon="geo-alt">
    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label">CEP <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('cep') is-invalid @enderror"
                   name="cep" value="{{ old('cep', $loja?->cep) }}" data-mask="cep" data-cep required>
            @error('cep')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Logradouro <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('logradouro') is-invalid @enderror"
                   name="logradouro" value="{{ old('logradouro', $loja?->logradouro) }}" required>
            @error('logradouro')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">Número <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('numero') is-invalid @enderror"
                   name="numero" value="{{ old('numero', $loja?->numero) }}" required>
            @error('numero')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">Complemento</label>
            <input type="text" class="form-control" name="complemento" value="{{ old('complemento', $loja?->complemento) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Bairro <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('bairro') is-invalid @enderror"
                   name="bairro" value="{{ old('bairro', $loja?->bairro) }}" required>
            @error('bairro')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Cidade <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('cidade') is-invalid @enderror"
                   name="cidade" value="{{ old('cidade', $loja?->cidade) }}" required>
            @error('cidade')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-2">
            <label class="form-label">UF <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('uf') is-invalid @enderror"
                   name="uf" value="{{ old('uf', $loja?->uf) }}" maxlength="2" style="text-transform:uppercase" required>
            @error('uf')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</x-erp.form-section>
