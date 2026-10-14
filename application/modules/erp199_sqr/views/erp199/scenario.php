<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Карточка сценария: форма для терминала и эффект на сервере.
 *
 * Форма и эффект вводятся JSON-ом осознанно: это контракт с приложением, и его
 * лучше видеть целиком, чем собирать из десятка полей с потерей структуры.
 * Пример формы:
 *   {"fields":[{"name":"stage","label":"Этап","type":"select","required":true,
 *               "options":{"assembly":"Сборка","packing":"Упаковка"}}]}
 * Примеры эффекта:
 *   {"kind":"record"}                              — только запись в журнал;
 *   {"kind":"set_status","status_param":"status"}   — сменить статус цели;
 *   {"kind":"set_attributes","store_as":"stage"}    — записать значение в атрибуты;
 *   {"kind":"inventory_scan","status_param":"condition"} — отметка в инвентаризации.
 *
 * Переменные: scenario, kinds, can_manage, messages.
 */
$is_new = ( ! $scenario);

$value = function ($field, $default = '') use ($scenario) {
    return $scenario AND $scenario[$field] !== NULL ? $scenario[$field] : $default;
};
?>
<div>

    <div class="mb-6">
        <a href="/qr/scenarios" class="text-sm text-blue-700 hover:underline dark:text-blue-400">← Все сценарии</a>
        <h1 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
            <?php echo $is_new ? 'Новый сценарий' : HTML::chars($scenario['label']); ?>
        </h1>
        <?php if ( ! $is_new): ?>
            <p class="mt-1 font-mono text-sm text-gray-500 dark:text-gray-400"><?php echo HTML::chars($scenario['code']); ?></p>
        <?php endif; ?>
    </div>

    <?php echo View::factory('erp199/flash')->set('messages', $messages); ?>

    <?php if ( ! $can_manage): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
            Сценарий доступен только для просмотра: правка требует права «erp199.scenarios.manage».
        </div>
    <?php else: ?>
        <form method="post" action="<?php echo $is_new ? '/qr/scenario' : '/qr/scenario/'.HTML::chars($scenario['code']); ?>" class="space-y-6">
            <?php if ( ! $is_new): ?><input type="hidden" name="id" value="<?php echo (int) $scenario['id']; ?>"><?php endif; ?>

            <section class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Основное</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <?php echo Component::render('form_field', array('id' => 'sc_code', 'label' => 'Код', 'name' => 'code', 'value' => $value('code'), 'required' => TRUE, 'hint' => 'латиница, например stage_mark')); ?>
                    <?php echo Component::render('form_field', array('id' => 'sc_label', 'label' => 'Название', 'name' => 'label', 'value' => $value('label'), 'required' => TRUE)); ?>
                    <?php echo Component::render('form_field', array('id' => 'sc_icon', 'label' => 'Иконка', 'name' => 'icon', 'value' => $value('icon'), 'hint' => 'например fas fa-camera')); ?>
                    <?php echo Component::render('form_field', array('id' => 'sc_sort', 'label' => 'Порядок', 'name' => 'sort', 'type' => 'number', 'value' => $value('sort', 100), 'hint' => 'меньше — выше в списке')); ?>
                </div>
                <?php echo Component::render('form_field', array('id' => 'sc_description', 'label' => 'Описание', 'name' => 'description', 'type' => 'textarea', 'value' => $value('description'))); ?>
                <label class="mt-3 inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300" <?php echo ( ! $scenario OR $scenario['is_active']) ? 'checked' : ''; ?>>
                    Сценарий включён
                </label>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Где показывать</h2>
                <div class="flex flex-wrap gap-4">
                    <?php foreach ($kinds as $kind => $label): ?>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="checkbox" name="target_kinds[]" value="<?php echo HTML::chars($kind); ?>" class="rounded border-gray-300"
                                <?php echo ($scenario AND in_array($kind, $scenario['target_kinds'], TRUE)) ? 'checked' : ''; ?>>
                            <?php echo HTML::chars($label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3 flex flex-wrap gap-4">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input type="checkbox" name="requires_photo" value="1" class="rounded border-gray-300" <?php echo ($scenario AND $scenario['requires_photo']) ? 'checked' : ''; ?>>
                        Требовать фото
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input type="checkbox" name="requires_comment" value="1" class="rounded border-gray-300" <?php echo ($scenario AND $scenario['requires_comment']) ? 'checked' : ''; ?>>
                        Требовать комментарий
                    </label>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Форма и эффект (JSON)</h2>
                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label for="sc_form" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Форма (какие поля показать)</label>
                        <textarea id="sc_form" name="form" rows="10" class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-xs dark:border-gray-600 dark:bg-gray-800 dark:text-white"><?php echo HTML::chars(json_encode($scenario ? $scenario['form'] : array('fields' => array()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></textarea>
                    </div>
                    <div>
                        <label for="sc_effect" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Эффект (что сделать на сервере)</label>
                        <textarea id="sc_effect" name="effect" rows="10" class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-xs dark:border-gray-600 dark:bg-gray-800 dark:text-white"><?php echo HTML::chars(json_encode($scenario ? $scenario['effect'] : array('kind' => 'record'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></textarea>
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Поддерживаемые эффекты: record, set_status, set_attributes, inventory_scan. Обязательное поле формы помечается «required».
                </p>
            </section>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Сохранить сценарий</button>
                <a href="/qr/scenarios" class="text-sm text-gray-600 hover:underline dark:text-gray-300">Отмена</a>
            </div>
        </form>
    <?php endif; ?>
</div>
