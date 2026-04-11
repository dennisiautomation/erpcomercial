@props(['title', 'icon' => null, 'description' => null])
<div class="erp-card mb-4">
    <div class="card-header">
        @if($icon)<i class="bi bi-{{ $icon }} me-1"></i>@endif
        {{ $title }}
        @if($description)<small class="text-muted ms-2">{{ $description }}</small>@endif
    </div>
    <div class="card-body erp-form">
        {{ $slot }}
    </div>
</div>
