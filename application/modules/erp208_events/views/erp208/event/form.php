<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 sm:p-8">
        <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
            <?php echo HTML::chars($event->loaded() ? __('Edit Event: :event_name', array(':event_name' => $event->event_name)) : __('Создать новое событие')); ?>
        </h3>

        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-300 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold"><?php echo __('Validation Error!'); ?></strong>
                <span class="block sm:inline"><?php echo __('Please correct the following issues:'); ?></span>
                <ul class="mt-2 list-disc list-inside">
                    <?php foreach ($errors as $field => $message): ?>
                        <li><?php echo HTML::chars($message); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?php echo HTML::chars($event->loaded() ? Route::url('erp208_events', array('action' => 'edit_event', 'id' => $event->id)) : Route::url('erp208_events', array('action' => 'create_event'))); ?>" method="post">
            <div class="mb-4">
                <label for="event_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    <?php echo __('Event Name'); ?> <span class="text-red-500">*</span>
                </label>
                <input type="text"
                       id="event_name"
                       name="event_name"
                       class="form-input w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 <?php echo isset($errors['event_name']) ? 'border-red-500' : ''; ?>"
                       value="<?php echo HTML::chars(Arr::get($_POST, 'event_name', $event->event_name)); ?>"
                       placeholder="<?php echo __('e.g., erp502.task_done'); ?>"
                       required>
                <?php if (isset($errors['event_name'])): ?>
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?php echo HTML::chars($errors['event_name']); ?></p>
                <?php endif; ?>
            </div>

            <div class="mb-6">
                <label for="payload" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    <?php echo __('Event Payload (JSON)'); ?>
                </label>
                <textarea id="payload"
                          name="payload"
                          rows="8"
                          class="form-textarea w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 <?php echo isset($errors['payload']) ? 'border-red-500' : ''; ?>"
                          placeholder='<?php echo HTML::chars(__('e.g., {"task_id": 123, "status": "completed"}')); ?>'><?php echo HTML::chars(Arr::get($_POST, 'payload', $event->payload)); ?></textarea>
                <?php if (isset($errors['payload'])): ?>
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?php echo HTML::chars($errors['payload']); ?></p>
                <?php endif; ?>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    <?php echo __('Enter event data in JSON format. This data will be passed to event listeners.'); ?>
                </p>
            </div>

            <div class="flex justify-end space-x-3">
                <a href="<?php echo HTML::chars(Route::url('erp208_events', array('action' => 'list_events'))); ?>"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    <?php echo __('Cancel'); ?>
                </a>
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    <i class="fas fa-save mr-2"></i>
                    <?php echo HTML::chars($event->loaded() ? __('Save Changes') : __('Create & Publish Event')); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const payloadTextarea = document.getElementById('payload');

        // Функция для форматирования JSON
        function formatJson() {
            try {
                const json = JSON.parse(payloadTextarea.value);
                payloadTextarea.value = JSON.stringify(json, null, 2);
            } catch (e) {
                // Если JSON невалиден, оставляем как есть
                console.warn('Invalid JSON in payload:', e);
            }
        }

        // Форматируем JSON при загрузке, если он есть
        if (payloadTextarea.value.trim() !== '') {
            formatJson();
        }

        // Добавляем обработчик для форматирования при потере фокуса
        payloadTextarea.addEventListener('blur', formatJson);
    });
</script>