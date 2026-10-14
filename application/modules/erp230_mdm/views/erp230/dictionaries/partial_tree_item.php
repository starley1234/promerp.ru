<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php
/**
 * Узел дерева типов изделий (рекурсивный партиал).
 *
 * Ожидает: $item — массив ['id', 'display_name', 'children' => [...], 'is_active'?]
 *          (см. Controller_Erp230_Dictionaries::_build_type_tree()).
 */
$item     = isset($item) ? $item : [];
$children = isset($item['children']) ? $item['children'] : [];
$has_kids = ! empty($children);
?>
<li class="tree-item" data-id="<?php echo HTML::chars(Arr::get($item, 'id', '')); ?>">
    <div class="flex items-center justify-between gap-2 px-2 py-1.5 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors group">
        <button type="button"
                class="flex items-center text-left flex-1 min-w-0 <?php echo $has_kids ? 'tree-toggle' : 'pl-5'; ?>"
                <?php if ($has_kids): ?>data-toggle="children-<?php echo HTML::chars(Arr::get($item, 'id', '')); ?>"<?php endif; ?>>
            <?php if ($has_kids): ?>
                <i class="fas fa-chevron-right text-[10px] text-gray-400 mr-2 transition-transform"></i>
            <?php endif; ?>
            <i class="fas fa-folder text-amber-500 mr-2"></i>
            <span class="truncate text-sm text-gray-800 dark:text-gray-200">
                <?php echo HTML::chars(Arr::get($item, 'display_name', '—')); ?>
            </span>
        </button>

        <span class="hidden group-hover:flex items-center gap-1">
            <a href="<?php echo URL::site('erp230_types/create?parent_id='.urlencode(Arr::get($item, 'id', ''))); ?>"
               class="p-1 text-gray-400 hover:text-blue-600" title="Добавить подтип">
                <i class="fas fa-plus text-xs"></i>
            </a>
            <a href="<?php echo URL::site('erp230_type_form/'.urlencode(Arr::get($item, 'id', ''))); ?>"
               class="p-1 text-gray-400 hover:text-emerald-600" title="Редактировать">
                <i class="fas fa-pen text-xs"></i>
            </a>
        </span>
    </div>

    <?php if ($has_kids): ?>
        <ul id="children-<?php echo HTML::chars(Arr::get($item, 'id', '')); ?>" class="hidden ml-4 border-l border-gray-200 dark:border-gray-700 pl-2 space-y-0.5">
            <?php foreach ($children as $child): ?>
                <?php echo View::factory('erp230/dictionaries/partial_tree_item')->set('item', $child); ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</li>
