@extends('layouts.app')

@section('title', 'Entrada por NF-e')

@section('content')
<x-erp.page-header title="Entrada por NF-e" icon="box-arrow-in-down"
    subtitle="Envie o XML da nota de compra: o sistema lê os produtos, você confere e a mercadoria entra no estoque">
    <a href="{{ route('app.movimentacoes.index') }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left-right me-1"></i> Movimentações
    </a>
</x-erp.page-header>

<div class="row g-3">
    <div class="col-lg-5">
        <x-erp.card title="1. Enviar o XML da nota" icon="filetype-xml">
            <form method="POST" action="{{ route('app.entradas-nfe.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="file" name="xml" accept=".xml,text/xml,application/xml"
                       class="form-control @error('xml') is-invalid @enderror" required>
                @error('xml')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text mb-3">
                    É o arquivo <strong>.xml</strong> que o fornecedor manda junto com o DANFE (por e-mail ou no portal dele).
                    O PDF não serve: ele não traz os dados dos produtos.
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-upload me-1"></i> Ler a nota
                </button>
            </form>
        </x-erp.card>

        <div class="card border-0 bg-light mt-3">
            <div class="card-body small text-muted">
                <strong class="text-body d-block mb-1">Como funciona</strong>
                <ol class="mb-0 ps-3">
                    <li>Você envia o XML (ou clica em <i class="bi bi-box-arrow-in-down"></i> numa nota de <a href="{{ route('app.nfes-recebidas.index') }}">NFes Recebidas</a>).</li>
                    <li>Cada produto da nota é ligado a um produto seu — ou vira um cadastro novo.</li>
                    <li>Ao confirmar, a quantidade entra no estoque, o custo é atualizado e, se quiser, as parcelas viram contas a pagar.</li>
                    <li>Da próxima nota do mesmo fornecedor, os produtos já vêm ligados sozinhos.</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <x-erp.card title="Notas lançadas e em conferência" icon="list-check">
            @if($entradas->isEmpty())
                <x-erp.empty-state title="Nenhuma entrada ainda" icon="inbox"
                    description="Envie o XML da primeira nota de compra ao lado." />
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Nota</th>
                                <th>Fornecedor</th>
                                <th class="text-end">Valor</th>
                                <th>Situação</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($entradas as $e)
                                <tr>
                                    <td>
                                        <strong class="small">nº {{ $e->numero }}</strong>
                                        <small class="text-muted d-block">{{ $e->data_emissao?->format('d/m/Y') }}</small>
                                    </td>
                                    <td class="small">{{ $e->fornecedor?->razao_social ?? $e->nome_emitente }}</td>
                                    <td class="text-end small">R$ {{ number_format((float) $e->valor_total, 2, ',', '.') }}</td>
                                    <td>
                                        @if($e->concluida())
                                            <span class="badge bg-success">No estoque</span>
                                            <small class="text-muted d-block">{{ $e->concluida_em?->timezone('America/Sao_Paulo')->format('d/m H:i') }} · {{ $e->concluidor?->name }}</small>
                                        @else
                                            <span class="badge bg-warning text-dark">Em conferência</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('app.entradas-nfe.show', $e) }}" class="btn btn-sm btn-outline-primary">
                                            {{ $e->concluida() ? 'Ver' : 'Conferir' }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($entradas->hasPages())
                    <div class="mt-3">{{ $entradas->links() }}</div>
                @endif
            @endif
        </x-erp.card>
    </div>
</div>
@endsection
