<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
$is_new = ( ! $card->loaded());
$params = $card->decode_params();
?>

<div>

    <?php echo View::factory('erp228/partials/flash')->set('messages', $messages); ?>
    <?php echo View::factory('erp228/partials/errors')->set('errors', $errors); ?>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <?php echo $is_new ? 'Новая технологическая карта' : 'Правка техкарты'; ?>
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Исходный код изделия и параметры производства — на их основе формируется техпроцесс.
        </p>
    </div>

    <form method="post" action="/tech_cards/save<?php echo $is_new ? '' : '/'.(int) $card->id; ?>"
          class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Название техкарты *</label>
                    <input type="text" name="title" value="<?php echo HTML::chars($card->title); ?>" maxlength="255"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white"
                           placeholder="Например: Кронштейн датчика вибрации">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Тип производства *</label>
                        <select name="production_type" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white">
                            <?php foreach ($production_types as $code => $label): ?>
                                <option value="<?php echo HTML::chars($code); ?>" <?php echo ($card->production_type === $code) ? 'selected' : ''; ?>>
                                    <?php echo HTML::chars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Тип объекта</label>
                        <select name="target_type" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white">
                            <?php foreach ($target_types as $code => $label): ?>
                                <option value="<?php echo HTML::chars($code); ?>" <?php echo ($card->target_type === $code) ? 'selected' : ''; ?>>
                                    <?php echo HTML::chars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Ссылка на объект (id)</label>
                        <input type="number" name="target_id" value="<?php echo $card->target_id ? (int) $card->target_id : ''; ?>"
                               class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white"
                               placeholder="необязательно">
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Исходный код изделия (OpenSCAD) *</h2>
                </div>
                <textarea name="source_code" rows="16"
                          class="w-full bg-transparent px-5 py-4 text-xs font-mono text-gray-800 dark:text-gray-100 focus:outline-none"
                          placeholder="cube([60, 40, 35]);"><?php echo HTML::chars($card->source_code); ?></textarea>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Параметры производства</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    JSON-объект: материал, партия, режимы. Используется шаблоном и уходит в промпт модели.
                </p>
                <textarea name="input_params" rows="8"
                          class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-xs font-mono text-gray-900 dark:text-white"><?php echo HTML::chars(json_encode($params, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></textarea>
                <?php if ($is_new): ?>
                    <button type="button" data-action="set-previous-value" data-value="{&#10;  &quot;материал&quot;: &quot;PA12&quot;,&#10;  &quot;партия&quot;: 1&#10;}"
                            class="text-xs text-blue-600 dark:text-blue-400 hover:underline">Подставить пример</button>
                <?php endif; ?>
            </div>

            <?php if ( ! $is_new): ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Состояние</h2>
                    <dl class="text-sm space-y-2">
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Статус</dt>
                            <dd class="text-gray-900 dark:text-white"><?php echo HTML::chars($card->status_label()); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">UUID</dt>
                            <dd class="text-gray-900 dark:text-white text-xs"><?php echo HTML::chars($card->uuid); ?></dd>
                        </div>
                    </dl>
                </div>
            <?php endif; ?>

            <div class="flex flex-col gap-2">
                <button type="submit"
                        class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg">
                    <i class="fas fa-save mr-2"></i> <?php echo $is_new ? 'Создать техкарту' : 'Сохранить изменения'; ?>
                </button>
                <a href="<?php echo $is_new ? '/tech_cards/list' : '/tech_cards/view/'.(int) $card->id; ?>"
                   class="w-full text-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                    Отмена
                </a>
            </div>
        </div>
    </form>
</div>
