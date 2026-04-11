@props(['field'])
@php
$tips = [
    'ncm' => 'NCM (Nomenclatura Comum do Mercosul) — código de 8 dígitos que classifica seu produto para fins fiscais. Exemplo: 61091000 = Camisetas de algodão. Pergunte ao seu contador se não souber.',
    'cest' => 'CEST (Código Especificador da Substituição Tributária) — obrigatório se o produto tem ST. Formato: 00.000.00.',
    'cfop' => 'CFOP (Código Fiscal de Operações) — identifica o tipo de operação. 5102 = venda de mercadoria dentro do estado. 6102 = venda interestadual.',
    'cst' => 'CST (Código de Situação Tributária) — define como o ICMS é cobrado. 00 = tributação integral. Usado no Lucro Presumido e Real.',
    'csosn' => 'CSOSN (Código de Situação da Operação no Simples Nacional) — similar ao CST mas para empresas do Simples. 102 = tributação sem crédito.',
    'icms' => 'ICMS — Imposto sobre Circulação de Mercadorias. Alíquota varia por estado (SP = 18%). No Simples Nacional, geralmente não se destaca.',
    'pis' => 'PIS — Contribuição social. Lucro Presumido: 0,65%. Lucro Real: 1,65%. Simples Nacional: não destaca.',
    'cofins' => 'COFINS — Contribuição social. Lucro Presumido: 3,0%. Lucro Real: 7,60%. Simples Nacional: não destaca.',
    'ipi' => 'IPI — Imposto sobre Produtos Industrializados. Apenas para indústrias. Comércio geralmente não tem IPI.',
    'origem' => 'Origem da mercadoria. 0 = Nacional (fabricado no Brasil). 1 = Estrangeira (importação direta).',
];
@endphp
<i class="bi bi-question-circle text-muted ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $tips[$field] ?? '' }}" style="cursor:help"></i>
