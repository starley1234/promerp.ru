<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php
/**
 * Форма создания/редактирования типа изделия (справочник MDM).
 *
 * Ожидает: $type (Model_Erp230_Type), $mode ('create'|'edit'),
 *          $parents (id => display_name с отступами), $errors (список ошибок).
 */
$mode    = isset($mode) ? $mode : 'create';
$errors  = isset($errors) ? (array) $errors : [];
$parents = isset($parents) ? (array) $parents : [];
$is_edit = ($mode === 'edit' AND isset($type) AND $type->loaded());

$action = $is_edit
    ? URL::site('erp230_type_save/'.$type->id)
    : URL::site('erp230_type_save');

$schema = ($is_edit AND ! empty($type->schema))
    ? (is_string($type->schema) ? $type->schema : json_encode($type->schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))
    : '';
?>
<div class="mx-auto max-w-3xl">
    <div class="mb-6">
        <a href="<?php echo URL::site('erp230_types'); ?>" class="text-sm text-blue-600 hover:underline">
            <i class="fas fa-arrow-left mr-1"></i> К справочнику типов
        </a>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white mt-2">
            <?php echo $is_edit ? 'Редактирование типа: '.HTML::chars($type->display_name) : 'Новый тип изделия'; ?>
        </h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Тип задаёт классификацию мастер-данных и JSON-схему атрибутов объектов.
        </p>
    </div>

    <?php if ($errors): ?>
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-300">
            <ul class="list-disc list-inside space-y-1">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo HTML::chars(is_array($error) ? implode('; ', $error) : $error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo $action; ?>" class="space-y-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
        <?php echo Form::hidden('csrf_token', Security::token()); ?>

        <div>
            <label for="display_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Название типа <span class="text-red-500">*</span>
            </label>
            <input type="text" name="display_name" id="display_name" required maxlength="255"
                   value="<?php echo HTML::chars($is_edit ? $type->display_name : Arr::get($_POST, 'display_name', '')); ?>"
                   class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
        </div>

        <div>
            <label for="id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Идентификатор (латиницей, a-z0-9_)
            </label>
            <input type="text" name="id" id="id" pattern="[a-z0-9_]*" maxlength="64"
                   <?php echo $is_edit ? 'readonly' : ''; ?>
                   value="<?php echo HTML::chars($is_edit ? $type->id : Arr::get($_POST, 'id', '')); ?>"
                   class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm font-mono bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 disabled:bg-gray-100">
            <?php if ($is_edit): ?>
                <p class="text-xs text-gray-400 mt-1">Идентификатор нельзя изменить после создания.</p>
            <?php endif; ?>
        </div>

        <div>
            <label for="parent_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Родительский тип
            </label>
            <select name="parent_id" id="parent_id"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                <option value="">— корневой уровень —</option>
                <?php foreach ($parents as $id => $name): ?>
                    <option value="<?php echo HTML::chars($id); ?>"
                        <?php echo ($is_edit AND (string) $type->parent_id === (string) $id) ? 'selected' : ''; ?>>
                        <?php echo HTML::chars($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="schema" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                JSON-схема атрибутов
            </label>
            <textarea name="schema" id="schema" rows="10" spellcheck="false"
                      class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-xs font-mono bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                      placeholder='{"weight": {"type": "number", "unit": "кг"}}'><?php echo HTML::chars($schema); ?></textarea>
            <p class="text-xs text-gray-400 mt-1">
                Опишите дополнительные атрибуты объектов этого типа. Пустое значение допустимо.
            </p>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="<?php echo URL::site('erp230_types'); ?>"
               class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                Отмена
            </a>
            <button type="submit"
                    class="px-4 py-2 text-sm rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium">
                <?php echo $is_edit ? 'Сохранить изменения' : 'Создать тип'; ?>
            </button>
        </div>
    </form>
</div>
