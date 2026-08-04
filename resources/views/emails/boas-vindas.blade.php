@extends('emails.layout')

@section('titulo', 'Bem-vindo ao ERP Comercial IA365')

@section('conteudo')
    @if($contexto === 'dono')
        <h1 style="margin:0 0 8px;font-size:22px;color:#0f172a;">Seja bem-vindo, {{ $user->name }}! 🎉</h1>
        <p style="margin:0 0 16px;font-size:15px;color:#475569;line-height:1.7;">
            A empresa <strong>{{ $user->empresa?->nome_fantasia ?: $user->empresa?->razao_social }}</strong>
            foi cadastrada no <strong>ERP Comercial IA365</strong> e o seu acesso de
            <strong>proprietário</strong> já está liberado.
        </p>
    @elseif($contexto === 'equipe')
        <h1 style="margin:0 0 8px;font-size:22px;color:#0f172a;">Bem-vindo à equipe, {{ $user->name }}!</h1>
        <p style="margin:0 0 16px;font-size:15px;color:#475569;line-height:1.7;">
            Seu acesso de <strong>administrador da plataforma IA365</strong> no ERP Comercial
            foi criado. Com ele você gerencia empresas, unidades, usuários e planos.
        </p>
    @else
        <h1 style="margin:0 0 8px;font-size:22px;color:#0f172a;">Seja bem-vindo, {{ $user->name }}!</h1>
        <p style="margin:0 0 16px;font-size:15px;color:#475569;line-height:1.7;">
            Você foi cadastrado no <strong>ERP Comercial</strong> da empresa
            <strong>{{ $user->empresa?->nome_fantasia ?: $user->empresa?->razao_social }}</strong>
            com o perfil de <strong>{{ $perfilLabel }}</strong>.
        </p>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 20px;">
        <tr>
            <td style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:18px 20px;">
                <p style="margin:0 0 6px;font-size:13px;color:#64748b;">Seus dados de acesso:</p>
                <p style="margin:0;font-size:15px;color:#0f172a;line-height:1.8;">
                    <strong>Endereço:</strong> <a href="{{ config('app.url') }}/login" style="color:#2563eb;text-decoration:none;">{{ preg_replace('#^https?://#', '', config('app.url')) }}</a><br>
                    <strong>E-mail:</strong> {{ $user->email }}<br>
                    <strong>Senha:</strong> definida por quem realizou o seu cadastro
                </p>
            </td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 20px;">
        <tr>
            <td style="border-radius:8px;background:linear-gradient(135deg,#2563eb,#3b82f6);background-color:#2563eb;">
                <a href="{{ config('app.url') }}/login"
                   style="display:inline-block;padding:13px 36px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;">
                    Acessar o sistema
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0;font-size:13px;color:#94a3b8;line-height:1.7;text-align:center;">
        Quer trocar a senha? Use a opção <strong>“Esqueci minha senha”</strong> na tela de login
        a qualquer momento.
    </p>
@endsection
