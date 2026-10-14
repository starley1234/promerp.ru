<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Справочник складов (erp401_wms).
 *
 * @package    Erp401
 * @category   Controllers
 */
class Controller_Erp401_Warehouse extends Controller_Erp401_Main
{
    /**
     * Список складов.
     */
    public function action_index()
    {
        if ( ! $this->can('erp401_wms.manage_warehouses'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $warehouses = Service_Erp401_Stock::instance()->warehouses(FALSE);

        $this->template->content = View::factory('erp401/warehouse/index')
            ->set('warehouses', $warehouses)
            ->set('types', Model_Erp401_Warehouse::$types)
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Форма создания/редактирования склада.
     */
    public function action_edit()
    {
        if ( ! $this->can('erp401_wms.manage_warehouses'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id        = (int) $this->request->param('id');
        $warehouse = $id ? ORM::factory('Erp401_Warehouse', $id) : ORM::factory('Erp401_Warehouse');

        $this->template->content = View::factory('erp401/warehouse/form')
            ->set('warehouse', $warehouse)
            ->set('types', Model_Erp401_Warehouse::$types)
            ->set('errors', array())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Сохранение склада (POST).
     */
    public function action_save()
    {
        if ( ! $this->can('erp401_wms.manage_warehouses'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/warehouse/warehouses');
        }

        $post = $this->request->post();
        $id   = (int) Arr::get($post, 'id');

        try
        {
            $stock = Service_Erp401_Stock::instance();

            if ($id)
            {
                $warehouse = $stock->update_warehouse($id, $post);
                $this->_flash('success', 'Склад «'.$warehouse->name.'» обновлён');
            }
            else
            {
                $warehouse = $stock->create_warehouse($post);
                $this->_flash('success', 'Склад «'.$warehouse->name.'» создан');
            }

            $this->redirect('/warehouse/warehouses');
        }
        catch (ORM_Validation_Exception $e)
        {
            $warehouse = $id ? ORM::factory('Erp401_Warehouse', $id) : ORM::factory('Erp401_Warehouse');
            $warehouse->values($post, array('code', 'name', 'type', 'object_id', 'address', 'description'));

            $this->template->content = View::factory('erp401/warehouse/form')
                ->set('warehouse', $warehouse)
                ->set('types', Model_Erp401_Warehouse::$types)
                ->set('errors', $e->errors('models'))
                ->set('messages', array());
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::ERROR, 'erp401: ошибка сохранения склада — :error',
                array(':error' => $e->getMessage()));

            $this->_flash('error', 'Не удалось сохранить склад. Подробности в журнале.');
            $this->redirect('/warehouse/warehouses');
        }
    }

    /**
     * Закрыть склад (деактивировать).
     */
    public function action_deactivate()
    {
        if ( ! $this->can('erp401_wms.manage_warehouses'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id = (int) $this->request->param('id');

        try
        {
            $warehouse = Service_Erp401_Stock::instance()->deactivate_warehouse($id);
            $this->_flash('success', 'Склад «'.$warehouse->name.'» закрыт');
        }
        catch (Exception_Erp401_Stock $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->redirect('/warehouse/warehouses');
    }
}
