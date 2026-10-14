<?php defined('SYSPATH') or die('No direct script access.');
$id = isset($id) ? (string) $id : 'reference_'.uniqid();
$name = isset($name) ? (string) $name : $id;
$label = isset($label) ? (string) $label : '';
$url = isset($url) ? (string) $url : '';
$value = isset($value) ? (string) $value : '';
$selected_label = isset($selected_label) ? (string) $selected_label : '';
$placeholder = isset($placeholder) ? (string) $placeholder : 'Начните вводить название…';
$hint = isset($hint) ? (string) $hint : 'Введите не менее 2 символов и выберите вариант из списка.';
$required = !empty($required);
$min_chars = isset($min_chars) ? max(0, (int) $min_chars) : 2;
?>
<div class="space-y-1" data-smart-reference data-url="<?php echo HTML::chars($url); ?>" data-min-chars="<?php echo $min_chars; ?>">
    <label for="<?php echo HTML::chars($id); ?>_search" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
        <?php echo HTML::chars($label); ?><?php if ($required): ?> <span class="text-rose-600" aria-hidden="true">*</span><?php endif; ?>
    </label>
    <div class="relative">
        <input type="hidden" id="<?php echo HTML::chars($id); ?>" name="<?php echo HTML::chars($name); ?>" value="<?php echo HTML::chars($value); ?>"<?php echo $required ? ' required' : ''; ?> data-reference-value>
        <input type="search" id="<?php echo HTML::chars($id); ?>_search" value="<?php echo HTML::chars($selected_label); ?>"
               placeholder="<?php echo HTML::chars($placeholder); ?>" autocomplete="off" role="combobox"
               aria-autocomplete="list" aria-expanded="false" aria-controls="<?php echo HTML::chars($id); ?>_results"
               class="block w-full rounded-lg border border-gray-300 bg-white min-h-10 py-2 pl-3 pr-9 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 dark:border-gray-600 dark:bg-gray-800 dark:text-white" data-reference-search>
        <button type="button" aria-label="Очистить выбор" title="Очистить выбор" class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-gray-400 hover:text-rose-600" data-reference-clear<?php echo $value === '' ? ' hidden' : ''; ?>>×</button>
        <div id="<?php echo HTML::chars($id); ?>_results" role="listbox" class="absolute z-40 mt-1 hidden max-h-64 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white p-1 shadow-xl dark:border-gray-600 dark:bg-gray-800" data-reference-results></div>
    </div>
    <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($hint); ?></p>
    <p class="sr-only" aria-live="polite" data-reference-status></p>
    <noscript><p class="text-sm text-rose-600">Для интерактивного выбора нужен JavaScript.</p></noscript>
</div>
