<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроли качества (erp405_quality): список, карточка, дефекты, решения.
 *
 * Экраны показывают тот же путь, что и сервис: завели контроль → записали
 * дефекты → изолировали партию → приняли решение → закрыли. Права проверяются
 * по шагам: смотреть — `erp405.view`, заводить и записывать дефекты —
 * `erp405.inspect`, решать судьбу брака (карантин, возврат, утиль) —
 * `erp405.decide`.
 *
 * @package    Erp405
 * @category   Controllers
 */
class Controller_Erp405_Inspection extends Controller_Erp405_Main
{
    /**
     * Список контролей с фильтрами.
     */
    public function action_index()
    {
        if ( ! $this->can('erp405.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $filters = array(
            'kind'    => $this->_query('kind'),
            'status'  => $this->_query('status'),
            'result'  => $this->_query('result'),
            'source'  => $this->_query('source'),
            'term'    => $this->_query('term'),
            'pending' => ($this->_query('pending') !== NULL AND $this->_query('pending') !== ''),
        );

        $page   = max(1, (int) $this->_query('page', 1));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $this->template->content = View::factory('erp405/inspection/index')
            ->set('rows', $this->_inspections()->search($filters, $limit, $offset))
            ->set('total', $this->_inspections()->count($filters))
            ->set('filters', $filters)
            ->set('page', $page)
            ->set('limit', $limit)
            ->set('can_inspect', $this->can('erp405.inspect'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Карточка контроля: партия, дефекты, решения, движения склада.
     */
    public function action_view()
    {
        if ( ! $this->can('erp405.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $inspection = $this->_inspections()->get($this->request->param('id'));

        if ( ! $inspection)
        {
            throw new HTTP_Exception_404('Контроль качества не найден');
        }

        $this->template->content = View::factory('erp405/inspection/view')
            ->set('inspection', $inspection)
            ->set('defects', $this->_inspections()->defects($inspection->id))
            ->set('decisions', $this->_inspections()->decisions($inspection->id))
            ->set('codes', $this->_codes()->options())
            ->set('warehouses', $this->_warehouses())
            ->set('quarantine_warehouses', $this->_inspections()->quarantine_warehouses())
            ->set('scrap_warehouses', $this->_inspections()->scrap_warehouses())
            ->set('can_inspect', $this->can('erp405.inspect'))
            ->set('can_decide', $this->can('erp405.decide'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Форма контроля: завести новый или поправить шапку существующего.
     */
    public function action_edit()
    {
        if ( ! $this->can('erp405.inspect'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id         = (int) $this->request->param('id');
        $inspection = $id ? $this->_inspections()->get($id) : NULL;

        if ($id AND ! $inspection)
        {
            throw new HTTP_Exception_404('Контроль качества не найден');
        }

        if ($this->request->method() === Request::POST)
        {
            try
            {
                if ($inspection)
                {
                    $this->_inspections()->update($inspection->id, array(
                        'qty'       => $this->_posted('qty'),
                        'item_name' => $this->_posted('item_name'),
                        'note'      => $this->_posted('note'),
                    ), $this->_current_user_id());

                    $this->_flash('success', 'Контроль '.$inspection->number.' сохранён');
                }
                else
                {
                    $inspection = $this->_inspections()->register(array(
                        'kind'         => $this->_posted('kind'),
                        'qty'          => $this->_posted('qty'),
                        'item_id'      => $this->_posted('item_id'),
                        'item_name'    => $this->_posted('item_name'),
                        'warehouse_id' => $this->_posted('warehouse_id'),
                        'supplier_id'  => $this->_posted('supplier_id'),
                        'order_id'     => $this->_posted('order_id'),
                        'note'         => $this->_posted('note'),
                    ), $this->_current_user_id());

                    $this->_flash('success', 'Контроль '.$inspection->number.' заведён');
                }

                $this->redirect('/quality/inspections/view/'.(int) $inspection->id);
            }
            catch (Exception $e)
            {
                $this->_flash_error($e);
            }
        }

        $this->template->content = View::factory('erp405/inspection/edit')
            ->set('inspection', $inspection)
            ->set('warehouses', $this->_warehouses())
            ->set('kinds', Model_Erp405_Inspection::$kinds)
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Переходы контроля: начать, признать годной, закрыть, отменить.
     *
     * @throws  HTTP_Exception_404
     */
    public function action_act()
    {
        if ( ! $this->can('erp405.inspect'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $act   = (string) $this->request->param('act');
        $id    = (int) $this->request->param('id');
        $user  = $this->_current_user_id();

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/quality/inspections/view/'.$id);
        }

        try
        {
            switch ($act)
            {
                case 'start':
                    $inspection = $this->_inspections()->start($id, $user);
                    $this->_flash('success', 'Контроль '.$inspection->number.' начат');
                    break;

                case 'pass':
                    if ( ! $this->can('erp405.decide'))
                    {
                        throw new HTTP_Exception_403('Доступ запрещен');
                    }

                    $inspection = $this->_inspections()->mark_passed($id, $user);
                    $this->_flash('success', 'Партия '.$inspection->number.' признана годной');
                    break;

                case 'close':
                    if ( ! $this->can('erp405.decide'))
                    {
                        throw new HTTP_Exception_403('Доступ запрещен');
                    }

                    $inspection = $this->_inspections()->close($id, $user);
                    $this->_flash('success', 'Контроль '.$inspection->number.' закрыт');
                    break;

                case 'cancel':
                    if ( ! $this->can('erp405.decide'))
                    {
                        throw new HTTP_Exception_403('Доступ запрещен');
                    }

                    $inspection = $this->_inspections()->cancel($id, $this->_posted('reason'), $user);
                    $this->_flash('warning', 'Контроль '.$inspection->number.' отменён');
                    break;

                default:
                    throw new HTTP_Exception_404('Неизвестное действие контроля');
            }
        }
        catch (HTTP_Exception $e)
        {
            throw $e;
        }
        catch (Exception $e)
        {
            $this->_flash_error($e);
        }

        $this->redirect('/quality/inspections/view/'.$id);
    }

    /**
     * Дефекты: записать или убрать.
     */
    public function action_defect()
    {
        if ( ! $this->can('erp405.inspect'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $action = (string) $this->request->param('action');
        $id     = (int) $this->request->param('id');

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/quality/inspections/view/'.$id);
        }

        try
        {
            if ($action === 'remove')
            {
                $inspection = $this->_inspections()->remove_defect($id, $this->_current_user_id());
                $this->_flash('warning', 'Запись о дефекте удалена из '.$inspection->number);
                $id = (int) $inspection->id;
            }
            else
            {
                $code_id = (int) $this->_posted('defect_code_id');

                $this->_inspections()->add_defect($id, array(
                    'defect_code_id' => $code_id,
                    'qty'            => $this->_posted('qty'),
                    'cause'          => $this->_posted('cause'),
                    'severity'       => $this->_posted('severity'),
                    'note'           => $this->_posted('note'),
                ), $this->_current_user_id());

                $this->_flash('warning', 'Дефект записан: итог контроля — брак');
            }
        }
        catch (Exception $e)
        {
            $this->_flash_error($e);
        }

        $this->redirect('/quality/inspections/view/'.$id);
    }

    /**
     * Решение по браку: переделать, вернуть, утиль, как есть, сортировка.
     */
    public function action_decide()
    {
        if ( ! $this->can('erp405.decide'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id = (int) $this->request->param('id');

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/quality/inspections/view/'.$id);
        }

        try
        {
            $inspection = $this->_inspections()->decide(
                $id,
                $this->_posted('decision'),
                $this->_posted('comment'),
                $this->_current_user_id(),
                array('warehouse_id' => $this->_posted('warehouse_id'))
            );

            $this->_flash('success', 'Решение по '.$inspection->number.': '.$inspection->decision_label());
        }
        catch (Exception $e)
        {
            $this->_flash_error($e);
        }

        $this->redirect('/quality/inspections/view/'.$id);
    }

    /**
     * Изолировать партию на карантин.
     */
    public function action_quarantine()
    {
        if ( ! $this->can('erp405.decide'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id = (int) $this->request->param('id');

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/quality/inspections/view/'.$id);
        }

        try
        {
            $inspection = $this->_inspections()->quarantine(
                $id,
                $this->_posted('warehouse_id'),
                $this->_posted('qty'),
                $this->_current_user_id()
            );

            $this->_flash('warning', 'Партия '.$inspection->number.' изолирована: '.(float) $inspection->quarantine_qty);
        }
        catch (Exception $e)
        {
            $this->_flash_error($e);
        }

        $this->redirect('/quality/inspections/view/'.$id);
    }

    /**
     * Выпустить партию из карантина.
     */
    public function action_release()
    {
        if ( ! $this->can('erp405.decide'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $id = (int) $this->request->param('id');

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/quality/inspections/view/'.$id);
        }

        try
        {
            $inspection = $this->_inspections()->release(
                $id,
                $this->_posted('warehouse_id'),
                $this->_posted('qty'),
                $this->_current_user_id()
            );

            $this->_flash('success', 'Партия '.$inspection->number.' выпущена из карантина');
        }
        catch (Exception $e)
        {
            $this->_flash_error($e);
        }

        $this->redirect('/quality/inspections/view/'.$id);
    }
}
