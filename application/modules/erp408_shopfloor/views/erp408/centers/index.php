<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-industry mr-2 text-blue-600"></i> Рабочие центры
        </h1>
        <a href="/shopfloor/centers/edit" class="px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700 text-sm">
            <i class="fas fa-plus mr-1"></i> Новый центр
        </a>
    </div>

    <?php echo View::factory('erp408/partials/flash')->set('messages', $messages); ?>

    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-3">
        <?php echo Component::render('kpi',array('label'=>'Всего центров','value'=>(int)$statistics['total'],'unit'=>'шт.','hint'=>'Рабочие центры в справочнике')); ?>
        <?php echo Component::render('kpi',array('label'=>'Включено','value'=>(int)$statistics['active'],'unit'=>'шт.','hint'=>'Доступны для назначения операций','state'=>'ok')); ?>
        <?php echo Component::render('kpi',array('label'=>'Участков','value'=>(int)$statistics['areas'],'unit'=>'шт.','hint'=>'Производственные зоны')); ?>
    </div>

    <form autocomplete="off" method="get" action="/shopfloor/centers" class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6 flex flex-wrap items-end gap-3 text-sm">
        <div class="flex-1 min-w-[12rem]">
            <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Поиск</label>
            <input type="text" name="term" value="<?php echo HTML::chars((string) $filters['term']); ?>" placeholder="код, название, цех"
                   class="w-full px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
        </div>
        <div>
            <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Вид</label>
            <select name="kind" class="px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                <option value="">Любой</option>
                <?php foreach ($kinds as $key => $label): ?>
                    <option value="<?php echo HTML::chars($key); ?>" <?php echo $filters['kind'] === $key ? 'selected' : ''; ?>><?php echo HTML::chars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs uppercase text-gray-500 dark:text-gray-400 mb-1">Активность</label>
            <select name="active" class="px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                <option value="">Все</option>
                <option value="1" <?php echo $filters['active'] === '1' ? 'selected' : ''; ?>>Включённые</option>
                <option value="0" <?php echo $filters['active'] === '0' ? 'selected' : ''; ?>>Выключенные</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 rounded-md bg-gray-900 text-white hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-900">
            <i class="fas fa-search"></i>
        </button>
    </form>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <?php if (empty($centers)): ?>
            <p class="px-5 py-10 text-sm text-gray-500 dark:text-gray-400">Рабочих центров нет. Заведите первый — операции нужно на что-то назначать.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <?php
            $center_rows = array();
            foreach ($centers as $center)
            {
                $name = '<a href="/shopfloor/centers/edit/'.(int) $center['id'].'" class="text-blue-600 hover:text-blue-800">'.HTML::chars($center['name']).'</a>';
                if ($center['note']) $name .= '<div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($center['note']).'</div>';
                $actions = '<form autocomplete="off" method="post" action="/shopfloor/centers/toggle/'.(int) $center['id'].'" class="inline"><button type="submit" class="rounded-md border border-gray-300 px-3 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">'.($center['is_active'] ? 'Выключить' : 'Включить').'</button></form> ';
                $actions .= '<form autocomplete="off" method="post" action="/shopfloor/centers/delete/'.(int) $center['id'].'" class="inline" data-confirm="Удалить центр? Он удаляется только без операций."><button type="submit" class="rounded-md border border-red-300 px-3 py-1 text-xs text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-300">Удалить</button></form>';
                $center_rows[] = array(
                    '<span class="font-mono">'.HTML::chars($center['code']).'</span>', $name,
                    HTML::chars($center['kind_label']), $center['workshop'] ? HTML::chars($center['workshop']) : '—',
                    $center['capacity_per_hour'] !== NULL ? number_format($center['capacity_per_hour'], 1, '.', ' ') : '—',
                    $center['cost_per_hour'] !== NULL ? number_format($center['cost_per_hour'], 2, '.', ' ').' ₽/ч' : '—',
                    Component::render('badge', array('label'=>$center['is_active'] ? 'включён' : 'выключен', 'state'=>$center['is_active'] ? 'ok' : 'neutral')),
                    $actions,
                );
            }
            echo Component::render('table', array(
                'caption'=>'Рабочие центры цеха', 'row_header'=>TRUE, 'sticky'=>TRUE,
                'columns'=>array(
                    array('label'=>'Код','nowrap'=>TRUE), array('label'=>'Название'), array('label'=>'Вид','priority'=>3),
                    array('label'=>'Цех','priority'=>3), array('label'=>'В час','align'=>'right','priority'=>2),
                    array('label'=>'Нормо-час','align'=>'right','priority'=>2), array('label'=>'Состояние'),
                    array('label'=>'Действия','align'=>'right','nowrap'=>TRUE),
                ), 'rows'=>$center_rows,
            ));
            ?>
            </div>
        <?php endif; ?>
    </div>
</div>
