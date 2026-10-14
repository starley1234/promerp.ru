<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты режимов интерфейса: простого и профессионального (Ui_Mode).
 *
 * Проверяют обещания, а не разметку построчно:
 *
 *  - режим один на запрос, неизвестный не принимается, в консоли без выбора
 *    остаётся прежний вид;
 *  - простой режим показывает ТЕ ЖЕ данные: что есть в таблице, есть и в плитках;
 *  - профессиональный режим не меняется: таблица, фильтры и «Ещё» — как раньше;
 *  - меню скрывает служебные разделы в простом режиме, а по умолчанию (вызов без
 *    режима) отдаёт всё, как отдавало раньше;
 *  - режим выбирают компоненты, а не представления модулей;
 *  - переключатель не открывает перенаправление на чужой сайт.
 *
 * Базы и HTTP не нужны: рендер представлений и чтение объявлений.
 */
class UiModeTest extends TestCase
{
    protected function setUp(): void
    {
        \Ui_Mode::force(NULL);
    }

    protected function tearDown(): void
    {
        \Ui_Mode::force(NULL);
    }

    // =========================================================================
    //  Состояние
    // =========================================================================

    public function test_modes_are_known_and_validated()
    {
        $this->assertSame(array('simple', 'pro'), array_keys(\Ui_Mode::modes()));
        $this->assertSame('Простой', \Ui_Mode::modes()['simple']);
        $this->assertSame('Профессиональный', \Ui_Mode::modes()['pro']);
        $this->assertSame(array_keys(\Ui_Mode::modes()), array_keys(\Ui_Mode::hints()), 'У каждого режима есть подсказка');

        $this->assertTrue(\Ui_Mode::valid('simple'));
        $this->assertTrue(\Ui_Mode::valid('pro'));
        $this->assertFalse(\Ui_Mode::valid('expert'));
        $this->assertFalse(\Ui_Mode::valid(NULL));
        $this->assertFalse(\Ui_Mode::valid(1));
    }

    public function test_default_mode_for_a_new_person_is_simple()
    {
        $this->assertSame('simple', \Ui_Mode::DEFAULT_MODE);
    }

    public function test_console_without_choice_keeps_the_classic_view()
    {
        // Человека за браузером нет — прежние тесты и консольные команды видят
        // привычную разметку, пока сами не выберут режим.
        $this->assertSame('pro', \Ui_Mode::current());
        $this->assertTrue(\Ui_Mode::pro());
    }

    public function test_force_selects_and_resets_the_mode()
    {
        \Ui_Mode::force('simple');
        $this->assertTrue(\Ui_Mode::simple());
        $this->assertFalse(\Ui_Mode::pro());

        \Ui_Mode::force('nonsense');
        $this->assertSame('pro', \Ui_Mode::current(), 'Неверное значение сбрасывает выбор');
    }

    public function test_unknown_mode_is_rejected_before_anything_is_saved()
    {
        $this->expectException(\InvalidArgumentException::class);

        \Ui_Mode::set('expert');
    }

    // =========================================================================
    //  Меню
    // =========================================================================

    private function hrefs(array $categories)
    {
        $hrefs = array();

        foreach ($categories as $category)
        {
            foreach ($category['items'] as $item)
            {
                $hrefs[] = $item['href'];
            }
        }

        return $hrefs;
    }

    public function test_simple_menu_hides_service_sections_and_keeps_daily_work()
    {
        $roles  = array('admin');
        $simple = $this->hrefs(\Ui_Navigation::categories($roles, 'simple'));
        $pro    = $this->hrefs(\Ui_Navigation::categories($roles, 'pro'));

        foreach (array('/', '/shopfloor', '/production/orders', '/warehouse/balances', '/quality', '/procurement',
                       '/crm', '/counterparties', '/documents', '/access/users', '/workforce', '/demo') as $href)
        {
            $this->assertContains($href, $simple, $href.' нужен в повседневной работе и виден в простом режиме');
        }

        foreach (array('/product-structures', '/planning', '/costing', '/traceability', '/qr', '/integration', '/docs',
                       '/docs/mcp', '/audit/journal', '/erp212', '/erp218', '/erp230_dictionaries', '/migrate_pg') as $href)
        {
            $this->assertNotContains($href, $simple, $href.' скрыт в простом режиме');
            $this->assertContains($href, $pro, $href.' остаётся в профессиональном');
        }

        $this->assertSame($pro, $this->hrefs(\Ui_Navigation::categories($roles)),
            'Без режима меню такое же, как раньше: все пункты');
    }

    public function test_simple_menu_drops_empty_categories_and_palette_follows_the_menu()
    {
        $roles = array('login', 'manager');

        foreach (\Ui_Navigation::categories($roles, 'simple') as $category)
        {
            $this->assertNotEmpty($category['items'], 'Пустых разделов в меню нет');
        }

        $palette = array_column(\Ui_Navigation::palette($roles, 'simple'), 'href');

        $this->assertNotContains('/product-structures', $palette, 'Ctrl+K не предлагает то, чего нет в меню');
        $this->assertNotContains('/docs/processes', $palette);
        $this->assertContains('/auth/profile', $palette, 'Профиль доступен всегда: там переключатель режима');

        $worker = array_column(\Ui_Navigation::palette(array('login', 'worker'), 'simple'), 'href');
        $this->assertContains('/documents', $worker);
        $this->assertContains('/quality', $worker);

        $this->assertContains('/product-structures', array_column(\Ui_Navigation::palette($roles), 'href'));
    }

    // =========================================================================
    //  Таблица → плитки
    // =========================================================================

    private function table_args()
    {
        return array(
            'caption' => 'Заказы',
            'columns' => array(
                array('label' => 'Заказ'),
                array('label' => 'Клиент'),
                array('label' => 'Сумма', 'align' => 'right'),
                array('label' => 'Срок', 'priority' => 2),
                array('label' => 'Склад', 'priority' => 3),
                array('label' => 'Комментарий', 'priority' => 4),
                array('label' => 'Действия'),
            ),
            'rows' => array(
                array('<a href="/o/1">ЗП-1</a>', 'ООО «Альфа»', '12 000 ₽', '05.10', 'Основной', '', '<a href="/o/1/edit">Править</a>'),
                array('class' => 'bg-amber-50', 'cells' => array('ЗП-2', 'ИП Бета', '800 ₽', '', 'Малый', 'срочно', '')),
            ),
            'row_header' => TRUE,
        );
    }

    public function test_simple_mode_draws_tiles_instead_of_a_table()
    {
        \Ui_Mode::force('simple');
        $html = (string) \Component::render('table', $this->table_args());

        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringContainsString('<ul', $html);
        $this->assertStringContainsString('aria-label="Заказы"', $html, 'Подпись списка сохранена');
        $this->assertSame(2, substr_count($html, '<li'), 'Одна плитка на строку');
    }

    public function test_tiles_show_the_same_data_as_the_table()
    {
        $args = $this->table_args();

        \Ui_Mode::force('pro');
        $table = (string) \Component::render('table', $args);
        \Ui_Mode::force('simple');
        $tiles = (string) \Component::render('table', $args);

        $this->assertStringContainsString('<table', $table);

        foreach (array('ЗП-1', 'ЗП-2', 'ООО «Альфа»', 'ИП Бета', '12 000 ₽', '800 ₽', '05.10',
                       'Основной', 'Малый', 'срочно', 'Править') as $value)
        {
            $this->assertStringContainsString($value, $table, '«'.$value.'» есть в таблице');
            $this->assertStringContainsString($value, $tiles, '«'.$value.'» не потерялось в плитках');
        }

        foreach (array('Клиент', 'Сумма', 'Срок', 'Склад', 'Комментарий') as $label)
        {
            $this->assertStringContainsString($label, $tiles, 'У каждого значения в плитке есть подпись «'.$label.'»');
        }
    }

    public function test_tile_puts_main_facts_first_and_the_rest_under_details()
    {
        \Ui_Mode::force('simple');
        $html = (string) \Component::render('table', $this->table_args());

        $details = strpos($html, '<details');
        $this->assertTrue($details !== FALSE, 'Колонки с priority 3–4 уходят в «Подробнее»');
        $this->assertStringContainsString('Подробнее', $html);

        $this->assertLessThan($details, strpos($html, 'Клиент'), 'Главное — до «Подробнее»');
        $this->assertLessThan($details, strpos($html, 'Срок'));
        $this->assertGreaterThan($details, strpos($html, 'Склад'), 'Второстепенное — под «Подробнее»');
    }

    public function test_tile_limits_facts_and_moves_actions_to_the_bottom()
    {
        \Ui_Mode::force('simple');
        $columns = array(array('label' => 'Заказ'));
        $row     = array('ЗП-9');

        foreach (range(1, 6) as $i)
        {
            $columns[] = array('label' => 'Поле'.$i);
            $row[]     = 'значение'.$i;
        }

        $columns[] = array('label' => 'Действие');
        $row[]     = '<a href="/go">Открыть</a>';

        $html = (string) \Component::render('table', array('columns' => $columns, 'rows' => array($row)));

        $this->assertLessThan(strpos($html, '<details'), strpos($html, 'Поле4'), 'Четыре факта видны сразу');
        $this->assertGreaterThan(strpos($html, '<details'), strpos($html, 'Поле5'), 'Сверх четырёх — под «Подробнее»');
        $this->assertStringNotContainsString('>Действие<', $html, 'У кнопок подписи нет: они внизу плитки');
        $this->assertGreaterThan(strpos($html, 'Поле6'), strpos($html, '/go'), 'Действие после данных');
    }

    public function test_tile_highlight_class_and_empty_state_are_kept()
    {
        \Ui_Mode::force('simple');
        $html = (string) \Component::render('table', $this->table_args());

        $this->assertStringContainsString('bg-amber-50', $html, 'Подсветка строки переходит на плитку');

        $empty = (string) \Component::render('table', array(
            'columns' => array(array('label' => 'Заказ')),
            'rows' => array(),
            'empty_title' => 'Заказов нет',
        ));

        $this->assertStringContainsString('Заказов нет', $empty);
        $this->assertStringNotContainsString('<ul', $empty);
    }

    /**
     * Плитки — это тело таблицы внутри панели, почти всегда с `flush` (таблица
     * во всю ширину). Без собственного отступа плитки прилегают к краю
     * панели: содержимое выглядит обрезанным, хотя карточного отступа просто
     * нет. Тот же принцип, что у `empty` с его `px-5 py-10`.
     */
    public function test_tiles_have_their_own_padding()
    {
        \Ui_Mode::force('simple');
        $html = (string) \Component::render('table', $this->table_args());

        $this->assertMatchesRegularExpression('/<ul class="[^"]*\bp-\d+/', $html,
            'У сетки плиток есть свой отступ: иначе плитки прилегают к краю карточки');
    }

    public function test_tiles_escape_labels_and_pass_prepared_cells_through()
    {
        \Ui_Mode::force('simple');
        $html = (string) \Component::render('table', array(
            'columns' => array(array('label' => 'Название'), array('label' => '<b>Подпись</b>')),
            'rows' => array(array('<a href="/x">ссылка</a>', 'значение')),
        ));

        $this->assertStringNotContainsString('<b>Подпись</b>', $html, 'Подпись колонки — текст, а не разметка');
        $this->assertStringContainsString('&lt;b&gt;', $html);
        $this->assertStringContainsString('<a href="/x">ссылка</a>', $html, 'Готовая ячейка не экранируется повторно');
    }

    public function test_professional_mode_keeps_the_table_exactly()
    {
        \Ui_Mode::force('pro');
        $html = (string) \Component::render('table', $this->table_args());

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('scope="col"', $html);
        $this->assertStringNotContainsString('Подробнее', $html);
    }

    // =========================================================================
    //  KPI, «Ещё», фильтры
    // =========================================================================

    public function test_kpi_is_big_in_simple_mode_even_when_compact_is_requested()
    {
        $args = array('label' => 'Заказов', 'value' => 7, 'unit' => 'шт.', 'compact' => TRUE);

        \Ui_Mode::force('pro');
        $pro = (string) \Component::render('kpi', $args);
        \Ui_Mode::force('simple');
        $simple = (string) \Component::render('kpi', $args);

        $this->assertStringContainsString('px-3 py-2', $pro, 'В профессиональном — мелкая плитка');
        $this->assertStringNotContainsString('px-3 py-2', $simple, 'В простом мелких плиток нет');
        $this->assertStringContainsString('p-4', $simple, 'В простом плитка крупная');

        // Размер цифры один и тот же в обоих режимах: эталон запрещает
        // `text-3xl`, а крупная плитка обязана своей величиной отступам,
        // а не кеглю, который перерастает заголовок страницы.
        $this->assertStringContainsString('text-2xl', $simple);
        $this->assertStringContainsString('text-2xl', $pro);
        $this->assertStringNotContainsString('text-3xl', $simple);
    }

    public function test_more_is_collapsed_in_simple_mode_and_flat_in_pro()
    {
        $args = array('summary' => 'Допполя', 'body' => '<input name="inn">');

        \Ui_Mode::force('simple');
        $simple = (string) \Component::render('more', $args);
        $this->assertStringContainsString('<details', $simple);
        $this->assertStringContainsString('Допполя', $simple);
        $this->assertStringContainsString('<input name="inn">', $simple);
        $this->assertStringNotContainsString(' open', $simple);

        $opened = (string) \Component::render('more', $args + array('open' => TRUE));
        $this->assertStringContainsString(' open', $opened);

        \Ui_Mode::force('pro');
        $pro = (string) \Component::render('more', $args);
        $this->assertStringNotContainsString('<details', $pro);
        $this->assertStringContainsString('<input name="inn">', $pro);
        $this->assertStringNotContainsString('Допполя', $pro, 'В профессиональном режиме обёртки нет');
    }

    public function test_more_has_a_default_summary()
    {
        \Ui_Mode::force('simple');

        $this->assertStringContainsString('Дополнительные настройки',
            (string) \Component::render('more', array('body' => 'x')));
    }

    public function test_filters_are_collapsed_in_simple_mode_and_unchanged_in_pro()
    {
        $args = array('action' => '/list', 'body' => '<input name="q">', 'reset_url' => '/list');

        \Ui_Mode::force('simple');
        $simple = (string) \Component::render('filters', $args);
        $this->assertStringContainsString('<details', $simple);
        $this->assertStringContainsString('Фильтры', $simple);
        $this->assertStringContainsString('<input name="q">', $simple);
        $this->assertStringContainsString('role="search"', $simple, 'Форма отбора на месте');

        \Ui_Mode::force('pro');
        $pro = (string) \Component::render('filters', $args);
        $this->assertStringNotContainsString('<details', $pro);
        $this->assertStringContainsString('<input name="q">', $pro);
        $this->assertStringContainsString('role="search"', $pro);
    }

    public function test_toolbar_is_collapsed_in_simple_mode_and_unchanged_in_pro()
    {
        $args = array('title' => 'Отбор', 'body' => '<input name="term">');

        \Ui_Mode::force('simple');
        $simple = (string) \Component::render('toolbar', $args);
        $this->assertStringContainsString('<details', $simple);
        $this->assertStringContainsString('Отбор', $simple);

        \Ui_Mode::force('pro');
        $this->assertStringNotContainsString('<details', (string) \Component::render('toolbar', $args));
    }

    // =========================================================================
    //  Переключатель
    // =========================================================================

    public function test_switch_offers_both_modes_and_marks_the_current_one()
    {
        \Ui_Mode::force('simple');
        $html = (string) \Component::render('mode_switch');

        $this->assertStringContainsString('/ui/mode/simple', $html);
        $this->assertStringContainsString('/ui/mode/pro', $html);
        $this->assertStringContainsString('Простой', $html);
        $this->assertStringContainsString('Профессиональный', $html);
        $this->assertStringContainsString('rel="nofollow"', $html, 'Роботы не должны переключать режим');
        $this->assertSame(1, substr_count($html, 'aria-current'), 'Активен ровно один режим');
        $this->assertMatchesRegularExpression('~<a[^>]*aria-current="true"[^>]*>\s*Простой~', $html);

        \Ui_Mode::force('pro');
        $this->assertMatchesRegularExpression('~<a[^>]*aria-current="true"[^>]*>\s*Профессиональный~',
            (string) \Component::render('mode_switch'));
    }

    public function test_mode_route_accepts_only_known_modes()
    {
        $route = \Route::get('erp100_ui_mode');

        foreach (array('simple', 'pro') as $mode)
        {
            $params = $route->matches(\Request::factory('ui/mode/'.$mode));
            $this->assertIsArray($params, 'Адрес /ui/mode/'.$mode.' ведёт на переключатель');
            $this->assertSame('Ui', $params['controller']);
            $this->assertSame('mode', $params['action']);
        }

        $this->assertFalse($route->matches(\Request::factory('ui/mode/expert')));
    }

    public function test_switch_returns_only_to_this_site()
    {
        $method = new \ReflectionMethod('Controller_Erp100_Ui', 'back');
        $method->setAccessible(TRUE);

        $cases = array(
            '/crm?status=open' => '/crm?status=open',
            '/'                => '/',
            ''                 => '/',
            '//evil.example'   => '/',
            'https://evil.example/' => '/',
            'crm'              => '/',
            '/\\evil.example'  => '/',
        );

        foreach ($cases as $given => $expected)
        {
            $controller = new \Controller_Erp100_Ui(\Request::factory('ui/mode/pro')->query('back', $given), new \Response);

            $this->assertSame($expected, $method->invoke($controller), 'back='.$given);
        }
    }

    // =========================================================================
    //  Архитектурные правила
    // =========================================================================

    public function test_module_views_do_not_ask_for_the_mode()
    {
        // Режим выбирают компоненты. Если представление модуля спрашивает его
        // само, `if simple` расползётся по сотням файлов — как до компонентов
        // расползалась разметка карточек.
        $offenders = array();
        $files     = array_merge(
            glob(APPPATH.'modules/*/views/*/*.php') ?: array(),
            glob(APPPATH.'modules/*/views/*/*/*.php') ?: array(),
            glob(APPPATH.'views/*/*.php') ?: array()
        );

        foreach ($files as $file)
        {
            if (strpos($file, '/views/components/') !== FALSE)
            {
                continue;
            }

            if (strpos((string) file_get_contents($file), 'Ui_Mode::') !== FALSE)
            {
                $offenders[] = str_replace(APPPATH, '', $file);
            }
        }

        $this->assertSame(array(), $offenders, 'Ui_Mode:: — только в компонентах, шаблоне и классах');
    }

    public function test_template_applies_the_mode_to_page_menu_and_stylesheet()
    {
        $template = (string) file_get_contents(APPPATH.'views/template_tailwindcss.php');

        $this->assertStringContainsString('data-ui="', $template);
        $this->assertStringContainsString("Assets::url('simple')", $template);
        $this->assertSame(2, substr_count($template, "Component::render('mode_switch'"),
            'Переключатель есть и в боковом меню, и в мобильном');
        $this->assertSame(3, substr_count($template, 'Ui_Mode::current()'),
            'Режим берётся для атрибута страницы, меню и Ctrl+K — из одного источника');
    }

    public function test_simple_stylesheet_acts_only_in_simple_mode()
    {
        $path = DOCROOT.'assets/css/simple.css';
        $this->assertFileExists($path);
        $this->assertTrue(\Assets::exists('simple'));

        $css = preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents($path));

        foreach (explode('}', $css) as $block)
        {
            if (strpos($block, '{') === FALSE)
            {
                continue;
            }

            $selectors = explode('{', $block);

            foreach (explode(',', $selectors[0]) as $selector)
            {
                $selector = trim($selector);

                $this->assertTrue(
                    strpos($selector, 'html[data-ui="simple"]') === 0 OR strpos($selector, 'html:not([data-ui="simple"])') === 0,
                    'Правило «'.$selector.'» не должно менять профессиональный режим'
                );
            }
        }
    }

    public function test_user_settings_are_kept_by_the_core_service_not_the_mysql_model()
    {
        $source = (string) file_get_contents(APPPATH.'modules/erp100_base/classes/Service/Erp100/UserSettings.php');

        $this->assertStringContainsString('users_settings', $source);
        $this->assertStringContainsString('ON CONFLICT (user_id)', $source, 'Запись одной настройки не стирает остальные');
        $this->assertStringContainsString('||', $source, 'jsonb сливается, а не заменяется целиком');
        $this->assertStringNotContainsString('SHOW COLUMNS', $source);
    }
}
