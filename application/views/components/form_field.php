<?php defined('SYSPATH') or die('No direct script access.');
$id = isset($id) ? (string) $id : 'field_'.uniqid();
$label = isset($label) ? $label : '';
$name = isset($name) ? (string) $name : $id;
$type = isset($type) ? (string) $type : 'text';
$value = isset($value) ? $value : '';
$hint = isset($hint) ? $hint : NULL;
$error = isset($error) ? $error : NULL;
$required = !empty($required);
// Operational forms must not be polluted by browser profile autofill. Auth and
// contact forms can explicitly pass a semantic token such as email, tel,
// current-password or new-password.
$autocomplete = isset($autocomplete) ? (string) $autocomplete : 'off';
?>
<?php
/*
 * Класс `field-label` задаёт подписи высоту ровно в одну строку (см.
 * input.css): в строке фильтров поля выравниваются по низу, и без этого поля
 * с подсказкой и без неё стоят на разной высоте.
 */
?>
<div class="space-y-1">
    <label for="<?php echo HTML::chars($id); ?>" class="field-label text-sm font-medium text-gray-700 dark:text-gray-200">
        <?php echo HTML::chars($label); ?><?php if ($required): ?> <span class="text-rose-600" aria-hidden="true">*</span><span class="sr-only">, обязательно</span><?php endif; ?>
    </label>
    <?php if ($type === 'textarea'): ?>
        <textarea id="<?php echo HTML::chars($id); ?>" name="<?php echo HTML::chars($name); ?>" autocomplete="<?php echo HTML::chars($autocomplete); ?>" <?php echo $required ? 'required' : ''; ?> <?php echo $error ? 'aria-invalid="true" aria-describedby="'.$id.'_error"' : ($hint ? 'aria-describedby="'.$id.'_hint"' : ''); ?> class="block w-full rounded-lg border border-gray-300 bg-white min-h-10 px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 dark:border-gray-600 dark:bg-gray-800 dark:text-white"><?php echo HTML::chars($value); ?></textarea>
    <?php else: ?>
        <input id="<?php echo HTML::chars($id); ?>" name="<?php echo HTML::chars($name); ?>" type="<?php echo HTML::chars($type); ?>" value="<?php echo HTML::chars($value); ?>" autocomplete="<?php echo HTML::chars($autocomplete); ?>" <?php echo $required ? 'required' : ''; ?> <?php echo $error ? 'aria-invalid="true" aria-describedby="'.$id.'_error"' : ($hint ? 'aria-describedby="'.$id.'_hint"' : ''); ?> class="block w-full rounded-lg border border-gray-300 bg-white min-h-10 px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
    <?php endif; ?>
    <?php if ($error): ?><p id="<?php echo HTML::chars($id); ?>_error" class="text-sm text-rose-600 dark:text-rose-400" role="alert"><?php echo HTML::chars($error); ?></p><?php elseif ($hint): ?><p id="<?php echo HTML::chars($id); ?>_hint" class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($hint); ?></p><?php endif; ?>
</div>
