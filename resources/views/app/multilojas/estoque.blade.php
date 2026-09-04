@extends('layouts.app')

@section('title', 'Estoque por Loja')

@section('content')
@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();

    // Alguma celula da pagina tem saldo com fracao? (resto do acidente da roda
    // do mouse, armadilha 66 — o rodape so avisa quando ha o que avisar)
    $temFracionario = collect($matriz)->contains(
        fn ($linha) => collect($linha['saldos'])->contains(
            fn ($v) => (float) $v != round((float) $v) || (float) $v < 0
        )
    );
@endphp
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4 class="mb-0">
        <i class="bi bi-boxes me-2"></i>Estoque por Loja
        <small class="text-muted fs-6 d-block">Saldo de cada produto em todas as unidades — edite direto na tabela</small>
    </h4>
    <a href="{{ route('app.multilojas.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i> {{ $errors->first() }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('app.multilojas.estoque') }}" class="row g-2 align-items-center">
            <div class="col-sm-8 col-md-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" value="{{ $busca }}" class="form-control"
                           placeholder="Buscar por descrição, código ou SKU...">
                </div>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit">Buscar</button>
                @if($busca !== '')
                    <a href="{{ route('app.multilojas.estoque') }}" class="btn btn-outline-secondary">Limpar</a>
                @endif
            </div>
        </form>
    </div>
</div>

<form method="POST" action="{{ route('app.multilojas.estoque.ajustar') }}">
    @csrf
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="text-muted small">
                <i class="bi bi-pencil-square me-1"></i>Edite as quantidades e salve tudo de uma vez
            </span>
            <button type="submit" class="btn btn-success" data-salvar disabled>
                <i class="bi bi-save me-1"></i> <span data-salvar-texto>Nenhuma alteração</span>
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:220px; position:sticky; left:0; background:var(--bs-table-bg,#f8f9fa); z-index:2;">Produto</th>
                            @foreach($unidades as $unidade)
                                <th class="text-center" style="min-width:120px;">
                                    {{ $unidade->nome }}
                                </th>
                            @endforeach
                            <th class="text-center" style="min-width:90px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($matriz as $linha)
                            @php $produto = $linha['produto']; @endphp
                            <tr>
                                <td style="position:sticky; left:0; background:#fff; z-index:1;">
                                    <div class="fw-semibold">{{ $produto->descricao }}</div>
                                    <small class="text-muted">{{ $produto->codigo_interno ?? $produto->sku ?? '—' }}</small>
                                </td>
                                @foreach($unidades as $unidade)
                                    @php $saldo = $linha['saldos'][$unidade->id] ?? 0; @endphp
                                    <td class="text-center">
                                        @php
                                            // Saldo com fracao so existe por acidente nesta base
                                            // (armadilha 66): 623 produtos ficaram assim quando o
                                            // campo era type="number" e a roda do mouse somava
                                            // 0,001 por clique.
                                            //
                                            // A tela e de CONTAGEM DE PECA: mostra sempre INTEIRO.
                                            // O amarelo marca quem chegou fracionario, e salvar a
                                            // 04/09 noite: a celula volta a mostrar o SALDO REAL,
                                            // com fracao ("nem que volte em fracao, eu preciso das
                                            // quantidades"). Arredondar na tela escondia o numero
                                            // que esta no banco — e numero escondido assusta mais
                                            // do que numero feio.
                                            //
                                            // Amarelo marca o que precisa de contagem: fracao (o
                                            // ruido da roda do mouse, armadilha 66) ou negativo.
                                            $precisaAcerto = ($saldo != round($saldo)) || $saldo < 0;
                                            $exibido = rtrim(rtrim(number_format($saldo, 3, ',', ''), '0'), ',');
                                        @endphp
                                        {{-- type="text", NAO "number": o spinner do type=number
                                             responde a RODA DO MOUSE numa tabela que rola.
                                             inputmode="numeric" = teclado numerico no celular.
                                             ⚠️ SEM `pattern`: a validacao nativa trava o Salvar do
                                             formulario inteiro com "o formato deve corresponder ao
                                             exigido" e nao diz qual celula — mesmo defeito do
                                             `min=0` que esta tela ja teve. Quem valida e o JS. --}}
                                        {{-- Setas ▲▼ sao botoes MEUS, nao o spinner do navegador:
                                             passo 1, nunca abaixo de 0, e a roda do mouse nao
                                             tem no que mexer. Numa celula com fracao a primeira
                                             seta ja cai no inteiro certo (0,002 ▲ = 1, ▼ = 0). --}}
                                        <div class="qtd-wrap d-inline-flex align-items-stretch" style="max-width:124px;">
                                            <input type="text" inputmode="numeric"
                                                   data-qtd autocomplete="off"
                                                   name="saldos[{{ $produto->id }}][{{ $unidade->id }}]"
                                                   value="{{ $exibido }}"
                                                   @if($precisaAcerto)
                                                       title="{{ $saldo < 0 ? 'Saldo negativo — saiu mais do que tinha em estoque.' : 'Saldo com fração — resto de um acerto antigo da tela. Digite a quantidade contada por cima, ou use as setas.' }}"
                                                   @endif
                                                   class="form-control form-control-sm text-center rounded-end-0
                                                          {{ $precisaAcerto ? 'border-warning bg-warning bg-opacity-10' : ($saldo <= 0 ? 'border-danger text-danger' : '') }}"
                                                   style="width:78px;">
                                            <div class="d-flex flex-column" style="width:22px;">
                                                <button type="button" class="qtd-seta" data-passo="1" tabindex="-1" title="Mais 1">&#9650;</button>
                                                <button type="button" class="qtd-seta" data-passo="-1" tabindex="-1" title="Menos 1">&#9660;</button>
                                            </div>
                                        </div>
                                    </td>
                                @endforeach
                                <td class="text-center fw-bold">
                                    {{ rtrim(rtrim(number_format($linha['total'], 3, ',', ''), '0'), ',') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $unidades->count() + 2 }}" class="text-center text-muted py-4">
                                    Nenhum produto encontrado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-muted small">
                <i class="bi bi-info-circle me-1"></i>
                Só as células que você alterar (ficam em <span class="text-primary fw-semibold">azul</span>)
                são enviadas e geram movimentação de <strong>Ajuste</strong> — as outras não saem daqui
                como estão. Digite a quantidade contada em <strong>número inteiro</strong> (<code>13</code>)
                ou use as setas; sem vírgula e sem sinal de menos.
                @if($temFracionario ?? false)
                    <span class="text-warning d-block">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Células em amarelo estão com saldo <strong>quebrado</strong>
                        (ex.: <code>0,007</code>, resto de um acerto antigo desta tela) ou
                        <strong>negativo</strong>. Digite a quantidade contada por cima para
                        corrigir — só o que você digitar é gravado.
                    </span>
                @endif
                @if(count($matriz) >= 300)
                    <span class="text-warning d-block">Exibindo os primeiros 300 produtos — use a busca para refinar.</span>
                @endif
            </span>
            <button type="submit" class="btn btn-success" data-salvar disabled>
                <i class="bi bi-save me-1"></i> <span data-salvar-texto>Nenhuma alteração</span>
            </button>
        </div>
    </div>
</form>

@push('styles')
<style>
    .qtd-seta { flex:1; border:1px solid #dee2e6; background:#f8f9fa; color:#495057;
                font-size:8px; line-height:1; padding:0; cursor:pointer; user-select:none; }
    .qtd-seta:first-child { border-radius:0 .25rem 0 0; border-bottom-width:0; border-left-width:0; }
    .qtd-seta:last-child  { border-radius:0 0 .25rem 0; border-left-width:0; }
    .qtd-seta:hover, .qtd-seta:active { background:#dee2e6; }
    .qtd-alterada { border-color:#0d6efd !important; box-shadow:0 0 0 .15rem rgba(13,110,253,.25); }
</style>
@endpush

@push('scripts')
<script>
/* Quantidades da tabela de estoque por loja (04/09/2026, rodada 4).
 *
 * Tres garantias, nesta ordem:
 *  1. NADA que o usuario nao tocou sai desta tela. No envio, celula intocada
 *     recebe `disabled` e nem viaja no POST — o servidor so ve o que mudou.
 *     (Alem de proteger o saldo, resolve outro bug: "0,002" no POST nao passa
 *     no `numeric` do Laravel e derrubava o Salvar inteiro com 422.)
 *  2. Celula com fracao e SUBSTITUIDA, nunca editada no meio: focar seleciona
 *     tudo, e enquanto ela ainda tiver o valor original, cada clique seleciona
 *     de novo. Digitar 5 em "0,002" da 5 — nao 0, nao 50.
 *  3. Setas ▲▼ sao botoes nossos (a roda do mouse nao faz nada — foi o spinner
 *     nativo que gerou os 817 ajustes quebrados). Passo 1, nunca abaixo de 0,
 *     e numa fracao a primeira seta ja cai no inteiro certo: 0,002 ▲ = 1, ▼ = 0.
 *
 * O usuario ve o que vai ser gravado antes de gravar: celula alterada fica
 * azul e o botao diz "Salvar N alteracoes" (desabilitado com zero).
 */
(function () {
    const form = document.querySelector('form[action="{{ route('app.multilojas.estoque.ajustar') }}"]');
    if (!form) return;

    const celulas  = () => Array.from(form.querySelectorAll('input[data-qtd]'));
    const alterada = el => el.value.trim() !== '' && el.value.trim() !== el.defaultValue;

    // Guarda o visual original (amarelo/vermelho) para restaurar se o usuario
    // voltar ao valor de antes.
    celulas().forEach(el => { el.dataset.visualOriginal = el.className; });

    function atualizarContador() {
        const n = celulas().filter(alterada).length;
        form.querySelectorAll('[data-salvar]').forEach(btn => {
            btn.disabled = n === 0;
            btn.querySelector('[data-salvar-texto]').textContent =
                n === 0 ? 'Nenhuma alteração'
                : n === 1 ? 'Salvar 1 alteração'
                : 'Salvar ' + n + ' alterações';
        });
    }

    function marcar(el) {
        el.className = el.dataset.visualOriginal;
        if (alterada(el)) {
            el.classList.remove('border-warning', 'bg-warning', 'bg-opacity-10', 'border-danger', 'text-danger');
            el.classList.add('qtd-alterada');
        }
        atualizarContador();
    }

    // ---- foco = seleciona tudo; fracao original re-seleciona a cada clique ----
    form.addEventListener('focusin', e => {
        if (e.target.matches('input[data-qtd]')) e.target.select();
    });

    // ---- digitacao: so digito ----
    form.addEventListener('input', e => {
        const el = e.target;
        if (!el.matches('input[data-qtd]')) return;
        // Virgula/ponto CORTA o resto: "13,5" fica 13, nunca 135.
        const v = el.value.split(/[.,]/)[0].replace(/[^0-9]/g, '').replace(/^0+(?=\d)/, '');
        if (v !== el.value) el.value = v;
        marcar(el);
    });

    // ---- setas: botoes e teclado ----
    function passo(el, dir) {
        const n = parseFloat(el.value.trim().replace(',', '.'));
        let novo;
        if (isNaN(n))                  novo = dir > 0 ? 1 : 0;
        else if (Number.isInteger(n))  novo = n + dir;
        else                           novo = dir > 0 ? Math.ceil(n) : Math.floor(n);
        el.value = String(Math.max(0, novo));
        marcar(el);
    }
    form.addEventListener('click', e => {
        // Clique numa celula que ainda tem a fracao original: seleciona tudo de
        // novo, para nao existir "digitar no meio" de 0,002.
        const el = e.target;
        if (el.matches && el.matches('input[data-qtd]')) {
            if (el.value === el.defaultValue && /[.,]/.test(el.value)) el.select();
            return;
        }
        // Clique numa seta
        const btn = e.target.closest && e.target.closest('.qtd-seta');
        if (!btn) return;
        e.preventDefault();
        const alvo = btn.closest('.qtd-wrap').querySelector('input[data-qtd]');
        passo(alvo, parseInt(btn.dataset.passo, 10));
    });
    form.addEventListener('keydown', e => {
        const el = e.target;
        if (!el.matches('input[data-qtd]')) return;
        if (e.key === 'ArrowUp')   { e.preventDefault(); passo(el, 1);  }
        if (e.key === 'ArrowDown') { e.preventDefault(); passo(el, -1); }
    });

    // ---- envio: so o que mudou viaja ----
    form.addEventListener('submit', e => {
        let primeiroRuim = null;
        const intocadas = [];

        celulas().forEach(el => {
            el.classList.remove('is-invalid');
            if (!alterada(el)) { intocadas.push(el); return; }
            if (!/^\d+$/.test(el.value.trim())) {
                el.classList.add('is-invalid');
                primeiroRuim = primeiroRuim || el;
            }
        });

        if (primeiroRuim) {
            e.preventDefault();
            primeiroRuim.scrollIntoView({block: 'center', inline: 'center'});
            primeiroRuim.focus();
            if (window.ERP && ERP.toast) ERP.toast('Quantidade inválida: use só números inteiros.', 'danger');
            return;
        }
        if (intocadas.length === celulas().length) { e.preventDefault(); return; }

        // Campo `disabled` nao e enviado: a celula que ninguem tocou fica em casa.
        intocadas.forEach(el => { el.disabled = true; });
    });

    // Voltou pela seta "voltar" do navegador com o form restaurado? Reabilita.
    window.addEventListener('pageshow', () => celulas().forEach(el => { el.disabled = false; }));

    atualizarContador();
})();
</script>
@endpush
@endsection
