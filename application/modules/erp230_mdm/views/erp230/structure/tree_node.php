<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php
/** Узел дерева структуры (рекурсивный партиал).
 *  Ожидает: $node = ['id','master_code','name','qty','children' => [...]] */
$node     = isset($node) ? (array) $node : [];
$children = isset($node['children']) ? (array) $node['children'] : [];
?>
<li>
    <div class="flex items-center gap-2 px-2 py-1 rounded hover:bg-gray-50 dark:hover:bg-gray-700/30">
        <i class="fas fa-<?php echo $children ? 'caret-down' : 'circle'; ?> text-[10px] text-gray-400 w-3"></i>
        <code class="font-mono text-xs text-gray-500"><?php echo HTML::chars(Arr::get($node, 'master_code', '')); ?></code>
        <span class="text-gray-800 dark:text-gray-200"><?php echo HTML::chars(Arr::get($node, 'name', '—')); ?></span>
        <?php if (Arr::get($node, 'qty') !== NULL): ?>
            <span class="ml-auto text-xs text-gray-500">×<?php echo (float) Arr::get($node, 'qty'); ?></span>
        <?php endif; ?>
    </div>
    <?php if ($children): ?>
        <ul class="ml-5 border-l border-gray-200 dark:border-gray-700 pl-3 space-y-1 mt-1">
            <?php foreach ($children as $child): ?>
                <?php echo View::factory('erp230/structure/tree_node')->set('node', $child); ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</li>
