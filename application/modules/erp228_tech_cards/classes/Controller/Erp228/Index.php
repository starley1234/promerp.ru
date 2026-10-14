<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Список и карточка технологических карт (erp228_tech_cards).
 *
 * @package    Erp228
 * @category   Controllers
 */
class Controller_Erp228_Index extends Controller_Erp228_Main
{
    /**
     * Сводка модуля: состояние очереди и объёмы.
     */
    public function action_index()
    {
        if ( ! $this->can('erp228.view'))
        {
            throw new HTTP_Exception_403('У вас нет прав на просмотр техкарт');
        }

        $service = Service_Erp228_TechCards::instance();

        $this->set_title('Технологические карты');
        $this->set_description('Состояние генерации техпроцессов и объёмы производства');

        $this->template->content = View::factory('erp228/index/index')
            ->set('stats', $service->stats())
            ->set('recent', $service->search(array(), 5, 0)['rows'])
            ->set('statuses', Model_Erp228_TechCard::$statuses)
            ->set('production_types', Model_Erp228_TechCard::$production_types)
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Список карт с фильтрами и постраничным выводом.
     */
    public function action_list()
    {
        if ( ! $this->can('erp228.view'))
        {
            throw new HTTP_Exception_403('У вас нет прав на просмотр техкарт');
        }

        $query   = $this->request->query();
        $filters = array(
            'term'            => isset($query['term']) ? trim((string) $query['term']) : NULL,
            'status'          => isset($query['status']) ? (string) $query['status'] : NULL,
            'production_type' => isset($query['production_type']) ? (string) $query['production_type'] : NULL,
        );

        $page  = isset($query['page']) ? max((int) $query['page'], 1) : 1;
        $limit = (int) Kohana::$config->load('erp228_tech_cards.items_per_page');

        if ($limit < 1)
        {
            $limit = 20;
        }

        $page_data = Service_Erp228_TechCards::instance()->search($filters, $limit, ($page - 1) * $limit);

        $this->set_title('Технологические карты');

        $this->template->content = View::factory('erp228/cards/list')
            ->set('items', $page_data['rows'])
            ->set('total', $page_data['total'])
            ->set('filters', $filters)
            ->set('page', $page)
            ->set('limit', $limit)
            ->set('pages', (int) ceil($page_data['total'] / $limit))
            ->set('statuses', Model_Erp228_TechCard::$statuses)
            ->set('production_types', Model_Erp228_TechCard::$production_types)
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Карточка техкарты: исходник, актуальный техпроцесс и история версий.
     */
    public function action_view()
    {
        if ( ! $this->can('erp228.view'))
        {
            throw new HTTP_Exception_403('У вас нет прав на просмотр техкарт');
        }

        $service = Service_Erp228_TechCards::instance();

        try
        {
            $card = $service->get($this->request->param('id'));
        }
        catch (Kohana_Exception $e)
        {
            throw new HTTP_Exception_404('Технологическая карта не найдена');
        }

        $document = $service->actual_document($card->id);

        $this->set_title($card->title);
        $this->set_description('Техпроцесс, операции и нормы времени');

        $this->template->content = View::factory('erp228/cards/view')
            ->set('card', $card)
            ->set('document', $document)
            ->set('operations', $document ? $document->operations_ordered() : array())
            ->set('versions', $service->versions($card->id))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Мягкое удаление карты (POST).
     */
    public function action_delete()
    {
        if ( ! $this->can('erp228.delete'))
        {
            throw new HTTP_Exception_403('У вас нет прав на удаление техкарт');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/tech_cards/list');
        }

        $id = (int) Arr::get($this->request->post(), 'id');

        try
        {
            $card = Service_Erp228_TechCards::instance()->get($id);
            Service_Erp228_TechCards::instance()->delete($id, $this->_user_id());

            $this->_flash('success', 'Техкарта «'.$card->title.'» перемещена в архив');
        }
        catch (Exception $e)
        {
            $this->_flash('error', 'Не удалось удалить: '.$e->getMessage());
        }

        $this->redirect('/tech_cards/list');
    }
}
