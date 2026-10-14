<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Документы и печать (erp404_documents): uuid шаблона.
 *
 * Шаблон попал в журнал действий (erp119) позже, чем проектировалась таблица, и
 * журналу нужен устойчивый идентификатор: у документа он есть с самого начала,
 * у шаблона — нет. Идентификатор по номеру строки журнал не устраивает: записи
 * живут дольше, чем «в этот раз шаблон оказался пятым».
 *
 * Миграция отдельная, а не правка первой: первая уже применена на стендах, и
 * менять применённую миграцию — значит получить разные схемы на разных машинах.
 * Здесь — расширение схемы, идемпотентное для обоих случаев (свежая установка и
 * работающий стенд).
 *
 * @package    Erp404
 * @category   Migrations
 */
class pgMigration_003_Erp404_Documents_TemplateUuid extends pgMigration
{
    /**
     * @return  void
     */
    public function up()
    {
        $this->run_query("ALTER TABLE erp_404_templates ADD COLUMN IF NOT EXISTS uuid UUID NOT NULL DEFAULT gen_random_uuid()");

        // Уникальность навешиваем отдельным запросом: ADD COLUMN IF NOT EXISTS не
        // создаёт ограничение, а повторный прогон не должен ругаться.
        $this->run_query("
            DO \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'erp_404_templates_uuid_key'
                ) THEN
                    ALTER TABLE erp_404_templates ADD CONSTRAINT erp_404_templates_uuid_key UNIQUE (uuid);
                END IF;
            END
            \$\$
        ");
    }

    /**
     * @return  void
     */
    public function down()
    {
        $this->run_query("ALTER TABLE erp_404_templates DROP CONSTRAINT IF EXISTS erp_404_templates_uuid_key");
        $this->run_query("ALTER TABLE erp_404_templates DROP COLUMN IF EXISTS uuid");
    }
}
