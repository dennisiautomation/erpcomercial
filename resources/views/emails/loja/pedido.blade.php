@php
    $retirada = $pedido->metodo_entrega === 'retirada';
    $moeda = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $titulo = match ($tipo) {
        'loja_novo' => 'Chegou um pedido novo',
        'confirmado' => 'Pedido confirmado',
        'faturado' => $retirada ? 'Seu pedido está pronto para retirada' : 'Seu pedido está a caminho',
        'enviado' => 'Seu pedido foi enviado',
        'entregue' => 'Pedido entregue',
        'cancelado' => 'Pedido cancelado',
        default => 'Recebemos o seu pedido',
    };
    $texto = match ($tipo) {
        'loja_novo' => 'Um cliente fechou um pedido na sua loja online. Ele já está em Comercial → Pedidos, com o canal Online.',
        'confirmado' => 'Está tudo certo com o seu pedido. A loja já está separando os produtos.',
        'faturado' => $retirada ? 'Você já pode retirar em ' . ($unidade?->nome ?? 'nossa loja') . '.' : 'Os produtos foram separados e seguem para o seu endereço.',
        'enviado' => 'Seu pedido saiu' . (filled($pedido->loja_transportadora) ? ' por ' . $pedido->loja_transportadora : '') . '.'
            . (filled($pedido->loja_rastreio_codigo) ? ' Código de rastreio: ' . $pedido->loja_rastreio_codigo . '.' : '') . ' Pelo botão abaixo você acompanha.',
        'entregue' => 'Obrigado por comprar com a gente!',
        'cancelado' => 'Este pedido foi cancelado. Se você já pagou, a loja vai falar com você sobre a devolução.',
        default => 'Guardamos o seu pedido. Pelo botão abaixo você acompanha o andamento e faz o pagamento, se ainda faltar.',
    };
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>{{ $titulo }}</title></head>
<body style="margin:0;padding:0;background-color:#f2f3f5;font-family:'Segoe UI',system-ui,-apple-system,Arial,sans-serif;color:#1a2230;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f2f3f5;padding:24px 0;"><tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
    <tr><td style="background-color:{{ $cor }};color:{{ $sobreCor }};border-radius:12px 12px 0 0;padding:22px 28px;font-size:20px;font-weight:700;">{{ $loja->nome }}</td></tr>
    <tr><td style="background-color:#ffffff;padding:28px;border:1px solid #e3e6ea;border-top:none;">
        <h1 style="margin:0 0 6px;font-size:22px;line-height:1.25;">{{ $titulo }}</h1>
        <p style="margin:0 0 6px;font-size:14px;color:#5b6676;">Pedido <strong style="color:#1a2230;">#{{ $pedido->numero }}</strong> · {{ $pedido->created_at->format('d/m/Y \à\s H:i') }}</p>
        <p style="margin:12px 0 20px;font-size:15px;line-height:1.6;color:#3a4453;">{{ $texto }}</p>

        @if($tipo === 'loja_novo')
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;background-color:#f7f8fa;border-radius:8px;">
                <tr><td style="padding:14px 16px;font-size:14px;line-height:1.6;">
                    <strong>{{ $cliente->nome_razao_social ?? 'Cliente' }}</strong><br>
                    {{ $cliente->telefone ?? '' }}@if(!empty($cliente->email)) · {{ $cliente->email }}@endif<br>
                    {{ $retirada ? 'Vai retirar na loja' : 'Entrega: ' . collect([trim(($cliente->logradouro ?? '') . ', ' . ($cliente->numero ?? ''), ', '), $cliente->bairro ?? null, trim(($cliente->cidade ?? '') . '/' . ($cliente->uf ?? ''), '/')])->filter()->implode(' · ') }}
                </td></tr>
            </table>
        @else
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 22px;"><tr>
                <td style="background-color:{{ $cor }};border-radius:8px;"><a href="{{ $link }}" style="display:inline-block;padding:13px 24px;color:{{ $sobreCor }};font-weight:700;font-size:15px;text-decoration:none;">Acompanhar o pedido</a></td>
            </tr></table>
        @endif

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border-top:1px solid #e3e6ea;">
            @foreach($itens as $i)
                <tr>
                    <td style="padding:10px 0;border-bottom:1px solid #eef0f3;">{{ $i->descricao }}<br><span style="color:#5b6676;font-size:13px;">{{ rtrim(rtrim(number_format((float) $i->quantidade, 3, ',', '.'), '0'), ',') }} × {{ $moeda($i->preco_unitario) }}</span></td>
                    <td align="right" style="padding:10px 0;border-bottom:1px solid #eef0f3;white-space:nowrap;font-weight:600;">{{ $moeda($i->total) }}</td>
                </tr>
            @endforeach
            <tr><td style="padding:10px 0 2px;color:#5b6676;">Produtos</td><td align="right" style="padding:10px 0 2px;">{{ $moeda($pedido->subtotal) }}</td></tr>
            @if((float) $pedido->desconto_valor > 0)
                <tr><td style="padding:2px 0;color:#5b6676;">Desconto{{ $pedido->loja_cupom ? ' (cupom ' . $pedido->loja_cupom . ')' : '' }}</td><td align="right" style="padding:2px 0;">− {{ $moeda($pedido->desconto_valor) }}</td></tr>
            @endif
            <tr><td style="padding:2px 0;color:#5b6676;">{{ $retirada ? 'Retirada na loja' : 'Entrega' }}</td><td align="right" style="padding:2px 0;">{{ $retirada ? 'Grátis' : ($pedido->frete_valor === null ? 'a combinar' : ((float) $pedido->frete_valor == 0 ? 'Grátis' : $moeda($pedido->frete_valor))) }}</td></tr>
            <tr><td style="padding:10px 0 0;font-size:17px;font-weight:700;border-top:1px solid #e3e6ea;">Total</td><td align="right" style="padding:10px 0 0;font-size:17px;font-weight:700;border-top:1px solid #e3e6ea;">{{ $moeda($pedido->total) }}</td></tr>
        </table>

        @if($tipo !== 'loja_novo' && $retirada && $unidade)
            <p style="margin:20px 0 0;font-size:14px;line-height:1.6;color:#3a4453;"><strong>Retirada:</strong> {{ $unidade->nome }} — {{ collect([trim(($unidade->logradouro ?? '') . ' ' . ($unidade->numero ?? '')), $unidade->bairro, trim(($unidade->cidade ?? '') . '/' . ($unidade->uf ?? ''), '/')])->filter()->implode(', ') }}</p>
        @endif
    </td></tr>
    <tr><td style="background-color:#f7f8fa;border:1px solid #e3e6ea;border-top:none;border-radius:0 0 12px 12px;padding:16px 28px;text-align:center;font-size:12px;color:#5b6676;line-height:1.6;">
        @if($tipo === 'loja_novo')
            Aviso automático da sua loja online. Para parar de receber, desligue em Loja online → Avisos por e-mail.
        @else
            Você recebeu este e-mail porque fez um pedido em {{ $loja->nome }}.@if(filled($loja->opcao('whatsapp'))) Dúvidas? WhatsApp {{ $loja->opcao('whatsapp') }}.@endif
        @endif
    </td></tr>
</table>
</td></tr></table>
</body>
</html>
