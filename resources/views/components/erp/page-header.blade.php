@props(['title', 'subtitle' => null, 'icon' => null])
<div class="page-header fade-in">
    <div>
        <h4>@if($icon)<i class="bi bi-{{ $icon }} me-2"></i>@endif{{ $title }}</h4>
        @if($subtitle)<p class="subtitle mb-0">{{ $subtitle }}</p>@endif
    </div>
    <div class="d-flex gap-2 flex-wrap">{{ $slot }}</div>
</div>
