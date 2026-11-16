<?php

/*
 | Loja online (e-commerce) — 14/11/2026.
 |
 | dominio_base: quando preenchido (ex.: "loja.ia365.com.br"), cada loja também
 | responde em {slug}.{dominio_base}. Vazio = só /loja/{slug} e domínio próprio.
 | Chave nova no .env entra por docker cp + config:cache + USR2 (armadilha 80).
 |
 | hosts_erp: hosts que NUNCA são loja (além do host do APP_URL).
 */
return [
    'dominio_base' => env('LOJA_ONLINE_DOMINIO_BASE', ''),
    // 16/11/2026 — domínio próprio simples: o que a tela "Domínio" manda o lojista apontar.
    // ip_servidor vazio = o IP para onde o endereço do ERP (APP_URL) resolve; cname_alvo vazio = o host do APP_URL.
    'ip_servidor' => env('LOJA_ONLINE_IP', ''),
    'cname_alvo' => env('LOJA_ONLINE_CNAME', ''),
    // true só onde a rotina do servidor (scripts/loja-dominios-host.sh) está instalada: muda o texto para "sai sozinho".
    'certificado_automatico' => (bool) env('LOJA_ONLINE_CERTIFICADO_AUTOMATICO', false),
    'hosts_erp' => array_filter(array_map('trim', explode(',', (string) env('LOJA_ONLINE_HOSTS_ERP', 'localhost,127.0.0.1')))),
];
