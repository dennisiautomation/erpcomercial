@props(['importRoute', 'templateType'])
<div class="btn-group">
    <button type="button" data-import="{{ $importRoute }}" class="btn btn-erp-outline">
        <i class="bi bi-upload me-1"></i> Importar CSV
    </button>
    <a href="{{ route('app.import.template', $templateType) }}" class="btn btn-erp-outline">
        <i class="bi bi-download me-1"></i> Modelo
    </a>
</div>
