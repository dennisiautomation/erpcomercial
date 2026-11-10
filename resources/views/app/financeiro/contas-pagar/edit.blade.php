@extends('layouts.app')
@php $errors = $errors ?? new \Illuminate\Support\ViewErrorBag(); @endphp

@section('title', 'Editar Conta a Pagar')

@section('content')
<x-erp.page-header title="Editar Conta a Pagar #{{ $contaPagar->id }}" subtitle="Ajuste valor, vencimento ou classificação antes de pagar" icon="pencil">
    <a href="{{ route('app.contas-pagar.show', $contaPagar) }}" class="btn btn-erp-outline">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</x-erp.page-header>

<div class="row">
    <div class="col-lg-8">
        <form method="POST" action="{{ route('app.contas-pagar.update', $contaPagar) }}">
            @csrf
            @method('PUT')

            @include('app.financeiro.contas-pagar._campos', ['conta' => $contaPagar])

            @if($proximasPendentes > 0)
            <div class="card border-0 bg-light mb-4">
                <div class="card-body">
                    <div class="form-check form-switch mb-0">
                        <input type="hidden" name="aplicar_proximas" value="0">
                        <input class="form-check-input" type="checkbox" name="aplicar_proximas" value="1" id="aplicar_proximas"
                            {{ old('aplicar_proximas') ? 'checked' : '' }} style="transform:scale(1.2);">
                        <label class="form-check-label fw-bold ms-2" for="aplicar_proximas">
                            Aplicar também nas próximas {{ $proximasPendentes }} {{ $proximasPendentes === 1 ? 'conta pendente' : 'contas pendentes' }} desta série
                        </label>
                    </div>
                    <div class="small text-muted mt-2">
                        Marque quando o valor mudou daqui para a frente (salário novo, aluguel reajustado). Leva valor, favorecido, descrição,
                        plano de contas, centro de custo e forma de pagamento. O vencimento e o mês de cada uma não mudam.
                        Deixe desmarcado para ajustar só este mês.
                    </div>
                </div>
            </div>
            @endif

            <div class="mb-4">
                <label for="observacoes" class="form-label fw-semibold">Observações</label>
                <textarea name="observacoes" id="observacoes" rows="3" maxlength="500"
                    class="form-control @error('observacoes') is-invalid @enderror"
                    placeholder="Informações adicionais...">{{ old('observacoes', $contaPagar->observacoes) }}</textarea>
                @error('observacoes')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex gap-2 mb-4">
                <button type="submit" class="btn btn-erp-primary btn-lg px-4">
                    <i class="bi bi-check-lg me-1"></i> Salvar
                </button>
                <a href="{{ route('app.contas-pagar.show', $contaPagar) }}" class="btn btn-erp-outline btn-lg">Cancelar</a>
            </div>
        </form>
    </div>

    <div class="col-lg-4">
        <x-erp.card title="Esta conta" icon="info-circle">
            <div class="small text-muted">
                <div class="mb-2">Parcela <strong>{{ $contaPagar->parcela }}/{{ $contaPagar->total_parcelas }}</strong>{{ $contaPagar->recorrente ? ' — recorrente (' . ($contaPagar->recorrencia_tipo ?? 'mensal') . ')' : '' }}</div>
                <div>Depois de paga, a conta não é mais editada: estorne a baixa se precisar corrigir.</div>
            </div>
        </x-erp.card>
    </div>
</div>
@endsection
