<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Форма создания/редактирования источника данных MDM.
 * 
 * @var Model_Erp230_Source $source Объект источника
 * @var array               $errors Ошибки валидации
 * @var string              $mode   Режим: create|edit
 * @var array               $kinds  Список доступных типов (kind)
 * @var int                 $usage  Количество использований (только для edit)
 */
?>

<div>

    <!-- Заголовок и навигация -->
    <div class="mb-6">
        <nav class="flex mb-4" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="<?php echo URL::site('erp230_sources'); ?>" class="text-sm text-gray-500 hover:text-emerald-600 dark:text-gray-400 transition-colors">
                        <i class="fas fa-database mr-2"></i> Источники
                    </a>
                </li>
                <?php if ($mode === 'edit'): ?>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 text-xs mx-2"></i>
                        <a href="<?php echo URL::site('erp230_sources/view/'.$source->id); ?>" class="text-sm text-gray-500 hover:text-emerald-600 dark:text-gray-400 transition-colors">
                            <?php echo HTML::chars($source->id); ?>
                        </a>
                    </div>
                </li>
                <?php endif; ?>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 text-xs mx-2"></i>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            <?php echo $mode === 'create' ? 'Новый источник' : 'Редактирование'; ?>
                        </span>
                    </div>
                </li>
            </ol>
        </nav>
        <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight">
            <?php echo $mode === 'create' ? 'Создание источника данных' : 'Редактирование: ' . HTML::chars($source->id); ?>
        </h1>
    </div>

    <?php if ( ! empty($errors)): ?>
        <div class="mb-6 p-4 bg-rose-50 dark:bg-rose-900/20 border-l-4 border-rose-500 rounded-r-lg">
            <div class="flex">
                <div class="flex-shrink-0 text-rose-500">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-bold text-rose-800 dark:text-rose-300">Обнаружены ошибки:</h3>
                    <ul class="text-xs text-rose-700 dark:text-rose-400 mt-1 list-disc list-inside">
                        <?php foreach ($errors as $field => $error): ?>
                            <li><?php echo HTML::chars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="max-w-3xl">
        <form action="" method="POST" class="space-y-6">
            
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="p-6 space-y-6">
                    
                    <!-- ID Источника -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                        <label for="id" class="block text-sm font-bold text-gray-700 dark:text-gray-300 pt-2">
                            Идентификатор (ID) <span class="text-rose-500">*</span>
                        </label>
                        <div class="md:col-span-2">
                            <input type="text" name="id" id="id" 
                                   value="<?php echo HTML::chars($source->id); ?>"
                                   <?php echo $mode === 'edit' ? 'disabled' : 'required'; ?>
                                   class="w-full px-4 py-2 rounded-lg border <?php echo isset($errors['id']) ? 'border-rose-500' : 'border-gray-300 dark:border-gray-600'; ?> bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-500 outline-none transition-all <?php echo $mode === 'edit' ? 'opacity-60 cursor-not-allowed' : ''; ?>"
                                   placeholder="Например: sap_erp, plm_tc, manual_entry">
                            <p class="mt-1 text-xs text-gray-500">Уникальный код латиницей. Не может быть изменен после создания.</p>
                        </div>
                    </div>

                    <!-- Отображаемое имя -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                        <label for="display_name" class="block text-sm font-bold text-gray-700 dark:text-gray-300 pt-2">
                            Наименование <span class="text-rose-500">*</span>
                        </label>
                        <div class="md:col-span-2">
                            <input type="text" name="display_name" id="display_name" 
                                   value="<?php echo HTML::chars($source->display_name); ?>"
                                   required
                                   class="w-full px-4 py-2 rounded-lg border <?php echo isset($errors['display_name']) ? 'border-rose-500' : 'border-gray-300 dark:border-gray-600'; ?> dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-500 outline-none transition-all"
                                   placeholder="Например: SAP ERP (Производство)">
                        </div>
                    </div>

                    <!-- Тип источника -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                        <label for="kind" class="block text-sm font-bold text-gray-700 dark:text-gray-300 pt-2">
                            Тип источника
                        </label>
                        <div class="md:col-span-2">
                            <select name="kind" id="kind" 
                                    class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
                                <?php foreach ($kinds as $k => $v): ?>
                                    <option value="<?php echo HTML::chars($k); ?>" <?php echo $source->kind === $k ? 'selected' : ''; ?>>
                                        <?php echo HTML::chars($v); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Trust Score -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                        <label for="trust_score" class="block text-sm font-bold text-gray-700 dark:text-gray-300 pt-2">
                            Trust Score (0-100)
                        </label>
                        <div class="md:col-span-2">
                            <div class="flex items-center gap-4">
                                <input type="range" name="trust_score" id="trust_score" 
                                       min="0" max="100" step="1"
                                       value="<?php echo (int) $source->trust_score ?: 50; ?>"
                                       class="flex-grow h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-emerald-600"
                                       data-action="mirror-next">
                                <output class="w-12 text-center font-bold text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20 py-1 rounded border border-emerald-100 dark:border-emerald-800">
                                    <?php echo (int) $source->trust_score ?: 50; ?>
                                </output>
                            </div>
                            
                            <?php if ($mode === 'edit' && $usage > 0): ?>
                            <div class="mt-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800 rounded-lg">
                                <div class="flex items-start">
                                    <i class="fas fa-exclamation-triangle text-amber-500 mt-0.5 mr-2"></i>
                                    <p class="text-xs text-amber-800 dark:text-amber-300 leading-relaxed">
                                        <strong>Внимание:</strong> Источник используется в <?php echo $usage; ?> значениях. 
                                        Понижение балла может привести к тому, что данные из этого источника перестанут быть приоритетными при консолидации.
                                    </p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
                
                <div class="p-6 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <a href="<?php echo $mode === 'edit' ? URL::site('erp230_sources/view/'.$source->id) : URL::site('erp230_sources'); ?>" 
                       class="text-sm font-medium text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                        Отмена
                    </a>
                    <button type="submit" class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg shadow-sm transition-colors flex items-center">
                        <i class="fas fa-save mr-2"></i>
                        <?php echo $mode === 'create' ? 'Создать источник' : 'Сохранить изменения'; ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>