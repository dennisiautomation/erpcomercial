@extends('layouts.app')

@section('title', 'Nova Loja')

@section('content')
<x-erp.page-header title="Nova Loja" subtitle="A configuração fiscal é preparada automaticamente após salvar" icon="shop">
    <a href="{{ route('app.lojas.index') }}" class="btn btn-erp-outline"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
</x-erp.page-header>

<form action="{{ route('app.lojas.store') }}" method="POST">
    @csrf
    @include('app.lojas._form')

    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="{{ route('app.lojas.index') }}" class="btn btn-erp-outline">Cancelar</a>
        <button type="submit" class="btn btn-erp-primary"><i class="bi bi-check-lg me-1"></i> Cadastrar Loja</button>
    </div>
</form>
@endsection
