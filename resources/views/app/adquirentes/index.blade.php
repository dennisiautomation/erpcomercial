@extends('layouts.app')

@section('title', 'Máquinas de Cartão — Taxas e Prazos')

@section('content')
<x-erp.page-header title="Máquinas de Cartão" icon="credit-card-2-front"
    subtitle="Taxas e prazos de recebimento por forma e faixa de parcelas">
    <a href="{{ route('app.adquirentes.recebiveis') }}" class="btn btn-outline-primary">
        <i class="bi bi-calendar-check me-1"></i> Recebíveis de Cartão
    </a>
</x-erp.page-header>

@php $formas = ['cartao_debito' => 'Cartão de Débito', 'cartao_credito' => 'Cartão de Crédito']; @endphp

<x-erp.card title="Nova regra" icon="plus-circle" class="mb-4">
    <form method="POST" action="{{ route('app.adquirentes.store') }}" class="row g-2 align-items-end erp-form">
        @csrf
        <div class="col-md-3">
            <label class="form-label">Adquirente / máquina</label>
            <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror"
                   placeholder="Stone, Cielo, PagSeguro..." value="{{ old('nome') }}" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Forma</label>
            <select name="forma" class="form-select" required>
                @foreach($formas as $valor => $label)
                    <option value="{{ $valor }}" @selected(old('forma') === $valor)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label">Parc. de</label>
            <input type="number" name="parcelas_de" class="form-control" min="1" max="24" value="{{ old('parcelas_de', 1) }}" required>
        </div>
        <div class="col-md-1">
            <label class="form-label">até</label>
            <input type="number" name="parcelas_ate" class="form-control" min="1" max="24" value="{{ old('parcelas_ate', 1) }}" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Taxa (%)</label>
            <input type="number" name="taxa_percentual" step="0.01" min="0" max="100" class="form-control" value="{{ old('taxa_percentual', '0.00') }}" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Prazo (D+)</label>
            <input type="number" name="prazo_dias" min="0" max="365" class="form-control" value="{{ old('prazo_dias', 1) }}" required>
        </div>
        <div class="col-md-1">
            <input type="hidden" name="ativo" value="1">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i></button>
        </div>
        @error('parcelas_ate')<div class="col-12 text-danger small">{{ $message }}</div>@enderror
    </form>
    <div class="form-text mt-2">
        Ex.: crédito 1x a 6x, taxa 3,5%, D+30. Nas vendas parceladas, a 1ª parcela cai em D+prazo e as
        demais a cada 30 dias. O PDV usa essas regras para lançar o contas a receber com valor líquido
        e data prevista.
    </div>
</x-erp.card>

<x-erp.card title="Regras cadastradas" icon="list-ul">
    <x-erp.data-table>
        <thead>
            <tr>
                <th>Adquirente</th>
                <th>Forma</th>
                <th>Parcelas</th>
                <th class="text-end">Taxa</th>
                <th class="text-end">Prazo</th>
                <th>Status</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse($taxas as $taxa)
            <tr>
                <form method="POST" action="{{ route('app.adquirentes.update', $taxa) }}">
                @csrf
                @method('PUT')
                <td style="min-width:140px;">
                    <input type="text" name="nome" class="form-control form-control-sm" value="{{ $taxa->nome }}" required>
                </td>
                <td style="min-width:150px;">
                    <select name="forma" class="form-select form-select-sm">
                        @foreach($formas as $valor => $label)
                            <option value="{{ $valor }}" @selected($taxa->forma === $valor)>{{ $label }}</option>
                        @endforeach
                    </select>
                </td>
                <td style="min-width:130px;">
                    <div class="d-flex gap-1 align-items-center">
                        <input type="number" name="parcelas_de" class="form-control form-control-sm" style="width:60px" min="1" max="24" value="{{ $taxa->parcelas_de }}">
                        <span class="text-muted">a</span>
                        <input type="number" name="parcelas_ate" class="form-control form-control-sm" style="width:60px" min="1" max="24" value="{{ $taxa->parcelas_ate }}">
                    </div>
                </td>
                <td class="text-end" style="min-width:100px;">
                    <input type="number" name="taxa_percentual" step="0.01" min="0" max="100" class="form-control form-control-sm text-end" value="{{ $taxa->taxa_percentual }}">
                </td>
                <td class="text-end" style="min-width:90px;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">D+</span>
                        <input type="number" name="prazo_dias" min="0" max="365" class="form-control form-control-sm text-end" value="{{ $taxa->prazo_dias }}">
                    </div>
                </td>
                <td>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="ativo" value="1" @checked($taxa->ativo)>
                    </div>
                </td>
                <td class="text-end text-nowrap">
                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Salvar"><i class="bi bi-check-lg"></i></button>
                </form>
                    <form method="POST" action="{{ route('app.adquirentes.destroy', $taxa) }}" class="d-inline" data-confirm="Remover esta regra?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Remover"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7">
                    <x-erp.empty-state title="Nenhuma regra cadastrada" icon="credit-card"
                        description="Cadastre as taxas e prazos das suas máquinas para o financeiro prever os recebimentos de cartão." />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-erp.data-table>
</x-erp.card>
@endsection
