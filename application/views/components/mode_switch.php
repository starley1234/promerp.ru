<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Переключатель режима интерфейса: «Простой | Профессиональный».
 *
 * Компонент: components/mode_switch
 * Переменные:
 *   class — дополнительные классы контейнера (отступы, скрытие при свёрнутом меню)
 *
 * Это две обычные ссылки на `/ui/mode/<режим>` (Controller_Erp100_Ui): работают
 * без JavaScript, в демо-режиме и для гостя. После выбора человек возвращается на
 * ту же страницу. Активный режим помечен `aria-current`.
 */
$class   = isset($class) ? $class : '';
$current = Ui_Mode::current();
$hints   = Ui_Mode::hints();
$route   = Route::get('erp100_ui_mode');

$initial = Request::initial();
$back    = '/'.ltrim($initial ? $initial->uri() : '', '/');
$query   = $initial ? (array) $initial->query() : array();

if ($query)
{
    $back .= '?'.http_build_query($query);
}
?>
<nav aria-label="Режим интерфейса" class="flex rounded-lg bg-gray-100 p-1 text-[11px] dark:bg-gray-700 <?php echo HTML::chars($class); ?>">
    <?php foreach (Ui_Mode::modes() as $key => $label): ?>
        <?php $active = ($key === $current); ?>
        <a href="/<?php echo HTML::chars($route->uri(array('mode' => $key)).'?back='.rawurlencode($back)); ?>"
           rel="nofollow"
           title="<?php echo HTML::chars($hints[$key]); ?>"
           <?php echo $active ? 'aria-current="true"' : ''; ?>
           class="flex-1 truncate rounded-md px-2 py-1.5 text-center transition-colors <?php echo $active
               ? 'bg-white font-semibold text-blue-700 shadow-sm dark:bg-gray-800 dark:text-blue-300'
               : 'text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white'; ?>">
            <?php echo HTML::chars($label); ?>
        </a>
    <?php endforeach; ?>
</nav>
