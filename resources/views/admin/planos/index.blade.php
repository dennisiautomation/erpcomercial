@extends('layouts.app')

@section('title', 'Planos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-credit-card-2-front me-2"></i>Planos</h4>
    <a href="{{ route('admin.planos.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Novo Plano
    </a>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Ordem</th>
                    <th>Nome</th>
                    <th>Slug</th>
                    <th class="text-end">Mensal</th>
                    <th class="text-end">Anual</th>
                    <th class="text-center">Unid.</th>
                    <th class="text-center">Users</th>
                    <th class="text-center">Prod.</th>
                    <th class="text-center">Notas/mes</th>
                    <th class="text-center">Features</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Empresas</th>
                    <th class="text-end">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($planos as $plano)
                    <tr>
                        <td>{{ $plano->ordem }}</td>
                        <td class="fw-semibold">{{ $plano->nome }}</td>
                        <td><code>{{ $plano->slug }}</code></td>
                        <td class="text-end">R$ {{ number_format($plano->preco_mensal, 2, ',', '.') }}</td>
                        <td class="text-end">R$ {{ number_format($plano->preco_anual, 2, ',', '.') }}</td>
                        <td class="text-center">{{ $plano->max_unidades }}</td>
                        <td class="text-center">{{ $plano->max_usuarios }}</td>
                        <td class="text-center">{{ number_format($plano->max_produtos, 0, ',', '.') }}</td>
                        <td class="text-center">{{ number_format($plano->max_notas_mes, 0, ',', '.') }}</td>
                        <td class="text-center">
                            @php
                                $features = [
                                    'PDV' => $plano->pdv_habilitado,
                                    'Fiscal' => $plano->fiscal_habilitado,
                                    'Multi' => $plano->multilojas_habilitado,
                                    'OS' => $plano->os_habilitado,
                                    'Contr.' => $plano->contratos_habilitado,
                                    'Conc.' => $plano->conciliacao_habilitada,
                                    'DRE' => $plano->dre_habilitado,
                                    'Bol.' => $plano->boletos_habilitado,
                                    'API' => $plano->api_habilitada,
                                ];
                            @endphp
                            @foreach($features as $label => $enabled)
                                <span class="badge {{ $enabled ? 'bg-success' : 'bg-secondary' }} me-1" title="{{ $label }}">
                                    {{ $label }}
                                </span>
                            @endforeach
                        </td>
                        <td class="text-center">
                            @if($plano->ativo)
                                <span class="badge bg-success">Ativo</span>
                            @else
                                <span class="badge bg-secondary">Inativo</span>
                            @endif
                        </td>
                        <td class="text-center">{{ $plano->empresas()->count() }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.planos.edit', $plano) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @if($plano->empresas()->count() === 0)
                                <form method="POST" action="{{ route('admin.planos.destroy', $plano) }}" class="d-inline"
                                      data-confirm="Tem certeza que deseja excluir este plano?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="text-center text-muted py-4">Nenhum plano cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
