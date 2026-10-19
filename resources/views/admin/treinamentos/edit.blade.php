@extends('layouts.app')

@section('title', 'Editar Treinamento')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-play-btn me-2"></i>Editar Treinamento: {{ $treinamento->titulo }}</h4>
    <a href="{{ route('admin.treinamentos.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<form method="POST" action="{{ route('admin.treinamentos.update', $treinamento) }}" id="formTreinamento">
    @csrf
    @method('PUT')
    @include('admin.treinamentos._form')
</form>
@endsection
