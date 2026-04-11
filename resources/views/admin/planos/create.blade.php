@extends('layouts.app')

@section('title', 'Novo Plano')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-credit-card-2-front me-2"></i>Novo Plano</h4>
    <a href="{{ route('admin.planos.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<form method="POST" action="{{ route('admin.planos.store') }}">
    @csrf
    @include('admin.planos._form')
</form>
@endsection
