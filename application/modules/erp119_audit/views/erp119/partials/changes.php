<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php if (empty($changes)): ?>
    <p class="text-sm text-gray-500 dark:text-gray-400">Без изменения значений.</p>
<?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
                    <caption class="sr-only">Таблица данных</caption>
            <thead>
                <tr class="text-left text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <th scope="col" class="py-2 pr-4">Поле</th>
                    <th scope="col" class="py-2 pr-4">Было</th>
                    <th scope="col" class="py-2">Стало</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php foreach ($changes as $change): ?>
                    <tr>
                        <td class="py-2 pr-4 font-mono text-xs text-gray-700 dark:text-gray-300"><?php echo HTML::chars($change['field']); ?></td>
                        <td class="py-2 pr-4 text-red-700 dark:text-red-300 break-all"><?php echo HTML::chars(Erp119_Ui::value($change['old'])); ?></td>
                        <td class="py-2 text-green-700 dark:text-green-300 break-all"><?php echo HTML::chars(Erp119_Ui::value($change['new'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
