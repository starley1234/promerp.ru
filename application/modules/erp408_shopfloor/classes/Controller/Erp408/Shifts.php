<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Смены и рабочий календарь (erp408_shopfloor).
 *
 * На странице две части: шаблоны смен (первая, вторая, ночная) и календарь на
 * месяц — какие дни рабочие и сколько часов доступно центру. Календарь можно
 * заполнить одной кнопкой (будни рабочие, выходные — нет), а потом поправить
 * отдельные дни: ремонт, сокращённый день, сверхурочные.
 *
 * Планировщик берёт часы отсюда, поэтому «нерабочий день» — не украшение, а
 * работающее правило: маршрут заказа сдвигается на ближайший рабочий день.
 *
 * @package    Erp408
 * @category   Controllers
 */
class Controller_Erp408_Shifts extends Controller_Erp408_Main
{
    /**
     * GET /shopfloor/shifts?month=YYYY-MM&center_id=
     */
    public function action_index()
    {
        if ( ! $this->can('erp408.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $month     = $this->month($this->_query('month'));
        $center_id = (int) $this->_query('center_id', 0);

        $from = $month.'-01';
        $to   = date('Y-m-t', strtotime($from));

        $this->template->content = View::factory('erp408/shifts/index')
            ->set('shifts', $this->_shifts()->search(array(), 50, 0))
            ->set('calendar', $this->_calendar_grid($from, $to, $center_id ? $center_id : NULL))
            ->set('month', $month)
            ->set('month_label', $this->month_label($month))
            ->set('from', $from)
            ->set('to', $to)
            ->set('center_id', $center_id)
            ->set('centers', $this->_centers()->active())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * POST /shopfloor/shifts/save — создать или изменить смену.
     */
    public function action_save()
    {
        if ( ! $this->can('erp408.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $post = $this->request->post();
        $id   = isset($post['id']) ? (int) $post['id'] : 0;

        try
        {
            if ($id)
            {
                $this->_shifts()->update($id, $post, $this->_current_user_id());
                $this->_flash('success', 'Смена обновлена');
            }
            else
            {
                $this->_shifts()->create($post, $this->_current_user_id());
                $this->_flash('success', 'Смена создана');
            }
        }
        catch (Exception_Erp408_Shopfloor $e)
        {
            $this->_flash('error', $e->getMessage());
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::ERROR, 'erp408: смена не сохранена — :error', array(':error' => $e->getMessage()));
            $this->_flash('error', 'Не удалось сохранить смену. Подробности в журнале.');
        }

        $this->redirect('/shopfloor/shifts');
    }

    /**
     * POST /shopfloor/shifts/toggle/<id>
     */
    public function action_toggle()
    {
        if ( ! $this->can('erp408.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id    = $this->request->param('id');
        $shift = $this->_shifts()->get($id);

        if ( ! $shift)
        {
            $this->_flash('error', 'Смена не найдена');
            $this->redirect('/shopfloor/shifts');
            return;
        }

        try
        {
            $updated = $this->_shifts()->update($id, array('is_active' => ! $shift['is_active']), $this->_current_user_id());

            $this->_flash('success', $updated['is_active'] ? 'Смена включена' : 'Смена выключена');
        }
        catch (Exception_Erp408_Shopfloor $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->redirect('/shopfloor/shifts');
    }

    /**
     * POST /shopfloor/shifts/delete/<id>
     */
    public function action_delete()
    {
        if ( ! $this->can('erp408.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        try
        {
            $this->_shifts()->delete($this->request->param('id'));
            $this->_flash('success', 'Смена удалена');
        }
        catch (Exception_Erp408_Shopfloor $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->redirect('/shopfloor/shifts');
    }

    /**
     * POST /shopfloor/shifts/day — отметить день в календаре.
     */
    public function action_day()
    {
        if ( ! $this->can('erp408.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $post = $this->request->post();

        try
        {
            $result = $this->_shifts()->set_day(
                isset($post['day']) ? $post['day'] : date('Y-m-d'),
                isset($post['work_center_id']) ? (int) $post['work_center_id'] : NULL,
                isset($post['shift_id']) ? (int) $post['shift_id'] : NULL,
                Erp::bool(isset($post['is_working']) ? $post['is_working'] : TRUE),
                isset($post['hours']) ? $post['hours'] : NULL,
                isset($post['note']) ? $post['note'] : NULL
            );

            $this->_flash('success', 'Календарь обновлён: '.$result['day']);
        }
        catch (Exception_Erp408_Shopfloor $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->redirect('/shopfloor/shifts?month='.substr(Erp::day(isset($post['day']) ? $post['day'] : NULL), 0, 7)
            .( ! empty($post['work_center_id']) ? '&center_id='.(int) $post['work_center_id'] : ''));
    }

    /**
     * POST /shopfloor/shifts/generate — заполнить календарь на месяц.
     */
    public function action_generate()
    {
        if ( ! $this->can('erp408.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $month  = $this->month($this->request->post('month'));
        $from   = $month.'-01';
        $to     = date('Y-m-t', strtotime($from));
        $center = (int) $this->request->post('center_id');

        try
        {
            $days = $this->_shifts()->generate($from, $to, $center ? array($center) : array(), array());

            $this->_flash('success', 'Календарь заполнен на '.$this->month_label($month).': дней '.$days);
        }
        catch (Exception_Erp408_Shopfloor $e)
        {
            $this->_flash('error', $e->getMessage());
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::ERROR, 'erp408: календарь не заполнен — :error', array(':error' => $e->getMessage()));
            $this->_flash('error', 'Не удалось заполнить календарь. Подробности в журнале.');
        }

        $this->redirect('/shopfloor/shifts?month='.$month.($center ? '&center_id='.$center : ''));
    }

    /**
     * Календарь в виде сетки: дни месяца с записями по центрам и сменам.
     *
     * @param   string      $from
     * @param   string      $to
     * @param   int|null    $center_id
     * @return  array       days (день => записи), hours_total, working_days
     */
    protected function _calendar_grid($from, $to, $center_id = NULL)
    {
        $rows = $this->_shifts()->calendar($from, $to, $center_id);

        $days         = array();
        $hours_total  = 0.0;
        $working_days = 0;

        foreach ($rows as $row)
        {
            $day = (string) $row['day'];

            if ( ! isset($days[$day]))
            {
                $days[$day] = array('day' => $day, 'entries' => array(), 'hours' => 0.0, 'is_working' => FALSE);
            }

            $days[$day]['entries'][] = $row;

            if ($row['is_working'])
            {
                $days[$day]['is_working'] = TRUE;
                $days[$day]['hours']     += (float) $row['hours'];
                $hours_total             += (float) $row['hours'];
            }
        }

        foreach ($days as $day => $info)
        {
            if ($info['is_working'])
            {
                $working_days++;
            }
        }

        return array(
            'days'         => $days,
            'hours_total'  => round($hours_total, 2),
            'working_days' => $working_days,
        );
    }

    /**
     * @param   string|null $month
     * @return  string  ГГГГ-ММ
     */
    protected function month($month = NULL)
    {
        $month = trim((string) $month);

        if ( ! preg_match('/^\d{4}-\d{2}$/', $month))
        {
            return date('Y-m');
        }

        return $month;
    }

    /**
     * @param   string  $month
     * @return  string
     */
    protected function month_label($month)
    {
        $names = array(1 => 'январь', 'февраль', 'март', 'апрель', 'май', 'июнь',
            'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь');

        $parts = explode('-', $month);

        return (isset($names[(int) $parts[1]]) ? $names[(int) $parts[1]] : $month).' '.$parts[0];
    }
}
