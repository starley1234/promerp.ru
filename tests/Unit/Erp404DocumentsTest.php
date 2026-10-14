<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Документы и печать (erp404_documents).
 *
 * Тест держит то, ради чего модуль существует: бумага не должна меняться задним
 * числом. Отсюда проверки версии шаблона, снимка выпуска, запрета правок после
 * выпуска и журнала печати. Тестовые записи узнаются по префиксу `ТЕСТ-404`.
 */
class Erp404DocumentsTest extends TestCase
{
    /** @var \Service_Erp404_Documents */
    protected $documents;

    /** @var \Service_Erp404_Templates */
    protected $templates;

    protected function setUp(): void
    {
        if ( ! $this->available())
        {
            $this->markTestSkipped('Модуль документов недоступен — выполните миграции erp404');
            return;
        }

        $this->documents = \Service_Erp404_Documents::instance();
        $this->templates = \Service_Erp404_Templates::instance();
    }

    protected function tearDown(): void
    {
        if ( ! $this->available())
        {
            return;
        }

        try
        {
            \DB::delete('erp_404_documents')->where('template_code', 'LIKE', 'ТЕСТ-404%')->execute();
            \DB::delete('erp_404_documents')->where('note', 'LIKE', '%ТЕСТ-404%')->execute();
            \DB::delete('erp_404_templates')->where('code', 'LIKE', 'ТЕСТ-404%')->execute();
        }
        catch (\Exception $e)
        {
            // уборка не должна прятать настоящую ошибку теста
        }
    }

    // =========================================================================
    //  Шаблоны
    // =========================================================================

    public function test_seeded_templates_are_searchable()
    {
        foreach (array('ORDER-SUPPLIER', 'RECEIPT-IN', 'PROD-ORDER', 'QUALITY-PASSPORT', 'SHIFT-ASSIGNMENT') as $code)
        {
            $template = $this->templates->find_by_code($code);

            $this->assertNotNull($template, 'стартовый шаблон '.$code.' на месте');
            $this->assertTrue($template->is_active(), $code.' включён');
            $this->assertSame(1, (int) $template->version, $code.' — первая версия');
            $this->assertNotEmpty($template->placeholders(), $code.' использует подстановки');
            $this->assertTrue(
                \Service_Erp404_Sources::instance()->supports($template->ref_type),
                $code.' привязан к поддержанному типу объекта'
            );
        }

        $found = $this->templates->search(array('term' => 'ORDER-SUPPLIER'));

        $this->assertGreaterThan(0, count($found), 'поиск по коду шаблона работает');
    }

    public function test_shift_assignment_uses_human_date_and_dispatch_board()
    {
        $source = \Service_Erp404_Sources::instance();

        $this->assertTrue($source->supports('erp408_shift_day'));

        $data = $source->fetch('erp408_shift_day', date('Y-m-d'));
        $this->assertSame(date('d.m.Y'), $data['day']);
        $this->assertIsArray($data['lines']);
        $this->assertArrayHasKey('operations', $data);

        $reason = $this->reason(function () use ($source) {
            $source->fetch('erp408_shift_day', 'внутренний-id');
        });
        $this->assertSame('ref_not_found', $reason, 'вместо внутреннего ID нужна понятная ISO-дата');

        $board_view = file_get_contents(APPPATH.'modules/erp408_shopfloor/views/erp408/index/index.php');
        $this->assertStringContainsString('template_code=SHIFT-ASSIGNMENT', $board_view, 'форма доступна с диспетчерской доски');
        $this->assertStringContainsString('ref_id=', $board_view, 'дата передаётся в форму без ручного ID');
    }

    public function test_template_with_unknown_placeholder_is_rejected()
    {
        $reason = $this->reason(function () {
            $this->templates->create(array(
                'code' => 'ТЕСТ-404-BAD',
                'name' => 'Плохой шаблон',
                'body' => '<p>{{number}}</p>',
            ));
        });

        $this->assertSame('unknown_placeholder', $reason, 'подстановка без пути не сохраняется');
    }

    public function test_template_version_grows_with_body()
    {
        $template = $this->templates->create(array(
            'code' => 'ТЕСТ-404-VER',
            'name' => 'Версия',
            'body' => '<p>{{document.number}}</p>',
        ));

        $this->assertSame(1, (int) $template->version);

        // Правка названия версию не поднимает: печать от неё не меняется.
        $template = $this->templates->update($template->id, array('name' => 'Версия 2'));

        $this->assertSame(1, (int) $template->version);

        $template = $this->templates->update($template->id, array('body' => '<p>Документ {{document.number}}</p>'));

        $this->assertSame(2, (int) $template->version, 'правка тела поднимает версию');
    }

    // =========================================================================
    //  Жизненный цикл документа
    // =========================================================================

    public function test_document_lifecycle_number_and_print_journal()
    {
        $template = $this->make_template();

        $document = $this->documents->create(array(
            'template_id' => $template->id,
            'title'       => 'Проверка жизненного цикла',
            'note'        => 'ТЕСТ-404: автоматический тест',
        ));

        $this->assertSame('draft', (string) $document->status);
        $this->assertMatchesRegularExpression('/^ДК-\d{4}-\d{6}$/', (string) $document->number, 'номер вида ДК-ГГГГ-НННННН');
        $this->assertSame(NULL, $document->rendered, 'у черновика снимка нет');
        $this->assertSame(0, (int) $document->print_count);

        // Печатать черновик нельзя: на бумагу ушло бы пустое место.
        $reason = $this->reason(function () use ($document) {
            $this->documents->print_document($document->id, NULL, 1);
        });

        $this->assertSame('not_issued', $reason, 'черновик не печатается');

        $document = $this->documents->issue($document->id, NULL, array('payload' => array('ref' => array('custom' => 'Партия 12'))));

        $this->assertSame('issued', (string) $document->status);
        $this->assertSame(1, (int) $document->template_version, 'запомнена версия шаблона');
        $this->assertStringContainsString('ДК-', (string) $document->rendered, 'снимок собран');
        $this->assertStringContainsString('Партия 12', (string) $document->rendered, 'данные подставлены');

        $document = $this->documents->print_document($document->id, NULL, 2, 'печать для склада');

        $this->assertSame('printed', (string) $document->status);
        $this->assertSame(1, (int) $document->print_count, 'счётчик печати растёт');

        $document = $this->documents->print_document($document->id, NULL, 1);

        $this->assertSame(2, (int) $document->print_count, 'повторная печать учитывается');

        $prints = $this->documents->prints($document->id);
        $copies = array();

        foreach ($prints as $print)
        {
            $copies[] = $print->copies();
        }

        sort($copies);

        $this->assertSame(2, count($prints), 'журнал печати хранит оба вывода');
        $this->assertSame(array(1, 2), $copies, 'в журнале — число копий каждой печати');

        $document = $this->documents->archive($document->id);

        $this->assertSame('archived', (string) $document->status);

        $reason = $this->reason(function () use ($document) {
            $this->documents->cancel($document->id, 'передумали', NULL);
        });

        $this->assertSame('invalid_transition', $reason, 'архивный документ не отменяется');
    }

    public function test_issued_document_cannot_be_rewritten()
    {
        $document = $this->documents->create(array(
            'template_id' => $this->make_template()->id,
            'note'        => 'ТЕСТ-404: автоматический тест',
        ));

        $reason = $this->reason(function () use ($document) {
            $this->documents->update($document->id, array('title' => 'Другое'), NULL);
        });

        $this->assertSame(NULL, $reason, 'черновик правится свободно');

        $document = $this->documents->issue($document->id, NULL, array('payload' => array('ref' => array('custom' => 'ok'))));

        $reason = $this->reason(function () use ($document) {
            $this->documents->update($document->id, array('title' => 'Подмена'), NULL);
        });

        $this->assertSame('invalid_transition', $reason, 'выпущенный документ не переписывается');

        $reason = $this->reason(function () use ($document) {
            $this->documents->issue($document->id, NULL, array('payload' => array('ref' => array('custom' => 'ещё раз'))));
        });

        $this->assertSame('invalid_transition', $reason, 'перевыпуск запрещён: снимок не подменяется');

        $reason = $this->reason(function () use ($document) {
            $this->documents->cancel($document->id, '', NULL);
        });

        $this->assertSame('invalid_argument', $reason, 'отмена без причины не принимается');

        $document = $this->documents->cancel($document->id, 'ошиблись шаблоном', NULL);

        $this->assertSame('cancelled', (string) $document->status);
        $this->assertStringContainsString('ошиблись шаблоном', (string) $document->note);
    }

    public function test_template_swap_does_not_change_issued_document()
    {
        $template = $this->make_template();

        $document = $this->documents->create(array(
            'template_id' => $template->id,
            'note'        => 'ТЕСТ-404: автоматический тест',
        ));

        $document = $this->documents->issue($document->id, NULL, array('payload' => array('ref' => array('custom' => 'до правки'))));
        $rendered = (string) $document->rendered;

        // Шаблон переписывают после выпуска — бумага остаётся прежней.
        $this->templates->update($template->id, array('body' => '<p>СОВСЕМ ДРУГОЙ ТЕКСТ {{document.number}}</p>'));

        $again = $this->documents->get($document->id);

        $this->assertSame($rendered, (string) $again->rendered, 'снимок выпуска не зависит от правок шаблона');
        $this->assertSame(1, (int) $again->template_version, 'в документе — версия, по которой печатали');
        $this->assertStringContainsString('до правки', $rendered);
    }

    // =========================================================================
    //  Подстановки
    // =========================================================================

    public function test_renderer_substitutes_rows_and_escapes_html()
    {
        $renderer = \Service_Erp404_Renderer::instance();

        $html = $renderer->render(
            '<h1>{{ref.number}}</h1>{{#rows ref.lines}}<tr><td>{{index}}</td><td>{{item_name}}</td></tr>{{/rows}}',
            array('ref' => array(
                'number' => 'ЗК-1 <b>',
                'lines'  => array(
                    array('item_name' => 'Болт'),
                    array('item_name' => 'Гайка'),
                ),
            ))
        );

        $this->assertStringContainsString('ЗК-1 &lt;b&gt;', $html, 'значения экранируются');
        $this->assertStringContainsString('<td>1</td><td>Болт</td>', $html);
        $this->assertStringContainsString('<td>2</td><td>Гайка</td>', $html, '{{index}} нумерует строки');

        $reason = $this->reason(function () use ($renderer) {
            $renderer->render('<p>{{ref.нет_такого_поля}}</p>', array('ref' => array('number' => '1')));
        });

        $this->assertSame('unknown_placeholder', $reason, 'протухшая подстановка — отказ, а не пустое место');
    }

    public function test_stats_count_documents()
    {
        $template = $this->make_template();

        $document = $this->documents->create(array(
            'template_id' => $template->id,
            'note'        => 'ТЕСТ-404: автоматический тест',
        ));

        $this->documents->issue($document->id, NULL, array('payload' => array('ref' => array('custom' => 'x'))));
        $this->documents->print_document($document->id, NULL, 3);

        $stats = $this->documents->stats(array('from' => date('Y-m-d'), 'to' => date('Y-m-d')));

        $this->assertGreaterThanOrEqual(1, $stats['total']);
        $this->assertGreaterThanOrEqual(1, $stats['issued']);
        $this->assertGreaterThanOrEqual(1, $stats['print_runs']);

        $by_type = $this->documents->by_ref_type(array('from' => date('Y-m-d'), 'to' => date('Y-m-d')));

        $this->assertGreaterThan(0, count($by_type), 'разрез по типам объектов считается');
    }

    // =========================================================================
    //  Агентский доступ
    // =========================================================================

    /**
     * Инструменты MCP объявлены: чтение, выпуск и печать, шаблоны.
     */
    public function test_mcp_tools_are_declared()
    {
        if ( ! class_exists('\Service_Erp804_Registry'))
        {
            $this->markTestSkipped('Модуль MCP не подключён');
        }

        $names = array();

        foreach (\Service_Erp804_Registry::instance()->tools() as $name => $tool)
        {
            $names[] = (string) $name;
        }

        foreach (array('documents.stats', 'documents.list', 'documents.get', 'documents.preview',
                       'documents.templates', 'documents.template', 'documents.template_check',
                       'documents.create', 'documents.issue', 'documents.print', 'documents.archive',
                       'documents.cancel', 'documents.template_save') as $tool)
        {
            $this->assertContains($tool, $names, 'инструмент '.$tool.' объявлен в mcp.php');
        }
    }

    /**
     * Инструменты не просто объявлены — вызов доходит до сервиса. Ошибка
     * предметной логики (`Exception_Erp404_Documents`) — законный ответ, ошибка
     * PHP (нет метода, нет аргумента, не тот тип) — нет.
     */
    public function test_mcp_handlers_reach_services()
    {
        if ( ! class_exists('\Service_Erp804_Registry'))
        {
            $this->markTestSkipped('Модуль MCP не подключён');
        }

        $registry = \Service_Erp804_Registry::instance();
        $template = $this->make_template();

        $document = $this->documents->create(array(
            'template_id' => $template->id,
            'note'        => 'ТЕСТ-404: вызов MCP',
        ));

        $to_cancel = $this->documents->create(array(
            'template_id' => $template->id,
            'note'        => 'ТЕСТ-404: отмена через MCP',
        ));

        $cases = array(
            'documents.stats'          => array(),
            'documents.list'           => array('limit' => 5),
            'documents.get'            => array('id' => (int) $document->id, 'with_body' => TRUE),
            'documents.preview'        => array('id' => (int) $document->id),
            'documents.templates'      => array('limit' => 5),
            'documents.template'       => array('code' => 'ТЕСТ-404-UNI'),
            'documents.template_check' => array('id' => (int) $template->id),
            'documents.create'         => array('template_code' => 'ТЕСТ-404-UNI', 'note' => 'ТЕСТ-404: черновик из MCP'),
            'documents.issue'          => array('id' => (int) $document->id, 'payload' => array('ref' => array('custom' => 'mcp'))),
            'documents.print'          => array('id' => (int) $document->id, 'copies' => 1),
            'documents.archive'        => array('id' => (int) $document->id),
            'documents.cancel'         => array('id' => (int) $to_cancel->id, 'reason' => 'ТЕСТ-404: проверка MCP'),
            'documents.template_save'  => array('code' => 'ТЕСТ-404-MCP', 'name' => 'Шаблон из MCP', 'body' => '<p>{{document.number}}</p>'),
        );

        $declared = $registry->tools();

        foreach ($cases as $name => $args)
        {
            $this->assertArrayHasKey($name, $declared, 'инструмент '.$name.' объявлен');

            try
            {
                $result = $registry->call($name, $args);
            }
            catch (\Exception_Erp804_Mcp $e)
            {
                // Предметный отказ сервиса — законный ответ: обработчик дошёл до
                // сервиса, а тот объяснил, почему нельзя. Ошибки PHP реестр не
                // ловит: они прилетят сюда как Error и уронят тест — это и нужно.
                $this->assertInstanceOf('Exception', $e->getPrevious(), $name.': отказ должен прийти из сервиса, а не из реестра');
                continue;
            }

            $this->assertIsArray($result, $name.' должен вернуть структуру ответа');
            $this->assertArrayHasKey('summary', $result, $name.': в ответе должна быть строка-итог');
        }
    }

    // =========================================================================
    //  Помощники
    // =========================================================================

    /**
     * Шаблон для тестов: универсальный (данные передаёт тест).
     */
    protected function make_template()
    {
        return $this->templates->create(array(
            'code' => 'ТЕСТ-404-UNI',
            'name' => 'Универсальный тестовый шаблон',
            'body' => '<h1>{{document.title}}</h1><p>{{document.number}} · {{ref.custom}} · {{meta.date}}</p>',
            'note' => 'ТЕСТ-404: шаблон для автотеста',
        ));
    }

    /**
     * Причина отказа сервиса (или NULL, если действие прошло).
     */
    protected function reason(callable $callback)
    {
        try
        {
            $callback();
        }
        catch (\Exception_Erp404_Documents $e)
        {
            return $e->reason();
        }

        return NULL;
    }

    /**
     * Модуль установлен: таблицы на месте.
     */
    protected function available()
    {
        try
        {
            return \DB::select(array(\DB::expr('COUNT(*)'), 'c'))->from('erp_404_templates')->execute()->get('c') >= 0;
        }
        catch (\Exception $e)
        {
            return FALSE;
        }
    }
}
