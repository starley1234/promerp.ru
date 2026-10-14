<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php if (isset($current_user) AND $current_user): ?>
<button type="button" data-bookmark-toggle
        data-status-url="<?php echo Route::url('default', array('controller'=>'erp118_links','action'=>'check_link_status')); ?>"
        data-toggle-url="<?php echo Route::url('default', array('controller'=>'erp118_links','action'=>'toggle_save')); ?>"
        class="rounded-xl p-2 text-gray-500 transition-colors hover:bg-gray-100 hover:text-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-blue-400"
        title="Сохранить текущую страницу в закладки" aria-label="Сохранить текущую страницу в закладки">
    <i class="fa-regular fa-bookmark text-base" aria-hidden="true" data-bookmark-icon></i>
</button>
<?php endif; ?>
