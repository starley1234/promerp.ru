<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Документация «про модули» обязана совпадать с кодом.
 *
 * Таблица модулей в README и инвентарь в docs/ARCHITECTURE.md генерируются
 * (`make docs`), а docs/MODULE_MAP.md — целиком продукт генератора. Этот тест
 * ловит ситуацию, в которой кто-то добавил/выключил модуль, но документация
 * осталась прежней: раньше так в README жили удалённые модули.
 *
 * Починка: `make docs`, затем коммит обновлённых файлов.
 */
class DocsSyncTest extends TestCase
{
    public function test_generated_documentation_matches_code()
    {
        if ( ! class_exists('Service_Erp218_Docs'))
        {
            $this->markTestSkipped('Модуль erp218_module_manager не подключён');
        }

        $problems = \Service_Erp218_Docs::instance()->check();

        $this->assertSame([], $problems,
            "Документация устарела — выполните `make docs` и закоммитьте результат:\n  - "
            .implode("\n  - ", $problems));
    }

    /**
     * Карточка модуля — основной инструмент агента; она должна собираться для
     * каждого включённого ERP-модуля и отвечать на ключевые вопросы.
     */
    public function test_module_context_card_works_for_every_enabled_erp_module()
    {
        if ( ! class_exists('Service_Erp218_Context'))
        {
            $this->markTestSkipped('Модуль erp218_module_manager не подключён');
        }

        $context = \Service_Erp218_Context::instance();
        $broken  = [];

        foreach (\Service_Erp218_Inventory::instance()->modules() as $row)
        {
            if ( ! $row['enabled'] OR ! preg_match('/^erp\d{3}_/', $row['name']))
            {
                continue;
            }

            try
            {
                $card = $context->build($row['name']);
            }
            catch (\Throwable $e)
            {
                $broken[] = $row['name'].': '.$e->getMessage();
                continue;
            }

            foreach (['module', 'path', 'services', 'routes', 'acl', 'tests'] as $key)
            {
                if ( ! array_key_exists($key, $card))
                {
                    $broken[] = $row['name'].': в карточке нет раздела '.$key;
                }
            }
        }

        $this->assertSame([], $broken, 'Карточка контекста ломается на модулях: '.implode('; ', $broken));
    }

    /**
     * Новый модуль, созданный генератором, сразу описан в README — иначе
     * ModuleConventionsTest поймает это позже и без подсказки про карточку.
     */
    public function test_every_enabled_erp_module_has_readme_with_purpose()
    {
        $missing = [];

        foreach (\Service_Erp218_Inventory::instance()->modules() as $row)
        {
            if ( ! $row['enabled'] OR ! preg_match('/^erp\d{3}_/', $row['name']))
            {
                continue;
            }

            $purpose = \Service_Erp218_Context::instance()->purpose($row['path']);

            if ($purpose === NULL)
            {
                $missing[] = $row['name'];
            }
        }

        $this->assertSame([], $missing,
            'В README этих модулей нет внятного абзаца «Назначение»: '.implode(', ', $missing));
    }
}
