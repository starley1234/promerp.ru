<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты модуля технологических карт (erp228_tech_cards).
 *
 * Две группы:
 *   * разбор ответа LLM и офлайн-шаблоны — чистые функции Service_Erp228_Parser,
 *     БД не нужна;
 *   * бизнес-логика Service_Erp228_TechCards на реальной БД: создание карты,
 *     поиск по кириллице, версии документа, операции, архив.
 *
 * Тесты создают карты с префиксом TEST-228 и удаляют их в tearDown,
 * поэтому безопасны для демо-данных. Если таблиц модуля нет — тесты пропускаются.
 */
class Erp228TechCardsTest extends TestCase
{
    /** @var \Service_Erp228_TechCards */
    protected $service;

    /** @var \Service_Erp228_Parser */
    protected $parser;

    /** @var array Созданные тестами карты */
    protected $created = array();

    protected function setUp(): void
    {
        $this->parser  = \Service_Erp228_Parser::instance();
        $this->service = \Service_Erp228_TechCards::instance();

        if ( ! $this->available())
        {
            $this->markTestSkipped('Модуль erp228_tech_cards недоступен — выполните миграции erp228');
        }
    }

    protected function tearDown(): void
    {
        if ( ! $this->available())
        {
            return;
        }

        try
        {
            // Записи, созданные тестом, плюс страховочная уборка по префиксу названия
            $ids = array_filter(array_map('intval', $this->created));

            $sweep = \DB::select('id')->from('erp_228_tech_cards')
                ->where('title', 'LIKE', 'TEST-228%')
                ->execute()->as_array(NULL, 'id');

            $ids = array_values(array_unique(array_merge($ids, array_map('intval', $sweep))));

            // Журнал действий (erp119_audit) тоже получает записи при создании
            // и правке карт: удаление там запрещено триггером, поэтому сначала
            // снимаем защиту на время уборки
            $this->purge_journal($ids);

            if ($ids)
            {
                $documents = \DB::select('id')->from('erp_228_tech_documents')
                    ->where('tech_card_id', 'IN', $ids)
                    ->execute()->as_array(NULL, 'id');

                if ($documents)
                {
                    \DB::delete('erp_228_tech_operations')
                        ->where('document_id', 'IN', array_map('intval', $documents))
                        ->execute();
                }

                \DB::delete('erp_228_tech_documents')->where('tech_card_id', 'IN', $ids)->execute();
                \DB::delete('erp_228_tech_cards')->where('id', 'IN', $ids)->execute();
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
    /**
     * Убрать записи журнала по тестовым техкартам.
     *
     * @param   array   $ids    идентификаторы карт
     * @return  void
     */
    protected function purge_journal(array $ids)
    {
        if ( ! class_exists('Service_Erp119_Partitions'))
        {
            return;
        }

        try
        {
            $uuids = $ids
                ? \DB::select('uuid')->from('erp_228_tech_cards')->where('id', 'IN', $ids)->execute()->as_array(NULL, 'uuid')
                : array();

            $partitions = \Service_Erp119_Partitions::instance();
            $partitions->disable_immutability();

            \DB::delete('erp_119_audit_log')
                ->where('entity_type', 'IN', array('tech_card', 'tech_document'))
                ->where('entity_label', 'LIKE', 'TEST-228%')
                ->execute();

            if ($uuids)
            {
                \DB::delete('erp_119_audit_log')
                    ->where('entity_id', 'IN', array_map('strval', $uuids))
                    ->execute();
            }

            $partitions->enable_immutability();
        }
        catch (\Exception $e)
        {
            // Уборка журнала не должна ломать тест
        }
    }

    protected function available()
    {
        try
        {
            \DB::select('id')->from('erp_228_tech_cards')->limit(1)->execute();

            return TRUE;
        }
        catch (\Exception $e)
        {
            return FALSE;
        }
    }

    /**
     * Создать тестовую карту.
     *
     * @param  array $overrides
     * @return \Model_Erp228_TechCard
     */
    protected function make(array $overrides = array())
    {
        $card = $this->service->create(array_merge(array(
            'title'           => 'TEST-228 Кронштейн крепёжный',
            'source_code'     => "cube([40, 30, 20]);\n// тестовая деталь",
            'production_type' => 'additive',
            'target_type'     => 'parametric',
            'input_params'    => array('материал' => 'PA12', 'партия' => 2),
        ), $overrides));

        $this->created[] = $card->id;

        return $card;
    }

    // ------------------------------------------------------- разбор и шаблоны --

    public function test_parser_reads_json_answer()
    {
        $parsed = $this->parser->parse(json_encode(array(
            'markdown'   => '## Техпроцесс',
            'operations' => array(
                array('name' => 'Резка заготовки', 'duration_sec' => 600, 'cost' => 100, 'params' => array('станок' => 'ленточная пила')),
                array('name' => 'Фрезеровка', 'description' => 'Черновая и чистовая', 'duration' => '25 мин', 'cost' => '1 200,50'),
            ),
        ), JSON_UNESCAPED_UNICODE));

        $this->assertSame('## Техпроцесс', $parsed['content_markdown']);
        $this->assertCount(2, $parsed['operations']);
        $this->assertSame('Резка заготовки', $parsed['operations'][0]['op_name']);
        $this->assertSame(600, $parsed['operations'][0]['duration_sec']);
        $this->assertSame('ленточная пила', $parsed['operations'][0]['params']['станок']);
        $this->assertSame(1500, $parsed['operations'][1]['duration_sec'], '«25 мин» — это 1500 секунд');
        $this->assertSame(1200.50, $parsed['operations'][1]['cost_amount']);
        $this->assertSame(10, $parsed['operations'][0]['sort_order']);
        $this->assertSame(20, $parsed['operations'][1]['sort_order']);
    }

    public function test_parser_strips_markdown_fence_and_extra_text()
    {
        $answer = "Вот техпроцесс:\n\n```json\n{\"markdown\": \"## Итог\", \"operations\": [{\"name\": \"Контроль\", \"time\": \"00:05:00\"}]}\n```\n\nГотово.";

        $parsed = $this->parser->parse($answer);

        $this->assertSame('## Итог', $parsed['content_markdown']);
        $this->assertCount(1, $parsed['operations']);
        $this->assertSame(300, $parsed['operations'][0]['duration_sec']);
    }

    public function test_parser_falls_back_to_markdown_list()
    {
        $answer = "## Техпроцесс\n\n1. Подготовка модели (15 мин)\n2. Печать партии\n- Контроль размеров (10 мин)\n\nОборудование: FDM.";

        $parsed = $this->parser->parse($answer);

        $this->assertCount(3, $parsed['operations']);
        $this->assertSame('Подготовка модели (15 мин)', $parsed['operations'][0]['op_name']);
        $this->assertSame(900, $parsed['operations'][0]['duration_sec']);
        $this->assertSame('Печать партии', $parsed['operations'][1]['op_name']);
        $this->assertSame(0, $parsed['operations'][1]['duration_sec']);
        $this->assertSame(600, $parsed['operations'][2]['duration_sec']);
    }

    public function test_parser_understands_hours_and_comma_decimals()
    {
        $parsed = $this->parser->parse(json_encode(array(
            'operations' => array(
                array('name' => 'Долгая операция', 'duration' => '2,5 ч'),
                array('name' => 'Средняя', 'duration' => '1.5 h'),
                array('name' => 'Короткая', 'duration' => '45 с'),
            ),
        ), JSON_UNESCAPED_UNICODE));

        $this->assertSame(9000, $parsed['operations'][0]['duration_sec']);
        $this->assertSame(5400, $parsed['operations'][1]['duration_sec']);
        $this->assertSame(45, $parsed['operations'][2]['duration_sec']);
    }

    public function test_offline_template_covers_production_types()
    {
        foreach (array('additive', 'mechanical', 'manual') as $type)
        {
            $parsed = $this->parser->offline(array(
                'title'           => 'Деталь '.$type,
                'production_type' => $type,
                'input_params'    => json_encode(array('материал' => 'PA12', 'партия' => 2), JSON_UNESCAPED_UNICODE),
            ));

            $this->assertNotEmpty($parsed['operations'], 'у типа '.$type.' должен быть шаблон');
            $this->assertNotEmpty($parsed['content_markdown']);
            $this->assertStringContainsString('Деталь '.$type, $parsed['content_markdown']);
            $this->assertSame(count($parsed['operations']), count($parsed['structured_data']));

            foreach ($parsed['operations'] as $operation)
            {
                $this->assertGreaterThan(0, $operation['duration_sec']);
                $this->assertArrayHasKey('инструмент', $operation['params']);
                $this->assertSame('PA12', $operation['params']['материал']);
            }
        }
    }

    public function test_template_scales_with_batch_size()
    {
        $single = $this->parser->template_operations('additive', array('партия' => 1));
        $batch  = $this->parser->template_operations('additive', array('партия' => 4));

        $this->assertGreaterThan($single[0]['duration_sec'], $batch[0]['duration_sec']);
        $this->assertGreaterThan($single[0]['cost_amount'], $batch[0]['cost_amount']);
    }

    public function test_prompt_contains_code_and_production_type()
    {
        $prompt = $this->parser->build_prompt(array(
            'title'           => 'Кронштейн',
            'production_type' => 'mechanical',
            'input_params'    => json_encode(array('партия' => 4), JSON_UNESCAPED_UNICODE),
            'source_code'     => 'difference() { cube([10,10,10]); sphere(6); }',
        ));

        $this->assertStringContainsString('Кронштейн', $prompt);
        $this->assertStringContainsString('Механическая обработка', $prompt);
        $this->assertStringContainsString('difference()', $prompt);
        $this->assertStringContainsString('JSON', $prompt);
    }

    // -------------------------------------------------------- бизнес-логика --

    public function test_create_generates_uuid_and_search_text()
    {
        $card = $this->make(array('title' => 'TEST-228 Пластина опорная'));

        $this->assertGreaterThan(0, $card->id);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $card->uuid);
        $this->assertSame('draft', $card->status);
        $this->assertStringContainsString('пластина опорная', $card->search_text);
        $this->assertSame(array('материал' => 'PA12', 'партия' => 2), $card->decode_params());
    }

    public function test_create_requires_title_and_source_code()
    {
        $this->expectException(\ORM_Validation_Exception::class);

        $this->service->create(array('title' => '', 'source_code' => ''));
    }

    public function test_search_finds_card_by_lowercase_cyrillic_term()
    {
        $card = $this->make(array('title' => 'TEST-228 Опора подшипниковая'));

        $result = $this->service->search(array('term' => 'подшипниковая'), 10, 0);

        $found = FALSE;

        foreach ($result['rows'] as $row)
        {
            if ((int) $row->id === (int) $card->id)
            {
                $found = TRUE;
            }
        }

        $this->assertTrue($found, 'поиск должен находить карту по фрагменту названия в нижнем регистре');
    }

    public function test_search_filters_by_status_and_production_type()
    {
        $card = $this->make(array('title' => 'TEST-228 Вал ступенчатый', 'production_type' => 'mechanical'));

        $by_type = $this->service->search(array('production_type' => 'mechanical', 'term' => 'вал ступенчатый'), 10, 0);

        $this->assertSame(1, (int) $by_type['total']);
        $this->assertSame((int) $card->id, (int) $by_type['rows'][0]->id);

        $by_status = $this->service->search(array('status' => 'ready', 'term' => 'вал ступенчатый'), 10, 0);

        $this->assertSame(0, (int) $by_status['total'], 'черновик не должен попадать в фильтр «готово»');
    }

    public function test_status_change_updates_card_and_search_text()
    {
        $card = $this->make(array('title' => 'TEST-228 Статусная деталь'));

        $card = $this->service->set_status($card->id, 'error', 'Модель недоступна');

        $this->assertSame('error', $card->status);
        $this->assertSame('Модель недоступна', $card->error_msg);
        $this->assertStringContainsString('ошибка', $card->search_text);

        $card = $this->service->set_status($card->id, 'ready');

        $this->assertSame('ready', $card->status);
        $this->assertNull($card->error_msg, 'при переходе в «готово» текст ошибки очищается');
    }

    public function test_set_status_rejects_unknown_status()
    {
        $this->expectException(\Kohana_Exception::class);

        $this->service->set_status(1, 'нетакого');
    }

    public function test_generate_creates_document_with_operations()
    {
        $card = $this->make(array('title' => 'TEST-228 Генерация офлайн'));

        $document = $this->service->generate($card->id);

        $this->assertGreaterThan(0, $document->id);
        $this->assertSame(1, (int) $document->is_actual);
        $this->assertSame('offline', $document->llm_model);

        $operations = $document->operations_ordered();

        $this->assertGreaterThan(0, count($operations));
        $this->assertGreaterThan(0, $document->duration_total());
        $this->assertGreaterThan(0, $document->cost_total());

        $card = $this->service->get($card->id);

        $this->assertSame('ready', $card->status, 'после генерации карта готова');
    }

    public function test_regeneration_keeps_single_actual_version()
    {
        $card = $this->make(array('title' => 'TEST-228 Две версии'));

        $first  = $this->service->generate($card->id);
        $second = $this->service->generate($card->id);

        $this->assertNotSame((int) $first->id, (int) $second->id);

        $actual = \DB::select(array(\DB::expr('COUNT(*)'), 'cnt'))
            ->from('erp_228_tech_documents')
            ->where('tech_card_id', '=', $card->id)
            ->where('is_actual', '=', 1)
            ->execute()->get('cnt');

        $this->assertSame(1, (int) $actual, 'актуальная версия у карты ровно одна');

        $this->assertSame(2, count($this->service->versions($card->id)));
        $this->assertSame((int) $second->id, (int) $this->service->actual_document($card->id)->id);
    }

    public function test_update_changes_fields_and_keeps_uuid()
    {
        $card = $this->make(array('title' => 'TEST-228 До правки'));
        $uuid = $card->uuid;

        $card = $this->service->update($card->id, array(
            'title'           => 'TEST-228 После правки',
            'production_type' => 'manual',
        ));

        $this->assertSame('TEST-228 После правки', $card->title);
        $this->assertSame('manual', $card->production_type);
        $this->assertSame($uuid, $card->uuid, 'публичный идентификатор не меняется');
        $this->assertStringContainsString('после правки', $card->search_text);
    }

    public function test_delete_is_soft_and_documents_stay()
    {
        $card = $this->make(array('title' => 'TEST-228 В архив'));

        $document = $this->service->generate($card->id);

        $this->service->delete($card->id);

        $stored = \DB::select('is_deleted')
            ->from('erp_228_tech_cards')
            ->where('id', '=', $card->id)
            ->execute()->get('is_deleted');

        $this->assertSame(1, (int) $stored, 'карта помечается, а не удаляется физически');

        $documents = \DB::select(array(\DB::expr('COUNT(*)'), 'cnt'))
            ->from('erp_228_tech_documents')
            ->where('tech_card_id', '=', $card->id)
            ->execute()->get('cnt');

        $this->assertSame(1, (int) $documents, 'история техпроцесса сохраняется');

        $this->assertSame(0, (int) $this->service->search(array('term' => 'в архив'), 10, 0)['total']);
    }

    public function test_get_accepts_uuid_and_reports_missing_card()
    {
        $card = $this->make(array('title' => 'TEST-228 По uuid'));

        $this->assertSame((int) $card->id, (int) $this->service->get($card->uuid)->id);

        $this->expectException(\Kohana_Exception::class);

        $this->service->get('00000000-0000-4000-8000-000000000000');
    }

    public function test_stats_counts_cards_and_operations()
    {
        $card = $this->make(array('title' => 'TEST-228 Для сводки'));

        $document = $this->service->generate($card->id);

        $stats = $this->service->stats();

        $this->assertGreaterThan(0, $stats['total']);
        $this->assertArrayHasKey('ready', $stats['by_status']);
        $this->assertGreaterThanOrEqual(count($document->operations_ordered()), $stats['operations']);
        $this->assertGreaterThan(0, $stats['cost_total']);
    }
}
