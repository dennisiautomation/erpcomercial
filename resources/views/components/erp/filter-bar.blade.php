@props(['action' => ''])
<div class="filter-bar fade-in">
    <form method="GET" action="{{ $action }}" class="row g-2 align-items-end">
        {{ $slot }}
        <div class="col-auto">
            <button type="submit" class="btn btn-erp-outline">
                <i class="bi bi-search me-1"></i> Filtrar
            </button>
        </div>
    </form>
</div>
