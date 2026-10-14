<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 mb-6">
        <div class="flex items-center justify-between mb-4 border-b border-gray-200 dark:border-gray-700 pb-4">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                <?php echo HTML::chars($event->event_name); ?>
            </h2>
            <div class="flex space-x-2">
                <a href="<?php echo Route::url('erp208_events', array('action' => 'edit_event', 'id' => $event->id)); ?>"
                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    <i class="fas fa-edit mr-2"></i> <?php echo __('Edit'); ?>
                </a>
                <a href="<?php echo Route::url('erp208_events', array('action' => 'delete_event', 'id' => $event->id)); ?>"
                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors"
                   data-confirm="<?php echo __('Are you sure you want to delete this event?'); ?>">
                    <i class="fas fa-trash-alt mr-2"></i> <?php echo __('Delete'); ?>
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <p class="text-gray-500 dark:text-gray-400 text-sm mb-1"><?php echo __('Event ID'); ?>:</p>
                <p class="text-gray-900 dark:text-gray-100 text-lg font-semibold"><?php echo HTML::chars($event->id); ?></p>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400 text-sm mb-1"><?php echo __('Event Name'); ?>:</p>
                <p class="text-gray-900 dark:text-gray-100 text-lg font-semibold"><?php echo HTML::chars($event->event_name); ?></p>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400 text-sm mb-1"><?php echo __('Published At'); ?>:</p>
                <p class="text-gray-900 dark:text-gray-100 text-lg font-semibold"><?php echo HTML::chars(date('Y-m-d H:i:s', strtotime($event->created_at))); ?></p>
            </div>
        </div>

        <div class="mt-6">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3"><?php echo __('Event Payload'); ?>:</h3>
            <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded-md overflow-x-auto">
                <pre class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap"><?php
                    // Попытка декодировать JSON для красивого вывода
                    $payload_decoded = json_decode($event->payload, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        echo HTML::chars(json_encode($payload_decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    } else {
                        echo HTML::chars($event->payload);
                    }
                ?></pre>
            </div>
        </div>

        <div class="mt-8 text-center">
            <a href="<?php echo Route::url('erp208_events', array('action' => 'list_events')); ?>"
               class="inline-flex items-center px-6 py-3 border border-gray-300 dark:border-gray-600 text-base font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                <i class="fas fa-arrow-left mr-3"></i> <?php echo __('Back to Event List'); ?>
            </a>
        </div>
    </div>
</div>

<?php
// Вывод сообщений об успехе/ошибке, если они есть в сессии
if (Session::instance()->get('success_message')) {
    echo '<script>document.addEventListener("DOMContentLoaded", function() { showNotification("' . addslashes(Session::instance()->get_once('success_message')) . '", "success"); });</script>';
}
if (Session::instance()->get('error_message')) {
    echo '<script>document.addEventListener("DOMContentLoaded", function() { showNotification("' . addslashes(Session::instance()->get_once('error_message')) . '", "error"); });</script>';
}
?>