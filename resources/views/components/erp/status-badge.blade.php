@props(['status', 'label' => null])
@php
    $s = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $labels = [
        'ativo' => 'Ativo', 'ativa' => 'Ativa', 'inativo' => 'Inativo', 'inativa' => 'Inativa',
        'pendente' => 'Pendente', 'paga' => 'Paga', 'pago' => 'Pago', 'vencida' => 'Vencida',
        'concluida' => 'Concluída', 'cancelado' => 'Cancelado', 'cancelada' => 'Cancelada',
        'autorizada' => 'Autorizada', 'rejeitada' => 'Rejeitada', 'inutilizada' => 'Inutilizada',
        'rascunho' => 'Rascunho', 'confirmado' => 'Confirmado', 'faturado' => 'Faturado',
        'entregue' => 'Entregue', 'em_aberto' => 'Em Aberto', 'aprovado' => 'Aprovado',
        'recusado' => 'Recusado', 'expirado' => 'Expirado', 'convertido' => 'Convertido',
        'em_implantacao' => 'Em Implantação', 'suspenso' => 'Suspenso', 'bloqueado' => 'Bloqueado',
        'aberto' => 'Aberto', 'fechado' => 'Fechado', 'devolvida' => 'Devolvida',
        'aberta' => 'Aberta', 'em_andamento' => 'Em Andamento', 'aguardando_peca' => 'Aguardando Peça',
        'solicitada' => 'Solicitada', 'em_transito' => 'Em Trânsito',
        'contingencia' => 'Contingência', 'vencido' => 'Vencido',
    ];
    $displayLabel = $label ?? ($labels[$s] ?? ucfirst(str_replace('_', ' ', $s)));
@endphp
<span class="badge-status {{ $s }}">{{ $displayLabel }}</span>
