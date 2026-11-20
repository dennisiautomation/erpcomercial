@extends('emails.layout')

@section('titulo', 'Novo teste grátis pelo site')

@section('conteudo')
    <h1 style="margin:0 0 8px;font-size:22px;color:#0f172a;">Novo teste grátis pelo site</h1>
    <p style="margin:0 0 16px;font-size:15px;color:#475569;line-height:1.7;">
        Uma empresa se cadastrou sozinha e já está usando o sistema em teste.
    </p>
    <table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;color:#0f172a;line-height:1.9;">
        <tr><td style="color:#64748b;padding-right:14px;">Empresa</td><td><strong>{{ $empresa->nome_fantasia ?: $empresa->razao_social }}</strong></td></tr>
        <tr><td style="color:#64748b;padding-right:14px;">Razão social</td><td>{{ $empresa->razao_social }}</td></tr>
        <tr><td style="color:#64748b;padding-right:14px;">CNPJ</td><td>{{ $empresa->cnpj }}</td></tr>
        <tr><td style="color:#64748b;padding-right:14px;">Cidade</td><td>{{ $empresa->cidade }}/{{ $empresa->uf }}</td></tr>
        <tr><td style="color:#64748b;padding-right:14px;">Plano</td><td>{{ $empresa->planoAssinatura?->nome }}</td></tr>
        <tr><td style="color:#64748b;padding-right:14px;">Teste até</td><td>{{ $empresa->trial_fim?->format('d/m/Y') }}</td></tr>
        <tr><td style="color:#64748b;padding-right:14px;">Dono</td><td>{{ $dono->name }}</td></tr>
        <tr><td style="color:#64748b;padding-right:14px;">E-mail</td><td>{{ $dono->email }}</td></tr>
        <tr><td style="color:#64748b;padding-right:14px;">WhatsApp</td><td>{{ $dono->telefone }}</td></tr>
    </table>
@endsection
