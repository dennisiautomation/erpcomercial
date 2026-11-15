<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>{{ $titulo }}</title></head>
<body style="margin:0;padding:0;background-color:#f2f3f5;font-family:'Segoe UI',system-ui,-apple-system,Arial,sans-serif;color:#1a2230;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f2f3f5;padding:24px 0;"><tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
    <tr><td style="background-color:{{ $cor }};color:{{ $sobreCor }};border-radius:12px 12px 0 0;padding:22px 28px;font-size:20px;font-weight:700;">{{ $loja->nome }}</td></tr>
    <tr><td style="background-color:#ffffff;padding:28px;border:1px solid #e3e6ea;border-top:none;">
        <h1 style="margin:0 0 12px;font-size:22px;line-height:1.25;">{{ $titulo }}</h1>
        @foreach($linhas as $linha)
            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#3a4453;">{{ $linha }}</p>
        @endforeach
        @if($botao && $link)
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:10px 0 4px;"><tr>
                <td style="background-color:{{ $cor }};border-radius:8px;"><a href="{{ $link }}" style="display:inline-block;padding:13px 24px;color:{{ $sobreCor }};font-weight:700;font-size:15px;text-decoration:none;">{{ $botao }}</a></td>
            </tr></table>
        @endif
    </td></tr>
    <tr><td style="background-color:#f7f8fa;border:1px solid #e3e6ea;border-top:none;border-radius:0 0 12px 12px;padding:16px 28px;text-align:center;font-size:12px;color:#5b6676;line-height:1.6;">
        {{ $rodape ?? ('Mensagem automática de ' . $loja->nome . '.') }}@if(filled($loja->opcao('whatsapp'))) Dúvidas? WhatsApp {{ $loja->opcao('whatsapp') }}.@endif
    </td></tr>
</table>
</td></tr></table>
</body>
</html>
