@extends('layouts.app')

@section('title', 'Backups de XML')

@section('content')
<x-erp.page-header title="Backups Mensais de XML" icon="file-earmark-zip" />

<div class="alert alert-info small">
    <i class="bi bi-info-circle me-1"></i>
    Backup mensal de todos os XMLs fiscais (NF-e, NFC-e, NFS-e autorizadas e canceladas)
    em um único arquivo ZIP. A Focus NFe retém XMLs por 5 anos, mas é <strong>obrigação do
    contribuinte manter cópia própria</strong>. Gere o backup e guarde em local seguro.
</div>

@if(! $fiscalAtivo)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Ative a emissão fiscal e configure o token Focus NFe para gerar backups.
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
                    $b = $backups[$mes] ?? ['status' => 'indisponivel', 'url' => null];
                    [$ano, $m] = explode('-', $mes);
                    $nomeMes = \Carbon\Carbon::createFromDate($ano, (int) $m, 1)->translatedFormat('F / Y');
                @endphp
                <tr>
                    <td>
                        <strong>{{ $nomeMes }}</strong>
                        <br><small class="text-muted font-monospace">{{ $mes }}</small>
                    </td>
                    <td>
                        @switch($b['status'])
                            @case('concluido')
                                <span class="badge bg-success"><i class="bi bi-check2 me-1"></i>Pronto</span>
                                @break
                            @case('processando')
                                <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Processando</span>
                                @break
                            @case('indisponivel')
                                <span class="badge bg-secondary">Não gerado</span>
                                @break
                            @default
                                <span class="badge bg-secondary">{{ ucfirst($b['status']) }}</span>
                        @endswitch
                    </td>
                    <td>
                        @if($b['url'])
                            <a href="{{ $b['url'] }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-download me-1"></i>Baixar ZIP
                            </a>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <form action="{{ route('app.backups-xml.gerar') }}" method="POST" class="d-inline"
                              data-confirm="Gerar backup de {{ $nomeMes }}? Pode demorar alguns minutos.">
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
