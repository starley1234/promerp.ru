<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Ежедневная рассылка повестки дня менеджерам (erp221_crm).
 *
 *   bin/console erp221:daily_reminders              # всем, у кого есть задачи
 *   bin/console erp221:daily_reminders --user=12    # только одному сотруднику
 *   bin/console erp221:daily_reminders --dry-run    # показать, что было бы отправлено
 *
 * Регламент (каждое утро перед началом рабочего дня):
 *   0 8 * * * cd /srv/promerp && bin/console erp221:daily_reminders --quiet
 *
 * Текст уведомлений собирает сервис напоминаний, а доставку выполняет центр
 * уведомлений (erp217): модуль не заводит второй канал и не рассылает письма
 * сам. Если сотрудник не подписан на событие, для него ничего не уйдёт —
 * повестка остаётся в интерфейсе на странице «Напоминания».
 *
 * @package    Erp221
 * @category   Tasks
 */
class Task_Erp221_DailyReminders extends Minion_Task
{
    protected $_options = array(
        'user'    => NULL,
        'dry-run' => FALSE,
        'quiet'   => FALSE,
    );

    /**
     * Выполнить задачу.
     *
     * @param   array  $options
     * @return  void
     */
    protected function _execute(?array $options = NULL)
    {
        $options = (array) $options;

        $user_id = $this->_value($options, 'user');
        $dry_run = $this->_flag($options, 'dry-run');
        $quiet   = $this->_flag($options, 'quiet');

        $reminders = Service_Erp221_Reminders::instance();

        $summary = $reminders->run($user_id ? (int) $user_id : NULL, (bool) $dry_run);

        $this->out(sprintf(
            '%s: сотрудников %d, уведомлений %d',
            $dry_run ? 'К отправке (без изменений)' : 'Сводки разосланы',
            $summary['users'],
            $summary['sent']
        ), $quiet);
    }

    /**
     * Сообщение в консоль (в --quiet молчим, если всё хорошо).
     *
     * @param   string  $message
     * @param   boolean $quiet
     * @return  void
     */
    protected function out($message, $quiet)
    {
        if ( ! $quiet)
        {
            Minion_CLI::write($message);
        }
    }
}
