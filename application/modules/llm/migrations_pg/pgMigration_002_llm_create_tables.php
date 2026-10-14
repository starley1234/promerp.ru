<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Миграция для создания таблицы шаблонов промптов (PostgreSQL)
 */
class pgMigration_002_llm_create_tables extends pgMigration {

    private $tables = [
        'llm_prompts',
    ];

    public function up()
    {
        // 1. llm_prompts
        DB::query(Database::UPDATE, "
            CREATE TABLE IF NOT EXISTS llm_prompts (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description VARCHAR(255) NULL,
                system TEXT NULL,
                prompt TEXT NOT NULL,
                provider_name VARCHAR(50) NULL,
                model_name VARCHAR(100) NULL,
                options JSONB NULL,
                developer_notes TEXT NULL,
                created_at INT NOT NULL,
                updated_at INT NOT NULL
            );
        ")->execute();

        DB::query(Database::UPDATE, "CREATE UNIQUE INDEX IF NOT EXISTS idx_llm_prompt_name ON llm_prompts (name);")->execute();

        // Комментарии в PostgreSQL задаются отдельными командами
        DB::query(Database::UPDATE, "COMMENT ON TABLE llm_prompts IS 'Справочник шаблонов промптов для LLM';")->execute();
        DB::query(Database::UPDATE, "COMMENT ON COLUMN llm_prompts.name IS 'Уникальное имя шаблона для вызова в коде';")->execute();
        DB::query(Database::UPDATE, "COMMENT ON COLUMN llm_prompts.description IS 'Описание назначения шаблона';")->execute();
        DB::query(Database::UPDATE, "COMMENT ON COLUMN llm_prompts.system IS 'Системный промпт (инструкция роли)';")->execute();
        DB::query(Database::UPDATE, "COMMENT ON COLUMN llm_prompts.prompt IS 'Текст основного промпта с переменными {{var}}';")->execute();
        DB::query(Database::UPDATE, "COMMENT ON COLUMN llm_prompts.provider_name IS 'Принудительный провайдер для этого шаблона';")->execute();
        DB::query(Database::UPDATE, "COMMENT ON COLUMN llm_prompts.model_name IS 'Принудительная модель для этого шаблона';")->execute();
        DB::query(Database::UPDATE, "COMMENT ON COLUMN llm_prompts.options IS 'Дополнительные параметры (temperature, response_format и т.д.)';")->execute();
        DB::query(Database::UPDATE, "COMMENT ON COLUMN llm_prompts.developer_notes IS 'Технические примечания разработчика';")->execute();

        // 2. Вставка базовых шаблонов
        $now = time();
        $default_prompts = [
            [
                'name' => 'email_notify',
                'description' => 'Приветственное письмо после активации',
                'system' => 'Вы — профессиональный менеджер по работе с клиентами. Пишите вежливо и лаконично.',
                'prompt' => 'Напиши приветственное письмо для пользователя по имени {{user}}. Сообщи ему, что его аккаунт успешно активирован и он может приступать к работе в системе.',
                'developer_notes' => 'Используется в модуле регистрации. Переменные: {{user}}',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'text_summarize',
                'description' => 'Саммари текста',
                'system' => 'Ты помощник, который делает краткие выжимки из длинных текстов.',
                'prompt' => 'Выдели основные тезисы из следующего текста: {{text}}',
                'developer_notes' => 'Общий шаблон для сокращения контента. Переменные: {{text}}',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        ];

        foreach ($default_prompts as $data) {
            DB::insert('llm_prompts', array_keys($data))
                ->values(array_values($data))
                ->execute();
        }
    }

    public function down()
    {
        foreach ($this->tables as $table)
        {
            DB::query(Database::UPDATE, "DROP TABLE IF EXISTS {$table} CASCADE;")->execute();
        }
    }
}