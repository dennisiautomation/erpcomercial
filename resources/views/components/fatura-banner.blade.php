{{-- Faixa da fatura do sistema (IA365 → loja), 16/11/2026.
     Dono, gerente, admin e financeiro: desde a emissão, com valor e botão.
     Demais perfis: só com fatura em atraso, e sem valor.
     Quem decide é App\Services\Plataforma\FaturasPlataforma::faixa. --}}
@auth
    @php $faixaFatura = \App\Services\Plataforma\FaturasPlataforma::faixa(auth()->user()); @endphp
    @if($faixaFatura && ! request()->routeIs('app.faturas.*'))
        <div class="alert alert-{{ $faixaFatura['nivel'] }} d-flex flex-wrap align-items-center gap-2 mb-3 py-2 {{ $faixaFatura['nivel'] === 'danger' ? '' : 'alert-dismissible fade show' }}"
             role="alert" id="faturaBanner" data-fatura-faixa="{{ $faixaFatura['nivel'] }}" data-fatura-id="{{ $faixaFatura['fatura_id'] }}"
             @if($faixaFatura['restrita']) data-fatura-restrita @endif>
            <i class="bi bi-{{ $faixaFatura['nivel'] === 'danger' ? 'exclamation-triangle-fill' : 'receipt' }}"></i>
            <div class="flex-grow-1" style="min-width: 12rem;">
                <strong>{{ $faixaFatura['texto'] }}</strong> {{ $faixaFatura['detalhe'] }}
            </div>
            @unless($faixaFatura['restrita'])
                <a href="{{ route('app.faturas.index') }}" class="btn btn-sm btn-{{ $faixaFatura['nivel'] === 'info' ? 'primary' : $faixaFatura['nivel'] }} flex-shrink-0" data-fatura-ver>Ver fatura</a>
            @endunless
            @if($faixaFatura['nivel'] !== 'danger')
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"
                        onclick="try{sessionStorage.setItem('faturaFaixaFechada','{{ $faixaFatura['fatura_id'] }}-{{ $faixaFatura['nivel'] }}')}catch(e){}"></button>
                <script>try{if(sessionStorage.getItem('faturaFaixaFechada')==='{{ $faixaFatura['fatura_id'] }}-{{ $faixaFatura['nivel'] }}')document.getElementById('faturaBanner').remove()}catch(e){}</script>
            @endif
        </div>
    @endif

@endauth
