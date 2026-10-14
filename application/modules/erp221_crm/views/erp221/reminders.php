<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Напоминания: задачи со сроком, просроченные сделки, застой и близкие сроки.
 *
 * Переменные: agenda, users, can_run, messages.
 *
 * Отправка идёт через центр уведомлений (erp217) и только тем, у кого есть
 * подписка; если подписок нет — список всё равно виден здесь.
 */
$money = function ($value) { return number_format((float) $value, 0, '.', ' '); };
?>
<div>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Напоминания</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Что требует внимания сегодня: задачи со сроком, сделки с прошедшим сроком и те, где давно не было движения.
            </p>
        </div>
        <?php if ($can_run): ?>
            <form method="post" action="/crm/reminders_run" class="flex flex-wrap items-end gap-2">
                <div class="space-y-1">
                    <label for="rem_user" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Кому</label>
                    <select id="rem_user" name="user_id" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">Всем, у кого есть задачи</option>
                        <?php foreach ($users as $id => $name): ?>
                            <option value="<?php echo (int) $id; ?>"><?php echo HTML::chars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Разослать сводку</button>
            </form>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp221/flash')->set('messages', $messages); ?>

    <div class="grid gap-6 lg:grid-cols-2">

        <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Задачи со сроком</h2>
            </header>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php if ( ! $agenda['tasks']): ?><p class="px-5 py-5 text-sm text-gray-500 dark:text-gray-400">Незакрытых задач нет.</p><?php endif; ?>
                <?php foreach ($agenda['tasks'] as $task): ?>
                    <div class="flex items-start justify-between gap-3 px-5 py-3 text-sm">
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white"><?php echo HTML::chars($task['subject'] ? $task['subject'] : 'Задача'); ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                <?php if ($task['deal_id']): ?><a href="/crm/deal/<?php echo (int) $task['deal_id']; ?>" class="text-blue-600 hover:underline dark:text-blue-400"><?php echo HTML::chars($task['deal_number']); ?></a> · <?php endif; ?>
                                <?php echo HTML::chars($task['scheduled_at']); ?>
                            </div>
                        </div>
                        <?php if ($task['is_overdue']): ?><?php echo Component::render('badge', array('label' => 'Просрочено', 'state' => 'bad')); ?><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Сделки с прошедшим сроком</h2>
            </header>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php if ( ! $agenda['overdue_deals']): ?><p class="px-5 py-5 text-sm text-gray-500 dark:text-gray-400">Просроченных сделок нет.</p><?php endif; ?>
                <?php foreach ($agenda['overdue_deals'] as $deal): ?>
                    <div class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <div>
                            <a href="/crm/deal/<?php echo (int) $deal['id']; ?>" class="font-medium text-blue-600 hover:underline dark:text-blue-400"><?php echo HTML::chars($deal['title']); ?></a>
                            <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($deal['client']['name']); ?> · срок <?php echo HTML::chars($deal['deadline']); ?></div>
                        </div>
                        <div class="text-right font-semibold text-gray-900 dark:text-white"><?php echo $money($deal['amount']); ?> ₽</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Сделки без движения</h2>
            </header>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php if ( ! $agenda['stale_deals']): ?><p class="px-5 py-5 text-sm text-gray-500 dark:text-gray-400">Застоя нет — все сделки двигаются.</p><?php endif; ?>
                <?php foreach ($agenda['stale_deals'] as $deal): ?>
                    <div class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <div>
                            <a href="/crm/deal/<?php echo (int) $deal['id']; ?>" class="font-medium text-blue-600 hover:underline dark:text-blue-400"><?php echo HTML::chars($deal['title']); ?></a>
                            <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($deal['client']['name']); ?> · <?php echo (int) $deal['stale_days']; ?> дн. без записей</div>
                        </div>
                        <?php echo Component::render('badge', array('label' => 'Застой', 'state' => 'warn')); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Срок решения на подходе</h2>
            </header>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php if ( ! $agenda['deadlines']): ?><p class="px-5 py-5 text-sm text-gray-500 dark:text-gray-400">Ближайшая неделя свободна.</p><?php endif; ?>
                <?php foreach ($agenda['deadlines'] as $deal): ?>
                    <div class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <div>
                            <a href="/crm/deal/<?php echo (int) $deal['id']; ?>" class="font-medium text-blue-600 hover:underline dark:text-blue-400"><?php echo HTML::chars($deal['title']); ?></a>
                            <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($deal['client']['name']); ?> · <?php echo HTML::chars($deal['deadline']); ?></div>
                        </div>
                        <div class="font-semibold text-gray-900 dark:text-white"><?php echo $money($deal['amount']); ?> ₽</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</div>
