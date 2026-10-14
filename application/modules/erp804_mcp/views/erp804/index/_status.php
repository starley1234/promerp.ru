<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
$labels = array(
    'pending'           => 'выполняется',
    'ok'                => 'выполнен',
    'error'             => 'ошибка',
    'awaiting_approval' => 'ждёт подтверждения',
    'approved'          => 'одобрен',
    'rejected'          => 'отклонён',
);

$classes = array(
    'pending'           => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    'ok'                => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    'error'             => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    'awaiting_approval' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200',
    'approved'          => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    'rejected'          => 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
);
?>

<span class="px-2 py-1 rounded text-xs <?php echo isset($classes[$status]) ? $classes[$status] : $classes['pending']; ?>">
    <?php echo HTML::chars(isset($labels[$status]) ? $labels[$status] : $status); ?>
</span>
