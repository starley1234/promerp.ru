<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Страница-эталон интерфейса `/pages/required_design_sample`.
 *
 * Проверяется поведение, а не наличие файла:
 *
 *  - роут ведёт на действие контроллера, а страница открыта гостю: её читает
 *    агент, и требовать вход ради справочника нельзя;
 *  - на странице есть все четыре типа экранов (ввод, карточка, перечень,
 *    сводка) и живые примеры общих компонентов;
 *  - эталон собран только на компонентах: он показывает правила интерфейса и
 *    поэтому обязан сам их соблюдать (без ручных таблиц, без режима в
 *    представлении, без inline-обработчиков и стилей);
 *  - страницу можно найти: она есть в меню, в Ctrl+K и в дымовом прогоне;
 *  - источник вариантов для примера `smart_reference` отвечает контрактом
 *    `{items:[{id,label,context}]}` и только читает.
 */
class DesignSampleTest extends TestCase
{
    /**
     * Гостевой режим: снимаем вход, оставшийся от других тестов, и поднимаем
     * сессию — в CLI она сама не стартует.
     *
     * @return void
     */
    protected function become_guest()
    {
        // Вход снимаем явно (см. комментарий в PublicPagesTest::become_guest()):
        // после разрушенной сессии прошлым тестом `logged_in()` может вернуть
        // TRUE, и страница ответит рабочим местом вместо гостевой.
        $_SESSION = array();

        try
        {
            \Session::instance()->delete('auth_user');
        }
        catch (\Throwable $e)
        {
            // Сессии могло не быть вовсе.
        }

        try
        {
            if (\Auth::instance()->logged_in())
            {
                \Auth::instance()->logout(TRUE);
            }
        }
        catch (\Throwable $e)
        {
            // Сессия в CLI могла быть уже уничтожена — гостю это не мешает.
        }

        if (session_status() !== PHP_SESSION_ACTIVE AND ! headers_sent())
        {
            @session_start();
        }

        $_SESSION = array();
    }

    public function test_route_leads_to_the_pages_controller()
    {
        $route = \Route::get('design_sample');

        $this->assertNotNull($route, 'Роут страницы-эталона не зарегистрирован');

        $defaults = $route->defaults();

        $this->assertSame('Pages', (string) $defaults['controller']);
        $this->assertSame('required_design_sample', (string) $defaults['action']);
        $this->assertTrue(method_exists('Controller_Pages', 'action_required_design_sample'),
            'Нет действия Controller_Pages::action_required_design_sample');
    }

    public function test_page_is_open_to_a_guest()
    {
        $this->become_guest();

        $response = \Request::factory('/pages/required_design_sample')->execute();

        $this->assertSame(200, $response->status(),
            'Страница-эталон должна открываться без входа: её читает агент');

        $body = $response->body();

        $this->assertStringContainsString('Эталон интерфейса', $body);
        $this->assertStringNotContainsString('Доступ ограничен', $body,
            'Гость снова видит страницу отказа');
    }

    public function test_page_shows_all_four_page_kinds()
    {
        $this->become_guest();

        $body = \Request::factory('/pages/required_design_sample')->execute()->body();

        foreach (array('page-form', 'page-item', 'page-list', 'page-summary') as $id)
        {
            $this->assertStringContainsString('id="'.$id.'"', $body,
                'На странице-эталоне нет примера '.$id);
        }
    }

    /**
     * Эталон показывает правила интерфейса, поэтому обязан сам их соблюдать:
     * только общие компоненты, никакого режима в представлении, никаких
     * inline-обработчиков и inline-стилей.
     */
    public function test_sample_page_follows_its_own_rules()
    {
        $source = file_get_contents(APPPATH.'views/pages/required_design_sample.php');

        // Компоненты, которые обязан показать эталон.
        $components = array('page_header', 'card', 'kpi', 'badge', 'table', 'empty',
            'pagination', 'filters', 'toolbar', 'form_field', 'smart_reference',
            'tabs', 'toast', 'more', 'mode_switch');

        foreach ($components as $component)
        {
            $this->assertMatchesRegularExpression(
                "/Component::(?:render|begin)\(\s*'".preg_quote($component, '/')."'/",
                $source,
                'На странице-эталоне не показан компонент '.$component
            );
        }

        $this->assertStringNotContainsString('<table', $source,
            'Эталон верстает список руками: пример перестанет совпадать с components/table');
        $this->assertStringNotContainsString('Ui_Mode::', $source,
            'Представление не спрашивает режим интерфейса');
        $this->assertStringNotContainsString('onclick=', $source,
            'Inline-обработчик: интерактив — через data-action/data-call');
        $this->assertStringNotContainsString(' style=', $source,
            'Inline-стиль: вид задают классы');
    }

    /**
     * Страницу должны находить: пункт меню, Ctrl+K и дымовой прогон. Экран без
     * навигационной ссылки не считается готовым.
     */
    public function test_page_is_reachable_from_menu_ctrl_k_and_smoke()
    {
        $roles   = array('login', 'erp812.view');
        $href    = '/pages/required_design_sample';
        $menu    = array();

        foreach (\Ui_Navigation::categories($roles) as $category)
        {
            foreach ($category['items'] as $item)
            {
                $menu[] = $item['href'];
            }
        }

        $this->assertContains($href, $menu, 'Страницы-эталона нет в общем меню');
        $this->assertContains($href, array_column(\Ui_Navigation::palette($roles), 'href'),
            'Страницу-эталон не найти через Ctrl+K');

        $urls = array();

        foreach ((new \Service_Dev_Smoke)->urls() as $entry)
        {
            $urls[] = $entry['url'];
        }

        $this->assertContains($href, $urls, 'Страница-эталон не проверяется в dev:smoke');
    }

    /**
     * Эталон показывает оба вида одних и тех же данных: по умолчанию —
     * таблицы, по запросу `?ui=simple` — плитки простого режима. Иначе агент
     * видит только половину интерфейса и верстает экран по плиткам.
     */
    public function test_page_shows_both_interface_views()
    {
        $this->become_guest();

        $pro = \Request::factory('/pages/required_design_sample')->execute()->body();

        $this->assertStringContainsString('<table', $pro,
            'По умолчанию эталон показывает таблицы, а не только плитки');

        $simple = \Request::factory('/pages/required_design_sample?ui=simple')->execute()->body();

        $this->assertStringNotContainsString('<table', $simple,
            '?ui=simple показывает плитки простого режима');
        $this->assertStringContainsString('<ul ', $simple);

        // Показ переключает вид только на время запроса: выбор человека не
        // должен меняться от того, что он открыл справочник.
        \Ui_Mode::force(NULL);

        $this->assertSame(\Ui_Mode::PRO, \Ui_Mode::current(),
            'Показ эталона не переключает режим интерфейса');
    }

    /**
     * Структура документа: на странице-эталоне ровно один h1. Примеры страниц
     * внутри показаны как h2 — иначе справочник сам нарушает своё же правило.
     */
    public function test_page_keeps_a_single_h1()
    {
        $this->become_guest();

        $body = \Request::factory('/pages/required_design_sample')->execute()->body();

        $this->assertSame(1, preg_match_all('/<h1[\s>]/', $body),
            'На странице должен быть один h1: примеры шапок идут с level => 2');
    }

    /**
     * Цель нажатия: интерфейсом пользуются на планшете в цеху, поэтому общие
     * компоненты держат высоту кнопок, полей и листания не меньше 40 px.
     */
    public function test_components_keep_a_touch_target()
    {
        $components = array(
            'filters',
            'toolbar',
            'form_field',
            'smart_reference',
            'pagination',
        );

        foreach ($components as $name)
        {
            $file = APPPATH.'views/components/'.$name.'.php';

            $this->assertFileExists($file);

            $this->assertStringContainsString('min-h-10', (string) file_get_contents($file),
                'Компонент '.$name.' держит цель нажатия не меньше 40 px');
        }
    }

    /**
     * Источник вариантов для живого примера `smart_reference`: контракт
     * `{items:[{id,label,context}]}`, доступ гостю, только чтение.
     */
    public function test_reference_endpoint_answers_the_picker_contract()
    {
        $this->become_guest();

        $response = \Request::factory('/pages/design_sample_reference?q='.rawurlencode('КРН'))->execute();

        $this->assertSame(200, $response->status(),
            'Пример выбора сущности должен работать и у гостя');

        $data = json_decode($response->body(), TRUE);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('items', $data);
        $this->assertNotEmpty($data['items'], 'По известному коду варианты находятся');

        foreach ($data['items'] as $item)
        {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('context', $item);
        }

        $empty = json_decode(\Request::factory('/pages/design_sample_reference?q=zzz-net-takogo')
            ->execute()->body(), TRUE);

        $this->assertSame(array(), $empty['items'], 'По неизвестному запросу вариантов нет');
    }
}
