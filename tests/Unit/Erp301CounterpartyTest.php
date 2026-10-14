<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты модуля контрагентов (erp301_counterparty).
 *
 * Проверяют бизнес-логику Service_Erp301_Counterparties на реальной БД:
 * жизненный цикл контрагента, проверки уникальности, поиск и фильтры,
 * контактные лица, реквизиты и договоры.
 *
 * Тесты создают собственные записи с префиксом TEST- и удаляют их в tearDown,
 * поэтому безопасны для демо-данных. Если таблиц модуля нет — тесты
 * пропускаются (выполните миграции erp301).
 */
class Erp301CounterpartyTest extends TestCase
{
    /** @var \Service_Erp301_Counterparties */
    protected $service;

    /** @var int Тестовые контрагенты */
    protected $created = array();

    protected function setUp(): void
    {
        if ( ! $this->available())
        {
            $this->markTestSkipped('Модуль erp301_counterparty недоступен — выполните миграции erp301');
            return;
        }

        $this->service = \Service_Erp301_Counterparties::instance();
    }

    protected function tearDown(): void
    {
        if ( ! $this->available())
        {
            return;
        }

        try
        {
            // Жёсткая уборка: сначала записи, созданные этим тестом (у сгенерированного
            // кода нет префикса TEST-, поэтому полагаться только на LIKE нельзя),
            // затем — страховочная уборка по префиксу.
            $ids = array_filter(array_map('intval', $this->created));

            $sweep = \DB::select('id')->from('erp_301_counterparties')
                ->where('code', 'LIKE', 'TEST-%')
                ->execute()->as_array(NULL, 'id');

            $ids = array_values(array_unique(array_merge($ids, array_map('intval', $sweep))));

            if ($ids)
            {
                \DB::delete('erp_301_contracts')->where('counterparty_id', 'IN', $ids)->execute();
                \DB::delete('erp_301_requisites')->where('counterparty_id', 'IN', $ids)->execute();
                \DB::delete('erp_301_contacts')->where('counterparty_id', 'IN', $ids)->execute();
                \DB::delete('erp_301_counterparties')->where('id', 'IN', $ids)->execute();
            }
        }
        catch (\Exception $e)
        {
            // подчистка не должна ломать тест
        }
    }

    // ------------------------------------------------------------- помощники --

    /**
     * Доступны ли таблицы модуля.
     *
     * @return bool
     */
    protected function available()
    {
        try
        {
            \DB::select('id')->from('erp_301_counterparties')->limit(1)->execute();

            return TRUE;
        }
        catch (\Exception $e)
        {
            return FALSE;
        }
    }

    /**
     * Создать тестового контрагента.
     *
     * @param   array   $data
     * @return  \Model_Erp301_Counterparty
     */
    protected function make(array $data = array())
    {
        static $seq = 0;
        $seq++;

        $defaults = array(
            'code'  => 'TEST-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'name'  => 'ООО «Тестовый контрагент '.$seq.'»',
            'kind'  => 'legal',
            'roles' => array('supplier'),
            // ИНН заведомо не пересекается ни с демо-данными, ни с другими тестами
            'inn'   => '77'.str_pad((string) (3000000 + $seq), 8, '0', STR_PAD_LEFT),
        );

        $counterparty = $this->service->create(array_merge($defaults, $data), 1);
        $this->created[] = $counterparty->id;

        return $counterparty;
    }

    // ------------------------------------------------- контрагенты: создание --

    public function test_create_returns_persisted_model()
    {
        $counterparty = $this->make(array('name' => 'ООО «Ромашка-Тест»', 'city' => 'Тверь'));

        $this->assertTrue($counterparty->loaded());
        $this->assertGreaterThan(0, (int) $counterparty->id);
        $this->assertSame(0, (int) $counterparty->is_deleted);
        $this->assertSame('ООО «Ромашка-Тест»', $counterparty->name);
        $this->assertSame('active', $counterparty->status);

        // Запись действительно в БД
        $again = \ORM::factory('Erp301_Counterparty', $counterparty->id);
        $this->assertTrue($again->loaded());
        $this->assertSame('Тверь', $again->city);
    }

    public function test_code_is_generated_when_empty()
    {
        $counterparty = $this->make(array('code' => ''));

        $this->assertMatchesRegularExpression('/^CP-\d{4,}$/', $counterparty->code);
    }

    public function test_code_is_normalized_to_upper_case()
    {
        $counterparty = $this->make(array('code' => ' test-lower '));

        $this->assertSame('TEST-LOWER', $counterparty->code);
    }

    public function test_duplicate_code_is_rejected()
    {
        $this->make(array('code' => 'TEST-DUP'));

        $this->expectException(\ORM_Validation_Exception::class);

        $this->service->create(array(
            'code'  => 'TEST-DUP',
            'name'  => 'Дубль кода',
            'kind'  => 'legal',
            'roles' => array('supplier'),
        ));
    }

    public function test_name_is_required()
    {
        $this->expectException(\ORM_Validation_Exception::class);

        $this->service->create(array('code' => 'TEST-NONAME', 'name' => '', 'kind' => 'legal'));
    }

    public function test_invalid_inn_is_rejected()
    {
        $this->expectException(\ORM_Validation_Exception::class);

        $this->service->create(array(
            'code'  => 'TEST-BADINN',
            'name'  => 'Кривой ИНН',
            'kind'  => 'legal',
            'inn'   => '12345',
        ));
    }

    public function test_duplicate_inn_is_rejected()
    {
        $this->make(array('code' => 'TEST-INN-1', 'inn' => '7812345678'));

        $this->expectException(\ORM_Validation_Exception::class);

        $this->service->create(array(
            'code'  => 'TEST-INN-2',
            'name'  => 'Второй с тем же ИНН',
            'kind'  => 'legal',
            'inn'   => '7812345678',
        ));
    }

    public function test_inn_is_cleaned_from_separators()
    {
        $counterparty = $this->make(array('code' => 'TEST-INN-3', 'inn' => '7719 876 543'));

        $this->assertSame('7719876543', (string) $counterparty->inn);
    }

    // ------------------------------------------------- контрагенты: правка ---

    public function test_update_changes_fields_and_keeps_code()
    {
        $counterparty = $this->make(array('code' => 'TEST-UPD'));

        $updated = $this->service->update($counterparty->id, array(
            'name'   => 'Новое имя',
            'code'   => 'TEST-UPD',
            'kind'   => 'individual',
            'roles'  => array('customer'),
            'rating' => 4,
        ), 1);

        $this->assertSame('Новое имя', $updated->name);
        $this->assertSame('individual', $updated->kind);
        $this->assertSame(array('customer'), $updated->role_codes());
        $this->assertSame(4, (int) $updated->rating);
    }

    public function test_unknown_roles_are_ignored()
    {
        $counterparty = $this->make(array('code' => 'TEST-ROLES', 'roles' => array('supplier', 'hacker', 'carrier')));

        $this->assertSame(array('supplier', 'carrier'), $counterparty->role_codes());
    }

    public function test_roles_label_is_human_readable()
    {
        $counterparty = $this->make(array('code' => 'TEST-LABEL', 'roles' => array('supplier', 'customer')));

        $this->assertSame('Поставщик, Покупатель', $counterparty->roles_label());
        $this->assertTrue($counterparty->has_role('supplier'));
        $this->assertFalse($counterparty->has_role('carrier'));
    }

    public function test_empty_optional_fields_become_null()
    {
        $counterparty = $this->make(array('code' => 'TEST-NULLS', 'inn' => '', 'email' => '', 'city' => ''));

        $this->assertNull($counterparty->inn);
        $this->assertNull($counterparty->email);
        $this->assertNull($counterparty->city);
    }

    // ------------------------------------------------- контрагенты: удаление -

    public function test_delete_archives_counterparty_and_children()
    {
        $counterparty = $this->make(array('code' => 'TEST-DEL'));
        $this->service->save_contact($counterparty->id, array('name' => 'Иван Тестов'));
        $this->service->save_contract($counterparty->id, array('number' => 'TEST-C-1', 'kind' => 'supply'));

        $this->service->delete($counterparty->id, 1);

        $row = \DB::select('is_deleted', 'status')->from('erp_301_counterparties')
            ->where('id', '=', $counterparty->id)->execute()->current();

        $this->assertSame(1, (int) $row['is_deleted']);
        $this->assertSame('archived', $row['status']);

        $contacts = (int) \DB::select(array(\DB::expr('COUNT(*)'), 'total'))->from('erp_301_contacts')
            ->where('counterparty_id', '=', $counterparty->id)->where('is_deleted', '=', 0)->execute()->get('total');
        $contracts = (int) \DB::select(array(\DB::expr('COUNT(*)'), 'total'))->from('erp_301_contracts')
            ->where('counterparty_id', '=', $counterparty->id)->where('is_deleted', '=', 0)->execute()->get('total');

        $this->assertSame(0, $contacts, 'контакты архивируются вместе с контрагентом');
        $this->assertSame(0, $contracts, 'договоры архивируются вместе с контрагентом');

        // get() больше не отдаёт архивного
        $this->expectException(\Kohana_Exception::class);
        $this->service->get($counterparty->id);
    }

    // ---------------------------------------------------- поиск и фильтры ---

    public function test_search_finds_by_name_code_and_inn()
    {
        $this->make(array('code' => 'TEST-SRCH', 'name' => 'ООО «Поиск-Тест»', 'inn' => '7712345678'));

        $by_name = $this->service->search(array('term' => 'Поиск-Тест'));
        $by_code = $this->service->search(array('term' => 'test-srch'));
        $by_inn  = $this->service->search(array('term' => '7712345678'));

        $this->assertGreaterThanOrEqual(1, $by_name['total']);
        $this->assertGreaterThanOrEqual(1, $by_code['total']);
        $this->assertGreaterThanOrEqual(1, $by_inn['total']);
    }

    public function test_search_is_case_insensitive_for_cyrillic()
    {
        // Регрессия: при locale=C кластера ILIKE/lower() не сворачивают регистр
        // кириллицы — поиск «ромашка» не находил «Ромашка». Поле search_text
        // заполняется через mb_strtolower, поэтому поиск регистронезависим.
        $this->make(array('code' => 'TEST-CASE', 'name' => 'ООО «Ромашка-Тест»'));

        foreach (array('ромашка', 'РОМАШКА', 'Ромашка', 'TEST-case', 'test-CASE') as $term)
        {
            $found = $this->service->search(array('term' => $term));
            $this->assertGreaterThanOrEqual(1, $found['total'], 'поиск по «'.$term.'» должен находить запись');
        }
    }

    public function test_search_text_is_refreshed_on_update()
    {
        $counterparty = $this->make(array('code' => 'TEST-STXT', 'name' => 'Старое имя'));
        $this->service->update($counterparty->id, array('name' => 'Новое имя', 'code' => 'TEST-STXT'));

        $this->assertSame(0, $this->service->search(array('term' => 'старое имя'))['total']);
        $this->assertSame(1, $this->service->search(array('term' => 'новое имя'))['total']);
    }

    public function test_contracts_are_searchable_by_title_in_any_case()
    {
        $counterparty = $this->make(array('code' => 'TEST-CTERM'));

        $this->service->save_contract($counterparty->id, array(
            'number' => 'ТЕСТ-ПОИСК-77',
            'title'  => 'Порошковая окраска тестовых корпусов',
            'kind'   => 'work',
        ));

        $this->assertSame(1, $this->service->contracts(array('term' => 'ПОРОШКОВАЯ'))['total']);
        $this->assertSame(1, $this->service->contracts(array('term' => 'окраска тестовых'))['total']);
        $this->assertSame(0, $this->service->contracts(array('term' => 'несуществующий предмет'))['total']);
    }

    public function test_search_filters_by_role_and_status()
    {
        $this->make(array('code' => 'TEST-FLT', 'roles' => array('carrier'), 'status' => 'blocked'));

        $role   = $this->service->search(array('term' => 'TEST-FLT', 'role' => 'carrier'));
        $other  = $this->service->search(array('term' => 'TEST-FLT', 'role' => 'supplier'));
        $status = $this->service->search(array('term' => 'TEST-FLT', 'status' => 'blocked'));

        $this->assertSame(1, $role['total']);
        $this->assertSame(0, $other['total']);
        $this->assertSame(1, $status['total']);
    }

    public function test_search_applies_limit_and_offset()
    {
        $counterparty = $this->make(array('code' => 'TEST-PAGE'));

        $page1 = $this->service->search(array('term' => 'TEST-PAGE'), 1, 0);
        $page2 = $this->service->search(array('term' => 'TEST-PAGE'), 1, 1);

        $this->assertSame(1, count($page1['rows']));
        $this->assertSame(0, count($page2['rows']));
        $this->assertSame((int) $counterparty->id, (int) $page1['rows'][0]->id);
    }

    public function test_suggest_returns_compact_list()
    {
        $this->make(array('code' => 'TEST-SUG', 'name' => 'ООО «Подсказка-Тест»'));

        $suggest = $this->service->suggest('Подсказка-Тест');

        $this->assertGreaterThanOrEqual(1, count($suggest));
        $this->assertArrayHasKey('id', $suggest[0]);
        $this->assertArrayHasKey('label', $suggest[0]);
        $this->assertArrayHasKey('hint', $suggest[0]);
    }

    public function test_find_by_inn_returns_null_for_unknown()
    {
        $this->assertNull($this->service->find_by_inn('9999999999'));

        $counterparty = $this->make(array('code' => 'TEST-BYINN', 'inn' => '7800000001'));
        $found        = $this->service->find_by_inn('7800000001');

        $this->assertNotNull($found);
        $this->assertSame((int) $counterparty->id, (int) $found->id);
    }

    // ----------------------------------------------------------- контакты ----

    public function test_save_contact_creates_and_updates()
    {
        $counterparty = $this->make(array('code' => 'TEST-CONTACT'));

        $contact = $this->service->save_contact($counterparty->id, array(
            'name'     => 'Пётр Тестов',
            'position' => 'Снабженец',
            'phone'    => '+7 900 000-00-00',
        ));

        $this->assertTrue($contact->loaded());

        $same = $this->service->save_contact($counterparty->id, array(
            'name'     => 'Пётр Тестов',
            'position' => 'Начальник снабжения',
        ), $contact->id);

        $this->assertSame($contact->id, $same->id);
        $this->assertSame('Начальник снабжения', $same->position);
    }

    public function test_only_one_primary_contact_per_counterparty()
    {
        $counterparty = $this->make(array('code' => 'TEST-PRIMARY'));

        $first  = $this->service->save_contact($counterparty->id, array('name' => 'Первый', 'is_primary' => 1));
        $second = $this->service->save_contact($counterparty->id, array('name' => 'Второй', 'is_primary' => 1));

        $first->reload();

        $this->assertSame(0, (int) $first->is_primary, 'новый основной контакт снимает признак у прежнего');
        $this->assertSame(1, (int) $second->is_primary);
    }

    public function test_delete_contact_is_soft()
    {
        $counterparty = $this->make(array('code' => 'TEST-CDEL'));
        $contact      = $this->service->save_contact($counterparty->id, array('name' => 'Удаляемый'));

        $this->service->delete_contact($counterparty->id, $contact->id);

        $this->assertSame(0, count($this->service->contacts($counterparty->id)->find_all()));

        $row = \DB::select('is_deleted')->from('erp_301_contacts')->where('id', '=', $contact->id)->execute()->current();
        $this->assertSame(1, (int) $row['is_deleted'], 'контакт остаётся в БД');
    }

    public function test_contact_of_another_counterparty_is_rejected()
    {
        $alpha = $this->make(array('code' => 'TEST-ALPHA'));
        $beta  = $this->make(array('code' => 'TEST-BETA'));

        $contact = $this->service->save_contact($alpha->id, array('name' => 'Чужой контакт'));

        $this->expectException(\Kohana_Exception::class);

        $this->service->save_contact($beta->id, array('name' => 'Перепривязка'), $contact->id);
    }

    // --------------------------------------------------------- реквизиты ----

    public function test_save_requisite_validates_account_for_bank()
    {
        $counterparty = $this->make(array('code' => 'TEST-REQ'));

        $requisite = $this->service->save_requisite($counterparty->id, array(
            'kind'      => 'bank',
            'bank_name' => 'АО «Тест-Банк»',
            'bik'       => '044525593',
            'account'   => '40702810600000012345',
        ));

        $this->assertTrue($requisite->loaded());
        $this->assertStringContainsString('Тест-Банк', $requisite->summary());
        $this->assertStringContainsString('40702810600000012345', $requisite->summary());
    }

    public function test_invalid_bik_is_rejected()
    {
        $counterparty = $this->make(array('code' => 'TEST-BIK'));

        $this->expectException(\ORM_Validation_Exception::class);

        $this->service->save_requisite($counterparty->id, array(
            'kind'    => 'bank',
            'bik'     => '123',
            'account' => '40702810600000012345',
        ));
    }

    public function test_legal_requisite_does_not_require_account()
    {
        $counterparty = $this->make(array('code' => 'TEST-LEGAL'));

        $requisite = $this->service->save_requisite($counterparty->id, array(
            'kind'    => 'legal',
            'address' => 'г. Тестов, ул. Проверочная, д. 1',
        ));

        $this->assertSame('г. Тестов, ул. Проверочная, д. 1', $requisite->summary());
    }

    public function test_printable_requisites_combines_bank_and_legal()
    {
        $counterparty = $this->make(array('code' => 'TEST-PRINT'));

        $this->service->save_requisite($counterparty->id, array(
            'kind'    => 'bank',
            'bank_name' => 'Тест-Банк',
            'bik'     => '044525593',
            'account' => '40702810600000099999',
        ));
        $this->service->save_requisite($counterparty->id, array(
            'kind'    => 'legal',
            'address' => 'г. Тестов, ул. Печатная, д. 2',
        ));

        $printable = $this->service->printable_requisites($counterparty->id);

        $this->assertNotNull($printable['bank']);
        $this->assertNotNull($printable['legal']);
        $this->assertSame(2, count($printable['lines']));
    }

    // ----------------------------------------------------------- договоры ---

    public function test_save_contract_parses_amount_and_dates()
    {
        $counterparty = $this->make(array('code' => 'TEST-CTR'));

        $contract = $this->service->save_contract($counterparty->id, array(
            'number'     => 'Т-2026/1',
            'kind'       => 'supply',
            'valid_from' => '01.02.2026',
            'valid_to'   => '31.12.2026',
            'amount'     => '1 250 000,50',
            'status'     => 'active',
        ), NULL, 1);

        $this->assertSame('2026-02-01', (string) $contract->valid_from);
        $this->assertSame('2026-12-31', $contract->valid_to);
        $this->assertEquals(1250000.50, (float) $contract->amount);
        $this->assertSame('1 250 000,50 RUB', $contract->amount_label());
    }

    public function test_contract_number_must_be_unique_within_counterparty()
    {
        $counterparty = $this->make(array('code' => 'TEST-CNUM'));

        $this->service->save_contract($counterparty->id, array('number' => 'УНИК-1', 'kind' => 'service'));

        $this->expectException(\ORM_Validation_Exception::class);

        $this->service->save_contract($counterparty->id, array('number' => 'УНИК-1', 'kind' => 'service'));
    }

    public function test_contract_period_is_validated()
    {
        $counterparty = $this->make(array('code' => 'TEST-CPERIOD'));

        $this->expectException(\ORM_Validation_Exception::class);

        $this->service->save_contract($counterparty->id, array(
            'number'     => 'ПЕРИОД-1',
            'kind'       => 'work',
            'valid_from' => '2026-05-01',
            'valid_to'   => '2026-04-01',
        ));
    }

    public function test_terminate_contract_sets_status_and_date()
    {
        $counterparty = $this->make(array('code' => 'TEST-CTERM'));

        $contract = $this->service->save_contract($counterparty->id, array(
            'number' => 'РАСТОРГ-1',
            'kind'   => 'lease',
            'status' => 'active',
        ), NULL, 1);

        $terminated = $this->service->terminate_contract($contract->id, 1);

        $this->assertSame('terminated', $terminated->status);
        $this->assertSame(date('Y-m-d'), (string) $terminated->valid_to);
    }

    public function test_expires_soon_detects_upcoming_end()
    {
        $counterparty = $this->make(array('code' => 'TEST-EXPIRE'));

        $soon = $this->service->save_contract($counterparty->id, array(
            'number'     => 'СКОРО-1',
            'kind'       => 'supply',
            'valid_from' => date('Y-m-d', strtotime('-10 days')),
            'valid_to'   => date('Y-m-d', strtotime('+5 days')),
            'status'     => 'active',
        ));

        $far = $this->service->save_contract($counterparty->id, array(
            'number'     => 'ПОЗЖЕ-1',
            'kind'       => 'supply',
            'valid_to'   => date('Y-m-d', strtotime('+200 days')),
            'status'     => 'active',
        ));

        $this->assertTrue($soon->expires_soon(30));
        $this->assertFalse($far->expires_soon(30));
        $this->assertGreaterThanOrEqual(1, count($this->service->expiring_contracts(30)->find_all()));
    }

    public function test_actualize_marks_overdue_contracts_expired()
    {
        $counterparty = $this->make(array('code' => 'TEST-OVERDUE'));

        $contract = $this->service->save_contract($counterparty->id, array(
            'number'     => 'ПРОСРОЧ-1',
            'kind'       => 'supply',
            'valid_from' => date('Y-m-d', strtotime('-60 days')),
            'valid_to'   => date('Y-m-d', strtotime('-1 day')),
            'status'     => 'active',
        ));

        $updated = $this->service->actualize_contract_statuses();

        $contract->reload();

        $this->assertGreaterThanOrEqual(1, $updated);
        $this->assertSame('expired', $contract->status);
    }

    public function test_contracts_filter_by_status_and_counterparty()
    {
        $counterparty = $this->make(array('code' => 'TEST-CFLT'));

        $this->service->save_contract($counterparty->id, array('number' => 'Ф-АКТИВ', 'kind' => 'supply', 'status' => 'active'));
        $this->service->save_contract($counterparty->id, array('number' => 'Ф-ПРОЕКТ', 'kind' => 'supply', 'status' => 'draft'));

        $active = $this->service->contracts(array('counterparty_id' => $counterparty->id, 'status' => 'active'));
        $all    = $this->service->contracts(array('counterparty_id' => $counterparty->id));

        $this->assertSame(1, $active['total']);
        $this->assertSame(2, $all['total']);
    }

    // -------------------------------------------------------- сводка / API --

    public function test_stats_returns_structure()
    {
        $this->make(array('code' => 'TEST-STATS', 'roles' => array('supplier')));

        $stats = $this->service->stats();

        foreach (array('total', 'by_status', 'by_role', 'active_contracts', 'expiring') as $key)
        {
            $this->assertArrayHasKey($key, $stats);
        }

        $this->assertGreaterThanOrEqual(1, $stats['total']);
        $this->assertGreaterThanOrEqual(1, $stats['by_role']['supplier']);
    }

    public function test_events_are_fired_on_write_operations()
    {
        $seen = array();

        \Event::listen('erp301.counterparty.created', function ($payload) use (&$seen) {
            $seen[] = $payload;
        });

        \Event::listen('erp301.contract.created', function ($payload) use (&$seen) {
            $seen[] = $payload;
        });

        $counterparty = $this->make(array('code' => 'TEST-EVENT'));
        $this->service->save_contract($counterparty->id, array('number' => 'СОБ-1', 'kind' => 'supply'), NULL, 1);

        $this->assertGreaterThanOrEqual(2, count($seen));

        $names = array();
        foreach ($seen as $payload)
        {
            $names[] = is_array($payload) ? $payload['event'] : $payload;
        }

        $this->assertContains('erp301.counterparty.created', $names);
        $this->assertContains('erp301.contract.created', $names);
    }

    public function test_service_is_singleton_and_controller_stays_thin()
    {
        $this->assertSame(\Service_Erp301_Counterparties::instance(), \Service_Erp301_Counterparties::instance());

        // Архитектурная проверка: контроллеры не пишут в БД сами
        foreach (array('Counterparty', 'Contract', 'Api') as $name)
        {
            $class = 'Controller_Erp301_'.$name;
            $this->assertTrue(class_exists($class), $class.' должен существовать');

            $body = file_get_contents(
                \Kohana::find_file('classes', 'Controller/Erp301/'.$name, 'php')
            );

            $this->assertStringNotContainsString('DB::insert', $body, $class.' не должен писать в БД напрямую');
            $this->assertStringNotContainsString('DB::update', $body, $class.' не должен писать в БД напрямую');
        }
    }
}
