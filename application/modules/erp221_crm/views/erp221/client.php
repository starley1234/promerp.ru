<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Карточка клиента: реквизиты (владелец — erp301), профиль продаж, контакты,
 * сделки и лента работы.
 *
 * Переменные: client, roles, users, can_manage, messages.
 */
$money = function ($value) { return number_format((float) $value, 0, '.', ' '); };
?>
<div>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo HTML::chars($client['name']); ?></h1>
            <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                <?php if ($client['inn']): ?><span>ИНН <?php echo HTML::chars($client['inn']); ?></span><?php endif; ?>
                <?php if ($client['code']): ?><span class="font-mono"><?php echo HTML::chars($client['code']); ?></span><?php endif; ?>
                <span>роли: <?php echo HTML::chars(implode(', ', $client['role_labels']) ?: '—'); ?></span>
                <a href="/counterparties/view/<?php echo (int) $client['id']; ?>" class="text-blue-600 hover:underline dark:text-blue-400">Карточка контрагента →</a>
            </div>
        </div>
        <div class="grid grid-cols-3 gap-3 text-right text-sm">
            <div><div class="text-xs text-gray-500 dark:text-gray-400">в работе</div><div class="font-semibold text-gray-900 dark:text-white"><?php echo $money($client['stats']['open_amount']); ?> ₽</div></div>
            <div><div class="text-xs text-gray-500 dark:text-gray-400">выиграно</div><div class="font-semibold text-emerald-600 dark:text-emerald-400"><?php echo $money($client['stats']['won_amount']); ?> ₽</div></div>
            <div><div class="text-xs text-gray-500 dark:text-gray-400">последняя запись</div><div class="font-semibold text-gray-900 dark:text-white"><?php echo $client['stats']['last_activity'] ? HTML::chars($client['stats']['last_activity']) : '—'; ?></div></div>
        </div>
    </div>

    <?php echo View::factory('erp221/flash')->set('messages', $messages); ?>

    <div class="grid gap-6 lg:grid-cols-3">

        <div class="space-y-6 lg:col-span-2">

            <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Сделки клиента</h2>
                </header>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    <?php if ( ! $client['deals']['rows']): ?>
                        <p class="px-5 py-6 text-sm text-gray-500 dark:text-gray-400">Сделок пока нет.</p>
                    <?php endif; ?>
                    <?php foreach ($client['deals']['rows'] as $deal): ?>
                        <div class="flex items-center justify-between gap-4 px-5 py-3">
                            <div>
                                <a href="/crm/deal/<?php echo (int) $deal['id']; ?>" class="font-medium text-blue-600 hover:underline dark:text-blue-400"><?php echo HTML::chars($deal['title']); ?></a>
                                <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($deal['number']); ?> · <?php echo HTML::chars($deal['stage']['label']); ?></div>
                            </div>
                            <div class="text-right text-sm">
                                <div class="font-semibold text-gray-900 dark:text-white"><?php echo $money($deal['amount']); ?> <?php echo HTML::chars($deal['currency']); ?></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo $deal['deadline'] ? HTML::chars($deal['deadline']) : 'без срока'; ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Лента клиента</h2>
                </header>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    <?php if ( ! $client['timeline']['rows']): ?>
                        <p class="px-5 py-6 text-sm text-gray-500 dark:text-gray-400">Записей нет.</p>
                    <?php endif; ?>
                    <?php foreach ($client['timeline']['rows'] as $entry): ?>
                        <article class="px-5 py-3">
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                <?php echo HTML::chars($entry['created_at']); ?>
                                <?php if ($entry['deal_number']): ?>· <a href="/crm/deal/<?php echo (int) $entry['deal_id']; ?>" class="text-blue-600 hover:underline dark:text-blue-400"><?php echo HTML::chars($entry['deal_number']); ?></a><?php endif; ?>
                                <?php if ($entry['author_name']): ?>· <?php echo HTML::chars($entry['author_name']); ?><?php endif; ?>
                            </div>
                            <?php if ($entry['content']): ?><div class="mt-1 text-sm text-gray-700 dark:text-gray-200"><?php echo HTML::chars($entry['content']); ?></div><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <div class="space-y-6">

            <section class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Профиль продаж</h2>
                <dl class="mb-4 space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Сегмент</dt><dd class="text-gray-900 dark:text-white"><?php echo HTML::chars($client['profile']['segment'] ? $client['profile']['segment'] : '—'); ?></dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Отрасль</dt><dd class="text-gray-900 dark:text-white"><?php echo HTML::chars($client['profile']['industry'] ? $client['profile']['industry'] : '—'); ?></dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Источник</dt><dd class="text-gray-900 dark:text-white"><?php echo HTML::chars($client['profile']['source'] ? $client['profile']['source'] : '—'); ?></dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Ответственный</dt><dd class="text-gray-900 dark:text-white"><?php echo isset($users[$client['profile']['owner_id']]) ? HTML::chars($users[$client['profile']['owner_id']]) : '—'; ?></dd></div>
                </dl>
                <?php if ($can_manage): ?>
                    <form method="post" action="/crm/client_save" class="space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <input type="hidden" name="id" value="<?php echo (int) $client['id']; ?>">
                        <?php echo Component::render('form_field', array('id' => 'profile_segment', 'label' => 'Сегмент', 'name' => 'segment', 'value' => $client['profile']['segment'])); ?>
                        <?php echo Component::render('form_field', array('id' => 'profile_industry', 'label' => 'Отрасль', 'name' => 'industry', 'value' => $client['profile']['industry'])); ?>
                        <?php echo Component::render('form_field', array('id' => 'profile_source', 'label' => 'Источник', 'name' => 'source', 'value' => $client['profile']['source'])); ?>
                        <div class="space-y-1">
                            <label for="profile_owner" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Ответственный</label>
                            <select id="profile_owner" name="owner_id" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                <option value="">Не назначен</option>
                                <?php foreach ($users as $id => $name): ?>
                                    <option value="<?php echo (int) $id; ?>"<?php echo (int) $client['profile']['owner_id'] === (int) $id ? ' selected' : ''; ?>><?php echo HTML::chars($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white dark:bg-gray-700">Сохранить профиль</button>
                    </form>
                <?php endif; ?>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Контакты</h2>
                <ul class="space-y-3">
                    <?php foreach ($client['contacts'] as $contact): ?>
                        <li class="flex items-start justify-between gap-2 text-sm">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">
                                    <?php echo HTML::chars($contact['name']); ?>
                                    <?php if ($contact['is_primary']): ?><?php echo Component::render('badge', array('label' => 'Основной', 'state' => 'info')); ?><?php endif; ?>
                                    <?php if ($contact['decision_maker_level'] === 'lpr'): ?><?php echo Component::render('badge', array('label' => 'ЛПР', 'state' => 'ok')); ?><?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars(trim(implode(' · ', array_filter(array($contact['position'], $contact['phone'], $contact['email'])))) ?: '—'); ?></div>
                            </div>
                            <?php if ($can_manage): ?>
                                <form method="post" action="/crm/contact_delete" data-confirm="Убрать контакт?">
                                    <input type="hidden" name="client_id" value="<?php echo (int) $client['id']; ?>">
                                    <input type="hidden" name="contact_id" value="<?php echo (int) $contact['id']; ?>">
                                    <button type="submit" class="text-xs text-rose-600 hover:underline dark:text-rose-400">Убрать</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($can_manage): ?>
                    <form method="post" action="/crm/contact_save" class="mt-4 space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <input type="hidden" name="client_id" value="<?php echo (int) $client['id']; ?>">
                        <?php echo Component::render('form_field', array('id' => 'contact_name', 'label' => 'Имя', 'name' => 'name', 'required' => TRUE)); ?>
                        <?php echo Component::render('form_field', array('id' => 'contact_position', 'label' => 'Должность', 'name' => 'position')); ?>
                        <?php echo Component::render('form_field', array('id' => 'contact_phone', 'label' => 'Телефон', 'name' => 'phone', 'autocomplete' => 'tel')); ?>
                        <?php echo Component::render('form_field', array('id' => 'contact_email', 'label' => 'Почта', 'name' => 'email', 'type' => 'email', 'autocomplete' => 'email')); ?>
                        <div class="space-y-1">
                            <label for="contact_level" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Уровень ЛПР</label>
                            <select id="contact_level" name="decision_maker_level" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                <option value="unknown">Неизвестно</option>
                                <option value="lpr">ЛПР — принимает решение</option>
                                <option value="lvpr">Влияет на решение</option>
                                <option value="user">Пользователь</option>
                            </select>
                        </div>
                        <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                            <input type="checkbox" name="is_primary" value="1" class="rounded border-gray-300"> основной контакт
                        </label>
                        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Добавить контакт</button>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
