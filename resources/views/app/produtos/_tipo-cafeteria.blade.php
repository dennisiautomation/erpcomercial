{{--
    Tipo do produto + atalho no PDV (Fase 1 cafeteria, 30/10/2026).
    Só entra na tela da empresa com "Usa ficha técnica" ligado; para as outras
    o produto continua Revenda sem ninguém ver o campo.
--}}
@php
    $tipoAtual = old('tipo', isset($produto) ? $produto->tipoEnum()->value : 'revenda');
    $atalhoAtual = (bool) old('atalho_pdv', isset($produto) ? $produto->atalho_pdv : false);
    $icones = ['revenda' => 'bag', 'produzido' => 'cup-hot', 'insumo' => 'basket', 'adicional' => 'plus-circle'];
@endphp
<div class="col-12">
    <label class="form-label fw-semibold mb-1">Tipo do produto</label>
    <div class="row g-2" id="tipoProdutoCards">
        @foreach(\App\Enums\TipoProduto::cases() as $tipo)
            <div class="col-6 col-lg-3">
                <input type="radio" class="btn-check" name="tipo" id="tipo_{{ $tipo->value }}" value="{{ $tipo->value }}" @checked($tipoAtual === $tipo->value)>
                <label class="tipo-card w-100 h-100 text-start p-2" for="tipo_{{ $tipo->value }}">
                    <span class="fw-semibold d-block"><i class="bi bi-{{ $icones[$tipo->value] }} me-1"></i>{{ $tipo->label() }}</span>
                    <small class="d-block text-muted" style="line-height:1.25">{{ $tipo->descricao() }}</small>
                </label>
            </div>
        @endforeach
    </div>
    @error('tipo') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    <div class="form-text" id="tipoProdutoAjuda">
        @if(isset($produto) && $produto->exists)
            A receita e os adicionais ficam em <a href="{{ route('app.produtos.composicao', $produto) }}">Ficha técnica e opções</a>.
        @else
            Depois de salvar, a receita (produzido) e os adicionais ficam em "Ficha técnica e opções", na tela do produto.
        @endif
    </div>
</div>
<div class="col-12" id="atalhoPdvWrap">
    <input type="hidden" name="atalho_pdv" value="0">
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" name="atalho_pdv" id="atalho_pdv" value="1" @checked($atalhoAtual)>
        <label class="form-check-label" for="atalho_pdv">Mostrar nos atalhos do PDV <span class="text-muted">(botão para tocar e lançar)</span></label>
    </div>
</div>
<style>
    /* Cartão do tipo: selecionado = borda forte + fundo claro, texto sempre escuro (contraste AA) */
    #tipoProdutoCards .tipo-card { display: block; border: 1px solid var(--bs-border-color); border-radius: .5rem; background: #fff; cursor: pointer; color: var(--bs-body-color); }
    #tipoProdutoCards .tipo-card i { color: var(--bs-primary); }
    #tipoProdutoCards .btn-check:checked + .tipo-card { border: 2px solid var(--bs-primary); background: rgba(79, 70, 229, .07); padding: calc(.5rem - 1px) !important; }
    #tipoProdutoCards .btn-check:focus-visible + .tipo-card { outline: 2px solid var(--bs-primary); outline-offset: 2px; }
</style>
@push('scripts')
<script>
(function () {
    // Insumo e adicional não vão sozinhos ao PDV: sem atalho para eles
    const wrap = document.getElementById('atalhoPdvWrap');
    const sync = () => {
        const tipo = document.querySelector('input[name="tipo"]:checked')?.value || 'revenda';
        const vende = tipo === 'revenda' || tipo === 'produzido';
        wrap.style.display = vende ? '' : 'none';
        if (!vende) document.getElementById('atalho_pdv').checked = false;
    };
    document.querySelectorAll('input[name="tipo"]').forEach(r => r.addEventListener('change', sync));
    sync();
})();
</script>
@endpush
