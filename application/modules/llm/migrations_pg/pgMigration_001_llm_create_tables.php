<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Migration for PostgreSQL
 */
class pgMigration_001_llm_create_tables extends pgMigration {

    private $tables = [
        'llm_requests',
    ];

    public function up()
    {
        DB::query(Database::UPDATE, "
            CREATE TABLE IF NOT EXISTS llm_requests (
                id SERIAL PRIMARY KEY,
                provider_name VARCHAR(50) NOT NULL,
                model_name VARCHAR(100) NOT NULL,
                request_messages JSONB NOT NULL,
                request_options JSONB NOT NULL,
                response_data JSONB NULL,
                prompt_tokens INT NULL,
                completion_tokens INT NULL,
                total_tokens INT NULL,
                request_timestamp INT NOT NULL,
                response_timestamp INT NULL,
                duration_ms INT NULL,
                status VARCHAR(10) NOT NULL DEFAULT 'success' CHECK (status IN ('success', 'error')),
                error_message TEXT NULL,
                user_id INT NULL,
                session_id VARCHAR(255) NULL
            );
        ")->execute();

        DB::query(Database::UPDATE, "COMMENT ON TABLE llm_requests IS 'Логирование запросов и ответов к LLM';")->execute();
        DB::query(Database::UPDATE, "COMMENT ON COLUMN llm_requests.provider_name IS 'Имя провайдера LLM (e.g., openai, openrouter)';")->execute();
        DB::query(Database::UPDATE, "COMMENT ON COLUMN llm_requests.model_name IS 'Имя модели LLM (e.g., gpt-4o, gpt-5-mini)';")->execute();
        DB::query(Database::UPDATE, "COMMENT ON COLUMN llm_requests.request_messages IS 'Отправленные сообщения в формате JSON';")->execute();
        DB::query(Database::UPDATE, "COMMENT ON COLUMN llm_requests.request_options IS 'Дополнительные опции запроса в формате JSON';")->execute();
        DB::query(Database::UPDATE, "COMMENT ON COLUMN llm_requests.response_data IS 'Полученные данные от LLM в формате JSON';")->execute();
        DB::query(Database::UPDATE, "COMMENT ON COLUMN llm_requests.request_timestamp IS 'UNIX-метка времени отправки запроса';")->execute();
        DB::query(Database::UPDATE, "COMMENT ON COLUMN llm_requests.status IS 'Статус выполнения запроса';")->execute();

        DB::query(Database::UPDATE, "CREATE INDEX idx_llm_provider_model ON llm_requests (provider_name, model_name);")->execute();
        DB::query(Database::UPDATE, "CREATE INDEX idx_llm_request_timestamp ON llm_requests (request_timestamp);")->execute();
        DB::query(Database::UPDATE, "CREATE INDEX idx_llm_user_id ON llm_requests (user_id);")->execute();
    }

    public function down()
    {
        $reversed_tables = array_reverse($this->tables);

        foreach ($reversed_tables as $table)
        {
            DB::query(Database::UPDATE, "DROP TABLE IF EXISTS {$table} CASCADE;")->execute();
        }
    }
}