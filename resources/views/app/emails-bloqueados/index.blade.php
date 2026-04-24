@extends('layouts.app')

@section('title', 'Emails Bloqueados')

@section('content')
<x-erp.page-header title="Emails Bloqueados" icon="envelope-slash">
    <a href="{{ route('app.configuracao-fiscal.edit') }}" class="btn btn-outline-secondary">
        <i class="bi bi-gear me-1"></i>Configurações
    </a>
</x-erp.page-header>

<div class="alert alert-info small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    A Focus NFe bloqueia emails que dão bounce repetido para economizar recursos e não
    prejudicar sua reputação de remetente. Se você corrigiu o email de um cliente e quer
    que os DANFEs/XMLs voltem a ser enviados, desbloqueie abaixo.
</div>

@if(! $fiscalAtivo)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Ative a emissão fiscal e configure o token Focus NFe para consultar emails bloqueados.
    </div>
@elseif($erro)
    <div class="alert alert-danger">
        <i class="bi bi-x-circle me-1"></i>
        {{ $erro }}
    </div>
@else
    <x-erp.data-table>
        <thead>
            <tr>
                <th>Email</th>
                <th>Motivo do bloqueio</th>
                <th>Bloqueado em</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse($emails as $item)
                <tr>
                    <td>
                        <i class="bi bi-envelope-x text-danger me-1"></i>
                        <span class="font-monospace">{{ $item['email'] }}</span>
                    </td>
                    <td>
                        <span class="small">{{ $item['motivo'] }}</span>
                    </td>
                    <td class="small text-muted">
                        @if($item['bloqueado_em'])
                            {{ \Carbon\Carbon::parse($item['bloqueado_em'])->format('d/m/Y H:i') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-end">
                        <form action="{{ route('app.emails-bloqueados.desbloquear', urlencode($item['email'])) }}"
                              method="POST" class="d-inline"
                              data-confirm="Desbloquear {{ $item['email'] }}? A Focus voltará a tentar entregas.">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-success">
                                <i class="bi bi-envelope-check me-1"></i>Desbloquear
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">
                    <x-erp.empty-state icon="envelope-check" title="Nenhum email bloqueado"
                        description="Todos os emails de clientes estão recebendo DANFEs/XMLs normalmente." />
                </td></tr>
            @endforelse
        </tbody>
    </x-erp.data-table>
@endif
@endsection
