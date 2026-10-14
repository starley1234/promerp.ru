<?php defined('SYSPATH') or die('No direct script access.');

/**
 * JSON API демонстрационного стенда (erp811_demo): `/demo/api/<action>`.
 *
 * Отдаёт то, что нужно автономной демонстрации и роботам-проверкам:
 * состояние стенда, сценарий, маршрут осмотра, состав данных.
 *
 * Права: стенд открыт (это демонстрация), но действий, меняющих данные, здесь
 * нет вовсе — вход и выход живут в контроллере страницы и работают только
 * POST-ом. Поэтому API стенда читающий по построению: даже если посетитель
 * получит доступ к этому адресу, испортить сценарий он не сможет.
 *
 * @package    Erp811
 * @category   Controllers
 */
class Controller_Erp811_Api extends Controller_Base
{
    /** @var Service_Erp811_Demo|null */
    protected $_demo;

    /** @var Service_Erp811_Scenario|null */
    protected $_scenario;

    /**
     * Стенд открыт: демонстрацию показывают без входа. Права проверяются на
     * уровне сборки (модуль включён / база собрана), а не роли гостя.
     */
    protected $public_actions = array('status', 'scenario', 'tour', 'counts', 'runs');

    public function before()
    {
        parent::before();

        $this->_demo     = Service_Erp811_Demo::instance();
        $this->_scenario = Service_Erp811_Scenario::instance();
    }

    // =========================================================================
    //  Чтение
    // =========================================================================

    /**
     * Состояние стенда: включён, собрана ли база, когда и что собрано.
     */
    public function action_status()
    {
        $status = $this->_demo->enabled() ? $this->_demo->status() : array(
            'enabled'   => FALSE,
            'available' => FALSE,
            'error'     => 'Модуль демонстрационного стенда выключен',
        );

        $this->_json_ok(array(
            'enabled'   => $this->_demo->enabled(),
            'inside'    => $this->_demo->active(),
            'database'  => $this->_demo->database_name(),
            'scenario'  => $this->_scenario->code(),
            'status'    => $status,
        ));
    }

    /**
     * Сценарий: что за производство, что выпускает, чем работает.
     */
    public function action_scenario()
    {
        $this->_json_ok(array(
            'code'      => $this->_scenario->code(),
            'company'   => $this->_scenario->company(),
            'landing'   => $this->_scenario->landing(),
            'headline'  => $this->_scenario->headline(),
            'clients'   => array_slice($this->_scenario->clients(), 0, 10),
            'suppliers' => array_slice($this->_scenario->suppliers(), 0, 10),
            'materials' => array_map(function ($row) {
                return array('code' => $row['code'], 'name' => $row['name'], 'polymer' => $row['polymer']);
            }, $this->_scenario->materials()),
            'parts'     => $this->_scenario->parts(),
            'centers'   => $this->_scenario->centers(),
            'shifts'    => $this->_scenario->shifts(),
            'history_days' => $this->_scenario->history_days(),
            'orders'    => $this->_scenario->order_count(),
        ));
    }

    /**
     * Тур: маршрут осмотра системы для гостя.
     */
    public function action_tour()
    {
        $rows = array();

        foreach ($this->_scenario->tour() as $index => $step)
        {
            $rows[] = array(
                'no'      => isset($step['step']) ? (int) $step['step'] : $index + 1,
                'title'   => isset($step['title']) ? $step['title'] : 'Шаг '.($index + 1),
                'url'     => isset($step['url']) ? $step['url'] : '/',
                'what'    => isset($step['what']) ? $step['what'] : '',
                'ask'     => isset($step['ask']) ? $step['ask'] : '',
            );
        }

        $this->_json_ok(array('steps' => $rows, 'total' => count($rows)));
    }

    /**
     * Состав данных стенда: сколько собрано на прошлой сборке.
     */
    public function action_counts()
    {
        $status = $this->_demo->status();

        if (empty($status['available']))
        {
            $this->_json_fail('База стенда ещё не собрана: bin/console demo --build', 'not_built', 409);
            return;
        }

        $this->_json_ok(array(
            'built_at' => isset($status['built_at']) ? $status['built_at'] : NULL,
            'scenario' => isset($status['scenario']) ? $status['scenario'] : $this->_scenario->code(),
            'counts'   => isset($status['counts']) ? (array) $status['counts'] : array(),
            'problems' => isset($status['problems']) ? (array) $status['problems'] : array(),
        ));
    }

    /**
     * Журнал сборок стенда: что и когда собирали.
     */
    public function action_runs()
    {
        $this->_json_ok(array('runs' => $this->_demo->runs(10)));
    }
}
