<?php defined('SYSPATH') or die('No direct script access.'); ?>
<?php
/**
 * Обратный обход структуры: где используется компонент.
 *
 * Ожидает: $object, $name, $type, $depth, $used_in.
 * См. Controller_Erp230_Structure::action_where_used().
 */
$name    = isset($name) ? $name : (string) $object->master_code;
$type    = isset($type) ? $type : 'ebom';
$depth   = isset($depth) ? (int) $depth : 1;
$used_in = isset($used_in) ? $used_in : [];
?>
<div>
    <div class="mb-5">
        <a href="<?php echo URL::site('erp230_objects/view/'.$object->id); ?>" class="text-sm text-blue-600 hover:underline">
            <i class="fas fa-arrow-left mr-1"></i> Карточка объекта
        </a>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white mt-1">
            Где используется: <?php echo HTML::chars($name); ?>
        </h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Тип связи <span class="font-mono"><?php echo HTML::chars($type); ?></span>, глубина поиска: <?php echo $depth; ?>
        </p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <table class="w-full text-left text-sm">
                    <caption class="sr-only">Таблица данных</caption>
            <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-500 uppercase text-[10px] tracking-wider">
                <tr>
                    <th scope="col" class="px-4 py-3 font-semibold">Изделие</th>
                    <th scope="col" class="px-4 py-3 font-semibold">Код</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-right">Количество</th>
                    <th scope="col" class="px-4 py-3 font-semibold text-right">Уровень</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php if (count($used_in)): ?>
                    <?php foreach ($used_in as $row): ?>
                        <?php $parent = is_array($row) ? Arr::get($row, 'parent') : $row; ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200">
                                <?php echo HTML::chars($parent ? $parent->display_name() : '—'); ?>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-500">
                                <?php echo HTML::chars($parent ? $parent->master_code : ''); ?>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">
                                <?php echo is_array($row) ? (float) Arr::get($row, 'qty', 1) : '—'; ?>
                            </td>
                            <td class="px-4 py-3 text-right text-xs text-gray-400">
                                <?php echo is_array($row) ? (int) Arr::get($row, 'level', 1) : ''; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-400 italic">
                            Компонент не используется ни в одной структуре
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
