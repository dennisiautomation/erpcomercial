<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titulo', 'ERP Comercial IA365')</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:'Segoe UI',system-ui,-apple-system,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
                    {{-- Cabeçalho --}}
                    <tr>
                        <td style="background:linear-gradient(135deg,#1e293b,#0f172a);background-color:#0f172a;border-radius:12px 12px 0 0;padding:28px 32px;text-align:center;">
                            <div style="font-size:22px;font-weight:700;color:#ffffff;letter-spacing:-0.5px;">
                                ERP <span style="color:#3b82f6;">Comercial</span>
                            </div>
                            <div style="font-size:12px;color:#94a3b8;margin-top:4px;">by IA365</div>
                        </td>
                    </tr>
                    {{-- Corpo --}}
                    <tr>
                        <td style="background-color:#ffffff;padding:32px;border:1px solid #e2e8f0;border-top:none;">
                            @yield('conteudo')
                        </td>
                    </tr>
                    {{-- Rodapé --}}
                    <tr>
                        <td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 12px 12px;padding:20px 32px;text-align:center;">
                            <p style="margin:0;font-size:12px;color:#94a3b8;line-height:1.6;">
                                Este é um e-mail automático do <strong>ERP Comercial IA365</strong> — não responda a esta mensagem.<br>
                                Dúvidas? Fale com a gente: <a href="mailto:dcanteli@ia365.com.br" style="color:#2563eb;text-decoration:none;">dcanteli@ia365.com.br</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
