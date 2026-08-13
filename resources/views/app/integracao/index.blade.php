@extends('layouts.app')

@section('title', 'Integrações')

@section('content')
<x-erp.page-header title="Integrações"
                   subtitle="Tokens de leitura para sistemas parceiros importarem seus dados"
                   icon="plug">
    <a href="{{ route('app.configuracoes.edit') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Configurações
    </a>
</x-erp.page-header>

<div class="alert alert-info d-flex align-items-start">
    <i class="bi bi-info-circle me-2 mt-1"></i>
    <div>
        O token dá acesso <strong>somente de leitura</strong> às suas vendas, lojas e vendedores
        — é o que o <strong>Gersen</strong> usa para importar as vendas automaticamente.
        Ele aparece <strong>uma única vez</strong>, logo depois de gerado: copie e cole no
        assistente de conexão do Gersen. Se vazar, revogue e gere outro.
    </div>
</div>

@if(session('novo_token'))
<div class="alert alert-warning">
    <div class="fw-semibold mb-2">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Copie o token agora — ele não será exibido de novo:
    </div>
    <div class="input-group">
        <input type="text" class="form-control font-monospace" id="novoTokenValor"
               value="{{ session('novo_token') }}" readonly onclick="this.select()">
        <button class="btn btn-outline-secondary" type="button"
                onclick="navigator.clipboard.writeText(document.getElementById('novoTokenValor').value); this.innerText='Copiado!'">
            <i class="bi bi-clipboard me-1"></i>Copiar
        </button>
    </div>
</div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <x-erp.data-table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Criado em</th>
                    <th>Último uso</th>
                    <th class="text-center">Situação</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tokens as $token)
                <tr>
                    <td class="fw-semibold">{{ $token->nome }}</td>
                    <td class="text-muted">{{ $token->created_at->format('d/m/Y H:i') }}</td>
                    <td class="text-muted">
                        @if($token->last_used_at)
                            {{ $token->last_used_at->format('d/m/Y H:i') }}
                        @else
                            Nunca usado
                        @endif
                    </td>
                    <td class="text-center">
                        @if($token->ativo)
                            <span class="badge bg-success bg-opacity-10 text-success">Ativo</span>
                        @else
                            <span class="badge bg-secondary bg-opacity-10 text-secondary">Revogado</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($token->ativo)
                        <form method="POST" action="{{ route('app.integracao.revogar', $token) }}"
                              onsubmit="return confirm('Revogar este token? A integração que o usa para de sincronizar na hora.')">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger">Revogar</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <i class="bi bi-plug fs-1 d-block mb-2 opacity-25"></i>
                        <p class="text-muted mb-0">Nenhum token gerado ainda — crie um ao lado para conectar o Gersen.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </x-erp.data-table>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Gerar token novo</h6></div>
            <div class="card-body">
                <form method="POST" action="{{ route('app.integracao.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="nome" class="form-control" value="Gersen" maxlength="80">
                        <small class="text-muted">Identifica quem usa o token (ex.: Gersen).</small>
                    </div>
                    <button type="submit" class="btn btn-erp-primary w-100">
                        <i class="bi bi-key me-1"></i> Gerar token
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
