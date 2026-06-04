<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Nova solicitação de demonstração</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2330;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f7;padding:28px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 6px 24px rgba(30,20,60,0.10);">

          <!-- Cabeçalho -->
          <tr>
            <td style="background:linear-gradient(135deg,#6d28d9,#4c1d95);padding:26px 32px;color:#ffffff;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="font-size:13px;letter-spacing:.5px;text-transform:uppercase;opacity:.85;">ERP Comercial · IA365</td>
                </tr>
                <tr>
                  <td style="font-size:22px;font-weight:800;padding-top:6px;">📬 Nova solicitação de demonstração</td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Intro -->
          <tr>
            <td style="padding:28px 32px 8px;">
              <p style="margin:0 0 4px;font-size:15px;line-height:1.6;color:#3a3f52;">
                Um novo lead pediu uma demonstração pela landing. Seguem os dados — o cliente está em cópia neste e-mail.
              </p>
            </td>
          </tr>

          <!-- Dados do lead -->
          <tr>
            <td style="padding:8px 32px 4px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #ececf2;border-radius:12px;overflow:hidden;">
                <tr>
                  <td style="padding:14px 18px;background:#faf9fe;border-bottom:1px solid #ececf2;font-size:13px;color:#8b8fa3;width:140px;">Nome</td>
                  <td style="padding:14px 18px;border-bottom:1px solid #ececf2;font-size:15px;font-weight:600;">{{ $lead->nome }}</td>
                </tr>
                <tr>
                  <td style="padding:14px 18px;background:#faf9fe;border-bottom:1px solid #ececf2;font-size:13px;color:#8b8fa3;">Empresa</td>
                  <td style="padding:14px 18px;border-bottom:1px solid #ececf2;font-size:15px;font-weight:600;">{{ $lead->empresa }}</td>
                </tr>
                <tr>
                  <td style="padding:14px 18px;background:#faf9fe;border-bottom:1px solid #ececf2;font-size:13px;color:#8b8fa3;">E-mail</td>
                  <td style="padding:14px 18px;border-bottom:1px solid #ececf2;font-size:15px;">
                    <a href="mailto:{{ $lead->email }}" style="color:#6d28d9;text-decoration:none;font-weight:600;">{{ $lead->email }}</a>
                  </td>
                </tr>
                <tr>
                  <td style="padding:14px 18px;background:#faf9fe;border-bottom:1px solid #ececf2;font-size:13px;color:#8b8fa3;">WhatsApp</td>
                  <td style="padding:14px 18px;border-bottom:1px solid #ececf2;font-size:15px;">
                    @php $wpp = preg_replace('/\D/', '', $lead->whatsapp); @endphp
                    <a href="https://wa.me/55{{ $wpp }}" style="color:#16a34a;text-decoration:none;font-weight:600;">{{ $lead->whatsapp }}</a>
                  </td>
                </tr>
                <tr>
                  <td style="padding:14px 18px;background:#faf9fe;font-size:13px;color:#8b8fa3;">Nº de lojas</td>
                  <td style="padding:14px 18px;font-size:15px;font-weight:600;">{{ $lead->qtd_lojas ?: '—' }}</td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- CTA WhatsApp -->
          <tr>
            <td style="padding:22px 32px 6px;" align="center">
              <a href="https://wa.me/55{{ preg_replace('/\D/', '', $lead->whatsapp) }}"
                 style="display:inline-block;background:#16a34a;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 26px;border-radius:10px;">
                Responder pelo WhatsApp
              </a>
            </td>
          </tr>

          <!-- Rodapé -->
          <tr>
            <td style="padding:22px 32px 28px;">
              <p style="margin:0;font-size:12px;line-height:1.6;color:#9a9eb0;text-align:center;border-top:1px solid #ececf2;padding-top:18px;">
                Recebido em {{ $lead->created_at?->format('d/m/Y H:i') }} · origem: {{ $lead->origem }}<br>
                ERP Comercial IA365 — Alameda Santos, 200, 9º andar, Bela Vista, São Paulo/SP
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
