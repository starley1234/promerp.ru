<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты интерактивной документации (модуль erp812_docs).
 *
 * Проверяют:
 *  - Корректность Markdown-рендеринга (таблицы, заголовки, callout-блоки, Mermaid-диаграммы);
 *  - Автоматическое извлечение метаданных модулей (DocRegistry);
 *  - Наличие и связность сквозных бизнес-процессов (P2P, O2C, Производство, Склад, ТОиР);
 *  - Полнотекстовый поисковый движок по модулям, процессам, таблицам и MCP-инструментам;
 *  - Регистрацию инструментов Model Context Protocol (MCP);
 *  - Маршрутизацию и целостность контроллеров;
 *  - Наличие локальных офлайн-ассетов (mermaid.min.js без CDN).
 */
class Erp812DocsTest extends TestCase
{
    /** @var \Service_Erp812_DocRegistry */
    protected $registry;

    protected function setUp(): void
    {
        $this->registry = \Service_Erp812_DocRegistry::instance();
    }

    // =========================================================================
    //  MarkdownRenderer: Форматирование и расширения
    // =========================================================================

    public function test_markdown_renderer_headings_and_tables()
    {
        $input = "# Заголовок 1\n## Заголовок 2\n\n| Колонка 1 | Колонка 2 |\n|---|---|\n| Значение 1 | Значение 2 |";
        $html = \Service_Erp812_MarkdownRenderer::render($input);

        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Заголовок 1</h1>', $html);
        $this->assertStringContainsString('<h2', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('Колонка 1', $html);
        $this->assertStringContainsString('Значение 1', $html);
    }

    public function test_markdown_renderer_converts_mermaid_blocks()
    {
        $input = "```mermaid\ngraph TD\n    A --> B\n```";
        $html = \Service_Erp812_MarkdownRenderer::render($input);

        $this->assertStringContainsString('class="mermaid', $html);
        $this->assertStringContainsString('graph TD', $html);
        $this->assertStringContainsString('A --&gt; B', $html);
    }

    public function test_markdown_renderer_callouts()
    {
        $input = "> [!NOTE]\n> Это информационная заметка.\n\n> [!WARNING]\n> Это предупреждение.";
        $html = \Service_Erp812_MarkdownRenderer::render($input);

        $this->assertStringContainsString('Примечание', $html);
        $this->assertStringContainsString('Это информационная заметка', $html);
        $this->assertStringContainsString('Внимание', $html);
        $this->assertStringContainsString('Это предупреждение', $html);
    }

    // =========================================================================
    //  DocRegistry: Извлечение метаданных и связность
    // =========================================================================

    public function test_doc_registry_extracts_all_modules()
    {
        $modules = $this->registry->modules();

        $this->assertNotEmpty($modules, 'Реестр модулей не должен быть пустым');
        $this->assertGreaterThanOrEqual(10, count($modules), 'В реестре должно быть как минимум 10 модулей');

        foreach (array('erp230_mdm', 'erp401_wms', 'erp402_production', 'erp403_procurement', 'erp812_docs') as $key)
        {
            $this->assertArrayHasKey($key, $modules, 'Модуль '.$key.' присутствует в реестре');
            $m = $modules[$key];
            $this->assertNotEmpty($m['title'], 'У модуля '.$key.' есть название');
            $this->assertNotEmpty($m['category'], 'У модуля '.$key.' определена категория');
            $this->assertIsArray($m['routes'], 'У модуля '.$key.' массив маршрутов');
            $this->assertIsArray($m['tables'], 'У модуля '.$key.' массив таблиц');
        }
    }

    public function test_doc_registry_business_processes()
    {
        $processes = $this->registry->business_processes();

        $this->assertNotEmpty($processes, 'Список бизнес-процессов не пустой');

        $expected_processes = array('p2p', 'o2c', 'production', 'wms_flow', 'maintenance');
        foreach ($expected_processes as $p_id)
        {
            $this->assertArrayHasKey($p_id, $processes, 'Процесс '.$p_id.' зарегистрирован');
            $p = $processes[$p_id];
            $this->assertNotEmpty($p['title']);
            $this->assertNotEmpty($p['description']);
            $this->assertNotEmpty($p['mermaid'], 'У процесса '.$p_id.' есть диаграмма Mermaid');
            $this->assertStringContainsString('graph', $p['mermaid']);
            $this->assertNotEmpty($p['steps'], 'У процесса '.$p_id.' есть пошаговый регламент');
            $this->assertNotEmpty($p['modules'], 'У процесса '.$p_id.' указаны связанные модули');
        }
    }

    public function test_doc_registry_search()
    {
        // Поиск по модулю склада
        $results = $this->registry->search('склад');
        $this->assertNotEmpty($results, 'Поиск по слову "склад" находит результаты');

        // Поиск по ключевому слову MCP
        $results_mcp = $this->registry->search('search');
        $this->assertNotEmpty($results_mcp, 'Поиск по ключевому слову MCP находит инструменты');

        // Поиск по процессу
        $results_proc = $this->registry->search('закупки');
        $this->assertNotEmpty($results_proc, 'Поиск по закупкам находит процесс P2P');
    }

    // =========================================================================
    //  MCP Tools: Инструменты AI
    // =========================================================================

    public function test_mcp_tools_declaration()
    {
        $declaration = \Kohana::find_file('modules/erp812_docs', 'mcp');
        $this->assertTrue($declaration !== FALSE, 'Файл mcp.php существует в erp812_docs');

        $mcp = include $declaration;
        $this->assertIsArray($mcp);
        $this->assertNotEmpty($mcp['tools'], 'Инструменты MCP объявлены');

        foreach ($mcp['tools'] as $tool_name => $tool)
        {
            $this->assertNotEmpty($tool_name);
            $this->assertNotEmpty($tool['title']);
            $this->assertNotEmpty($tool['description']);
            $this->assertFalse($tool['mutating'], 'Инструменты документации доступны только для чтения');
            $this->assertTrue(is_callable($tool['handler']), 'У инструмента есть исполняемый обработчик');
            
            // Вызов инструмента docs.search через handler
            if ($tool_name === 'docs.search')
            {
                $res = call_user_func($tool['handler'], array('query' => 'mdm'));
                $this->assertArrayHasKey('results', $res);
            }
            if ($tool_name === 'docs.process.get')
            {
                $res = call_user_func($tool['handler'], array('process_id' => 'p2p'));
                $this->assertArrayHasKey('process', $res);
            }
        }
    }

    // =========================================================================
    //  Маршруты и контроллеры
    // =========================================================================

    public function test_routes_and_controllers_resolve()
    {
        $routes = array(
            'erp812_docs_home'         => array('Erp812_Index', 'index', '/docs'),
            'erp812_docs_processes'    => array('Erp812_Processes', 'index', '/docs/processes'),
            'erp812_docs_modules'      => array('Erp812_Modules', 'index', '/docs/modules'),
            'erp812_docs_architecture' => array('Erp812_Architecture', 'index', '/docs/architecture'),
            'erp812_docs_mcp'          => array('Erp812_Mcp', 'index', '/docs/mcp'),
            'erp812_docs_api'          => array('Erp812_Api', 'search', '/erp812_api/search'),
        );

        foreach ($routes as $route_name => $expected)
        {
            $route = \Route::get($route_name);
            $this->assertNotNull($route, 'Маршрут '.$route_name.' зарегистрирован');

            $class = 'Controller_'.$expected[0];
            $action = 'action_'.$expected[1];

            $this->assertTrue(class_exists($class), 'Класс контроллера '.$class.' существует');
            $this->assertTrue(method_exists($class, $action), 'Метод '.$action.' существует в '.$class);
        }
    }

    public function test_acl_permissions_registered()
    {
        $acl = \Kohana::$config->load('acl')->as_array();
        $this->assertArrayHasKey('erp812.view', $acl, 'Право erp812.view объявлено в acl.php');
        $this->assertContains('admin', (array)$acl['erp812.view']);
        $this->assertContains('manager', (array)$acl['erp812.view']);
    }

    // =========================================================================
    //  Offline-First: Локальные вендорные ассеты
    // =========================================================================

    public function test_offline_mermaid_asset_exists()
    {
        $asset_path = DOCROOT . 'assets/vendor/mermaid/mermaid.min.js';
        $this->assertFileExists($asset_path, 'Файл mermaid.min.js размещен локально в assets/vendor/mermaid/');
        $this->assertGreaterThan(1000000, filesize($asset_path), 'Размер mermaid.min.js превышает 1 МБ');
    }
}
