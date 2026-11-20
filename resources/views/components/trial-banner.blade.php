@auth
    @if(!auth()->user()->is_admin && auth()->user()->empresa)
        @php
            $empresaTrial = auth()->user()->empresa;
        @endphp
        {{-- Trial só aparece quando é o trial que governa o acesso:
             cobrança direta (licença mensal/anual paga à IA365) e regimes
             cortesia/parceiro/pós-pago não são avaliação. --}}
        @if($empresaTrial->em_trial && $empresaTrial->isTrialActive()
            && ! $empresaTrial->temCobrancaDireta() && ! $empresaTrial->ehGratuita()
            && ! $empresaTrial->assinatura_solicitada_em)
            <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center mb-3 py-2" role="alert" id="trialBanner">
                <i class="bi bi-clock-history me-2"></i>
                <div class="flex-grow-1">
                    <strong>Período de avaliação.</strong>
                    {{ $empresaTrial->diasRestantesTrial() }} dias restantes.
                    {{-- Veio do site (20/11/2026): quem cuida das faturas assina daqui mesmo --}}
                    @if($empresaTrial->veioDoSite() && \App\Services\Plataforma\FaturasPlataforma::podeVer(auth()->user()))
                        <a href="{{ route('app.assinatura.index') }}" class="alert-link" data-teste-assinar>Assinar agora</a>
                    @elseif(auth()->user()->isDono())
                        <a href="{{ route('app.plano.index') }}" class="alert-link">Ver planos</a>
                    @endif
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        @endif
    @endif
@endauth
