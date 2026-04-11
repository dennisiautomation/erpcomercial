@props(['title' => null, 'icon' => null, 'class' => ''])
<div class="erp-card {{ $class }}">
    @if($title)
    <div class="card-header">
        @if($icon)<i class="bi bi-{{ $icon }} me-1"></i>@endif
        {{ $title }}
    </div>
    @endif
    <div class="card-body">
        {{ $slot }}
    </div>
</div>
