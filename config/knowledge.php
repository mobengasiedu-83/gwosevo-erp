<?php

return [
    'ai_enabled' => env('KNOWLEDGE_AI_ENABLED', false),
    'ai_base_url' => env('KNOWLEDGE_AI_BASE_URL', 'https://api.openai.com/v1'),
    'ai_api_key' => env('KNOWLEDGE_AI_API_KEY'),
    'ai_model' => env('KNOWLEDGE_AI_MODEL', 'gpt-4o-mini'),
    'ai_timeout' => env('KNOWLEDGE_AI_TIMEOUT', 30),
    'semantic_enabled' => env('KNOWLEDGE_SEMANTIC_ENABLED', false),
    'embedding_model' => env('KNOWLEDGE_EMBEDDING_MODEL', 'text-embedding-3-small'),
    'embedding_dimensions' => env('KNOWLEDGE_EMBEDDING_DIMENSIONS', 1536),
    'qdrant_url' => env('KNOWLEDGE_QDRANT_URL'),
    'qdrant_api_key' => env('KNOWLEDGE_QDRANT_API_KEY'),
    'qdrant_collection' => env('KNOWLEDGE_QDRANT_COLLECTION', 'erp_knowledge'),
    'graph_tenant_id' => env('KNOWLEDGE_GRAPH_TENANT_ID'),
    'graph_client_id' => env('KNOWLEDGE_GRAPH_CLIENT_ID'),
    'graph_client_secret' => env('KNOWLEDGE_GRAPH_CLIENT_SECRET'),
];
