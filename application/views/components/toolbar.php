<?php defined('SYSPATH') or die('No direct script access.');
$title = isset($title) ? $title : 'Фильтры';
$body = isset($body) ? $body : '';
$reset = isset($reset) ? $reset : NULL;
ob_start();
?>
<form method="get" class="mb-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800" aria-label="<?php echo HTML::chars($title); ?>"><div class="flex flex-wrap items-end gap-3"><div class="mr-auto"><h2 class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo HTML::chars($title); ?></h2></div><?php echo $body; ?><?php if ($reset): ?><a href="<?php echo HTML::chars($reset); ?>" class="inline-flex min-h-10 items-center rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Сбросить</a><?php endif; ?><button type="submit" class="inline-flex min-h-10 items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">Применить</button></div></form>
<?php
// В простом режиме (Ui_Mode) панель свёрнута и раскрывается сама, когда отбор уже задан.
echo Component::render('more', array(
    'summary' => $title,
    'icon'  => 'fas fa-filter',
    'plain' => TRUE,
    'open'  => Component::query_applied(),
    'body'  => ob_get_clean(),
));
