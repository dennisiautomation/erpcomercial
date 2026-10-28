@props(['title', 'subtitle' => null, 'icon' => null])
<div class="page-header fade-in">
    <div>
        <h1>@if($icon)<i class="bi bi-{{ $icon }} me-2" aria-hidden="true"></i>@endif{{ $title }}</h1>
        @if($subtitle)<p class="subtitle mb-0">{{ $subtitle }}</p>@endif
        @isset($breadcrumb)<div class="page-breadcrumb">{{ $breadcrumb }}</div>@endisset
    </div>
    <div class="d-flex gap-2 flex-wrap">{{ $slot }}</div>
</div>
