<?php defined('SYSPATH') or die('No direct script access.'); ?>

<?php
/**
 * Список плитками — вид компонента `table` в простом режиме (Ui_Mode).
 *
 * Не вызывается из представлений напрямую: `table` сам передаёт сюда те же
 * `caption`, `columns`, `rows` — модуль описывает данные один раз.
 *
 * Как строка становится плиткой:
 *   - первая ячейка — заголовок плитки (номер, название);
 *   - колонка-действие (подпись пустая, «Действие», «Действия» или «Открыть») —
 *     кнопки идут в низ плитки без подписи;
 *   - колонки с `priority` 1–2 — «факты» (подпись и значение), но не больше
 *     четырёх: плитка должна читаться с одного взгляда;
 *   - остальные колонки (`priority` 3–4 и всё сверх четырёх) — под «Подробнее»;
 *   - пустые ячейки не показываются;
 *   - класс строки (`class` в паре «класс + ячейки») подсвечивает плитку.
 *
 * Переменные: caption, columns, rows, aria_label — как у `table`.
 */
$caption   = isset($caption) ? $caption : 'Список';
$columns   = isset($columns) ? (array) $columns : array();
$rows      = isset($rows) ? (array) $rows : array();
$aria_label = isset($aria_label) ? $aria_label : $caption;
$max_facts = 4;
?>
<?php
// Отступ вокруг сетки: плитки — это тело таблицы внутри панели, почти всегда
// с `flush` (таблица во всю ширину). Без своих отступов плитки прилегают к
// краю панели — и выглядят не как содержимое, а как обрезка. Тот же принцип,
// что у `empty` (`px-5 py-10`): тело само отвечает за свои поля.
?>
<ul class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2 xl:grid-cols-3" aria-label="<?php echo HTML::chars($aria_label); ?>">
<?php foreach ($rows as $row): ?>
    <?php
    $cells = array_values((array) $row);
    $tone  = 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700';

    if (is_array($row) AND isset($row['cells']))
    {
        $cells = array_values((array) $row['cells']);
        $tone  = isset($row['class']) && $row['class'] !== '' ? (string) $row['class'].' border-gray-200 dark:border-gray-700' : $tone;
    }

    $title   = '';
    $facts   = array();
    $details = array();
    $actions = array();

    foreach ($cells as $index => $cell)
    {
        $column = isset($columns[$index]) ? (array) $columns[$index] : array();
        $label  = isset($column['label']) ? trim((string) $column['label']) : '';

        if ($index === 0)
        {
            $title = $cell;
            continue;
        }

        if (trim((string) $cell) === '')
        {
            continue;
        }

        if (in_array(mb_strtolower($label, 'UTF-8'), array('', 'действие', 'действия', 'открыть'), TRUE))
        {
            $actions[] = $cell;
            continue;
        }

        $priority = isset($column['priority']) ? (int) $column['priority'] : 1;

        if ($priority <= 2 AND count($facts) < $max_facts)
        {
            $facts[] = array($label, $cell);
        }
        else
        {
            $details[] = array($label, $cell);
        }
    }
    ?>
    <li class="rounded-xl border shadow-sm p-5 <?php echo HTML::chars($tone); ?>">
        <div class="text-base font-semibold text-gray-900 dark:text-white"><?php echo $title; ?></div>

        <?php if ($facts): ?>
            <dl class="mt-3 grid grid-cols-2 gap-4 text-sm">
                <?php foreach ($facts as $fact): ?>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($fact[0]); ?></dt>
                        <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100"><?php echo $fact[1]; ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>

        <?php if ($details): ?>
            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-medium text-blue-600 dark:text-blue-400">Подробнее</summary>
                <dl class="mt-3 grid grid-cols-2 gap-4 text-sm">
                    <?php foreach ($details as $fact): ?>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400"><?php echo HTML::chars($fact[0]); ?></dt>
                            <dd class="mt-0.5 text-gray-900 dark:text-gray-100"><?php echo $fact[1]; ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </details>
        <?php endif; ?>

        <?php if ($actions): ?>
            <div class="mt-4 flex flex-wrap items-center gap-2"><?php echo implode(' ', $actions); ?></div>
        <?php endif; ?>
    </li>
<?php endforeach; ?>
</ul>
