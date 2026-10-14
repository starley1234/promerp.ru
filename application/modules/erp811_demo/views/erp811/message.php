<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="mx-auto max-w-2xl space-y-6">

    <?php echo Component::render('page_header', array(
        'title'        => 'Демонстрационный стенд',
        'actions_html' => '<a href="'.Route::url('erp811_demo_index').'" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"><i class="fas fa-arrow-left" aria-hidden="true"></i> На страницу стенда</a>',
    )); ?>

    <div role="alert">
        <?php Component::begin('card', array('state' => 'warn', 'icon' => 'fas fa-triangle-exclamation', 'title' => 'Стенд сейчас недоступен')); ?>
            <p class="text-sm text-gray-800 dark:text-gray-100"><?php echo HTML::chars($message); ?></p>
        <?php Component::end(); ?>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        Сценарий: <code><?php echo HTML::chars($scenario->code()); ?></code> ·
        <?php echo HTML::chars($scenario->company()['name']); ?>
    </p>
</div>
