<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Точка входа в аудит для модулей (исторический интерфейс).
 *
 * Раньше это была заглушка: запись уходила только в лог Kohana, в базе ничего
 * не оставалось. Теперь вызовы идут в журнал действий (модуль erp119_audit),
 * а файловый лог сохраняется как зеркало для отладки.
 *
 * Сигнатуры вызовов в проекте исторически разные, поэтому разбор форматов
 * живёт в Service_Erp119_Journal::legacy():
 *
 *   1. Audit::log('module_manager', 'status_changed', $id, array('old_status' => …, 'new_status' => …));
 *   2. Audit::log('email_direct_send_attempt', array('to' => …));
 *   3. Audit::log('erp221.deal.updated', $id, $after, $before);
 *   4. Audit::log('erp221.deal.deleted', $id, array('deal_id' => $id));
 *
 * Запись в журнал никогда не ломает бизнес-операцию: сбой базы уводит запись
 * в файл-страховку (application/logs/audit-fallback.log), откуда её возвращает
 * задача `bin/console erp119:sync --replay`.
 *
 * @package    Audit
 * @category   Security
 */
class Audit {

    /**
     * Зафиксировать действие в журнале аудита.
     *
     * @param   string  $category   категория или имя события ('module_manager', 'erp221.deal.updated')
     * @param   mixed   $action     действие либо данные (исторические форматы)
     * @param   mixed   $object_id  идентификатор объекта
     * @param   mixed   $data       данные действия
     * @return  void
     */
    public static function log($category, $action = NULL, $object_id = NULL, $data = NULL)
    {
        // 1. Зеркало в файловый лог: остаётся для отладки и разбора инцидентов
        Kohana::$log->add(Log::INFO, 'AUDIT: :category - :action (Object ID: :object_id, Data: :data)', array(
            ':category'  => (string) $category,
            ':action'    => is_scalar($action) ? (string) $action : gettype($action),
            ':object_id' => is_scalar($object_id) ? (string) $object_id : gettype($object_id),
            ':data'      => json_encode(is_scalar($data) ? $data : (array) $data, JSON_UNESCAPED_UNICODE),
        ));

        // 2. Запись в журнал действий (erp119_audit)
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            // Модуль выключен — остаётся только файловый лог
            return;
        }

        try
        {
            Service_Erp119_Journal::instance()->legacy($category, $action, $object_id, $data);
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::ERROR, 'AUDIT: запись в журнал не удалась — :error', array(
                ':error' => $e->getMessage(),
            ));
        }
    }
}
