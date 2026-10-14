<?php defined('SYSPATH') or die('No direct script access.');
$tabs = isset($tabs) ? (array) $tabs : array();
$label = isset($label) ? $label : 'Разделы';
?>
<?php if ($tabs): ?><nav aria-label="<?php echo HTML::chars($label); ?>" class="border-b border-gray-200 dark:border-gray-700"><div class="-mb-px flex gap-5 overflow-x-auto">
<?php foreach ($tabs as $tab): $active = !empty($tab['active']); ?><a href="<?php echo HTML::chars(isset($tab['href']) ? $tab['href'] : '#'); ?>" class="whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium <?php echo $active ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'; ?>" <?php echo $active ? 'aria-current="page"' : ''; ?>><?php echo HTML::chars(isset($tab['label']) ? $tab['label'] : ''); ?><?php if (isset($tab['count'])): ?> <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs dark:bg-gray-700"><?php echo HTML::chars($tab['count']); ?></span><?php endif; ?></a><?php endforeach; ?></div></nav><?php endif; ?>
