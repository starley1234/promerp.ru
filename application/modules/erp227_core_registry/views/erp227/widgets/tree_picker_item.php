<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php
/**
 * Узел выбора объекта в дереве (рекурсивный партиал виджета tree_picker).
 *
 * Ожидает: $item (объект/массив с id, name, code, children), $level (int).
 * См. application/modules/erp227_core_registry/views/erp227/widgets/tree_picker.php.
 */
$level    = isset($level) ? (int) $level : 0;
$item     = isset($item) ? $item : NULL;
$children = $item ? (array) $item->children->find_all() : [];

$id   = $item ? $item->id : 0;
$code = $item ? (string) $item->code : '';
$name = $item ? (string) $item->name : '';
?>
<li class="tree-node" data-id="<?php echo (int) $id; ?>" data-margin-indent="<?php echo (int) $level * 16; ?>">
    <label class="flex items-center gap-2 px-2 py-1 rounded hover:bg-blue-50 dark:hover:bg-blue-900/20 cursor-pointer">
        <?php if ($children): ?>
            <i class="fas fa-caret-right text-[10px] text-gray-400 tree-caret"></i>
        <?php else: ?>
            <span class="w-3"></span>
        <?php endif; ?>
        <input type="checkbox" class="tree-select form-checkbox" value="<?php echo (int) $id; ?>"
               data-code="<?php echo HTML::chars($code); ?>" data-name="<?php echo HTML::chars($name); ?>">
        <code class="font-mono text-[11px] text-gray-500"><?php echo HTML::chars($code); ?></code>
        <span class="text-sm text-gray-800 dark:text-gray-200 truncate"><?php echo HTML::chars($name); ?></span>
    </label>

    <?php if ($children): ?>
        <ul class="tree-children hidden">
            <?php foreach ($children as $child): ?>
                <?php echo View::factory('erp227/widgets/tree_picker_item')
                    ->set('item', $child)
                    ->set('level', $level + 1); ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</li>
