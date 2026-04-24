{{--
    Popup de ajuda fiscal. Conteúdo (tabelas oficiais CONFAZ / Receita / TIPI)
    está em public/js/fiscal-help.js para não sobrecarregar o compilador Blade.

    Inclua UMA vez no layout (já está em layouts/app.blade.php).
    Dispare via <x-erp.fiscal-tooltip field="..." /> ou:
        onclick="ErpFiscalHelp.open('ncm')"
--}}
<div class="modal fade" id="erpFiscalHelpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-book me-2"></i>
                    <span id="erpFiscalHelpTitle">Ajuda fiscal</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="erpFiscalHelpBody">
                <div class="text-center text-muted py-4">
                    <span class="spinner-border spinner-border-sm me-2"></span>Carregando…
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Baseado em fontes oficiais (CONFAZ, Receita Federal). Consulte seu contador em caso de dúvida.
                </small>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('js/fiscal-help.js') }}?v={{ filemtime(public_path('js/fiscal-help.js')) }}" defer></script>
