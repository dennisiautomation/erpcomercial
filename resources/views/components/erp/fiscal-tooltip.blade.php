@props(['field'])
@php
// Textos curtos para hover, com redação alinhada à legislação oficial
// (Ajustes SINIEF, Convênios ICMS/CONFAZ, TIPI, NT NF-e).
$tips = [
    'ncm' => 'NCM (Nomenclatura Comum do Mercosul) — 8 dígitos que classificam o produto para tributação. Formato XXXX.XX.XX. Obrigatório desde NT 2016.001.',
    'cest' => 'CEST (Convênio ICMS 142/2018) — 7 dígitos (SS.III.DD). Obrigatório quando o NCM consta nos anexos do convênio, mesmo sem ST na operação.',
    'cfop' => 'CFOP (Ajuste SINIEF 07/2001) — 4 dígitos. 1º dígito: 1=entrada UF, 2=entrada interestadual, 3=importação, 5=saída UF, 6=saída interestadual, 7=exportação.',
    'cst' => 'CST ICMS (regime Normal) — Tabela B (Conv. s/nº 1970). Ex: 00=tributada integralmente, 10=com ST, 60=ICMS-ST já recolhido.',
    'csosn' => 'CSOSN (Ajuste SINIEF 03/2010) — 3 dígitos, só para Simples Nacional. 102=tributada sem crédito (mais comum), 500=ST já recolhida.',
    'icms' => 'ICMS — alíquota interna varia por UF (17% SC/SP/RS/MT/MS → 23% MA). Interestadual: 7% S/SE→N/NE/CO, 12% demais, 4% importados (Res. SF 13/2012).',
    'icms_st' => 'ICMS-ST — usa alíquota interna da UF de destino presumida + MVA para estimar a base. Veja Convênio ICMS 142/2018 e protocolos específicos.',
    'mva' => 'MVA (Margem de Valor Agregado) / IVA-ST — percentual sobre (valor produto + IPI + frete) para estimar a base da ST. Varia por mercadoria e UF.',
    'fcp' => 'FCP (Fundo de Combate à Pobreza) — adicional de 1% a 4% sobre ICMS, aplicado em UFs específicas. Campo separado desde NT 2016.002.',
    'pis' => 'PIS — Lucro Real: 1,65% (não-cumulativo, com créditos). Lucro Presumido: 0,65% (cumulativo). Simples Nacional: recolhido no DAS, não destaca.',
    'cofins' => 'COFINS — Lucro Real: 7,60% (não-cumulativo). Lucro Presumido: 3,00% (cumulativo). Simples Nacional: recolhido no DAS, não destaca.',
    'ipi' => 'IPI — só para indústria ou equiparado (art. 9º RIPI). Alíquotas via TIPI (Dec. 11.158/2022). Comércio puro: CST 53/99 sem destaque.',
    'origem' => 'Origem (Ajuste SINIEF 20/2012) — 0=nacional, 1=importada direta, 2=importada mercado interno, 3-5-8=nacional c/ conteúdo importado, 6-7=importada sem similar.',
    'ibs' => 'IBS (Reforma Tributária EC 132/2023) — substitui ICMS+ISS gradualmente 2026-2033. Alíquota-teste 2026: 0,9%.',
    'cbs' => 'CBS (Reforma Tributária EC 132/2023) — substitui PIS+COFINS. Alíquota-teste 2026: 0,1%.',
    'is' => 'IS (Imposto Seletivo — EC 132/2023) — sobre produtos nocivos à saúde/ambiente (cigarros, álcool, veículos de combustão). Em vigor em 2027.',
    'di' => 'DI (Declaração de Importação, NT 2015.003) — obrigatória quando Origem é 1, 2, 3, 6, 7 ou 8.',
    'csc' => 'CSC (Código de Segurança do Contribuinte) — usado para gerar o QR-Code da NFC-e. Solicitado na SEFAZ estadual (homologação e produção separados).',
    'nfse_padrao' => 'NFS-e — Municipal (prefeitura valida) ou Nacional (Portal RFB via LC 214/2025). Cidades novas já usam o Nacional; as demais estão em migração até 2033.',
    'item_lc116' => 'Item da lista de serviços da Lei Complementar 116/2003 (ISSQN). Ex: 01.01 = análise e desenvolvimento de sistemas; 17.19 = contabilidade.',
    'regime_especial' => 'Regime Especial de tributação municipal (ISS) — concedido pela prefeitura a MEI, estimativa, sociedade de profissionais, ME/EPP, etc.',
];

// Campos que têm popup detalhado adicional (modal completo com tabelas oficiais)
$comModal = ['ncm', 'cest', 'cfop', 'cst', 'csosn', 'icms', 'pis', 'cofins', 'ipi', 'origem', 'ibs', 'cbs', 'is'];

$tip = $tips[$field] ?? null;
$temModal = in_array($field, $comModal, true);
@endphp
@if($tip)
    <i class="bi bi-info-circle text-muted ms-1"
       data-bs-toggle="tooltip"
       data-bs-placement="top"
       data-bs-html="true"
       title="{{ $tip }}@if($temModal)<br><small class='text-warning'>Clique para ver a tabela completa</small>@endif"
       @if($temModal)
           role="button"
           data-fiscal-help="{{ $field }}"
           onclick="window.ErpFiscalHelp?.open('{{ $field }}')"
       @endif
       style="cursor:{{ $temModal ? 'pointer' : 'help' }}"></i>
@endif
