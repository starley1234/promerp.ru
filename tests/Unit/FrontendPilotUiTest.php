<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Regression contract for recently added operator-facing modules. */
class FrontendPilotUiTest extends TestCase
{
    public function test_pilot_pages_use_module_shell_with_top_menu()
    {
        $contracts = array(
            'erp231_product_structure' => array('Erp231','Structure','/product-structures'),
            'erp412_traceability'      => array('Erp412','Traceability','/traceability'),
            'erp802_integration'       => array('Erp802','Integration','/integration'),
            'erp409_costing'           => array('Erp409','Index','/costing'),
        );

        foreach ($contracts as $module => $contract)
        {
            list($directory, $controller, $href) = $contract;
            $main = APPPATH.'modules/'.$module.'/classes/Controller/'.$directory.'/Main.php';
            $human = APPPATH.'modules/'.$module.'/classes/Controller/'.$directory.'/'.$controller.'.php';
            $this->assertFileExists($main);
            $this->assertStringContainsString('set_menu(', file_get_contents($main), $module.' объявляет верхнее меню');
            $this->assertStringContainsString('extends Controller_'.$directory.'_Main', file_get_contents($human), $module.' использует общий shell модуля');
            $this->assertStringContainsString($href, file_get_contents($main));
        }
    }

    public function test_global_navigation_and_ctrl_k_share_role_filtered_catalogue()
    {
        $manager = \Ui_Navigation::categories(array('login','manager'));
        $manager_palette = \Ui_Navigation::palette(array('login','manager'));
        $manager_hrefs = array();
        foreach ($manager as $category) foreach ($category['items'] as $item) $manager_hrefs[] = $item['href'];
        $palette_hrefs = array_column($manager_palette, 'href');

        foreach (array('/product-structures','/traceability','/integration','/costing') as $href)
        {
            $this->assertContains($href, $manager_hrefs);
            $this->assertContains($href, $palette_hrefs, $href.' доступен и через Ctrl+K');
        }
        $this->assertNotContains('/access/users', $manager_hrefs, 'администрирование не показывается без права');

        $worker_hrefs = array_column(\Ui_Navigation::palette(array('login','worker')), 'href');
        $this->assertContains('/documents', $worker_hrefs);
        $this->assertContains('/quality', $worker_hrefs);
        $this->assertNotContains('/traceability', $worker_hrefs, 'палитра не предлагает запрещённый раздел');
        $this->assertNotContains('/docs/mcp', $worker_hrefs, 'MCP-консоль скрыта без права');
    }

    public function test_main_stylesheet_uses_content_version_to_avoid_stale_table_css()
    {
        $template = file_get_contents(APPPATH.'views/template_tailwindcss.php');
        $this->assertStringContainsString("Assets::url('css')", $template,
            'После сборки браузер должен получить новый URL CSS, а не закэшированную таблицу');
        $this->assertStringNotContainsString('href="/assets/css/app.css"', $template);
        $this->assertStringContainsString('?v=', \Assets::url('css'));
    }

    public function test_document_tables_use_shared_responsive_component()
    {
        foreach (array('document/index.php', 'document/view.php', 'template/index.php') as $relative)
        {
            $source = file_get_contents(APPPATH.'modules/erp404_documents/views/erp404/'.$relative);
            $this->assertStringContainsString("Component::render('table'", $source, $relative.' использует общий table component');
            $this->assertStringNotContainsString('<table', $source, $relative.' не дублирует ручную разметку таблицы');
        }
    }

    public function test_wms_tables_and_dashboard_kpis_use_shared_components()
    {
        $views = array(
            'index/index.php', 'movement/index.php', 'reservation/index.php',
            'warehouse/index.php', 'widget/item_card.php',
        );
        foreach ($views as $relative)
        {
            $source = file_get_contents(APPPATH.'modules/erp401_wms/views/erp401/'.$relative);
            $this->assertStringContainsString("Component::render('table'", $source, $relative.' использует общий table component');
            $this->assertStringNotContainsString('<table', $source, $relative.' не дублирует ручную разметку таблицы');
        }
        $dashboard = file_get_contents(APPPATH.'modules/erp401_wms/views/erp401/index/index.php');
        $this->assertStringContainsString("Component::render('kpi'", $dashboard, 'складская сводка использует KPI с единицами');
    }

    public function test_production_tables_use_shared_responsive_component()
    {
        foreach (array('index/index.php', 'order/index.php', 'order/view.php') as $relative)
        {
            $source = file_get_contents(APPPATH.'modules/erp402_production/views/erp402/'.$relative);
            $this->assertStringContainsString("Component::render('table'", $source, $relative.' использует общий table component');
            $this->assertStringNotContainsString('<table', $source, $relative.' не дублирует ручную разметку таблицы');
        }
    }

    public function test_quality_tables_use_shared_responsive_component()
    {
        foreach (array('defect/index.php', 'index/quarantine.php', 'inspection/index.php', 'inspection/view.php') as $relative)
        {
            $source = file_get_contents(APPPATH.'modules/erp405_quality/views/erp405/'.$relative);
            $this->assertStringContainsString("Component::render('table'", $source, $relative.' использует общий table component');
            $this->assertStringNotContainsString('<table', $source, $relative.' не дублирует ручную разметку таблицы');
        }
    }

    public function test_planning_dashboards_use_shared_responsive_components()
    {
        foreach (array('index/deficits.php', 'index/index.php', 'program/index.php', 'program/view.php', 'proposal/index.php', 'widget/item_deficits.php') as $relative)
        {
            $source = file_get_contents(APPPATH.'modules/erp407_planning/views/erp407/'.$relative);
            $this->assertStringContainsString("Component::render('table'", $source, $relative.' использует общий table component');
            $this->assertStringNotContainsString('<table', $source, $relative.' не дублирует ручную разметку таблицы');
        }
        $deficits = file_get_contents(APPPATH.'modules/erp407_planning/views/erp407/index/deficits.php');
        $this->assertStringContainsString("Component::render('kpi'", $deficits, 'дефициты показывают единицы и целевое покрытие через KPI');
    }

    public function test_shopfloor_tables_kpis_and_operational_forms_use_shared_components()
    {
        $views = array(
            'centers/index.php', 'index/index.php', 'operations/index.php',
            'operations/view.php', 'shifts/index.php', 'widget/order_operations.php',
        );
        foreach ($views as $relative)
        {
            $source = file_get_contents(APPPATH.'modules/erp408_shopfloor/views/erp408/'.$relative);
            $this->assertStringContainsString("Component::render('table'", $source, $relative.' использует общий table component');
            $this->assertStringNotContainsString('<table', $source, $relative.' не дублирует ручную разметку таблицы');
        }

        foreach (array('centers/index.php', 'index/index.php') as $relative)
        {
            $source = file_get_contents(APPPATH.'modules/erp408_shopfloor/views/erp408/'.$relative);
            $this->assertStringContainsString("Component::render('kpi'", $source, $relative.' показывает операционные показатели с единицами');
        }
        $operations = file_get_contents(APPPATH.'modules/erp408_shopfloor/views/erp408/operations/index.php');
        $this->assertStringContainsString("Component::render('pagination'", $operations, 'пагинация операций использует общий компонент');

        $source = '';
        foreach (glob(APPPATH.'modules/erp408_shopfloor/views/erp408/*/*.php') as $view) $source .= file_get_contents($view);
        $this->assertStringContainsString('autocomplete="off"', $source, 'операционные формы цеха защищены от browser autofill');
    }

    public function test_procurement_tables_and_item_selection_use_shared_components()
    {
        $views = array(
            'index/low_stock.php', 'order/form.php', 'order/index.php', 'order/view.php',
            'receipt/index.php', 'receipt/view.php', 'request/index.php', 'request/view.php',
            'supplier/compare.php', 'supplier/index.php', 'supplier/view.php',
        );
        foreach ($views as $relative)
        {
            $source = file_get_contents(APPPATH.'modules/erp403_procurement/views/erp403/'.$relative);
            $this->assertStringContainsString("Component::render('table'", $source, $relative.' использует общий table component');
            $this->assertStringNotContainsString('<table', $source, $relative.' не дублирует ручную разметку таблицы');
        }

        foreach (array('order/form.php', 'request/view.php', 'supplier/compare.php') as $relative)
        {
            $source = file_get_contents(APPPATH.'modules/erp403_procurement/views/erp403/'.$relative);
            $this->assertStringContainsString("Component::render('smart_reference'", $source, $relative.' не требует ручного ввода внутреннего ID');
            $this->assertStringContainsString('/procurement/suppliers/reference', $source);
        }
        $controller = file_get_contents(APPPATH.'modules/erp403_procurement/classes/Controller/Erp403/Supplier.php');
        $this->assertStringContainsString('function action_reference()', $controller);
        $this->assertStringContainsString("can('erp403.view')", $controller, 'справочник защищён правом procurement');
    }

    public function test_pilot_views_use_shared_components_dark_theme_and_autocomplete_policy()
    {
        $views = array(
            'erp231_product_structure/views/erp231/index.php',
            'erp231_product_structure/views/erp231/view.php',
            'erp412_traceability/views/erp412/index.php',
            'erp412_traceability/views/erp412/view.php',
            'erp802_integration/views/erp802/index.php',
            'erp802_integration/views/erp802/view.php',
        );

        foreach ($views as $relative)
        {
            $source = file_get_contents(APPPATH.'modules/'.$relative);
            $this->assertStringContainsString("Component::", $source, $relative.' использует общую UI-библиотеку');
            $this->assertStringContainsString('dark:', $source, $relative.' поддерживает общую тёмную тему');
        }

        foreach (array('erp231_product_structure','erp412_traceability','erp802_integration') as $module)
        {
            $source = '';
            foreach (glob(APPPATH.'modules/'.$module.'/views/*/*.php') as $view) $source .= file_get_contents($view);
            $this->assertStringContainsString('autocomplete="off"', $source, $module.' защищает операционные поля от browser autofill');
        }
    }
}
