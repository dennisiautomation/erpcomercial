@extends('layouts.app')

@section('title', 'Editar Loja')

@section('content')
<x-erp.page-header title="Editar Loja" subtitle="{{ $loja->nome }}" icon="shop">
    <a href="{{ route('app.lojas.index') }}" class="btn btn-erp-outline"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
</x-erp.page-header>

<form action="{{ route('app.lojas.update', $loja) }}" method="POST">
    @csrf
    @method('PUT')
    @include('app.lojas._form')

    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="{{ route('app.lojas.index') }}" class="btn btn-erp-outline">Cancelar</a>
        <button type="submit" class="btn btn-erp-primary"><i class="bi bi-check-lg me-1"></i> Salvar Alterações</button>
    </div>
</form>
@endsection
