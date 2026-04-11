@props(['empty' => 'Nenhum registro encontrado.', 'emptyIcon' => 'inbox'])
<div class="erp-card">
    <div class="table-responsive">
        <table class="erp-table">
            {{ $slot }}
        </table>
    </div>
    @if(isset($pagination))
        <div class="p-3 border-top">{{ $pagination }}</div>
    @endif
</div>
