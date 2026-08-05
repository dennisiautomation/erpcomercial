@extends('layouts.app')

@section('title', 'Minhas Lojas')

@section('content')
<x-erp.page-header title="Minhas Lojas" subtitle="Cadastro e situação fiscal das lojas da sua empresa" icon="shop">
    @php
        $podeCriarLoja = auth()->user()->isDono()
            || (auth()->user()->perfil instanceof \App\Enums\Perfil
                ? auth()->user()->perfil->value === 'gerente'
                : auth()->user()->perfil === 'gerente');
    @endphp
    @if($podeCriarLoja)
        @if($limiteAtingido)
            <span class="badge bg-warning text-dark align-self-center me-2" title="Fale com a IA365 para ampliar o plano">
                Limite de lojas do plano atingido
            </span>
        @else
            <a href="{{ route('app.lojas.create') }}" class="btn btn-erp-primary">
                <i class="bi bi-plus-lg me-1"></i> Nova Loja
            </a>
        @endif
    @endif
</x-erp.page-header>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr class="bg-light">
                    <th class="ps-3">Loja</th>
                    <th>CNPJ</th>
                    <th>Cidade/UF</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Fiscal</th>
                    <th class="text-center pe-3" style="width:100px">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lojas as $loja)
                    @php
                        $cfg = $configs->get($loja->id);
                        $sessaoAtual = (int) session('unidade_id') === $loja->id;
                    @endphp
                    <tr>
                        <td class="ps-3">
                            <span class="fw-semibold">{{ $loja->nome }}</span>
                            @if($sessaoAtual)
                                <span class="badge bg-primary bg-opacity-10 text-primary ms-1">atual</span>
                            @endif
                        </td>
                        <td class="small">{{ $loja->cnpj ?: $empresa->cnpj }}</td>
                        <td class="small">{{ $loja->cidade ?: '-' }}{{ $loja->uf ? '/' . $loja->uf : '' }}</td>
                        <td class="text-center"><x-erp.status-badge :status="$loja->status" /></td>
                        <td class="text-center">
                            @if($cfg && $cfg->focus_empresa_id && $cfg->emissao_fiscal_ativa)
                                <span class="badge bg-success bg-opacity-10 text-success" title="Vínculo Focus NFe ativo">
                                    <i class="bi bi-check-circle me-1"></i>Pronta
                                </span>
                            @elseif($cfg && $cfg->emissao_fiscal_ativa)
                                <span class="badge bg-warning bg-opacity-10 text-warning" title="Aguardando provisionamento na Focus NFe">
                                    <i class="bi bi-hourglass-split me-1"></i>Provisionando
                                </span>
                            @else
                                <span class="badge bg-secondary bg-opacity-10 text-secondary">Sem emissão</span>
                            @endif
                        </td>
                        <td class="text-center pe-3">
                            @if($podeEditar($loja))
                                <a href="{{ route('app.lojas.edit', $loja) }}" class="btn btn-sm btn-erp-outline" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-erp.empty-state title="Nenhuma loja cadastrada" icon="shop" description="Cadastre a primeira loja da sua empresa." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<p class="text-muted small mt-3 mb-0">
    <i class="bi bi-info-circle me-1"></i>
    Lojas com o mesmo CNPJ compartilham certificado A1, CSC e numeração das notas.
    Para excluir uma loja, fale com o suporte da IA365 — aqui você pode inativá-la na edição.
</p>
@endsection
