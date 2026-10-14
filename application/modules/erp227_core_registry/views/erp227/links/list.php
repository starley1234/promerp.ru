<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php
/**
 * Список связей объекта реестра (erp227).
 *
 * Ожидает: $source (Model_Erp227_Object), $links (ORM-коллекция Model_Erp227_Link),
 *          $pagination (Pagination).
 * См. Controller_Erp227_Links::action_for_object().
 */
$links      = isset($links) ? $links : [];
$pagination = isset($pagination) ? $pagination : NULL;
?>
<div>
    <div class="flex items-center justify-between gap-4 mb-5">
        <div>
            <a href="<?php echo URL::site('registry/objects'); ?>" class="text-sm text-blue-600 hover:underline">
                <i class="fas fa-arrow-left mr-1"></i> Реестр объектов
            </a>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mt-1">
                Связи: <?php echo HTML::chars($source->name); ?>
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Всего связей: <?php echo $pagination ? (int) $pagination->total_items : count($links); ?>
            </p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <table class="w-full text-left text-sm">
                    <caption class="sr-only">Таблица данных</caption>
            <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-500 uppercase text-[10px] tracking-wider">
                <tr>
                    <th scope="col" class="px-4 py-3 font-semibold">Целевой объект</th>
                    <th scope="col" class="px-4 py-3 font-semibold">Тип связи</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-right">Порядок</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php if (count($links)): ?>
                    <?php foreach ($links as $link): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-4 py-3">
                                <?php $target = $link->target; ?>
                                <span class="text-gray-800 dark:text-gray-200">
                                    <?php echo HTML::chars($target->loaded() ? $target->name : 'объект #'.$link->target_id); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                    <?php echo HTML::chars($link->type); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-xs text-gray-400"><?php echo (int) $link->sort_order; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="px-4 py-8 text-center text-gray-400 italic">Связей нет</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagination): ?>
        <div class="mt-4"><?php echo $pagination; ?></div>
    <?php endif; ?>
</div>
