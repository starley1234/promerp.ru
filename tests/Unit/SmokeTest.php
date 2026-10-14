<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Дымовой прогон маршрутов внутри процесса (Service_Dev_Smoke).
 *
 * Проверяет, что страницы действительно отвечают: контроллеры, модели и
 * представления не падают. 35 маршрутов выполняются за ~0,1 с, внешний
 * HTTP-сервер не нужен — поэтому тест годится для быстрой обратной связи.
 *
 * 302/400/403/404 — допустимые ответы (нужна авторизация, нет параметров, страницы нет).
 * Проблемой считается 500 и необработанное исключение.
 */
class SmokeTest extends TestCase
{
    /** @var array */
    protected $result;

    protected function setUp(): void
    {
        try
        {
            \DB::query(\Database::SELECT, 'SELECT 1')->execute();
        }
        catch (\Exception $e)
        {
            $this->markTestSkipped('Нет подключения к БД: '.$e->getMessage());
        }

        $smoke = new \Service_Dev_Smoke;

        // Администратор из БД (если есть) — чтобы проверялись и закрытые страницы
        $smoke->login_as();

        // + действия чтения JSON API: ровно они и падали незамеченными, пока
        // прогон ходил только по маршрутам (B16)
        $this->result = $smoke->run(array_merge($smoke->urls(), $smoke->action_urls(), $smoke->api_urls()));
    }

    public function test_all_routes_respond_without_errors()
    {
        $broken = [];

        foreach ($this->result['rows'] as $row)
        {
            if ($row['error'] !== NULL)
            {
                $broken[] = $row['url'].' → '.$row['status'].' '.$row['error'];
            }
        }

        $this->assertSame([], $broken,
            "Маршруты с ошибками (".count($broken)." из ".count($this->result['rows'])."):\n".implode("\n", $broken));
    }

    public function test_smoke_run_is_fast()
    {
        $this->assertLessThan(30.0, $this->result['stats']['time'],
            'Прогон маршрутов слишком долгий: '.$this->result['stats']['time'].' с');
    }

    public function test_routes_are_discovered()
    {
        $this->assertGreaterThan(50, $this->result['stats']['total'],
            'Найдено слишком мало адресов — вероятно, init.php модулей не выполняется');
    }

    /**
     * JSON API — такая же точка входа, как страницы: у каждого модуля она есть,
     * а значит должна проверяться прогоном, а не обнаруживаться агентом.
     */
    public function test_api_actions_are_covered()
    {
        $smoke = new \Service_Dev_Smoke;
        $api   = $smoke->api_urls();

        $this->assertGreaterThan(100, count($api),
            'Действия чтения JSON API не попали в прогон: проверено '.count($api));
    }

    /**
     * Регрессия: если вход под администратором молча падает, дымовой прогон
     * продолжает работать — но анонимно, и «403» вместо реальных страниц
     * выглядит нормальным ответом. Поэтому вход проверяем явно.
     */
    public function test_admin_is_logged_in_for_smoke_run()
    {
        $admin = \DB::select(['ru.user_id', 'user_id'])
            ->from(['roles_users', 'ru'])
            ->join(['roles', 'r'], 'INNER')
            ->on('r.id', '=', 'ru.role_id')
            ->where('r.name', '=', 'admin')
            ->limit(1)
            ->execute()
            ->get('user_id');

        if ($admin === NULL)
        {
            $this->markTestSkipped('В базе нет администратора — прогон идёт анонимно');
        }

        $smoke = new \Service_Dev_Smoke;

        $this->assertNotNull($smoke->login_as(),
            'Service_Dev_Smoke::login_as() не выполнил вход — дымовой прогон проверяет страницы анонимно');
    }
}
