<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php
/**
 * Дерево структуры изделия (BOM) — разузлование вниз.
 *
 * Ожидает: $object, $name, $type, $depth, $nodes, $tree, $children, $can_edit, $link_types.
 * См. Controller_Erp230_Structure::action_tree().
 */
$name       = isset($name) ? $name : (string) $object->master_code;
$type       = isset($type) ? $type : 'ebom';
$depth      = isset($depth) ? (int) $depth : 1;
$nodes      = isset($nodes) ? (int) $nodes : 0;
$tree       = isset($tree) ? (array) $tree : [];
$children   = isset($children) ? $children : [];
$can_edit   = ! empty($can_edit);
$link_types = isset($link_types) ? (array) $link_types : [];
?>
<div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-5">
        <div>
            <a href="<?php echo URL::site('erp230_objects/view/'.$object->id); ?>" class="text-sm text-blue-600 hover:underline">
                <i class="fas fa-arrow-left mr-1"></i> Карточка объекта
            </a>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mt-1">
                Структура: <?php echo HTML::chars($name); ?>
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Код <?php echo HTML::chars($object->master_code); ?> ·
                тип связи <span class="font-mono"><?php echo HTML::chars($type); ?></span> ·
                узлов: <?php echo $nodes; ?> · глубина: <?php echo $depth; ?>
            </p>
        </div>

        <form method="get" class="flex items-end gap-2">
            <div>
                <label class="block text-xs text-gray-500 mb-1" for="type">Тип связи</label>
                <select name="type" id="type" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm px-2 py-1.5">
                    <?php foreach ($link_types as $code => $title): ?>
                        <option value="<?php echo HTML::chars($code); ?>" <?php echo $code === $type ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1" for="depth">Глубина</label>
                <input type="number" min="1" max="10" name="depth" id="depth" value="<?php echo $depth; ?>"
                       class="w-20 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm px-2 py-1.5">
            </div>
            <button type="submit" class="px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm">Показать</button>
        </form>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <?php if ($tree): ?>
            <ul class="space-y-1 text-sm">
                <?php foreach ($tree as $node): ?>
                    <?php echo View::factory('erp230/structure/tree_node')->set('node', $node); ?>
                <?php endforeach; ?>
            </ul>
        <?php elseif (count($children)): ?>
            <p class="text-sm text-gray-500 mb-3">Прямые компоненты (по связям первого уровня):</p>
            <ul class="space-y-1 text-sm">
                <?php foreach ($children as $child): ?>
                    <li class="flex items-center gap-2">
                        <i class="fas fa-cube text-gray-400"></i>
                        <code class="font-mono text-xs"><?php echo HTML::chars($child->master_code); ?></code>
                        <span><?php echo HTML::chars($child->display_name()); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="py-8 text-center text-gray-400 italic">Структура не заполнена</p>
        <?php endif; ?>
    </div>
</div>
