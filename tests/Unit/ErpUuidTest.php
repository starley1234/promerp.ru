<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Идентификатор объекта известен сразу после создания (uuid).
 *
 * Найдено при живой проверке модуля документов: колонку `uuid` заполняла база
 * (`gen_random_uuid()`), модель о выданном значении не знала до перечитывания
 * строки — и журнал действий (`erp119`) получал запись с пустым `entity_id`.
 * Теперь uuid выдаётся помощью `Erp::uuid()` до вставки, и это проверяется
 * здесь: помощник, модели проектных модулей и запись в журнал сразу после
 * создания.
 *
 * Тестовые записи помечены `ТЕСТ-UUID*` и удаляются в tearDown.
 */
class ErpUuidTest extends TestCase
{
    protected function tearDown(): void
    {
        try
        {
            $orders = \DB::select('id')->from('erp_403_orders')->where('number', 'LIKE', 'ТЕСТ-UUID%')->execute()->as_array();
            $ids    = array_column($orders, 'id');

            if ($ids)
            {
                \DB::delete('erp_119_audit_log')
                    ->where('module', 'LIKE', 'erp403%')
                    ->where('message', 'LIKE', '%ТЕСТ-UUID%')
                    ->execute();
                \DB::delete('erp_403_orders')->where('id', 'IN', $ids)->execute();
            }

            \DB::delete('erp_402_orders')->where('number', 'LIKE', 'ТЕСТ-UUID%')->execute();
            \DB::delete('erp_404_templates')->where('code', 'LIKE', 'ТЕСТ-UUID%')->execute();
        }
        catch (\Exception $e)
        {
            // уборка не должна прятать настоящую ошибку теста
        }
    }

    // =========================================================================
    //  Помощник
    // =========================================================================

    public function test_uuid_helper_makes_canonical_v4()
    {
        $uuid = \Erp::uuid();

        $this->assertTrue(\Erp::is_uuid($uuid), 'uuid в каноническом виде: '.$uuid);
        $this->assertSame('4', substr($uuid, 14, 1), 'версия 4');
        $this->assertContains(substr($uuid, 19, 1), array('8', '9', 'a', 'b'), 'вариант RFC 4122');

        $many = array();

        for ($i = 0; $i < 200; $i++)
        {
            $many[] = \Erp::uuid();
        }

        $this->assertCount(200, array_unique($many), 'uuid не повторяются');

        // Готовый uuid разбирается как идентификатор объекта.
        $ref = \Erp::id_or_uuid($uuid);

        $this->assertSame('uuid', $ref[0]);
        $this->assertSame($uuid, $ref[1]);
    }

    // =========================================================================
    //  Модели проектных модулей
    // =========================================================================

    public function test_models_know_their_uuid_right_after_create()
    {
        $order = \ORM::factory('Erp403_Order');
        $order->number = 'ТЕСТ-UUID-'.random_int(100000, 999999);
        $order->status = 'draft';
        $order->save();

        $this->assertTrue($order->loaded(), 'заказ закупки создан');
        $this->assertTrue(\Erp::is_uuid((string) $order->uuid), 'модель заказа знает свой uuid: "'.$order->uuid.'"');

        $template = \ORM::factory('Erp404_Template');
        $template->code    = 'ТЕСТ-UUID-'.random_int(100000, 999999);
        $template->name    = 'Проверка uuid';
        $template->ref_type = '';
        $template->kind    = 'print';
        $template->body    = '<p>{{document.number}}</p>';
        $template->version = 1;
        $template->is_active = 1;
        $template->save();

        $this->assertTrue(\Erp::is_uuid((string) $template->uuid), 'модель шаблона знает свой uuid');
    }

    /**
     * Журнал действий привязывает запись к объекту сразу, а не к пустой строке.
     */
    public function test_journal_keeps_entity_uuid_of_fresh_model()
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            $this->markTestSkipped('Журнал действий не подключён');
        }

        $order = \ORM::factory('Erp403_Order');
        $order->number = 'ТЕСТ-UUID-'.random_int(100000, 999999);
        $order->status = 'draft';
        $order->save();

        $this->assertTrue(\Erp::is_uuid((string) $order->uuid), 'uuid выдан до записи в журнал: "'.$order->uuid.'"');

        \Service_Erp119_Journal::instance()->change('order', (string) $order->uuid, 'create', array(), array('number' => $order->number), array(
            'module'       => 'erp403_procurement',
            'entity_label' => (string) $order->number,
            'message'      => 'Тестовая запись ТЕСТ-UUID: проверка привязки к объекту',
        ));

        $entry = \DB::select('entity_id')
            ->from('erp_119_audit_log')
            ->where('entity_id', '=', (string) $order->uuid)
            ->limit(1)
            ->execute()
            ->current();

        $this->assertNotEmpty($entry, 'запись журнала нашлась по uuid объекта');
        $this->assertSame((string) $order->uuid, (string) $entry['entity_id'], 'в журнале — uuid, а не пустая строка');
    }
}
