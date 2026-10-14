<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Фоновая задача для обработки очереди уведомлений.
 * Модуль ERP217: Центр уведомлений
 * 
 * Запуск через Cron:
 * * * * * * /usr/bin/php /path/to/index.php --task=Erp217:Process
 *
 * @package    ERP217
 * @category   Task
 */
class Task_Erp217_Process extends Minion_Task {

    /**
     * Конфигурация параметров задачи
     * @var array
     */
    protected $_options = array(
        'limit' => NULL,
    );

    /**
     * Исполнение задачи обработки очереди
     *
     * @param array $params
     * @return void
     */
    protected function _execute(array $params)
    {
        try 
        {
            $limit = $params['limit'];
            
            // Получаем экземпляр шины уведомлений
            $bus = Service_Erp217_Bus::instance();
            
            // Запуск процесса обработки
            $bus->process_notification($limit);

            Minion_CLI::write("Notification queue processing completed.");
        }
        catch (Exception $e)
        {
            Minion_CLI::write("Error processing notification queue: " . $e->getMessage());
            Kohana_Log::instance()->add(Log::ERROR, 'Task_Erp217_Process Error: ' . $e->getMessage());
        }
    }
}