{{-- ================================================================
     Flash Alerts — success, error, warning, info
     Auto-dismiss after 6 seconds with smooth fade-out
     ================================================================ --}}

@if(session('success'))
    <div class="erp-alert erp-alert-success alert alert-dismissible fade show mb-3" role="alert" data-auto-dismiss="6000">
        <i class="bi bi-check-circle-fill"></i>
        <div class="flex-grow-1">{{ session('success') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
@endif

@if(session('error'))
    <div class="erp-alert erp-alert-danger alert alert-dismissible fade show mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div class="flex-grow-1">{{ session('error') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
@endif

@if(session('warning'))
    <div class="erp-alert erp-alert-warning alert alert-dismissible fade show mb-3" role="alert" data-auto-dismiss="7000">
        <i class="bi bi-exclamation-circle-fill"></i>
        <div class="flex-grow-1">{{ session('warning') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
@endif

@if(session('info'))
    <div class="erp-alert erp-alert-info alert alert-dismissible fade show mb-3" role="alert" data-auto-dismiss="6000">
        <i class="bi bi-info-circle-fill"></i>
        <div class="flex-grow-1">{{ session('info') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
@endif
