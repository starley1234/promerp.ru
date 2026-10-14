<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Документы и печать (erp404_documents): таблицы.
 *
 * Три таблицы, которые повторяют путь бумаги — от шаблона до подшивки:
 *
 *   erp_404_templates   шаблон печатной формы: HTML с подстановками
 *                       `{{ref.number}}`, привязан к типу объекта;
 *   erp_404_documents   выпущенный документ: номер, шаблон и его версия,
 *                       данные подстановки (payload), снимок HTML (rendered)
 *                       и состояние (черновик → выпущен → напечатан → в архиве);
 *   erp_404_prints      журнал печати: кто, когда и сколько копий напечатал.
 *
 * Почему снимок, а не «печатаем из шаблона каждый раз»: шаблон правят, и без
 * снимка вчерашняя накладная печаталась бы сегодняшним текстом. Поэтому
 * документ хранит и версию шаблона, и готовый HTML — печать не зависит от
 * того, что стало с шаблоном позже.
 *
 * Вложений здесь нет намеренно: файлы живут в `erp212_file_storage`, который
 * уже умеет связывать файл с любым объектом (`erp_212_file_links`). Дублировать
 * хранилище модулем печати — верный способ развести две правды о файле.
 *
 * Ссылки на чужие модули (заказ erp403, производственный заказ erp402, контроль
 * качества erp405, операция erp408, контрагент erp301) — мягкие, без внешних
 * ключей: `ref_type` + `ref_id`. Модули включаются и выключаются независимо, а
 * выпущенные документы остаются читаемыми.
 *
 * Флаги — SMALLINT 0/1, а не BOOLEAN: PostgreSQL отдаёт boolean строками
 * `t`/`f`, и `(bool) 'f'` — это TRUE (см. AGENTS.md).
 *
 * @package    Erp404
 * @category   Migrations
 */
class pgMigration_001_Erp404_Documents_CreateTables extends pgMigration
{
    /**
     * @return  void
     */
    public function up()
    {
        $this->_create_templates();
        $this->_create_documents();
        $this->_create_prints();

        $this->run_query("CREATE SEQUENCE IF NOT EXISTS erp_404_document_number_seq START 1");
    }

    /**
     * @return  void
     */
    public function down()
    {
        $this->run_query('DROP TABLE IF EXISTS erp_404_prints CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_404_documents CASCADE');
        $this->run_query('DROP TABLE IF EXISTS erp_404_templates CASCADE');
        $this->run_query('DROP SEQUENCE IF EXISTS erp_404_document_number_seq');
    }

    /**
     * Шаблоны печатных форм.
     *
     * `ref_type` — тип объекта, для которого шаблон предназначен (пустая
     * строка — универсальный). `body` — HTML с подстановками; `version`
     * увеличивается при правке тела, а выпущенные документы запоминают ту
     * версию, по которой их напечатали.
     *
     * @return  void
     */
    protected function _create_templates()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_404_templates (
                id              SERIAL PRIMARY KEY,
                code            VARCHAR(64)  NOT NULL,
                name            VARCHAR(255) NOT NULL,
                ref_type        VARCHAR(32)  NOT NULL DEFAULT '',
                kind            VARCHAR(16)  NOT NULL DEFAULT 'print',
                body            TEXT         NOT NULL,
                version         INTEGER      NOT NULL DEFAULT 1,
                is_active       SMALLINT     NOT NULL DEFAULT 1,
                note            TEXT         NULL,
                search_text     VARCHAR(512) NULL,
                created_by      INTEGER      NULL,
                created_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_404_templates_code_key UNIQUE (code),
                CONSTRAINT erp_404_templates_kind_check CHECK (kind IN ('print', 'label', 'act')),
                CONSTRAINT erp_404_templates_version_check CHECK (version >= 1),
                CONSTRAINT erp_404_templates_active_check CHECK (is_active IN (0, 1))
            )
        ");

        $this->run_query("COMMENT ON TABLE erp_404_templates IS 'Шаблоны печатных форм: HTML с подстановками (erp404_documents)'");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_404_templates_ref_type ON erp_404_templates (ref_type, is_active)");
    }

    /**
     * Выпущенные документы.
     *
     * `ref_id` — строкой: у соседних модулей идентификатор бывает числовым
     * (`43`) или uuid, а ссылка мягкая, и приводить её к одному типу нельзя.
     * `payload` — данные подстановки на момент выпуска (JSONB), `rendered` —
     * готовый HTML. Оба нужны: по payload документ можно пересобрать новым
     * шаблоном, по rendered — напечатать ровно то, что выпустили.
     *
     * @return  void
     */
    protected function _create_documents()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_404_documents (
                id                  SERIAL PRIMARY KEY,
                uuid                UUID         NOT NULL DEFAULT gen_random_uuid(),
                number              VARCHAR(32)  NOT NULL,
                template_id         INTEGER      NULL,
                template_code       VARCHAR(64)  NULL,
                template_version    INTEGER      NULL,
                ref_type            VARCHAR(32)  NOT NULL DEFAULT '',
                ref_id              VARCHAR(64)  NULL,
                title               VARCHAR(255) NOT NULL,
                status              VARCHAR(16)  NOT NULL DEFAULT 'draft',
                payload             JSONB        NULL DEFAULT '{}'::jsonb,
                rendered            TEXT         NULL,
                print_count         INTEGER      NOT NULL DEFAULT 0,
                issued_at           TIMESTAMP WITH TIME ZONE NULL,
                printed_at          TIMESTAMP WITH TIME ZONE NULL,
                archived_at         TIMESTAMP WITH TIME ZONE NULL,
                cancelled_at        TIMESTAMP WITH TIME ZONE NULL,
                created_by          INTEGER      NULL,
                note                TEXT         NULL,
                search_text         VARCHAR(1024) NULL,
                created_at          TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at          TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_404_documents_number_key UNIQUE (number),
                CONSTRAINT erp_404_documents_uuid_key UNIQUE (uuid),
                CONSTRAINT erp_404_documents_status_check
                    CHECK (status IN ('draft', 'issued', 'printed', 'archived', 'cancelled')),
                CONSTRAINT erp_404_documents_print_count_check CHECK (print_count >= 0),
                CONSTRAINT erp_404_documents_template_version_check
                    CHECK (template_version IS NULL OR template_version >= 1)
            )
        ");

        $this->run_query("COMMENT ON TABLE erp_404_documents IS 'Выпущенные документы: шаблон, данные и снимок печати (erp404_documents)'");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_404_documents_ref ON erp_404_documents (ref_type, ref_id)");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_404_documents_status ON erp_404_documents (status, created_at)");
    }

    /**
     * Журнал печати.
     *
     * Печать — событие, а не поле: по журналу видно, кто и когда выводил
     * документ на бумагу и сколько копий ушло. `print_count` в документе —
     * быстрый счётчик для списков, истина — здесь.
     *
     * @return  void
     */
    protected function _create_prints()
    {
        $this->run_query("
            CREATE TABLE IF NOT EXISTS erp_404_prints (
                id              SERIAL PRIMARY KEY,
                document_id     INTEGER      NOT NULL,
                printed_by      INTEGER      NULL,
                copies          INTEGER      NOT NULL DEFAULT 1,
                note            TEXT         NULL,
                printed_at      TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                CONSTRAINT erp_404_prints_document_fkey
                    FOREIGN KEY (document_id) REFERENCES erp_404_documents (id) ON DELETE CASCADE,
                CONSTRAINT erp_404_prints_copies_check CHECK (copies >= 1)
            )
        ");

        $this->run_query("COMMENT ON TABLE erp_404_prints IS 'Журнал печати документов (erp404_documents)'");
        $this->run_query("CREATE INDEX IF NOT EXISTS idx_404_prints_document ON erp_404_prints (document_id, printed_at)");
    }
}
