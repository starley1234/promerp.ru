<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты компонентов интерфейса (application/views/components).
 *
 * Проверяют обещания из `application/views/components/README.md`, а не разметку
 * построчно:
 *
 *  - у таблицы есть подпись, заголовки со `scope` и область прокрутки — именно
 *    этого не хватало 128 свёрстанным руками таблицам;
 *  - данные не становятся разметкой: то, что передали строкой, приходит
 *    экранированным (иначе название позиции ломало бы вёрстку и открывало
 *    HTML-инъекцию);
 *  - у цифры есть подпись и единица измерения, у состояния — слово и цвет из
 *    одного словаря;
 *  - пустое состояние объясняет, что произошло, и предлагает действие;
 *  - спарклайн строится только по числовому ряду и не рисуется по одной точке.
 *
 * Тесты быстрые: ни базы, ни HTTP — только рендер представлений.
 */
class ComponentsTest extends TestCase
{
    // =========================================================================
    //  Таблица
    // =========================================================================

    public function test_operational_form_field_disables_browser_autofill_by_default()
    {
        $html = (string) \Component::render('form_field', array(
            'id' => 'order_number',
            'name' => 'order_number',
            'label' => 'Номер заказа',
        ));
        $this->assertStringContainsString('autocomplete="off"', $html);

        $email = (string) \Component::render('form_field', array(
            'id' => 'contact_email',
            'name' => 'email',
            'label' => 'E-mail',
            'type' => 'email',
            'autocomplete' => 'email',
        ));
        $this->assertStringContainsString('autocomplete="email"', $email, 'контактные и auth-поля могут явно включить корректное автозаполнение');
    }

    public function test_table_has_caption_scope_and_scroll_region()
    {
        $html = \Component::render('table', array(
            'caption' => 'Документы и журнал печати',
            'columns' => array(
                array('label' => 'Номер', 'nowrap' => TRUE),
                array('label' => 'Название'),
            ),
            'rows' => array(
                array('ДК-2026-000005', 'Сменное задание'),
                array('<a href="/procurement/orders/view/5">ЗП-2026-000005</a>'
                    ."\n".'<span class="text-xs">Плановая закупка ходовых материалов</span>', 'Закупка'),
            ),
            'row_header' => TRUE,
            'sticky' => TRUE,
        ));

        $this->assertStringContainsString('<caption', $html, 'У таблицы есть подпись');
        $this->assertStringContainsString('Документы и журнал печати', $html);
        $this->assertStringContainsString('scope="col"', $html, 'Заголовки колонок со scope');
        $this->assertStringContainsString('scope="row"', $html, 'Первая ячейка строки — заголовок строки');
        $this->assertStringContainsString('<col style="width: 12rem; min-width: 12rem;">', $html,
            'Ширина первой колонки закреплена через нативный colgroup');
        $this->assertStringContainsString('style="width: 12rem; min-width: 12rem; overflow: visible;"', $html,
            'Ячейка имеет независимую от Tailwind резервную ширину');
        $this->assertStringContainsString('width: 12rem; min-width: 12rem; white-space: normal; overflow-wrap: anywhere;', $html,
            'Содержимое не наследует nowrap и остаётся полностью видимым');
        $this->assertStringContainsString('ДК-2026-000005', $html);
        $this->assertStringContainsString('ЗП-2026-000005', $html);
        $this->assertStringContainsString('Плановая закупка ходовых материалов', $html);
        $this->assertStringContainsString('overflow-x-auto', $html, 'На узком экране таблица прокручивается');
        $this->assertStringContainsString('sticky top-0 z-10', $html,
            'Липкая шапка привязана к краю scroll-region и не перекрывает первую строку');
        $this->assertStringNotContainsString('sticky top-16', $html,
            'Отступ top-16 внутри overflow-контейнера сдвигал шапку на 4rem поверх данных');
        $this->assertStringContainsString('role="region"', $html, 'Область прокрутки имеет роль');
        $this->assertStringContainsString('aria-label="Документы и журнал печати"', $html, 'У области есть имя');
    }

    public function test_compact_numeric_row_header_does_not_reserve_entity_width()
    {
        $html = \Component::render('table', array(
            'columns' => array(array('label' => '№'), array('label' => 'Результат')),
            'rows' => array(array('1', 'готово')),
            'row_header' => TRUE,
            'row_header_compact' => TRUE,
        ));

        $this->assertStringContainsString('scope="row"', $html);
        $this->assertStringNotContainsString('<colgroup>', $html);
        $this->assertStringNotContainsString('width: 12rem', $html);
    }

    public function test_table_priority_hides_columns_on_narrow_screens()
    {
        $html = \Component::render('table', array(
            'columns' => array(
                array('label' => 'Главное', 'priority' => 1),
                array('label' => 'Второстепенное', 'priority' => 3),
            ),
            'rows' => array(array('а', 'б')),
        ));

        $this->assertStringContainsString('hidden md:table-cell', $html, 'Колонка с приоритетом 3 скрывается до md');
        $this->assertStringNotContainsString('hidden md:table-cell"></th><th scope="col"', $html);
    }

    public function test_table_shows_empty_state_instead_of_blank_area()
    {
        $html = \Component::render('table', array(
            'columns' => array(array('label' => 'Номенклатура')),
            'rows' => array(),
            'empty_title' => 'По заданным условиям позиций нет',
            'empty_hint' => 'Снимите фильтр «только ниже минимума»',
        ));

        $this->assertStringNotContainsString('<table', $html, 'Пустая таблица не рисуется');
        $this->assertStringContainsString('По заданным условиям позиций нет', $html);
        $this->assertStringContainsString('Снимите фильтр', $html);
    }

    // =========================================================================
    //  Экранирование
    // =========================================================================

    public function test_table_and_card_escape_their_text()
    {
        $evil = '<script>alert(1)</script>';

        $table = \Component::render('table', array(
            'caption' => $evil,
            'columns' => array(array('label' => $evil)),
            'rows' => array(array('обычная ячейка')),
        ));

        $card = \Component::render('card', array('title' => $evil, 'subtitle' => $evil, 'body' => 'тело'));

        foreach (array($table, $card) as $html)
        {
            $this->assertStringNotContainsString('<script>', $html, 'Разметка из данных не проходит');
            $this->assertStringContainsString('&lt;script&gt;', $html, 'Текст приходит экранированным');
        }
    }

    public function test_cell_html_is_rendered_as_is()
    {
        // В ячейку можно положить ссылку — это осознанное решение: таблица не
        // знает про содержимое ячейки, а данные в неё кладёт код проекта.
        $html = \Component::render('table', array(
            'columns' => array(array('label' => 'Контроль')),
            'rows' => array(array('<a href="/quality/inspections/view/1">ПК-1</a>')),
        ));

        $this->assertStringContainsString('<a href="/quality/inspections/view/1">ПК-1</a>', $html);
    }

    public function test_smart_reference_submits_id_and_shows_human_label()
    {
        $html = \Component::render('smart_reference', array(
            'id' => 'manager_id', 'name' => 'manager_id', 'label' => 'Руководитель',
            'url' => '/workforce/api/reference?type=employee', 'value' => 17,
            'selected_label' => 'Иванов Иван · мастер',
        ));
        $this->assertStringContainsString('name="manager_id" value="17"', $html);
        $this->assertStringContainsString('value="Иванов Иван · мастер"', $html);
        $this->assertStringContainsString('role="combobox"', $html);
        $this->assertStringContainsString('role="listbox"', $html);
        $this->assertStringNotContainsString('name="manager_id_search"', $html, 'Видимый текст не отправляется как ID');
    }

    // =========================================================================
    //  KPI и состояния
    // =========================================================================

    public function test_kpi_shows_label_unit_and_target()
    {
        $html = \Component::render('kpi', array(
            'label' => 'Покрытие запасов',
            'value' => '43',
            'unit' => 'дн.',
            'target' => 21,
            'state' => 'ok',
            'hint' => 'медиана по позициям с расходом',
        ));

        $this->assertStringContainsString('Покрытие запасов', $html);
        $this->assertStringContainsString('43', $html);
        $this->assertStringContainsString('дн.', $html, 'Единица измерения обязательна');
        $this->assertStringContainsString('цель 21', $html);
        $this->assertStringContainsString('медиана по позициям', $html);
        $this->assertStringContainsString('в норме', $html, 'Состояние подписано словом');
    }

    public function test_kpi_with_url_becomes_link()
    {
        $html = \Component::render('kpi', array(
            'label' => 'Просроченные поставки',
            'value' => '25',
            'unit' => '%',
            'state' => 'bad',
            'url' => '/procurement/orders?overdue=1',
        ));

        $this->assertStringContainsString('<a href="/procurement/orders?overdue=1"', $html);
        $this->assertStringContainsString('проблема', $html);
    }

    public function test_kpi_shows_sparkline_only_for_a_series()
    {
        $series = \Component::render('kpi', array(
            'label' => 'OEE',
            'value' => '76',
            'unit' => '%',
            'trend' => array(61, 65, 70, 76),
            'state' => 'ok',
        ));

        $single = \Component::render('kpi', array(
            'label' => 'OEE',
            'value' => '76',
            'unit' => '%',
            'trend' => array(76),
        ));

        $this->assertStringContainsString('<polyline', $series, 'По ряду значений рисуется тренд');
        $this->assertStringContainsString('aria-hidden="true"', $series, 'График не мешает скринридеру');
        $this->assertStringNotContainsString('<polyline', $single, 'По одной точке тренд не строится');
    }

    public function test_badge_uses_state_dictionary()
    {
        $bad = \Component::render('badge', array('state' => 'bad', 'title' => 'заказ просрочен'));
        $warn = \Component::render('badge', array('state' => 'warn'));
        $unknown = \Component::render('badge', array('state' => 'что-то'));

        $this->assertStringContainsString('проблема', $bad);
        $this->assertStringContainsString('заказ просрочен', $bad);
        $this->assertStringContainsString('внимание', $warn);
        $this->assertStringContainsString('без оценки', $unknown, 'Неизвестное состояние не ломает вёрстку');
    }

    // =========================================================================
    //  Пустое состояние и листание
    // =========================================================================

    public function test_empty_explains_and_offers_action()
    {
        $html = \Component::render('empty', array(
            'title' => 'Очередь пуста',
            'hint' => 'Брак либо разобран, либо его нет',
            'action_html' => '<a href="/quality/inspections">Все контроли</a>',
        ));

        $this->assertStringContainsString('Очередь пуста', $html);
        $this->assertStringContainsString('Брак либо разобран', $html);
        $this->assertStringContainsString('/quality/inspections', $html);
    }

    public function test_pagination_keeps_filters_and_marks_current_page()
    {
        $html = \Component::render('pagination', array(
            'page' => 2,
            'pages' => 5,
            'url' => function ($page) {
                return '/warehouse/balances?page='.$page.'&warehouse_id=3';
            },
        ));

        $this->assertStringContainsString('aria-current="page"', $html, 'Текущая страница отмечена');
        $this->assertStringContainsString('/warehouse/balances?page=3&amp;warehouse_id=3', $html, 'Фильтры сохраняются в ссылках');
        $this->assertStringContainsString('aria-label="Постраничный переход"', $html);
    }

    public function test_pagination_is_silent_for_one_page()
    {
        $html = \Component::render('pagination', array(
            'page' => 1,
            'pages' => 1,
            'url' => function ($page) { return '/list?page='.$page; },
        ));

        $this->assertSame('', trim($html), 'Одна страница — листать нечего');
    }

    // =========================================================================
    //  begin()/end()
    // =========================================================================

    public function test_begin_end_capture_markup_and_keep_explicit_body()
    {
        \Component::begin('card', array('title' => 'Остатки'));
        echo '<p>разметка внутри</p>';
        ob_start();
        \Component::end();
        $with_markup = ob_get_clean();

        $this->assertStringContainsString('<p>разметка внутри</p>', $with_markup, 'Разметка внутри стала телом');

        // Пустая пара begin()/end() не должна затирать тело, переданное заранее:
        // именно так отрисовываются фильтры списка.
        \Component::begin('card', array('title' => 'Фильтры', 'body' => '<input name="q">'));
        ob_start();
        \Component::end();
        $explicit = ob_get_clean();

        $this->assertStringContainsString('<input name="q">', $explicit, 'Переданное тело сохранено');
    }

    // =========================================================================
    //  Состояния: словарь
    // =========================================================================

    public function test_state_classes_cover_every_state_and_part()
    {
        foreach (array('ok', 'warn', 'bad', 'info', 'neutral', 'неизвестное') as $state)
        {
            foreach (array('text', 'bg', 'border', 'badge') as $part)
            {
                $this->assertNotEmpty(
                    \Component::state_class($state, $part),
                    'Состояние «'.$state.'» имеет класс для '.$part
                );
            }
        }

        $this->assertNotSame(
            \Component::state_class('ok', 'text'),
            \Component::state_class('bad', 'text'),
            'Разные состояния выглядят по-разному'
        );
    }

    public function test_page_header_has_one_h1_crumbs_and_escapes_data()
    {
        $html = (string) \Component::render('page_header', array(
            'title'        => 'Заказ <b>1</b>',
            'lead'         => 'Что на странице',
            'crumbs'       => array(array('Документация', '/docs'), array('Модули', NULL)),
            'actions_html' => '<a href="/demo">Демо</a>',
        ));

        $this->assertSame(1, substr_count($html, '<h1'), 'на странице один заголовок первого уровня');
        $this->assertStringContainsString('Заказ &lt;b&gt;1&lt;/b&gt;', $html, 'заголовок экранируется');
        $this->assertStringContainsString('href="/docs"', $html);
        $this->assertStringContainsString('aria-current="page"', $html, 'последняя крошка — текущая страница');
        $this->assertStringContainsString('<a href="/demo">Демо</a>', $html, 'кнопки — готовый HTML');

        $plain = (string) \Component::render('page_header', array('title' => 'Без подписи'));
        $this->assertStringNotContainsString('<nav', $plain, 'без крошек навигации нет');
    }

    /**
     * Компонент не наследует общие переменные шаблона.
     *
     * `View` подмешивает их в любое представление, поэтому `card` без
     * заголовка получала заголовком название страницы: на `/terms` выходило
     * два заголовка «Условия использования». Проверяем два направления: список
     * известных компоненту переменных покрывает всё, что делает общим
     * шаблон, и сами переменные в компонент не протекают.
     */
    public function test_components_do_not_inherit_template_globals()
    {
        $reflection = new \ReflectionClass('View');
        $property   = $reflection->getProperty('_global_data');
        $property->setAccessible(TRUE);

        $globals = array_keys((array) $property->getValue());

        $component = new \ReflectionClass('Component');
        $known     = $component->getProperty('_template_globals');
        $known->setAccessible(TRUE);

        $missing = array_diff($globals, (array) $known->getValue());

        $this->assertSame(array(), $missing,
            'шаблон объявил общие переменные, о которых компонент не знает: '
            .'дополните Component::$_template_globals');

        // Пока название страницы объявлено общим, компонент обязан его не видеть.
        \View::set_global('title', 'Условия использования');

        $card = (string) \Component::render('card', array('body' => 'Текст'));

        $this->assertStringNotContainsString('Условия использования', $card,
            'карточка без заголовка не получает заголовком название страницы');
        $this->assertStringNotContainsString('<h2', $card);

        $badge = (string) \Component::render('badge', array('label' => 'ok', 'state' => 'ok'));

        $this->assertStringNotContainsString('title="', $badge,
            'бейдж без подсказки не получает подсказкой название страницы');

        $empty = (string) \Component::render('empty', array('hint' => 'Подсказка'));

        $this->assertStringContainsString('Данных нет', $empty,
            'пустое состояние показывает своё сообщение, а не название страницы');

        \View::set_global('title', NULL);
    }
}
