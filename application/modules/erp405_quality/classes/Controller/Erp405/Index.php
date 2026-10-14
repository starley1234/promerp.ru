<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Доска качества (erp405_quality).
 *
 * Отвечает на вопрос «что с качеством сегодня»: сколько партий проверено,
 * сколько брака и на какую долю, что ждёт решения, что стоит на карантине и где
 * теряем качество — у поставщика, в цехе или на складе.
 *
 * @package    Erp405
 * @category   Controllers
 */
class Controller_Erp405_Index extends Controller_Erp405_Main
{
    /**
     * Сводка качества.
     */
    public function action_index()
    {
        if ( ! $this->can('erp405.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $this->template->content = View::factory('erp405/index/index')
            ->set('summary', $this->_stats()->summary())
            ->set('pending', $this->_inspections()->pending(10))
            ->set('quarantine', $this->_stats()->quarantine())
            ->set('by_cause', $this->_stats()->by_cause())
            ->set('by_supplier', $this->_stats()->by_supplier(NULL, NULL, 5))
            ->set('by_month', $this->_stats()->by_month(6))
            ->set('by_code', $this->_stats()->by_code(NULL, NULL, 5))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Карантин: партии, которые физически лежат на складе карантина.
     */
    public function action_quarantine()
    {
        if ( ! $this->can('erp405.view'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $this->template->content = View::factory('erp405/index/quarantine')
            ->set('rows', $this->_stats()->quarantine())
            ->set('warehouses', $this->_inspections()->quarantine_warehouses())
            ->set('can_decide', $this->can('erp405.decide'))
            ->set('messages', $this->_flash_messages());
    }
}
