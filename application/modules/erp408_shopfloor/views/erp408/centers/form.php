<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="mx-auto max-w-3xl">

    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
        <?php echo $center ? 'Рабочий центр '.HTML::chars($center['code']) : 'Новый рабочий центр'; ?>
    </h1>

    <?php echo View::factory('erp408/partials/flash')->set('messages', $messages); ?>

    <form autocomplete="off" method="post" action="/shopfloor/centers/save" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4 text-sm">
        <?php if ($center): ?>
            <input type="hidden" name="id" value="<?php echo (int) $center['id']; ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Код *</label>
                <input type="text" name="code" required value="<?php echo $center ? HTML::chars($center['code']) : ''; ?>" placeholder="СТ-01"
                       class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 font-mono">
            </div>
            <div>
                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Вид</label>
                <select name="kind" class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                    <?php foreach ($kinds as $key => $label): ?>
                        <option value="<?php echo HTML::chars($key); ?>" <?php echo ($center AND $center['kind'] === $key) ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Название *</label>
                <input type="text" name="name" required value="<?php echo $center ? HTML::chars($center['name']) : ''; ?>" placeholder="Токарный станок №1"
                       class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Цех или участок</label>
                <input type="text" name="workshop" value="<?php echo $center ? HTML::chars((string) $center['workshop']) : ''; ?>" placeholder="Механический участок"
                       class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Производительность в час</label>
                <input type="number" step="0.01" min="0" name="capacity_per_hour" value="<?php echo $center ? (float) $center['capacity_per_hour'] : ''; ?>"
                       class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Нужна для расчёта загрузки.</p>
            </div>
            <div>
                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Стоимость нормо-часа</label>
                <input type="number" step="0.01" min="0" name="cost_per_hour" value="<?php echo $center ? (float) $center['cost_per_hour'] : ''; ?>"
                       class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Пойдёт в себестоимость (erp409).</p>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Примечание</label>
                <textarea name="note" rows="2"
                          class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"><?php echo $center ? HTML::chars((string) $center['note']) : ''; ?></textarea>
            </div>
            <div class="md:col-span-2">
                <label class="inline-flex items-center text-gray-700 dark:text-gray-200">
                    <input type="checkbox" name="is_active" value="1" class="mr-2" <?php echo ( ! $center OR $center['is_active']) ? 'checked' : ''; ?>>
                    Включён (на него можно назначать операции)
                </label>
            </div>
        </div>

        <div class="flex items-center gap-2 pt-2">
            <button type="submit" class="px-5 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">
                <i class="fas fa-save mr-1"></i> Сохранить
            </button>
            <a href="/shopfloor/centers" class="px-5 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                Отмена
            </a>
        </div>
    </form>
</div>
