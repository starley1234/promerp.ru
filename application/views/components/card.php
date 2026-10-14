<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Карточка (панель) — базовый контейнер страницы.
 *
 * Компонент: components/card
 * Переменные:
 *   title     — заголовок (экранируется)
 *   icon      — класс иконки, например 'fas fa-boxes'
 *   subtitle  — подпись под заголовком
 *   actions_html — кнопки/ссылки справа от заголовка (готовый HTML)
 *   body      — содержимое карточки (готовый HTML: либо через Component::begin/end)
 *   id        — id заголовка, если на него ссылается aria-labelledby
 *   flush     — TRUE: тело без внутренних отступов (для таблиц во всю ширину)
 *   state     — ok|warn|bad|info|neutral: цвет левой границы и фона шапки
 */
$title   = isset($title) ? $title : NULL;
$icon    = isset($icon) ? $icon : NULL;
$subtitle = isset($subtitle) ? $subtitle : NULL;
$actions = isset($actions_html) ? $actions_html : NULL;
$body    = isset($body) ? $body : '';
$id      = isset($id) ? $id : NULL;
$flush   = ! empty($flush);
$state   = isset($state) && $state ? $state : NULL;

$head_classes = 'flex flex-wrap items-start justify-between gap-3 px-5 py-3 border-b border-gray-200 dark:border-gray-700';

if ($state)
{
	$head_classes .= ' '.Component::state_class($state, 'bg');
}
?>

<section class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden <?php echo $state ? Component::state_class($state, 'border') : 'border border-transparent'; ?>"
         <?php echo $id ? 'aria-labelledby="'.HTML::chars($id).'"' : ''; ?>>

    <?php if ($title OR $actions): ?>
        <div class="<?php echo $head_classes; ?>">
            <div>
                <?php if ($title): ?>
                    <h2<?php echo $id ? ' id="'.HTML::chars($id).'"' : ''; ?> class="font-semibold text-gray-900 dark:text-white">
                        <?php if ($icon): ?><i class="<?php echo HTML::chars($icon); ?> mr-1 text-blue-600 dark:text-blue-400" aria-hidden="true"></i><?php endif; ?>
                        <?php echo HTML::chars($title); ?>
                    </h2>
                <?php endif; ?>
                <?php if ($subtitle): ?>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1"><?php echo HTML::chars($subtitle); ?></p>
                <?php endif; ?>
            </div>
            <?php if ($actions): ?>
                <div class="flex items-center gap-2 shrink-0"><?php echo $actions; ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($flush): ?>
        <?php echo $body; ?>
    <?php else: ?>
        <div class="p-5"><?php echo $body; ?></div>
    <?php endif; ?>
</section>
