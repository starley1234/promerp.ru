<?php defined('SYSPATH') or die('No direct script access.');
$input = 'mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 dark:border-gray-600 dark:bg-gray-800 dark:text-white';
$table_rows = array();
foreach ($packages as $package)
{
    $state = $package['status']==='done' ? 'ok' : (in_array($package['status'], array('failed','partial'), TRUE) ? 'bad' : 'info');
    $table_rows[] = array(
        '<a class="font-mono font-medium text-blue-700 hover:underline dark:text-blue-400" href="'.Route::url('erp802',array('action'=>'view','id'=>$package['id'])).'">#'.HTML::chars($package['id']).'</a>',
        HTML::chars($package['source_name']),
        HTML::chars($package['dry_run'] ? 'Только проверка' : 'Импорт'),
        Component::render('badge', array('label'=>$package['status'],'state'=>$state)),
        HTML::chars($package['applied_rows'].' / '.$package['skipped_rows'].' / '.$package['error_rows']),
        HTML::chars($package['created_at']),
    );
}
?>
<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Интеграционные пакеты</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Контролируемый обмен с файлами и 1С: проверка, построчный протокол и идемпотентная обработка.</p>
    </header>

    <?php if ($error): ?><div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200"><?php echo HTML::chars($error); ?></div><?php endif; ?>

    <?php if ($can_import): ?>
        <?php Component::begin('card', array('title'=>'Загрузить пакет','subtitle'=>'Сначала оставьте режим «Только проверить», изучите протокол и лишь затем запускайте импорт.','icon'=>'fas fa-file-import','id'=>'new-package')); ?>
        <form method="post" enctype="multipart/form-data" autocomplete="off" class="grid gap-4 md:grid-cols-4">
            <input type="hidden" name="csrf_token" value="<?php echo Security::token(); ?>">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Источник<select name="source_code" class="<?php echo $input; ?>"><?php foreach($sources as $source): ?><option value="<?php echo HTML::chars($source['code']); ?>"><?php echo HTML::chars($source['name'].' ('.$source['code'].')'); ?></option><?php endforeach; ?></select></label>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">CSV / JSON<input required type="file" name="package" accept=".csv,.json,application/json,text/csv" class="mt-2 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-blue-700 dark:text-gray-300 dark:file:bg-blue-950 dark:file:text-blue-300"></label>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Номер пакета во внешней системе<input name="external_batch_id" autocomplete="off" class="<?php echo $input; ?>" placeholder="необязательно"></label>
            <div class="flex flex-wrap items-end gap-3"><label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200"><input type="checkbox" name="dry_run" value="1" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"> Только проверить</label><button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Загрузить</button></div>
        </form>
        <?php Component::end(); ?>
    <?php endif; ?>

    <?php Component::begin('card', array('title'=>'Журнал пакетов','icon'=>'fas fa-list-check','flush'=>TRUE)); ?>
        <?php echo Component::render('table', array(
            'caption'=>'Журнал интеграционных пакетов',
            'columns'=>array(
                array('label'=>'Пакет','priority'=>1), array('label'=>'Источник','priority'=>1), array('label'=>'Режим','priority'=>2),
                array('label'=>'Статус','priority'=>1), array('label'=>'Применено / пропущено / ошибки','priority'=>3), array('label'=>'Создан','priority'=>3,'nowrap'=>TRUE),
            ),
            'rows'=>$table_rows, 'row_header'=>TRUE, 'sticky'=>TRUE,
            'empty_title'=>'Пакетов пока нет', 'empty_hint'=>'Загрузите CSV или JSON в режиме проверки — данные не будут изменены.',
        )); ?>
    <?php Component::end(); ?>
</div>
