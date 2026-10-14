<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты «Рабочего места» (модуль erp811_demo) — прототипа интерфейса из
 * `docs/FRONTEND_REVIEW.md` (§3.1).
 *
 * Проверяют обещания экрана, а не строки вёрстки:
 *
 *  - страница собирает данные из витрин и **не падает**, если витрина выключена
 *    или пуста (пустой период — пустые блоки, а не ошибка);
 *  - контракт данных постоянен: период, показатели, внимание, цех, заказы,
 *    склад, качество;
 *  - представление — из компонентов: у каждой таблицы есть подпись, у
 *    заголовков `scope`, длинные таблицы прокручиваются, состояния приходят
 *    словарём;
 *  - данные не становятся разметкой: подставленный `<script>` приходит
 *    экранированным (в компоненте экранирование по умолчанию);
 *  - ссылки ведут на существующие страницы, а маршрут `demo/workspace` — на
 *    контроллер стенда.
 */
class Erp811WorkspaceTest extends TestCase
{
    /** @var \Service_Erp811_Workspace */
    protected $workspace;

    protected function setUp(): void
    {
        $this->workspace = \Service_Erp811_Workspace::instance();
    }

    // =========================================================================
    //  Контракт данных
    // =========================================================================

    public function test_build_returns_documented_blocks()
    {
        $data = $this->workspace->build(array(
            'from' => date('Y-m-d', strtotime('-30 days')),
            'to'   => date('Y-m-d'),
        ));

        foreach (array('period', 'kpi', 'attention', 'shop', 'orders', 'stock', 'quality') as $block)
        {
            $this->assertArrayHasKey($block, $data, 'Блок «'.$block.'» есть в данных экрана');
        }

        $this->assertSame(date('Y-m-d'), $data['period']['to'], 'Период по умолчанию — до сегодня');
        $this->assertIsArray($data['kpi']);
        $this->assertIsArray($data['shop']);
        $this->assertIsArray($data['orders']['rows']);
        $this->assertIsArray($data['stock']['rows']);
        $this->assertIsArray($data['quality']);
    }

    public function test_period_without_movements_still_gives_a_whole_screen()
    {
        // Период, в котором движений не было: витрины отдают пустые наборы, а
        // экран должен собраться целиком и показать пустые состояния.
        // Внимание и список открытых заказов к периоду не привязаны — это
        // состояние «на сейчас», поэтому здесь проверяется структура.
        $data = $this->workspace->build(array(
            'from' => '2001-01-01',
            'to'   => '2001-01-02',
        ));

        $this->assertIsArray($data['attention']);
        $this->assertIsArray($data['orders']['rows']);
        $this->assertSame(array(), $data['shop']['operations'], 'За период без работы нет операций');
        $this->assertSame(array(), $data['quality']['causes'], 'За период без брака нет причин');
        $this->assertIsArray($data['stock']['rows']);
    }

    public function test_blocks_survive_a_missing_module()
    {
        // Заглушка вместо витрины: сервис, который не отвечает, не должен
        // ломать страницу — это обещание экрана демонстрации.
        $method = new \ReflectionMethod($this->workspace, '_service');
        $method->setAccessible(TRUE);

        $this->assertNull($method->invoke($this->workspace, 'Service_НетТакогоМодуля', 'panel', array()), 'Нет модуля — нет блока');
        $this->assertNull($method->invoke($this->workspace, 'Service_Erp811_Scenario', 'нетТакогоМетода', array()), 'Нет метода — нет блока');
        $this->assertIsArray($method->invoke($this->workspace, 'Service_Erp811_Scenario', 'company', array()), 'Рабочий вызов проходит');
    }

    // =========================================================================
    //  Представление
    // =========================================================================

    protected function render(array $data)
    {
        return \View::factory('erp811/workspace')
            ->set('scenario', \Service_Erp811_Scenario::instance())
            ->set('data', $data)
            ->set('inside', FALSE)
            ->set('filters', array('from' => '2026-08-01', 'to' => '2026-09-01'))
            ->render();
    }

    protected function dataset()
    {
        return array(
            'period'    => array('from' => '2026-08-01', 'to' => '2026-09-01'),
            'kpi'       => array(
                array('title' => 'Покрытие запасов', 'value' => '20.5', 'unit' => 'дней', 'target' => 21, 'state' => 'warn', 'hint' => 'хватит ли запаса', 'url' => '/analytics/stock'),
            ),
            'attention' => array(
                array('state' => 'bad', 'title' => '<script>alert(1)</script>', 'value' => '2 поз.', 'hint' => 'нет запаса', 'url' => '/analytics/stock?tab=deficit'),
            ),
            'shop'      => array(
                'available'    => TRUE,
                'oee'          => 67.3,
                'availability' => 88.8,
                'performance'  => 86.2,
                'quality'      => 98.0,
                'target'       => 65.0,
                'state'        => 'ok',
                'plan_fact'    => array('qty_ok' => 33381, 'qty_scrap' => 9, 'done' => 105),
                'pareto'       => array(array('label' => 'Засор сопла', 'hours' => 12.5, 'share' => 41.2)),
                'operations'   => array(array(
                    'number' => 'OP-1', 'order' => 'ЗК-1', 'name' => 'Печать', 'center' => 'Принтер №1',
                    'status' => 'paused', 'qty_plan' => 300, 'qty_ok' => 120, 'started' => NULL,
                )),
            ),
            'orders'    => array(
                'rows'  => array(array(
                    'number' => 'ЗК-1', 'item' => 'Клипса', 'status' => 'in_progress', 'planned' => 300,
                    'done' => 120, 'progress' => 40, 'due' => '2026-09-01', 'uom' => 'шт.',
                )),
                'stats' => array('total' => 30),
            ),
            'stock'     => array(
                'items'        => 1,
                'shortage_sum' => 0.0,
                'mixed_units'  => TRUE,
                'rows'         => array(array(
                    'code' => 'MAT-TPU-FLX', 'name' => 'Пластик TPU', 'stock' => 0.264,
                    'min_qty' => 0.5, 'cover' => 0.9, 'shortage' => 0.3, 'reasons' => 'ниже минимума, хватит ненадолго',
                )),
                'totals' => array('stock' => 3416.2),
            ),
            'quality'   => array(
                'available' => TRUE,
                'share'     => 12.0,
                'target'    => 8.0,
                'state'     => 'warn',
                'summary'   => array('total' => 25),
                'causes'    => array(array('label' => 'Печать', 'qty' => 8.0)),
            ),
        );
    }

    public function test_view_is_built_from_components()
    {
        $html = $this->render($this->dataset());

        foreach (array('Что требует решения', 'Цех сейчас', 'Заказы в работе', 'Склад: чего не хватает', 'Качество', 'Простои станков') as $title)
        {
            $this->assertStringContainsString($title, $html, 'Блок «'.$title.'» есть на экране');
        }

        $this->assertSame(6, substr_count($html, '<caption'), 'У каждой таблицы есть подпись');
        $this->assertGreaterThan(10, substr_count($html, 'scope="'), 'Заголовки колонок со scope');
        $this->assertSame(6, substr_count($html, 'overflow-x-auto'), 'Длинные таблицы прокручиваются на узком экране');
        $this->assertStringContainsString('Покрытие запасов', $html);
        $this->assertStringContainsString('дней', $html, 'Единица измерения у показателя');
    }

    public function test_showcase_data_cannot_become_markup()
    {
        $html = $this->render($this->dataset());

        $this->assertStringNotContainsString('<script>alert(1)', $html, 'Данные не становятся разметкой');
        $this->assertStringContainsString('&lt;script&gt;', $html, 'Опасный текст приходит экранированным');
        $this->assertStringContainsString('aria-label="Готовность 40 процентов"', $html, 'Полоса готовности описана словами');
    }

    public function test_empty_view_explains_what_happened()
    {
        $html = $this->render(array(
            'period' => array('from' => '2001-01-01', 'to' => '2001-01-02'),
            'kpi' => array(), 'attention' => array(),
            'shop' => array('state' => 'neutral', 'operations' => array(), 'pareto' => array(), 'plan_fact' => array()),
            'orders' => array('rows' => array(), 'stats' => array()),
            'stock' => array('rows' => array(), 'items' => 0),
            'quality' => array('causes' => array()),
        ));

        $this->assertStringContainsString('Ничего не требует решения', $html);
        $this->assertStringContainsString('Открытых заказов нет', $html);
        $this->assertStringContainsString('Дефицита нет', $html);
        $this->assertStringNotContainsString('<table', $html, 'Пустая таблица не рисуется вообще');
    }

    // =========================================================================
    //  Маршрут и доступ
    // =========================================================================

    public function test_route_leads_to_the_stand_controller()
    {
        $route = \Route::get('erp811_demo_workspace');

        // В этой версии Route метод defaults() без аргументов читает набор
        // значений по умолчанию (с аргументом — пишет).
        $defaults = (array) $route->defaults();

        $this->assertSame('Erp811', $defaults['directory'], 'Адрес ведёт в контроллер стенда');
        $this->assertSame('Demo', $defaults['controller']);
        $this->assertSame('workspace', $defaults['action']);
        $this->assertSame('demo/workspace', ltrim($route->uri(array()), '/'), 'Адрес — /demo/workspace');
        $this->assertTrue(method_exists('Controller_Erp811_Demo', 'action_workspace'), 'Действие объявлено');
    }

    public function test_workspace_is_public_for_the_showcase()
    {
        $reflection = new \ReflectionClass('Controller_Erp811_Demo');
        $property   = $reflection->getProperty('public_actions');
        $property->setAccessible(TRUE);

        $this->assertContains('workspace', $property->getValue($reflection->newInstanceWithoutConstructor()), 'Экран показа открыт без входа');
    }

    public function test_showcase_page_does_not_leak_the_working_database()
    {
        // Экран в белом списке (иначе показ упирался бы в 403 до входа), но
        // сам решает, что показать: вне демо-режима и без вошедшего
        // пользователя гостя уводит на лендинг, а не отдаёт рабочую базу.
        // Живой прогон это подтверждает: аноним получает 302 на /demo.
        $source = file_get_contents(APPPATH.'modules/erp811_demo/classes/Controller/Erp811/Demo.php');

        $this->assertMatchesRegularExpression(
            '/! \$this->_current_user\s*\)\s*\{\s*\$this->redirect\(Route::get\(\'erp811_demo_index\'\)/s',
            $source,
            'Вне демо-режима анонимного посетителя ведём на лендинг стенда'
        );
    }
}
