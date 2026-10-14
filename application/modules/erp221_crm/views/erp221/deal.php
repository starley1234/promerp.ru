<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Карточка сделки: клиент, лента, задачи, участники, ИИ-разбор и смена стадии.
 *
 * Переменные: deal, stages, loss_reasons, client_contacts, types, insights,
 * ai_available, users, can_manage, can_delete, messages.
 */
$money = function ($value) { return number_format((float) $value, 2, '.', ' '); };
?>
<div>

    <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="font-mono text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($deal['number']); ?></div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo HTML::chars($deal['title']); ?></h1>
            <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                <a href="/crm/client/<?php echo (int) $deal['client']['id']; ?>" class="text-blue-600 hover:underline dark:text-blue-400">
                    <i class="fas fa-building mr-1" aria-hidden="true"></i><?php echo HTML::chars($deal['client']['name']); ?>
                </a>
                <?php echo Component::render('badge', array('label' => $deal['stage']['label'], 'state' => $deal['stage']['kind'] === 'won' ? 'ok' : ($deal['stage']['kind'] === 'lost' ? 'bad' : 'info'))); ?>
                <?php if ($deal['is_overdue']): ?><?php echo Component::render('badge', array('label' => 'Срок прошёл', 'state' => 'bad')); ?><?php endif; ?>
                <?php if ($deal['stale_days'] > 0): ?><span>без записей <?php echo (int) $deal['stale_days']; ?> дн.</span><?php endif; ?>
            </div>
        </div>
        <div class="text-right">
            <div class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $money($deal['amount']); ?> <?php echo HTML::chars($deal['currency']); ?></div>
            <div class="text-sm text-gray-500 dark:text-gray-400">вероятность <?php echo (int) $deal['probability']; ?> % · прогноз <?php echo $money($deal['forecast']); ?></div>
            <div class="text-sm text-gray-500 dark:text-gray-400">срок: <?php echo $deal['deadline'] ? HTML::chars($deal['deadline']) : 'не указан'; ?></div>
        </div>
    </div>

    <?php echo View::factory('erp221/flash')->set('messages', $messages); ?>

    <div class="grid gap-6 lg:grid-cols-3">

        <div class="space-y-6 lg:col-span-2">

            <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <header class="flex items-center justify-between border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Лента работы</h2>
                    <span class="text-xs text-gray-500 dark:text-gray-400"><?php echo count($deal['timeline']); ?> записей</span>
                </header>

                <?php if ($can_manage): ?>
                    <form method="post" action="/crm/activity_save" class="grid gap-3 border-b border-gray-200 p-5 md:grid-cols-2 dark:border-gray-700">
                        <input type="hidden" name="deal_id" value="<?php echo (int) $deal['id']; ?>">
                        <div class="space-y-1">
                            <label for="activity_type" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Что сделано</label>
                            <select id="activity_type" name="type" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                <?php foreach ($types as $code => $label): ?>
                                    <option value="<?php echo HTML::chars($code); ?>"><?php echo HTML::chars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label for="activity_status" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Состояние</label>
                            <select id="activity_status" name="status" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                <option value="completed">Выполнено</option>
                                <option value="pending">Задача на будущее</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <?php echo Component::render('form_field', array('id' => 'activity_subject', 'label' => 'Тема (необязательно)', 'name' => 'subject')); ?>
                        </div>
                        <div class="md:col-span-2">
                            <?php echo Component::render('form_field', array('id' => 'activity_content', 'label' => 'Содержание', 'name' => 'content', 'type' => 'textarea')); ?>
                        </div>
                        <?php echo Component::render('form_field', array('id' => 'activity_scheduled', 'label' => 'Срок задачи', 'name' => 'scheduled_at', 'type' => 'datetime-local', 'hint' => 'обязателен для задачи')); ?>
                        <div class="space-y-1">
                            <label for="activity_assigned" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Кому</label>
                            <select id="activity_assigned" name="assigned_to" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                <option value="">Мне</option>
                                <?php foreach ($users as $id => $name): ?>
                                    <option value="<?php echo (int) $id; ?>"><?php echo HTML::chars($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Добавить в ленту</button>
                        </div>
                    </form>

                    <form method="post" action="/crm/activity_file" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3 border-b border-gray-200 p-5 dark:border-gray-700">
                        <input type="hidden" name="deal_id" value="<?php echo (int) $deal['id']; ?>">
                        <div class="space-y-1">
                            <label for="deal_file" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Приложить файл</label>
                            <input id="deal_file" type="file" name="file" class="text-sm text-gray-700 dark:text-gray-200">
                        </div>
                        <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 dark:bg-gray-700">Загрузить</button>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Файл уходит в общее хранилище (erp212), в ленте остаётся ссылка.</p>
                    </form>
                <?php endif; ?>

                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    <?php if ( ! $deal['timeline']): ?>
                        <p class="px-5 py-6 text-sm text-gray-500 dark:text-gray-400">Записей пока нет. Первый звонок — и лента оживёт.</p>
                    <?php endif; ?>
                    <?php foreach ($deal['timeline'] as $entry): ?>
                        <article class="px-5 py-4">
                            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <?php echo Component::render('badge', array('label' => isset($types[$entry['type']]) ? $types[$entry['type']] : $entry['type'], 'state' => 'neutral')); ?>
                                <span><?php echo HTML::chars($entry['created_at']); ?></span>
                                <?php if ($entry['author_name']): ?><span>· <?php echo HTML::chars($entry['author_name']); ?></span><?php endif; ?>
                                <?php if ($entry['status'] === 'pending'): ?>
                                    <?php echo Component::render('badge', array('label' => $entry['is_overdue'] ? 'Просрочено' : 'Ждёт', 'state' => $entry['is_overdue'] ? 'bad' : 'warn')); ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($entry['subject']): ?><div class="mt-1 font-medium text-gray-900 dark:text-white"><?php echo HTML::chars($entry['subject']); ?></div><?php endif; ?>
                            <?php if ($entry['content']): ?><div class="mt-1 whitespace-pre-line text-sm text-gray-700 dark:text-gray-200"><?php echo HTML::chars($entry['content']); ?></div><?php endif; ?>
                            <?php if ($entry['storage_file_id']): ?>
                                <a href="/erp212/file/<?php echo (int) $entry['storage_file_id']; ?>" class="mt-1 inline-block text-sm text-blue-600 hover:underline dark:text-blue-400">
                                    <i class="fas fa-paperclip mr-1" aria-hidden="true"></i> Файл #<?php echo (int) $entry['storage_file_id']; ?>
                                </a>
                            <?php endif; ?>
                            <?php if ($can_manage AND $entry['status'] === 'pending'): ?>
                                <form method="post" action="/crm/activity_delete" class="mt-2 flex gap-2">
                                    <input type="hidden" name="id" value="<?php echo (int) $entry['id']; ?>">
                                    <input type="hidden" name="deal_id" value="<?php echo (int) $deal['id']; ?>">
                                    <input type="hidden" name="do" value="complete">
                                    <button type="submit" class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs text-white hover:bg-emerald-700">Выполнено</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <div class="space-y-6">

            <?php if ($can_manage): ?>
                <section class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Сменить стадию</h2>
                    <form method="post" action="/crm/deal_stage" class="space-y-3">
                        <input type="hidden" name="id" value="<?php echo (int) $deal['id']; ?>">
                        <select name="stage_id" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            <?php foreach ($stages as $stage): ?>
                                <option value="<?php echo (int) $stage['id']; ?>"<?php echo (int) $stage['id'] === (int) $deal['stage_id'] ? ' selected' : ''; ?>><?php echo HTML::chars($stage['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="loss_reason_id" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            <option value="">Причина отказа (для отказа)</option>
                            <?php foreach ($loss_reasons as $reason): ?>
                                <option value="<?php echo (int) $reason['id']; ?>"><?php echo HTML::chars($reason['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <textarea name="comment" rows="2" placeholder="Комментарий к переходу" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"></textarea>
                        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Перевести</button>
                    </form>
                </section>
            <?php endif; ?>

            <section class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Участники сделки</h2>
                <?php if ( ! $deal['contacts']): ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Контакты не добавлены.</p>
                <?php endif; ?>
                <ul class="space-y-2">
                    <?php foreach ($deal['contacts'] as $contact): ?>
                        <li class="flex items-start justify-between gap-2 text-sm">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">
                                    <?php echo HTML::chars($contact['name']); ?>
                                    <?php if ($contact['is_primary']): ?><?php echo Component::render('badge', array('label' => 'Основной', 'state' => 'info')); ?><?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <?php echo HTML::chars(trim(implode(' · ', array_filter(array($contact['position'], $contact['phone'], $contact['email']))))); ?>
                                </div>
                            </div>
                            <?php if ($can_manage): ?>
                                <form method="post" action="/crm/deal_contact">
                                    <input type="hidden" name="id" value="<?php echo (int) $deal['id']; ?>">
                                    <input type="hidden" name="contact_id" value="<?php echo (int) $contact['contact_id']; ?>">
                                    <input type="hidden" name="do" value="remove">
                                    <button type="submit" class="text-xs text-rose-600 hover:underline dark:text-rose-400">Убрать</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($can_manage AND $client_contacts): ?>
                    <form method="post" action="/crm/deal_contact" class="mt-4 space-y-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <input type="hidden" name="id" value="<?php echo (int) $deal['id']; ?>">
                        <select name="contact_id" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            <?php foreach ($client_contacts as $contact): ?>
                                <option value="<?php echo (int) $contact['id']; ?>"><?php echo HTML::chars($contact['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                            <input type="checkbox" name="is_primary" value="1" class="rounded border-gray-300"> основной контакт по сделке
                        </label>
                        <button type="submit" class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs text-white dark:bg-gray-700">Добавить контакт</button>
                    </form>
                <?php endif; ?>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">ИИ-разбор</h2>
                <?php if ( ! $ai_available): ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Провайдер LLM не подключён — сделка работает без подсказок.</p>
                <?php else: ?>
                    <?php if ($can_manage): ?>
                        <form method="post" action="/crm/ai_run" class="mb-3 flex gap-2">
                            <input type="hidden" name="deal_id" value="<?php echo (int) $deal['id']; ?>">
                            <select name="insight" class="flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                <?php foreach ($insights as $code => $label): ?>
                                    <option value="<?php echo HTML::chars($code); ?>"><?php echo HTML::chars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700">Разобрать</button>
                        </form>
                    <?php endif; ?>
                    <div class="space-y-3">
                        <?php if ( ! $deal['insights']): ?>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Разборов ещё не было.</p>
                        <?php endif; ?>
                        <?php foreach ($deal['insights'] as $row): ?>
                            <div class="rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-900/40">
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <?php echo HTML::chars(isset($insights[$row['insight_type']]) ? $insights[$row['insight_type']] : $row['insight_type']); ?>
                                    · <?php echo HTML::chars($row['created_at']); ?>
                                    <?php if ($row['confidence'] !== NULL): ?>· уверенность <?php echo round($row['confidence'] * 100); ?> %<?php endif; ?>
                                </div>
                                <div class="mt-1 whitespace-pre-line text-gray-700 dark:text-gray-200"><?php echo HTML::chars($row['content']); ?></div>
                                <div class="mt-1 text-xs text-gray-400">машина может ошибаться — решение принимает человек</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($can_manage): ?>
                <section class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Правка сделки</h2>
                    <form method="post" action="/crm/deal_save" class="space-y-3">
                        <input type="hidden" name="id" value="<?php echo (int) $deal['id']; ?>">
                        <?php echo Component::render('form_field', array('id' => 'edit_title', 'label' => 'Название', 'name' => 'title', 'value' => $deal['title'])); ?>
                        <?php echo Component::render('form_field', array('id' => 'edit_amount', 'label' => 'Сумма', 'name' => 'amount', 'type' => 'number', 'value' => $deal['amount'])); ?>
                        <?php echo Component::render('form_field', array('id' => 'edit_deadline', 'label' => 'Срок', 'name' => 'deadline', 'type' => 'date', 'value' => $deal['deadline'])); ?>
                        <div class="space-y-1">
                            <label for="edit_priority" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Приоритет</label>
                            <select id="edit_priority" name="priority_level" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                <?php foreach (array('low' => 'Низкий', 'medium' => 'Обычный', 'high' => 'Высокий', 'critical' => 'Критичный') as $code => $label): ?>
                                    <option value="<?php echo $code; ?>"<?php echo $deal['priority_level'] === $code ? ' selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label for="edit_assigned" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Ответственный</label>
                            <select id="edit_assigned" name="assigned_to" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                <option value="">Не назначен</option>
                                <?php foreach ($users as $id => $name): ?>
                                    <option value="<?php echo (int) $id; ?>"<?php echo (int) $deal['assigned_to'] === (int) $id ? ' selected' : ''; ?>><?php echo HTML::chars($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php echo Component::render('form_field', array('id' => 'edit_description', 'label' => 'Описание', 'name' => 'description', 'type' => 'textarea', 'value' => $deal['description'])); ?>
                        <button type="submit" class="w-full rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white dark:bg-gray-700">Сохранить</button>
                    </form>

                    <?php if ($can_delete): ?>
                        <form method="post" action="/crm/deal_delete" class="mt-3" data-confirm="Убрать сделку из работы? История останется в журнале.">
                            <input type="hidden" name="id" value="<?php echo (int) $deal['id']; ?>">
                            <button type="submit" class="text-xs text-rose-600 hover:underline dark:text-rose-400">Убрать сделку из работы</button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    </div>
</div>
