<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Task для фоновой индексации объектов реестра (векторный поиск).
 *
 * @package Erp227
 */
class Task_Erp227_Indexing extends Minion_Task {

    protected $_options = array(
        'limit' => 10,
    );

    protected function _execute(array $params)
    {
        try {
            $limit = (int) $params['limit'];
            $indexed_count = 0;

            Minion_CLI::write("Starting Erp227 Vector Indexing...");
            Minion_CLI::write("Target limit: " . $limit . " objects.");

            $search_service = Service_Erp227_Search::instance();

            for ($i = 0; $i < $limit; $i++) {
                // Вызываем метод поиска и индексации следующего объекта
                $result = $search_service->index_next_unindexed();

                if ($result) {
                    $indexed_count++;
                    Minion_CLI::write(Minion_CLI::color("Indexed object " . ($i + 1) . "/" . $limit, 'green'));
                } else {
                    Minion_CLI::write("No more objects to index.");
                    break;
                }
            }

            Minion_CLI::write('----------------------------------------');
            Minion_CLI::write(Minion_CLI::color('Indexing session completed.', 'cyan'));
            Minion_CLI::write('Total objects processed: ' . $indexed_count);
            Minion_CLI::write('----------------------------------------');

        } catch (Exception $e) {
            Minion_CLI::write(Minion_CLI::color('CRITICAL ERROR: ' . $e->getMessage(), 'red'));
            Kohana_Exception::log($e);
        }
    }
}