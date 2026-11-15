@extends('loja.layout')

@section('conteudo')
@php($meios = \App\Services\LojaOnline\PagamentoLoja::meios($loja))
<div class="lj-caixa">
    <div class="lj-pagina__topo"><h1>Entrega, pagamento e trocas</h1></div>
    <div class="lj-texto" style="max-width:72ch;padding-bottom:clamp(2rem,6vw,4rem)">
        <h2>Como recebo meu pedido</h2>
        <p>@if($loja->opcao('entrega'))Entregamos no endereço que você informar. O frete é calculado pelo CEP antes de você pagar.@endif
@if($loja->opcao('retirada'))Você também pode retirar na loja, sem frete{{ $unidade ? ': ' . collect([trim(($unidade->logradouro ?? '') . ' ' . ($unidade->numero ?? '')), $unidade->bairro, trim(($unidade->cidade ?? '') . ($unidade->uf ? '/' . $unidade->uf : ''))])->filter()->implode(', ') : '' }}.@endif</p>

        <h2 style="margin-top:2rem">Como pago</h2>
        <p>@if($meios['pix'])PIX: o código aparece na tela assim que você fecha o pedido, e a confirmação é automática.@endif
@if($meios['cartao'])Cartão: você paga em uma página segura do nosso parceiro de pagamento.@endif
@if($meios['combinar'])Se preferir, feche o pedido e combine o pagamento com a loja.@endif</p>

        <h2 style="margin-top:2rem">Trocas e devoluções</h2>
        <p>{{ filled($loja->opcao('texto_trocas')) ? $loja->opcao('texto_trocas') : 'Fale com a loja para trocar ou devolver um produto. Compras feitas pela internet podem ser devolvidas em até 7 dias depois do recebimento, conforme o Código de Defesa do Consumidor.' }}</p>

        @if(filled($loja->opcao('whatsapp')) || filled($loja->opcao('email')))
            <h2 style="margin-top:2rem">Fale com a gente</h2>
            <p>@if(filled($loja->opcao('whatsapp')))WhatsApp: {{ $loja->opcao('whatsapp') }}@endif
@if(filled($loja->opcao('email')))E-mail: {{ $loja->opcao('email') }}@endif</p>
        @endif
    </div>
</div>
@endsection
