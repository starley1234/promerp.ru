<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="mx-auto max-w-4xl">

    <?php echo View::factory('erp119/partials/flash')->set('messages', $messages); ?>

    <div class="mb-6">
        <a href="/audit/journal?entity_type=<?php echo urlencode($entity_type); ?>"
           class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400">
            <i class="fas fa-arrow-left mr-1"></i> К ленте по этой сущности
        </a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-2">
            История объекта
            <span class="text-gray-500 dark:text-gray-400 font-mono text-lg"><?php echo HTML::chars($entity_type.' #'.$entity_id); ?></span>
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Записей: <?php echo (int) $total; ?></p>
    </div>

    <?php if (empty($rows)): ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8 text-center">
            <i class="fas fa-history text-3xl text-gray-300 dark:text-gray-600 mb-3"></i>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                По этому объекту записей нет. История ведётся с момента включения журнала.
            </p>
        </div>
    <?php else: ?>
        <ol class="relative border-l border-gray-200 dark:border-gray-700 ml-3">
            <?php foreach ($rows as $row): ?>
                <li class="mb-6 ml-6">
                    <span class="absolute -left-2 flex items-center justify-center w-4 h-4 rounded-full <?php echo $row['severity'] === 'warning' OR $row['severity'] === 'critical' ? 'bg-amber-400' : 'bg-blue-400'; ?>"></span>

                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs <?php echo Erp119_Ui::action_tone($row['action']); ?>">
                                    <?php echo HTML::chars(Erp119_Ui::action_label($row['action'])); ?>
                                </span>
                                <span class="text-sm text-gray-900 dark:text-white">
                                    <?php echo HTML::chars($row['message'] ? $row['message'] : $row['entity_label']); ?>
                                </span>
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap"
                                  title="<?php echo HTML::chars($row['occurred_at']); ?>">
                                <?php echo HTML::chars(date('d.m.Y H:i', strtotime($row['occurred_at']))); ?>
                                (<?php echo HTML::chars(Erp119_Ui::ago($row['occurred_at'])); ?>)
                            </span>
                        </div>

                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                            <?php echo HTML::chars($row['actor_name'] ? $row['actor_name'] : Erp119_Ui::source_label($row['source'])); ?>
                            · <?php echo HTML::chars(Erp119_Ui::source_label($row['source'])); ?>
                            <?php if ($row['actor_ip']): ?> · <?php echo HTML::chars($row['actor_ip']); ?><?php endif; ?>
                            · <a href="/audit/journal/view/<?php echo (int) $row['id']; ?>"
                                 class="text-blue-600 hover:text-blue-700 dark:text-blue-400">запись #<?php echo (int) $row['id']; ?></a>
                        </p>

                        <?php if ( ! empty($row['changes'])): ?>
                            <?php echo View::factory('erp119/partials/changes')->set('changes', $row['changes']); ?>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>

        <?php if ($pages > 1): ?>
            <?php
            $link = function ($page_number) use ($entity_type, $entity_id) {
                return '/audit/journal/entity?'.http_build_query(array(
                    'entity_type' => $entity_type,
                    'entity_id'   => $entity_id,
                    'page'        => $page_number,
                ));
            };
            ?>
            <div class="flex items-center justify-between mt-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Страница <?php echo (int) $page; ?> из <?php echo (int) $pages; ?></p>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo HTML::chars($link($page - 1)); ?>"
                           class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">Назад</a>
                    <?php endif; ?>
                    <?php if ($page < $pages): ?>
                        <a href="<?php echo HTML::chars($link($page + 1)); ?>"
                           class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">Вперёд</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
