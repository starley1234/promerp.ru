<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Карточка свободного объекта: что это, откуда взялось, к чему привязано.
 *
 * Атрибуты — свободный словарь (марка, модель, серийный номер, состояние):
 * их заполняет оператор или подсказывает распознавание фото, а модератор
 * проверяет перед тем, как объект попадёт в рабочие сценарии.
 *
 * Переменные: object, tokens, photos, history, types, statuses, can_edit,
 * can_moderate, messages.
 */
$attributes = (array) $object['attributes'];
?>
<div>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="/qr/objects" class="text-sm text-blue-700 hover:underline dark:text-blue-400">← Все объекты</a>
            <h1 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white"><?php echo HTML::chars($object['name']); ?></h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                <?php echo Component::render('badge', array('label' => Service_Erp199_Objects::instance()->status_label($object['status']), 'state' => Service_Erp199_Objects::instance()->state_of($object['status']))); ?>
                <?php if ($object['type_label']): ?><span class="ml-2"><?php echo HTML::chars($object['type_label']); ?></span><?php endif; ?>
                <span class="ml-2"><?php echo $object['source'] === 'ai' ? 'распознан по фото' : ($object['source'] === 'scan' ? 'заведён сканированием' : 'заведён вручную'); ?></span>
            </p>
        </div>
        <?php if ($can_moderate AND $object['status'] === 'draft'): ?>
            <form method="post" action="/qr/moderate" class="flex flex-wrap items-end gap-2">
                <input type="hidden" name="id" value="<?php echo (int) $object['id']; ?>">
                <div>
                    <label for="moderate_comment" class="block text-xs text-gray-500 dark:text-gray-400">Комментарий модератора</label>
                    <input id="moderate_comment" name="comment" class="mt-1 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                </div>
                <button type="submit" name="status" value="active" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700">Принять</button>
                <button type="submit" name="status" value="rejected" class="rounded-lg bg-rose-600 px-4 py-2 text-sm text-white hover:bg-rose-700">Отклонить</button>
            </form>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp199/flash')->set('messages', $messages); ?>

    <div class="grid gap-6 lg:grid-cols-3">

        <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Характеристики</h2>
            </header>
            <div class="p-5 text-sm">
                <?php if ($can_edit): ?>
                    <form method="post" action="/qr/save" class="space-y-3">
                        <input type="hidden" name="id" value="<?php echo (int) $object['id']; ?>">
                        <?php echo Component::render('form_field', array('id' => 'obj_name', 'label' => 'Название', 'name' => 'name', 'value' => $object['name'], 'required' => TRUE)); ?>
                        <div>
                            <label for="obj_type" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Тип</label>
                            <select id="obj_type" name="type" class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                <option value="">не указан</option>
                                <?php foreach ($types as $type): ?>
                                    <option value="<?php echo HTML::chars($type['code']); ?>" <?php echo $object['type_id'] == $type['id'] ? 'selected' : ''; ?>><?php echo HTML::chars($type['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php echo Component::render('form_field', array('id' => 'obj_brand', 'label' => 'Марка', 'name' => 'brand', 'value' => $object['brand'])); ?>
                        <?php echo Component::render('form_field', array('id' => 'obj_model', 'label' => 'Модель', 'name' => 'model', 'value' => $object['model'])); ?>
                        <?php echo Component::render('form_field', array('id' => 'obj_serial', 'label' => 'Серийный номер', 'name' => 'serial_number', 'value' => $object['serial_number'])); ?>
                        <?php echo Component::render('form_field', array('id' => 'obj_description', 'label' => 'Описание', 'name' => 'description', 'type' => 'textarea', 'value' => $object['description'])); ?>
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">Сохранить</button>
                    </form>
                <?php else: ?>
                    <dl class="space-y-2">
                        <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">Марка</dt><dd class="text-gray-900 dark:text-white"><?php echo HTML::chars((string) $object['brand']) ?: '—'; ?></dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">Модель</dt><dd class="text-gray-900 dark:text-white"><?php echo HTML::chars((string) $object['model']) ?: '—'; ?></dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">Серийный номер</dt><dd class="text-gray-900 dark:text-white"><?php echo HTML::chars((string) $object['serial_number']) ?: '—'; ?></dd></div>
                    </dl>
                <?php endif; ?>

                <?php if ($attributes): ?>
                    <div class="mt-4 border-t border-gray-100 pt-3 dark:border-gray-700">
                        <h3 class="mb-2 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Распознанные атрибуты</h3>
                        <dl class="space-y-2">
                            <?php foreach ($attributes as $key => $value): ?>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-gray-500 dark:text-gray-400"><?php echo HTML::chars((string) $key); ?></dt>
                                    <dd class="text-right text-gray-900 dark:text-white"><?php echo HTML::chars(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)); ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                <?php endif; ?>
                <?php if ($object['description']): ?>
                    <p class="mt-4 border-t border-gray-100 pt-3 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300"><?php echo nl2br(HTML::chars((string) $object['description'])); ?></p>
                <?php endif; ?>
                <?php if ($object['moderator_comment']): ?>
                    <p class="mt-4 border-t border-gray-100 pt-3 text-sm <?php echo Component::state_class('warn', 'text'); ?> dark:border-gray-700">Решение модератора: <?php echo HTML::chars((string) $object['moderator_comment']); ?></p>
                <?php endif; ?>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Наклейки объекта</h2>
            </header>
            <div class="p-5">
                <?php if ( ! $tokens): ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Наклеек нет.</p>
                <?php else: ?>
                    <ul class="space-y-2">
                        <?php foreach ($tokens as $token): ?>
                            <li class="flex items-center justify-between gap-2">
                                <a href="/qr/token/<?php echo HTML::chars($token['code']); ?>" class="font-mono text-sm text-blue-600 hover:underline dark:text-blue-400"><?php echo HTML::chars($token['code']); ?></a>
                                <?php echo Component::render('badge', array('label' => Service_Erp199_Tokens::instance()->status_label($token['status']), 'state' => Service_Erp199_Tokens::instance()->state_of($token['status']))); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">История изменений</h2>
            </header>
            <div class="p-5">
                <?php if ( ! $history): ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Изменений не было.</p>
                <?php else: ?>
                    <ol class="space-y-3 text-sm">
                        <?php
                        $types = array(
                            'create'        => 'Создан',
                            'update'        => 'Изменён',
                            'ai_refinement' => 'Уточнено распознавание',
                            'moderation'    => 'Решение модератора',
                        );

                        foreach ($history as $entry):
                            $details = array();

                            foreach ((array) $entry['changed_data'] as $key => $value)
                            {
                                if (is_scalar($value) AND $value !== '')
                                {
                                    $details[] = $key.'='.HTML::chars((string) $value);
                                }
                            }
                        ?>
                            <li class="border-l-2 border-gray-200 pl-3 dark:border-gray-700">
                                <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars((string) $entry['created_at']); ?></div>
                                <div class="text-gray-900 dark:text-white"><?php echo HTML::chars(Arr::get($types, (string) $entry['change_type'], (string) $entry['change_type'])); ?></div>
                                <?php if ($details): ?><div class="text-xs text-gray-500 dark:text-gray-400"><?php echo implode(' · ', $details); ?></div><?php endif; ?>
                                <?php if ($entry['user_id']): ?><div class="text-xs text-gray-500 dark:text-gray-400">пользователь #<?php echo (int) $entry['user_id']; ?></div><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php if ($photos): ?>
        <section class="mt-6 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Фотографии</h2>
            <div class="flex flex-wrap gap-3">
                <?php foreach ($photos as $photo): ?>
                    <a href="<?php echo HTML::chars($photo['url']); ?>" target="_blank">
                        <img src="<?php echo HTML::chars($photo['url']); ?>" alt="Фото объекта"
                             class="h-32 w-32 rounded-lg border border-gray-200 object-cover dark:border-gray-700" loading="lazy">
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
