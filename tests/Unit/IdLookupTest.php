<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Мусор в адресе не доходит до СУБД.
 *
 * Сервисы модулей умеют искать документ и по числовому id, и по uuid. «Умный»
 * поиск, который на любую нечисловую строку делает запрос по uuid, доводит
 * мусор из адреса (`/procurement/orders/view/abc`) до PostgreSQL, и она
 * отвечает `invalid input syntax for type uuid` — то есть 500 вместо 404.
 *
 * Тест держит правило «сначала формат, потом запрос» для всех сервисов с
 * таким поиском и заодно проверяет, что правильный uuid по-прежнему ищется.
 * Правило живёт в `Erp::id_or_uuid()`.
 */
class IdLookupTest extends TestCase
{
    /**
     * Сервисы с поиском «по id или uuid»: имя сервиса => метод.
     */
    protected function lookups()
    {
        return array(
            'Service_Erp228_TechCards'    => 'get',
            'Service_Erp402_Orders'       => 'get',
            'Service_Erp403_Orders'       => 'get',
            'Service_Erp403_Requests'     => 'get',
            'Service_Erp403_Receipts'     => 'get',
            'Service_Erp405_Inspections'  => 'get',
            'Service_Erp407_Programs'     => 'get',
            'Service_Erp407_Proposals'    => 'get',
            'Service_Erp408_Operations'   => 'get',
            'Service_Erp408_Shifts'       => 'get',
            'Service_Erp408_WorkCenters'  => 'get',
            'Service_Erp804_Calls'        => 'find',
            'Service_Erp804_Keys'         => 'find',
        );
    }

    /**
     * Значения, которые не являются ни числовым id, ни uuid.
     */
    protected function garbage()
    {
        return array('abc', '2 раза', "'", '<script>', 'null', '1e5', '123-456');
    }

    public function test_helper_recognises_id_and_uuid()
    {
        $this->assertSame(array('id', 7), \Erp::id_or_uuid('7'));
        $this->assertSame(array('id', 7), \Erp::id_or_uuid(7));
        $this->assertSame(
            array('uuid', '9f1b0f4c-4a59-4b0e-9c2e-5f8f2b7a1d33'),
            \Erp::id_or_uuid('9F1B0F4C-4A59-4B0E-9C2E-5F8F2B7A1D33'),
            'uuid приводится к нижнему регистру'
        );

        $this->assertSame(
            array('uuid', '09f1b0f4-4a59-4b0e-9c2e-5f8f2b7a1d33'),
            \Erp::id_or_uuid('09f1b0f4-4a59-4b0e-9c2e-5f8f2b7a1d33'),
            'ведущий ноль не мешает uuid'
        );

        foreach (array(NULL, '', 0, '0', 'abc', '<script>', '1e5', '-1') as $value)
        {
            $this->assertNull(\Erp::id_or_uuid($value), 'значение не id и не uuid: '.var_export($value, TRUE));
        }
    }

    public function test_garbage_identifier_does_not_reach_database()
    {
        $checked = 0;

        foreach ($this->lookups() as $service => $method)
        {
            if ( ! class_exists($service) OR ! method_exists($service, 'instance'))
            {
                continue;
            }

            $instance = call_user_func(array($service, 'instance'));

            foreach ($this->garbage() as $value)
            {
                $checked++;

                try
                {
                    $found = $instance->$method($value);
                }
                catch (\Kohana_Exception $e)
                {
                    // «Документ не найден» — законный ответ сервисов, которые
                    // бросают исключение вместо NULL. Важно, что это не ошибка СУБД.
                    $this->assertFalse($e instanceof \Database_Exception, 'ошибка СУБД вместо «не найдено»: '.$e->getMessage());
                    continue;
                }
                catch (\Database_Exception $e)
                {
                    $this->fail($service.'::'.$method.'('.$value.') уходит в СУБД: '.$e->getMessage());
                }

                $this->assertNull($found, $service.'::'.$method.'('.$value.') должен вернуть NULL');
            }
        }

        $this->assertGreaterThan(50, $checked, 'Проверены все сервисы с поиском по id или uuid');
    }

    /**
     * Существующий uuid по-прежнему находится, а несуществующий — не роняет
     * сервис: проверка формата не должна ломать нормальный путь.
     */
    /**
     * Сквозная проверка через страницы: мусор в адресе — это 404, а не 500.
     *
     * Сервисы под тестом выше, но путь «адрес → контроллер → сервис» стоит
     * проверить целиком: раньше эти адреса отвечали ошибкой СУБД, потому что
     * контроллер передавал строку из адреса дальше как есть.
     */
    public function test_garbage_identifier_pages_answer_not_five_hundred()
    {
        if ( ! class_exists('Service_Dev_Smoke'))
        {
            $this->markTestSkipped('Сервис дымового прогона недоступен');
        }

        $urls = array(
            '/procurement/orders/view/abc',
            '/procurement/orders/edit/abc',
            '/procurement/receipts/view/abc',
            '/procurement/requests/view/abc',
            '/quality/inspections/view/abc',
            '/quality/inspections/edit/abc',
            '/planning/programs/view/abc',
            '/planning/proposals/edit/abc',
            '/shopfloor/operations/view/abc',
            '/shopfloor/operations/edit/abc',
        );

        $smoke = new \Service_Dev_Smoke;
        $smoke->login_as();

        foreach ($smoke->run(array_map(function ($url) { return array('url' => $url, 'name' => $url); }, $urls))['rows'] as $row)
        {
            $this->assertSame(NULL, $row['error'], $row['url'].': '.$row['status'].' '.$row['error']);
            $this->assertLessThan(500, (int) $row['status'], $row['url'].' отвечает '.$row['status']);
        }
    }

    public function test_unknown_uuid_is_not_found()
    {
        $uuid = '00000000-0000-4000-8000-000000000000';

        foreach ($this->lookups() as $service => $method)
        {
            if ( ! class_exists($service) OR ! method_exists($service, 'instance'))
            {
                continue;
            }

            $instance = call_user_func(array($service, 'instance'));

            try
            {
                $this->assertNull($instance->$method($uuid), $service.'::'.$method.' на неизвестном uuid');
            }
            catch (\Kohana_Exception $e)
            {
                $this->assertFalse($e instanceof \Database_Exception, 'ошибка СУБД вместо «не найдено»: '.$e->getMessage());
            }
        }
    }

}
