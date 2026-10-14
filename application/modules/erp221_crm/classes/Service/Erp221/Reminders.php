<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Напоминания CRM (erp221_crm): что менеджер забыл сделать.
 *
 * Модуль ничего не «додумывает»: он показывает факты из базы — задачи со
 * сроком, просроченные сделки, сделки без движения и близкие сроки решения.
 * Отправка в почту или мессенджер идёт через центр уведомлений (erp217), но
 * только если у сотрудника есть подписка; иначе список просто виден в
 * интерфейсе. Ошибка доставки не должна ломать продажу — она логируется.
 *
 * @package    Erp221
 * @category   Services
 */
class Service_Erp221_Reminders
{
    /** @var Service_Erp221_Reminders|null */
    protected static $_instance;

    /**
     * @return  Service_Erp221_Reminders
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Сводка дня для сотрудника: задачи, просрочка и застой.
     *
     * @param   integer $user_id  NULL — по всем сотрудникам (для руководителя)
     * @param   integer $limit
     * @return  array
     */
    public function agenda($user_id = NULL, $limit = 50)
    {
        return array(
            'user_id'       => $user_id === NULL ? NULL : (int) $user_id,
            'tasks'         => Service_Erp221_Timeline::instance()->tasks($user_id, array()),
            'overdue_tasks' => Service_Erp221_Timeline::instance()->tasks($user_id, array('overdue' => TRUE)),
            'overdue_deals' => Service_Erp221_Deals::instance()->search(array('overdue' => TRUE, 'assigned_to' => $user_id), $limit, 0)['rows'],
            'stale_deals'   => $this->stagnant_deals($user_id, NULL, $limit),
            'deadlines'     => $this->upcoming_deadlines($user_id, 7, $limit),
        );
    }

    /**
     * Незакрытые задачи, срок которых уже близко.
     *
     * @param   integer $minutes  окно вперёд (по умолчанию — из конфига)
     * @param   integer $user_id
     * @return  array
     */
    public function tasks_due($minutes = NULL, $user_id = NULL)
    {
        if ($minutes === NULL)
        {
            $minutes = (int) Kohana::$config->load('erp221_crm.reminders.task_before_minutes');
        }

        $limit = date('Y-m-d H:i:s', time() + max(1, (int) $minutes) * 60);

        return Service_Erp221_Timeline::instance()->tasks($user_id, array('due_before' => $limit));
    }

    /**
     * Сделки в работе без записей в ленте дольше N дней.
     *
     * @param   integer $user_id
     * @param   integer $days      NULL — из конфига модуля
     * @param   integer $limit
     * @return  array
     */
    public function stagnant_deals($user_id = NULL, $days = NULL, $limit = 50)
    {
        if ($days === NULL)
        {
            $days = (int) Kohana::$config->load('erp221_crm.stagnation.days_limit');
        }

        $days = max(1, (int) $days);

        $page = Service_Erp221_Deals::instance()->search(array(
            'stale'       => $days,
            'assigned_to' => $user_id,
        ), $limit, 0);

        return $page['rows'];
    }

    /**
     * Сделки в работе, срок решения по которым наступит в ближайшие дни.
     *
     * @param   integer $user_id
     * @param   integer $days
     * @param   integer $limit
     * @return  array
     */
    public function upcoming_deadlines($user_id = NULL, $days = 7, $limit = 50)
    {
        $page = Service_Erp221_Deals::instance()->search(array(
            'kind'          => 'open',
            'deadline_from' => date('Y-m-d'),
            'deadline_to'   => date('Y-m-d', strtotime('+'.max(1, (int) $days).' days')),
            'assigned_to'   => $user_id,
        ), $limit, 0);

        return $page['rows'];
    }

    /**
     * Отправить сводку сотруднику через центр уведомлений (erp217).
     *
     * Возвращает число принятых уведомлений: пустой список означает, что
     * подписок на события CRM у сотрудника нет — это нормальная ситуация,
     * а не ошибка.
     *
     * @param   integer $user_id
     * @param   array   $agenda
     * @return  integer
     */
    public function notify_user($user_id, array $agenda)
    {
        if ( ! class_exists('Service_Erp217_Bus') OR empty($agenda['tasks']) AND empty($agenda['overdue_deals']))
        {
            return 0;
        }

        $sent = 0;

        try
        {
            $bus = Service_Erp217_Bus::instance();

            foreach ($agenda['tasks'] as $task)
            {
                $result = $bus->send('erp221.task.due', (int) $user_id, array(
                    'subject'   => $task['subject'] ? $task['subject'] : 'Задача по сделке',
                    'content'   => $task['content'],
                    'deal'      => $task['deal_number'],
                    'scheduled' => $task['scheduled_at'],
                ), 'erp221_deal', $task['deal_id']);

                $sent += is_array($result) ? count($result) : 0;
            }

            foreach ($agenda['overdue_deals'] as $deal)
            {
                $result = $bus->send('erp221.deal.overdue', (int) $user_id, array(
                    'number' => $deal['number'],
                    'title'  => $deal['title'],
                    'client' => $deal['client']['name'],
                    'amount' => number_format($deal['amount'], 2, ',', ' '),
                    'due'    => $deal['deadline'],
                ), 'erp221_deal', $deal['id']);

                $sent += is_array($result) ? count($result) : 0;
            }
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp221: сводка не отправлена пользователю :id — :error', array(
                ':id'    => (int) $user_id,
                ':error' => $e->getMessage(),
            ));
        }

        return $sent;
    }

    /**
     * Разослать сводки: конкретному сотруднику или всем, у кого есть задачи.
     *
     * @param   integer $user_id
     * @param   boolean $dry_run  только показать, кому ушла бы сводка
     * @return  array   ['users' => int, 'sent' => int]
     */
    public function run($user_id = NULL, $dry_run = FALSE)
    {
        $user_ids = array();

        if ($user_id)
        {
            $user_ids[] = (int) $user_id;
        }
        else
        {
            foreach (DB::select('assigned_to')->from('erp_221_activities')
                ->where('status', '=', 'pending')
                ->where('assigned_to', 'IS NOT', NULL)
                ->group_by('assigned_to')
                ->execute()->as_array() as $row)
            {
                $user_ids[] = (int) $row['assigned_to'];
            }

            foreach (DB::select('assigned_to')->from('erp_221_deals')
                ->where('is_deleted', '=', FALSE)
                ->where('assigned_to', 'IS NOT', NULL)
                ->group_by('assigned_to')
                ->execute()->as_array() as $row)
            {
                $user_ids[] = (int) $row['assigned_to'];
            }
        }

        $user_ids = array_values(array_unique($user_ids));

        $sent = 0;

        foreach ($user_ids as $id)
        {
            if ($dry_run)
            {
                continue;
            }

            $sent += $this->notify_user($id, $this->agenda($id));
        }

        return array('users' => count($user_ids), 'sent' => $sent);
    }

    /**
     * Просроченные задачи: их видно в интерфейсе, даже если уведомления выключены.
     *
     * @param   integer $user_id
     * @return  array
     */
    public function overdue($user_id = NULL)
    {
        return array(
            'tasks' => Service_Erp221_Timeline::instance()->tasks($user_id, array('overdue' => TRUE)),
            'deals' => Service_Erp221_Deals::instance()->search(array('overdue' => TRUE, 'assigned_to' => $user_id), 50, 0)['rows'],
        );
    }
}
