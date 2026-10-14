<?php defined('SYSPATH') or die('No direct script access.');
$applied_count = count($inventory) - count($pending);
$rows = array();
foreach ($inventory as $row)
{
    $rows[] = array('cells' => array(
        Component::render('badge', array('label'=>$row['applied']?'применена':'ожидает','state'=>$row['applied']?'ok':'warn')),
        '<span class="font-mono text-xs">'.HTML::chars($row['class']).'</span>',
        HTML::chars($row['module']), HTML::chars($row['db_group']),
        $row['applied_at'] ? HTML::chars($row['applied_at']) : '—',
    ));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Миграции базы данных · PromERP</title>
    <link rel="stylesheet" href="<?php echo HTML::chars(Assets::url('css')); ?>"><link rel="stylesheet" href="<?php echo HTML::chars(Assets::url('fontawesome')); ?>">
</head>
<body class="min-h-screen bg-gray-50 text-gray-800 dark:bg-gray-900 dark:text-gray-100">
<div class="container mx-auto max-w-7xl px-4 py-6 space-y-6">
    <div>
        <p class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">ERP111 · схема базы данных</p>
        <h1 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">Миграции</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Проверка и обновление структуры всех подключённых баз без командной строки.</p>
    </div>

    <?php if (!empty($configuration_warning)): ?><div role="alert" class="rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm text-rose-900 dark:border-rose-800 dark:bg-rose-950/30 dark:text-rose-200"><strong>Ошибка настройки баз:</strong> <?php echo HTML::chars($configuration_warning); ?></div><?php endif; ?>
    <?php if ($message): ?><?php echo Component::render('toast', array('message'=>$message,'type'=>$message_type)); ?><?php endif; ?>
    <?php if (!empty($operation_log)): ?>
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800" role="status">
            <h2 class="mb-2 font-semibold">Результат запуска</h2>
            <div class="space-y-1 font-mono text-xs">
                <?php foreach($operation_log as $entry): ?><p class="<?php echo $entry['level']==='error'?'text-rose-600':($entry['level']==='ok'?'text-emerald-600':'text-gray-600 dark:text-gray-300'); ?>"><?php echo HTML::chars($entry['message']); ?></p><?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <?php echo Component::render('kpi',array('label'=>'Найдено','value'=>count($inventory),'unit'=>'миграций','state'=>'info')); ?>
        <?php echo Component::render('kpi',array('label'=>'Применено','value'=>$applied_count,'unit'=>'миграций','state'=>'ok')); ?>
        <?php echo Component::render('kpi',array('label'=>'Ожидает','value'=>count($pending),'unit'=>'миграций','state'=>$pending?'warn':'ok')); ?>
    </div>

    <?php Component::begin('card',array('title'=>'Обновление схемы','subtitle'=>'Каждая миграция выполняется в отдельной транзакции. При ошибке текущая миграция откатывается.')); ?>
    <?php if ($pending): ?>
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
            Будут применены: <strong><?php echo count($pending); ?></strong>. Не закрывайте страницу до появления результата.
        </div>
        <form method="post" action="<?php echo HTML::chars($action_url); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo HTML::chars($csrf_token); ?>">
            <input type="hidden" name="operation" value="run">
            <button type="submit" data-confirm="Применить все ожидающие миграции базы данных?" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                <i class="fas fa-database" aria-hidden="true"></i>Применить ожидающие миграции
            </button>
        </form>
    <?php else: ?>
        <p class="text-sm font-medium text-emerald-700 dark:text-emerald-300"><i class="fas fa-circle-check mr-2" aria-hidden="true"></i>Схема актуальна, запускать ничего не нужно.</p>
    <?php endif; ?>
    <?php Component::end(); ?>

    <?php if ($orphans): ?>
        <div role="alert" class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
            <strong>Записи без файлов миграций:</strong> <?php echo HTML::chars(implode(', ',array_keys($orphans))); ?>. Они не откатываются автоматически.
        </div>
    <?php endif; ?>

    <?php echo Component::render('table',array('caption'=>'Все миграции проекта','columns'=>array(
        array('label'=>'Состояние'),array('label'=>'Миграция'),array('label'=>'Модуль'),array('label'=>'База'),array('label'=>'Применена')
    ),'rows'=>$rows,'empty_title'=>'Миграции не найдены')); ?>
    <p><a href="/" class="text-sm font-semibold text-blue-600 hover:underline">← Вернуться в PromERP</a></p>
</div>
<script src="<?php echo HTML::chars(Assets::url('theme_init')); ?>"></script><script src="<?php echo HTML::chars(Assets::url('app_js')); ?>" defer></script>
</body></html>
