<?php defined('SYSPATH') or die('No direct script access.');
return array(
    
    'default_model' => 'local::qwen3-coder-30b-a3b-instruct',
    'vision_model'  => 'local::qwen/qwen3.5-9b',
	
	'embedding_model'  => 'local::text-embedding-qwen3-embedding-0.6b',
    
    // 'proxy' => null,
    
    'providers' => array(
        
		'local' => array(
            'api_key'       => getenv('TOOLFORGE_API_KEY'),
            'base_url'      => getenv('TOOLFORGE_API_URL'),
			
			'default_model' => 'qwen3-coder-30b-a3b-instruct', 
			'vision_model' => 'qwen/qwen3.5-9b', 
			
			'models' => array(
              	'qwen/qwen3.5-9b',
				'qwen_qwen3-vl-4b-instruct',
				'openai/gpt-oss-20b',
				'qwen3-coder-30b-a3b-instruct', 
            ),
			
			'timeout'       => 300,
            'params'        => array(
                'temperature' => 0.1,
            ),
        ),
        
        'local_lms' => array(
            'api_key'       => getenv('TOOLFORGE_API_KEY'),
            'base_url'      => getenv('TOOLFORGE_API_URL'),
			'timeout'       => 300,
            'params'        => array(
                'temperature' => 0.1,
            ),
        ),
        
		'openrouter' => array(
          	'api_key'       => getenv('OPENROUTER_API_KEY'),
            'base_url'      => 'https://openrouter.ai/api/v1/',
            'default_model' => 'google/gemini-2.5-flash-lite', // Модель по умолчанию для OpenRouter
			'vision_model' => 'google/gemini-2.5-flash-lite', 
			
			
			'models'		=> array( 
				'google/gemini-2.5-flash-lite',
				'google/gemini-2.5-flash',
				'google/gemini-3-flash-preview',
				'x-ai/grok-4.20',
				'x-ai/grok-build-0.1',
				'openai/gpt-oss-120b',
				'qwen/qwen3.7-plus',
				'qwen/qwen3-coder',
				'qwen/qwen3-coder-30b-a3b-instruct',
				'mistralai/mistral-small-3.2-24b-instruct',
				'openai/gpt-5.1-codex-mini',
				'nvidia/nemotron-3-ultra-550b-a55b:free',
				'deepseek/deepseek-v4-flash',
				
				'tencent/hy3-preview',
				'xiaomi/fp8',
				'deepseek/deepseek-v4-pro',
				'stepfun/step-3.7-flash',
				'google/gemini-3.1-flash-lite',
            ),
			
			
            'timeout'       => 300,
            'params'        => array( // Параметры по умолчанию для всех запросов через этот провайдер
                'temperature' => 0.1,
            ),
        ),

    ),

    // --- Перенаправление моделей ---
    // Позволяет перенаправлять запросы со старых моделей на новые,
    // возможно, даже с изменением провайдера.
    // Ключ - строка 'старый_провайдер::старая_модель', значение 'model'.
    'model_aliases' => array(
        'openrouter::gemini-pro-old' => array( // Пример: старая модель OpenRouter
            'model'    => 'openrouter::google/gemini-1.5-pro-latest',
        ),
        // Добавьте здесь другие алиасы по мере необходимости
    ),
);