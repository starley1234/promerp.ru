<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php
/**
 * Цветной бейдж статуса контрагента или договора.
 * Ожидает: $status, $labels.
 */
$map = array(
    'active'     => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    'blocked'    => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    'archived'   => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    'draft'      => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    'suspended'  => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200',
    'expired'    => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200',
    'terminated' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
);
$class = isset($map[$status]) ? $map[$status] : $map['draft'];
$label = isset($labels[$status]) ? $labels[$status] : $status;
?>
<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $class; ?>">
    <?php echo HTML::chars($label); ?>
</span>
