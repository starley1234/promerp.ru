<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты шины событий erp208 (Service_Erp208_EventBus).
 *
 * Проверяют то, что нельзя увидеть, глядя на интерфейс: подписчики, объявленные
 * в базе (`erp_208_event_subscribers`), действительно подключаются к шине, а
 * отключённые — не подключаются.
 *
 * Почему это отдельный тест. Загрузка подписчиков была обёрнута в `try/catch` с
 * записью в лог, поэтому ошибка `->where('is_active', '=', 1)` против колонки
 * BOOLEAN («operator does not exist: boolean = integer» на PostgreSQL) не
 * ломала ни один экран: она просто выключала подписчиков из базы. Из шести
 * записей демонстрационных данных подключалась одна. Такой класс дефектов
 * ловится только тестом, который смотрит на состояние шины, а не на страницу.
 *
 * Тест поднимает **свежий** экземпляр шины через рефлексию (синглтон мог быть
 * создан раньше, до появления тестовых подписчиков), публикует событие и
 * проверяет, кто его получил. Свои строки подписчиков и записанные события
 * тест убирает в tearDown.
 */
class Erp208EventBusTest extends TestCase
{
    /** @var string Префикс событий теста */
    const PREFIX = 'erp208.test.';

    /** @var array Названия событий, заведённых тестом */
    protected $events = array();

    /** @var array Записи, которые оставил колбэк (готовит сам колбэк) */
    public static $received = array();

    protected function setUp(): void
    {
        if ( ! class_exists('Service_Erp208_EventBus'))
        {
            $this->markTestSkipped('Модуль событий erp208 недоступен');
            return;
        }

        if ( ! $this->_has_table('erp_208_event_subscribers'))
        {
            $this->markTestSkipped('Нет таблицы подписчиков — выполните миграции erp208');
            return;
        }

        self::$received = array();

        // Подписчик, который обязан получить событие: активный, с callback-методом.
        // callback_method — статический метод этого же класса (он не тест: имени
        // с префиксом test_ у него нет).
        $this->_subscriber(self::PREFIX.'active', 'Tests\Unit\Erp208EventBusTest::probe', TRUE, FALSE);

        // Отключённый подписчик с тем же методом: событие он получать не должен.
        $this->_subscriber(self::PREFIX.'disabled', 'Tests\Unit\Erp208EventBusTest::probe', FALSE, FALSE);

        // Активный подписчик-уведомление: подключается как прокси, но НЕ как
        // callback (иначе «уведомление» вдруг вызвало бы метод).
        $this->_subscriber(self::PREFIX.'notification', NULL, TRUE, TRUE);

        // Активный подписчик, который ничего не делает: нужен, чтобы поймать
        // `'f'`-как-истину. Если флаг is_notification прочитать как строку,
        // запись превратится в прокси-слушателя и «появится» в шине.
        $this->_subscriber(self::PREFIX.'silent', NULL, TRUE, FALSE);
    }

    protected function tearDown(): void
    {
        if ( ! $this->_has_table('erp_208_event_subscribers'))
        {
            return;
        }

        \DB::delete('erp_208_event_subscribers')
            ->where('event_name', 'LIKE', self::PREFIX.'%')
            ->execute();

        if ($this->_has_table('erp_208_events'))
        {
            \DB::delete('erp_208_events')
                ->where('event_name', 'LIKE', self::PREFIX.'%')
                ->execute();
        }

        self::$received = array();

        // Синглтон шины мог запомнить тестовых слушателей — сбрасываем,
        // чтобы они не «выстрелили» в других тестах.
        $this->_reset_singleton();
    }

    /**
     * Колбэк-ловушка: пишет, какое событие до него дошло.
     */
    public static function probe($payload, $event_name = NULL, $initiator_id = NULL)
    {
        self::$received[] = array(
            'event'   => $event_name,
            'payload' => $payload,
        );
    }

    public function test_active_subscriber_from_db_receives_event()
    {
        $bus = $this->_fresh_bus();

        $this->assertTrue($bus->has_listeners(self::PREFIX.'active'),
            'активный подписчик из базы должен быть подключён к шине');

        $this->assertTrue($bus->publish(self::PREFIX.'active', array('number' => 'ТЕСТ-208'), 1));

        $this->assertCount(1, self::$received, 'колбэк подписчика должен вызваться ровно один раз');
        $this->assertSame(self::PREFIX.'active', self::$received[0]['event']);
        $this->assertSame('ТЕСТ-208', self::$received[0]['payload']['number']);
    }

    public function test_disabled_subscriber_does_not_receive_event()
    {
        $bus = $this->_fresh_bus();

        $this->assertFalse($bus->has_listeners(self::PREFIX.'disabled'),
            'отключённый подписчик не подключается к шине');
        $this->assertFalse($bus->publish(self::PREFIX.'disabled', array('number' => 'ТЕСТ-208'), 1));
        $this->assertSame(array(), self::$received, 'отключённый подписчик не получает событие');
    }

    public function test_notification_subscriber_is_registered_as_proxy()
    {
        $bus = $this->_fresh_bus();

        $this->assertTrue($bus->has_listeners(self::PREFIX.'notification'),
            'подписчик-уведомление подключается как прокси в центр уведомлений');

        $bus->publish(self::PREFIX.'notification', array('number' => 'ТЕСТ-208'), 1);

        $this->assertSame(array(), self::$received,
            'подписчик-уведомление не должен вызывать callback-метод');
    }

    public function test_boolean_flags_are_not_read_as_strings()
    {
        $bus = $this->_fresh_bus();

        // `is_notification` здесь `f` (false). Если прочитать его как строку,
        // запись станет прокси-слушателем и попадёт в шину — это и есть ошибка
        // класса «boolean из PostgreSQL» (AGENTS.md §6 п. 38).
        $this->assertFalse($bus->has_listeners(self::PREFIX.'silent'),
            'подписчик без уведомления и без метода не должен появляться в шине');
    }

    public function test_bus_loads_all_active_subscribers_from_db()
    {
        $bus = $this->_fresh_bus();

        $rows = \DB::select('event_name', 'callback_method', 'is_notification')
            ->from('erp_208_event_subscribers')
            ->where('is_active', '=', TRUE)
            ->execute()->as_array();

        $this->assertGreaterThan(0, count($rows), 'в базе должны быть активные подписчики');

        $connected = 0;

        foreach ($rows as $row)
        {
            $is_notification = \Erp::bool($row['is_notification']);
            $resolvable      = $this->_callback_resolves($row['callback_method']);

            if ( ! $is_notification && ! $resolvable)
            {
                // В демонстрационных данных есть подписчики на методы чужих
                // проектов (`Model_Notifications_Service::…`, `Model_CRM_Service::…`) —
                // таких классов в системе нет, и подписчик обязан просто
                // пропускаться, не ломая загрузку остальных.
                continue;
            }

            $this->assertTrue($bus->has_listeners($row['event_name']),
                'активный подписчик события '.$row['event_name'].' должен быть подключён');

            $connected++;
        }

        $this->assertGreaterThan(0, $connected, 'ни один подписчик из базы не подключился к шине');
    }

    /**
     * Превратится ли строка callback в вызываемое значение: та же логика, что в
     * `Service_Erp208_EventBus::_create_callable_from_string()`.
     */
    protected function _callback_resolves($callback)
    {
        if ( ! $callback)
        {
            return FALSE;
        }

        if (strpos($callback, '::') !== FALSE)
        {
            list($class, $method) = explode('::', $callback, 2);

            return class_exists($class) && method_exists($class, $method);
        }

        if (strpos($callback, '->') !== FALSE)
        {
            list($class) = explode('->', $callback, 2);

            return class_exists($class);
        }

        return function_exists($callback);
    }

    /**
     * Свежий экземпляр шины: синглтон мог быть создан до появления тестовых
     * подписчиков, поэтому сбрасываем его и создаём заново (конструктор сам
     * читает подписчиков из базы).
     *
     * @return \Service_Erp208_EventBus
     */
    protected function _fresh_bus()
    {
        $this->_reset_singleton();

        return \Service_Erp208_EventBus::instance();
    }

    protected function _reset_singleton()
    {
        $property = new \ReflectionProperty('Service_Erp208_EventBus', '_instance');
        $property->setAccessible(TRUE);
        $property->setValue(NULL, NULL);
    }

    /**
     * Заводит подписчика теста.
     */
    protected function _subscriber($event_name, $callback, $is_active, $is_notification)
    {
        $this->events[] = $event_name;

        \DB::insert('erp_208_event_subscribers', array(
            'event_name', 'module_id', 'callback_method', 'is_active', 'is_notification', 'description',
        ))->values(array(
            'event_name'      => $event_name,
            'module_id'       => 'erp208',
            'callback_method' => $callback,
            'is_active'       => $is_active,
            'is_notification' => $is_notification,
            'description'     => 'ТЕСТ-208: подписчик теста шины событий',
        ))->execute();
    }

    protected function _has_table($table)
    {
        try
        {
            return (bool) \DB::query(\Database::SELECT, "SELECT to_regclass('public.".$table."') IS NOT NULL AS ok")
                ->execute()->get('ok');
        }
        catch (\Exception $e)
        {
            return FALSE;
        }
    }
}
