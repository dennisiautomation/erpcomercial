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
    'hosts_erp' => array_filter(array_map('trim', explode(',', (string) env('LOJA_ONLINE_HOSTS_ERP', 'localhost,127.0.0.1')))),
];
