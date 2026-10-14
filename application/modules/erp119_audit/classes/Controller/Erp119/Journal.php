<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Лента журнала действий (erp119_audit): поиск, карточка записи, выгрузка.
 *
 * @package    Erp119
 * @category   Controllers
 */
class Controller_Erp119_Journal extends Controller_Erp119_Main
{
    /**
     * Лента с фильтрами и постраничным выводом.
     */
    public function action_index()
    {
        if ( ! $this->can('erp119_audit.view'))
        {
            throw new HTTP_Exception_403('У вас нет прав на журнал действий');
        }

        $filters = $this->_filters();
        $page    = max(1, (int) $this->request->query('page'));
        $limit   = (int) Kohana::$config->load('erp119_audit')->get('items_per_page', 30);

        if ($limit < 1)
        {
            $limit = 30;
        }

        $query = Service_Erp119_Query::instance();
        $data  = $query->search($filters, $limit, ($page - 1) * $limit);

        $this->set_title('Лента действий');

        $this->template->content = View::factory('erp119/journal/list')
            ->set('rows', $data['rows'])
            ->set('total', $data['total'])
            ->set('page', $page)
            ->set('limit', $limit)
            ->set('pages', (int) ceil($data['total'] / $limit))
            ->set('filters', $filters)
            ->set('facets', $query->facets())
            ->set('journal', Service_Erp119_Journal::instance())
            ->set('can_export', (bool) $this->can('erp119_audit.export'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Карточка записи: значения до/после, контекст, другие записи того же запроса.
     */
    public function action_view()
    {
        if ( ! $this->can('erp119_audit.view'))
        {
            throw new HTTP_Exception_403('У вас нет прав на журнал действий');
        }

        $id  = (int) $this->request->param('id');
        $row = Service_Erp119_Query::instance()->find($id);

        if ( ! $row)
        {
            throw new HTTP_Exception_404('Запись журнала #:id не найдена', array(':id' => $id));
        }

        $related = $row['request_id']
            ? Service_Erp119_Query::instance()->by_request($row['request_id'])
            : array();

        $this->set_title('Запись журнала #'.$row['id']);

        $this->template->content = View::factory('erp119/journal/view')
            ->set('row', $row)
            ->set('related', array_filter($related, function ($item) use ($row) {
                return (int) $item['id'] !== (int) $row['id'];
            }))
            ->set('journal', Service_Erp119_Journal::instance())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * История одной сущности: /audit/journal/entity?entity_type=tech_card&entity_id=…
     */
    public function action_entity()
    {
        if ( ! $this->can('erp119_audit.view'))
        {
            throw new HTTP_Exception_403('У вас нет прав на журнал действий');
        }

        $entity_type = trim((string) $this->request->query('entity_type'));
        $entity_id   = trim((string) $this->request->query('entity_id'));

        if ($entity_type === '' OR $entity_id === '')
        {
            throw new HTTP_Exception_400('Укажите entity_type и entity_id');
        }

        $page  = max(1, (int) $this->request->query('page'));
        $limit = (int) Kohana::$config->load('erp119_audit')->get('items_per_page', 30);
        $data  = Service_Erp119_Query::instance()->timeline($entity_type, $entity_id, $limit, ($page - 1) * $limit);

        $this->set_title('История: '.$entity_type.' #'.$entity_id);

        $this->template->content = View::factory('erp119/entity/timeline')
            ->set('rows', $data['rows'])
            ->set('total', $data['total'])
            ->set('page', $page)
            ->set('limit', $limit)
            ->set('pages', (int) ceil($data['total'] / $limit))
            ->set('entity_type', $entity_type)
            ->set('entity_id', $entity_id)
            ->set('journal', Service_Erp119_Journal::instance())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Выгрузка среза журнала в CSV. Сама выгрузка тоже попадает в журнал.
     */
    public function action_export()
    {
        if ( ! $this->can('erp119_audit.export'))
        {
            throw new HTTP_Exception_403('У вас нет прав на выгрузку журнала');
        }

        $export = Service_Erp119_Export::instance();

        $this->auto_render = FALSE;

        $this->response
            ->headers('Content-Type', 'text/csv; charset=utf-8')
            ->headers('Content-Disposition', 'attachment; filename="'.$export->filename().'"')
            ->body($export->csv($this->_filters()));
    }
}
