<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Бейдж состояния.
 *
 * Компонент: components/badge
 * Переменные:
 *   label — текст (обязательно); если не передан, берётся подпись состояния
 *   state — ok|warn|bad|info|neutral
 *   title — подсказка при наведении (что именно не так)
 *
 * Зачем: состояние должно читаться одинаково во всех модулях — цвет и слово
 * берутся из одного словаря `Component::states()`, а не подбираются в каждом
 * представлении.
 */
$state = isset($state) && $state ? $state : 'neutral';
$label = isset($label) && $label !== '' ? $label : Component::state_label($state);
$title = isset($title) ? $title : NULL;
?>
<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium <?php echo Component::state_class($state, 'badge'); ?>"
      <?php echo $title ? 'title="'.HTML::chars($title).'"' : ''; ?>>
    <?php echo HTML::chars($label); ?>
</span>
