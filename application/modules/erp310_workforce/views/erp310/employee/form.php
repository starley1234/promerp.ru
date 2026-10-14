<?php defined('SYSPATH') or die('No direct script access.');
$v=function($key,$default='') use($employee){return HTML::chars(Arr::get($employee,$key,$default));};
?>
<div class="mx-auto max-w-5xl space-y-5">
    <div><a href="/workforce/employees" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">← К списку</a><h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-2"><?php echo Arr::get($employee,'id')?'Карточка сотрудника':'Новый сотрудник'; ?></h1></div>
    <?php if ($errors): ?><div role="alert" class="rounded-lg border border-rose-300 bg-rose-50 dark:border-rose-800 dark:bg-rose-950/40 p-4 text-sm text-rose-800 dark:text-rose-200"><strong>Не удалось сохранить:</strong><ul class="list-disc ml-5 mt-1"><?php foreach($errors as $error): ?><li><?php echo HTML::chars(is_array($error)?implode(', ',$error):$error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" action="/workforce/employees/save" class="space-y-5">
        <input type="hidden" name="id" value="<?php echo (int)Arr::get($employee,'id'); ?>">
        <?php Component::begin('card', array('title'=>'Личные и контактные данные')); ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div><label for="personnel_no" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Табельный номер *</label><input required id="personnel_no" name="personnel_no" value="<?php echo $v('personnel_no'); ?>" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"></div>
            <div><label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Фамилия *</label><input required id="last_name" name="last_name" value="<?php echo $v('last_name'); ?>" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"></div>
            <div><label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Имя *</label><input required id="first_name" name="first_name" value="<?php echo $v('first_name'); ?>" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"></div>
            <div><label for="middle_name" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Отчество</label><input id="middle_name" name="middle_name" value="<?php echo $v('middle_name'); ?>" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"></div>
            <div><label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">E-mail</label><input type="email" id="email" name="email" value="<?php echo $v('email'); ?>" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"></div>
            <div><label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Телефон</label><input id="phone" name="phone" value="<?php echo $v('phone'); ?>" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"></div>
        </div><?php Component::end(); ?>
        <?php Component::begin('card', array('title'=>'Назначение и доступ','subtitle'=>'Учётная запись отвечает за вход, назначение — за место человека в организации.')); ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div><label for="department_id" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Подразделение *</label><select required id="department_id" name="department_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"><option value="">Выберите</option><?php foreach($departments as $d): ?><option value="<?php echo (int)$d->id; ?>"<?php echo (int)Arr::get($employee,'department_id')===(int)$d->id?' selected':''; ?>><?php echo HTML::chars($d->name); ?></option><?php endforeach; ?></select></div>
            <div><label for="position_id" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Должность</label><select id="position_id" name="position_id" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"><option value="">Не указана</option><?php foreach($positions as $p): ?><option value="<?php echo (int)$p->id; ?>"<?php echo (int)Arr::get($employee,'position_id')===(int)$p->id?' selected':''; ?>><?php echo HTML::chars($p->name); ?></option><?php endforeach; ?></select></div>
            <?php echo Component::render('smart_reference', array(
                'id'=>'user_id','name'=>'user_id','label'=>'Учётная запись',
                'url'=>'/workforce/api/reference?type=user&current_id='.(int)Arr::get($employee,'user_id'),
                'value'=>Arr::get($employee,'user_id'), 'selected_label'=>Arr::get($employee,'username'),
                'placeholder'=>'Логин или e-mail', 'hint'=>'Необязательно. Показываются только свободные учётные записи.'
            )); ?>
            <?php echo Component::render('smart_reference', array(
                'id'=>'manager_id','name'=>'manager_id','label'=>'Руководитель',
                'url'=>'/workforce/api/reference?type=employee&exclude_id='.(int)Arr::get($employee,'id'),
                'value'=>Arr::get($employee,'manager_id'), 'selected_label'=>Arr::get($employee,'manager_name'),
                'placeholder'=>'ФИО или табельный номер', 'hint'=>'Необязательно. Выберите действующего сотрудника.'
            )); ?>
            <div><label for="hired_at" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Дата приёма</label><input type="date" id="hired_at" name="hired_at" value="<?php echo $v('hired_at'); ?>" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"></div>
            <div><label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Статус</label><select id="status" name="status" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"><?php foreach($statuses as $code=>$label): ?><option value="<?php echo $code; ?>"<?php echo Arr::get($employee,'status','active')===$code?' selected':''; ?>><?php echo HTML::chars($label); ?></option><?php endforeach; ?></select></div>
            <div><label for="rate" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Ставка</label><input type="number" min="0.1" max="2" step="0.1" id="rate" name="rate" value="<?php echo $v('rate',1); ?>" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"></div>
            <input type="hidden" name="assignment_from" value="<?php echo $v('assignment_from',date('Y-m-d')); ?>">
        </div><?php Component::end(); ?>
        <div class="flex justify-end gap-3"><a href="/workforce/employees" class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm text-gray-700 dark:text-gray-200">Отмена</a><button class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700" type="submit">Сохранить</button></div>
    </form>
</div>
