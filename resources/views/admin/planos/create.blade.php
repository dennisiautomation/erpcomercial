@extends('layouts.app')

@section('title', 'Novo Plano')

@section('content')
<x-erp.page-header title="Novo Plano" icon="credit-card-2-front">
    <a href="{{ route('admin.planos.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
</x-erp.page-header>

<form method="POST" action="{{ route('admin.planos.store') }}">
    @csrf
    @include('admin.planos._form')
</form>
@endsection
