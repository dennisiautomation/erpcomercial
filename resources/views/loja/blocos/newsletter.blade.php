<section class="lj-caixa lj-secao">
    <div class="lj-novidades">
        <div>
            <h2>{{ $bloco['titulo'] ?: 'Receba as novidades' }}</h2>
            @if(filled($bloco['texto'] ?? ''))<p>{{ $bloco['texto'] }}</p>@endif
        </div>
        <form data-lj-contato="newsletter" action="{{ $ctx->caminho('contato') }}" novalidate>
            <div class="lj-novidades__linha">
                <label class="lj-so-leitor" for="nl-{{ $bloco['id'] ?? 'x' }}">Seu e-mail</label>
                <input class="lj-campo" id="nl-{{ $bloco['id'] ?? 'x' }}" type="email" name="email" placeholder="seu@email.com" autocomplete="email" required>
                <button class="lj-btn" type="submit">{{ $bloco['botao_texto'] ?: 'Quero receber' }}</button>
            </div>
            <label class="lj-aceite"><input type="checkbox" name="aceite" value="1" required> <span>Aceito receber novidades e ofertas de {{ $loja->nome }}. <a href="{{ $ctx->url('politica/privacidade') }}">Política de privacidade</a></span></label>
            <p class="lj-novidades__retorno" data-lj-contato-retorno role="status" aria-live="polite"></p>
        </form>
    </div>
</section>
