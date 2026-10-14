<?php defined('SYSPATH') or die('No direct script access.');

/**
 * HMVC-виджеты модуля технологических карт (erp228_tech_cards).
 *
 * Пример вставки в другое представление:
 *
 *     echo Request::factory('/tech_cards/widget/status_badge/12')->execute()->body();
 *
 * Виджеты отдают пустую строку, если карты нет или нет прав: чужой модуль
 * не должен ломаться из-за встроенного блока.
 *
 * @package    Erp228
 * @category   Controllers
 */
class Controller_Erp228_Widget extends Controller_Erp228_Main
{
    public function before()
    {
        parent::before();

        $this->auto_render = FALSE;
    }

    /**
     * Бейдж состояния генерации карты.
     */
    public function action_status_badge()
    {
        $this->response->headers('Content-Type', 'text/html; charset=utf-8');

        if ( ! $this->can('erp228.view'))
        {
            $this->response->body('');

            return;
        }

        try
        {
            $card = Service_Erp228_TechCards::instance()->get($this->request->param('id'));
        }
        catch (Kohana_Exception $e)
        {
            $this->response->body('');

            return;
        }

        $this->response->body(View::factory('erp228/widgets/status_badge')
            ->set('card', $card)
            ->set('statuses', Model_Erp228_TechCard::$statuses)
            ->render());
    }

    /**
     * Список операций актуального техпроцесса карты.
     */
    public function action_operations_list()
    {
        $this->response->headers('Content-Type', 'text/html; charset=utf-8');

        if ( ! $this->can('erp228.view'))
        {
            $this->response->body('');

            return;
        }

        try
        {
            $card = Service_Erp228_TechCards::instance()->get($this->request->param('id'));
        }
        catch (Kohana_Exception $e)
        {
            $this->response->body('');

            return;
        }

        $document = Service_Erp228_TechCards::instance()->actual_document($card->id);

        $this->response->body(View::factory('erp228/widgets/operations_list')
            ->set('card', $card)
            ->set('document', $document)
            ->set('operations', $document ? $document->operations_ordered() : array())
            ->render());
    }
}
