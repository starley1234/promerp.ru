<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Виджеты журнала действий (erp119_audit).
 *
 * Встраиваются в карточки других модулей:
 *   /audit/widget/recent?limit=8                          — последние действия
 *   /audit/widget/timeline?entity_type=tech_card&entity_id=3  — история объекта
 *
 * @package    Erp119
 * @category   Controllers
 */
class Controller_Erp119_Widget extends Controller_Erp119_Main
{
    /**
     * Последние действия в системе.
     */
    public function action_recent()
    {
        if ( ! $this->can('erp119_audit.view'))
        {
            throw new HTTP_Exception_403('У вас нет прав на журнал действий');
        }

        $limit = (int) $this->request->query('limit');
        $limit = ($limit > 0 AND $limit <= 50) ? $limit : (int) Kohana::$config->load('erp119_audit')->get('recent_limit', 8);

        $this->template->content = View::factory('erp119/widgets/recent')
            ->set('rows', Service_Erp119_Query::instance()->search(array(), $limit, 0)['rows'])
            ->set('journal', Service_Erp119_Journal::instance());
    }

    /**
     * История одной сущности — для вкладки «История» в карточке.
     */
    public function action_timeline()
    {
        if ( ! $this->can('erp119_audit.view'))
        {
            throw new HTTP_Exception_403('У вас нет прав на журнал действий');
        }

        $entity_type = trim((string) $this->request->query('entity_type'));
        $entity_id   = trim((string) $this->request->query('entity_id'));
        $limit       = (int) $this->request->query('limit');

        if ($entity_type === '' OR $entity_id === '')
        {
            throw new HTTP_Exception_400('Укажите entity_type и entity_id');
        }

        $data = Service_Erp119_Query::instance()->timeline($entity_type, $entity_id, $limit > 0 ? $limit : 10, 0);

        $this->template->content = View::factory('erp119/widgets/timeline')
            ->set('rows', $data['rows'])
            ->set('total', $data['total'])
            ->set('entity_type', $entity_type)
            ->set('entity_id', $entity_id)
            ->set('journal', Service_Erp119_Journal::instance());
    }
}
