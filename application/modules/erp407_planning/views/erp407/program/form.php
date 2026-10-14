<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="mx-auto max-w-3xl">

    <div class="mb-6">
        <div class="text-sm text-gray-500 dark:text-gray-400">
            <a href="/planning/programs" class="text-blue-600 hover:text-blue-800">Программы</a>
            <span class="mx-1">/</span>
            <span><?php echo $program ? HTML::chars($program->number) : 'новая программа'; ?></span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
            <?php echo $program ? 'Правка программы' : 'Новая производственная программа'; ?>
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Программа — это спрос: что и к какому сроку нужно произвести. Изделия задаются строками,
            потребность в материалах считает расчёт по техкартам (erp228), складу (erp401) и заказам (erp402).
        </p>
    </div>

    <?php echo View::factory('erp407/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <form method="post" action="/planning/programs/save" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-5">
        <?php if ($program): ?>
            <input type="hidden" name="id" value="<?php echo (int) $program->id; ?>">
        <?php endif; ?>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Название</label>
            <input type="text" name="title" required maxlength="255"
                   value="<?php echo $program ? HTML::chars($program->title) : ''; ?>"
                   placeholder="например: План выпуска на октябрь"
                   class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Период с</label>
                <input type="date" name="period_from" required
                       value="<?php echo $program ? HTML::chars($program->period_from) : date('Y-m-d'); ?>"
                       class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">по</label>
                <input type="date" name="period_to" required
                       value="<?php echo $program ? HTML::chars($program->period_to) : date('Y-m-d', strtotime('+30 days')); ?>"
                       class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Горизонт, дней</label>
                <input type="number" name="horizon_days" min="1" max="365"
                       value="<?php echo $program ? (int) $program->horizon_days : 30; ?>"
                       class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Примечание</label>
            <textarea name="note" rows="3"
                      class="w-full border rounded-md px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100"
                      placeholder="контекст плана: источник спроса, договор, сезонность"><?php echo $program ? HTML::chars((string) $program->note) : ''; ?></textarea>
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="/planning/programs" class="text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900">Отмена</a>
            <button type="submit" class="px-5 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                <i class="fas fa-save mr-1"></i> Сохранить
            </button>
        </div>
    </form>

    <?php if ($program): ?>
        <div class="mt-6 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Что дальше</h2>
            <ol class="text-sm text-gray-600 dark:text-gray-300 space-y-1 list-decimal list-inside">
                <li>Добавьте строки программы: изделие, количество, срок.</li>
                <li>Утвердите программу — после этого её состояние и расчёт меняются только осознанно.</li>
                <li>Запустите «Рассчитать потребность»: система разложит изделия по техкартам (до 3 уровней),
                    вычтет склад, незавершёнку и уже заказанное, покажет дефициты и даты заказа.</li>
                <li>По дефицитам создайте предложения и утвердите их — закупка уйдёт в erp403,
                    выпуск станет заказом производства erp402.</li>
            </ol>
        </div>
    <?php endif; ?>
</div>
