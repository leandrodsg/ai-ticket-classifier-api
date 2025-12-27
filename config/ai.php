<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Models
    |--------------------------------------------------------------------------
    |
    | Lista de modelos padrão que serão tentados em ordem. Estes modelos
    | foram testados e validados para classificação de tickets.
    | Atualizar manualmente 1x por mês consultando rankings do OpenRouter.
    | Última atualização: 2025-12-27
    |
    */

    'default_models' => [
        env('AI_MODEL_PRIMARY', 'google/gemini-2.0-flash-exp:free'),
        env('AI_MODEL_SECONDARY', 'qwen/qwen3-coder:free'),
        env('AI_MODEL_TERTIARY', 'meta-llama/llama-3.2-3b-instruct:free'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Discovery Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações para descoberta automática de modelos free quando os
    | modelos padrão falharem. O sistema consultará a API do OpenRouter
    | para encontrar novos modelos gratuitos disponíveis.
    |
    */

    'auto_discovery' => [
        // Habilitar/desabilitar descoberta automática
        'enabled' => env('AI_AUTO_DISCOVERY', true),
        
        // Intervalo de cache (em segundos) - 6 horas
        'cache_ttl' => 21600,
        
        // Número máximo de modelos free a considerar
        'max_models' => 10,
        
        // Filtros de descoberta
        'filters' => [
            // Apenas modelos free
            'free_only' => true,
            
            // Tipo de modelo (chat completion)
            'type' => 'chat',
            
            // Ranking mínimo (0-1000, quanto maior melhor)
            'min_ranking' => 50,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Strategy
    |--------------------------------------------------------------------------
    |
    | Configurações de timeout e retry para o sistema de fallback.
    | Controla quantas tentativas fazer e quanto tempo esperar.
    |
    */

    'fallback' => [
        // Número máximo de retries por modelo
        'max_retries' => 3,
        
        // Timeout por tentativa de modelo (segundos)
        'timeout_per_model' => 10,
        
        // Timeout total para todo o processo (segundos)
        'total_timeout' => 30,
        
        // Usar auto-discovery como último recurso
        'use_discovery_on_failure' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações de cache para modelos descobertos e resultados.
    |
    */

    'cache' => [
        // Chave do cache para modelos descobertos
        'key_prefix' => 'ai_models_',
        
        // Driver de cache (null = usa default da aplicação)
        'driver' => env('AI_CACHE_DRIVER', null),
    ],

];
