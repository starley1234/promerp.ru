<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>
    <div class="flex justify-between items-center mb-6">
        <a href="<?php echo Route::url('erp208_events', array('action' => 'manage_subscription')); ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-900">
            <i class="fas fa-plus-circle mr-2"></i> <?php echo __('Add New Subscriber'); ?>
        </a>
    </div>

    <?php if (Session::instance()->get('success_message')): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 dark:bg-green-900 dark:border-green-700 dark:text-green-300" role="alert">
            <strong class="font-bold"><?php echo __('Success!'); ?></strong>
            <span class="block sm:inline"><?php echo Session::instance()->get_once('success_message'); ?></span>
        </div>
    <?php endif; ?>

    <?php if (Session::instance()->get('error_message')): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 dark:bg-red-900 dark:border-red-700 dark:text-red-300" role="alert">
            <strong class="font-bold"><?php echo __('Error!'); ?></strong>
            <span class="block sm:inline"><?php echo Session::instance()->get_once('error_message'); ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg mb-6">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <form action="<?php echo Route::url('erp208_events', array('action' => 'list_subscribers')); ?>" method="get" class="flex flex-col sm:flex-row items-center space-y-3 sm:space-y-0 sm:space-x-4">
                <div class="relative flex-grow w-full sm:w-auto">
                    <input type="text" name="search" value="<?php echo HTML::chars($search); ?>" placeholder="<?php echo __('Search by event name, module, or callback...'); ?>" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                </div>
                <button type="submit" class="w-full sm:w-auto inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-900">
                    <?php echo __('Search'); ?>
                </button>
                <?php if (!empty($search)): ?>
                    <a href="<?php echo Route::url('erp208_events', array('action' => 'list_subscribers')); ?>" class="w-full sm:w-auto inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600 dark:focus:ring-offset-gray-900">
                        <?php echo __('Reset'); ?>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (count($subscribers) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <caption class="sr-only">Таблица данных</caption>
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo __('Event Name'); ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo __('Module ID'); ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo __('Callback Method'); ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo __('Active'); ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo __('Notification'); ?>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <?php echo __('Description'); ?>
                            </th>
                            <th scope="col" class="relative px-6 py-3">
                                <span class="sr-only"><?php echo __('Actions'); ?></span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($subscribers as $subscriber): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                    <?php echo HTML::chars($subscriber->event_name); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    <?php echo HTML::chars($subscriber->module_id); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                    <?php echo HTML::chars($subscriber->callback_method); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php if ($subscriber->is_active): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                            <?php echo __('Yes'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                                            <?php echo __('No'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php if ($subscriber->is_notification): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                            <?php echo __('Yes'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                            <?php echo __('No'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-300">
                                    <?php 
                                        $desc = strip_tags((string) $subscriber->description);
                                        $words = explode(' ', $desc);
                                        echo HTML::chars(implode(' ', array_slice($words, 0, 12))) . (count($words) > 12 ? '...' : '');
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="<?php echo Route::url('erp208_events', array('action' => 'manage_subscription', 'id' => $subscriber->id)); ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">
                                        <?php echo __('Edit'); ?>
                                    </a>
                                    <a href="<?php echo Route::url('erp208_events', array('action' => 'delete_subscription', 'id' => $subscriber->id)); ?>" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300" data-confirm="<?php echo __('Are you sure you want to delete this subscription?'); ?>">
                                        <?php echo __('Delete'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pagination->total_pages > 1): ?>
                <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                    <?php echo $pagination; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="p-4 text-center text-gray-500 dark:text-gray-400">
                <?php echo __('No subscribers found.'); ?>
            </div>
        <?php endif; ?>
    </div>
</div>