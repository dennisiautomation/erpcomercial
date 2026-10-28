@extends('layouts.app')

@section('title', 'Treinamentos')

@section('content')
<x-erp.page-header title="Treinamentos" icon="play-btn">
    <a href="{{ route('admin.treinamentos.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Novo Treinamento
        </a>
</x-erp.page-header>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Ordem</th>
                    <th>Título</th>
                    <th class="text-end">Tamanho</th>
                    <th>Perfis</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Assistiram</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($treinamentos as $treinamento)
                    <tr>
                        <td>{{ $treinamento->ordem }}</td>
                        <td>
                            <div class="fw-semibold">{{ $treinamento->titulo }}</div>
                            @if($treinamento->descricao)
                                <div class="small text-muted">{{ \Illuminate\Support\Str::limit($treinamento->descricao, 90) }}</div>
                            @endif
                        </td>
                        <td class="text-end">{{ number_format($treinamento->tamanho / 1048576, 1, ',', '.') }} MB</td>
                        <td>
                            <span class="badge bg-light text-dark border">Dono</span>
                            <span class="badge bg-light text-dark border">Gerente</span>
                            @foreach(\App\Models\Treinamento::PERFIS as $perfil)
                                @if(in_array($perfil->value, $treinamento->perfis ?? [], true))
                                    <span class="badge bg-primary bg-opacity-10 text-primary">{{ $perfil->label() }}</span>
                                @endif
                            @endforeach
                        </td>
                        <td class="text-center">
                            @if($treinamento->ativo)
                                <span class="badge bg-success">Ativo</span>
                            @else
                                <span class="badge bg-secondary">Inativo</span>
                            @endif
                        </td>
                        <td class="text-center">{{ $treinamento->usuarios_concluidos_count }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.treinamentos.edit', $treinamento) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.treinamentos.destroy', $treinamento) }}" class="d-inline"
                                  data-confirm="Excluir este treinamento? O vídeo e o registro de quem assistiu também serão apagados.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Nenhum treinamento cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
