<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Журнал и проведение складских движений (erp401_wms).
 *
 * @package    Erp401
 * @category   Controllers
 */
class Controller_Erp401_Movement extends Controller_Erp401_Main
{
    /**
     * Журнал движений с фильтрами.
     */
    public function action_index()
    {
        if ( ! $this->can('erp401_wms.view_stock'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $stock = Service_Erp401_Stock::instance();

        $filters = array(
            'type'         => $this->request->query('type'),
            'warehouse_id' => $this->request->query('warehouse_id'),
            'item_id'      => $this->request->query('item_id'),
            'date_from'    => $this->request->query('date_from'),
            'date_to'      => $this->request->query('date_to'),
        );

        $page   = max(1, (int) $this->request->query('page'));
        $limit  = 50;
        $result = $stock->movements($filters, $limit, ($page - 1) * $limit);

        $this->template->content = View::factory('erp401/movement/index')
            ->set('rows', $result['rows'])
            ->set('total', $result['total'])
            ->set('page', $page)
            ->set('limit', $limit)
            ->set('filters', $filters)
            ->set('types', Model_Erp401_Movement::$types)
            ->set('warehouses', $stock->warehouses(TRUE))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Форма проведения операции (приход / расход / перемещение).
     */
    public function action_create()
    {
        if ( ! $this->can('erp401_wms.manage_movements'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $stock = Service_Erp401_Stock::instance();

        $this->template->content = View::factory('erp401/movement/create')
            ->set('types', Model_Erp401_Movement::$types)
            ->set('warehouses', $stock->warehouses(TRUE))
            ->set('catalog', $stock->catalog())
            ->set('units', $stock->units())
            ->set('old', array())
            ->set('errors', array())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Проведение операции (POST).
     *
     * Все изменения остатков идут только через Service_Erp401_Stock —
     * он держит транзакцию, пишет журнал и публикует события.
     */
    public function action_post()
    {
        if ( ! $this->can('erp401_wms.manage_movements'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/warehouse/movements/create');
        }

        $post         = $this->request->post();
        $stock        = Service_Erp401_Stock::instance();
        $type         = (string) Arr::get($post, 'type');
        $item_id      = (int) Arr::get($post, 'item_id');
        $qty          = str_replace(',', '.', (string) Arr::get($post, 'qty', '0'));
        $warehouse_id = (int) Arr::get($post, 'warehouse_id');
        $to_id        = (int) Arr::get($post, 'warehouse_to_id');

        $opt = array(
            'uom_code'  => (string) Arr::get($post, 'uom_code', 'pcs'),
            'doc_type'  => (string) Arr::get($post, 'doc_type', 'manual'),
            'doc_id'    => Arr::get($post, 'doc_id') ? (int) Arr::get($post, 'doc_id') : NULL,
            'note'      => (string) Arr::get($post, 'note'),
        );

        try
        {
            switch ($type)
            {
                case 'receipt':
                    $movement = $stock->receive($warehouse_id, $item_id, $qty, $opt);
                    break;

                case 'issue':
                    $movement = $stock->issue($warehouse_id, $item_id, $qty, $opt);
                    break;

                case 'transfer':
                    $movement = $stock->transfer($warehouse_id, $to_id, $item_id, $qty, $opt);
                    break;

                default:
                    throw new Exception_Erp401_Stock('Неизвестный тип операции', NULL, 'invalid_type');
            }

            $this->_flash('success', 'Операция проведена: движение #'.$movement->id
                .' ('.HTML::chars($movement->type_label()).', '
                .$movement->qty.' '.$movement->uom_code.')');

            $this->redirect('/warehouse/movements');
        }
        catch (Exception_Erp401_Stock $e)
        {
            // Ошибка бизнес-правила: показываем форму снова с текстом ошибки
            $this->template->content = View::factory('erp401/movement/create')
                ->set('types', Model_Erp401_Movement::$types)
                ->set('warehouses', $stock->warehouses(TRUE))
                ->set('catalog', $stock->catalog())
                ->set('units', $stock->units())
                ->set('old', $post)
                ->set('errors', array($e->getMessage()))
                ->set('messages', array());
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::ERROR, 'erp401: ошибка проведения операции — :error',
                array(':error' => $e->getMessage()));

            $this->_flash('error', 'Не удалось провести операцию. Подробности в журнале.');
            $this->redirect('/warehouse/movements/create');
        }
    }
}
