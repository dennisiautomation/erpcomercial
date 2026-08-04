@extends('emails.layout')

@section('titulo', 'Redefinição de senha — ERP Comercial IA365')

@section('conteudo')
    <h1 style="margin:0 0 8px;font-size:22px;color:#0f172a;">Redefinição de senha</h1>
    <p style="margin:0 0 20px;font-size:15px;color:#475569;line-height:1.7;">
        Olá, <strong>{{ $user->name }}</strong>. Recebemos um pedido para redefinir a senha da sua
        conta no ERP Comercial. Clique no botão abaixo para criar uma nova senha:
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 20px;">
        <tr>
            <td style="border-radius:8px;background:linear-gradient(135deg,#2563eb,#3b82f6);background-color:#2563eb;">
                <a href="{{ $link }}"
                   style="display:inline-block;padding:13px 36px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;">
                    Redefinir minha senha
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 14px;font-size:13px;color:#64748b;line-height:1.7;">
        Se o botão não funcionar, copie e cole este endereço no navegador:<br>
        <a href="{{ $link }}" style="color:#2563eb;word-break:break-all;">{{ $link }}</a>
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="background-color:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;">
                <p style="margin:0;font-size:13px;color:#92400e;line-height:1.6;">
                    ⏱️ Este link é válido por <strong>60 minutos</strong>. Se você não pediu a
                    redefinição, ignore este e-mail — sua senha continua a mesma.
                </p>
            </td>
        </tr>
    </table>
@endsection
