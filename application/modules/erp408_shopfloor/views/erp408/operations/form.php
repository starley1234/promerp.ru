<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div class="mx-auto max-w-3xl">

    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
        <?php echo $operation ? 'Операция '.(int) $operation['op_no'] : 'Операция вручную'; ?>
    </h1>

    <?php echo View::factory('erp408/partials/flash')->set('messages', $messages); ?>

    <form autocomplete="off" method="post" action="/shopfloor/operations/save" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-4 text-sm">
        <?php if ($operation): ?>
            <input type="hidden" name="id" value="<?php echo (int) $operation['id']; ?>">
        <?php else: ?>
            <div>
                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Производственный заказ *</label>
                <select name="order_id" class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100" required>
                    <option value="">— выберите заказ —</option>
                    <?php foreach ($orders as $order): ?>
                        <option value="<?php echo (int) $order['id']; ?>" <?php echo (int) $order_id === (int) $order['id'] ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($order['number'].' · '.$order['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Показаны открытые заказы производства (erp402).</p>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Название работы *</label>
                <input type="text" name="name" value="<?php echo $operation ? HTML::chars($operation['name']) : ''; ?>" required
                       class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
            </div>

            <div>
                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Количество по плану</label>
                <input type="number" step="0.001" min="0" name="qty_planned"
                       value="<?php echo $operation ? (float) $operation['qty_planned'] : ''; ?>"
                       class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
            </div>

            <div>
                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Норма времени, ч</label>
                <input type="number" step="0.01" min="0" name="planned_hours"
                       value="<?php echo $operation ? (float) $operation['planned_hours'] : ''; ?>"
                       class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
            </div>

            <div>
                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Нормативная стоимость</label>
                <input type="number" step="0.01" min="0" name="norm_cost"
                       value="<?php echo $operation ? (float) $operation['norm_cost'] : ''; ?>"
                       class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
            </div>

            <div>
                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">День плана</label>
                <input type="date" name="planned_day"
                       value="<?php echo $operation ? HTML::chars((string) $operation['planned_day']) : date('Y-m-d'); ?>"
                       class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
            </div>

            <div>
                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Рабочий центр</label>
                <select name="work_center_id" class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                    <option value="">— не назначен —</option>
                    <?php foreach ($centers as $center): ?>
                        <option value="<?php echo (int) $center['id']; ?>"
                            <?php echo ($operation AND (int) $operation['work_center_id'] === (int) $center['id']) ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($center['code'].' · '.$center['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Номер операции</label>
                <input type="number" step="10" min="1" name="op_no"
                       value="<?php echo $operation ? (int) $operation['op_no'] : ''; ?>" placeholder="автоматически"
                       class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Описание</label>
                <textarea name="description" rows="3"
                          class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"><?php echo $operation ? HTML::chars((string) $operation['description']) : ''; ?></textarea>
            </div>
        </div>

        <div class="flex items-center gap-2 pt-2">
            <button type="submit" class="px-5 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">
                <i class="fas fa-save mr-1"></i> Сохранить
            </button>
            <a href="<?php echo $operation ? '/shopfloor/operations/view/'.(int) $operation['id'] : '/shopfloor/operations'; ?>"
               class="px-5 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                Отмена
            </a>
        </div>
    </form>
</div>
