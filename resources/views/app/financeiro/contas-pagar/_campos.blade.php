{{-- Campos comuns de Nova conta e Editar conta. $conta = null na criação. --}}
@php
    $conta = $conta ?? null;
    $tipoFav = old('favorecido_tipo', $conta?->favorecido_tipo ?? 'fornecedor');
    $fornecedorIdAtual = old('fornecedor_id', $conta?->fornecedor_id);
    $fornecedorAtual = $fornecedorIdAtual
        ? ($conta && (int) $conta->fornecedor_id === (int) $fornecedorIdAtual
            ? $conta->fornecedor
            : \App\Models\Fornecedor::where('empresa_id', auth()->user()->empresa_id)->find($fornecedorIdAtual))
        : null;
    $podeCadastrarFornecedor = \App\Http\Middleware\CheckPermission::canUser(auth()->user(), 'produtos', 'criar');
    $competenciaAtual = old('competencia', $conta?->competencia?->format('Y-m'));
    $categoriasAntigas = ['Aluguel', 'Agua', 'Energia', 'Internet', 'Telefone', 'Fornecedor', 'Imposto', 'Folha de Pagamento', 'Marketing', 'Manutencao', 'Frete', 'Outro'];
@endphp

<x-erp.form-section title="Para quem e o quê" icon="person-check">
    <div class="mb-3">
        <label class="form-label fw-semibold d-block">Pagar para <span class="text-danger">*</span></label>
        <div class="btn-group" role="group" aria-label="Para quem é o pagamento">
            <input type="radio" class="btn-check" name="favorecido_tipo" id="favTipoFornecedor" value="fornecedor" autocomplete="off" @checked($tipoFav === 'fornecedor')>
            <label class="btn btn-outline-primary" for="favTipoFornecedor"><i class="bi bi-building me-1"></i> Fornecedor</label>

            <input type="radio" class="btn-check" name="favorecido_tipo" id="favTipoFuncionario" value="funcionario" autocomplete="off" @checked($tipoFav === 'funcionario')>
            <label class="btn btn-outline-primary" for="favTipoFuncionario"><i class="bi bi-person-badge me-1"></i> Funcionário</label>

            <input type="radio" class="btn-check" name="favorecido_tipo" id="favTipoOutro" value="outro" autocomplete="off" @checked($tipoFav === 'outro')>
            <label class="btn btn-outline-primary" for="favTipoOutro"><i class="bi bi-bank me-1"></i> Imposto / outro</label>
        </div>
        <div class="form-text">Funcionário: salário, vale, benefício. Imposto / outro: INSS, FGTS, Receita, operadora do vale-transporte.</div>
    </div>

    {{-- Fornecedor --}}
    <div class="mb-4" data-fav="fornecedor">
        <label for="fornecedorBusca" class="form-label fw-semibold">Fornecedor <span class="text-danger">*</span></label>
        <div class="position-relative">
            <div class="input-group input-group-lg" id="fornecedorBuscaGroup" @if($fornecedorAtual) style="display:none;" @endif>
                <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
                <input type="text" id="fornecedorBusca" class="form-control @error('fornecedor_id') is-invalid @enderror"
                       placeholder="Buscar fornecedor por nome ou CPF/CNPJ..." autocomplete="off">
            </div>
            <div id="fornecedorResultados" class="list-group mt-1 position-absolute w-100 shadow-lg"
                 style="z-index:1050; display:none; max-height:300px; overflow-y:auto;"></div>
            <input type="hidden" name="fornecedor_id" id="fornecedorId" value="{{ $fornecedorAtual?->id }}">
            <div id="fornecedorSelecionado" class="mt-2" @unless($fornecedorAtual) style="display:none;" @endunless>
                <div class="d-flex align-items-center bg-primary bg-opacity-10 rounded-3 p-2 ps-3">
                    <i class="bi bi-building text-primary me-2 fs-5"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold" id="fornecedorNome">{{ $fornecedorAtual?->razao_social }}</div>
                        <small class="text-muted" id="fornecedorDoc">{{ $fornecedorAtual?->cpf_cnpj }}</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-circle ms-2" id="btnRemoverFornecedor" title="Trocar fornecedor">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        </div>
        <div id="fornecedorAviso" class="text-danger small mt-1" style="display:none;"></div>
        @error('fornecedor_id')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
        <div class="form-text" id="fornecedorDica">
            Escolha o fornecedor na lista. Não achou?
            @if($podeCadastrarFornecedor)
                <a href="{{ route('app.fornecedores.create') }}" target="_blank">Cadastre o fornecedor</a>
                (abre em outra aba) e depois volte aqui e busque de novo.
            @else
                Peça ao dono ou gerente para cadastrar o fornecedor.
            @endif
        </div>
    </div>

    {{-- Funcionário --}}
    <div class="mb-4" data-fav="funcionario">
        <label for="funcionario_id" class="form-label fw-semibold">Funcionário <span class="text-danger">*</span></label>
        <select name="funcionario_id" id="funcionario_id" class="form-select form-select-lg @error('funcionario_id') is-invalid @enderror">
            <option value="">Selecione...</option>
            @foreach($funcionarios as $f)
                @php $sel = (string) old('funcionario_id', $conta?->funcionario_id) === (string) $f->id; @endphp
                @if($f->status === 'ativo' || $sel)
                    <option value="{{ $f->id }}" @selected($sel)>{{ $f->name }}{{ $f->status !== 'ativo' ? ' (inativo)' : '' }}</option>
                @endif
            @endforeach
        </select>
        @error('funcionario_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Aparecem os funcionários cadastrados em Cadastros → Funcionários.</div>
    </div>

    {{-- Imposto / outro --}}
    <div class="mb-4" data-fav="outro">
        <label for="favorecido_nome" class="form-label fw-semibold">Pagar para <span class="text-danger">*</span></label>
        <input type="text" name="favorecido_nome" id="favorecido_nome" maxlength="150" list="favorecidosComuns"
               class="form-control form-control-lg @error('favorecido_nome') is-invalid @enderror"
               value="{{ old('favorecido_nome', $conta?->favorecido_nome) }}" placeholder="Ex: INSS, FGTS, Receita Federal">
        <datalist id="favorecidosComuns">
            <option value="INSS"></option>
            <option value="FGTS"></option>
            <option value="Receita Federal"></option>
            <option value="Simples Nacional"></option>
            <option value="Prefeitura"></option>
            <option value="Vale-transporte"></option>
            <option value="Vale-refeição"></option>
            <option value="Plano de saúde"></option>
        </datalist>
        @error('favorecido_nome')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-4">
        <label for="descricao" class="form-label fw-semibold">Descrição <span class="text-danger">*</span></label>
        <input type="text" name="descricao" id="descricao" maxlength="255"
            class="form-control @error('descricao') is-invalid @enderror"
            value="{{ old('descricao', $conta?->descricao) }}" placeholder="Ex: Salário, Vale do dia 20, Guia do INSS, Aluguel..." required>
        @error('descricao')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</x-erp.form-section>

<x-erp.form-section title="Valores e Vencimento" icon="currency-dollar">
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <label for="valor" class="form-label fw-semibold">Valor <span class="text-danger">*</span></label>
            <div class="input-group input-group-lg">
                <span class="input-group-text">R$</span>
                <input type="number" name="valor" id="valor" step="0.01" min="0.01"
                    class="form-control @error('valor') is-invalid @enderror"
                    value="{{ old('valor', $conta?->valor) }}" placeholder="0,00" required>
            </div>
            @error('valor')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-4">
            <label for="vencimento" class="form-label fw-semibold">Vencimento <span class="text-danger">*</span></label>
            <input type="date" name="vencimento" id="vencimento"
                class="form-control form-control-lg @error('vencimento') is-invalid @enderror"
                value="{{ old('vencimento', $conta?->vencimento?->format('Y-m-d')) }}" required>
            @error('vencimento')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-4">
            <label for="competencia" class="form-label fw-semibold">Mês de competência</label>
            <input type="month" name="competencia" id="competencia"
                class="form-control form-control-lg @error('competencia') is-invalid @enderror"
                value="{{ $competenciaAtual }}">
            @error('competencia')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">Mês a que a despesa pertence. Ex.: salário de outubro pago em novembro = outubro.</div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-5">
            @if($planoContas->isNotEmpty())
                <label for="plano_conta_id" class="form-label fw-semibold">Plano de contas</label>
                <select name="plano_conta_id" id="plano_conta_id" class="form-select @error('plano_conta_id') is-invalid @enderror">
                    <option value="">Sem classificação</option>
                    @foreach($planoContas->groupBy(fn ($p) => $p->parent?->nome ?? 'Outras') as $grupo => $itens)
                        <optgroup label="{{ $grupo }}">
                            @foreach($itens as $p)
                                <option value="{{ $p->id }}" @selected((string) old('plano_conta_id', $conta?->plano_conta_id) === (string) $p->id)>{{ $p->codigo }} — {{ $p->nome }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('plano_conta_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">É por aqui que a despesa aparece separada no DRE.</div>
                @if($conta && ! $conta->plano_conta_id && $conta->categoria)
                    <div class="form-text">Categoria antiga desta conta: <strong>{{ $conta->categoria }}</strong>.</div>
                @endif
            @else
                <label for="categoria" class="form-label fw-semibold">Categoria</label>
                <select name="categoria" id="categoria" class="form-select @error('categoria') is-invalid @enderror">
                    <option value="">Selecione...</option>
                    @php $catAtual = old('categoria', $conta?->categoria); @endphp
                    @if($catAtual && ! in_array($catAtual, $categoriasAntigas, true))
                        <option value="{{ $catAtual }}" selected>{{ $catAtual }}</option>
                    @endif
                    @foreach($categoriasAntigas as $cat)
                        <option value="{{ $cat }}" @selected($catAtual === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
                @error('categoria')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">Para separar salários, encargos e benefícios no DRE, crie o plano de contas (quadro ao lado).</div>
            @endif
        </div>
        <div class="col-md-4">
            @if($centrosCusto->isNotEmpty())
                <label for="centro_custo_id" class="form-label fw-semibold">Centro de custo</label>
                <select name="centro_custo_id" id="centro_custo_id" class="form-select @error('centro_custo_id') is-invalid @enderror">
                    <option value="">Nenhum</option>
                    @foreach($centrosCusto as $cc)
                        <option value="{{ $cc->id }}" @selected((string) old('centro_custo_id', $conta?->centro_custo_id) === (string) $cc->id)>{{ $cc->nome }}</option>
                    @endforeach
                </select>
                @error('centro_custo_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                @if($conta && ! $conta->centro_custo_id && $conta->centro_custo)
                    <div class="form-text">Centro antigo desta conta: <strong>{{ $conta->centro_custo }}</strong>.</div>
                @endif
            @else
                <label for="centro_custo" class="form-label fw-semibold">Centro de custo</label>
                <input type="text" name="centro_custo" id="centro_custo" maxlength="100"
                    class="form-control @error('centro_custo') is-invalid @enderror"
                    value="{{ old('centro_custo', $conta?->centro_custo) }}" placeholder="Ex: Administrativo">
                @error('centro_custo')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text"><a href="{{ route('app.centros-custo.create') }}" target="_blank">Cadastre os centros de custo</a> para escolher numa lista.</div>
            @endif
        </div>
        <div class="col-md-3">
            <label for="forma_pagamento" class="form-label fw-semibold">Forma de pagamento</label>
            @php $formaAtual = old('forma_pagamento', $conta?->forma_pagamento); @endphp
            <select name="forma_pagamento" id="forma_pagamento" class="form-select">
                <option value="">Selecione...</option>
                @foreach(['boleto' => 'Boleto', 'pix' => 'PIX', 'transferencia' => 'Transferência', 'debito_automatico' => 'Débito Automático', 'dinheiro' => 'Dinheiro'] as $valorForma => $rotulo)
                    <option value="{{ $valorForma }}" @selected($formaAtual === $valorForma)>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>
    </div>
</x-erp.form-section>

@push('scripts')
<script>
(function () {
    // ===== PARA QUEM: mostra só o campo do tipo escolhido =====
    const blocosFav = document.querySelectorAll('[data-fav]');
    function tipoFavorecido() {
        const marcado = document.querySelector('input[name="favorecido_tipo"]:checked');
        return marcado ? marcado.value : 'fornecedor';
    }
    function mostrarFavorecido() {
        const tipo = tipoFavorecido();
        blocosFav.forEach(b => { b.style.display = b.dataset.fav === tipo ? '' : 'none'; });
    }
    document.querySelectorAll('input[name="favorecido_tipo"]').forEach(r => r.addEventListener('change', mostrarFavorecido));
    mostrarFavorecido();

    // ===== FORNECEDOR SEARCH =====
    const fornecedorBuscarUrl = '{{ route("app.search.fornecedores") }}';
    const fornecedorCadastrarUrl = @json($podeCadastrarFornecedor ? route('app.fornecedores.create') : null);
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const fornecedorBusca = document.getElementById('fornecedorBusca');
    const fornecedorResultados = document.getElementById('fornecedorResultados');
    let fornecedorTimeout;

    fornecedorBusca.addEventListener('input', function() {
        clearTimeout(fornecedorTimeout);
        const termo = this.value.trim();
        if (termo.length < 2) { fornecedorResultados.style.display = 'none'; return; }
        fornecedorTimeout = setTimeout(() => {
            fetch(`${fornecedorBuscarUrl}?q=${encodeURIComponent(termo)}`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            })
            .then(r => r.json())
            .then(fornecedores => {
                fornecedorResultados.innerHTML = '';
                if (fornecedores.length === 0) {
                    const vazio = document.createElement('div');
                    vazio.className = 'list-group-item small py-2';
                    vazio.innerHTML = '<div class="text-muted mb-1">Nenhum fornecedor encontrado com esse nome.</div>';
                    if (!fornecedorCadastrarUrl) {
                        vazio.insertAdjacentHTML('beforeend', '<div class="text-muted">Peça ao dono ou gerente para cadastrar o fornecedor.</div>');
                        fornecedorResultados.appendChild(vazio);
                        fornecedorResultados.style.display = 'block';
                        return;
                    }
                    const cadastrar = document.createElement('a');
                    cadastrar.href = fornecedorCadastrarUrl + '?razao_social=' + encodeURIComponent(termo);
                    cadastrar.target = '_blank';
                    cadastrar.className = 'btn btn-sm btn-primary';
                    cadastrar.innerHTML = '<i class="bi bi-plus-lg me-1"></i>';
                    cadastrar.append('Cadastrar "' + termo + '" como fornecedor');
                    vazio.appendChild(cadastrar);
                    fornecedorResultados.appendChild(vazio);
                    fornecedorResultados.style.display = 'block';
                    return;
                }
                fornecedores.forEach(f => {
                    const item = document.createElement('a');
                    item.href = '#';
                    item.className = 'list-group-item list-group-item-action py-2';
                    const nome = document.createElement('div');
                    nome.className = 'fw-semibold';
                    nome.textContent = f.razao_social;
                    const doc = document.createElement('small');
                    doc.className = 'text-muted';
                    doc.textContent = f.cpf_cnpj || 'Sem documento';
                    item.append(nome, doc);
                    item.addEventListener('click', function(e) {
                        e.preventDefault();
                        document.getElementById('fornecedorId').value = f.id;
                        document.getElementById('fornecedorNome').textContent = f.razao_social;
                        document.getElementById('fornecedorDoc').textContent = f.cpf_cnpj || '';
                        document.getElementById('fornecedorSelecionado').style.display = 'block';
                        document.getElementById('fornecedorBuscaGroup').style.display = 'none';
                        document.getElementById('fornecedorAviso').style.display = 'none';
                        fornecedorResultados.style.display = 'none';
                    });
                    fornecedorResultados.appendChild(item);
                });
                fornecedorResultados.style.display = 'block';
            })
            .catch(() => {
                // Sessão caída ou rede: avisa em vez de parecer "não achou nada" (armadilha 74).
                fornecedorResultados.innerHTML = '<div class="list-group-item small text-danger py-2">Não consegui buscar agora. Recarregue a página e tente de novo.</div>';
                fornecedorResultados.style.display = 'block';
            });
        }, 300);
    });

    fornecedorBusca.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') fornecedorResultados.style.display = 'none';
    });

    document.getElementById('btnRemoverFornecedor').addEventListener('click', function() {
        document.getElementById('fornecedorId').value = '';
        document.getElementById('fornecedorSelecionado').style.display = 'none';
        document.getElementById('fornecedorBuscaGroup').style.display = 'flex';
        fornecedorBusca.value = '';
        fornecedorBusca.focus();
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#fornecedorBusca') && !e.target.closest('#fornecedorResultados')) {
            fornecedorResultados.style.display = 'none';
        }
    });

    // Voltou da aba de cadastro: refaz a busca com o que já está digitado.
    window.addEventListener('focus', function() {
        if (tipoFavorecido() === 'fornecedor' && !document.getElementById('fornecedorId').value && fornecedorBusca.value.trim().length >= 2) {
            fornecedorBusca.dispatchEvent(new Event('input'));
        }
    });

    // Sem favorecido não salva: avisa na tela em vez de mandar pro servidor.
    // stopPropagation: sem ele o erp-core (ouvinte no document) trava o botão Salvar por 10 s
    // com o "carregando", mesmo o envio tendo sido barrado aqui.
    fornecedorBusca.closest('form').addEventListener('submit', function(e) {
        const tipo = tipoFavorecido();
        if (tipo === 'fornecedor') {
            if (document.getElementById('fornecedorId').value) return;
            e.preventDefault();
            e.stopPropagation();
            const aviso = document.getElementById('fornecedorAviso');
            aviso.textContent = fornecedorBusca.value.trim()
                ? 'Você digitou o nome, mas não escolheu o fornecedor na lista. Clique no nome na lista ou, se ele não aparecer, cadastre o fornecedor.'
                : 'Escolha o fornecedor: digite o nome e clique nele na lista. Se a conta é de salário ou imposto, mude o "Pagar para" acima.';
            aviso.style.display = 'block';
            fornecedorBusca.classList.add('is-invalid');
            fornecedorBusca.focus();
            fornecedorBusca.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        const campo = document.getElementById(tipo === 'funcionario' ? 'funcionario_id' : 'favorecido_nome');
        if (campo.value.trim()) return;
        e.preventDefault();
        e.stopPropagation();
        campo.classList.add('is-invalid');
        campo.focus();
        campo.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
})();
</script>
@endpush
