<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="mx-auto max-w-5xl">

    <?php echo View::factory('erp119/partials/flash')->set('messages', $messages); ?>

    <div class="mb-6">
        <a href="/audit/journal" class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400">
            <i class="fas fa-arrow-left mr-1"></i> К ленте
        </a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-2">
            <?php echo HTML::chars($row['message'] ? $row['message'] : Erp119_Ui::action_label($row['action'])); ?>
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Запись #<?php echo (int) $row['id']; ?> ·
            <?php echo HTML::chars(date('d.m.Y H:i:s', strtotime($row['occurred_at']))); ?>
        </p>
    </div>

    <div class="flex flex-wrap gap-2 mb-6">
        <span class="inline-flex px-3 py-1 rounded-lg text-sm <?php echo Erp119_Ui::action_tone($row['action']); ?>">
            <?php echo HTML::chars(Erp119_Ui::action_label($row['action'])); ?>
        </span>
        <span class="inline-flex px-3 py-1 rounded-lg text-sm <?php echo Erp119_Ui::severity_tone($row['severity']); ?>">
            <?php echo HTML::chars(Erp119_Ui::severity_label($row['severity'])); ?>
        </span>
        <span class="inline-flex px-3 py-1 rounded-lg text-sm bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200">
            <?php echo HTML::chars($journal->label_for($row['module'], $row['entity_type'])); ?>
        </span>
        <?php if ($row['entity_id'] !== ''): ?>
            <a href="/audit/journal/entity?entity_type=<?php echo urlencode($row['entity_type']); ?>&entity_id=<?php echo urlencode($row['entity_id']); ?>"
               class="inline-flex px-3 py-1 rounded-lg text-sm bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200 hover:bg-blue-100">
                <i class="fas fa-history mr-2 mt-0.5"></i> История объекта #<?php echo HTML::chars($row['entity_id']); ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Изменения</h2>

            <?php echo View::factory('erp119/partials/changes')->set('changes', $row['changes']); ?>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Кто и откуда</h2>

            <?php
            $meta = array(
                'Пользователь' => $row['actor_name'] ? $row['actor_name'].' (#'.$row['actor_id'].')' : 'система',
                'Источник'     => Erp119_Ui::source_label($row['source']),
                'IP'           => $row['actor_ip'] ? $row['actor_ip'] : '—',
                'Маршрут'      => $row['route'] ? $row['route'] : '—',
                'Модуль'       => $row['module'],
                'Сущность'     => $row['entity_type'].($row['entity_id'] !== '' ? ' #'.$row['entity_id'] : ''),
                'Метка'        => $row['entity_label'] ? $row['entity_label'] : '—',
                'Запрос'       => $row['request_id'] ? $row['request_id'] : '—',
            );
            ?>

            <dl class="space-y-2 text-sm">
                <?php foreach ($meta as $label => $value): ?>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400 whitespace-nowrap"><?php echo HTML::chars($label); ?></dt>
                        <dd class="text-gray-900 dark:text-white text-right break-all"><?php echo HTML::chars($value); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>

            <?php if ($row['request_id']): ?>
                <a href="/audit/journal?request_id=<?php echo urlencode($row['request_id']); ?>"
                   class="inline-flex items-center mt-4 text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400">
                    <i class="fas fa-link mr-1"></i> Все записи этого запроса
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( ! empty($row['context'])): ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 mb-6">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Контекст</h2>
            <pre class="text-xs bg-gray-50 dark:bg-gray-900/60 rounded-lg p-4 overflow-x-auto text-gray-700 dark:text-gray-300"><?php echo HTML::chars(json_encode($row['context'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre>
        </div>
    <?php endif; ?>

    <?php if ( ! empty($related)): ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                В том же запросе (<?php echo count($related); ?>)
            </h2>

            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php foreach ($related as $item): ?>
                    <li class="px-5 py-3 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm text-gray-900 dark:text-white">
                                <?php echo HTML::chars($item['message'] ? $item['message'] : Erp119_Ui::action_label($item['action'])); ?>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                <?php echo HTML::chars(date('d.m.Y H:i:s', strtotime($item['occurred_at']))); ?>
                                · <?php echo HTML::chars($journal->label_for($item['module'], $item['entity_type'])); ?>
                            </p>
                        </div>
                        <a href="/audit/journal/view/<?php echo (int) $item['id']; ?>"
                           class="text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 whitespace-nowrap">Открыть</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
