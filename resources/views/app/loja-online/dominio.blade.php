@extends('layouts.app')

@section('title', 'Loja online — Domínio')

@section('content')
<x-erp.page-header title="Loja online" icon="globe2" subtitle="Domínio: a loja no seu endereço (www.sualoja.com.br)">
    <a href="{{ $loja->enderecoInterno() }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-up-right me-1"></i> {{ $loja->noAr() ? 'Abrir a loja' : 'Ver prévia' }}</a>
</x-erp.page-header>

@include('app.loja-online._abas')

@php
    $pode = auth()->user()->hasPermission('loja_online', 'editar');
    $status = $loja->dominio ? ($loja->dominio_status ?: 'aguardando') : null;
    $passo = ! $loja->dominio ? 1 : ($status === 'aguardando' ? 2 : 3);
    $copiar = fn (string $valor) => "navigator.clipboard.writeText('" . e($valor) . "').then(function(){ERP.toast('Copiado.','success')})";
@endphp

<div class="row g-3">
    <div class="col-lg-8">
        <x-erp.card title="Hoje a sua loja abre em" icon="link-45deg" class="mb-3">
            <div class="input-group">
                <input type="text" class="form-control" readonly value="{{ $loja->enderecoPublico() }}" id="enderecoAtual" aria-label="Endereço atual da loja">
                <button class="btn btn-outline-secondary" type="button" onclick="{{ $copiar($loja->enderecoPublico()) }}"><i class="bi bi-clipboard"></i> Copiar</button>
            </div>
            <div class="form-text">Este endereço funciona sempre, com ou sem domínio próprio. O domínio próprio é opcional: serve para a loja ter o nome da sua marca.</div>
        </x-erp.card>

        {{-- Passo 1 --}}
        <x-erp.card class="mb-3">
            <div class="d-flex align-items-start gap-3" data-passo="1">
                <span class="badge rounded-pill fs-6 {{ $passo > 1 ? 'text-bg-success' : 'text-bg-primary' }}">{!! $passo > 1 ? '<i class="bi bi-check-lg"></i>' : '1' !!}</span>
                <div class="flex-grow-1">
                    <h6 class="mb-1">Qual é o seu domínio?</h6>
                    <p class="small text-muted mb-2">É o endereço que você comprou (no Registro.br, GoDaddy, Hostinger…). Recomendamos usar com <strong>www</strong> na frente. Ainda não tem um? Compre em <a href="https://registro.br" target="_blank" rel="noopener">registro.br</a> — um <code>.com.br</code> custa cerca de R$ 40 por ano.</p>
                    <form method="POST" action="{{ route('app.loja-online.dominio.salvar') }}" class="row g-2">
                        @csrf
                        <div class="col-sm-8">
                            <input type="text" name="dominio" id="dominio" class="form-control @error('dominio') is-invalid @enderror" value="{{ old('dominio', $loja->dominio) }}" placeholder="www.sualoja.com.br" maxlength="190" autocomplete="off" autocapitalize="none" spellcheck="false" aria-label="Seu domínio" @disabled(! $pode) required>
                            @error('dominio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-4"><button type="submit" class="btn btn-primary w-100" @disabled(! $pode)>{{ $loja->dominio ? 'Trocar domínio' : 'Continuar' }}</button></div>
                    </form>
                    <div class="form-text">Pode colar o endereço inteiro: a tela tira o "https://" sozinha.</div>
                </div>
            </div>
        </x-erp.card>

        {{-- Passo 2 --}}
        <x-erp.card class="mb-3">
            <div class="d-flex align-items-start gap-3 {{ $passo < 2 ? 'opacity-50' : '' }}" data-passo="2">
                <span class="badge rounded-pill fs-6 {{ $passo > 2 ? 'text-bg-success' : ($passo === 2 ? 'text-bg-primary' : 'text-bg-secondary') }}">{!! $passo > 2 ? '<i class="bi bi-check-lg"></i>' : '2' !!}</span>
                <div class="flex-grow-1">
                    <h6 class="mb-1">Aponte o domínio para a loja</h6>
                    @if(! $loja->dominio)
                        <p class="small text-muted mb-0">Depois de informar o domínio, mostramos aqui exatamente o que criar, com botão de copiar.</p>
                    @else
                        <p class="small text-muted mb-2">Entre no site onde você comprou <strong>{{ $loja->dominio }}</strong>, abra a parte de <strong>DNS</strong> (ou "Zona DNS") e crie este registro:</p>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-2" data-registros>
                                <thead class="table-light"><tr><th>Tipo</th><th>Nome</th><th>Valor (aponta para)</th></tr></thead>
                                <tbody>
                                @foreach($registros as $reg)
                                    <tr>
                                        <td><strong data-reg-tipo>{{ $reg['tipo'] }}</strong></td>
                                        <td><code data-reg-nome>{{ $reg['nome'] }}</code> <button type="button" class="btn btn-sm btn-link p-0 ms-1" onclick="{{ $copiar($reg['nome']) }}" aria-label="Copiar o nome"><i class="bi bi-clipboard"></i></button></td>
                                        <td><code data-reg-valor>{{ $reg['valor'] }}</code> <button type="button" class="btn btn-sm btn-link p-0 ms-1" onclick="{{ $copiar($reg['valor']) }}" aria-label="Copiar o valor"><i class="bi bi-clipboard"></i></button></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        @foreach($registros as $reg)<div class="form-text mt-0 mb-2">{{ $reg['nota'] }}</div>@endforeach
                        <ul class="small text-muted mb-0 ps-3">
                            <li>Se já existir um registro com o mesmo nome, <strong>troque o valor</strong> em vez de criar outro.</li>
                            <li>O campo "TTL" pode ficar como está.</li>
                            <li>Não mexa nos registros de e-mail (os do tipo MX): eles não têm relação com a loja.</li>
                        </ul>
                    @endif
                </div>
            </div>
        </x-erp.card>

        {{-- Passo 3 --}}
        <x-erp.card>
            <div class="d-flex align-items-start gap-3 {{ $passo < 2 ? 'opacity-50' : '' }}" data-passo="3">
                <span class="badge rounded-pill fs-6 {{ $status === 'ativo' ? 'text-bg-success' : ($passo >= 2 ? 'text-bg-primary' : 'text-bg-secondary') }}">{!! $status === 'ativo' ? '<i class="bi bi-check-lg"></i>' : '3' !!}</span>
                <div class="flex-grow-1">
                    <h6 class="mb-1">Confira se já está valendo</h6>
                    @if(! $loja->dominio)
                        <p class="small text-muted mb-0">Um botão confere o apontamento e diz o que falta, se faltar.</p>
                    @else
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span class="badge text-bg-{{ $situacao[1] ?? 'warning' }} fs-6" data-dominio-situacao="{{ $status }}">{{ $situacao[0] ?? 'Aguardando o apontamento' }}</span>
                            @if($loja->dominio_conferido_em)<span class="small text-muted">conferido em {{ $loja->dominio_conferido_em->format('d/m/Y H:i') }}</span>@endif
                        </div>
                        @if($loja->dominio_obs)<p class="small mb-2" data-dominio-obs>{{ $loja->dominio_obs }}</p>@endif
                        @if($status === 'ativo')
                            <p class="small mb-2">Sua loja: <a href="https://{{ $loja->dominio }}" target="_blank" rel="noopener">https://{{ $loja->dominio }}</a></p>
                        @endif
                        <div class="d-flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('app.loja-online.dominio.conferir') }}">@csrf
                                <button type="submit" class="btn {{ $status === 'ativo' ? 'btn-outline-secondary' : 'btn-primary' }}" @disabled(! $pode)><i class="bi bi-arrow-repeat me-1"></i> Conferir agora</button>
                            </form>
                            <form method="POST" action="{{ route('app.loja-online.dominio.tirar') }}" onsubmit="return confirm('Tirar o domínio {{ $loja->dominio }} da loja? Ela continua abrindo pelo endereço de sempre.')">@csrf
                                <button type="submit" class="btn btn-outline-danger" @disabled(! $pode)>Tirar o domínio</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </x-erp.card>
    </div>

    <div class="col-lg-4">
        <x-erp.card title="Onde fica o DNS em cada site" icon="signpost" class="mb-3">
            <div class="accordion accordion-flush" id="ondeDns">
                @foreach([
                    ['Registro.br', 'Entre em registro.br → clique no domínio → "Configurar zona DNS" (ou "Editar zona") → "Nova entrada". Escolha o tipo, preencha nome e valor e salve.'],
                    ['GoDaddy', 'Meus produtos → ao lado do domínio, "DNS" → "Adicionar novo registro". Escolha o tipo, preencha nome e valor e salve.'],
                    ['Hostinger', 'Domínios → clique no domínio → "DNS / Nameservers" → "Gerenciar registros DNS" → preencha tipo, nome e valor → "Adicionar registro".'],
                    ['Cloudflare', 'Escolha o domínio → DNS → Records → "Add record". Deixe a nuvem em cinza ("DNS only") até a loja abrir com cadeado.'],
                    ['Outro site', 'Procure por "DNS", "Zona DNS" ou "Gerenciar DNS" no painel. Todos pedem as mesmas três coisas: tipo, nome e valor.'],
                ] as $i => [$site, $como])
                    <div class="accordion-item">
                        <h2 class="accordion-header"><button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#dns{{ $i }}">{{ $site }}</button></h2>
                        <div id="dns{{ $i }}" class="accordion-collapse collapse" data-bs-parent="#ondeDns"><div class="accordion-body small py-2">{{ $como }}</div></div>
                    </div>
                @endforeach
            </div>
        </x-erp.card>
        <x-erp.card title="Dúvidas comuns" icon="question-circle">
            <dl class="small mb-0">
                <dt>Quanto tempo demora?</dt><dd>O apontamento costuma valer em minutos, mas pode levar algumas horas. Clique em "Conferir agora" de vez em quando.</dd>
                <dt>Vou perder meu e-mail ou meu site antigo?</dt><dd>O e-mail não muda. Se já existe um site nesse mesmo endereço, ele dá lugar à loja — se quiser manter o site, use outro nome, como <code>loja.suaempresa.com.br</code>.</dd>
                <dt>O que é o cadeado?</dt><dd>É o certificado de segurança (o "https"). Sem ele o navegador avisa que o site não é seguro; por isso a loja só é dada como "no ar" quando ele está pronto.</dd>
                <dt class="mb-0">E o endereço antigo?</dt><dd class="mb-0">Continua funcionando. Os links que você já divulgou não quebram.</dd>
            </dl>
        </x-erp.card>
    </div>
</div>
@endsection
