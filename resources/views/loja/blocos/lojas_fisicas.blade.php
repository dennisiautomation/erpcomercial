{{-- Nossas lojas (bloco do modelo exclusivo, 17/11/2026). Endereço e telefone vêm do cadastro das lojas no ERP. --}}
@php
    $titulo = fn (?string $t) => mb_convert_case(mb_strtolower(trim((string) $t)), MB_CASE_TITLE);
    $unidades = $bloco['unidades'] ?? collect();
@endphp
@if($unidades->isNotEmpty())
<section class="lj-caixa lj-secao lj-lojas" aria-labelledby="lj-lojas-{{ $bloco['id'] ?? 'x' }}">
    <div class="lj-lojas__cabeca">
        <h2 id="lj-lojas-{{ $bloco['id'] ?? 'x' }}">{{ $bloco['titulo'] ?: 'Nossas lojas' }}</h2>
        @if(filled($bloco['texto'] ?? ''))<p>{{ $bloco['texto'] }}</p>@endif
    </div>
    <ol class="lj-lojas__lista">
        @foreach($unidades as $u)
            @php
                $fone = preg_replace('/\D/', '', (string) $u->telefone);
                $foneTela = strlen($fone) === 11 ? sprintf('(%s) %s-%s', substr($fone, 0, 2), substr($fone, 2, 5), substr($fone, 7))
                    : (strlen($fone) === 10 ? sprintf('(%s) %s-%s', substr($fone, 0, 2), substr($fone, 2, 4), substr($fone, 6)) : '');
                $rua = trim($titulo($u->logradouro) . (filled($u->numero) && ! in_array(mb_strtoupper(trim($u->numero)), ['SN', 'S/N'], true) ? ', ' . $u->numero : ''));
                $cidade = trim($titulo($u->cidade) . ($u->uf ? ' · ' . mb_strtoupper($u->uf) : ''));
                $mapa = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(collect([$rua, $titulo($u->bairro), $titulo($u->cidade), $u->uf])->filter()->implode(', '));
            @endphp
            <li class="lj-loja">
                <span class="lj-loja__n" aria-hidden="true">{{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                <h3>{{ $titulo(preg_replace('/^loja\s+/i', '', (string) $u->nome)) }}</h3>
                <p class="lj-loja__cidade">{{ $cidade }}</p>
                @if($rua !== '')<p>{{ $rua }}@if(filled($u->bairro))<br>{{ $titulo($u->bairro) }}@endif</p>@endif
                <p class="lj-loja__acoes">
                    @if($rua !== '')<a href="{{ $mapa }}" target="_blank" rel="noopener"><i class="bi bi-geo-alt" aria-hidden="true"></i> Como chegar</a>@endif
                    @if($foneTela)<a href="https://wa.me/55{{ $fone }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i> {{ $foneTela }}</a>@endif
                </p>
            </li>
        @endforeach
    </ol>
</section>
@endif
