@extends('layouts.app')

@section('title', 'Open Finance')

@section('content')
<div class="fade-in">
<div class="page-header">
    <div>
        <h4><i class="bi bi-bank me-2"></i>Open Finance</h4>
        <div class="subtitle">O extrato do seu banco direto no sistema</div>
    </div>
</div>

<div class="erp-card p-4" style="max-width: 760px;">
    <h5 class="mb-3">Ligue a conta do banco e pare de conferir extrato à mão</h5>
    <ul class="mb-4" style="line-height: 1.9;">
        <li>O que entra e sai da conta aparece aqui sozinho, várias vezes por dia.</li>
        <li>O sistema cruza cada lançamento com as suas contas a receber e a pagar e já mostra qual é qual.</li>
        <li>Você só confere e confirma — a baixa sai com a data e o valor que estão no banco.</li>
        <li>A autorização é feita por você, dentro do seu banco, e pode ser desligada quando quiser.</li>
    </ul>
    <div class="alert alert-info mb-0">
        <i class="bi bi-info-circle me-1"></i>
        Esta função não faz parte do seu plano atual. Para ligar, fale com a IA365.
    </div>
</div>
</div>
@endsection
