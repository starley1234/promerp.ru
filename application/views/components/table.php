<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Таблица — один компонент на весь интерфейс.
 *
 * Компонент: components/table
 * Переменные:
 *   caption     — подпись таблицы (что за данные; видна только скринридером)
 *   columns     — массив колонок: array('label' => '…', 'align' => 'right',
 *                 'priority' => 1, 'nowrap' => TRUE, 'class' => '…')
 *                 priority: 1 — видна всегда, 2 — скрывается до sm,
 *                 3 — до md, 4 — до lg
 *   rows        — массив строк; значение ячейки — готовый HTML (экранируйте
 *                 данные сами: таблица не знает, что в ячейке). Строку можно
 *                 передать и парой: array('class' => 'bg-amber-50', 'cells' =>
 *                 array(…)) — когда всю строку нужно подсветить (проблема,
 *                 выбранный документ)
 *   row_header  — TRUE: первая ячейка строки — <th scope="row">
 *   row_header_compact — TRUE: не резервировать минимальную ширину для
 *                 короткого технического ключа (например, номера строки)
 *   empty_title / empty_hint — что показать, если строк нет
 *   aria_label  — имя области прокрутки для скринридера
 *   sticky      — TRUE: шапка «прилипает» при прокрутке длинного списка
 *
 * Режим интерфейса (Ui_Mode): в простом режиме те же `columns` и `rows` рисуются
 * плитками (components/table_tiles), в профессиональном — таблицей. Представление
 * об этом не знает; `priority` колонки заодно говорит, что главное для плитки.
 *
 * Правила компонента (из docs/FRONTEND_REVIEW.md): у таблицы всегда есть
 * подпись, заголовки со `scope`, а на узком экране — горизонтальная прокрутка,
 * а не обрезанный край. Приоритет колонок позволяет убрать на телефоне
 * второстепенное, не теряя главное.
 */
$caption    = isset($caption) && trim((string) $caption) !== '' ? $caption : 'Таблица данных';
$columns    = isset($columns) ? (array) $columns : array();
$rows       = isset($rows) ? (array) $rows : array();
$row_header = ! empty($row_header);
$row_header_compact = ! empty($row_header_compact);
$empty_title = isset($empty_title) ? $empty_title : 'Данных нет';
$empty_hint  = isset($empty_hint) ? $empty_hint : NULL;
$aria_label  = isset($aria_label) ? $aria_label : ($caption ? $caption : 'Таблица данных');
$sticky      = ! empty($sticky);

$hidden = array(
    2 => 'hidden sm:table-cell',
    3 => 'hidden md:table-cell',
    4 => 'hidden lg:table-cell',
);
?>
<?php if (empty($rows)): ?>

    <?php echo Component::render('empty', array('title' => $empty_title, 'hint' => $empty_hint)); ?>

<?php elseif (Ui_Mode::simple()): ?>

    <?php echo Component::render('table_tiles', array(
        'caption'    => $caption,
        'columns'    => $columns,
        'rows'       => $rows,
        'aria_label' => $aria_label,
    )); ?>

<?php else: ?>

    <div class="overflow-x-auto" role="region" aria-label="<?php echo HTML::chars($aria_label); ?>" tabindex="0">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <?php if ($row_header && ! $row_header_compact && $columns): ?>
                <colgroup>
                    <col style="width: 12rem; min-width: 12rem;">
                    <?php for ($column_index = 1, $column_count = count($columns); $column_index < $column_count; $column_index++): ?>
                        <col>
                    <?php endfor; ?>
                </colgroup>
            <?php endif; ?>

            <?php if ($caption): ?>
                <caption class="sr-only"><?php echo HTML::chars($caption); ?></caption>
            <?php endif; ?>

            <?php if ($columns): ?>
                <thead class="bg-gray-50 dark:bg-gray-700 <?php echo $sticky ? 'sticky top-0 z-10' : ''; ?>">
                <tr>
                    <?php foreach ($columns as $index => $column): ?>
                        <?php
                        $align    = isset($column['align']) ? $column['align'] : 'left';
                        $priority = isset($column['priority']) ? (int) $column['priority'] : 1;
                        $classes  = 'px-4 py-3 font-medium text-gray-600 dark:text-gray-200 '
                            .($align === 'right' ? 'text-right ' : 'text-left ')
                            .(! empty($column['nowrap']) ? 'whitespace-nowrap ' : '')
                            .(isset($hidden[$priority]) ? $hidden[$priority].' ' : '')
                            .(isset($column['class']) ? $column['class'] : '');
                        ?>
                        <th scope="col" class="<?php echo trim($classes); ?>"<?php echo $row_header && $index === 0 && ! $row_header_compact ? ' style="width: 12rem; min-width: 12rem;"' : ''; ?>><?php echo HTML::chars($column['label']); ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
            <?php endif; ?>

            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <?php foreach ($rows as $row): ?>
                <?php
                // Строка — либо список ячеек, либо пара «класс + ячейки».
                $cells = array_values((array) $row);
                $tr    = '';

                if (is_array($row) AND isset($row['cells']))
                {
                    $cells = array_values((array) $row['cells']);
                    $tr    = isset($row['class']) ? (string) $row['class'] : '';
                }
                ?>
                <tr class="<?php echo HTML::chars($tr); ?>">
                    <?php foreach ($cells as $index => $cell): ?>
                        <?php
                        $column   = isset($columns[$index]) ? (array) $columns[$index] : array();
                        $align    = isset($column['align']) ? $column['align'] : 'left';
                        $priority = isset($column['priority']) ? (int) $column['priority'] : 1;
                        $classes  = 'px-4 py-3 '
                            .($align === 'right' ? 'text-right ' : 'text-left ')
                            .(! empty($column['nowrap']) ? 'whitespace-nowrap ' : '')
                            .($row_header && $index === 0 && ! $row_header_compact ? 'align-top ' : '')
                            .(isset($hidden[$priority]) ? $hidden[$priority].' ' : '')
                            .(isset($column['class']) ? $column['class'] : '');
                        ?>
                        <?php if ($row_header AND $index === 0): ?>
                            <th scope="row" class="<?php echo trim($classes); ?> font-medium text-gray-900 dark:text-white"<?php echo ! $row_header_compact ? ' style="width: 12rem; min-width: 12rem; overflow: visible;"' : ''; ?>>
                                <?php if ($row_header_compact): ?>
                                    <?php echo $cell; ?>
                                <?php else: ?>
                                    <div style="box-sizing: border-box; display: block; width: 12rem; min-width: 12rem; white-space: normal; overflow-wrap: anywhere; overflow: visible;"><?php echo $cell; ?></div>
                                <?php endif; ?>
                            </th>
                        <?php else: ?>
                            <td class="<?php echo trim($classes); ?> text-gray-700 dark:text-gray-200"><?php echo $cell; ?></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>
