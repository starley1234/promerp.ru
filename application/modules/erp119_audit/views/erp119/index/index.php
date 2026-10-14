<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <?php echo View::factory('erp119/partials/flash')->set('messages', $messages); ?>

    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Журнал действий</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Кто, когда и что изменил: правки, смены статусов, удаления, выгрузки
            </p>
        </div>
        <div class="flex gap-2">
            <a href="/audit/journal"
               class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                <i class="fas fa-stream mr-2"></i> Лента
            </a>
            <?php if ( ! empty($stats['total'])): ?>
                <a href="/audit/journal/export"
                   class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg">
                    <i class="fas fa-file-csv mr-2"></i> Выгрузить CSV
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $critical = $stats['severity']['warning'] + $stats['severity']['critical'];
    $tiles = array(
        array('label' => 'За 24 часа', 'value' => $stats['day'], 'icon' => 'fas fa-clock', 'tone' => 'blue'),
        array('label' => 'За неделю', 'value' => $stats['week'], 'icon' => 'fas fa-calendar-week', 'tone' => 'indigo'),
        array('label' => 'За '.$stats['period_days'].' дн.', 'value' => $stats['period'], 'icon' => 'fas fa-calendar-alt', 'tone' => 'gray'),
        array('label' => 'Всего записей', 'value' => $stats['total'], 'icon' => 'fas fa-database', 'tone' => 'gray'),
        array('label' => 'Важных за период', 'value' => $critical, 'icon' => 'fas fa-exclamation-triangle', 'tone' => $critical ? 'amber' : 'gray'),
        array('label' => 'Пользователей в журнале', 'value' => $stats['actors'], 'icon' => 'fas fa-users', 'tone' => 'gray'),
    );
    ?>

    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
        <?php foreach ($tiles as $tile): ?>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400"><?php echo HTML::chars($tile['label']); ?></p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1"><?php echo (int) $tile['value']; ?></p>
                    </div>
                    <i class="<?php echo $tile['icon']; ?> text-xl text-<?php echo $tile['tone']; ?>-500"></i>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($stats['total'])): ?>
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-xl p-6 mb-6">
            <h2 class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-2">Журнал пока пуст</h2>
            <p class="text-sm text-blue-800 dark:text-blue-200">
                Записи появляются двумя путями: сервисы модулей пишут их через
                <code class="font-mono">Service_Erp119_Journal::change()</code>, а события шины
                (<code class="font-mono">erp_208_events</code>) дочитываются задачей
                <code class="font-mono">bin/console erp119:sync</code>.
            </p>
        </div>
    <?php endif; ?>

    <?php
    $max = max(1, $activity ? max($activity) : 1);
    $days_labels = array('пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'вс');
    ?>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6">
        <div class="xl:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Активность за 14 дней</h2>

            <?php if (empty($activity)): ?>
                <p class="text-sm text-gray-500 dark:text-gray-400">Нет данных за период.</p>
            <?php else: ?>
                <div class="flex items-end gap-1 h-32">
                    <?php foreach ($activity as $day => $total): ?>
                        <div class="flex-1 flex flex-col items-center justify-end h-full group">
                            <span class="text-xs text-gray-500 dark:text-gray-400 opacity-0 group-hover:opacity-100"><?php echo (int) $total; ?></span>
                            <div class="w-full bg-blue-500 hover:bg-blue-600 rounded-t"
                                 data-progress-height="<?php echo max(2, (int) round($total / $max * 100)); ?>"
                                 title="<?php echo HTML::chars($day.': '.$total); ?>"></div>
                            <span class="text-[10px] text-gray-400 mt-1"><?php echo date('d.m', strtotime($day)); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Важность за период</h2>

            <?php
            $severity_total = array_sum($stats['severity']);
            $rows = array(
                array('info', 'Обычные'),
                array('notice', 'Заметные'),
                array('warning', 'Важные'),
                array('critical', 'Критичные'),
            );
            ?>

            <ul class="space-y-3">
                <?php foreach ($rows as $row): ?>
                    <?php
                    $severity = $row[0];
                    $count    = (int) $stats['severity'][$severity];
                    $share    = $severity_total > 0 ? round($count / $severity_total * 100) : 0;
                    ?>
                    <li>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs <?php echo Erp119_Ui::severity_tone($severity); ?>">
                                <?php echo HTML::chars($row[1]); ?>
                            </span>
                            <span class="text-gray-600 dark:text-gray-300"><?php echo $count; ?> <span class="text-gray-400">(<?php echo $share; ?>%)</span></span>
                        </div>
                        <div class="h-1.5 bg-gray-100 dark:bg-gray-700 rounded">
                            <div class="h-1.5 bg-blue-500 rounded" data-progress="<?php echo $share; ?>"></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <p class="text-xs text-gray-500 dark:text-gray-400 mt-4">
                Последняя запись: <?php echo $stats['last_at'] ? HTML::chars(date('d.m.Y H:i', strtotime($stats['last_at']))) : '—'; ?>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="xl:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Последние записи</h2>
                <a href="/audit/journal" class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400">Вся лента</a>
            </div>

            <?php if (empty($recent)): ?>
                <p class="px-5 py-6 text-sm text-gray-500 dark:text-gray-400">Записей нет.</p>
            <?php else: ?>
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    <?php foreach ($recent as $row): ?>
                        <li class="px-5 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm text-gray-900 dark:text-white">
                                        <span class="inline-flex px-2 py-0.5 rounded text-xs mr-2 <?php echo Erp119_Ui::action_tone($row['action']); ?>">
                                            <?php echo HTML::chars(Erp119_Ui::action_label($row['action'])); ?>
                                        </span>
                                        <?php echo HTML::chars($row['entity_label'] ? $row['entity_label'] : $journal->label_for($row['module'], $row['entity_type'])); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        <?php echo HTML::chars($row['actor_name'] ? $row['actor_name'] : Erp119_Ui::source_label($row['source'])); ?>
                                        · <?php echo date('d.m.Y H:i', strtotime($row['occurred_at'])); ?>
                                        · <?php echo HTML::chars($journal->label_for($row['module'], $row['entity_type'])); ?>
                                    </p>
                                </div>
                                <a href="/audit/journal/view/<?php echo (int) $row['id']; ?>"
                                   class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 whitespace-nowrap">Открыть</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="space-y-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Кто активнее</h2>

                <?php if (empty($stats['top_actors'])): ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Нет данных.</p>
                <?php else: ?>
                    <ul class="space-y-2">
                        <?php foreach ($stats['top_actors'] as $actor): ?>
                            <li class="flex items-center justify-between text-sm">
                                <a href="/audit/journal?actor_id=<?php echo (int) $actor['actor_id']; ?>"
                                   class="text-gray-800 dark:text-gray-200 hover:text-blue-600"><?php echo HTML::chars($actor['name']); ?></a>
                                <span class="text-gray-500 dark:text-gray-400"><?php echo (int) $actor['total']; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Что меняют</h2>

                <?php if (empty($stats['top_entities'])): ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Нет данных.</p>
                <?php else: ?>
                    <ul class="space-y-2">
                        <?php foreach ($stats['top_entities'] as $entity): ?>
                            <li class="flex items-center justify-between text-sm">
                                <a href="/audit/journal?module=<?php echo urlencode($entity['module']); ?>&entity_type=<?php echo urlencode($entity['entity_type']); ?>"
                                   class="text-gray-800 dark:text-gray-200 hover:text-blue-600"><?php echo HTML::chars($entity['label']); ?></a>
                                <span class="text-gray-500 dark:text-gray-400"><?php echo (int) $entity['total']; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Действия</h2>

                <?php if (empty($stats['top_actions'])): ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Нет данных.</p>
                <?php else: ?>
                    <ul class="space-y-2">
                        <?php foreach ($stats['top_actions'] as $action): ?>
                            <li class="flex items-center justify-between text-sm">
                                <a href="/audit/journal?action=<?php echo urlencode($action['value']); ?>"
                                   class="inline-flex px-2 py-0.5 rounded text-xs <?php echo Erp119_Ui::action_tone($action['value']); ?>">
                                    <?php echo HTML::chars(Erp119_Ui::action_label($action['value'])); ?>
                                </a>
                                <span class="text-gray-500 dark:text-gray-400"><?php echo (int) $action['total']; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
