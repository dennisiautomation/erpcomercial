<?php

/*
| Agente de ajuda dentro do ERP (botão "Ajuda" em todas as telas).
|
| Responde dúvidas com a base de conhecimento (resources/ajuda/*.md), consulta
| SÓ os dados da empresa de quem está logado (leitura) e, quando é problema de
| verdade, abre chamado no app.ia365 (POST /api/public/tickets/agente, §294 de lá).
|
| A IA é trocável pelo .env: qualquer API no formato da OpenAI (/chat/completions
| com tools). DeepSeek é o padrão; OpenAI funciona trocando as 3 linhas.
*/

return [
    // Liga/desliga o botão para todo mundo. Desligado = botão some e as rotas respondem 404.
    'ativo' => (bool) env('AJUDA_ATIVO', false),

    'llm' => [
        'base_url' => rtrim((string) env('AJUDA_LLM_BASE_URL', 'https://api.deepseek.com'), '/'),
        'api_key' => env('AJUDA_LLM_API_KEY', env('DEEPSEEK_API_KEY')),
        'modelo' => env('AJUDA_LLM_MODELO', 'deepseek-chat'),
        'temperatura' => (float) env('AJUDA_LLM_TEMPERATURA', 0.2),
        'max_tokens' => (int) env('AJUDA_LLM_MAX_TOKENS', 1200),
        'timeout' => (int) env('AJUDA_LLM_TIMEOUT', 60),
    ],

    // Abertura de chamado no app.ia365
    'chamado' => [
        'url' => env('AJUDA_CHAMADO_URL', 'https://app.ia365.com.br/api/public/tickets/agente'),
        'token' => env('AJUDA_CHAMADO_TOKEN'),
        // por usuário, além do limite do próprio app.ia365 (3/h por telefone)
        'max_por_dia_usuario' => (int) env('AJUDA_CHAMADO_MAX_DIA', 5),
    ],

    // Limites de uso
    'max_mensagens_hora_usuario' => (int) env('AJUDA_MAX_MENSAGENS_HORA', 60),
    'max_rodadas_ferramenta' => 6,
    'historico_mensagens' => 16, // quantas mensagens anteriores vão para a IA
];
