<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PWA — instalar o ERP como aplicativo
    |--------------------------------------------------------------------------
    |
    | Chave geral. Com `PWA_ATIVO=false` no .env:
    |   - o convite de instalação some de todas as telas;
    |   - o <link rel="manifest"> deixa de ser emitido;
    |   - o /sw.js passa a servir um service worker que se DESREGISTRA e limpa
    |     o cache — é assim que se desfaz a instalação já feita nos navegadores
    |     sem esperar deploy de código.
    |
    | Rollback é: PWA_ATIVO=false no .env + docker cp + config:clear.
    |
    */

    'ativo' => (bool) env('PWA_ATIVO', true),

];
