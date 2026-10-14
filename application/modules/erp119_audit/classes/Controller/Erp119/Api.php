<?php defined('SYSPATH') or die('No direct script access.');

/**
 * JSON API журнала действий (erp119_audit).
 *
 * Нужен другим модулям и внешним системам: показать историю объекта в своей
 * карточке, отдать ленту за период, сообщить состояние секций. Права те же,
 * что и в интерфейсе — API не обходит ACL.
 *
 * @package    Erp119
 * @category   Controllers
 */
class Controller_Erp119_Api extends Controller_Erp119_Main
{
    /**
     * Поиск по журналу.
     */
    public function action_search()
    {
        return $this->_respond(function () {
            $this->_require('erp119_audit.view');

            $limit   = (int) $this->request->query('limit');
            $offset  = (int) $this->request->query('offset');
            $filters = $this->_filters();

            $data = Service_Erp119_Query::instance()->search(
                $filters,
                $limit > 0 ? $limit : 30,
                $offset > 0 ? $offset : 0
            );

            $journal = Service_Erp119_Journal::instance();

            foreach ($data['rows'] as $index => $row)
            {
                $data['rows'][$index]['entity_label_full'] = $journal->label_for($row['module'], $row['entity_type']);
            }

            return array(
                'status'  => 'ok',
                'total'   => $data['total'],
                'limit'   => $data['limit'],
                'offset'  => $data['offset'],
                'filters' => $filters,
                'rows'    => $data['rows'],
            );
        });
    }

    /**
     * Сводка: объёмы, важность, топы, активность по дням.
     */
    public function action_stats()
    {
        return $this->_respond(function () {
            $this->_require('erp119_audit.view');

            $days = (int) $this->request->query('days');

            if ($days < 1)
            {
                $days = (int) Kohana::$config->load('erp119_audit')->get('default_period_days', 30);
            }

            $query = Service_Erp119_Query::instance();

            return array(
                'status'   => 'ok',
                'stats'    => $query->stats($days),
                'activity' => $query->activity(14),
            );
        });
    }

    /**
     * История сущности: /audit/api/entity?entity_type=…&entity_id=…
     */
    public function action_entity()
    {
        return $this->_respond(function () {
            $this->_require('erp119_audit.view');

            $entity_type = trim((string) $this->request->query('entity_type'));
            $entity_id   = trim((string) $this->request->query('entity_id'));

            if ($entity_type === '' OR $entity_id === '')
            {
                throw new HTTP_Exception_400('Укажите entity_type и entity_id');
            }

            $limit = (int) $this->request->query('limit');

            $data = Service_Erp119_Query::instance()->timeline($entity_type, $entity_id, $limit > 0 ? $limit : 50, 0);

            return array(
                'status'      => 'ok',
                'entity_type' => $entity_type,
                'entity_id'   => $entity_id,
                'total'       => $data['total'],
                'rows'        => $data['rows'],
            );
        });
    }

    /**
     * Одна запись журнала.
     */
    public function action_view()
    {
        return $this->_respond(function () {
            $this->_require('erp119_audit.view');

            $id  = (int) $this->request->param('id');
            $row = Service_Erp119_Query::instance()->find($id);

            if ( ! $row)
            {
                throw new HTTP_Exception_404('Запись журнала не найдена');
            }

            return array('status' => 'ok', 'row' => $row);
        });
    }

    /**
     * Состояние секций журнала (мониторинг).
     */
    public function action_partitions()
    {
        return $this->_respond(function () {
            $this->_require('erp119_audit.manage');

            $service = Service_Erp119_Partitions::instance();

            return array(
                'status'     => 'ok',
                'partitions' => $service->inventory(),
                'backlog'    => $service->default_backlog(),
                'expired'    => $service->expired(),
            );
        });
    }

    /**
     * Справочники: сущности и настройки аудита.
     */
    public function action_settings()
    {
        return $this->_respond(function () {
            $this->_require('erp119_audit.view');

            $query = Service_Erp119_Query::instance();

            return array(
                'status'   => 'ok',
                'settings' => $query->settings(),
                'facets'   => $query->facets(),
            );
        });
    }

    /**
     * Право на действие; иначе — 403 в формате JSON.
     *
     * @param   string  $ability
     * @return  void
     */
    protected function _require($ability)
    {
        if ( ! $this->can($ability))
        {
            throw new HTTP_Exception_403('Недостаточно прав: '.$ability);
        }
    }
}
