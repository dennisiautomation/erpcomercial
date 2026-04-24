<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Focus NFe — revenda white-label
    |
    | master_token = Token Principal de Produção da conta Focus NFe.
    |   Usado apenas para:
    |     - Criar/atualizar empresas-filhas via POST/PUT /v2/empresas
    |     - APIs acessórias (NCM, CFOP, CEP, CNAE, municípios, CNPJ)
    |
    | Este token NÃO deve ser usado para emitir notas fiscais.
    | Emissão sempre usa o token específico da empresa (focus_token_producao
    | ou focus_token_homologacao) obtido na criação da empresa-filha.
    */
    'focus_nfe' => [
        'master_token'     => env('FOCUS_MASTER_TOKEN'),
        'ambiente_padrao'  => env('FOCUS_NFE_AMBIENTE', 'homologacao'),
        'webhook_base_url' => env('FOCUS_WEBHOOK_BASE_URL', env('APP_URL')),
    ],

];
