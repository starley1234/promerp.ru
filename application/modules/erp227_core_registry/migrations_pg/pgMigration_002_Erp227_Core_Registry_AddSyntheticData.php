<?php defined('SYSPATH') or die('No direct script access.');

class pgMigration_002_Erp227_Core_Registry_AddSyntheticData extends pgMigration
{
    protected $_db_group = 'default'; 

    public function up()
    {
        // 1. Дополнительные единицы измерения для РЭА
        $this->run_query("
            INSERT INTO erp_227_units (unit, name, is_decimal)
            VALUES 
                ('m2', 'Квадратный метр', true),
                ('dm2', 'Квадратный дециметр', true),
                ('mm', 'Миллиметр', true),
                ('g', 'Грамм', true),
                ('pack', 'Упаковка', false),
                ('set', 'Комплект', false)
            ON CONFLICT (unit) DO NOTHING;
        ");

        // 2. Расширенные типы объектов для производства электроники
        $this->run_query("
            INSERT INTO erp_227_object_types (code, name, schema)
            VALUES 
                (
                    'component', 
                    'Электронный компонент', 
                    '{\"fields\": [{\"name\": \"package\", \"type\": \"string\"}, {\"name\": \"manufacturer\", \"type\": \"string\"}, {\"name\": \"datasheet_url\", \"type\": \"string\"}]}'
                ),
                (
                    'pcb', 
                    'Печатная плата', 
                    '{\"fields\": [{\"name\": \"layers\", \"type\": \"number\"}, {\"name\": \"thickness\", \"type\": \"number\"}, {\"name\": \"finish\", \"type\": \"string\"}]}'
                ),
                (
                    'assembly', 
                    'Сборочный узел (PCBA)', 
                    '{\"fields\": [{\"name\": \"mounting_type\", \"type\": \"string\"}, {\"name\": \"is_rohs\", \"type\": \"boolean\"}]}'
                ),
                (
                    'equipment', 
                    'Производственное оборудование', 
                    '{\"fields\": [{\"name\": \"work_center\", \"type\": \"string\"}, {\"name\": \"maintenance_period\", \"type\": \"number\"}]}'
                ),
                (
                    'employee', 
                    'Персонал', 
                    '{\"fields\": [{\"name\": \"skill_level\", \"type\": \"integer\"}, {\"name\": \"access_role\", \"type\": \"string\"}]}'
                ),
                (
                    'work_order', 
                    'Производственное задание', 
                    '{\"fields\": [{\"name\": \"priority\", \"type\": \"string\"}, {\"name\": \"batch_number\", \"type\": \"string\"}]}'
                )
            ON CONFLICT (code) DO NOTHING;
        ");

        // 3. Настройка нумераторов (Sequences) для созданных типов
        $this->run_query("
            INSERT INTO erp_227_sequences (type_id, prefix, current_val, padding)
            SELECT id, 
                CASE 
                    WHEN code = 'component' THEN 'CMP-'
                    WHEN code = 'pcb'       THEN 'PCB-'
                    WHEN code = 'assembly'  THEN 'ASY-'
                    WHEN code = 'equipment' THEN 'EQP-'
                    WHEN code = 'employee'  THEN 'EMP-'
                    WHEN code = 'work_order' THEN 'WO-'
                    ELSE 'OBJ-'
                END,
                0, 6
            FROM erp_227_object_types
            WHERE code IN ('component', 'pcb', 'assembly', 'equipment', 'employee', 'work_order')
            ON CONFLICT (type_id) DO NOTHING;
        ");

        // 4. Наполнение реестра объектов (Синтетические данные РЭА)
        $this->run_query("
            DO $$
            DECLARE
                type_comp_id BIGINT := (SELECT id FROM erp_227_object_types WHERE code = 'component');
                type_pcb_id  BIGINT := (SELECT id FROM erp_227_object_types WHERE code = 'pcb');
                type_assy_id BIGINT := (SELECT id FROM erp_227_object_types WHERE code = 'assembly');
                type_equ_id  BIGINT := (SELECT id FROM erp_227_object_types WHERE code = 'equipment');
                obj_pcb_id   BIGINT;
                obj_assy_id  BIGINT;
            BEGIN
                -- Компоненты
                INSERT INTO erp_227_objects (type_id, code, name, attributes, kind) VALUES
                (type_comp_id, 'RES-0402-10K', 'Резистор 10кОм 1% 0402', '{\"package\": \"0402\", \"manufacturer\": \"Yageo\"}', 'definition'),
                (type_comp_id, 'CAP-0603-100NF', 'Конденсатор 100нФ 50В X7R 0603', '{\"package\": \"0603\", \"manufacturer\": \"Murata\"}', 'definition'),
                (type_comp_id, 'MCU-STM32F103C8T6', 'Микроконтроллер STM32F103C8T6', '{\"package\": \"LQFP-48\", \"manufacturer\": \"STMicroelectronics\"}', 'definition');

                -- Печатная плата
                INSERT INTO erp_227_objects (type_id, code, name, attributes, kind) 
                VALUES (type_pcb_id, 'PCB-CTRL-001', 'Плата управления v1.0', '{\"layers\": 4, \"thickness\": 1.6, \"finish\": \"ENIG\"}', 'definition')
                RETURNING id INTO obj_pcb_id;

                -- Сборочная единица (Готовое изделие)
                INSERT INTO erp_227_objects (type_id, code, name, attributes, kind) 
                VALUES (type_assy_id, 'ASSY-CTRL-001', 'Модуль управления в сборе', '{\"mounting_type\": \"SMT\", \"is_rohs\": true}', 'definition')
                RETURNING id INTO obj_assy_id;

                -- Оборудование
                INSERT INTO erp_227_objects (type_id, code, name, attributes, kind) VALUES
                (type_equ_id, 'SMT-LINE-01', 'Линия поверхностного монтажа №1', '{\"work_center\": \"SMT-SHOP\", \"maintenance_period\": 180}', 'asset'),
                (type_equ_id, 'AOI-01', 'Система автоматической оптической инспекции', '{\"work_center\": \"QC-DEPT\", \"maintenance_period\": 90}', 'asset');

                -- 5. Создание связей (BOM - Ведомость материалов)
                IF obj_assy_id IS NOT NULL AND obj_pcb_id IS NOT NULL THEN
                    -- Плата входит в сборку
                    INSERT INTO erp_227_object_links (source_id, target_id, link_type, quantity, unit)
                    VALUES (obj_assy_id, obj_pcb_id, 'BOM_ITEM', 1, 'pcs');

                    -- Компоненты входят в сборку
                    INSERT INTO erp_227_object_links (source_id, target_id, link_type, quantity, unit)
                    SELECT obj_assy_id, id, 'BOM_ITEM', 10, 'pcs' 
                    FROM erp_227_objects WHERE code LIKE 'RES-%' OR code LIKE 'CAP-%';
                    
                    INSERT INTO erp_227_object_links (source_id, target_id, link_type, quantity, unit)
                    SELECT obj_assy_id, id, 'BOM_ITEM', 1, 'pcs' 
                    FROM erp_227_objects WHERE code LIKE 'MCU-%';
                END IF;
            END $$;
        ");
    }

    public function down()
    {
        $this->run_query("DELETE FROM erp_227_object_links;");
        $this->run_query("DELETE FROM erp_227_objects;");
        $this->run_query("DELETE FROM erp_227_sequences WHERE type_id IN (SELECT id FROM erp_227_object_types WHERE code IN ('component', 'pcb', 'assembly', 'equipment', 'employee', 'work_order'));");
        $this->run_query("DELETE FROM erp_227_object_types WHERE code IN ('component', 'pcb', 'assembly', 'equipment', 'employee', 'work_order');");
        $this->run_query("DELETE FROM erp_227_units WHERE unit IN ('m2', 'dm2', 'mm', 'g', 'pack', 'set');");
    }
}