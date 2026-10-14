<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Договоры контрагентов (erp301_counterparty).
 *
 * @package    Erp301
 * @category   Controllers
 */
class Controller_Erp301_Contract extends Controller_Erp301_Main
{
    const PAGE_SIZE = 50;

    /**
     * Список договоров с фильтрами.
     */
    public function action_index()
    {
        if ( ! $this->can('erp301.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещён');
        }

        $filters = array(
            'status'          => $this->request->query('status'),
            'kind'            => $this->request->query('kind'),
            'term'            => trim((string) $this->request->query('term')),
            'counterparty_id' => (int) $this->request->query('counterparty_id'),
        );

        $page = max(1, (int) $this->request->query('page'));

        $result = Service_Erp301_Counterparties::instance()
            ->contracts($filters, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);

        $this->template->content = View::factory('erp301/contract/index')
            ->set('rows', $result['rows'])
            ->set('total', $result['total'])
            ->set('filters', $filters)
            ->set('page', $page)
            ->set('page_size', self::PAGE_SIZE)
            ->set('kinds', Model_Erp301_Contract::$kinds)
            ->set('statuses', Model_Erp301_Contract::$statuses)
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Форма договора (создание/редактирование).
     */
    public function action_edit()
    {
        if ( ! $this->can('erp301.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещён');
        }

        $service = Service_Erp301_Counterparties::instance();

        $id   = (int) $this->request->param('id');
        $contract = $id ? ORM::factory('Erp301_Contract', $id) : ORM::factory('Erp301_Contract');

        if ($id AND ( ! $contract->loaded() OR (int) $contract->is_deleted === 1))
        {
            throw new HTTP_Exception_404('Договор не найден');
        }

        $counterparty_id = (int) $this->request->query('counterparty_id');

        if ( ! $counterparty_id AND $contract->loaded())
        {
            $counterparty_id = (int) $contract->counterparty_id;
        }

        $counterparty = NULL;

        if ($counterparty_id)
        {
            try
            {
                $counterparty = $service->get($counterparty_id);
            }
            catch (Kohana_Exception $e)
            {
                throw new HTTP_Exception_404('Контрагент не найден');
            }
        }

        $this->template->content = View::factory('erp301/contract/form')
            ->set('contract', $contract)
            ->set('counterparty', $counterparty)
            ->set('counterparty_id', $counterparty_id)
            ->set('kinds', Model_Erp301_Contract::$kinds)
            ->set('statuses', Model_Erp301_Contract::$statuses)
            ->set('errors', array())
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Сохранение договора (POST).
     */
    public function action_save()
    {
        if ( ! $this->can('erp301.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещён');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/counterparties/contracts');
        }

        $post   = $this->request->post();
        $id     = (int) Arr::get($post, 'id');
        $cp_id  = (int) Arr::get($post, 'counterparty_id');
        $service = Service_Erp301_Counterparties::instance();

        try
        {
            $contract = $service->save_contract($cp_id, $post, $id ?: NULL, $this->_user_id());

            $this->_flash('success', 'Договор № '.$contract->number.' сохранён');
            $this->redirect('/counterparties/view/'.$contract->counterparty_id);
        }
        catch (ORM_Validation_Exception $e)
        {
            $contract = $id ? ORM::factory('Erp301_Contract', $id) : ORM::factory('Erp301_Contract');
            $contract->values($post, array(
                'number', 'title', 'kind', 'signed_at', 'valid_from', 'valid_to',
                'amount', 'currency', 'payment_terms', 'status', 'note',
            ));

            $counterparty = NULL;

            try
            {
                $counterparty = $service->get($cp_id);
            }
            catch (Kohana_Exception $e)
            {
                throw new HTTP_Exception_404('Контрагент не найден');
            }

            $this->template->content = View::factory('erp301/contract/form')
                ->set('contract', $contract)
                ->set('counterparty', $counterparty)
                ->set('counterparty_id', $cp_id)
                ->set('kinds', Model_Erp301_Contract::$kinds)
                ->set('statuses', Model_Erp301_Contract::$statuses)
                ->set('errors', $e->errors(''))
                ->set('messages', $this->_flash_messages());
        }
    }

    /**
     * Расторжение договора (POST).
     */
    public function action_terminate()
    {
        if ( ! $this->can('erp301.manage'))
        {
            throw new HTTP_Exception_403('Доступ запрещён');
        }

        $id = (int) Arr::get($this->request->post(), 'id');

        try
        {
            $contract = Service_Erp301_Counterparties::instance()->terminate_contract($id, $this->_user_id());
            $this->_flash('success', 'Договор № '.$contract->number.' расторгнут');

            $this->redirect('/counterparties/view/'.$contract->counterparty_id);
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
            $this->redirect('/counterparties/contracts');
        }
    }
}
