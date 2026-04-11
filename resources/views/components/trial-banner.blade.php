@auth
    @if(!auth()->user()->is_admin && auth()->user()->empresa)
        @php
            $empresaTrial = auth()->user()->empresa;
        @endphp
        @if($empresaTrial->em_trial && $empresaTrial->isTrialActive())
            <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center mb-3 py-2" role="alert" id="trialBanner">
                <i class="bi bi-clock-history me-2"></i>
                <div class="flex-grow-1">
                    <strong>Periodo de avaliacao.</strong>
                    {{ $empresaTrial->diasRestantesTrial() }} dias restantes.
                    <a href="{{ route('app.plano.index') }}" class="alert-link">Ver planos</a>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        @endif
    @endif
@endauth
