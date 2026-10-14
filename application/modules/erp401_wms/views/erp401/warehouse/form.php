<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php $is_new = ! $warehouse->loaded(); ?>

<div>

    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
        <i class="fas fa-warehouse mr-2 text-blue-600"></i>
        <?php echo $is_new ? 'Новый склад' : 'Редактирование склада'; ?>
    </h1>

    <?php echo View::factory('erp401/partials/flash')->set('messages', isset($messages) ? $messages : array()); ?>

    <?php if ( ! empty($errors)): ?>
        <div class="mb-4 border border-red-200 bg-red-50 dark:bg-red-900/30 dark:border-red-700 text-red-800 dark:text-red-200 rounded-md px-4 py-3 text-sm">
            <?php foreach ($errors as $error): ?>
                <div><i class="fas fa-exclamation-circle mr-1"></i> <?php echo HTML::chars(is_array($error) ? implode(', ', $error) : $error); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/warehouse/warehouses/save"
          class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 space-y-5 max-w-2xl">

        <input type="hidden" name="id" value="<?php echo (int) $warehouse->id; ?>">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Код</label>
                <input type="text" id="code" name="code" required maxlength="32"
                       value="<?php echo HTML::chars((string) $warehouse->code); ?>"
                       placeholder="WH-MAIN"
                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
            </div>

            <div>
                <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Тип склада</label>
                <select id="type" name="type"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                    <?php foreach ($types as $key => $label): ?>
                        <option value="<?php echo HTML::chars($key); ?>"
                            <?php echo ($warehouse->type === $key) ? 'selected' : ''; ?>>
                            <?php echo HTML::chars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Наименование</label>
            <input type="text" id="name" name="name" required maxlength="255"
                   value="<?php echo HTML::chars((string) $warehouse->name); ?>"
                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
        </div>

        <div>
            <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Адрес / расположение</label>
            <input type="text" id="address" name="address" maxlength="255"
                   value="<?php echo HTML::chars((string) $warehouse->address); ?>"
                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Описание</label>
            <textarea id="description" name="description" rows="3"
                      class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm"><?php echo HTML::chars((string) $warehouse->description); ?></textarea>
        </div>

        <div class="flex items-center space-x-3">
            <button type="submit"
                    class="inline-flex items-center px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">
                <i class="fas fa-save mr-2"></i> Сохранить
            </button>
            <a href="/warehouse/warehouses"
               class="px-5 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                Отмена
            </a>
        </div>
    </form>

</div>
