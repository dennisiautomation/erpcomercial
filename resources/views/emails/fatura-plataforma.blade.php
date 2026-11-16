@extends('emails.layout')

@php
    $valor = 'R$ ' . number_format((float) $fatura->valor, 2, ',', '.');
    $vence = $fatura->vencimento->format('d/m/Y');
    [$titulo, $frase, $corFundo, $corBorda, $corTexto] = match ($tipo) {
        'emitida' => ['Sua fatura está disponível', 'A fatura abaixo já pode ser paga por PIX ou boleto.', '#eff6ff', '#bfdbfe', '#1e40af'],
        'vence' => ['Sua fatura vence em breve', 'Ainda não identificamos o pagamento da fatura abaixo.', '#fffbeb', '#fde68a', '#92400e'],
        'atraso' => ['Sua fatura está em atraso', 'Não identificamos o pagamento da fatura abaixo. Regularize para evitar a suspensão do acesso ao sistema.', '#fef2f2', '#fecaca', '#991b1b'],
        'suspensa' => ['Acesso ao sistema suspenso', 'O acesso ao ERP Comercial foi suspenso porque a fatura abaixo continua em aberto. Assim que o pagamento for confirmado, o acesso volta sozinho.', '#fef2f2', '#fecaca', '#991b1b'],
        default => ['Pagamento confirmado', 'Recebemos o pagamento da fatura abaixo. Obrigado!', '#f0fdf4', '#bbf7d0', '#166534'],
    };
    $botao = $fatura->link ?: $linkFaturas;
@endphp

@section('titulo', $titulo . ' — ERP Comercial IA365')

@section('conteudo')
    <h1 style="margin:0 0 8px;font-size:22px;color:#0f172a;">{{ $titulo }}</h1>
    <p style="margin:0 0 20px;font-size:15px;color:#475569;line-height:1.7;">
        Olá, <strong>{{ $user->name }}</strong>. {{ $frase }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
        <tr>
            <td style="background-color:{{ $corFundo }};border:1px solid {{ $corBorda }};border-radius:8px;padding:16px 18px;">
                <p style="margin:0 0 4px;font-size:13px;color:#64748b;">{{ $empresa?->nome_fantasia ?: $empresa?->razao_social }}</p>
                <p style="margin:0 0 6px;font-size:16px;font-weight:600;color:#0f172a;">{{ $fatura->titulo() }}</p>
                <p style="margin:0;font-size:15px;color:{{ $corTexto }};line-height:1.6;">
                    <strong>{{ $valor }}</strong> ·
                    @if($tipo === 'paga') pago em {{ ($fatura->pago_em ?? now())->format('d/m/Y') }}
                    @elseif($tipo === 'atraso' || $tipo === 'suspensa') venceu em {{ $vence }}
                    @else vence em {{ $vence }}
                    @endif
                </p>
            </td>
        </tr>
    </table>

    @if($tipo !== 'paga')
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 20px;">
            <tr>
                <td style="border-radius:8px;background-color:#2563eb;">
                    <a href="{{ $botao }}" style="display:inline-block;padding:13px 36px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;">
                        Ver fatura e pagar
                    </a>
                </td>
            </tr>
        </table>
        <p style="margin:0 0 14px;font-size:13px;color:#64748b;line-height:1.7;">
            Na página da fatura estão o PIX, o boleto e a nota fiscal. Você também encontra todas as faturas dentro do sistema, no menu <strong>Faturas</strong>:<br>
            <a href="{{ $linkFaturas }}" style="color:#2563eb;word-break:break-all;">{{ $linkFaturas }}</a>
        </p>
        <p style="margin:0;font-size:13px;color:#64748b;line-height:1.7;">Se você já pagou, desconsidere este aviso: a baixa é feita assim que o banco confirma.</p>
    @else
        <p style="margin:0;font-size:13px;color:#64748b;line-height:1.7;">
            O histórico das faturas fica dentro do sistema, no menu <strong>Faturas</strong>.
        </p>
    @endif
@endsection
