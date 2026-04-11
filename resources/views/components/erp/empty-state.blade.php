@props(['icon' => 'inbox', 'title' => 'Nenhum registro encontrado', 'description' => null, 'actionUrl' => null, 'actionLabel' => null])
<div class="empty-state">
    <i class="bi bi-{{ $icon }}"></i>
    <h5>{{ $title }}</h5>
    @if($description)<p>{{ $description }}</p>@endif
    @if($actionUrl)
        <a href="{{ $actionUrl }}" class="btn btn-erp-primary mt-2">
            <i class="bi bi-plus-lg me-1"></i> {{ $actionLabel ?? 'Criar Novo' }}
        </a>
    @endif
</div>
