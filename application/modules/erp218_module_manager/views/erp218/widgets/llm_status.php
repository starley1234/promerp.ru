<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php
/**
 * Виджет статуса LLM-анализа модулей (реестр erp218).
 *
 * Ожидает: $stats = ['pending' => int, 'completed' => int, 'failed' => int]
 *          (см. Controller_Erp218_Widget::action_llm_status()).
 */
$stats   = isset($stats) ? $stats : [];
$pending = (int) Arr::get($stats, 'pending', 0);
$done    = (int) Arr::get($stats, 'completed', 0);
$failed  = (int) Arr::get($stats, 'failed', 0);
$total   = $pending + $done + $failed;
$percent = $total > 0 ? (int) round($done / $total * 100) : 0;
?>
<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 card-shadow p-5 mb-6">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center">
            <div class="w-10 h-10 bg-violet-50 dark:bg-violet-900/30 rounded-lg flex items-center justify-center text-violet-600 dark:text-violet-400 mr-3">
                <i class="fas fa-brain"></i>
            </div>
            <div>
                <h3 class="font-bold text-gray-900 dark:text-white">LLM-анализ модулей</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Автоматическое описание и оценка качества кода</p>
            </div>
        </div>
        <span class="text-xs text-gray-400">
            всего задач: <?php echo $total; ?>
        </span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3">
            <p class="text-xs font-medium text-amber-700 dark:text-amber-300 uppercase tracking-wide">В очереди</p>
            <p class="text-2xl font-bold text-amber-800 dark:text-amber-200 mt-1"><?php echo $pending; ?></p>
        </div>

        <div class="rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 px-4 py-3">
            <p class="text-xs font-medium text-emerald-700 dark:text-emerald-300 uppercase tracking-wide">Обработано</p>
            <p class="text-2xl font-bold text-emerald-800 dark:text-emerald-200 mt-1"><?php echo $done; ?></p>
        </div>

        <div class="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-4 py-3">
            <p class="text-xs font-medium text-red-700 dark:text-red-300 uppercase tracking-wide">Ошибки</p>
            <p class="text-2xl font-bold text-red-800 dark:text-red-200 mt-1"><?php echo $failed; ?></p>
        </div>
    </div>

    <div class="mt-4">
        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
            <span>Готовность анализа</span>
            <span><?php echo $percent; ?> %</span>
        </div>
        <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5">
            <div class="bg-violet-500 h-1.5 rounded-full" data-progress="<?php echo $percent; ?>"></div>
        </div>
        <?php if ($pending > 0): ?>
            <p class="text-xs text-gray-400 mt-2">
                <i class="fas fa-clock mr-1"></i>
                Запустить разбор: <code class="font-mono">bin/console erp218:llm:process</code>
            </p>
        <?php endif; ?>
    </div>
</div>
