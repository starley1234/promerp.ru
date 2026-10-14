<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Erp199 SQR: схема QR-маркировки и мобильного терминала (PostgreSQL).
 *
 * Порт с MySQL (файл migrations/Migration_erp_199_001_create_tables.php).
 * Отличия от исходной схемы — осознанные:
 *
 *  * наклейка (`erp_199_tokens`) ссылается на **цель** любого модуля-владельца
 *    (`target_kind` + `target_id`/`target_uuid`), а не только на локальный
 *    объект: QR вешается на оборудование erp227, партию erp412, заказ erp402,
 *    сотрудника erp310, номенклатуру склада — данные остаются у владельца;
 *  * `erp_199_objects` остались, но это только «свободные» объекты, заведённые
 *    с телефона (то, для чего у системы нет владельца);
 *  * добавлены сценарии терминала (`erp_199_scenarios`) — сервер описывает
 *    страницы для тонкого клиента данными, а не кодом;
 *  * добавлены сессии инвентаризации (`erp_199_inventories` и её позиции).
 *
 * Миграция идемпотентна: повторный запуск ничего не ломает.
 */
class pgMigration_001_Erp199_Sqr_CreateTables extends pgMigration
{
    public function up()
    {
        // --- Типы целей и категории локальных объектов -----------------------
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_199_types(
            id SERIAL PRIMARY KEY,
            code VARCHAR(32) NOT NULL UNIQUE,
            label VARCHAR(120) NOT NULL,
            adapter VARCHAR(24) NOT NULL DEFAULT 'local',
            icon VARCHAR(48),
            hint TEXT,
            ai_prompt_specs TEXT,
            expected_attributes JSONB NOT NULL DEFAULT '[]'::jsonb,
            allow_local BOOLEAN NOT NULL DEFAULT FALSE,
            sort SMALLINT NOT NULL DEFAULT 100,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT erp_199_type_adapter_ck CHECK(adapter IN ('local','registry','item','lot','order','employee'))
        )");
        $this->run_query("COMMENT ON TABLE erp_199_types IS 'Типы целей QR-наклеек: что именно маркируем и каким адаптером читаем'");

        // --- Локальные объекты (нет владельца в других модулях) ---------------
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_199_objects(
            id BIGSERIAL PRIMARY KEY,
            uuid UUID NOT NULL DEFAULT gen_random_uuid(),
            type_id INTEGER REFERENCES erp_199_types(id) ON DELETE SET NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'draft',
            source VARCHAR(16) NOT NULL DEFAULT 'manual',
            name VARCHAR(255) NOT NULL,
            brand VARCHAR(120),
            model VARCHAR(120),
            serial_number VARCHAR(120),
            description TEXT,
            attributes JSONB NOT NULL DEFAULT '{}'::jsonb,
            specs JSONB NOT NULL DEFAULT '{}'::jsonb,
            ai_confidence NUMERIC(5,2),
            ai_raw JSONB,
            location_id BIGINT REFERENCES erp_199_objects(id) ON DELETE SET NULL,
            base_lat NUMERIC(10,7),
            base_lon NUMERIC(11,7),
            moderator_comment TEXT,
            created_by INTEGER,
            updated_by INTEGER,
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
            CONSTRAINT erp_199_object_status_ck CHECK(status IN ('draft','active','rejected','archived')),
            CONSTRAINT erp_199_object_source_ck CHECK(source IN ('manual','ai','scan','import')),
            CONSTRAINT erp_199_object_uuid_uq UNIQUE(uuid)
        )");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_199_object_type_idx ON erp_199_objects(type_id,status)");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_199_object_serial_idx ON erp_199_objects(serial_number) WHERE serial_number IS NOT NULL");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_199_object_name_idx ON erp_199_objects(name)");
        $this->run_query("COMMENT ON TABLE erp_199_objects IS 'Свободные объекты, заведённые с терминала: у системы нет модуля-владельца'");

        $this->run_query("CREATE TABLE IF NOT EXISTS erp_199_objects_history(
            id BIGSERIAL PRIMARY KEY,
            object_id BIGINT NOT NULL REFERENCES erp_199_objects(id) ON DELETE CASCADE,
            user_id INTEGER,
            change_type VARCHAR(16) NOT NULL DEFAULT 'update',
            changed_data JSONB NOT NULL DEFAULT '{}'::jsonb,
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT erp_199_history_type_ck CHECK(change_type IN ('create','update','ai_refinement','moderation'))
        )");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_199_history_object_idx ON erp_199_objects_history(object_id,created_at DESC)");

        // --- Наклейки (QR-коды) ----------------------------------------------
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_199_tokens(
            id BIGSERIAL PRIMARY KEY,
            uuid UUID NOT NULL DEFAULT gen_random_uuid(),
            code VARCHAR(64) NOT NULL,
            batch VARCHAR(32),
            status VARCHAR(16) NOT NULL DEFAULT 'new',
            target_kind VARCHAR(24),
            target_id BIGINT,
            target_uuid UUID,
            target_label VARCHAR(255),
            object_id BIGINT REFERENCES erp_199_objects(id) ON DELETE SET NULL,
            label_note VARCHAR(255),
            issued_by INTEGER,
            issued_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            bound_by INTEGER,
            bound_at TIMESTAMPTZ,
            printed_at TIMESTAMPTZ,
            print_count INTEGER NOT NULL DEFAULT 0,
            scans_count INTEGER NOT NULL DEFAULT 0,
            last_scan_at TIMESTAMPTZ,
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT erp_199_token_code_uq UNIQUE(code),
            CONSTRAINT erp_199_token_uuid_uq UNIQUE(uuid),
            CONSTRAINT erp_199_token_status_ck CHECK(status IN ('new','active','blocked','damaged','lost','archived')),
            CONSTRAINT erp_199_token_kind_ck CHECK(target_kind IS NULL OR target_kind IN ('local','registry','item','lot','order','employee')),
            -- Активная наклейка обязана иметь цель: «активна, но ни на что не указывает» — дефект данных.
            CONSTRAINT erp_199_token_active_ck CHECK(status <> 'active' OR target_kind IS NOT NULL),
            CONSTRAINT erp_199_token_local_ck CHECK(target_kind IS DISTINCT FROM 'local' OR object_id IS NOT NULL)
        )");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_199_token_status_idx ON erp_199_tokens(status,batch)");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_199_token_target_idx ON erp_199_tokens(target_kind,target_id)");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_199_token_object_idx ON erp_199_tokens(object_id)");
        $this->run_query("COMMENT ON TABLE erp_199_tokens IS 'Наклейки с QR: код печатается заранее, цель привязывается при первом скане'");

        // --- Медиа (фото с телефона) ------------------------------------------
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_199_media(
            id BIGSERIAL PRIMARY KEY,
            uuid UUID NOT NULL DEFAULT gen_random_uuid(),
            token_id BIGINT REFERENCES erp_199_tokens(id) ON DELETE SET NULL,
            object_id BIGINT REFERENCES erp_199_objects(id) ON DELETE SET NULL,
            purpose VARCHAR(16) NOT NULL DEFAULT 'object',
            file_path VARCHAR(255) NOT NULL,
            file_name VARCHAR(160),
            mime VARCHAR(64),
            size_bytes INTEGER,
            sha256 CHAR(64),
            geo_lat NUMERIC(10,7),
            geo_lon NUMERIC(11,7),
            uploaded_by INTEGER,
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT erp_199_media_uuid_uq UNIQUE(uuid),
            CONSTRAINT erp_199_media_purpose_ck CHECK(purpose IN ('object','plate','scan','evidence','inventory'))
        )");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_199_media_token_idx ON erp_199_media(token_id,created_at DESC)");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_199_media_object_idx ON erp_199_media(object_id,created_at DESC)");

        // --- Журнал сканов и действий ----------------------------------------
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_199_activity(
            id BIGSERIAL PRIMARY KEY,
            uuid UUID NOT NULL DEFAULT gen_random_uuid(),
            token_id BIGINT REFERENCES erp_199_tokens(id) ON DELETE SET NULL,
            token_code VARCHAR(64),
            object_id BIGINT,
            user_id INTEGER,
            key_id BIGINT,
            action_type VARCHAR(24) NOT NULL,
            scenario_code VARCHAR(48),
            result VARCHAR(24),
            payload JSONB NOT NULL DEFAULT '{}'::jsonb,
            comment TEXT,
            geo_lat NUMERIC(10,7),
            geo_lon NUMERIC(11,7),
            ip VARCHAR(45),
            device VARCHAR(160),
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT erp_199_activity_uuid_uq UNIQUE(uuid)
        )");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_199_activity_created_idx ON erp_199_activity(created_at DESC)");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_199_activity_token_idx ON erp_199_activity(token_id,created_at DESC)");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_199_activity_user_idx ON erp_199_activity(user_id,created_at DESC)");
        $this->run_query("COMMENT ON TABLE erp_199_activity IS 'Журнал: кто, чем, когда и где сканировал наклейку и что сделал'");

        // --- Сценарии терминала (страницы для тонкого клиента) ----------------
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_199_scenarios(
            id SERIAL PRIMARY KEY,
            code VARCHAR(48) NOT NULL UNIQUE,
            label VARCHAR(120) NOT NULL,
            description TEXT,
            icon VARCHAR(48),
            target_kinds JSONB NOT NULL DEFAULT '[]'::jsonb,
            requires_photo BOOLEAN NOT NULL DEFAULT FALSE,
            requires_comment BOOLEAN NOT NULL DEFAULT FALSE,
            allow_multiple BOOLEAN NOT NULL DEFAULT TRUE,
            form JSONB NOT NULL DEFAULT '{\"fields\":[]}'::jsonb,
            effect JSONB NOT NULL DEFAULT '{\"kind\":\"record\"}'::jsonb,
            result_note VARCHAR(255),
            sort SMALLINT NOT NULL DEFAULT 100,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $this->run_query("COMMENT ON TABLE erp_199_scenarios IS 'Сценарии мобильного терминала: форму и эффект описывает сервер, приложение их рисует'");

        // --- Инвентаризация ---------------------------------------------------
        $this->run_query("CREATE TABLE IF NOT EXISTS erp_199_inventories(
            id BIGSERIAL PRIMARY KEY,
            uuid UUID NOT NULL DEFAULT gen_random_uuid(),
            code VARCHAR(32) NOT NULL UNIQUE,
            label VARCHAR(160) NOT NULL,
            scope JSONB NOT NULL DEFAULT '{}'::jsonb,
            status VARCHAR(12) NOT NULL DEFAULT 'open',
            comment TEXT,
            stats JSONB NOT NULL DEFAULT '{}'::jsonb,
            started_by INTEGER,
            started_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            closed_by INTEGER,
            closed_at TIMESTAMPTZ,
            CONSTRAINT erp_199_inventory_uuid_uq UNIQUE(uuid),
            CONSTRAINT erp_199_inventory_status_ck CHECK(status IN ('open','closed'))
        )");

        $this->run_query("CREATE TABLE IF NOT EXISTS erp_199_inventory_items(
            id BIGSERIAL PRIMARY KEY,
            inventory_id BIGINT NOT NULL REFERENCES erp_199_inventories(id) ON DELETE CASCADE,
            token_id BIGINT REFERENCES erp_199_tokens(id) ON DELETE SET NULL,
            token_code VARCHAR(64),
            object_id BIGINT,
            status VARCHAR(16) NOT NULL DEFAULT 'found',
            scanned_by INTEGER,
            scanned_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            media_id BIGINT,
            comment TEXT,
            geo_lat NUMERIC(10,7),
            geo_lon NUMERIC(11,7),
            CONSTRAINT erp_199_inventory_item_status_ck CHECK(status IN ('found','missing','damaged','foreign')),
            CONSTRAINT erp_199_inventory_item_uq UNIQUE(inventory_id,token_id)
        )");
        $this->run_query("CREATE INDEX IF NOT EXISTS erp_199_inventory_item_idx ON erp_199_inventory_items(inventory_id,status)");

        $this->seed_types();
        $this->seed_scenarios();
    }

    /**
     * Базовые типы целей: что чаще всего маркируют на производстве.
     */
    protected function seed_types()
    {
        $types = array(
            array('equipment', 'Оборудование', 'registry', 'fas fa-wrench',
                'Станок, установка, инструмент. Данные ведёт реестр erp227.',
                'Проанализируй заводскую табличку: производитель, модель, серийный номер, мощность, год выпуска.',
                array('brand', 'model', 'serial_number', 'power', 'voltage', 'year'), FALSE, 10),
            array('location', 'Локация', 'registry', 'fas fa-map-pin',
                'Помещение, участок, склад, зона. Данные ведёт реестр erp227.',
                'Определи тип помещения, этаж, зону.',
                array('room_number', 'floor', 'department', 'zone_type'), TRUE, 20),
            array('item', 'Номенклатура', 'item', 'fas fa-box',
                'Позиция номенклатуры со остатками склада (erp227 + erp401).',
                'Проанализируй упаковку: наименование, артикул, партия, количество.',
                array('part_number', 'batch_id', 'quantity', 'unit'), FALSE, 30),
            array('lot', 'Партия / серийный номер', 'lot', 'fas fa-code-branch',
                'Партия или экземпляр изделия. Данные ведёт erp412.',
                'Найди номер партии или серийный номер изделия.',
                array('lot_number', 'serial_number', 'manufactured_at'), FALSE, 40),
            array('order', 'Производственный заказ', 'order', 'fas fa-gears',
                'Заказ на выпуск. Данные ведёт erp402.',
                'Найди номер производственного заказа и изделие.',
                array('order_number', 'item', 'qty'), FALSE, 50),
            array('employee', 'Сотрудник', 'employee', 'fas fa-user',
                'Учётная запись сотрудника. Данные ведёт erp310.',
                'Определи ФИО, должность и наличие СИЗ.',
                array('full_name', 'position', 'has_helmet', 'has_vest'), FALSE, 60),
            array('tool', 'Оснастка и инструмент', 'local', 'fas fa-screwdriver-wrench',
                'Свободный объект модуля: то, для чего нет владельца в системе.',
                'Определи вид инструмента, размер, состояние.',
                array('kind', 'size', 'condition'), TRUE, 70),
            array('container', 'Тара и контейнер', 'local', 'fas fa-box-open',
                'Свободный объект модуля: контейнер, паллета, ящик.',
                'Определи тип тары, размер, состояние.',
                array('kind', 'size', 'condition'), TRUE, 80),
            array('document', 'Документ на месте', 'local', 'fas fa-file-lines',
                'Бумажный носитель, который нужно связать с электронной копией.',
                'Определи вид документа, номер, дату.',
                array('kind', 'number', 'date'), TRUE, 90),
        );

        foreach ($types as $type)
        {
            $this->run_query(
                "INSERT INTO erp_199_types(code,label,adapter,icon,hint,ai_prompt_specs,expected_attributes,allow_local,sort)
                 VALUES (
                    '".$type[0]."',
                    '".str_replace("'", "''", $type[1])."',
                    '".$type[2]."',
                    '".$type[3]."',
                    '".str_replace("'", "''", $type[4])."',
                    '".str_replace("'", "''", $type[5])."',
                    '".json_encode($type[6], JSON_UNESCAPED_UNICODE)."',
                    ".($type[7] ? 'TRUE' : 'FALSE').",
                    ".(int) $type[8]."
                 )
                 ON CONFLICT (code) DO NOTHING"
            );
        }
    }

    /**
     * Сценарии терминала. Форма — это данные: приложение рисует поля,
     * эффект выполняет сервер.
     */
    protected function seed_scenarios()
    {
        $scenarios = array(
            array(
                'inventory_scan', 'Инвентаризация: объект найден', 'fas fa-clipboard-check',
                'Отметить объект в открытой сессии инвентаризации. Если сессии нет — останется запись в журнале.',
                array(), FALSE, FALSE,
                array('fields' => array(
                    array('name' => 'condition', 'label' => 'Состояние', 'type' => 'select',
                        'options' => array('ok' => 'Исправен', 'damaged' => 'Повреждён', 'unknown' => 'Не проверял')),
                )),
                array('kind' => 'inventory_scan', 'status_param' => 'condition'),
                'Отмечено в инвентаризации', 10,
            ),
            array(
                'stage_mark', 'Отметить этап', 'fas fa-forward',
                'Зафиксировать, на каком этапе находится изделие: изготовление, сборка, контроль, упаковка, отгрузка.',
                array('lot', 'order', 'local'), FALSE, FALSE,
                array('fields' => array(
                    array('name' => 'stage', 'label' => 'Этап', 'type' => 'select', 'required' => TRUE,
                        'options' => array(
                            'manufacturing' => 'Изготовление',
                            'assembly' => 'Сборка',
                            'control' => 'Контроль',
                            'packing' => 'Упаковка',
                            'shipped' => 'Отгружено',
                        )),
                    array('name' => 'comment', 'label' => 'Комментарий', 'type' => 'text'),
                )),
                array('kind' => 'set_attributes', 'store_as' => 'stage'),
                'Этап записан', 20,
            ),
            array(
                'status_mark', 'Отметить статус', 'fas fa-flag',
                'Изменить статус цели: подтвердить, забраковать, отправить в архив, отметить ремонт.',
                array('local', 'registry', 'lot'), FALSE, FALSE,
                array('fields' => array(
                    array('name' => 'status', 'label' => 'Новый статус', 'type' => 'select', 'required' => TRUE,
                        'options' => array(
                            'active' => 'В работе',
                            'repair' => 'В ремонте',
                            'archived' => 'В архиве',
                            'rejected' => 'Забракован',
                        )),
                    array('name' => 'comment', 'label' => 'Причина', 'type' => 'text'),
                )),
                array('kind' => 'set_status', 'status_param' => 'status'),
                'Статус изменён', 30,
            ),
            array(
                'photo_fix', 'Зафиксировать фото', 'fas fa-camera',
                'Сфотографировать объект, шильдик или состояние. Фото остаётся в карточке.',
                array(), TRUE, FALSE,
                array('fields' => array(
                    array('name' => 'purpose', 'label' => 'Что на фото', 'type' => 'select',
                        'options' => array('object' => 'Объект', 'plate' => 'Шильдик/табличка', 'evidence' => 'Подтверждение')),
                )),
                array('kind' => 'record'),
                'Фото сохранено', 40,
            ),
            array(
                'location_mark', 'Отметить местоположение', 'fas fa-location-dot',
                'Записать, где объект находится сейчас. Координаты берутся с телефона автоматически.',
                array('local', 'registry', 'item'), FALSE, FALSE,
                array('fields' => array(
                    array('name' => 'location', 'label' => 'Место', 'type' => 'text', 'required' => TRUE),
                    array('name' => 'comment', 'label' => 'Комментарий', 'type' => 'text'),
                )),
                array('kind' => 'set_attributes', 'store_as' => 'location', 'remember_geo' => TRUE),
                'Местоположение записано', 50,
            ),
            array(
                'note', 'Запись в журнал', 'fas fa-pen',
                'Оставить отметку о событии: осмотр, замечание, передача смены.',
                array(), FALSE, TRUE,
                array('fields' => array(
                    array('name' => 'comment', 'label' => 'Что произошло', 'type' => 'textarea', 'required' => TRUE),
                )),
                array('kind' => 'record'),
                'Запись добавлена', 60,
            ),
        );

        foreach ($scenarios as $scenario)
        {
            $this->run_query(
                "INSERT INTO erp_199_scenarios(code,label,icon,description,target_kinds,requires_photo,requires_comment,form,effect,result_note,sort)
                 VALUES (
                    '".$scenario[0]."',
                    '".$this->literal($scenario[1])."',
                    '".$scenario[2]."',
                    '".$this->literal($scenario[3])."',
                    '".json_encode($scenario[4], JSON_UNESCAPED_UNICODE)."',
                    ".($scenario[5] ? 'TRUE' : 'FALSE').",
                    ".($scenario[6] ? 'TRUE' : 'FALSE').",
                    '".json_encode($scenario[7], JSON_UNESCAPED_UNICODE)."',
                    '".json_encode($scenario[8], JSON_UNESCAPED_UNICODE)."',
                    '".$this->literal($scenario[9])."',
                    ".(int) $scenario[10]."
                 )
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

    public function down()
    {
        foreach (array(
            'erp_199_inventory_items', 'erp_199_inventories', 'erp_199_scenarios',
            'erp_199_activity', 'erp_199_media', 'erp_199_tokens', 'erp_199_objects_history',
            'erp_199_objects', 'erp_199_types',
        ) as $table)
        {
            $this->run_query('DROP TABLE IF EXISTS '.$table.' CASCADE');
        }
    }
}
