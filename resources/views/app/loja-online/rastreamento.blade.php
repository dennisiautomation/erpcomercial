@extends('layouts.app')

@section('title', 'Loja online — Anúncios e busca')

@section('content')
<x-erp.page-header title="Loja online" icon="globe2" subtitle="Anúncios, medição e como a loja aparece nas buscas" />

@include('app.loja-online._abas')

<form method="POST" action="{{ route('app.loja-online.secao.salvar', 'rastreamento') }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    <x-erp.card title="Rastreamento de anúncios e visitas" icon="graph-up-arrow" class="mb-3">
        <p class="text-muted small">Cole só o <strong>código de identificação</strong> de cada ferramenta — a loja monta o resto. Ela avisa cada uma quando alguém <strong>vê um produto, põe no carrinho, começa a fechar e conclui a compra</strong> (com o valor), que é o que as campanhas precisam para medir venda. Nada carrega antes de o visitante aceitar os cookies.</p>
        <div class="row g-3">
            @foreach([
                ['meta_pixel', 'Pixel da Meta (Facebook e Instagram)', '1234567890123456', 'Gerenciador de Eventos → Fontes de dados → ID do pixel'],
                ['ga4', 'Google Analytics 4', 'G-AB12CD34EF', 'Administrador → Fluxos de dados → ID da métrica'],
                ['google_ads', 'Google Ads', 'AW-123456789', 'Metas → Conversões → Tag do Google'],
                ['google_ads_rotulo', 'Google Ads — rótulo da conversão de compra', 'AbCdEfGhIj', 'É o trecho depois da barra em AW-123456789/AbCdEfGhIj'],
                ['gtm', 'Google Tag Manager', 'GTM-ABC1234', 'Só se você gerencia as tags por lá'],
                ['tiktok_pixel', 'Pixel do TikTok', 'C4ABCDE1234567890XYZ', 'TikTok Ads → Eventos → ID do pixel'],
            ] as [$campo, $rotulo, $exemplo, $ajuda])
                <div class="col-md-6">
                    <label for="{{ $campo }}" class="form-label">{{ $rotulo }}</label>
                    <input type="text" name="{{ $campo }}" id="{{ $campo }}" class="form-control @error($campo) is-invalid @enderror" value="{{ old($campo, $loja->opcao($campo)) }}" placeholder="{{ $exemplo }}" autocomplete="off" spellcheck="false">
                    @error($campo) <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">{{ $ajuda }}</div>
                </div>
            @endforeach
        </div>
    </x-erp.card>

    <x-erp.card title="Confirmar que o domínio é seu" icon="patch-check" class="mb-3">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="verificacao_google" class="form-label">Google Search Console / Merchant Center</label>
                <input type="text" name="verificacao_google" id="verificacao_google" class="form-control @error('verificacao_google') is-invalid @enderror" value="{{ old('verificacao_google', $loja->opcao('verificacao_google')) }}" spellcheck="false">
                @error('verificacao_google') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">Método "tag HTML": cole só o código que vem em <code>content="…"</code>.</div>
            </div>
            <div class="col-md-6">
                <label for="verificacao_meta" class="form-label">Meta — verificação de domínio</label>
                <input type="text" name="verificacao_meta" id="verificacao_meta" class="form-control @error('verificacao_meta') is-invalid @enderror" value="{{ old('verificacao_meta', $loja->opcao('verificacao_meta')) }}" spellcheck="false">
                @error('verificacao_meta') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">Configurações do negócio → Segurança da marca → Domínios → "metatag": só o código do <code>content="…"</code>.</div>
            </div>
        </div>
    </x-erp.card>

    <x-erp.card title="Como a loja aparece no Google e ao compartilhar" icon="search" class="mb-3">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="seo_titulo" class="form-label">Título da página inicial</label>
                <input type="text" name="seo_titulo" id="seo_titulo" maxlength="70" class="form-control" value="{{ old('seo_titulo', $loja->opcao('seo_titulo')) }}" placeholder="{{ $loja->nome }}">
            </div>
            <div class="col-md-6">
                <label for="descricao_busca" class="form-label">Descrição</label>
                <input type="text" name="descricao_busca" id="descricao_busca" maxlength="160" class="form-control" value="{{ old('descricao_busca', $loja->opcao('descricao_busca')) }}" placeholder="O que a loja vende, em uma frase">
            </div>
            @foreach([['imagem_compartilhar', 'Imagem ao compartilhar o link', 'Aparece no WhatsApp e nas redes. Deitada, 1200 × 630.'], ['favicon', 'Ícone da aba do navegador', 'Quadrado, PNG, 256 × 256.']] as [$campo, $rotulo, $ajuda])
                <div class="col-md-6">
                    <label for="{{ $campo }}" class="form-label">{{ $rotulo }}</label>
                    <input type="file" name="{{ $campo }}" id="{{ $campo }}" accept="image/png,image/jpeg,image/webp" class="form-control @error($campo) is-invalid @enderror">
                    @error($campo) <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">{{ $ajuda }}</div>
                    @if(filled($loja->opcao($campo)))
                        <div class="d-flex align-items-center gap-3 mt-2">
                            <img src="{{ Storage::url($loja->opcao($campo)) }}" class="rounded border" style="height:48px" alt="Imagem atual">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="remover_{{ $campo }}" id="remover_{{ $campo }}" value="1"><label class="form-check-label" for="remover_{{ $campo }}">Tirar</label></div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="form-text mt-2">A loja já publica sozinha o mapa do site (<code>/sitemap.xml</code>) e os dados de produto para o Google (preço e disponibilidade).</div>
    </x-erp.card>
    <div class="lj-salvar d-flex justify-content-end"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Salvar</button></div>
</form>
@endsection
