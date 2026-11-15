@extends('loja.layout')

@section('pagina', 'compra')

@section('conteudo')
@php($s = $situacao['situacao'])
@php($retirada = $pedido->metodo_entrega === 'retirada')
@php($nivel = match($s) { 'entregue' => 4, 'faturado', 'enviado' => 3, 'pago', 'confirmado' => 2, default => 1 })
@php($temBoleto = $temBoleto ?? false)
@php($zap = filled($loja->opcao('whatsapp')) ? 'https://wa.me/55' . preg_replace('/\D/', '', $loja->opcao('whatsapp')) . '?text=' . rawurlencode('Olá! Fiz o pedido #' . $pedido->numero . ' na loja online.') : null)
<div class="lj-caixa" data-lj-pedido data-situacao="{{ $s }}">
    <div class="lj-pagina__topo">
        <div>
            <h1>Pedido #{{ $pedido->numero }}</h1>
            <p style="color:var(--suave);margin-top:.3rem">Feito em {{ $pedido->created_at->format('d/m/Y \à\s H:i') }} · <strong data-lj-rotulo style="color:var(--texto)">{{ $situacao['rotulo'] }}</strong></p>
        </div>
    </div>

    @if(session('loja_ok'))<div class="lj-aviso lj-aviso--ok"><i class="bi bi-check-circle"></i><span>{{ session('loja_ok') }}</span></div>@endif

    @if($s === 'cancelado')
        <div class="lj-aviso lj-aviso--erro"><i class="bi bi-x-circle"></i><span>Este pedido foi cancelado.@if($situacao['pago']) Como havia um pagamento, a loja vai falar com você sobre a devolução.@endif</span></div>
    @else
        <ol class="lj-etapas" aria-label="Andamento do pedido">
            <li data-feito>Pedido recebido</li>
            <li @if($nivel >= 2) data-feito @else data-agora @endif>{{ $s === 'confirmado' ? 'Confirmado' : 'Pagamento' }}</li>
            <li @if($nivel >= 3) data-feito @elseif($nivel === 2) data-agora @endif>Separação</li>
            <li @if($nivel >= 4) data-feito @elseif($nivel === 3) data-agora @endif>{{ $retirada ? 'Retirada' : ($s === 'enviado' ? 'A caminho' : 'Entrega') }}</li>
        </ol>
    @endif

    <div class="lj-duas" style="padding-top:0">
        <div>
            @if($freteCombinar && $s === 'aguardando')
                <div class="lj-pagar">
                    <h2>A loja vai combinar a entrega com você</h2>
                    <p style="color:var(--suave);margin-top:.5rem">O frete deste endereço não pôde ser calculado na hora. Assim que a loja confirmar o valor, você paga.</p>
                    @if($zap)<a class="lj-btn" style="margin-top:1rem" href="{{ $zap }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Falar com a loja</a>@endif
                </div>
            @elseif($podePagar && ($meios['pix'] || $meios['cartao'] || $temBoleto))
                <div class="lj-pagar" data-lj-pagar data-url="{{ $ctx->url('pedido/' . $pedido->loja_token . '/pagar') }}">
                    <h2>Pague para confirmar o pedido</h2>
                    <p class="lj-pagar__valor">R$ {{ number_format((float) $pedido->total, 2, ',', '.') }}</p>
                    <p style="color:var(--suave);margin-top:.5rem">A confirmação aparece aqui sozinha.</p>

                    <div data-lj-pix @if(!($pix['ok'] ?? false) || ($pix['tipo'] ?? '') !== 'qr') hidden @endif>
                        <div class="lj-pix">
                            <div class="lj-pix__qr" data-lj-qr>
                                @if(($pix['ok'] ?? false) && filled($pix['qr_base64'] ?? null))<img src="data:image/png;base64,{{ $pix['qr_base64'] }}" alt="QR Code do PIX">@endif
                            </div>
                            <div>
                                <ol>
                                    <li>Abra o aplicativo do seu banco e escolha pagar com PIX.</li>
                                    <li>Aponte a câmera para o QR Code, ou copie o código abaixo.</li>
                                    <li>Confirme o valor. Esta página avisa quando o pagamento cair.</li>
                                </ol>
                                <div class="lj-pix__codigo">
                                    <label class="lj-so-leitor" for="lj-copia">PIX copia e cola</label>
                                    <input class="lj-campo" id="lj-copia" readonly value="{{ $pix['copia_cola'] ?? '' }}" data-lj-copia>
                                    <button class="lj-btn lj-btn--p" type="button" data-lj-copiar>Copiar código PIX</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div data-lj-cartao @if(!$linkCartao && !(($pix['ok'] ?? false) && ($pix['tipo'] ?? '') === 'link')) hidden @endif style="margin-top:1.2rem">
                        <a class="lj-btn" data-lj-link target="_blank" rel="noopener" href="{{ $linkCartao ?? ($pix['link'] ?? '#') }}"><i class="bi bi-box-arrow-up-right"></i> Abrir a página de pagamento</a>
                        <p style="color:var(--suave);font-size:.9rem;margin-top:.6rem">Ela abre em outra aba. Depois de pagar, volte aqui.</p>
                    </div>

                    <div data-lj-boleto @if(!($boleto['ok'] ?? false)) hidden @endif style="margin-top:1.2rem">
                        <a class="lj-btn" data-lj-boleto-link target="_blank" rel="noopener" href="{{ $boleto['link'] ?? '#' }}"><i class="bi bi-upc-scan" aria-hidden="true"></i> Abrir o boleto</a>
                        <div class="lj-pix__codigo" style="margin-top:.9rem">
                            <label class="lj-so-leitor" for="lj-linha">Linha digitável do boleto</label>
                            <input class="lj-campo" id="lj-linha" readonly value="{{ $boleto['linha'] ?? '' }}" data-lj-copia>
                            <button class="lj-btn lj-btn--p lj-btn--linha" type="button" data-lj-copiar>Copiar a linha</button>
                        </div>
                        <p style="color:var(--suave);font-size:.9rem;margin-top:.6rem">Vence em <span data-lj-boleto-vence>{{ $boleto['vence_em'] ?? '' }}</span>. O banco leva até 3 dias úteis para avisar o pagamento — o pedido é separado depois disso.</p>
                    </div>
                    @if($boleto !== null && !($boleto['ok'] ?? false))<div class="lj-aviso lj-aviso--erro" style="margin-top:1rem"><i class="bi bi-exclamation-circle"></i><span>{{ $boleto['erro'] ?? 'Não foi possível gerar o boleto agora.' }}</span></div>@endif

                    @if(($pix !== null && !($pix['ok'] ?? false)))<div class="lj-aviso lj-aviso--erro" style="margin-top:1rem"><i class="bi bi-exclamation-circle"></i><span>{{ $pix['erro'] ?? 'Não foi possível gerar o PIX agora.' }} Tente de novo em instantes.</span></div>@endif
                    <div class="lj-aviso lj-aviso--erro" data-lj-pagar-erro hidden style="margin-top:1rem"><i class="bi bi-exclamation-circle"></i><span></span></div>

                    <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1.3rem">
                        @if($meios['pix'])<button class="lj-btn {{ $escolha === 'pix' ? 'lj-btn--linha' : '' }}" type="button" data-lj-meio="pix"><i class="bi bi-qr-code"></i> {{ $escolha === 'pix' ? 'Gerar o PIX de novo' : 'Pagar com PIX' }}</button>@endif
                        @if($meios['cartao'])<button class="lj-btn lj-btn--linha" type="button" data-lj-meio="cartao"><i class="bi bi-credit-card"></i> Pagar com cartão</button>@endif
                        @if($temBoleto)<button class="lj-btn lj-btn--linha" type="button" data-lj-meio="boleto"><i class="bi bi-upc-scan"></i> Pagar com boleto</button>@endif
                    </div>
                    <p class="lj-espera">Aguardando o pagamento…</p>
                </div>
            @elseif($s === 'aguardando')
                <div class="lj-pagar">
                    <h2>Pedido recebido</h2>
                    <p style="color:var(--suave);margin-top:.5rem">A loja vai falar com você para combinar o pagamento{{ $retirada ? ' e a retirada' : ' e a entrega' }}.</p>
                    @if($zap)<a class="lj-btn" style="margin-top:1rem" href="{{ $zap }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Falar com a loja</a>@endif
                </div>
            @elseif($s !== 'cancelado')
                <div class="lj-pagar lj-pagar--ok">
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                    <h2>@if($s === 'entregue')Pedido entregue @elseif($s === 'enviado')Pedido enviado @elseif($s === 'faturado'){{ $retirada ? 'Pronto para retirada' : 'Pedido a caminho' }} @elseif($s === 'pago')Pagamento confirmado @else Pedido confirmado pela loja @endif</h2>
                    <p style="color:var(--suave);margin-top:.4rem">
                        @if($s === 'entregue')Obrigado pela compra!
                        @elseif($s === 'enviado')Saiu em {{ $pedido->loja_enviado_em->format('d/m/Y') }}@if($pedido->loja_transportadora) por {{ $pedido->loja_transportadora }}@endif.
                        @elseif($s === 'faturado'){{ $retirada ? 'Seu pedido está separado e pronto para retirada' . ($unidade ? ' em ' . $unidade->nome . '.' : '.') : 'Seu pedido foi separado e está a caminho.' }}
                        @elseif($retirada)A loja já está separando o seu pedido. Você retira{{ $unidade ? ' em ' . $unidade->nome : '' }} assim que ela avisar.
                        @else A loja já está separando o seu pedido para a entrega.@endif
                    </p>
                </div>
            @endif

            @if($pedido->loja_enviado_em && $s !== 'cancelado' && (filled($pedido->loja_rastreio_codigo) || filled($pedido->loja_rastreio_url)))
                <div class="lj-rastreio" data-lj-rastreio>
                    <i class="bi bi-truck" aria-hidden="true"></i>
                    <div>
                        <strong>Rastreio{{ $pedido->loja_transportadora ? ' · ' . $pedido->loja_transportadora : '' }}</strong>
                        @if(filled($pedido->loja_rastreio_codigo))<span class="lj-rastreio__codigo">{{ $pedido->loja_rastreio_codigo }}</span>@endif
                    </div>
                    @if(filled($pedido->loja_rastreio_url))<a class="lj-btn lj-btn--p" href="{{ $pedido->loja_rastreio_url }}" target="_blank" rel="noopener noreferrer">Rastrear</a>@endif
                </div>
            @elseif(($entregaExterna ?? null) && ! in_array($s, ['cancelado', 'entregue'], true))
                <div class="lj-rastreio" data-lj-rastreio><i class="bi bi-bicycle" aria-hidden="true"></i><div><strong>Entrega em andamento</strong><span class="lj-rastreio__codigo">Acompanhe o entregador no mapa</span></div>
                    <a class="lj-btn lj-btn--p" href="{{ $entregaExterna }}" target="_blank" rel="noopener noreferrer">Acompanhar</a></div>
            @endif
            @if($s === 'cancelado' && $pedido->loja_estornado_em)<div class="lj-aviso lj-aviso--ok" style="margin-top:1rem"><i class="bi bi-arrow-counterclockwise"></i><span>O valor deste pedido foi devolvido em {{ $pedido->loja_estornado_em->format('d/m/Y') }}.</span></div>@endif

            <h2 style="font-size:1.1rem;letter-spacing:0;margin-top:2rem">Itens</h2>
            @foreach($itens as $i)
                <div class="lj-item lj-item--mini">
                    <div class="lj-item__foto">@if($fotos[$i->produto_id] ?? null)<img src="/storage/{{ $fotos[$i->produto_id] }}" alt="">@else<span class="lj-semfoto">{{ mb_substr($i->descricao, 0, 1) }}</span>@endif</div>
                    <div>{{ $i->descricao }}<small>{{ rtrim(rtrim(number_format((float) $i->quantidade, 3, ',', '.'), '0'), ',') }} × R$ {{ number_format((float) $i->preco_unitario, 2, ',', '.') }}</small></div>
                    <div class="lj-item__total">R$ {{ number_format((float) $i->total, 2, ',', '.') }}</div>
                </div>
            @endforeach
            @if(filled($pedido->observacoes_externas))<p style="margin-top:1rem;color:var(--suave)"><strong style="color:var(--texto)">Seu recado:</strong> {{ $pedido->observacoes_externas }}</p>@endif

            @if(($podeAvaliar ?? false) && $s !== 'cancelado')
            <section class="lj-avaliar" id="avaliar">
                <h2>O que você achou?</h2>
                <p style="color:var(--suave);font-size:.92rem;margin-bottom:.8rem">Sua avaliação ajuda quem está escolhendo.{{ $loja->opcao('avaliacoes_moderar') ? ' Ela aparece na loja depois de conferida.' : '' }}</p>
                @if(session('loja_erro'))<div class="lj-aviso lj-aviso--erro"><i class="bi bi-exclamation-circle"></i><span>{{ session('loja_erro') }}</span></div>@endif
                @php($jaVistos = [])
                @foreach($itens as $i)
                    @php($alvo = (int) (($gradeDe[$i->produto_id] ?? null) ?: $i->produto_id))
                    @continue(in_array($alvo, $jaVistos, true))
                    @php($jaVistos[] = $alvo)
                    @php($feita = $avaliacoesFeitas[$alvo] ?? null)
                    <form class="lj-avaliar__item" method="post" action="{{ $ctx->url('pedido/' . $pedido->loja_token . '/avaliar') }}">
                        @csrf
                        <input type="hidden" name="produto_id" value="{{ $i->produto_id }}">
                        <strong>{{ $i->descricao }}</strong>
                        <fieldset class="lj-estrelas-campo">
                            <legend class="lj-so-leitor">Nota para {{ $i->descricao }}</legend>
                            @for($n = 5; $n >= 1; $n--)
                                <input type="radio" name="nota" value="{{ $n }}" id="nt-{{ $alvo }}-{{ $n }}" @checked((int) ($feita->nota ?? 0) === $n) required>
                                <label for="nt-{{ $alvo }}-{{ $n }}" title="{{ $n }} {{ $n === 1 ? 'estrela' : 'estrelas' }}"><i class="bi bi-star-fill" aria-hidden="true"></i><span class="lj-so-leitor">{{ $n }} {{ $n === 1 ? 'estrela' : 'estrelas' }}</span></label>
                            @endfor
                        </fieldset>
                        <label class="lj-so-leitor" for="tx-{{ $alvo }}">Comentário</label>
                        <textarea class="lj-campo" id="tx-{{ $alvo }}" name="texto" rows="2" maxlength="1000" placeholder="Conte como foi (opcional)">{{ $feita->texto ?? '' }}</textarea>
                        <button class="lj-btn lj-btn--p {{ $feita ? 'lj-btn--linha' : '' }}" type="submit">{{ $feita ? 'Atualizar avaliação' : 'Enviar avaliação' }}</button>
                    </form>
                @endforeach
            </section>
            @endif
        </div>

        <aside class="lj-resumo">
            <h2>Resumo</h2>
            <dl>
                <dt>Produtos</dt><dd>R$ {{ number_format((float) $pedido->subtotal, 2, ',', '.') }}</dd>
                @if((float) $pedido->desconto_valor > 0)<dt class="lj-desconto">Desconto{{ $pedido->loja_cupom ? ' (cupom ' . $pedido->loja_cupom . ')' : '' }}</dt><dd class="lj-desconto">− R$ {{ number_format((float) $pedido->desconto_valor, 2, ',', '.') }}</dd>@endif
                <dt>{{ $retirada ? 'Retirada na loja' : 'Entrega' . ($pedido->frete_servico_nome ? ' · ' . $pedido->frete_servico_nome : '') }}</dt>
                <dd>{{ $retirada ? 'Grátis' : ($pedido->frete_valor !== null ? ((float) $pedido->frete_valor == 0 ? 'Grátis' : 'R$ ' . number_format((float) $pedido->frete_valor, 2, ',', '.')) : 'a combinar') }}</dd>
                <dt class="lj-total">Total</dt><dd class="lj-total">R$ {{ number_format((float) $pedido->total, 2, ',', '.') }}</dd>
            </dl>
            <div class="lj-bloco">
                <h2>{{ $retirada ? 'Retirar em' : 'Entregar para' }}</h2>
                @if($retirada)
                    <p>{{ $unidade?->nome }}</p>
                    <p style="color:var(--suave);font-size:.92rem">{{ $unidade ? collect([trim(($unidade->logradouro ?? '') . ' ' . ($unidade->numero ?? '')), $unidade->bairro, trim(($unidade->cidade ?? '') . ($unidade->uf ? '/' . $unidade->uf : ''))])->filter()->implode(', ') : '' }}</p>
                @else
                    <p>{{ $cliente->nome_razao_social ?? '' }}</p>
                    <p style="color:var(--suave);font-size:.92rem">{{ collect([trim(($cliente->logradouro ?? '') . ', ' . ($cliente->numero ?? ''), ', '), $cliente->complemento ?? null, $cliente->bairro ?? null, trim(($cliente->cidade ?? '') . '/' . ($cliente->uf ?? ''), '/'), $cliente->cep ?? null])->filter()->implode(' · ') }}</p>
                    @if($pedido->frete_prazo_dias)<p style="color:var(--suave);font-size:.92rem">Prazo: até {{ $pedido->frete_prazo_dias }} dias úteis depois da postagem.</p>@endif
                @endif
            </div>
            <div class="lj-bloco">
                <h2>Guarde este endereço</h2>
                <p style="color:var(--suave);font-size:.88rem;margin-bottom:.6rem">É por ele que você acompanha o pedido.</p>
                <div class="lj-pix__codigo">
                    <input class="lj-campo" readonly value="{{ $ctx->urlAbsoluta('pedido/' . $pedido->loja_token) }}" aria-label="Endereço do pedido" data-lj-copia>
                    <button class="lj-btn lj-btn--p lj-btn--linha" type="button" data-lj-copiar>Copiar</button>
                </div>
            </div>
            {{-- quando o quadro principal já traz o botão do WhatsApp, não repete aqui --}}
            @if($zap && ($s !== 'aguardando' || ($podePagar && ($meios['pix'] || $meios['cartao']))))<a class="lj-btn lj-btn--linha lj-btn--cheio" style="margin-top:1rem" href="{{ $zap }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Falar com a loja</a>@endif
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
<script>
@if($compraAgora)
LJ.rastro.evento('compra', {pedido: {{ (int) $pedido->numero }}, valor: {{ (float) $pedido->total }}, itens: {!! json_encode($itens->map(fn ($i) => ['id' => $i->produto_id, 'nome' => $i->descricao, 'preco' => (float) $i->preco_unitario, 'quantidade' => (float) $i->quantidade])->values(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) !!}});
@endif
(function () {
    var raiz = document.querySelector('[data-lj-pedido]');
    document.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-lj-copiar]');
        if (!b) return;
        var campo = b.parentElement.querySelector('[data-lj-copia]');
        var feito = function () { var t = b.textContent; b.textContent = 'Copiado!'; setTimeout(function () { b.textContent = t; }, 1800); };
        if (navigator.clipboard) navigator.clipboard.writeText(campo.value).then(feito, function () { campo.select(); document.execCommand('copy'); feito(); });
        else { campo.select(); document.execCommand('copy'); feito(); }
    });

    function desenharQr(codigo) {
        var alvo = document.querySelector('[data-lj-qr]');
        if (!alvo || alvo.querySelector('img') || !codigo || typeof qrcode !== 'function') return;
        try { var q = qrcode(0, 'M'); q.addData(codigo); q.make(); alvo.innerHTML = q.createSvgTag({cellSize: 4, margin: 0, scalable: true}); } catch (e) {}
    }
    var copia = document.querySelector('#lj-copia');
    if (copia) desenharQr(copia.value);

    var pagar = document.querySelector('[data-lj-pagar]');
    if (pagar) {
        var erro = pagar.querySelector('[data-lj-pagar-erro]');
        pagar.addEventListener('click', function (ev) {
            var b = ev.target.closest('[data-lj-meio]');
            if (!b) return;
            b.disabled = true; erro.hidden = true;
            LJ.post(pagar.dataset.url, {meio: b.dataset.ljMeio}).then(function (j) {
                if (!j.ok) { erro.querySelector('span').textContent = j.erro || 'Não foi possível gerar o pagamento agora.'; erro.hidden = false; return; }
                if (j.tipo === 'qr') {
                    pagar.querySelector('[data-lj-cartao]').hidden = true;
                    var bq = pagar.querySelector('[data-lj-boleto]'); if (bq) bq.hidden = true;
                    var qr = pagar.querySelector('[data-lj-qr]'); qr.innerHTML = '';
                    if (j.qr_base64) { var im = new Image(); im.alt = 'QR Code do PIX'; im.src = 'data:image/png;base64,' + j.qr_base64; qr.appendChild(im); }
                    copia.value = j.copia_cola; desenharQr(j.copia_cola);
                    pagar.querySelector('[data-lj-pix]').hidden = false;
                    b.classList.add('lj-btn--linha'); b.lastChild.textContent = ' Gerar o PIX de novo';
                } else if (j.tipo === 'boleto') {
                    pagar.querySelector('[data-lj-pix]').hidden = true; pagar.querySelector('[data-lj-cartao]').hidden = true;
                    var cx = pagar.querySelector('[data-lj-boleto]');
                    cx.querySelector('[data-lj-boleto-link]').href = j.link; cx.querySelector('#lj-linha').value = j.linha || '';
                    cx.querySelector('[data-lj-boleto-vence]').textContent = j.vence_em || ''; cx.hidden = false;
                    window.open(j.link, '_blank', 'noopener');
                } else {
                    pagar.querySelector('[data-lj-pix]').hidden = true;
                    var bx = pagar.querySelector('[data-lj-boleto]'); if (bx) bx.hidden = true;
                    pagar.querySelector('[data-lj-link]').href = j.link;
                    pagar.querySelector('[data-lj-cartao]').hidden = false;
                    window.open(j.link, '_blank', 'noopener');
                }
            }).catch(function () { erro.querySelector('span').textContent = 'Sem conexão. Tente de novo.'; erro.hidden = false; })
              .finally(function () { b.disabled = false; });
        });
    }

    // Enquanto aguarda pagamento, pergunta ao servidor e recarrega quando mudar.
    if (raiz.dataset.situacao === 'aguardando') {
        var url = @json($ctx->url('pedido/' . $pedido->loja_token . '/situacao')), vezes = 0;
        var laco = setInterval(function () {
            if (document.hidden) return;
            if (++vezes > 150) { clearInterval(laco); return; }
            fetch(url, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'}).then(function (r) { return r.json(); }).then(function (j) {
                if (j.situacao && j.situacao !== 'aguardando') { clearInterval(laco); location.reload(); }
            }).catch(function () {});
        }, 6000);
    }
})();
</script>
@endpush
