<section class="lj-caixa lj-secao">
    <div class="lj-texto" data-al="centro">
        @if(filled($bloco['titulo'] ?? ''))<h2>{{ $bloco['titulo'] }}</h2>@endif
        @if(filled($bloco['texto'] ?? ''))<p>{{ $bloco['texto'] }}</p>@endif
        @include('loja.partials.redes', ['classe' => 'lj-redes--grandes', 'comNome' => true])
        @if($ctx->rascunho && blank($loja->opcao('instagram')) && blank($loja->opcao('facebook')) && blank($loja->opcao('tiktok')) && blank($loja->opcao('youtube')) && blank($loja->opcao('whatsapp')))
            <div class="lj-vazio" style="margin-top:1rem">Nenhuma rede cadastrada. Preencha em Loja online → Configurações → Redes sociais.</div>
        @endif
    </div>
</section>
