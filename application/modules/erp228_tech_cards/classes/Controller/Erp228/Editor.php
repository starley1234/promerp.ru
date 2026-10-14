<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Создание, правка и запуск генерации технологических карт (erp228_tech_cards).
 *
 * @package    Erp228
 * @category   Controllers
 */
class Controller_Erp228_Editor extends Controller_Erp228_Main
{
    /**
     * Форма создания карты.
     */
    public function action_create()
    {
        if ( ! $this->can('erp228.manage'))
        {
            throw new HTTP_Exception_403('У вас нет прав на создание техкарт');
        }

        $this->set_title('Новая технологическая карта');
        $this->set_description('Исходный код изделия и параметры производства');

        $this->template->content = $this->_form(ORM::factory('Erp228_TechCard'), array());
    }

    /**
     * Форма правки карты.
     */
    public function action_edit()
    {
        if ( ! $this->can('erp228.manage'))
        {
            throw new HTTP_Exception_403('У вас нет прав на правку техкарт');
        }

        try
        {
            $card = Service_Erp228_TechCards::instance()->get($this->request->param('id'));
        }
        catch (Kohana_Exception $e)
        {
            throw new HTTP_Exception_404('Технологическая карта не найдена');
        }

        $this->set_title('Правка: '.$card->title);

        $this->template->content = $this->_form($card, array());
    }

    /**
     * Сохранение карты (POST): создание без id, правка с id в адресе.
     */
    public function action_save()
    {
        if ( ! $this->can('erp228.manage'))
        {
            throw new HTTP_Exception_403('У вас нет прав на правку техкарт');
        }

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect('/tech_cards/list');
        }

        $service = Service_Erp228_TechCards::instance();
        $post    = $this->request->post();
        $id      = (int) $this->request->param('id');

        // ВАЖНО: redirect() бросает HTTP_Exception_302 — держим его вне try,
        // иначе собственный редирект ловится общим catch (см. dev:audit redirect_in_try)
        $redirect = NULL;

        try
        {
            if ($id)
            {
                $card = $service->update($id, $post, $this->_user_id());
                $this->_flash('success', 'Техкарта «'.$card->title.'» обновлена');
            }
            else
            {
                $card = $service->create($post, $this->_user_id());
                $this->_flash('success', 'Техкарта «'.$card->title.'» создана');
            }

            $redirect = '/tech_cards/view/'.$card->id;
        }
        catch (ORM_Validation_Exception $e)
        {
            $card = $id ? $service->get($id) : ORM::factory('Erp228_TechCard');

            $card->values($post, array(
                'title', 'source_code', 'production_type', 'target_type', 'target_id',
            ));

            $this->template->content = $this->_form($card, $e->errors(''));

            return;
        }

        $this->redirect($redirect);
    }

    /**
     * Запуск генерации техпроцесса по карте (POST).
     */
    public function action_generate()
    {
        return $this->_respond(function () {
            if ( ! $this->can('erp228.manage'))
            {
                throw new HTTP_Exception_403('У вас нет прав на генерацию техпроцесса');
            }

            $id     = (int) Arr::get($_POST, 'id', $this->request->param('id'));
            $card   = Service_Erp228_TechCards::instance()->get($id);
            $result = Service_Erp228_TechCards::instance()->generate($card->id, $this->_user_id());

            return array(
                'status'      => 'ok',
                'id'          => (int) $result->id,
                'card_status' => 'ready',
                'message'     => 'Техпроцесс сформирован: '.count($result->operations_ordered()).' операций',
                'redirect'    => '/tech_cards/view/'.$card->id,
            );
        });
    }

    /**
     * Представление формы карты.
     *
     * @param  Model_Erp228_TechCard $card
     * @param  array $errors
     * @return View
     */
    protected function _form($card, array $errors)
    {
        return View::factory('erp228/cards/form')
            ->set('card', $card)
            ->set('errors', $errors)
            ->set('production_types', Model_Erp228_TechCard::$production_types)
            ->set('target_types', Model_Erp228_TechCard::$target_types)
            ->set('statuses', Model_Erp228_TechCard::$statuses)
            ->set('messages', $this->_flash_messages());
    }
}
