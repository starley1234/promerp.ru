<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Терминал цеха (erp408_shopfloor) — рабочее место мастера и оператора.
 *
 * Страница рассчитана на планшет у станка: выбирается рабочий центр, дальше
 * показываются задания на день (и просроченные) с крупными кнопками «Начать»,
 * «Пауза», «Готово». Поиск по коду принимает номер производственного заказа,
 * номер операции или идентификатор — так оператор находит работу, не листая
 * списки.
 *
 * Отметки уходят в `erp_408_worklogs` (кто и когда нажал кнопку) и меняют
 * статус операции; каждое нажатие — отдельная запись, поэтому по журналу
 * видно и время работы, и простои.
 *
 * @package    Erp408
 * @category   Controllers
 */
class Controller_Erp408_Terminal extends Controller_Erp408_Main
{
    /**
     * GET /shopfloor/terminal?center_id=&day=
     */
    public function action_index()
    {
        if ( ! $this->can('erp408.terminal'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $center_id = (int) $this->_query('center_id', 0);
        $day       = Erp::day($this->_query('day'));

        $this->template->content = View::factory('erp408/terminal/index')
            ->set('tasks', $this->_operations()->terminal_tasks($center_id ? $center_id : NULL, $day))
            ->set('centers', $this->_centers()->active())
            ->set('center_id', $center_id)
            ->set('day', $day)
            ->set('shift', $this->_shifts()->current())
            ->set('downtime_reasons', Model_Erp408_Operation::$downtime_reasons)
            ->set('messages', $this->_flash_messages());
    }

    /**
     * GET /shopfloor/terminal/find?code= — поиск задания по коду с места.
     *
     * Принимает номер заказа («ПО-2026-000001»), номер операции («10»),
     * номер-операцию («ПО-2026-000001/30») или идентификатор операции.
     */
    public function action_find()
    {
        if ( ! $this->can('erp408.terminal'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $code = trim((string) $this->_query('code', ''));
        $found = $this->locate($code);

        if ($found)
        {
            $this->redirect('/shopfloor/operations/view/'.$found['id'].'?from=terminal');
            return;
        }

        $this->_flash('warning', $code === ''
            ? 'Введите код с задания'
            : 'По коду «'.$code.'» открытых операций не найдено');

        $this->redirect('/shopfloor/terminal?center_id='.(int) $this->_query('center_id', 0));
    }

    /**
     * Найти открытую операцию по коду: идентификатор, номер-операция или
     * номер заказа + номер операции.
     *
     * @param   string      $code
     * @return  array|null
     */
    protected function locate($code)
    {
        $code = trim($code);

        if ($code === '')
        {
            return NULL;
        }

        // Идентификатор или uuid операции
        $operation = $this->_operations()->get($code);

        if ($operation AND $operation['is_open'])
        {
            return $operation;
        }

        // «НОМЕР/ОП» — номер заказа и номер операции
        if (strpos($code, '/') !== FALSE)
        {
            list($number, $op_no) = array_map('trim', explode('/', $code, 2));

            $row = DB::select()
                ->from('erp_408_operations')
                ->where('order_number', '=', $number)
                ->where('op_no', '=', (int) $op_no)
                ->where('status', 'IN', array('planned', 'in_progress', 'paused'))
                ->order_by('id', 'ASC')
                ->limit(1)
                ->execute()
                ->current();

            if ($row)
            {
                return $this->_operations()->prepare($row);
            }
        }

        // Номер заказа: показываем первую незакрытую операцию
        $row = DB::select()
            ->from('erp_408_operations')
            ->where('order_number', '=', $code)
            ->where('status', 'IN', array('planned', 'in_progress', 'paused'))
            ->order_by('op_no', 'ASC')
            ->limit(1)
            ->execute()
            ->current();

        return $row ? $this->_operations()->prepare($row) : NULL;
    }
}
