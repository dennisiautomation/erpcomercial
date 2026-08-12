@extends('layouts.app')

@section('title', 'Backups de XML')

@section('content')
<x-erp.page-header title="Backups Mensais de XML" icon="file-earmark-zip" />

<div class="alert alert-info small">
    <i class="bi bi-info-circle me-1"></i>
    Pacote mensal com todos os XMLs fiscais transmitidos (NF-e, NFC-e, NFS-e — autorizadas e
    canceladas) num único ZIP, montado a partir das cópias que o sistema guarda de cada nota.
    É <strong>obrigação do contribuinte manter cópia própria</strong> — baixe e guarde em
    local seguro. O ZIP traz um <code>manifest.json</code> com o índice das notas.
</div>

@if(! $fiscalAtivo)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Ative a emissão fiscal para gerar os pacotes mensais de XML.
    </div>
@else
    <x-erp.data-table>
        <thead>
            <tr>
                <th>Mês / Ano</th>
                <th>Status</th>
                <th>Download</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            @foreach($meses as $mes)
                @php
                    $b = $backups[$mes] ?? ['status' => 'indisponivel', 'arquivos' => null, 'atualizado_em' => null];
                    [$ano, $m] = explode('-', $mes);
                    $nomeMes = \Carbon\Carbon::createFromDate($ano, (int) $m, 1)->translatedFormat('F / Y');
                    $mesCorrente = $mes === now()->format('Y-m');
                @endphp
                <tr>
                    <td>
                        <strong>{{ $nomeMes }}</strong>
                        @if($mesCorrente)
                            <span class="badge text-bg-light border ms-1">em andamento</span>
                        @endif
                        <br><small class="text-muted font-monospace">{{ $mes }}</small>
                    </td>
                    <td>
                        @if($b['status'] === 'concluido')
                            <span class="badge bg-success"><i class="bi bi-check2 me-1"></i>Pronto</span>
                            <br><small class="text-muted">{{ $b['arquivos'] }} XML(s)
                                @if($b['atualizado_em']) · {{ \Carbon\Carbon::parse($b['atualizado_em'])->format('d/m H:i') }}@endif
                            </small>
                        @else
                            <span class="badge bg-secondary">Não gerado</span>
                        @endif
                    </td>
                    <td>
                        @if($b['status'] === 'concluido')
                            <a href="{{ route('app.backups-xml.download', $mes) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-download me-1"></i>Baixar ZIP
                            </a>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <form action="{{ route('app.backups-xml.gerar') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="mes" value="{{ $mes }}">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                @if($b['status'] === 'concluido')
                                    <i class="bi bi-arrow-clockwise me-1"></i>Regerar
                                @else
                                    <i class="bi bi-play-circle me-1"></i>Gerar
                                @endif
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-erp.data-table>
@endif
@endsection
