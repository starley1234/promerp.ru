<?php defined('SYSPATH') or die('No direct script access.');

/**
 * erp221_crm: схема PostgreSQL (сделки, воронка, лента активностей, ИИ-заметки).
 *
 * Порт модуля с MySQL. Клиентов и контактных лиц модуль не дублирует: они
 * остаются у владельца — «Контрагентов» (erp301). Здесь лежит только то, что
 * принадлежит продажам: стадии воронки, сделки, связи сделки с контактами,
 * лента активностей с историей, ИИ-разбор, база знаний и сырые сообщения.
 * На клиента/контакт CRM ведёт «профиль продаж» (сегмент, отрасль, уровень
 * ЛПР, предпочтительный канал) — это дополнение к чужой записи, а не её копия.
 *
 * Миграция идемпотентна: повторный запуск ничего не ломает.
 */
class pgMigration_001_Erp221_Crm_CreateTables extends pgMigration
{
    public function up()
    {
        // --- Стадии воронки ----------------------------------------------------
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_221_stages(
            id SERIAL PRIMARY KEY,
            code VARCHAR(32) NOT NULL UNIQUE,
            label VARCHAR(120) NOT NULL,
            kind VARCHAR(12) NOT NULL DEFAULT 'open',
            probability SMALLINT NOT NULL DEFAULT 20,
            sort SMALLINT NOT NULL DEFAULT 100,
            color VARCHAR(24),
            requires_loss_reason BOOLEAN NOT NULL DEFAULT FALSE,
            required_fields JSONB NOT NULL DEFAULT '[]'::jsonb,
            actions JSONB NOT NULL DEFAULT '[]'::jsonb,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT erp_221_stage_kind_ck CHECK(kind IN ('open','won','lost')),
            CONSTRAINT erp_221_stage_probability_ck CHECK(probability BETWEEN 0 AND 100)
        )");
        $this->run_query("COMMENT ON TABLE erp_221_stages IS 'Стадии воронки продаж: порядок, вероятность и требования перехода'");

        // --- Причины отказа ----------------------------------------------------
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_221_loss_reasons(
            id SERIAL PRIMARY KEY,
            code VARCHAR(32) NOT NULL UNIQUE,
            label VARCHAR(160) NOT NULL,
            sort SMALLINT NOT NULL DEFAULT 100,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");

        // --- Профиль продаж клиента (дополнение к контрагенту erp301) ----------
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_221_client_profiles(
            counterparty_id INTEGER PRIMARY KEY REFERENCES erp_301_counterparties(id) ON DELETE CASCADE,
            segment VARCHAR(64),
            industry VARCHAR(120),
            annual_revenue NUMERIC(15,2),
            source VARCHAR(64),
            owner_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            ai_profile_notes TEXT,
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_221_client_profile_owner_idx ON erp_221_client_profiles(owner_id)");

        // --- Профиль продаж контактного лица (дополнение к erp301_contacts) ----
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_221_contact_profiles(
            contact_id INTEGER PRIMARY KEY REFERENCES erp_301_contacts(id) ON DELETE CASCADE,
            decision_maker_level VARCHAR(12) NOT NULL DEFAULT 'unknown',
            preferred_channel VARCHAR(16) NOT NULL DEFAULT 'phone',
            telegram_id VARCHAR(100),
            whatsapp_phone VARCHAR(64),
            birthday DATE,
            social_links JSONB NOT NULL DEFAULT '{}'::jsonb,
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT erp_221_contact_lpr_ck CHECK(decision_maker_level IN ('lpr','lvpr','user','unknown')),
            CONSTRAINT erp_221_contact_channel_ck CHECK(preferred_channel IN ('phone','email','telegram','whatsapp'))
        )");

        // --- Сделки -------------------------------------------------------------
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_221_deals(
            id BIGSERIAL PRIMARY KEY,
            uuid UUID NOT NULL DEFAULT gen_random_uuid(),
            number VARCHAR(32) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            counterparty_id INTEGER NOT NULL REFERENCES erp_301_counterparties(id) ON DELETE RESTRICT,
            contact_id INTEGER REFERENCES erp_301_contacts(id) ON DELETE SET NULL,
            stage_id INTEGER NOT NULL REFERENCES erp_221_stages(id) ON DELETE RESTRICT,
            loss_reason_id INTEGER REFERENCES erp_221_loss_reasons(id) ON DELETE SET NULL,
            amount NUMERIC(15,2) NOT NULL DEFAULT 0,
            currency VARCHAR(8) NOT NULL DEFAULT 'RUB',
            probability SMALLINT,
            deadline DATE,
            priority_level VARCHAR(12) NOT NULL DEFAULT 'medium',
            source VARCHAR(64),
            assigned_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
            ai_summary TEXT,
            ai_probability SMALLINT,
            properties JSONB NOT NULL DEFAULT '{}'::jsonb,
            closed_at TIMESTAMPTZ,
            is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
            created_by INTEGER,
            updated_by INTEGER,
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            search_text VARCHAR(1024),
            CONSTRAINT erp_221_deal_number_uq UNIQUE(number),
            CONSTRAINT erp_221_deal_uuid_uq UNIQUE(uuid),
            CONSTRAINT erp_221_deal_probability_ck CHECK(probability IS NULL OR probability BETWEEN 0 AND 100),
            CONSTRAINT erp_221_deal_priority_ck CHECK(priority_level IN ('low','medium','high','critical'))
        )");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_221_deal_cp_idx ON erp_221_deals(counterparty_id,is_deleted)");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_221_deal_stage_idx ON erp_221_deals(stage_id,is_deleted)");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_221_deal_assigned_idx ON erp_221_deals(assigned_to,is_deleted)");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_221_deal_deadline_idx ON erp_221_deals(deadline) WHERE is_deleted IS FALSE");
        $this->run_query("COMMENT ON TABLE erp_221_deals IS 'Сделка: клиент — контрагент erp301, деньги и сроки — здесь, номенклатура и заказы остаются у своих модулей'");

        // --- Участники сделки ---------------------------------------------------
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_221_deal_contacts(
            id SERIAL PRIMARY KEY,
            deal_id BIGINT NOT NULL REFERENCES erp_221_deals(id) ON DELETE CASCADE,
            contact_id INTEGER NOT NULL REFERENCES erp_301_contacts(id) ON DELETE CASCADE,
            role VARCHAR(64),
            is_primary BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT erp_221_deal_contact_uq UNIQUE(deal_id,contact_id)
        )");

        // --- Лента: звонки, письма, встречи, задачи, файлы, системные записи ----
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_221_activities(
            id BIGSERIAL PRIMARY KEY,
            uuid UUID NOT NULL DEFAULT gen_random_uuid(),
            deal_id BIGINT REFERENCES erp_221_deals(id) ON DELETE CASCADE,
            counterparty_id INTEGER REFERENCES erp_301_counterparties(id) ON DELETE CASCADE,
            type VARCHAR(24) NOT NULL DEFAULT 'comment',
            status VARCHAR(16) NOT NULL DEFAULT 'completed',
            subject VARCHAR(255),
            content TEXT,
            storage_file_id INTEGER,
            meta JSONB NOT NULL DEFAULT '{}'::jsonb,
            assigned_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
            scheduled_at TIMESTAMPTZ,
            completed_at TIMESTAMPTZ,
            created_by INTEGER,
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
            CONSTRAINT erp_221_activity_type_ck CHECK(type IN ('comment','call','meeting','email','task','file','system','stage','note')),
            CONSTRAINT erp_221_activity_status_ck CHECK(status IN ('pending','completed','cancelled')),
            CONSTRAINT erp_221_activity_owner_ck CHECK(deal_id IS NOT NULL OR counterparty_id IS NOT NULL)
        )");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_221_activity_deal_idx ON erp_221_activities(deal_id,created_at DESC)");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_221_activity_cp_idx ON erp_221_activities(counterparty_id,created_at DESC)");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_221_activity_plan_idx ON erp_221_activities(scheduled_at) WHERE status = 'pending'");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_221_activity_assigned_idx ON erp_221_activities(assigned_to,status)");

        // --- История изменений активности (снимок целиком: «было — стало») -----
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_221_activities_history(
            id BIGSERIAL PRIMARY KEY,
            activity_id BIGINT NOT NULL REFERENCES erp_221_activities(id) ON DELETE CASCADE,
            changed_by INTEGER,
            changed_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            snapshot JSONB NOT NULL DEFAULT '{}'::jsonb
        )");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_221_activity_history_idx ON erp_221_activities_history(activity_id,changed_at DESC)");

        // --- ИИ-разбор: резюме, вероятность, возражения, следующий шаг ---------
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_221_ai_analysis(
            id BIGSERIAL PRIMARY KEY,
            entity_type VARCHAR(16) NOT NULL,
            entity_id BIGINT NOT NULL,
            insight_type VARCHAR(32) NOT NULL,
            content TEXT NOT NULL,
            confidence NUMERIC(5,4),
            model VARCHAR(120),
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT erp_221_ai_entity_ck CHECK(entity_type IN ('deal','client','contact','activity'))
        )");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_221_ai_entity_idx ON erp_221_ai_analysis(entity_type,entity_id,created_at DESC)");

        // --- База знаний для подсказок менеджеру --------------------------------
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_221_knowledge_base(
            id SERIAL PRIMARY KEY,
            category VARCHAR(64) NOT NULL DEFAULT 'general',
            question_pattern TEXT,
            answer_text TEXT NOT NULL,
            tags TEXT[] NOT NULL DEFAULT '{}'::text[],
            is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_221_kb_search_idx ON erp_221_knowledge_base USING gin(to_tsvector('russian', coalesce(question_pattern,'') || ' ' || answer_text))");

        // --- Сырые сообщения (почта/телеграм/WhatsApp) до разбора --------------
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_221_messages(
            id BIGSERIAL PRIMARY KEY,
            deal_id BIGINT REFERENCES erp_221_deals(id) ON DELETE CASCADE,
            counterparty_id INTEGER REFERENCES erp_301_counterparties(id) ON DELETE CASCADE,
            direction VARCHAR(8) NOT NULL,
            channel VARCHAR(32) NOT NULL,
            external_id VARCHAR(255),
            subject VARCHAR(255),
            text TEXT,
            is_processed_by_ai BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT erp_221_message_direction_ck CHECK(direction IN ('in','out'))
        )");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_221_message_deal_idx ON erp_221_messages(deal_id,created_at DESC)");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_221_message_ai_idx ON erp_221_messages(is_processed_by_ai) WHERE is_processed_by_ai IS FALSE");

        $this->seed_stages();
        $this->seed_loss_reasons();
    }

    /**
     * Откат: сначала связи, потом то, на что они ссылаются.
     *
     * Справочник контрагентов (erp_301_*) модулю не принадлежит и не трогается —
     * профили продаж уходят вместе со своими таблицами.
     */
    public function down()
    {
        $this->run_query("DROP TABLE IF EXISTS erp_221_activities_history");
        $this->run_query("DROP TABLE IF EXISTS erp_221_deal_contacts");
        $this->run_query("DROP TABLE IF EXISTS erp_221_activities");
        $this->run_query("DROP TABLE IF EXISTS erp_221_messages");
        $this->run_query("DROP TABLE IF EXISTS erp_221_ai_analysis");
        $this->run_query("DROP TABLE IF EXISTS erp_221_deals");
        $this->run_query("DROP TABLE IF EXISTS erp_221_client_profiles");
        $this->run_query("DROP TABLE IF EXISTS erp_221_contact_profiles");
        $this->run_query("DROP TABLE IF EXISTS erp_221_knowledge_base");
        $this->run_query("DROP TABLE IF EXISTS erp_221_loss_reasons");
        $this->run_query("DROP TABLE IF EXISTS erp_221_stages");
    }

    /**
     * Стадии воронки по умолчанию: от первого касания до решения.
     *
     * Вероятность — рабочая оценка для прогноза, а не обещание: менеджер может
     * её переопределить в карточке сделки.
     */
    protected function seed_stages()
    {
        $stages = array(
            array('new',         'Новая заявка',         'open', 10,  10, '#64748b', FALSE, array(), array()),
            array('qualify',     'Квалификация',         'open', 25,  20, '#0ea5e9', FALSE, array(), array()),
            array('proposal',    'Коммерческое предложение', 'open', 50, 30, '#6366f1', FALSE, array('amount'), array()),
            array('negotiation', 'Согласование условий', 'open', 75,  40, '#f59e0b', FALSE, array('deadline'), array()),
            array('won',         'Выиграна',             'won',  100, 50, '#10b981', FALSE, array('amount'), array('create_activity')),
            array('lost',        'Проиграна',            'lost', 0,   60, '#ef4444', TRUE, array(), array('create_activity')),
        );

        foreach ($stages as $stage)
        {
            $this->run_query(
                "INSERT INTO erp_221_stages(code,label,kind,probability,sort,color,requires_loss_reason,required_fields,actions)
                 VALUES (
                    '".$stage[0]."',
                    '".$this->literal($stage[1])."',
                    '".$stage[2]."',
                    ". (int) $stage[3] .",
                    ". (int) $stage[4] .",
                    '".$stage[5]."',
                    ". ($stage[6] ? 'TRUE' : 'FALSE') .",
                    '".json_encode($stage[7], JSON_UNESCAPED_UNICODE)."',
                    '".json_encode($stage[8], JSON_UNESCAPED_UNICODE)."'
                 )
                 ON CONFLICT (code) DO NOTHING"
            );
        }
    }

    /**
     * Причины отказа по умолчанию — чтобы отказ не превращался в «просто закрыли».
     */
    protected function seed_loss_reasons()
    {
        $reasons = array(
            array('price',        'Дороже конкурента'),
            array('competitor',   'Выбрали другого поставщика'),
            array('timing',       'Отложили закупку'),
            array('no_budget',    'Нет бюджета'),
            array('no_decision',  'Решение не принято'),
            array('no_contact',   'Потеряли контакт'),
            array('other',        'Другое'),
        );

        foreach ($reasons as $index => $reason)
        {
            $this->run_query(
                "INSERT INTO erp_221_loss_reasons(code,label,sort)
                 VALUES ('".$reason[0]."', '".$this->literal($reason[1])."', ". (10 + $index * 10) .")
                 ON CONFLICT (code) DO NOTHING"
            );
        }
    }

    /**
     * Экранирование одинарной кавычки для строкового литерала SQL.
     *
     * @param   string  $value
     * @return  string
     */
    protected function literal($value)
    {
        return str_replace("'", "''", (string) $value);
    }
}
