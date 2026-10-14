<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Быстрый статический аудит кода (application/classes/Dev/Auditor.php).
 *
 * Тест работает без БД и сети: разбирает 700+ файлов токенайзером за ~0,2 с,
 * поэтому пригоден как обратная связь после каждой правки:
 *
 *   bin/console dev:audit      # подробный отчёт
 *   bin/test                   # эти тесты как защита от регрессий
 *
 * Разрешённые (известные) отклонения перечислены в $known_errors.
 */
class CodeAuditTest extends TestCase
{
    /** @var array */
    protected $result;

    /**
     * Известные дефекты живут в application/audit-baseline.php: оттуда их читает
     * и задача dev:audit, поэтому список один на весь проект.
     */

    protected function setUp(): void
    {
        $this->result = (new \Dev_Auditor)->audit();
    }

    /**
     * Ошибки, которые считаются недопустимыми всегда
     * (fatal при загрузке, поломка CLI, неработающие страницы).
     */
    public function test_no_blocking_code_defects()
    {
        $blocking = ['duplicate_class', 'signature', 'abstract', 'task_file', 'task_contract', 'migration', 'view'];

        $violations = [];

        foreach ($this->result['errors'] as $error)
        {
            if (in_array($error['check'], $blocking, TRUE))
            {
                $violations[] = $error['check'].': '.$error['file'].':'.$error['line'].' — '.$error['message'];
            }
        }

        $this->assertSame([], $violations, "Найдены блокирующие дефекты кода:\n".implode("\n", $violations));
    }

    /**
     * Нет ссылок на представления, которых не существует.
     */
    public function test_all_views_exist()
    {
        $missing = [];

        foreach ($this->result['errors'] as $error)
        {
            if ($error['check'] === 'view')
            {
                $missing[] = $error['file'].':'.$error['line'].' — '.$error['message'];
            }
        }

        $this->assertSame([], $missing, "Отсутствующие представления:\n".implode("\n", $missing));
    }

    /**
     * Все задачи Minion и миграции соблюдают контракт (класс по пути, up/down).
     */
    public function test_tasks_and_migrations_follow_contracts()
    {
        $problems = [];

        foreach ($this->result['errors'] as $error)
        {
            if (in_array($error['check'], ['task_file', 'task_contract', 'migration'], TRUE))
            {
                $problems[] = $error['check'].': '.$error['message'];
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    /**
     * Ошибки, которых нет в baseline, должны быть устранены.
     */
    public function test_no_undocumented_errors()
    {
        $unknown = [];

        foreach ($this->result['errors'] as $error)
        {
            $unknown[] = $error['check'].': '.$error['file'].':'.$error['line'].' — '.$error['message'];
        }

        $this->assertSame([], $unknown,
            "Новые ошибки аудита (исправьте или внесите в application/audit-baseline.php):\n".implode("\n", $unknown));
    }

    /**
     * Список известных отклонений должен оставаться актуальным.
     */
    public function test_baseline_has_no_stale_entries()
    {
        $stale = [];

        foreach ($this->result['stale'] as $entry)
        {
            $stale[] = $entry['check'].' → '.(isset($entry['file']) ? $entry['file'] : '*');
        }

        $this->assertSame([], $stale,
            "Записи application/audit-baseline.php больше не нужны — удалите их:\n".implode("\n", $stale));
    }

    /**
     * Аудит должен оставаться быстрым: он вызывается в каждом прогоне тестов.
     */
    public function test_audit_is_fast()
    {
        $this->assertLessThan(5.0, $this->result['stats']['time'],
            'Аудит должен укладываться в несколько секунд (сейчас '.$this->result['stats']['time'].' с)');
    }

    /**
     * Аудит действительно видит код проекта.
     */
    public function test_audit_covers_the_codebase()
    {
        $this->assertGreaterThan(500, $this->result['stats']['files'], 'Разобрано слишком мало файлов');
        $this->assertGreaterThan(500, $this->result['stats']['classes'], 'Разобрано слишком мало классов');
    }
}
