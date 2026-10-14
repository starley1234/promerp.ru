<?php defined('SYSPATH') or die('No direct script access.');

class pgMigration_002_erp230_mdm_add_data extends pgMigration
{
    /**
     * @var string Группа базы данных
     */
    protected $_db_group = 'mdm';

    /**
     * Применение миграции
     */
    public function up()
    {
       

        // 2. Иерархия типов (РЭА и Авиастроение) с расширением x-uom
        $this->run_query("
            INSERT INTO types (id, parent_id, display_name, schema) VALUES
            ('root', NULL, 'Объект НСИ', '{\"title\": \"Объект НСИ\", \"description\": \"Базовый тип для всех объектов системы\", \"properties\": {\"name\": {\"type\": \"string\", \"title\": \"Наименование\"}, \"description\": {\"type\": \"string\", \"title\": \"Описание\"}}}'),
            
            -- Компоненты (ЭРИ)
            ('component', 'root', 'Электронная компонентная база', '{\"title\": \"Электронная компонентная база\", \"description\": \"Электронные компоненты и радиодетали\", \"required\": [\"manufacturer\"], \"properties\": {\"name\": {\"type\": \"string\", \"title\": \"Наименование\"}, \"description\": {\"type\": \"string\", \"title\": \"Описание\"}}}'),
            ('resistor', 'component', 'Резистор', '{\"title\": \"Резистор\", \"description\": \"Пассивный элемент электрических цепей\", \"properties\": {\"name\": {\"type\": \"string\", \"title\": \"Наименование\"}, \"description\": {\"type\": \"string\", \"title\": \"Описание\"}, \"resistance\": {\"type\": \"number\", \"title\": \"Сопротивление\", \"x-uom\": \"ohm\"}, \"tolerance\": {\"type\": \"string\", \"title\": \"Допуск\", \"x-uom\": \"percent\"}}, \"x-mdm\": {\"code_policy\": {\"mode\": \"auto\", \"prefix\": \"RES-\", \"pad\": 6}}}'),
            ('capacitor', 'component', 'Конденсатор', '{\"title\": \"Конденсатор\", \"description\": \"Двухполюсник с определённым значением ёмкости\", \"properties\": {\"name\": {\"type\": \"string\", \"title\": \"Наименование\"}, \"description\": {\"type\": \"string\", \"title\": \"Описание\"}, \"capacitance\": {\"type\": \"number\", \"title\": \"Емкость\", \"x-uom\": \"uf\"}, \"voltage\": {\"type\": \"number\", \"title\": \"Напряжение\", \"x-uom\": \"v\"}}, \"x-mdm\": {\"code_policy\": {\"mode\": \"auto\", \"prefix\": \"CAP-\", \"pad\": 6}}}'),
            ('ic', 'component', 'Микросхема', '{\"title\": \"Микросхема\", \"description\": \"Интегральная схема в микроэлектронном исполнении\", \"properties\": {\"name\": {\"type\": \"string\", \"title\": \"Наименование\"}, \"description\": {\"type\": \"string\", \"title\": \"Описание\"}, \"package\": {\"type\": \"string\", \"title\": \"Корпус\"}, \"pin_count\": {\"type\": \"integer\", \"title\": \"Кол-во выводов\"}}, \"x-mdm\": {\"code_policy\": {\"mode\": \"auto\", \"prefix\": \"IC-\", \"pad\": 6}}}'),
            ('connector', 'component', 'Соединитель', '{\"title\": \"Соединитель\", \"description\": \"Электротехническое устройство для соединения проводников\", \"properties\": {\"name\": {\"type\": \"string\", \"title\": \"Наименование\"}, \"description\": {\"type\": \"string\", \"title\": \"Описание\"}, \"contact_type\": {\"type\": \"string\", \"title\": \"Тип контактов\"}}, \"x-mdm\": {\"code_policy\": {\"mode\": \"auto\", \"prefix\": \"CONNECTOR-\", \"pad\": 6}}}'),
            
            -- Сборочные единицы
            ('assembly', 'root', 'Сборочная единица', '{\"title\": \"Сборочная единица\", \"description\": \"Изделие, составные части которого подлежат соединению\", \"required\": [\"drawing_number\"], \"properties\": {\"name\": {\"type\": \"string\", \"title\": \"Наименование\"}, \"description\": {\"type\": \"string\", \"title\": \"Описание\"}}}'),
            ('pcba', 'assembly', 'Печатный узел', '{\"title\": \"Печатный узел\", \"description\": \"Печатная плата с установленными компонентами\", \"properties\": {\"name\": {\"type\": \"string\", \"title\": \"Наименование\"}, \"description\": {\"type\": \"string\", \"title\": \"Описание\"}, \"layer_count\": {\"type\": \"integer\", \"title\": \"Кол-во слоев\"}}, \"x-mdm\": {\"code_policy\": {\"mode\": \"auto\", \"prefix\": \"PCBA-\", \"pad\": 6}}}'),
            ('module', 'assembly', 'Электронный модуль (блок)', '{\"title\": \"Электронный модуль (блок)\", \"description\": \"Законченное устройство в составе системы\", \"properties\": {\"name\": {\"type\": \"string\", \"title\": \"Наименование\"}, \"description\": {\"type\": \"string\", \"title\": \"Описание\"}, \"power_consumption\": {\"type\": \"number\", \"title\": \"Потребляемая мощность\", \"x-uom\": \"w\"}}, \"x-mdm\": {\"code_policy\": {\"mode\": \"auto\", \"prefix\": \"MOD-\", \"pad\": 6}}}'),
            ('harness', 'assembly', 'Жгут кабельный', '{\"title\": \"Жгут кабельный\", \"description\": \"Совокупность проводов и кабелей\", \"properties\": {\"name\": {\"type\": \"string\", \"title\": \"Наименование\"}, \"description\": {\"type\": \"string\", \"title\": \"Описание\"}, \"length\": {\"type\": \"number\", \"title\": \"Длина\", \"x-uom\": \"m\"}}, \"x-mdm\": {\"code_policy\": {\"mode\": \"auto\", \"prefix\": \"HRN-\", \"pad\": 6}}}'),
            
            -- Системы и оборудование
            ('equipment', 'root', 'Бортовое оборудование', '{\"title\": \"Бортовое оборудование\", \"description\": \"Комплекс технических средств на борту судна\", \"required\": [\"part_number\"], \"properties\": {\"name\": {\"type\": \"string\", \"title\": \"Наименование\"}, \"description\": {\"type\": \"string\", \"title\": \"Описание\"}}}'),
            ('avionics', 'equipment', 'Авионика', '{\"title\": \"Авионика\", \"description\": \"Совокупность электронных систем летательного аппарата\", \"properties\": {\"name\": {\"type\": \"string\", \"title\": \"Наименование\"}, \"description\": {\"type\": \"string\", \"title\": \"Описание\"}, \"software_version\": {\"type\": \"string\", \"title\": \"Версия ПО\"}}}'),
            ('navigation', 'avionics', 'Навигационное оборудование', '{\"title\": \"Навигационное оборудование\", \"description\": \"Системы определения местоположения\", \"properties\": {\"name\": {\"type\": \"string\", \"title\": \"Наименование\"}, \"description\": {\"type\": \"string\", \"title\": \"Описание\"}}}'),
            ('communication', 'avionics', 'Связное оборудование', '{\"title\": \"Связное оборудование\", \"description\": \"Системы передачи данных и голоса\", \"properties\": {\"name\": {\"type\": \"string\", \"title\": \"Наименование\"}, \"description\": {\"type\": \"string\", \"title\": \"Описание\"}}}'),
            
            -- Материалы
            ('material', 'root', 'Материал', '{\"title\": \"Материал\", \"description\": \"Вещества, используемые для производства изделий\", \"properties\": {\"name\": {\"type\": \"string\", \"title\": \"Наименование\"}, \"description\": {\"type\": \"string\", \"title\": \"Описание\"}}}'),
            ('metal', 'material', 'Металлы и сплавы', '{\"title\": \"Металлы и сплавы\", \"description\": \"Металлические материалы\", \"properties\": {\"name\": {\"type\": \"string\", \"title\": \"Наименование\"}, \"description\": {\"type\": \"string\", \"title\": \"Описание\"}}}'),
            ('chemical', 'material', 'Химия и ЛКМ', '{\"title\": \"Химия и ЛКМ\", \"description\": \"Лакокрасочные и химические материалы\", \"properties\": {\"name\": {\"type\": \"string\", \"title\": \"Наименование\"}, \"description\": {\"type\": \"string\", \"title\": \"Описание\"}}}')
            ON CONFLICT (id) DO UPDATE SET
                parent_id = EXCLUDED.parent_id,
                display_name = EXCLUDED.display_name,
                schema = EXCLUDED.schema;
        ");

        // Обновляем пути (path)
        $this->run_query("
            WITH RECURSIVE type_tree AS (
                SELECT id, text2ltree(id) as new_path FROM types WHERE parent_id IS NULL
                UNION ALL
                SELECT t.id, tt.new_path || text2ltree(t.id)
                FROM types t
                JOIN type_tree tt ON t.parent_id = tt.id
            )
            UPDATE types SET path = type_tree.new_path FROM type_tree WHERE types.id = type_tree.id;
        ");
    }

    /**
     * Откат миграции
     */
    public function down()
    {
        $this->run_query("DELETE FROM types WHERE id IN ('material', 'metal', 'chemical', 'equipment', 'avionics', 'navigation', 'communication', 'assembly', 'pcba', 'module', 'harness', 'component', 'resistor', 'capacitor', 'ic', 'connector', 'root');");
    }
}