<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-md">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
        <?php echo __('Publish New Event'); ?>
    </h3>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-300 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold"><?php echo __('Error!'); ?></strong>
            <span class="block sm:inline">
                <?php foreach ($errors as $field => $message): ?>
                    <?php echo HTML::chars($message); ?><br>
                <?php endforeach; ?>
            </span>
        </div>
    <?php endif; ?>

    <form action="<?php echo URL::site('erp208_events/widget/event_publisher_form'); ?>" method="post" class="space-y-4">
        <div>
            <label for="event_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                <?php echo __('Event Name'); ?> <span class="text-red-500">*</span>
            </label>
            <input
                type="text"
                id="event_name"
                name="event_name"
                list="event_name_suggestions"
                value="<?php echo HTML::chars(Arr::get($post_data, 'event_name', '')); ?>"
                class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-200"
                placeholder="e.g., erp502.task_done"
                required
            >
            <?php if (!empty($event_name_suggestions)): ?>
                <datalist id="event_name_suggestions">
                    <?php foreach ($event_name_suggestions as $suggestion): ?>
                        <option value="<?php echo HTML::chars($suggestion); ?>">
                    <?php endforeach; ?>
                </datalist>
            <?php endif; ?>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                <?php echo __('A unique identifier for the event (e.g., moduleID.action).'); ?>
            </p>
        </div>

        <div>
            <label for="payload" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                <?php echo __('Payload (JSON)'); ?>
            </label>
            <textarea
                id="payload"
                name="payload"
                rows="5"
                class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-200"
                placeholder='<?php echo HTML::chars('{"task_id": 123, "status": "completed"}'); ?>'
            ><?php echo HTML::chars(Arr::get($post_data, 'payload', '')); ?></textarea>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                <?php echo __('Optional data associated with the event, in JSON format.'); ?>
            </p>
        </div>

        <div class="flex justify-end">
            <button
                type="submit"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
                <i class="fas fa-paper-plane mr-2"></i>
                <?php echo __('Publish Event'); ?>
            </button>
        </div>
    </form>
</div>