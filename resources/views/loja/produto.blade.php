@extends('loja.layout')

@section('conteudo')
@php
    $desconto = $p['promocao'] && $p['normal'] > 0 ? max(1, (int) round((1 - $p['preco'] / $p['normal']) * 100)) : 0;
    $poucas = $p['disponivel'] && ! $loja->opcao('vender_sem_estoque') && $p['saldo'] <= 3;
    $zapProduto = filled($loja->opcao('whatsapp')) ? 'https://wa.me/55' . preg_replace('/\D/', '', $loja->opcao('whatsapp')) . '?text=' . rawurlencode('Olá! Tenho interesse no produto "' . $p['nome'] . '" (cód. ' . $p['codigo'] . ').') : null;
    $ficha = array_filter([
        'Código' => $p['codigo'],
        'Código de barras' => $model->codigo_barras,
        'Categoria' => $p['categoria'],
        'Peso' => (float) $model->peso_bruto > 0 ? rtrim(rtrim(number_format((float) $model->peso_bruto, 3, ',', '.'), '0'), ',') . ' kg' : null,
        'Medidas (A × L × C)' => ((float) $model->altura_cm > 0 && (float) $model->largura_cm > 0 && (float) $model->comprimento_cm > 0)
            ? (float) $model->altura_cm . ' × ' . (float) $model->largura_cm . ' × ' . (float) $model->comprimento_cm . ' cm' : null,
    ]);
@endphp
<div class="lj-caixa">
    <nav class="lj-trilha" aria-label="Você está em">
        <a href="{{ $ctx->url() }}">Início</a> <span>/</span>
        @if(($p['categoria_id'] ?? $model->categoria_id) && $p['categoria'])<a href="{{ $ctx->url('produtos?categoria=' . ($p['categoria_id'] ?? $model->categoria_id)) }}">{{ $p['categoria'] }}</a> <span>/</span>@endif
        <span>{{ $p['nome'] }}</span>
    </nav>

    <div class="lj-produto" data-lj-produto="{{ $p['id'] }}">
        <div class="lj-galeria @if(count($galeria) > 1) lj-galeria--varias @endif">
            <div class="lj-galeria__moldura">
                <div class="lj-selos">
                    @if($desconto)<span class="lj-selo">-{{ $desconto }}%</span>@endif
                    @if(! $p['disponivel'])<span class="lj-selo lj-selo--fim">Esgotado</span>@elseif($p['novo'] && ! $desconto)<span class="lj-selo lj-selo--novo">Novo</span>@endif
                </div>
                <div class="lj-galeria__palco" data-lj-palco tabindex="0" aria-label="Fotos do produto">
                    @forelse($galeria as $i => $foto)
                        <button type="button" class="lj-zoom" data-lj-zoom="{{ $i }}" aria-label="Ampliar a foto {{ $i + 1 }}">
                            <img src="{{ $foto }}" alt="{{ $p['nome'] }} — foto {{ $i + 1 }}" @if($i > 0) loading="lazy" @else data-lj-foto-fixa style="view-transition-name:lj-foto" @endif>
                        </button>
                    @empty
                        <span class="lj-semfoto">{{ mb_substr($p['nome'], 0, 1) }}</span>
                    @endforelse
                </div>
                <div class="lj-galeria__acoes">
                    <button type="button" class="lj-galeria__acao" data-lj-fav="{{ $p['id'] }}" aria-pressed="false" aria-label="Guardar nos favoritos"><i class="bi bi-heart" aria-hidden="true"></i></button>
                    <button type="button" class="lj-galeria__acao" data-lj-partilhar data-titulo="{{ $p['nome'] }}" data-url="{{ $ctx->urlAbsoluta($p['caminho']) }}" aria-label="Enviar para alguém"><i class="bi bi-share" aria-hidden="true"></i></button>
                </div>
                @if(count($galeria) > 1)
                    <div class="lj-galeria__pontos" aria-hidden="true">@foreach($galeria as $i => $foto)<i @if($i === 0) data-agora @endif></i>@endforeach</div>
                    <button type="button" class="lj-galeria__seta" data-lj-galeria="-1" aria-label="Foto anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                    <button type="button" class="lj-galeria__seta" data-lj-galeria="1" aria-label="Próxima foto"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                    <span class="lj-galeria__conta" data-lj-galeria-conta aria-hidden="true">1 / {{ count($galeria) }}</span>
                @endif
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
            @if($p['categoria'])<a class="lj-produto__cat" href="{{ $ctx->url('produtos?categoria=' . ($p['categoria_id'] ?? $model->categoria_id)) }}">{{ $p['categoria'] }}</a>@endif
            <h1>{{ $p['nome'] }}</h1>
            <p class="lj-produto__ref">Cód. {{ $p['codigo'] }}
                @if(($p['avaliacoes'] ?? 0) > 0) · <a class="lj-nota" href="#avaliacoes"><i class="bi bi-star-fill" aria-hidden="true"></i> {{ number_format((float) $p['nota'], 1, ',', '') }} <small>({{ $p['avaliacoes'] }} {{ $p['avaliacoes'] === 1 ? 'avaliação' : 'avaliações' }})</small></a>@endif
            </p>

            <div class="lj-compra">
                <div class="lj-produto__preco">
                    @if($p['promocao'])<s>R$ {{ number_format($p['normal'], 2, ',', '.') }}</s>@endif
                    <b>R$ {{ number_format($p['preco'], 2, ',', '.') }}</b>
                    <span class="lj-preco-nota">{{ $loja->opcao('preco_atacado') ? 'preço de atacado' : 'à vista' . ($meios['pix'] ? ' no PIX' : '') }}</span>
                    @if($desconto)<span class="lj-economia">Você economiza R$ {{ number_format($p['normal'] - $p['preco'], 2, ',', '.') }}</span>@endif
                </div>
                <ul class="lj-formas">
                    @if($meios['pix'])<li><i class="bi bi-qr-code" aria-hidden="true"></i> <span><strong>PIX</strong> com confirmação na hora</span></li>@endif
                    @if($meios['cartao'])<li><i class="bi bi-credit-card" aria-hidden="true"></i> <span><strong>Cartão de crédito</strong>@if($p['parcelas']) em até {{ $p['parcelas']['vezes'] }}x de R$ {{ number_format($p['parcelas']['valor'], 2, ',', '.') }}{{ $p['parcelas']['sem_juros'] ? ' sem juros' : '' }}@endif</span></li>@endif
                    @if(! $meios['pix'] && ! $meios['cartao'])<li><i class="bi bi-chat-dots" aria-hidden="true"></i> <span>Pagamento combinado com a loja depois do pedido</span></li>@endif
                </ul>

                @foreach(($grade ?? []) as $g)
                    <div class="lj-variacao" data-lj-variacao>
                        <span class="lj-variacao__nome">{{ $g['nome'] }}: <strong>{{ collect($g['opcoes'])->firstWhere('atual', true)['valor'] ?? '' }}</strong></span>
                        <div class="lj-variacao__opcoes" role="group" aria-label="{{ $g['nome'] }}">
                            @foreach($g['opcoes'] as $o)
                                <a href="{{ $ctx->url($o['url']) }}" class="lj-variacao__opcao @if(! $o['disponivel']) lj-variacao__opcao--fim @endif" @if($o['atual']) aria-current="true" @endif
                                   @if(! $o['disponivel']) title="Esgotado" @endif>{{ $o['valor'] }}@if(! $o['disponivel'])<span class="lj-so-leitor"> (esgotado)</span>@endif</a>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if($p['disponivel'])
                    <p class="lj-estoque @if($poucas) lj-estoque--pouco @else lj-estoque--ok @endif">
                        <i class="bi bi-{{ $poucas ? 'fire' : 'check-circle-fill' }}" aria-hidden="true"></i>
                        @if($poucas){{ (int) $p['saldo'] === 1 ? 'Última unidade' : 'Últimas ' . (int) $p['saldo'] . ' unidades' }}
                        @elseif($loja->opcao('mostrar_estoque') && $p['saldo'] >= 1){{ (int) $p['saldo'] }} em estoque
                        @else Disponível @endif
                    </p>
                    <form method="post" action="{{ $ctx->caminho('carrinho/adicionar') }}" data-lj-add class="lj-comprar">
                        @csrf
                        <input type="hidden" name="produto_id" value="{{ $p['id'] }}">
                        <div class="lj-qtd" data-lj-qtd>
                            <button type="button" data-d="-1" aria-label="Diminuir quantidade">−</button>
                            <label class="lj-so-leitor" for="lj-qtd">Quantidade</label>
                            <input id="lj-qtd" type="number" name="quantidade" value="1" min="1" max="{{ $loja->opcao('vender_sem_estoque') ? 999 : max(1, (int) $p['saldo']) }}" inputmode="numeric">
                            <button type="button" data-d="1" aria-label="Aumentar quantidade">+</button>
                        </div>
                        <button class="lj-btn lj-btn--grande" type="submit" data-lj-comprar-principal><i class="bi bi-bag-plus" aria-hidden="true"></i> Adicionar ao carrinho</button>
                    </form>
                    <button class="lj-btn lj-btn--linha lj-btn--cheio" type="button" data-lj-comprar-agora data-ir="{{ $ctx->url('finalizar') }}">Comprar agora</button>
                @else
                    <p class="lj-estoque lj-estoque--fim"><i class="bi bi-x-circle-fill" aria-hidden="true"></i> Esgotado no momento</p>
                    @if($loja->opcao('avise_me'))
                        <form class="lj-avise" data-lj-avise action="{{ $ctx->caminho('avise-me') }}" novalidate>
                            <input type="hidden" name="produto_id" value="{{ $p['id'] }}">
                            <label for="lj-avise-email"><strong>Avise-me quando chegar</strong></label>
                            <div class="lj-avise__linha">
                                <input class="lj-campo" id="lj-avise-email" type="email" name="email" required autocomplete="email" placeholder="seu@email.com" value="{{ $contaLoja->email ?? '' }}">
                                <button class="lj-btn" type="submit">Avisar</button>
                            </div>
                            <label class="lj-aceite"><input type="checkbox" name="aceite" value="1" required> <span>Aceito receber um e-mail quando este produto voltar. <a href="{{ $ctx->url('politica/privacidade') }}">Privacidade</a></span></label>
                            <p class="lj-avise__saida" data-lj-avise-saida aria-live="polite"></p>
                        </form>
                    @endif
                    @if($zapProduto && ! $loja->opcao('avise_me'))
                        <div class="lj-comprar">
                            <a class="lj-btn lj-btn--linha" target="_blank" rel="noopener"
                               href="https://wa.me/55{{ preg_replace('/\D/', '', $loja->opcao('whatsapp')) }}?text={{ rawurlencode('Olá! Quando volta o produto "' . $p['nome'] . '" (cód. ' . $p['codigo'] . ')?') }}">
                                <i class="bi bi-whatsapp" aria-hidden="true"></i> Avise-me quando chegar</a>
                        </div>
                    @endif
                @endif
            </div>

            @if($loja->opcao('entrega'))
            <div class="lj-bloco">
                <h2><i class="bi bi-truck" aria-hidden="true"></i> Calcular frete</h2>
                <form class="lj-frete" data-lj-frete data-produto="{{ $p['id'] }}" action="{{ $ctx->caminho('frete') }}">
                    <label class="lj-so-leitor" for="lj-cep">CEP</label>
                    <input class="lj-campo" id="lj-cep" name="cep" inputmode="numeric" maxlength="9" placeholder="00000-000" autocomplete="postal-code">
                    <button class="lj-btn lj-btn--linha" type="submit">Calcular</button>
                </form>
                <div class="lj-frete__saida" data-lj-frete-saida aria-live="polite"></div>
            </div>
            @endif

            <ul class="lj-garantias">
                @if($loja->opcao('retirada'))<li><i class="bi bi-bag-check" aria-hidden="true"></i> <span><strong>Retire na loja</strong> <small>sem pagar frete</small></span></li>@endif
                <li><i class="bi bi-arrow-repeat" aria-hidden="true"></i> <span><strong>Troca e devolução</strong> <small><a href="{{ $ctx->url('politica/trocas') }}">veja como funciona</a></small></span></li>
                <li><i class="bi bi-shield-check" aria-hidden="true"></i> <span><strong>Direto com a loja</strong> <small>sem intermediário</small></span></li>
            </ul>

            <div class="lj-partilha">
                <button class="lj-btn lj-btn--p lj-btn--linha lj-fav-texto" type="button" data-lj-fav="{{ $p['id'] }}" aria-pressed="false"><i class="bi bi-heart" aria-hidden="true"></i> <span>Favoritar</span></button>
                @if($zapProduto)<a class="lj-btn lj-btn--p lj-btn--linha" href="{{ $zapProduto }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i> Tirar dúvida</a>@endif
                <a class="lj-btn lj-btn--p lj-btn--linha" target="_blank" rel="noopener" href="https://wa.me/?text={{ rawurlencode($p['nome'] . ' — ' . $ctx->urlAbsoluta($p['caminho'])) }}"><i class="bi bi-share" aria-hidden="true"></i> Enviar para alguém</a>
            </div>
        </div>
    </div>

    <div class="lj-abas" data-lj-abas>
        <div class="lj-abas__lista" role="tablist" aria-label="Informações do produto">
            <button type="button" role="tab" id="aba-descricao" aria-controls="painel-descricao" aria-selected="true">Descrição</button>
            <button type="button" role="tab" id="aba-ficha" aria-controls="painel-ficha" aria-selected="false" tabindex="-1">Ficha técnica</button>
            <button type="button" role="tab" id="aba-entrega" aria-controls="painel-entrega" aria-selected="false" tabindex="-1">Entrega e trocas</button>
        </div>
        <div class="lj-abas__painel" role="tabpanel" id="painel-descricao" aria-labelledby="aba-descricao">
            @if(filled($model->descricao_detalhada))<p class="lj-descricao">{{ $model->descricao_detalhada }}</p>
            @else<p class="lj-descricao" style="color:var(--suave)">A loja ainda não escreveu a descrição deste produto.@if($zapProduto) <a href="{{ $zapProduto }}" target="_blank" rel="noopener">Pergunte pelo WhatsApp.</a>@endif</p>@endif
        </div>
        <div class="lj-abas__painel" role="tabpanel" id="painel-ficha" aria-labelledby="aba-ficha" hidden>
            <dl class="lj-ficha">@foreach($ficha as $k => $v)<dt>{{ $k }}</dt><dd>{{ $v }}</dd>@endforeach</dl>
        </div>
        <div class="lj-abas__painel" role="tabpanel" id="painel-entrega" aria-labelledby="aba-entrega" hidden>
            <div class="lj-politica__texto">
                @foreach(['entrega', 'trocas'] as $tipoPolitica)
                    @foreach(array_slice(preg_split('/\n\s*\n/', trim(\App\Services\LojaOnline\PoliticasLoja::texto($loja, $tipoPolitica))), 0, 4) as $paragrafo)
                        @php($par = trim($paragrafo))
                        @if(mb_strlen($par) <= 70 && str_ends_with($par, ':'))<h2>{{ rtrim($par, ':') }}</h2>@else<p>{{ $par }}</p>@endif
                    @endforeach
                @endforeach
                <p><a class="lj-link" href="{{ $ctx->url('politica/trocas') }}">Ver a política completa</a></p>
            </div>
        </div>
    </div>
</div>

@if($loja->opcao('avaliacoes') && ($avaliacoes ?? collect())->isNotEmpty())
<section class="lj-caixa lj-secao lj-avaliacoes" id="avaliacoes">
    <div class="lj-secao__topo"><h2>Avaliações de quem comprou</h2></div>
    <div class="lj-avaliacoes__resumo">
        <b>{{ number_format((float) $p['nota'], 1, ',', '') }}</b>
        <span class="lj-estrelas" aria-hidden="true">@for($n = 1; $n <= 5; $n++)<i class="bi bi-star{{ $n <= round((float) $p['nota']) ? '-fill' : '' }}"></i>@endfor</span>
        <small>{{ $p['avaliacoes'] }} {{ $p['avaliacoes'] === 1 ? 'avaliação' : 'avaliações' }}</small>
        <div class="lj-avaliacoes__barras" aria-hidden="true">
            @for($n = 5; $n >= 1; $n--)
                <span>{{ $n }}</span><i><b style="width:{{ round($avaliacoes->where('nota', $n)->count() / max(1, $avaliacoes->count()) * 100) }}%"></b></i><span>{{ $avaliacoes->where('nota', $n)->count() }}</span>
            @endfor
        </div>
    </div>
    <ul class="lj-avaliacoes__lista">
        @foreach($avaliacoes as $a)
            <li>
                <span class="lj-estrelas" aria-label="{{ $a->nota }} de 5 estrelas">@for($n = 1; $n <= 5; $n++)<i class="bi bi-star{{ $n <= $a->nota ? '-fill' : '' }}" aria-hidden="true"></i>@endfor</span>
                <strong>{{ $a->nome }}</strong> <small>· {{ \Illuminate\Support\Carbon::parse($a->created_at)->format('d/m/Y') }} · compra verificada</small>
                @if($a->texto)<p>{{ $a->texto }}</p>@endif
                @if($a->resposta)<p class="lj-avaliacoes__resposta"><strong>{{ $loja->nome }}:</strong> {{ $a->resposta }}</p>@endif
            </li>
        @endforeach
    </ul>
</section>
@endif

@if($relacionados->isNotEmpty())
<section class="lj-caixa lj-secao">
    <div class="lj-secao__topo"><h2>Você também pode gostar</h2>
        <div class="lj-secao__acoes"><span class="lj-setas" data-lj-setas><button type="button" data-d="-1" aria-label="Produtos anteriores"><i class="bi bi-arrow-left" aria-hidden="true"></i></button><button type="button" data-d="1" aria-label="Próximos produtos"><i class="bi bi-arrow-right" aria-hidden="true"></i></button></span></div>
    </div>
    <div class="lj-grade lj-grade--carrossel lj-sem-cat" style="--colunas:4" data-lj-trilho tabindex="0">
        @foreach($relacionados as $r) @include('loja.partials.cartao', ['p' => $r]) @endforeach
    </div>
</section>
@endif

<section class="lj-caixa lj-secao" data-lj-vistos data-url="{{ $ctx->caminho('vistos') }}" hidden>
    <div class="lj-secao__topo"><h2>Vistos recentemente</h2></div>
    <div class="lj-grade lj-grade--carrossel lj-sem-cat" style="--colunas:4" data-lj-trilho tabindex="0" data-lj-vistos-lista></div>
</section>
<div style="height:clamp(2rem,5vw,3.5rem)"></div>

@if($p['disponivel'])
{{-- barra de compra fixa no celular: aparece quando o botão principal sai da tela --}}
<div class="lj-barra-compra" data-lj-barra hidden>
    <div><small>{{ \Illuminate\Support\Str::limit($p['nome'], 32) }}</small><b>R$ {{ number_format($p['preco'], 2, ',', '.') }}</b></div>
    <button class="lj-btn" type="button" data-lj-barra-botao><i class="bi bi-bag-plus" aria-hidden="true"></i> Adicionar</button>
</div>
@endif

{{-- foto ampliada --}}
@if(count($galeria))
<dialog class="lj-luz" data-lj-luz aria-label="Foto ampliada de {{ $p['nome'] }}">
    <button type="button" class="lj-icone lj-luz__fechar" data-lj-x aria-label="Fechar"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    @if(count($galeria) > 1)
        <button type="button" class="lj-galeria__seta" data-lj-luz-ir="-1" aria-label="Foto anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
        <button type="button" class="lj-galeria__seta" data-lj-luz-ir="1" aria-label="Próxima foto"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
    @endif
    <img data-lj-luz-img src="" alt="{{ $p['nome'] }}">
</dialog>
@endif
@endsection

@push('scripts')
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p['nome'], 'sku' => $p['codigo'],
    'image' => array_map(fn ($f) => url($f), $galeria),
    'offers' => ['@type' => 'Offer', 'priceCurrency' => 'BRL', 'price' => number_format($p['preco'], 2, '.', ''),
        'availability' => 'https://schema.org/' . ($p['disponivel'] ? 'InStock' : 'OutOfStock')],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
<script>
LJ.rastro.evento('ver_produto', {valor: {{ $p['preco'] }}, itens: [{id: {{ $p['id'] }}, nome: {!! json_encode($p['nome'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}, preco: {{ $p['preco'] }}}]});
(function () {
    var FOTOS = {!! json_encode($galeria, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!};
    var palco = document.querySelector('[data-lj-palco]');
    var minis = document.querySelectorAll('[data-lj-mini]');
    var conta = document.querySelector('[data-lj-galeria-conta]');
    var atual = 0;
    function ir(i, suave) {
        atual = (i + FOTOS.length) % FOTOS.length;
        palco.scrollTo({left: palco.clientWidth * atual, behavior: suave === false ? 'auto' : 'smooth'});
    }
    minis.forEach(function (b) { b.addEventListener('click', function () { ir(Number(b.dataset.ljMini)); }); });
    document.querySelectorAll('[data-lj-galeria]').forEach(function (b) { b.addEventListener('click', function () { ir(atual + Number(b.dataset.ljGaleria)); }); });
    if (palco && FOTOS.length > 1) {
        palco.addEventListener('scroll', function () {
            var i = Math.round(palco.scrollLeft / palco.clientWidth);
            if (i === atual && minis[i] && minis[i].getAttribute('aria-current') === 'true') return;
            atual = i;
            minis.forEach(function (b, n) { b.setAttribute('aria-current', n === i ? 'true' : 'false'); });
            if (conta) conta.textContent = (i + 1) + ' / ' + FOTOS.length;
        }, {passive: true});
        palco.addEventListener('keydown', function (ev) { if (ev.key === 'ArrowRight') ir(atual + 1); if (ev.key === 'ArrowLeft') ir(atual - 1); });
    }
    // zoom ao passar o mouse (computador) e foto ampliada ao clicar
    var luz = document.querySelector('[data-lj-luz]');
    document.querySelectorAll('[data-lj-zoom]').forEach(function (z) {
        var img = z.querySelector('img');
        if (matchMedia('(hover:hover) and (pointer:fine)').matches) {
            z.addEventListener('mousemove', function (ev) {
                var r = z.getBoundingClientRect();
                img.style.transformOrigin = ((ev.clientX - r.left) / r.width * 100) + '% ' + ((ev.clientY - r.top) / r.height * 100) + '%';
                z.classList.add('lj-zoom--ativo');
            });
            z.addEventListener('mouseleave', function () { z.classList.remove('lj-zoom--ativo'); });
        }
        z.addEventListener('click', function () {
            if (!luz || typeof luz.showModal !== 'function') return;
            var n = Number(z.dataset.ljZoom), foto = luz.querySelector('[data-lj-luz-img]');
            var mostrar = function (i) { n = (i + FOTOS.length) % FOTOS.length; foto.src = FOTOS[n]; };
            mostrar(n); luz.showModal();
            luz.onclick = function (ev) {
                var seta = ev.target.closest('[data-lj-luz-ir]');
                if (seta) mostrar(n + Number(seta.dataset.ljLuzIr));
                else if (ev.target === luz || ev.target.closest('[data-lj-x]')) luz.close();
            };
            luz.onkeydown = function (ev) { if (ev.key === 'ArrowRight') mostrar(n + 1); if (ev.key === 'ArrowLeft') mostrar(n - 1); };
        });
    });
    var q = document.querySelector('[data-lj-qtd]');
    if (q) {
        var campo = q.querySelector('input');
        q.addEventListener('click', function (ev) {
            var d = ev.target.closest('button[data-d]');
            if (!d) return;
            campo.value = Math.max(Number(campo.min) || 1, Math.min(Number(campo.max) || 999, (Number(campo.value) || 1) + Number(d.dataset.d)));
        });
    }
    // comprar agora = adicionar e ir direto para o fechamento
    var form = document.querySelector('.lj-comprar[data-lj-add]'), agora = document.querySelector('[data-lj-comprar-agora]');
    if (form && agora) agora.addEventListener('click', function () { form.dataset.ir = agora.dataset.ir; form.requestSubmit(); });
    // barra de compra fixa (celular)
    var barra = document.querySelector('[data-lj-barra]'), principal = document.querySelector('[data-lj-comprar-principal]');
    if (barra && principal && 'IntersectionObserver' in window) {
        new IntersectionObserver(function (es) { barra.hidden = es[0].isIntersecting || es[0].boundingClientRect.top > 0; }, {threshold: 0}).observe(principal);
        barra.querySelector('[data-lj-barra-botao]').addEventListener('click', function () { form.requestSubmit(); });
    }
    // abas
    var abas = document.querySelector('[data-lj-abas]');
    if (abas) {
        var botoes = [].slice.call(abas.querySelectorAll('[role=tab]'));
        var escolher = function (b, foco) {
            botoes.forEach(function (o) { var sel = o === b; o.setAttribute('aria-selected', sel); o.tabIndex = sel ? 0 : -1; document.getElementById(o.getAttribute('aria-controls')).hidden = !sel; });
            if (foco) b.focus();
        };
        botoes.forEach(function (b, i) {
            b.addEventListener('click', function () { escolher(b); });
            b.addEventListener('keydown', function (ev) { if (ev.key === 'ArrowRight') escolher(botoes[(i + 1) % botoes.length], true); if (ev.key === 'ArrowLeft') escolher(botoes[(i - 1 + botoes.length) % botoes.length], true); });
        });
    }
    // frete
    var f = document.querySelector('[data-lj-frete]');
    if (f) {
        var saida = document.querySelector('[data-lj-frete-saida]');
        f.cep.addEventListener('input', function () { var d = f.cep.value.replace(/\D/g, '').slice(0, 8); f.cep.value = d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d; });
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
    // vistos recentemente (guardado só no navegador do visitante)
    try {
        var chave = 'lj_vistos_{{ $loja->id }}', id = {{ $p['id'] }};
        var lista = (JSON.parse(localStorage.getItem(chave)) || []).filter(function (x) { return x !== id; });
        var caixa = document.querySelector('[data-lj-vistos]');
        if (lista.length && caixa) {
            fetch(caixa.dataset.url + '?ids=' + lista.slice(0, 8).join(','), {credentials: 'same-origin'}).then(function (r) { return r.ok ? r.text() : ''; }).then(function (html) {
                if (html.trim() === '') return;
                caixa.querySelector('[data-lj-vistos-lista]').innerHTML = html; caixa.hidden = false;
                caixa.querySelector('[data-lj-vistos-lista]').classList.add('lj-vista');
            });
        }
        lista.unshift(id); localStorage.setItem(chave, JSON.stringify(lista.slice(0, 12)));
    } catch (e) {}
})();
</script>
@endpush
