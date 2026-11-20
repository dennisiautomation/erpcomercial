@extends('emails.layout')

@section('titulo', 'Confirme seu e-mail — ERP Comercial IA365')

@section('conteudo')
    <h1 style="margin:0 0 8px;font-size:22px;color:#0f172a;">Falta só um clique</h1>
    <p style="margin:0 0 20px;font-size:15px;color:#475569;line-height:1.7;">
        Olá, <strong>{{ $nome }}</strong>. Recebemos o seu pedido de teste grátis do ERP Comercial
        no plano <strong>{{ $plano->nome === 'Basico' ? 'Básico' : $plano->nome }}</strong>
        ({{ (int) ($plano->dias_trial ?: 14) }} dias). Confirme o seu e-mail para a conta ser criada:
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 20px;">
        <tr>
            <td style="border-radius:8px;background-color:#2563eb;">
                <a href="{{ $link }}"
                   style="display:inline-block;padding:13px 36px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;">
                    Confirmar e entrar
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 14px;font-size:13px;color:#64748b;line-height:1.7;">
        Se o botão não funcionar, copie e cole este endereço no navegador:<br>
        <a href="{{ $link }}" style="color:#2563eb;word-break:break-all;">{{ $link }}</a>
    </p>
    <p style="margin:0;font-size:13px;color:#64748b;line-height:1.7;">
        O link vale por {{ \App\Services\Plataforma\ContratacaoSite::VALIDADE_HORAS }} horas. Se não foi você quem pediu, é só ignorar este e-mail: nenhuma conta é criada sem a confirmação.
    </p>
@endsection
