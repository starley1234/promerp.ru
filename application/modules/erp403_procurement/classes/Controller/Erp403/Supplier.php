<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Поставщики (erp403_procurement): условия закупки, история цен и сравнение
 * по позиции.
 *
 * Поставщик живёт в справочнике контрагентов (erp301); здесь — закупочная
 * часть: срок поставки, валюта, минимальная партия, оценка, предпочтение.
 *
 * @package    Erp403
 * @category   Controllers
 */
class Controller_Erp403_Supplier extends Controller_Erp403_Main
{
    /**
     * Список поставщиков с условиями.
     */
    public function action_index()
    {
        if ( ! $this->can('erp403.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $filters = array(
            'search'    => $this->_query('search'),
            'preferred' => $this->_query('preferred'),
        );

        $page   = max(1, (int) $this->_query('page', 1));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $this->template->content = View::factory('erp403/supplier/index')
            ->set('rows', $this->_suppliers()->search($filters, $limit, $offset))
            ->set('total', $this->_suppliers()->count($filters))
            ->set('filters', $filters)
            ->set('page', $page)
            ->set('limit', $limit)
            ->set('stats', $this->_suppliers()->stats())
            ->set('savings', $this->_suppliers()->savings(10))
            ->set('can_manage', $this->can('erp403.manage'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Карточка поставщика: условия, цены, исполнительность.
     */
    public function action_view()
    {
        if ( ! $this->can('erp403.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $supplier_id  = (int) $this->request->param('id');
        $counterparty = $this->_suppliers()->counterparty($supplier_id);

        if ( ! $counterparty)
        {
            throw new HTTP_Exception_404('Поставщик не найден');
        }

        $profile = $this->_suppliers()->profile($supplier_id, TRUE);
        $item_id = (int) $this->_query('item_id');

        $this->template->content = View::factory('erp403/supplier/view')
            ->set('counterparty', $counterparty)
            ->set('profile', $profile)
            ->set('prices', $this->_suppliers()->supplier_prices($supplier_id, 50))
            ->set('on_time', $this->_suppliers()->on_time_rate($supplier_id))
            ->set('compare', $item_id ? $this->_suppliers()->compare($item_id, NULL) : array())
            ->set('compare_item_id', $item_id)
            ->set('orders', $this->_orders()->search(array('supplier_id' => $supplier_id), 10, 0))
            ->set('order_stats', $this->_orders()->stats())
            ->set('can_manage', $this->can('erp403.manage'))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Сохранить закупочные условия поставщика (POST).
     */
    public function action_save()
    {
        if ( ! $this->can('erp403.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/procurement/suppliers');
        }

        $post        = $this->request->post();
        $supplier_id = ! empty($post['supplier_id']) ? (int) $post['supplier_id'] : NULL;

        try
        {
            $profile = $this->_suppliers()->save_profile($supplier_id, $post, $this->_current_user_id());

            $this->_flash('success', 'Условия поставщика сохранены: срок '.$profile->lead_time().' дн.');
        }
        catch (Exception $e)
        {
            $this->_flash_error($e, 'Не удалось сохранить условия поставщика');
        }

        $this->redirect($supplier_id ? '/procurement/suppliers/view/'.$supplier_id : '/procurement/suppliers');
    }

    /** JSON suggestions for the human-readable item selector. */
    public function action_reference()
    {
        if ( ! $this->can('erp403.view')) throw new HTTP_Exception_403('Доступ запрещен');

        $this->auto_render = FALSE;
        $term = mb_strtolower(trim((string) $this->_query('q')));
        $items = array();
        if (class_exists('Service_Erp401_Stock'))
        {
            foreach (Service_Erp401_Stock::instance()->catalog(500) as $item)
            {
                $haystack = mb_strtolower((string) $item['code'].' '.(string) $item['name']);
                if ($term !== '' AND mb_strpos($haystack, $term) === FALSE) continue;
                $items[] = array(
                    'id'=>(int) $item['id'],
                    'label'=>trim((string) $item['code'].' · '.(string) $item['name'], ' ·'),
                    'context'=>'Номенклатура',
                );
                if (count($items) >= 20) break;
            }
        }
        $this->response->headers('Content-Type', 'application/json; charset=utf-8');
        $this->response->body(json_encode(array('items'=>$items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Сравнение поставщиков по позиции (страница «у кого брать»).
     */
    public function action_compare()
    {
        if ( ! $this->can('erp403.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $item_id = (int) $this->_query('item_id');
        $qty     = $this->_query('qty');

        $rows   = $item_id ? $this->_suppliers()->compare($item_id, $qty) : array();
        $choice = $rows ? $rows[0] : NULL;

        $this->template->content = View::factory('erp403/supplier/compare')
            ->set('rows', $rows)
            ->set('choice', $choice)
            ->set('item_id', $item_id)
            ->set('qty', $qty)
            ->set('item_name', $item_id ? $this->_suppliers()->item_name($item_id) : NULL)
            ->set('history', $item_id ? $this->_suppliers()->history($item_id, 30) : array())
            ->set('messages', $this->_flash_messages());
    }
}
