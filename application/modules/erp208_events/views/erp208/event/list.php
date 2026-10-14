<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white"><?= __('All Events') ?></h2>
        <a href="<?= Route::url('erp208_events', array('action' => 'create_event')) ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
            <i class="fas fa-plus-circle mr-2"></i>
            <?= __('Publish New Event') ?>
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 mb-6">
        <form action="<?= Route::url('erp208_events', array('action' => 'list_events')) ?>" method="get" class="flex flex-col sm:flex-row items-center gap-4">
            <div class="relative flex-grow w-full sm:w-auto">
                <input type="text" name="search" id="search" class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="<?= __('Search by event name...') ?>" value="<?= HTML::chars($search) ?>">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
            </div>
            <button type="submit" class="w-full sm:w-auto px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                <?= __('Search') ?>
            </button>
            <?php if (!empty($search)): ?>
                <a href="<?= Route::url('erp208_events', array('action' => 'list_events')) ?>" class="w-full sm:w-auto px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors text-center">
                    <?= __('Reset') ?>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (count($events) > 0): ?>
        <div class="overflow-x-auto bg-white dark:bg-gray-800 shadow-lg rounded-lg">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <caption class="sr-only">Таблица данных</caption>
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            <?= __('ID') ?>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            <?= __('Event Name') ?>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            <?= __('Payload Summary') ?>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            <?= __('Created At') ?>
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            <?= __('Actions') ?>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                <?= HTML::chars($event->id) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                <?= HTML::chars($event->event_name) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 max-w-xs truncate">
                                <?php
                                    $payload_data = json_decode($event->payload, true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($payload_data)) {
                                        echo HTML::chars(substr(json_encode($payload_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 0, 100)) . (strlen(json_encode($payload_data)) > 100 ? '...' : '');
                                    } else {
                                        echo HTML::chars(substr($event->payload, 0, 100)) . (strlen($event->payload) > 100 ? '...' : '');
                                    }
                                ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                <?= HTML::chars(date('Y-m-d H:i:s', strtotime($event->created_at))) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="<?= Route::url('erp208_events', array('action' => 'view_event', 'id' => $event->id)) ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3" title="<?= __('View Details') ?>">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?= Route::url('erp208_events', array('action' => 'edit_event', 'id' => $event->id)) ?>" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-3" title="<?= __('Edit Event') ?>">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="<?= Route::url('erp208_events', array('action' => 'delete_event', 'id' => $event->id)) ?>" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300" title="<?= __('Delete Event') ?>" data-confirm="<?= __('Are you sure you want to delete this event?') ?>">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pagination->total_pages > 1): ?>
            <div class="mt-6">
                <?= $pagination->render() ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 text-center text-gray-700 dark:text-gray-300">
            <p class="text-lg mb-4"><?= __('No events found.') ?></p>
            <p><?= __('You can publish a new event using the button above.') ?></p>
        </div>
    <?php endif; ?>
</div>

<?php if (Session::instance()->get('success_message')): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            window.showNotification('<?= Session::instance()->get_once('success_message') ?>', 'success');
        });
    </script>
<?php endif; ?>

<?php if (Session::instance()->get('error_message')): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            window.showNotification('<?= Session::instance()->get_once('error_message') ?>', 'error');
        });
    </script>
<?php endif; ?>