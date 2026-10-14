<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Виджеты качества (erp405_quality): для дашбордов и карточек соседних модулей.
 *
 * @package    Erp405
 * @category   Controllers
 */
class Controller_Erp405_Widget extends Controller_Erp405_Main
{
    /** @var bool Виджет не рендерит общий шаблон — только свой фрагмент */
    public $auto_render = FALSE;

    /**
     * Сводка качества: брак, доля брака, что ждёт решения, карантин.
     */
    public function action_summary()
    {
        if ( ! $this->can('erp405.view'))
        {
            return;
        }

        $this->response->body(View::factory('erp405/widget/summary')
            ->set('summary', $this->_stats()->summary())
            ->set('pending', $this->_inspections()->pending(5))
            ->render());
    }

    /**
     * Контроли по позиции приёмки закупки (для карточки приёмки в erp403).
     */
    public function action_receipt_line()
    {
        if ( ! $this->can('erp405.view'))
        {
            return;
        }

        $line_id = (int) $this->request->param('id');

        if ( ! $line_id)
        {
            $line_id = (int) $this->_query('line_id');
        }

        $this->response->body(View::factory('erp405/widget/receipt_line')
            ->set('line_id', $line_id)
            ->set('inspections', $line_id ? $this->_inspections()->for_receipt_line($line_id) : array())
            ->render());
    }

    /**
     * Что стоит на карантине (для склада и производства).
     */
    public function action_quarantine()
    {
        if ( ! $this->can('erp405.view'))
        {
            return;
        }

        $this->response->body(View::factory('erp405/widget/quarantine')
            ->set('rows', $this->_stats()->quarantine())
            ->render());
    }
}
