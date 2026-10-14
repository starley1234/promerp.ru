<?php defined('SYSPATH') or die('No direct script access.'); ?>

<div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            <i class="fas fa-calendar-alt mr-2 text-blue-600"></i> Смены и календарь · <?php echo HTML::chars($month_label); ?>
        </h1>

        <form autocomplete="off" method="get" action="/shopfloor/shifts" class="flex flex-wrap items-center gap-2 text-sm">
            <input type="month" name="month" value="<?php echo HTML::chars($month); ?>"
                   class="px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
            <select name="center_id" class="px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                <option value="0">Все центры (общий календарь)</option>
                <?php foreach ($centers as $center): ?>
                    <option value="<?php echo (int) $center['id']; ?>" <?php echo (int) $center_id === (int) $center['id'] ? 'selected' : ''; ?>>
                        <?php echo HTML::chars($center['code'].' · '.$center['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-4 py-2 rounded-md bg-gray-900 text-white hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-900">
                <i class="fas fa-eye mr-1"></i> Показать
            </button>
            <button type="submit" formaction="/shopfloor/shifts/generate" formmethod="post"
                    class="px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">
                <i class="fas fa-magic mr-1"></i> Заполнить месяц
            </button>
        </form>
    </div>

    <?php echo View::factory('erp408/partials/flash')->set('messages', $messages); ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="font-semibold text-gray-900 dark:text-white">Смены</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Шаблон «когда работают»: часы смены берутся из календаря дня.</p>
            </div>

            <?php
            $shift_rows=array();foreach($shifts['rows'] as $shift){$actions='<form autocomplete="off" method="post" action="/shopfloor/shifts/toggle/'.(int)$shift['id'].'" class="inline"><button type="submit" class="rounded-md border border-gray-300 px-3 py-1 text-xs dark:border-gray-600">'.($shift['is_active']?'Выключить':'Включить').'</button></form> <form autocomplete="off" method="post" action="/shopfloor/shifts/delete/'.(int)$shift['id'].'" class="inline" data-confirm="Удалить смену? Она удаляется только без операций."><button type="submit" class="rounded-md border border-red-300 px-3 py-1 text-xs text-red-700 dark:border-red-700 dark:text-red-300" aria-label="Удалить смену">×</button></form>';$shift_rows[]=array('class'=>$shift['is_active']?'':'opacity-60','cells'=>array('<span class="font-mono">'.HTML::chars($shift['code']).'</span><div class="text-xs text-gray-500 dark:text-gray-400">'.HTML::chars($shift['name']).'</div>',HTML::chars($shift['label']),$actions));}
            echo Component::render('table',array('caption'=>'Шаблоны смен','row_header'=>TRUE,'columns'=>array(array('label'=>'Смена'),array('label'=>'Время'),array('label'=>'Действия','align'=>'right','nowrap'=>TRUE)),'rows'=>$shift_rows,'empty_title'=>'Смен пока нет','empty_hint'=>'Добавьте первую смену в форме ниже.'));
            ?>

            <form autocomplete="off" method="post" action="/shopfloor/shifts/save" class="px-5 py-4 border-t border-gray-200 dark:border-gray-700 space-y-2 text-sm">
                <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Новая смена</div>
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" name="code" placeholder="S4" class="px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 font-mono">
                    <input type="text" name="name" placeholder="Четвёртая смена" class="px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                    <input type="time" name="start_time" value="08:00" class="px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                    <input type="time" name="end_time" value="16:00" class="px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                    <input type="number" step="0.5" min="0.5" max="24" name="duration_hours" value="8" class="px-3 py-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                    <button type="submit" class="px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">Добавить</button>
                </div>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="font-semibold text-gray-900 dark:text-white">Календарь месяца</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Рабочих дней: <?php echo (int) $calendar['working_days']; ?>, часов по записям: <?php echo number_format($calendar['hours_total'], 0, '.', ' '); ?>.
                        Если записей на день нет, будни считаются рабочими по 8 часов.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <?php
                $calendar_rows=array();$cursor=strtotime($from);$last=strtotime($to);
                while($cursor<=$last){$day=date('Y-m-d',$cursor);$info=isset($calendar['days'][$day])?$calendar['days'][$day]:NULL;$isodow=(int)date('N',$cursor);$entries='—';if($info){$parts=array();foreach($info['entries'] as $entry)$parts[]='<span class="mr-2 inline-block '.($entry['is_working']?'text-green-700 dark:text-green-400':'text-red-600').'">'.($entry['is_working']?number_format($entry['hours'],1,'.',' ').' ч':'нерабочий').'</span>';$entries=implode('',$parts);}$actions='<form autocomplete="off" method="post" action="/shopfloor/shifts/day" class="flex items-center justify-end gap-1"><input type="hidden" name="day" value="'.HTML::chars($day).'"><input type="hidden" name="work_center_id" value="'.(int)$center_id.'"><input type="number" step="0.5" min="0" max="24" name="hours" value="'.(($info AND $info['is_working'])?(float)$info['hours']:8).'" autocomplete="off" class="w-16 rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"><input type="hidden" name="is_working" value="1"><button type="submit" class="rounded-md bg-blue-600 px-2 py-1 text-xs text-white">Рабочий</button></form><form autocomplete="off" method="post" action="/shopfloor/shifts/day" class="mt-1 text-right"><input type="hidden" name="day" value="'.HTML::chars($day).'"><input type="hidden" name="work_center_id" value="'.(int)$center_id.'"><input type="hidden" name="is_working" value="0"><input type="hidden" name="hours" value="0"><input type="hidden" name="note" value="Нерабочий день"><button type="submit" class="rounded-md border border-gray-300 px-2 py-1 text-xs dark:border-gray-600">Выходной</button></form>';$calendar_rows[]=array('class'=>($info AND $info['is_working'])?'':'bg-gray-50 dark:bg-gray-700/30','cells'=>array(date('d.m',$cursor).'<div class="text-xs text-gray-500 dark:text-gray-400">'.array(1=>'пн','вт','ср','чт','пт','сб','вс')[$isodow].($isodow>=6?' · выходной по умолчанию':'').'</div>',$entries,($info AND $info['is_working'])?number_format($info['hours'],1,'.',' '):'—',$actions));$cursor=strtotime('+1 day',$cursor);}
                echo Component::render('table',array('caption'=>'Производственный календарь за '.(string)$month_label,'sticky'=>TRUE,'columns'=>array(array('label'=>'День','nowrap'=>TRUE),array('label'=>'Записи календаря'),array('label'=>'Часов','align'=>'right'),array('label'=>'Отметить день','align'=>'right')),'rows'=>$calendar_rows));
                ?>
            </div>
        </div>
    </div>
</div>
