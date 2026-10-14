<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php
/**
 * Виджет «Уровень доверия» объекта MDM.
 *
 * Ожидает: $score (0–100), $is_dirty (boolean), $object_id.
 * См. Controller_Erp230_Widget::action_trust_score().
 */
$score     = isset($score) ? (int) $score : 0;
$is_dirty  = ! empty($is_dirty);
$object_id = isset($object_id) ? $object_id : NULL;

if ($score >= 80)      { $level = 'высокий'; $class = 'emerald'; }
elseif ($score >= 50)  { $level = 'средний'; $class = 'amber'; }
else                   { $level = 'низкий';  $class = 'red'; }
?>
<div class="inline-flex items-center gap-3 px-3 py-2 rounded-lg border border-<?php echo $class; ?>-200 dark:border-<?php echo $class; ?>-800 bg-<?php echo $class; ?>-50 dark:bg-<?php echo $class; ?>-900/20">
    <div class="relative w-12 h-12 shrink-0">
        <svg class="w-12 h-12 -rotate-90" viewBox="0 0 36 36">
            <circle cx="18" cy="18" r="15.5" fill="none" stroke="currentColor" class="text-gray-200 dark:text-gray-700" stroke-width="3"></circle>
            <circle cx="18" cy="18" r="15.5" fill="none" stroke="currentColor"
                    class="text-<?php echo $class; ?>-500" stroke-width="3" stroke-linecap="round"
                    stroke-dasharray="<?php echo $score; ?>, 100"></circle>
        </svg>
        <span class="absolute inset-0 flex items-center justify-center text-xs font-bold text-gray-800 dark:text-gray-100">
            <?php echo $score; ?>
        </span>
    </div>
    <div class="leading-tight">
        <p class="text-xs text-gray-500 dark:text-gray-400">Доверие к данным</p>
        <p class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo $level; ?></p>
        <?php if ($is_dirty): ?>
            <p class="text-[11px] text-amber-600 dark:text-amber-400 mt-0.5">
                <i class="fas fa-exclamation-triangle mr-1"></i> запись требует консолидации
            </p>
        <?php endif; ?>
    </div>
    <?php if ($object_id): ?>
        <a href="<?php echo URL::site('erp230_objects/view/'.$object_id); ?>"
           class="ml-1 text-xs text-blue-600 dark:text-blue-400 hover:underline">карточка</a>
    <?php endif; ?>
</div>
