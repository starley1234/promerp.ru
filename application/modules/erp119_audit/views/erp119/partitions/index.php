<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <?php echo View::factory('erp119/partials/flash')->set('messages', $messages); ?>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Секции журнала</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Журнал секционирован по месяцам: удаление по сроку хранения — это DROP секции,
            а не построчный DELETE. Месяцы вперёд создаются автоматически
            (<?php echo (int) $months_ahead; ?> мес.), срок хранения:
            <?php echo $retention_months ? (int) $retention_months.' мес.' : 'бессрочно'; ?>.
        </p>
    </div>

    <div class="flex flex-wrap gap-2 mb-6">
        <form method="post" action="/audit/partitions/rotate">
            <input type="hidden" name="__html" value="1">
            <input type="hidden" name="mode" value="ensure">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg">
                <i class="fas fa-plus mr-2"></i> Создать месяцы вперёд
            </button>
        </form>

        <?php if ($backlog): ?>
            <form method="post" action="/audit/partitions/rotate">
                <input type="hidden" name="__html" value="1">
                <input type="hidden" name="mode" value="attach">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium rounded-lg">
                    <i class="fas fa-layer-group mr-2"></i> Разобрать DEFAULT (<?php echo count($backlog); ?> мес.)
                </button>
            </form>
        <?php endif; ?>

        <?php if ($expired): ?>
            <form method="post" action="/audit/partitions/rotate">
                <input type="hidden" name="__html" value="1">
                <input type="hidden" name="mode" value="drop">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg"
                        data-confirm="Удалить секции старше срока хранения? Действие необратимо.">
                    <i class="fas fa-trash mr-2"></i> Удалить истёкшие (<?php echo count($expired); ?>)
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($backlog): ?>
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl p-5 mb-6">
            <h2 class="text-sm font-semibold text-amber-900 dark:text-amber-100 mb-2">
                <i class="fas fa-exclamation-triangle mr-2"></i> Записи в DEFAULT-секции
            </h2>
            <p class="text-sm text-amber-800 dark:text-amber-200 mb-3">
                Эти записи попали в DEFAULT, потому что месячная секция не была создана (обычно — простой сервера).
                Разберите DEFAULT: месяцы будут созданы, записи переедут в свои секции.
            </p>
            <ul class="text-sm text-amber-900 dark:text-amber-100">
                <?php foreach ($backlog as $month => $total): ?>
                    <li><?php echo HTML::chars($month); ?> — <?php echo (int) $total; ?> записей</li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                    <caption class="sr-only">Таблица данных</caption>
                <thead class="bg-gray-50 dark:bg-gray-900/40 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left">Секция</th>
                        <th scope="col" class="px-4 py-3 text-left">Месяц</th>
                        <th scope="col" class="px-4 py-3 text-right">Записей</th>
                        <th scope="col" class="px-4 py-3 text-right">Размер</th>
                        <th scope="col" class="px-4 py-3 text-left">Границы</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    <?php foreach ($partitions as $partition): ?>
                        <tr class="<?php echo $partition['is_default'] ? 'bg-amber-50/60 dark:bg-amber-900/10' : ''; ?>">
                            <td class="px-4 py-3 font-mono text-xs text-gray-800 dark:text-gray-200">
                                <?php echo HTML::chars($partition['partition']); ?>
                                <?php if ($partition['is_default']): ?>
                                    <span class="ml-1 inline-flex px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-800">DEFAULT</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300"><?php echo HTML::chars($partition['month'] ? $partition['month'] : '—'); ?></td>
                            <td class="px-4 py-3 text-right text-gray-900 dark:text-white"><?php echo (int) $partition['rows']; ?></td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300"><?php echo HTML::chars($partition['size']); ?></td>
                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($partition['bound']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($expired): ?>
        <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Старше срока хранения</h2>
            <ul class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                <?php foreach ($expired as $partition): ?>
                    <li class="font-mono text-xs">
                        <?php echo HTML::chars($partition['partition']); ?> —
                        <?php echo (int) $partition['rows']; ?> записей, <?php echo HTML::chars($partition['size']); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="mt-6 text-xs text-gray-500 dark:text-gray-400">
        Из консоли: <code class="font-mono">bin/console erp119:partitions --attach-default --drop-expired</code>.
        Регламент раз в сутки — см. README модуля.
    </div>
</div>
