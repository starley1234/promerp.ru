<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Справочники воронки: стадии и причины отказа.
 *
 * Переменные: stages, loss_reasons, kinds, usage, can_manage, messages.
 *
 * Стадия — это не только название: вероятность участвует в прогнозе,
 * обязательные поля и действия срабатывают при переходе (см. Service_Erp221_Deals).
 */
$usage_by_code = array();

foreach ($usage as $row)
{
    $usage_by_code[$row['code']] = $row;
}
?>
<div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Стадии и причины отказа</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Воронка настраивается без программиста: переименуйте стадию, поменяйте вероятность
            или задайте обязательные поля — переходы начнут проверяться сразу.
        </p>
    </div>

    <?php echo View::factory('erp221/flash')->set('messages', $messages); ?>

    <div class="grid gap-6 lg:grid-cols-2">

        <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Стадии воронки</h2>
            </header>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php foreach ($stages as $stage): ?>
                    <div class="px-5 py-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2 font-medium text-gray-900 dark:text-white">
                                    <svg class="inline-block h-2.5 w-2.5" viewBox="0 0 10 10" aria-hidden="true"><circle cx="5" cy="5" r="5" fill="<?php echo HTML::chars($stage['color'] ? $stage['color'] : '#94a3b8'); ?>"></circle></svg>
                                    <?php echo HTML::chars($stage['label']); ?>
                                    <?php echo Component::render('badge', array('label' => $kinds[$stage['kind']], 'state' => $stage['kind'] === 'won' ? 'ok' : ($stage['kind'] === 'lost' ? 'bad' : 'info'))); ?>
                                    <?php if ( ! $stage['is_active']): ?><?php echo Component::render('badge', array('label' => 'Выключена', 'state' => 'neutral')); ?><?php endif; ?>
                                </div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    код <?php echo HTML::chars($stage['code']); ?> · вероятность <?php echo (int) $stage['probability']; ?> % ·
                                    сортировка <?php echo (int) $stage['sort']; ?> ·
                                    сделок <?php echo isset($usage_by_code[$stage['code']]) ? (int) $usage_by_code[$stage['code']]['count'] : 0; ?>
                                    <?php if ($stage['required_fields']): ?>· обязательные поля: <?php echo HTML::chars(implode(', ', $stage['required_fields'])); ?><?php endif; ?>
                                    <?php if ($stage['requires_loss_reason']): ?>· требует причину отказа<?php endif; ?>
                                </div>
                            </div>
                            <?php if ($can_manage): ?>
                                <form method="post" action="/crm/stage_delete" data-confirm="Удалить стадию?">
                                    <input type="hidden" name="id" value="<?php echo (int) $stage['id']; ?>">
                                    <button type="submit" class="text-xs text-rose-600 hover:underline dark:text-rose-400">Удалить</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php if ($can_manage): ?>
                            <details class="mt-2">
                                <summary class="cursor-pointer text-xs text-blue-600 dark:text-blue-400">Изменить</summary>
                                <form method="post" action="/crm/stage_save" class="mt-3 grid gap-2 md:grid-cols-2">
                                    <input type="hidden" name="code" value="<?php echo HTML::chars($stage['code']); ?>">
                                    <?php echo Component::render('form_field', array('id' => 'stage_label_'.$stage['id'], 'label' => 'Название', 'name' => 'label', 'value' => $stage['label'])); ?>
                                    <div class="space-y-1">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="stage_kind_<?php echo (int) $stage['id']; ?>">Вид</label>
                                        <select id="stage_kind_<?php echo (int) $stage['id']; ?>" name="kind" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                            <?php foreach ($kinds as $code => $label): ?>
                                                <option value="<?php echo $code; ?>"<?php echo $stage['kind'] === $code ? ' selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php echo Component::render('form_field', array('id' => 'stage_probability_'.$stage['id'], 'label' => 'Вероятность, %', 'name' => 'probability', 'type' => 'number', 'value' => $stage['probability'])); ?>
                                    <?php echo Component::render('form_field', array('id' => 'stage_sort_'.$stage['id'], 'label' => 'Сортировка', 'name' => 'sort', 'type' => 'number', 'value' => $stage['sort'])); ?>
                                    <?php echo Component::render('form_field', array('id' => 'stage_color_'.$stage['id'], 'label' => 'Цвет (CSS)', 'name' => 'color', 'value' => $stage['color'])); ?>
                                    <?php echo Component::render('form_field', array('id' => 'stage_required_'.$stage['id'], 'label' => 'Обязательные поля', 'name' => 'required_fields', 'value' => implode(', ', $stage['required_fields']), 'hint' => 'amount, deadline, description, source, contact_id')); ?>
                                    <div class="flex flex-wrap items-center gap-4 md:col-span-2">
                                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                            <input type="checkbox" name="requires_loss_reason" value="1"<?php echo $stage['requires_loss_reason'] ? ' checked' : ''; ?> class="rounded border-gray-300"> требует причину отказа
                                        </label>
                                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                            <input type="checkbox" name="is_active" value="1"<?php echo $stage['is_active'] ? ' checked' : ''; ?> class="rounded border-gray-300"> включена
                                        </label>
                                    </div>
                                    <div class="md:col-span-2">
                                        <button type="submit" class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs text-white dark:bg-gray-700">Сохранить стадию</button>
                                    </div>
                                </form>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($can_manage): ?>
                <form method="post" action="/crm/stage_save" class="grid gap-2 border-t border-gray-200 p-5 md:grid-cols-2 dark:border-gray-700">
                    <div class="md:col-span-2"><h3 class="text-sm font-semibold text-gray-900 dark:text-white">Новая стадия</h3></div>
                    <?php echo Component::render('form_field', array('id' => 'new_stage_code', 'label' => 'Код', 'name' => 'code', 'hint' => 'латиница: например pilot')); ?>
                    <?php echo Component::render('form_field', array('id' => 'new_stage_label', 'label' => 'Название', 'name' => 'label')); ?>
                    <?php echo Component::render('form_field', array('id' => 'new_stage_probability', 'label' => 'Вероятность, %', 'name' => 'probability', 'type' => 'number', 'value' => 30)); ?>
                    <?php echo Component::render('form_field', array('id' => 'new_stage_sort', 'label' => 'Сортировка', 'name' => 'sort', 'type' => 'number', 'value' => 100)); ?>
                    <div class="md:col-span-2">
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Добавить стадию</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Причины отказа</h2>
            </header>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php foreach ($loss_reasons as $reason): ?>
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="text-sm">
                            <span class="font-medium text-gray-900 dark:text-white"><?php echo HTML::chars($reason['label']); ?></span>
                            <span class="ml-2 font-mono text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($reason['code']); ?></span>
                            <?php if ( ! $reason['is_active']): ?><?php echo Component::render('badge', array('label' => 'Выключена', 'state' => 'neutral')); ?><?php endif; ?>
                        </div>
                        <?php if ($can_manage): ?>
                            <form method="post" action="/crm/loss_reason_delete" data-confirm="Удалить причину?">
                                <input type="hidden" name="id" value="<?php echo (int) $reason['id']; ?>">
                                <button type="submit" class="text-xs text-rose-600 hover:underline dark:text-rose-400">Удалить</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($can_manage): ?>
                <form method="post" action="/crm/loss_reason_save" class="grid gap-2 border-t border-gray-200 p-5 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Новая причина</h3>
                    <?php echo Component::render('form_field', array('id' => 'reason_code', 'label' => 'Код', 'name' => 'code', 'hint' => 'латиница: например price_dump')); ?>
                    <?php echo Component::render('form_field', array('id' => 'reason_label', 'label' => 'Формулировка', 'name' => 'label')); ?>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Добавить причину</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>
