<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты журнала действий (erp119_audit).
 *
 * Журнал append-only, поэтому уборка тестовых записей не может быть обычным
 * DELETE: триггер её запрещает. В tearDown триггер на секциях отключается
 * на время уборки и включается обратно — тот же приём, что в обслуживании
 * DEFAULT-секции (Service_Erp119_Partitions::attach_default()).
 *
 * Тестовые записи помечены модулем `test119` (или признаком в контексте),
 * поэтому демо-данные в общей базе не страдают. Если таблиц модуля нет —
 * тесты пропускаются.
 */
class Erp119AuditTest extends TestCase
{
    /** @var \Service_Erp119_Journal */
    protected $journal;

    /** @var \Service_Erp119_Mapper */
    protected $mapper;

    /** @var \Service_Erp119_Query */
    protected $query;

    /** @var \Service_Erp119_Partitions */
    protected $partitions;

    /** @var string Начало прогона — граница для уборки */
    protected $started_at;

    /** @var int Указатель чтения событий до теста */
    protected $cursor_before = 0;

    /** @var string Путь к файлу-страховке из конфигурации */
    protected $fallback_path;

    /** @var array Техкарты, созданные тестами интеграции */
    protected $cleanup_cards = array();

    protected function setUp(): void
    {
        $this->journal    = \Service_Erp119_Journal::instance();
        $this->mapper     = $this->journal->mapper();
        $this->query      = \Service_Erp119_Query::instance();
        $this->partitions = \Service_Erp119_Partitions::instance();

        if ( ! $this->available())
        {
            $this->markTestSkipped('Модуль erp119_audit недоступен — выполните миграции erp119');
        }

        // С часовым поясом: колонка TIMESTAMPTZ, а сессия БД живёт в UTC
        $this->started_at     = date('c');
        $this->cursor_before  = $this->journal->cursor();
        $this->fallback_path  = $this->journal->fallback_path();

        // Указатель ставим на «сейчас»: каждый тест читает только те события,
        // которые опубликовал сам (иначе тесты влияют друг на друга)
        $this->journal->set_cursor($this->events_max_id());

        \Service_Erp119_Journal::reset_context();
    }

    protected function tearDown(): void
    {
        if ( ! $this->available())
        {
            return;
        }

        try
        {
            $this->table_triggers(FALSE);

            // Тестовые записи: помечены модулем, сущностью или признаком в контексте
            \DB::query(\Database::DELETE, "
                DELETE FROM erp_119_audit_log
                WHERE module = 'test119'
                   OR entity_type LIKE 'probe%'
                   OR (context->>'legacy_call') = 'true'
                   OR (context->>'test119') = 'true'
                   OR (context->'payload'->>'test119') = 'true'
                   OR (
                        occurred_at >= :started
                        AND module IN ('erp119', 'app')
                        AND entity_type IN ('audit_log', 'module_manager', 'erp221.deal.updated')
                      )
            ")->param(':started', $this->started_at)->execute();

            // События шины, опубликованные тестами: помеченные меткой либо
            // опубликованные сервисом модуля в тесте интеграции (TEST-119 …)
            \DB::query(\Database::DELETE, "DELETE FROM erp_208_events WHERE payload LIKE '%test119%' OR payload LIKE '%TEST-119%'")->execute();

            $this->cleanup_tech_cards();

            $this->table_triggers(TRUE);
            $this->drop_empty_old_partitions();

            // Файлы-страховки, созданные тестом (уже разобранные — данные в БД)
            foreach (glob($this->fallback_path.'*') as $file)
            {
                if (filemtime($file) >= strtotime($this->started_at))
                {
                    @unlink($file);
                }
            }

            // Указатель чтения событий возвращаем как было
            \DB::update('erp_119_audit_cursors')
                ->set(array('last_event_id' => $this->cursor_before))
                ->where('name', '=', 'events')
                ->execute();
        }
        catch (\Exception $e)
        {
            // Уборка не должна маскировать ошибку теста
        }
        finally
        {
            \Service_Erp119_Journal::reset_context();
        }
    }

    // =====================================================================
    // Сравнение значений и маскирование
    // =====================================================================

    public function test_diff_finds_only_real_changes()
    {
        $changes = $this->journal->diff(
            array('title' => 'ТК-1', 'count' => '5', 'note' => NULL, 'params' => array('a' => 1)),
            array('title' => 'ТК-2', 'count' => 5, 'note' => '', 'params' => array('a' => 1))
        );

        $fields = array_column($changes, 'field');

        $this->assertContains('title', $fields, 'изменённое значение попадает в diff');
        $this->assertContains('note', $fields, 'NULL и пустая строка — разные значения');
        $this->assertNotContains('count', $fields, 'строка "5" и число 5 — одно и то же');
        $this->assertNotContains('params', $fields, 'одинаковые массивы не считаются изменением');
    }

    public function test_diff_reports_removed_and_added_fields()
    {
        $changes = $this->journal->diff(array('old_field' => 'a'), array('new_field' => 'b'));
        $by_field = array_column($changes, NULL, 'field');

        $this->assertNull($by_field['old_field']['new'], 'исчезнувшее поле — изменение со значением NULL');
        $this->assertNull($by_field['new_field']['old'], 'появившееся поле — изменение без прежнего значения');
    }

    public function test_diff_limits_huge_change_sets()
    {
        $before = array();
        $after  = array();

        for ($i = 0; $i < 60; $i++)
        {
            $before['field_'.$i] = 1;
            $after['field_'.$i]  = 2;
        }

        $changes = $this->journal->diff($before, $after);

        $this->assertCount(51, $changes, 'изменения ограничены 50 полями + пометка');
        $this->assertSame('*', end($changes)['field']);
    }

    public function test_secrets_are_masked_before_write()
    {
        $this->journal->record(array(
            'module'      => 'test119',
            'entity_type' => 'probe',
            'entity_id'   => '1',
            'action'      => 'update',
            'changes'     => array(
                array('field' => 'name', 'old' => 'a', 'new' => 'b'),
                array('field' => 'password', 'old' => 'old-secret', 'new' => 'new-secret'),
                array('field' => 'api_key_hash', 'old' => 'k1', 'new' => 'k2'),
            ),
            'context'     => array('token' => 'secret-token', 'keep' => 'ok'),
        ));

        $row = $this->this_test_row();

        $this->assertNotNull($row, 'запись сохранена');
        $this->assertSame('***', $row['changes'][1]['new'], 'пароль маскируется');
        $this->assertSame('***', $row['changes'][2]['old'], 'поле с «key» в имени маскируется');
        $this->assertSame('***', $row['context']['token'], 'токен в контексте маскируется');
        $this->assertSame('ok', $row['context']['keep'], 'остальной контекст сохраняется');
        $this->assertSame('b', $row['changes'][0]['new'], 'обычное поле пишется как есть');
    }

    public function test_secret_detection_covers_name_variants()
    {
        $this->assertTrue($this->journal->is_secret('PASSWORD_CONFIRM'));
        $this->assertTrue($this->journal->is_secret('user_token'));
        $this->assertTrue($this->journal->is_secret('cvv'));
        $this->assertFalse($this->journal->is_secret('title'));
        $this->assertFalse($this->journal->is_secret('description'));

        // Правило намеренно широкое: имена вида tokenizer_name попадут под маску,
        // «лишняя» маска дешевле утечки
        $this->assertTrue($this->journal->is_secret('tokenizer_name'));
    }

    // =====================================================================
    // Запись
    // =====================================================================

    public function test_record_normalizes_entry()
    {
        $this->journal->record(array(
            'module'      => 'test119',
            'entity_type' => 'probe',
            'entity_id'   => '42',
            'action'      => 'create',
            'entity_label' => 'Пробный объект',
            'message'     => 'Создан пробный объект',
            'changes'     => array(),
            'context'     => array('test119' => 'true'),
        ));

        $row = $this->this_test_row();

        $this->assertSame('test119', $row['module']);
        $this->assertSame('probe', $row['entity_type']);
        $this->assertSame('42', $row['entity_id']);
        $this->assertSame('create', $row['action']);
        $this->assertSame('info', $row['severity'], 'создание — обычная важность');
        $this->assertSame('cli', $row['source'], 'запись из консоли помечается как cli');
        $this->assertNotEmpty($row['request_id'], 'запрос помечается идентификатором для трассировки');
        $this->assertNotEmpty($row['occurred_at']);
    }

    public function test_change_skips_empty_diff_and_writes_when_forced()
    {
        $options = array(
            'module'  => 'test119',
            'context' => array('test119' => 'true'),
        );

        $same = $this->journal->change('probe', '7', 'update', array('a' => 1), array('a' => '1'), $options);

        $this->assertNull($same, 'без изменений запись не создаётся');

        $forced = $this->journal->change('probe', '7', 'update', array('a' => 1), array('a' => 1), $options + array('force' => TRUE));

        $this->assertIsArray($forced, 'принудительная запись создаётся');
        $this->assertNotNull($this->this_test_row());
    }

    public function test_change_writes_before_and_after()
    {
        $this->journal->change('probe', '9', 'status', array('status' => 'draft'), array('status' => 'ready'), array(
            'module'       => 'test119',
            'entity_label' => 'Партия 9',
            'severity'     => 'warning',
            'context'      => array('test119' => 'true'),
        ));

        $row = $this->this_test_row();

        $this->assertSame('warning', $row['severity']);
        $this->assertSame('status', $row['changes'][0]['field']);
        $this->assertSame('draft', $row['changes'][0]['old']);
        $this->assertSame('ready', $row['changes'][0]['new']);
        $this->assertSame('warning', $row['severity']);
        $this->assertStringContainsString('Партия 9', $row['message'], 'сообщение собирается автоматически');
    }

    public function test_record_many_writes_all_entries_in_one_batch()
    {
        $entries = array();

        for ($i = 0; $i < 5; $i++)
        {
            $entries[] = array(
                'module'      => 'test119',
                'entity_type' => 'probe_batch',
                'entity_id'   => (string) $i,
                'action'      => 'create',
                'context'     => array('test119' => 'true'),
            );
        }

        $written = $this->journal->record_many($entries);

        $this->assertSame(5, $written);
        $this->assertSame(5, $this->query->search(array('entity_type' => 'probe_batch'), 10, 0)['total']);
    }

    // =====================================================================
    // Разбор событий шины
    // =====================================================================

    public function test_event_mapping_by_convention()
    {
        $entry = $this->mapper->parse('erp228.card.created', array(
            'id'     => 5,
            'uuid'   => 'uuid-5',
            'title'  => 'ТК-5 Кронштейн',
            'status' => 'draft',
            'at'     => date('Y-m-d H:i:s'),
        ), 42);

        $this->assertSame('erp228', $entry['module']);
        $this->assertSame('tech_card', $entry['entity_type'], 'правило конфигурации переопределяет имя сущности');
        $this->assertSame('create', $entry['action']);
        $this->assertSame('uuid-5', $entry['entity_id'], 'uuid в приоритете перед числовым id — совпадает с записью сервиса модуля');
        $this->assertSame('ТК-5 Кронштейн', $entry['entity_label']);
        $this->assertSame(42, $entry['actor_id'], 'инициатор из шины, если в payload нет пользователя');
        $this->assertSame('erp228.card.created', $entry['context']['event']);
        $this->assertArrayNotHasKey('title', $entry['context']['payload'], 'уже разобранные поля не дублируются в контексте');
    }

    public function test_event_with_entity_id_in_payload()
    {
        $entry = $this->mapper->parse('erp301.contract.updated', array(
            'contract_id' => 77,
            'number'      => 'Д-77/2026',
            'status'      => 'active',
        ), NULL);

        $this->assertSame('erp301', $entry['module']);
        $this->assertSame('contract', $entry['entity_type']);
        $this->assertSame('update', $entry['action']);
        $this->assertSame('77', $entry['entity_id'], 'ключ <сущность>_id разбирается автоматически');
        $this->assertSame('Д-77/2026', $entry['entity_label']);
    }

    public function test_mapper_ignores_noise_events()
    {
        $this->assertNull($this->mapper->parse('erp208_events.published', array('id' => 1)));
        $this->assertNull($this->mapper->parse('erp208.automation.trigger', array()));
        $this->assertNotNull($this->mapper->parse('erp228.card.updated', array('id' => 1, 'title' => 'ТК')));
    }

    public function test_two_segment_event_names_are_parsed()
    {
        $parts = $this->mapper->parse('erp217_notification_center.executed', array('id' => 3, 'title' => 'Письмо'), NULL);

        $this->assertSame('erp217', $parts['module'], 'модуль выделяется из имени с подчёркиванием');
        $this->assertSame('notification_center', $parts['entity_type']);
        $this->assertSame('execute', $parts['action']);
    }

    public function test_action_kind_normalization()
    {
        $cases = array(
            'created'          => 'create',
            'updated'          => 'update',
            'status_changed'   => 'update',
            'deleted'          => 'delete',
            'dashboard_viewed' => 'read',
            'token_created'    => 'create',
            'sent'             => 'send',
            'failed'           => 'fail',
            'approved'         => 'approve',
            'archived'         => 'delete',
        );

        foreach ($cases as $verb => $expected)
        {
            $this->assertSame($expected, $this->mapper->kind($verb), 'глагол '.$verb);
        }
    }

    public function test_reads_are_skipped_unless_entity_tracks_them()
    {
        // Технологические карты просмотры не аудируют (track_reads = 0)
        $this->assertNull($this->mapper->parse('erp228.card.viewed', array('id' => 1, 'title' => 'ТК')));

        // Пользователи — аудируются (track_reads = 1 в erp_119_audit_settings)
        $entry = $this->mapper->parse('erp100.user.viewed', array('id' => 5, 'name' => 'admin'));

        $this->assertNotNull($entry);
        $this->assertSame('read', $entry['action']);
    }

    public function test_severity_mapping_for_events()
    {
        $delete = $this->mapper->parse('erp228.card.deleted', array('id' => 1, 'title' => 'ТК'));
        $view   = $this->mapper->parse('erp100.user.viewed', array('id' => 1, 'name' => 'admin'));
        $custom = $this->mapper->parse('erp228.card.updated', array('id' => 1, 'title' => 'ТК', 'severity' => 'critical'));

        $this->assertSame('warning', $delete['severity'], 'удаление важнее обычной правки');
        $this->assertSame('notice', $view['severity'], 'просмотр — заметное, но не важное');
        $this->assertSame('critical', $custom['severity'], 'важность из payload имеет приоритет');
    }

    // =====================================================================
    // Дочитывание шины (outbox)
    // =====================================================================

    public function test_sync_reads_new_events_and_moves_cursor()
    {
        $event_id = $this->push_event('erp228.card.status', array(
            'id'     => 123,
            'title'  => 'ТК-123 Синхронизация',
            'status' => 'ready',
        ));

        $this->journal->set_cursor($event_id - 1);

        $stat = \Service_Erp119_Sync::instance()->run(100, FALSE);

        $this->assertSame(1, $stat['journaled'], 'своё событие записано, чужие не читаются');
        $this->assertSame($event_id, $stat['cursor'], 'указатель сдвигается после записи');
        $this->assertSame($event_id, $this->journal->cursor());

        $row = $this->query->search(array('entity_type' => 'tech_card', 'entity_id' => '123'), 5, 0)['rows'][0];

        $this->assertSame('status', $row['action']);
        $this->assertSame('ТК-123 Синхронизация', $row['entity_label']);
        $this->assertSame('event', $row['source']);
    }

    public function test_sync_does_not_double_write()
    {
        $event_id = $this->push_event('erp228.card.created', array('id' => 321, 'title' => 'ТК-321'));

        $this->journal->set_cursor($event_id - 1);

        $first = \Service_Erp119_Sync::instance()->run(100, FALSE);

        $after_first = $this->query->search(array('entity_type' => 'tech_card', 'entity_id' => '321'), 5, 0)['total'];

        $second = \Service_Erp119_Sync::instance()->run(100, FALSE);

        $after_second = $this->query->search(array('entity_type' => 'tech_card', 'entity_id' => '321'), 5, 0)['total'];

        $this->assertGreaterThanOrEqual(1, $first['journaled'], 'своё событие записано');
        $this->assertSame(0, $second['journaled'], 'повторный запуск ничего не записывает');
        $this->assertSame($after_first, $after_second, 'повторный запуск не задваивает журнал');
    }

    public function test_sync_from_start_does_not_duplicate_imported_events()
    {
        $event_id = $this->push_event('erp228.card.created', array('id' => 777, 'title' => 'ТК-777 Повторный перенос'));

        $this->journal->set_cursor($event_id - 1);
        \Service_Erp119_Sync::instance()->run(5000, FALSE);

        $filter = array('entity_type' => 'tech_card', 'entity_id' => '777');
        $after_first = $this->query->search($filter, 5, 0)['total'];

        // Повторное чтение шины с начала: так делают после правки правил
        // разбора. Записи не должны задвоиться — событие уже помечено своим
        // номером в журнале.
        $this->journal->set_cursor(0);
        $stat = \Service_Erp119_Sync::instance()->run(5000, FALSE);

        $this->assertSame(1, $after_first, 'первое чтение записало ровно одну запись');
        $this->assertSame(1, $this->query->search($filter, 5, 0)['total'], 'повторный перенос не задваивает журнал');
        $this->assertGreaterThanOrEqual(1, $stat['known'], 'уже перенесённые события посчитаны отдельно');
    }

    public function test_sync_dry_run_does_not_write()
    {
        $event_id = $this->push_event('erp228.card.updated', array('id' => 654, 'title' => 'ТК-654'));

        $this->journal->set_cursor($event_id - 1);

        $stat = \Service_Erp119_Sync::instance()->run(100, TRUE);

        $this->assertGreaterThanOrEqual(1, count($stat['entries']));
        $this->assertSame(0, $stat['journaled']);
        $this->assertSame($event_id - 1, $this->journal->cursor(), 'режим проверки не двигает указатель');
        $this->assertSame(0, $this->query->search(array('entity_type' => 'tech_card', 'entity_id' => '654'), 5, 0)['total']);
    }

    // =====================================================================
    // Совместимость со старым Audit::log()
    // =====================================================================

    public function test_audit_log_writes_to_journal()
    {
        \Audit::log('module_manager', 'status_changed', 42, array('old_status' => 'disabled', 'new_status' => 'enabled'));

        $row = $this->query->search(array('entity_type' => 'module_manager'), 5, 0)['rows'][0];

        $this->assertSame('update', $row['action'], 'status_changed → update');
        $this->assertSame('42', $row['entity_id']);
        $this->assertSame('notice', $row['severity']);
        $this->assertTrue($row['context']['legacy_call']);
        $this->assertSame('enabled', $row['context']['data']['new_status']);
    }

    public function test_audit_log_understands_event_style_category_with_before_after()
    {
        \Audit::log('erp221.deal.updated', 7, array('status' => 'won'), array('status' => 'lead'));

        $row = $this->query->search(array('entity_type' => 'erp221.deal.updated'), 5, 0)['rows'][0];

        $this->assertSame('7', $row['entity_id'], 'id из второго аргумента');
        $this->assertSame('update', $row['action']);
        $this->assertSame('lead', $row['changes'][0]['old'], 'до/после разбираются из аргументов');
        $this->assertSame('won', $row['changes'][0]['new']);
        $this->assertSame('erp221', $row['module']);
    }

    // =====================================================================
    // Интеграция: сервис модуля пишет в журнал
    // =====================================================================

    public function test_tech_card_changes_are_journaled_with_before_and_after()
    {
        if ( ! class_exists('Service_Erp228_TechCards'))
        {
            $this->markTestSkipped('Модуль технологических карт недоступен');
        }

        $cards = \Service_Erp228_TechCards::instance();

        $card = $cards->create(array(
            'title'           => 'TEST-119 Интеграция журнала',
            'source_code'     => 'cube([10, 10, 10]);',
            'production_type' => 'mechanical',
            'target_type'     => 'parametric',
            'input_params'    => array('партия' => 1),
        ));

        $this->cleanup_cards[] = $card->id;

        $created = $this->query->timeline('tech_card', $card->uuid, 5, 0);

        $this->assertSame(1, $created['total'], 'создание карты попало в журнал');
        $this->assertSame('create', $created['rows'][0]['action']);
        $this->assertSame('erp228', $created['rows'][0]['module']);
        $this->assertSame('TEST-119 Интеграция журнала', $created['rows'][0]['entity_label']);

        $cards->update($card->id, array('title' => 'TEST-119 Интеграция журнала (правка)'));

        $updated = $this->query->timeline('tech_card', $card->uuid, 5, 0);

        $this->assertSame(2, $updated['total'], 'правка карты попала в журнал');
        $this->assertSame('update', $updated['rows'][0]['action']);
        $this->assertSame('title', $updated['rows'][0]['changes'][0]['field'], 'видно, что именно изменилось');

        $cards->delete($card->id);

        $this->assertSame(3, $this->query->timeline('tech_card', $card->uuid, 5, 0)['total'], 'архивирование карты тоже в журнале');
    }

    // =====================================================================
    // Чтение
    // =====================================================================

    public function test_search_filters_pagination_and_ordering()
    {
        for ($i = 1; $i <= 3; $i++)
        {
            $this->journal->record(array(
                'module'      => 'test119',
                'entity_type' => 'probe_search',
                'entity_id'   => (string) $i,
                'action'      => $i === 3 ? 'delete' : 'update',
                'severity'    => $i === 3 ? 'warning' : 'info',
                'changes'     => array(array('field' => 'status', 'old' => 'a', 'new' => 'b')),
                'context'     => array('test119' => 'true'),
            ));
        }

        $all = $this->query->search(array('entity_type' => 'probe_search'), 10, 0);

        $this->assertSame(3, $all['total']);
        $this->assertSame('3', $all['rows'][0]['entity_id'], 'сначала самые свежие записи');

        $deleted = $this->query->search(array('entity_type' => 'probe_search', 'action' => 'delete'), 10, 0);

        $this->assertSame(1, $deleted['total']);
        $this->assertSame('warning', $deleted['rows'][0]['severity']);

        $page = $this->query->search(array('entity_type' => 'probe_search'), 2, 2);

        $this->assertSame(3, $page['total'], 'общее число не зависит от страницы');
        $this->assertCount(1, $page['rows']);

        $today = $this->query->search(array(
            'entity_type' => 'probe_search',
            'from'        => date('Y-m-d'),
            'to'          => date('Y-m-d'),
        ), 10, 0);

        $this->assertSame(3, $today['total'], 'фильтр «за сегодня» захватывает записи дня');
        $this->assertSame(0, $this->query->search(array(
            'entity_type' => 'probe_search',
            'from'        => date('Y-m-d', strtotime('+2 day')),
        ), 10, 0)['total'], 'будущий период пуст');

        $with_changes = $this->query->search(array('entity_type' => 'probe_search', 'has_changes' => TRUE), 10, 0);

        $this->assertSame(3, $with_changes['total']);
        $this->assertEmpty($this->query->search(array('entity_type' => 'probe_search', 'field' => 'нет_такого'), 10, 0)['rows']);
        $this->assertSame(3, $this->query->search(array('entity_type' => 'probe_search', 'field' => 'status'), 10, 0)['total']);
    }

    public function test_search_by_cyrillic_term_is_case_insensitive()
    {
        $this->journal->record(array(
            'module'       => 'test119',
            'entity_type'  => 'probe_cyr',
            'entity_id'    => '1',
            'action'       => 'create',
            'entity_label' => 'ООО «РОМАШКА» — договор',
            'context'      => array('test119' => 'true'),
        ));

        $found = $this->query->search(array('entity_type' => 'probe_cyr', 'term' => 'ромашка'), 10, 0);

        $this->assertSame(1, $found['total'], 'поиск по кириллице не зависит от регистра');
        $this->assertSame(0, $this->query->search(array('entity_type' => 'probe_cyr', 'term' => 'василёк'), 10, 0)['total']);
    }

    public function test_timeline_returns_history_of_single_entity()
    {
        foreach (array('create', 'update', 'delete') as $action)
        {
            $this->journal->record(array(
                'module'      => 'test119',
                'entity_type' => 'probe_timeline',
                'entity_id'   => '77',
                'action'      => $action,
                'context'     => array('test119' => 'true'),
            ));
        }

        $this->journal->record(array(
            'module'      => 'test119',
            'entity_type' => 'probe_timeline',
            'entity_id'   => '78',
            'action'      => 'create',
            'context'     => array('test119' => 'true'),
        ));

        $timeline = $this->query->timeline('probe_timeline', '77', 10, 0);

        $this->assertSame(3, $timeline['total'], 'история только этого объекта');
        $this->assertSame('delete', $timeline['rows'][0]['action']);
    }

    public function test_stats_and_facets_include_test_records()
    {
        $this->journal->record(array(
            'module'      => 'test119',
            'entity_type' => 'probe_stats',
            'entity_id'   => '1',
            'action'      => 'delete',
            'severity'    => 'warning',
            'context'     => array('test119' => 'true'),
        ));

        $stats = $this->query->stats(30);

        $this->assertGreaterThan(0, $stats['period']);
        $this->assertGreaterThan(0, $stats['severity']['warning']);
        $this->assertNotEmpty($stats['top_actions']);
        $this->assertNotEmpty($this->query->facets()['actions']);
        $this->assertNotEmpty($this->query->settings(), 'справочник сущностей заполнен миграцией');
    }

    public function test_inventory_and_export()
    {
        $this->journal->record(array(
            'module'      => 'test119',
            'entity_type' => 'probe_export',
            'entity_id'   => '1',
            'action'      => 'create',
            'context'     => array('test119' => 'true'),
        ));

        $inventory = $this->partitions->inventory();
        $months    = array_filter(array_column($inventory, 'month'));

        $this->assertContains(date('Y-m'), array_values($months), 'секция текущего месяца существует');
        $this->assertNotEmpty(array_filter($inventory, function ($row) { return $row['is_default']; }));

        $csv = \Service_Erp119_Export::instance()->csv(array('entity_type' => 'probe_export'));

        $this->assertStringContainsString('probe_export', $csv, 'выгрузка содержит записи');
        $this->assertStringContainsString('Время', $csv, 'заголовок выгрузки на месте');
        $this->assertStringEndsWith('.csv', \Service_Erp119_Export::instance()->filename());

        $audit = $this->query->search(array('module' => 'erp119', 'action' => 'export'), 5, 0);

        $this->assertGreaterThan(0, $audit['total'], 'выгрузка журнала сама попадает в журнал');
    }

    public function test_by_request_groups_records_of_one_request()
    {
        $request_id = 'req-'.bin2hex(random_bytes(4));

        for ($i = 0; $i < 2; $i++)
        {
            $this->journal->record(array(
                'module'      => 'test119',
                'entity_type' => 'probe_request',
                'entity_id'   => (string) $i,
                'action'      => 'update',
                'request_id'  => $request_id,
                'context'     => array('test119' => 'true'),
            ));
        }

        $rows = $this->query->by_request($request_id);

        $this->assertCount(2, $rows, 'одна операция пользователя — одна цепочка записей');
        $this->assertSame('probe_request', $rows[0]['entity_type']);
        $this->assertCount(0, $this->query->by_request('req-нет-такого'), 'неизвестный запрос — пустой список');
    }

    public function test_find_returns_record_with_decoded_json()
    {
        $this->journal->record(array(
            'module'      => 'test119',
            'entity_type' => 'probe_find',
            'entity_id'   => '9',
            'action'      => 'update',
            'changes'     => array(array('field' => 'status', 'old' => 'a', 'new' => 'b')),
            'context'     => array('test119' => 'true'),
        ));

        $row   = $this->this_test_row();
        $found = $this->query->find($row['id']);

        $this->assertSame($row['id'], $found['id']);
        $this->assertSame('status', $found['changes'][0]['field'], 'changes разбираются в массив');
        $this->assertNull($this->query->find(999999999), 'несуществующая запись — NULL');
    }

    public function test_activity_covers_requested_days()
    {
        $this->journal->record(array(
            'module'      => 'test119',
            'entity_type' => 'probe_activity',
            'entity_id'   => '1',
            'action'      => 'create',
            'context'     => array('test119' => 'true'),
        ));

        $activity = $this->query->activity(7);

        $this->assertCount(7, $activity, 'график заполнен по всем дням периода');
        $this->assertSame(date('Y-m-d'), array_key_last($activity));
        $this->assertGreaterThan(0, $activity[date('Y-m-d')], 'сегодняшняя запись попала в разрез по дням');
    }

    public function test_settings_describe_audited_entities()
    {
        $settings = $this->query->settings();
        $by_entity = array_column($settings, NULL, 'entity_type');

        $this->assertNotEmpty($settings, 'справочник аудита заполнен миграцией');
        $this->assertArrayHasKey('tech_card', $by_entity);
        $this->assertSame(0, (int) $by_entity['tech_card']['track_reads'], 'просмотры техкарт не аудируются');
        $this->assertSame(1, (int) $by_entity['user']['track_reads'], 'просмотры пользователей аудируются');
    }

    // =====================================================================
    // Неизменяемость, файл-страховка, секции
    // =====================================================================

    public function test_journal_is_append_only()
    {
        $this->journal->record(array(
            'module'      => 'test119',
            'entity_type' => 'probe_immutable',
            'entity_id'   => '1',
            'action'      => 'create',
            'context'     => array('test119' => 'true'),
        ));

        $error = NULL;

        try
        {
            \DB::query(\Database::UPDATE, "UPDATE erp_119_audit_log SET action = 'changed' WHERE entity_type = 'probe_immutable'")->execute();
        }
        catch (\Exception $e)
        {
            $error = $e->getMessage();
        }

        $this->assertNotNull($error, 'UPDATE в журнале запрещён');
        $this->assertStringContainsString('append-only', $error);

        $error = NULL;

        try
        {
            \DB::query(\Database::DELETE, "DELETE FROM erp_119_audit_log WHERE entity_type = 'probe_immutable'")->execute();
        }
        catch (\Exception $e)
        {
            $error = $e->getMessage();
        }

        $this->assertNotNull($error, 'DELETE в журнале запрещён');
        $this->assertSame(1, $this->query->search(array('entity_type' => 'probe_immutable'), 5, 0)['total']);
    }

    public function test_failed_write_goes_to_fallback_file_and_replays()
    {
        $method = new \ReflectionMethod($this->journal, 'fallback');
        $method->setAccessible(TRUE);

        $row = $this->journal->normalize(array(
            'module'      => 'test119',
            'entity_type' => 'probe_fallback',
            'entity_id'   => '1',
            'action'      => 'create',
            'changes'     => array(),
            'context'     => array('test119' => 'true'),
        ));

        $method->invoke($this->journal, $row, new \Exception('тестовый сбой записи'));

        $this->assertFileExists($this->fallback_path, 'запись сохранена в файл-страховку');
        $line = trim(file_get_contents($this->fallback_path));
        $this->assertStringContainsString('probe_fallback', $line);

        $result = $this->journal->replay();

        $this->assertSame(1, $result['imported'], 'запись вернулась в БД');
        $this->assertSame(1, $this->query->search(array('entity_type' => 'probe_fallback'), 5, 0)['total']);
        $this->assertFileDoesNotExist($this->fallback_path, 'разобранный файл переименован');
    }

    public function test_default_partition_is_parsed_into_month_partition()
    {
        // Дата заведомо старше всех созданных секций — запись обязана попасть в DEFAULT
        $past = date('Y-m-d H:i:s', strtotime('-5 years'));

        $this->journal->record(array(
            'module'      => 'test119',
            'entity_type' => 'probe_default',
            'entity_id'   => '1',
            'action'      => 'create',
            'occurred_at' => $past,
            'context'     => array('test119' => 'true'),
        ));

        $month = date('Y-m', strtotime('-5 years'));

        $this->assertArrayHasKey($month, $this->partitions->default_backlog(), 'запись в DEFAULT-секции');

        $result = $this->partitions->attach_default();

        $this->assertGreaterThanOrEqual(1, $result['moved'], 'записи перенесены');
        $this->assertArrayNotHasKey($month, $this->partitions->default_backlog(), 'DEFAULT разобран');
        $this->assertSame(1, $this->query->search(array('entity_type' => 'probe_default'), 5, 0)['total'], 'запись доступна через родительскую таблицу');
        $this->assertIsArray($this->partitions->ensure(0, 0), 'задача обслуживания секций идемпотентна');
    }

    // =====================================================================
    // Помощники
    // =====================================================================

    /**
     * Первая тестовая запись по метке.
     *
     * @return  array|null
     */
    protected function this_test_row()
    {
        $rows = $this->query->search(array('module' => 'test119'), 5, 0)['rows'];

        return $rows ? $rows[0] : NULL;
    }

    /**
     * Убрать техкарты, созданные тестом интеграции, и записи журнала по ним.
     *
     * @return  void
     */
    protected function cleanup_tech_cards()
    {
        if ( ! $this->cleanup_cards OR ! $this->available())
        {
            return;
        }

        $ids = array_filter(array_map('intval', $this->cleanup_cards));

        $sweep = \DB::select('id')->from('erp_228_tech_cards')
            ->where('title', 'LIKE', 'TEST-119%')
            ->execute()->as_array(NULL, 'id');

        $ids = array_values(array_unique(array_merge($ids, array_map('intval', $sweep))));

        $documents = \DB::select('id')->from('erp_228_tech_documents')
            ->where('tech_card_id', 'IN', $ids)
            ->execute()->as_array(NULL, 'id');

        if ($documents)
        {
            \DB::delete('erp_228_tech_operations')->where('document_id', 'IN', array_map('intval', $documents))->execute();
            \DB::delete('erp_228_tech_documents')->where('tech_card_id', 'IN', $ids)->execute();
        }

        \DB::delete('erp_228_tech_cards')->where('id', 'IN', $ids)->execute();

        \DB::delete('erp_119_audit_log')
            ->where('entity_label', 'LIKE', 'TEST-119%')
            ->execute();
    }

    /**
     * Максимальный идентификатор события шины.
     *
     * @return  int
     */
    protected function events_max_id()
    {
        return (int) \DB::select(array(\DB::expr('COALESCE(MAX(id), 0)'), 'max'))
            ->from('erp_208_events')
            ->execute()
            ->get('max', 0);
    }

    /**
     * Опубликовать событие в шину (erp_208_events) и вернуть его id.
     *
     * @param   string  $name
     * @param   array   $payload
     * @return  int
     */
    protected function push_event($name, array $payload)
    {
        // Метка в payload: уборка событий шины и записей журнала по ней точная,
        // независимо от того, когда событие реально было опубликовано
        $payload['test119'] = 'true';

        $result = \DB::insert('erp_208_events', array('event_name', 'initiator_id', 'payload', 'created_at'))
            ->values(array($name, 1, json_encode($payload, JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s')))
            ->execute();

        return (int) $result[0];
    }

    /**
     * Включить/выключить триггер неизменяемости на всех секциях.
     *
     * Прямое обслуживание журнала доступно только владельцу таблиц и не имеет
     * пользовательского интерфейса: правка журнала — исключительная операция.
     *
     * @param   bool    $enabled
     * @return  void
     */
    protected function table_triggers($enabled)
    {
        if ($enabled)
        {
            $this->partitions->enable_immutability();
        }
        else
        {
            $this->partitions->disable_immutability();
        }
    }

    /**
     * Удалить пустые секции, созданные тестом (месяцы вне рабочего окна).
     *
     * @return  void
     */
    protected function drop_empty_old_partitions()
    {
        $border = date('Y-m-01', strtotime('-6 month'));

        foreach ($this->partitions->inventory() as $partition)
        {
            if ($partition['is_default'] OR $partition['month'] === NULL OR $partition['rows'] > 0)
            {
                continue;
            }

            if ($partition['month'].'-01' < $border)
            {
                \DB::query(NULL, 'DROP TABLE IF EXISTS '.$partition['partition'])->execute();
            }
        }
    }

    /**
     * Есть ли таблицы модуля.
     *
     * @return  bool
     */
    protected function available()
    {
        static $available;

        if ($available === NULL)
        {
            try
            {
                $available = (bool) \DB::select(array(\DB::expr('COUNT(*)'), 'cnt'))
                    ->from('information_schema.tables')
                    ->where('table_name', '=', 'erp_119_audit_log')
                    ->execute()
                    ->get('cnt');
            }
            catch (\Exception $e)
            {
                $available = FALSE;
            }
        }

        return $available;
    }
}
