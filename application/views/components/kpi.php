<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Показатель (KPI) — цифра, которую можно прочитать однозначно.
 *
 * Компонент: components/kpi
 * Переменные:
 *   label   — что это за цифра (обязательно)
 *   value   — значение
 *   unit    — единица измерения: %, шт., кг, дн., ₽ (обязательно; «7020» без
 *             единицы читается как угодно)
 *   hint    — пояснение под цифрой (например, «из чего сложилось»)
 *   target  — целевое значение (число или строка)
 *   state   — ok|warn|bad|info|neutral (цвет цифры и рамки)
 *   url     — куда ведёт клик по плитке (тогда плитка — ссылка)
 *   trend   — ряд значений для спарклайна (мини-график без JavaScript)
 *   compact — TRUE: маленькая плитка без тренда и целей (в простом режиме
 *             интерфейса игнорируется: плитка всегда крупная)
 */
$label   = isset($label) ? $label : '';
$value   = isset($value) ? $value : '';
$unit    = isset($unit) ? $unit : '';
$hint    = isset($hint) ? $hint : NULL;
$target  = isset($target) ? $target : NULL;
$state   = isset($state) && $state ? $state : 'neutral';
$url     = isset($url) ? $url : NULL;
$trend   = isset($trend) ? (array) $trend : array();
// В простом режиме (Ui_Mode) мелких плиток нет: «крупная клетка» читается легче.
$compact = ( ! empty($compact) && Ui_Mode::pro());

$tile = 'block rounded-lg border bg-white dark:bg-gray-800 p-4 h-full';

if ($compact)
{
    $tile = 'block rounded border border-gray-100 dark:border-gray-700 px-3 py-2 h-full';
}
elseif ($url)
{
    $tile .= ' hover:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-500';
}
else
{
    $tile .= ' '.Component::state_class($state, 'border');
}

$tag  = $url ? 'a' : 'div';
$attr = $url ? ' href="'.HTML::chars($url).'"' : '';
?>
<<?php echo $tag.$attr; ?> class="<?php echo $tile; ?>">
    <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($label); ?></div>

    <div class="mt-1 flex items-end justify-between gap-2">
        <div class="text-2xl font-semibold tabular-nums <?php echo $compact ? 'text-gray-900 dark:text-white' : Component::state_class($state, 'text'); ?>">
            <?php echo HTML::chars($value); ?><?php if ($unit): ?><span class="text-sm font-normal text-gray-500 dark:text-gray-400"> <?php echo HTML::chars($unit); ?></span><?php endif; ?>
        </div>

        <?php if ( ! $compact AND count($trend) > 1): ?>
            <span class="shrink-0"><?php echo Component::sparkline($trend, 120, 28, $state); ?></span>
        <?php endif; ?>
    </div>

    <?php if ( ! $compact AND ($target !== NULL OR $state !== 'neutral')): ?>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            <?php if ($target !== NULL): ?>цель <?php echo HTML::chars((string) $target); ?><?php endif; ?>
            <?php if ($state !== 'neutral'): ?>
                <?php echo $target !== NULL ? '· ' : ''; ?><span class="<?php echo Component::state_class($state, 'text'); ?>"><?php echo HTML::chars(Component::state_label($state)); ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($hint): ?>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-2"><?php echo HTML::chars($hint); ?></div>
    <?php endif; ?>
</<?php echo $tag; ?>>
