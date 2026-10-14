<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-5">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
            <i class="fas fa-calendar-alt mr-1 text-blue-600"></i> Планирование
        </h3>
        <a href="/planning" class="text-xs text-blue-600 hover:text-blue-800">Доска</a>
    </div>

    <div class="grid grid-cols-3 gap-3 text-center mb-3">
        <div>
            <div class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo (int) $stats['programs']; ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400">программ</div>
        </div>
        <div>
            <div class="text-lg font-semibold <?php echo $summary['deficit'] > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white'; ?>">
                <?php echo (int) $summary['deficit']; ?>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">дефицитов</div>
        </div>
        <div>
            <div class="text-lg font-semibold text-yellow-600"><?php echo (int) $proposal_stats['new']; ?></div>
            <div class="text-xs text-gray-500 dark:text-gray-400">к решению</div>
        </div>
    </div>

    <?php if ( ! empty($program)): ?>
        <div class="text-xs text-gray-500 dark:text-gray-400">
            Последний расчёт:
            <a href="/planning/programs/view/<?php echo (int) $program->id; ?>" class="text-blue-600 hover:text-blue-800 font-mono">
                <?php echo HTML::chars($program->number); ?>
            </a>
            <?php echo $program->run_at ? 'от '.HTML::chars(date('d.m.Y H:i', strtotime($program->run_at))) : ''; ?>
        </div>
    <?php else: ?>
        <div class="text-xs text-gray-500 dark:text-gray-400">Расчётов ещё не было.</div>
    <?php endif; ?>

    <?php if ( ! empty($deficits)): ?>
        <ul class="mt-3 divide-y divide-gray-200 dark:divide-gray-700 text-xs">
            <?php foreach ($deficits as $row): ?>
                <li class="py-2 flex items-center justify-between">
                    <span class="text-gray-900 dark:text-white"><?php echo HTML::chars($row->name); ?></span>
                    <span class="text-red-600 dark:text-red-400 whitespace-nowrap ml-2">
                        -<?php echo number_format((float) $row->deficit_qty, 3, '.', ' '); ?>
                        <?php echo HTML::chars($row->uom_code); ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
        <a href="/planning/deficits" class="block mt-2 text-xs text-blue-600 hover:text-blue-800">Все дефициты →</a>
    <?php endif; ?>
</div>
