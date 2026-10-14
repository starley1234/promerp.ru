<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Карточка типа объекта: адаптер, подсказка распознавания, характеристики.
 *
 * Переменные: type, kinds, can_manage, messages.
 */
$is_new = ( ! $type);

$value = function ($field, $default = '') use ($type) {
    return $type AND $type[$field] !== NULL ? $type[$field] : $default;
};
?>
<div>

    <div class="mb-6">
        <a href="/qr/types" class="text-sm text-blue-700 hover:underline dark:text-blue-400">← Все типы</a>
        <h1 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
            <?php echo $is_new ? 'Новый тип' : HTML::chars($type['label']); ?>
        </h1>
    </div>

    <?php echo View::factory('erp199/flash')->set('messages', $messages); ?>

    <?php if ( ! $can_manage): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
            Тип доступен только для просмотра: правка требует права «erp199.types.manage».
        </div>

        <section class="mt-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">Код</dt><dd class="font-mono text-gray-900 dark:text-white"><?php echo HTML::chars($value('code')); ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">Адаптер</dt><dd class="text-gray-900 dark:text-white"><?php echo HTML::chars(Arr::get($kinds, $value('adapter'), $value('adapter'))); ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">Характеристики</dt><dd class="text-right text-gray-900 dark:text-white"><?php echo HTML::chars(implode(', ', $type['expected_attributes'])); ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">Подсказка распознавания</dt><dd class="max-w-md text-right text-gray-900 dark:text-white"><?php echo HTML::chars($value('ai_prompt_specs')); ?></dd></div>
            </dl>
        </section>
    <?php else: ?>
        <form method="post" action="<?php echo $is_new ? '/qr/type' : '/qr/type/'.HTML::chars($type['code']); ?>" class="space-y-6">
            <?php if ( ! $is_new): ?><input type="hidden" name="id" value="<?php echo (int) $type['id']; ?>"><?php endif; ?>

            <section class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Основное</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <?php echo Component::render('form_field', array('id' => 'type_code', 'label' => 'Код', 'name' => 'code', 'value' => $value('code'), 'required' => TRUE, 'hint' => 'латиница, например equipment')); ?>
                    <?php echo Component::render('form_field', array('id' => 'type_label', 'label' => 'Название', 'name' => 'label', 'value' => $value('label'), 'required' => TRUE)); ?>
                    <div>
                        <label for="type_adapter" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Адаптер (владелец данных)</label>
                        <select id="type_adapter" name="adapter" class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            <?php foreach ($kinds as $kind => $label): ?>
                                <option value="<?php echo HTML::chars($kind); ?>" <?php echo $value('adapter', 'local') === $kind ? 'selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php echo Component::render('form_field', array('id' => 'type_icon', 'label' => 'Иконка', 'name' => 'icon', 'value' => $value('icon'), 'hint' => 'например fas fa-wrench')); ?>
                    <?php echo Component::render('form_field', array('id' => 'type_sort', 'label' => 'Порядок', 'name' => 'sort', 'type' => 'number', 'value' => $value('sort', 100))); ?>
                </div>
                <?php echo Component::render('form_field', array('id' => 'type_hint', 'label' => 'Подсказка оператору', 'name' => 'hint', 'type' => 'textarea', 'value' => $value('hint'))); ?>
                <div class="mt-3 flex flex-wrap gap-4">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input type="checkbox" name="allow_local" value="1" class="rounded border-gray-300" <?php echo ($type AND $type['allow_local']) ? 'checked' : ''; ?>>
                        Разрешить заводить такой объект в поле
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300" <?php echo ( ! $type OR $type['is_active']) ? 'checked' : ''; ?>>
                        Тип включён
                    </label>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Распознавание фото</h2>
                <?php echo Component::render('form_field', array('id' => 'type_prompt', 'label' => 'Что искать на фотографии', 'name' => 'ai_prompt_specs', 'type' => 'textarea', 'value' => $value('ai_prompt_specs'), 'hint' => 'подсказка добавляется к общему запросу из config/erp199_sqr.php')); ?>
                <?php echo Component::render('form_field', array('id' => 'type_expected', 'label' => 'Ожидаемые характеристики', 'name' => 'expected_attributes', 'value' => $type ? implode(', ', $type['expected_attributes']) : '', 'hint' => 'через запятую: brand, model, serial_number')); ?>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Распознавание — помощник: если LLM-модуль недоступен, объект всё равно заводится, просто без подсказок.
                </p>
            </section>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Сохранить тип</button>
                <a href="/qr/types" class="text-sm text-gray-600 hover:underline dark:text-gray-300">Отмена</a>
            </div>
        </form>
    <?php endif; ?>
</div>
