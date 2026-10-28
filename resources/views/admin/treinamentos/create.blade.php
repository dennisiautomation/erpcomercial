@extends('layouts.app')

@section('title', 'Novo Treinamento')

@section('content')
<x-erp.page-header title="Novo Treinamento" icon="play-btn">
    <a href="{{ route('admin.treinamentos.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
</x-erp.page-header>

<form method="POST" action="{{ route('admin.treinamentos.store') }}" id="formTreinamento">
    @csrf
    @include('admin.treinamentos._form')
</form>
@endsection
