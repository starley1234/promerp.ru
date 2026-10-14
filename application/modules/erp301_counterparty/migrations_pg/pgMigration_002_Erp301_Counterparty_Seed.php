<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Миграция 002: демонстрационные контрагенты, контакты, реквизиты и договоры.
 *
 * Набор подобран под производственный контур: поставщики компонентов,
 * покупатель готовой продукции, перевозчик и подрядчик-монтажник.
 * Миграция идемпотентна: ON CONFLICT DO NOTHING по коду контрагента.
 */
class pgMigration_002_Erp301_Counterparty_Seed extends pgMigration
{
    // protected $_db_group = 'default';

    public function up()
    {
        // 1. Контрагенты
        $this->run_query("
            INSERT INTO erp_301_counterparties
                (code, name, full_name, kind, roles, inn, kpp, country, city, address, phone, email, status, rating, note)
            VALUES
                ('CP-0001', 'ООО «Платан-Компонент»', 'Общество с ограниченной ответственностью «Платан-Компонент»',
                 'legal', '[\"supplier\"]'::jsonb, '7701234567', '770101001', 'RU', 'Москва',
                 'г. Москва, ул. Складочная, д. 12, стр. 3', '+7 495 123-45-67', 'sales@platan-comp.ru', 'active', 4,
                 'Основной поставщик пассивных компонентов SMT'),
                ('CP-0002', 'ООО «ЧипЭлектроника»', 'Общество с ограниченной ответственностью «ЧипЭлектроника»',
                 'legal', '[\"supplier\"]'::jsonb, '7802345678', '780201001', 'RU', 'Санкт-Петербург',
                 'г. Санкт-Петербург, пр. Обуховской обороны, д. 76', '+7 812 234-56-78', 'order@chip-el.ru', 'active', 3,
                 'Микроконтроллеры и корпуса; сроки поставки плавают при дефиците'),
                ('CP-0003', 'АО «Промышленные приборы»', 'Акционерное общество «Промышленные приборы»',
                 'legal', '[\"customer\"]'::jsonb, '5003456789', '500301001', 'RU', 'Подольск',
                 'г. Подольск, ул. Промышленная, д. 4', '+7 496 345-67-89', 'zakupki@rprb.ru', 'active', 5,
                 'Головной заказчик серии контроллеров, платежи 50/50'),
                ('CP-0004', 'ООО «ТрансЛогистик»', 'Общество с ограниченной ответственностью «ТрансЛогистик»',
                 'legal', '[\"carrier\"]'::jsonb, '6604567890', '660401001', 'RU', 'Екатеринбург',
                 'г. Екатеринбург, ул. Машиностроителей, д. 19', '+7 343 456-78-90', 'dispatch@translog.ru', 'active', 3,
                 'Доставка собранных модулей по РФ, до 5 дней'),
                ('CP-0005', 'ИП Серёгин А. В.', 'Индивидуальный предприниматель Серёгин Андрей Владимирович',
                 'individual', '[\"contractor\"]'::jsonb, '502345678901', NULL, 'RU', 'Химки',
                 'г. Химки, ул. Заводская, д. 7, кв. 41', '+7 916 567-89-01', 'seregin@montazh-av.ru', 'blocked', 2,
                 'Монтаж шкафов автоматики; приостановлен по качеству')
            ON CONFLICT (code) DO NOTHING;
        ");

        // 2. Контактные лица
        $this->run_query("
            INSERT INTO erp_301_contacts (counterparty_id, name, position, phone, email, is_primary)
            SELECT c.id, v.name, v.position, v.phone, v.email, v.is_primary
            FROM (VALUES
                ('CP-0001', 'Максим Ковалёв',   'Менеджер по продажам',   '+7 495 123-45-77', 'kovalev@platan-comp.ru', 1),
                ('CP-0001', 'Анна Литвинова',   'Логист',                 '+7 495 123-45-78', 'logistics@platan-comp.ru', 0),
                ('CP-0003', 'Сергей Данилов',   'Начальник закупок',      '+7 496 345-67-99', 'danilov@rprb.ru', 1),
                ('CP-0004', 'Ольга Пирогова',   'Диспетчер',              '+7 343 456-78-91', 'olga@translog.ru', 1)
            ) AS v(cp_code, name, position, phone, email, is_primary)
            JOIN erp_301_counterparties c ON c.code = v.cp_code
            WHERE NOT EXISTS (
                SELECT 1 FROM erp_301_contacts x WHERE x.counterparty_id = c.id AND x.name = v.name
            );
        ");

        // 3. Реквизиты
        $this->run_query("
            INSERT INTO erp_301_requisites
                (counterparty_id, kind, title, bank_name, bik, account, corr_account, currency, address, is_primary)
            SELECT c.id, v.kind, v.title, v.bank_name, v.bik, v.account, v.corr_account, v.currency, v.address, v.is_primary
            FROM (VALUES
                ('CP-0001', 'bank',  'Основной счёт', 'АО «Альфа-Банк»',              '044525593', '40702810600000012345', '30101810200000000593', 'RUB', NULL, 1),
                ('CP-0001', 'legal', 'Юридический адрес', NULL, NULL, NULL, NULL, 'RUB', 'г. Москва, ул. Складочная, д. 12, стр. 3', 0),
                ('CP-0003', 'bank',  'Основной счёт', 'ПАО «Сбербанк»',                '044525225', '40702810700000054321', '30101810400000000225', 'RUB', NULL, 1),
                ('CP-0003', 'postal', 'Почтовый адрес', NULL, NULL, NULL, NULL, 'RUB', 'г. Подольск, а/я 15', 0)
            ) AS v(cp_code, kind, title, bank_name, bik, account, corr_account, currency, address, is_primary)
            JOIN erp_301_counterparties c ON c.code = v.cp_code
            WHERE NOT EXISTS (
                SELECT 1 FROM erp_301_requisites x
                WHERE x.counterparty_id = c.id AND x.kind = v.kind AND x.title = v.title
            );
        ");

        // 4. Договоры
        $this->run_query("
            INSERT INTO erp_301_contracts
                (counterparty_id, number, title, kind, signed_at, valid_from, valid_to, amount, currency, payment_terms, status, note)
            SELECT c.id, v.number, v.title, v.kind, v.signed_at::date, v.valid_from::date, v.valid_to::date,
                   v.amount, 'RUB', v.payment_terms, v.status, v.note
            FROM (VALUES
                ('CP-0001', 'П-2026-14', 'Поставка пассивных компонентов SMT', 'supply',
                 '2026-01-15', '2026-01-15', '2026-12-31', 4850000.00, 'отсрочка 30 дней', 'active',
                 'Квартальные выборки по спецификации'),
                ('CP-0002', 'П-2026-27', 'Поставка микроконтроллеров и корпусов', 'supply',
                 '2026-02-01', '2026-02-01', '2026-10-05', 3200000.00, 'предоплата 50%', 'active',
                 'Истекает осенью — требуется продление'),
                ('CP-0003', 'К-2026-03', 'Изготовление партии контроллеров АСУ-3', 'work',
                 '2026-01-20', '2026-02-01', '2026-09-30', 11800000.00, '50/50 по этапам', 'active',
                 'Три этапа: партии по 200 изделий'),
                ('CP-0004', 'У-2026-08', 'Транспортно-экспедиционное обслуживание', 'service',
                 '2026-03-01', '2026-03-01', '2026-03-31', 180000.00, 'по факту рейса', 'expired',
                 'Истёк, ожидается пролонгация'),
                ('CP-0005', 'Р-2026-11', 'Монтаж шкафов автоматики', 'work',
                 '2026-04-10', '2026-04-15', '2026-08-31', 940000.00, 'аванс 30%', 'suspended',
                 'Приостановлен: замечания по качеству монтажа')
            ) AS v(cp_code, number, title, kind, signed_at, valid_from, valid_to, amount, payment_terms, status, note)
            JOIN erp_301_counterparties c ON c.code = v.cp_code
            ON CONFLICT (counterparty_id, number) DO NOTHING;
        ");
    }

    public function down()
    {
        $this->run_query("DELETE FROM erp_301_contracts WHERE number IN ('П-2026-14','П-2026-27','К-2026-03','У-2026-08','Р-2026-11');");
        $this->run_query("DELETE FROM erp_301_requisites WHERE counterparty_id IN (SELECT id FROM erp_301_counterparties WHERE code IN ('CP-0001','CP-0003'));");
        $this->run_query("DELETE FROM erp_301_contacts WHERE counterparty_id IN (SELECT id FROM erp_301_counterparties WHERE code IN ('CP-0001','CP-0003','CP-0004'));");
        $this->run_query("DELETE FROM erp_301_counterparties WHERE code IN ('CP-0001','CP-0002','CP-0003','CP-0004','CP-0005');");
    }
}
