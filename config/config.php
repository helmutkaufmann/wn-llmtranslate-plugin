<?php

return [
    /*
    | Endpoint seeded when nothing has been configured in the backend yet.
    | Defaults to a local OpenAI-compatible server (Ollama's address; LM Studio
    | is http://localhost:1234/v1, llama.cpp http://localhost:8080/v1).
    */
    'default_endpoint' => [
        'name'     => 'local',
        'format'   => 'openai',
        'base_url' => env('LLM_TRANSLATE_BASE_URL', 'http://localhost:11434/v1'),
        'model'    => env('LLM_TRANSLATE_MODEL', 'llama3.1'),
        'api_key'  => env('LLM_TRANSLATE_API_KEY', ''),
    ],

    // Strings sent to the model per request (small batches suit local models).
    'batch_size' => 20,

    // Request timeout in seconds (local models can be slow).
    'timeout' => 120,

    // Max messages translated per click in Settings -> Translate messages.
    'max_per_run' => 200,
];
