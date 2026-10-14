<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Секции журнала: состояние и обслуживание (erp119_audit).
 *
 * Экран для администратора: видно, куда ложатся записи, сколько занимает
 * журнал, есть ли записи в DEFAULT-секции (значит, месяц не был создан)
 * и что будет удалено по сроку хранения.
 *
 * @package    Erp119
 * @category   Controllers
 */
class Controller_Erp119_Partitions extends Controller_Erp119_Main
{
    /**
     * Состояние секций.
     */
    public function action_index()
    {
        if ( ! $this->can('erp119_audit.manage'))
        {
            throw new HTTP_Exception_403('У вас нет прав на обслуживание журнала');
        }

        $service = Service_Erp119_Partitions::instance();
        $config  = Kohana::$config->load('erp119_audit');

        $this->set_title('Секции журнала');

        $this->template->content = View::factory('erp119/partitions/index')
            ->set('partitions', $service->inventory())
            ->set('backlog', $service->default_backlog())
            ->set('expired', $service->expired())
            ->set('retention_months', $config->get('storage.retention_months', NULL))
            ->set('months_ahead', $config->get('storage.months_ahead', 3))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Обслуживание: создать месяцы вперёд, разобрать DEFAULT, удалить истёкшие.
     */
    public function action_rotate()
    {
        if ( ! $this->can('erp119_audit.manage'))
        {
            throw new HTTP_Exception_403('У вас нет прав на обслуживание журнала');
        }

        $this->_respond(function () {
            $service = Service_Erp119_Partitions::instance();
            $action  = (string) $this->request->post('mode');

            $created = $service->ensure();

            // Сам факт обслуживания журнала — тоже событие аудита
            $context = array('mode' => $action, 'created' => $created, 'requested_by' => $this->_user_id());

            if ($action === 'attach')
            {
                $result = $service->attach_default();
                $journal_id = $this->record_maintenance('attach_default', $context + $result);

                return array(
                    'status'   => 'success',
                    'message'  => sprintf('Разобрано: перенесено %d записей в %d секций', $result['moved'], count($result['months'])),
                    'journal'  => $journal_id,
                    'redirect' => '/audit/partitions',
                );
            }

            if ($action === 'drop')
            {
                $dropped = $service->drop_expired();
                $this->record_maintenance('drop_expired', $context + array('dropped' => count($dropped)));

                return array(
                    'status'   => 'success',
                    'message'  => 'Удалено секций: '.count($dropped),
                    'redirect' => '/audit/partitions',
                );
            }

            $this->record_maintenance('ensure_partitions', $context);

            return array(
                'status'   => 'success',
                'message'  => $created ? 'Созданы секции: '.implode(', ', $created) : 'Все месячные секции уже существуют',
                'redirect' => '/audit/partitions',
            );
        });
    }

    /**
     * Записать обслуживание в журнал (действие администратора).
     *
     * @param   string  $action
     * @param   array   $context
     * @return  int|null
     */
    protected function record_maintenance($action, array $context)
    {
        $entry = Service_Erp119_Journal::instance()->record(array(
            'module'       => 'erp119',
            'entity_type'  => 'audit_log',
            'entity_id'    => $action,
            'entity_label' => 'Журнал действий',
            'action'       => 'maintenance',
            'severity'     => 'notice',
            'message'      => 'Обслуживание журнала: '.$action,
            'context'      => $context,
        ));

        return $entry ? $entry['entity_id'] : NULL;
    }
}
