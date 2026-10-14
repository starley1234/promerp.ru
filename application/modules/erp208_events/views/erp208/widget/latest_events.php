<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="card bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 mb-6">
    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
        <?= __('Latest Events') ?>
    </h3>

    <?php if (empty($latest_events)): ?>
        <p class="text-gray-600 dark:text-gray-400">
            <?= __('No events recorded yet.') ?>
        </p>
    <?php else: ?>
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            <?php foreach ($latest_events as $event): ?>
                <li class="py-3 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-blue-600 dark:text-blue-400">
                            <?= HTML::chars($event->event_name) ?>
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            <?= HTML::chars(Date::fuzzy_span($event->created_at)) ?>
                            (<?= HTML::chars(date('Y-m-d H:i:s', $event->created_at)) ?>)
                        </p>
                    </div>
                    <a href="<?= Route::url('erp208_events', array('action' => 'view_event', 'id' => $event->id)) ?>"
                       class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium transition-colors duration-200"
                       title="<?= __('View details') ?>">
                        <i class="fas fa-eye"></i>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="mt-4 text-right">
            <a href="<?= Route::url('erp208_events', array('action' => 'list_events')) ?>"
               class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium transition-colors duration-200">
                <?= __('View All Events') ?> <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    <?php endif; ?>
</div>