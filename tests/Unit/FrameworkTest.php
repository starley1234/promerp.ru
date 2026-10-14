<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Папки фреймворка (system/, modules/) — это Konine 3.4.0, их править нельзя.
 * Проектные доработки живут в application/ (каскад Kohana) или в модуле проекта.
 *
 * Тест сверяет файлы фреймворка с паспортом application/framework-manifest.php
 * (пересоздаётся задачей `bin/console dev:framework --update` при обновлении Konine).
 */
class FrameworkTest extends TestCase
{
    /** @var array */
    protected $report;

    protected function setUp(): void
    {
        $this->report = (new \Dev_Framework)->compare();
    }

    public function test_manifest_exists()
    {
        $this->assertGreaterThan(100, $this->report['manifest'],
            'Паспорт фреймворка отсутствует или пуст — выполните bin/console dev:framework --update');
    }

    public function test_framework_files_are_untouched()
    {
        $this->assertSame([], $this->report['modified'],
            "Файлы фреймворка изменены (доработки должны быть в application/):\n".implode("\n", $this->report['modified']));
    }

    public function test_framework_composition_is_stable()
    {
        $this->assertSame([], $this->report['added'],
            "Файлы добавлены в папки фреймворка:\n".implode("\n", $this->report['added']));

        $this->assertSame([], $this->report['removed'],
            "Файлы удалены из папок фреймворка:\n".implode("\n", $this->report['removed']));
    }
}
