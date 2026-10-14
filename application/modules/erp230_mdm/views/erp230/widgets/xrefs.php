<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php
/**
 * Виджет «Кросс-референсы»: связи объекта MDM с внешними системами (PLM/ERP).
 *
 * Ожидает: $xrefs — список ['source_id', 'remote_id', 'source_name'].
 * См. Controller_Erp230_Widget::action_xrefs().
 */
$xrefs = isset($xrefs) ? (array) $xrefs : [];
?>
<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex items-center">
        <i class="fas fa-link text-gray-400 mr-2"></i>
        <h4 class="font-semibold text-sm text-gray-900 dark:text-white">Внешние идентификаторы</h4>
    </div>

    <?php if ($xrefs): ?>
        <ul class="divide-y divide-gray-100 dark:divide-gray-700">
            <?php foreach ($xrefs as $xref): ?>
                <li class="px-4 py-2.5 flex items-center justify-between text-sm">
                    <span class="text-gray-500 dark:text-gray-400">
                        <?php echo HTML::chars(Arr::get($xref, 'source_name', 'источник #'.Arr::get($xref, 'source_id', '?'))); ?>
                    </span>
                    <code class="font-mono text-xs text-gray-800 dark:text-gray-200">
                        <?php echo HTML::chars(Arr::get($xref, 'remote_id', '')); ?>
                    </code>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="px-4 py-6 text-center text-sm text-gray-400 italic">
            Связей с внешними системами нет
        </p>
    <?php endif; ?>
</div>
