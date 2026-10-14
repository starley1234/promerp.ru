<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Регрессия S3: boolean из PostgreSQL читается как строка `t`/`f`.
 *
 * Драйвер отдаёт boolean строкой, а `'f'` в PHP — непустая строка, то есть
 * «истина»: `(bool) $model->is_dirty` и `! empty($model->is_dirty)` были
 * одинаковыми для `true` и `false`. Заметно это только на записи из БД, поэтому
 * проверка читает модель, а не считает значения в памяти.
 *
 * Модуль MDM (`erp230_mdm`) — живой потребитель: его модели работают через
 * отдельную группу `mdm`, и приведение сделано в базовой модели
 * (`Model_Erp230_Main::get()`), а не в каждом представлении.
 */
class Erp230BooleanReadTest extends TestCase
{
    protected function setUp(): void
    {
        if ( ! class_exists('Model_Erp230_Object'))
        {
            $this->markTestSkipped('Модуль MDM (erp230) не подключён');
        }

        if ( ! class_exists('Service_Erp407_Programs'))
        {
            // Планирование — второй потребитель правила; без него тест не имеет смысла
            $this->markTestSkipped('Модуль планирования (erp407) не подключён');
        }
    }

    public function test_mdm_boolean_columns_are_cast_to_bool()
    {
        try
        {
            $object = \ORM::factory('Erp230_Object')->find();
        }
        catch (\Exception $e)
        {
            $this->markTestSkipped('База MDM недоступна: '.$e->getMessage());
            return;
        }

        if ( ! $object->loaded())
        {
            $this->markTestSkipped('В базе MDM нет ни одного объекта');
            return;
        }

        $this->assertTrue(is_bool($object->is_dirty), 'is_dirty приходит из модели как bool, а не строкой t/f');
        $this->assertTrue(is_bool($object->as_array()['is_dirty']), 'то же и в массиве модели');

        // «Грязный» объект отдаёт true, чистый — false; проверяем смысл, а не тип
        $this->assertSame(
            ($object->as_array()['is_dirty'] === TRUE),
            $object->is_dirty,
            'значение и его массив совпадают'
        );
    }

    public function test_planning_requirement_reports_late_as_bool()
    {
        try
        {
            $requirement = \ORM::factory('Erp407_Requirement')->order_by('id', 'DESC')->find();
        }
        catch (\Exception $e)
        {
            $this->markTestSkipped('Таблиц планирования нет: '.$e->getMessage());
            return;
        }

        if ( ! $requirement->loaded())
        {
            $this->markTestSkipped('Расчётов планирования ещё не было');
            return;
        }

        $this->assertTrue(is_bool($requirement->is_late()), 'is_late() отвечает bool даже для строки f/t');

        // В снимке лежит тот же смысл, что и в самой строке БД
        $raw = \DB::select('late')->from('erp_407_requirements')
            ->where('id', '=', (int) $requirement->id)->execute()->get('late');

        $this->assertSame(\Erp::bool($raw), $requirement->is_late(),
            'is_late() совпадает со значением в базе');
    }
}
