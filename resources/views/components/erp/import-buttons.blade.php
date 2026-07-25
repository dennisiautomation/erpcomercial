@props(['importRoute', 'templateType'])
<div class="btn-group">
    <button type="button" data-import="{{ $importRoute }}" class="btn btn-erp-outline"
            title="Envie a planilha preenchida (.xlsx ou .csv)">
        <i class="bi bi-upload me-1"></i> Importar planilha
    </button>
    <a href="{{ route('app.import.template', $templateType) }}" class="btn btn-erp-outline"
       title="Baixa o modelo em Excel (.xlsx) com as colunas certas e exemplos">
        <i class="bi bi-file-earmark-excel me-1"></i> Modelo Excel
    </a>
</div>
