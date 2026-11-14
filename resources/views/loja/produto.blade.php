@extends('loja.layout')

@section('conteudo')
<div class="lj-caixa">
    <nav class="lj-trilha" aria-label="Você está em">
        <a href="{{ $ctx->url() }}">Início</a> <span>/</span>
        @if($model->categoria_id && $p['categoria'])<a href="{{ $ctx->url('produtos?categoria=' . $model->categoria_id) }}">{{ $p['categoria'] }}</a> <span>/</span>@endif
        <span>{{ $p['nome'] }}</span>
    </nav>

    <div class="lj-produto">
        <div class="lj-galeria @if(count($galeria) > 1) lj-galeria--varias @endif">
            <div class="lj-galeria__palco" data-lj-palco tabindex="0" aria-label="Fotos do produto">
                @forelse($galeria as $i => $foto)
                    <img src="{{ $foto }}" alt="{{ $p['nome'] }} — foto {{ $i + 1 }}" @if($i > 0) loading="lazy" @endif>
                @empty
                    <span class="lj-semfoto">{{ mb_substr($p['nome'], 0, 1) }}</span>
                @endforelse
            </div>
            @if(count($galeria) > 1)
                <div class="lj-miniaturas" role="tablist" aria-label="Escolher foto">
                    @foreach($galeria as $i => $foto)
                        <button type="button" data-lj-mini="{{ $i }}" aria-label="Foto {{ $i + 1 }}" aria-current="{{ $i === 0 ? 'true' : 'false' }}"><img src="{{ $foto }}" alt="" loading="lazy"></button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="lj-produto__info">
            <h1>{{ $p['nome'] }}</h1>
            <p class="lj-produto__ref">Cód. {{ $p['codigo'] }}</p>

            <div class="lj-produto__preco">
                <b>R$ {{ number_format($p['preco'], 2, ',', '.') }}</b>
                @if($p['promocao'])
                    <s>R$ {{ number_format($p['normal'], 2, ',', '.') }}</s>
                    <span class="lj-selo" style="position:static">-{{ max(1, (int) round((1 - $p['preco'] / $p['normal']) * 100)) }}%</span>
                @endif
            </div>
            <p class="lj-produto__nota">
                @if($meios['pix'] && $meios['cartao'])No PIX, com confirmação na hora, ou no cartão.
                @elseif($meios['pix'])No PIX, com confirmação na hora.
                @elseif($meios['cartao'])Pagamento no cartão.
                @else Pagamento combinado com a loja depois do pedido.@endif
            </p>

            @if($p['disponivel'])
                <p class="lj-estoque lj-estoque--ok"><i class="bi bi-check-circle-fill"></i>
                    @if(!$loja->opcao('vender_sem_estoque') && $p['saldo'] <= 3)
                        {{ (int) $p['saldo'] === 1 ? 'Última unidade' : 'Últimas ' . (int) $p['saldo'] . ' unidades' }}
                    @elseif($loja->opcao('mostrar_estoque') && $p['saldo'] >= 1){{ (int) $p['saldo'] }} em estoque
                    @else Disponível @endif
                </p>
                <form method="post" action="{{ $ctx->url('carrinho/adicionar') }}" data-lj-add class="lj-comprar">
                    @csrf
                    <input type="hidden" name="produto_id" value="{{ $p['id'] }}">
                    <div class="lj-qtd" data-lj-qtd>
                        <button type="button" data-d="-1" aria-label="Diminuir quantidade">−</button>
                        <label class="lj-so-leitor" for="lj-qtd">Quantidade</label>
                        <input id="lj-qtd" type="number" name="quantidade" value="1" min="1" max="{{ $loja->opcao('vender_sem_estoque') ? 999 : max(1, (int) $p['saldo']) }}" inputmode="numeric">
                        <button type="button" data-d="1" aria-label="Aumentar quantidade">+</button>
                    </div>
                    <button class="lj-btn" type="submit"><i class="bi bi-bag-plus"></i> Adicionar ao carrinho</button>
                </form>
            @else
                <p class="lj-estoque lj-estoque--fim"><i class="bi bi-x-circle-fill"></i> Esgotado no momento</p>
                @if(filled($loja->opcao('whatsapp')))
                    <div class="lj-comprar">
                        <a class="lj-btn lj-btn--linha" target="_blank" rel="noopener"
                           href="https://wa.me/55{{ preg_replace('/\D/', '', $loja->opcao('whatsapp')) }}?text={{ rawurlencode('Olá! Quando volta o produto "' . $p['nome'] . '" (cód. ' . $p['codigo'] . ')?') }}">
                            <i class="bi bi-whatsapp"></i> Avise-me quando chegar</a>
                    </div>
                @endif
            @endif

            @if($loja->opcao('entrega'))
            <div class="lj-bloco">
                <h2>Calcular frete</h2>
                <form class="lj-frete" data-lj-frete data-produto="{{ $p['id'] }}" action="{{ $ctx->url('frete') }}">
                    <label class="lj-so-leitor" for="lj-cep">CEP</label>
                    <input class="lj-campo" id="lj-cep" name="cep" inputmode="numeric" maxlength="9" placeholder="00000-000" autocomplete="postal-code">
                    <button class="lj-btn lj-btn--linha" type="submit">Calcular</button>
                </form>
                <div class="lj-frete__saida" data-lj-frete-saida aria-live="polite"></div>
                @if($loja->opcao('retirada'))<p class="lj-produto__nota"><i class="bi bi-bag-check"></i> Ou retire na loja, sem frete.</p>@endif
            </div>
            @endif

            @if(filled($model->descricao_detalhada))
            <div class="lj-bloco">
                <h2>Sobre o produto</h2>
                <p class="lj-descricao">{{ $model->descricao_detalhada }}</p>
            </div>
            @endif

            @php($ficha = array_filter([
                'Código de barras' => $model->codigo_barras,
                'Peso' => (float) $model->peso_bruto > 0 ? rtrim(rtrim(number_format((float) $model->peso_bruto, 3, ',', '.'), '0'), ',') . ' kg' : null,
                'Medidas (A × L × C)' => ((float) $model->altura_cm > 0 && (float) $model->largura_cm > 0 && (float) $model->comprimento_cm > 0)
                    ? (float) $model->altura_cm . ' × ' . (float) $model->largura_cm . ' × ' . (float) $model->comprimento_cm . ' cm' : null,
            ]))
            @if($ficha)
            <div class="lj-bloco">
                <h2>Ficha</h2>
                <dl class="lj-ficha">@foreach($ficha as $k => $v)<dt>{{ $k }}</dt><dd>{{ $v }}</dd>@endforeach</dl>
            </div>
            @endif
        </div>
    </div>
</div>

@if($relacionados->isNotEmpty())
<section class="lj-caixa lj-secao" style="padding-top:0">
    <div class="lj-secao__topo"><h2>Você também pode gostar</h2></div>
    <div class="lj-grade" style="--colunas:4">
        @foreach($relacionados as $r) @include('loja.partials.cartao', ['p' => $r]) @endforeach
    </div>
</section>
@endif
<div style="height:clamp(2rem,5vw,3.5rem)"></div>
@endsection

@push('scripts')
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p['nome'], 'sku' => $p['codigo'],
    'image' => array_map(fn ($f) => url($f), $galeria),
    'offers' => ['@type' => 'Offer', 'priceCurrency' => 'BRL', 'price' => number_format($p['preco'], 2, '.', ''),
        'availability' => 'https://schema.org/' . ($p['disponivel'] ? 'InStock' : 'OutOfStock')],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
<script>
(function () {
    var palco = document.querySelector('[data-lj-palco]');
    var minis = document.querySelectorAll('[data-lj-mini]');
    minis.forEach(function (b) {
        b.addEventListener('click', function () {
            palco.scrollTo({left: palco.clientWidth * Number(b.dataset.ljMini), behavior: 'smooth'});
        });
    });
    if (palco && minis.length) {
        palco.addEventListener('scroll', function () {
            var i = Math.round(palco.scrollLeft / palco.clientWidth);
            minis.forEach(function (b, n) { b.setAttribute('aria-current', n === i ? 'true' : 'false'); });
        }, {passive: true});
    }
    var q = document.querySelector('[data-lj-qtd]');
    if (q) {
        var campo = q.querySelector('input');
        q.addEventListener('click', function (ev) {
            var d = ev.target.closest('button[data-d]');
            if (!d) return;
            var v = Math.max(Number(campo.min) || 1, Math.min(Number(campo.max) || 999, (Number(campo.value) || 1) + Number(d.dataset.d)));
            campo.value = v;
        });
    }
    var f = document.querySelector('[data-lj-frete]');
    if (f) {
        var saida = document.querySelector('[data-lj-frete-saida]');
        f.addEventListener('submit', function (ev) {
            ev.preventDefault();
            saida.textContent = 'Calculando…';
            LJ.post(f.getAttribute('action'), {cep: f.cep.value, produto_id: Number(f.dataset.produto)}).then(function (j) {
                saida.textContent = '';
                if (j._status === 429) { saida.textContent = 'Muitas consultas seguidas. Tente de novo em um minuto.'; return; }
                if (!j.disponivel) { saida.textContent = j.mensagem || (j.errors && j.errors.cep && j.errors.cep[0]) || 'Não foi possível calcular.'; return; }
                j.opcoes.forEach(function (o) {
                    var d = document.createElement('div'), a = document.createElement('span'), b = document.createElement('strong'), s = document.createElement('small');
                    a.textContent = o.nome; s.textContent = o.prazo; a.appendChild(s); b.textContent = LJ.moeda(o.valor);
                    d.appendChild(a); d.appendChild(b); saida.appendChild(d);
                });
            }).catch(function () { saida.textContent = 'Não foi possível calcular agora.'; });
        });
    }
})();
</script>
@endpush
