<?php defined('SYSPATH') or die('No direct script access.');

$s = function (array $properties, array $required = array()) {
    return array('type' => 'object', 'properties' => $properties, 'required' => $required);
};
$f = function ($type, $description, array $extra = array()) {
    return array('type' => $type, 'description' => $description) + $extra;
};

return array(
    'module' => 'erp310_workforce',
    'title' => 'Сотрудники и организационная структура',
    'description' => 'Кадровый справочник: сотрудники, учётные записи, подразделения, должности и история назначений.',
    'tools' => array(
        'workforce.employees.search' => array(
            'title' => 'Поиск сотрудников',
            'description' => 'Ищет сотрудников по ФИО, табельному номеру, телефону или e-mail с фильтром подразделения и статуса. Возвращает текущие назначения и связь с учётной записью.',
            'scope' => 'erp310.view', 'mutating' => FALSE, 'destructive' => FALSE, 'idempotent' => TRUE,
            'schema' => $s(array(
                'term' => $f('string', 'ФИО, табельный номер, телефон или e-mail'),
                'department_id' => $f('integer', 'Подразделение'),
                'status' => $f('string', 'Кадровый статус', array('enum' => array('active','leave','dismissed'))),
                'limit' => $f('integer', 'Количество строк', array('minimum'=>1,'maximum'=>100)),
                'offset' => $f('integer', 'Смещение', array('minimum'=>0)),
            )),
            'handler' => function (array $args) {
                $result = Service_Erp310_Workforce::instance()->search(array(
                    'term' => Arr::get($args,'term'), 'department_id' => Arr::get($args,'department_id'),
                    'status' => Arr::get($args,'status'),
                ), Arr::get($args,'limit',25), Arr::get($args,'offset',0));
                return array('summary'=>'Найдено сотрудников: '.$result['total']) + $result;
            },
        ),
        'workforce.employee.get' => array(
            'title' => 'Карточка сотрудника',
            'description' => 'Возвращает сотрудника, текущую должность, подразделение, учётную запись и полную историю назначений.',
            'scope' => 'erp310.view', 'mutating' => FALSE, 'destructive' => FALSE, 'idempotent' => TRUE,
            'schema' => $s(array('id'=>$f('integer','ID сотрудника')), array('id')),
            'handler' => function (array $args) {
                $service=Service_Erp310_Workforce::instance(); $employee=$service->get($args['id']);
                return array('summary'=>$employee['personnel_no'].' · '.$employee['last_name'].' '.$employee['first_name'], 'employee'=>$employee, 'assignments'=>$service->assignment_history($employee['id']));
            },
        ),
        'workforce.structure.list' => array(
            'title' => 'Организационная структура',
            'description' => 'Активные организации, филиалы, подразделения, цеха, участки и бригады с parent_id.',
            'scope' => 'erp310.view', 'mutating' => FALSE, 'destructive' => FALSE, 'idempotent' => TRUE,
            'schema' => $s(array()),
            'handler' => function () {
                $rows=array(); foreach(Service_Erp310_Workforce::instance()->departments() as $d) $rows[]=array('id'=>(int)$d->id,'parent_id'=>$d->parent_id?(int)$d->parent_id:NULL,'code'=>$d->code,'name'=>$d->name,'kind'=>$d->kind);
                return array('summary'=>'Активных подразделений: '.count($rows),'departments'=>$rows);
            },
        ),
        'workforce.positions.list' => array(
            'title' => 'Должности',
            'description' => 'Активные должности по подразделениям и число текущих сотрудников.',
            'scope' => 'erp310.view', 'mutating' => FALSE, 'destructive' => FALSE, 'idempotent' => TRUE,
            'schema' => $s(array()),
            'handler' => function () { $rows=Service_Erp310_Workforce::instance()->position_rows(TRUE); return array('summary'=>'Активных должностей: '.count($rows),'positions'=>$rows); },
        ),
        'workforce.staffing.list' => array(
            'title' => 'Штат и вакансии',
            'description' => 'Плановые, занятые и вакантные ставки по подразделениям и должностям. Отрицательная вакансия означает превышение штата.',
            'scope' => 'erp310.view', 'mutating' => FALSE, 'destructive' => FALSE, 'idempotent' => TRUE,
            'schema' => $s(array()),
            'handler' => function () { $rows=Service_Erp310_Workforce::instance()->staffing_rows(TRUE); return array('summary'=>'Строк штатного расписания: '.count($rows),'staffing'=>$rows); },
        ),
        'workforce.staffing.save' => array(
            'title' => 'Изменить штатное расписание',
            'description' => 'Создаёт или изменяет план ставок. Требует подтверждения человека: изменение влияет на отчёт вакансий и кадровые решения.',
            'scope' => 'erp310.manage', 'mutating' => TRUE, 'destructive' => FALSE, 'idempotent' => FALSE, 'approval' => TRUE,
            'schema' => $s(array(
                'id'=>$f('integer','ID строки при изменении'), 'department_id'=>$f('integer','Подразделение'),
                'position_id'=>$f('integer','Должность'), 'planned_fte'=>$f('number','Плановое число ставок'),
                'valid_from'=>$f('string','Начало действия ГГГГ-ММ-ДД'), 'valid_to'=>$f('string','Конец действия'),
                'note'=>$f('string','Основание или примечание'),
            ), array('department_id','position_id','planned_fte')),
            'handler' => function (array $args) { $row=Service_Erp310_Workforce::instance()->save_staff_unit($args,NULL); return array('summary'=>'План ставок сохранён','id'=>(int)$row->id,'planned_fte'=>(float)$row->planned_fte); },
        ),
        'workforce.employee.save' => array(
            'title' => 'Создать или изменить сотрудника',
            'description' => 'Сохраняет карточку и текущее назначение. Передайте id для изменения; без id создаётся сотрудник. Требует подтверждения из-за персональных данных.',
            'scope' => 'erp310.manage', 'mutating' => TRUE, 'destructive' => FALSE, 'idempotent' => FALSE, 'approval' => TRUE,
            'schema' => $s(array(
                'id'=>$f('integer','ID при изменении'), 'personnel_no'=>$f('string','Табельный номер'),
                'last_name'=>$f('string','Фамилия'), 'first_name'=>$f('string','Имя'), 'middle_name'=>$f('string','Отчество'),
                'email'=>$f('string','E-mail'), 'phone'=>$f('string','Телефон'), 'hired_at'=>$f('string','Дата приёма ГГГГ-ММ-ДД'),
                'status'=>$f('string','Статус',array('enum'=>array('active','leave'))),
                'department_id'=>$f('integer','Подразделение'), 'position_id'=>$f('integer','Должность'),
                'user_id'=>$f('integer','Учётная запись erp100'), 'rate'=>$f('number','Ставка 0.1–2.0'),
            ), array('personnel_no','last_name','first_name','department_id')),
            'handler' => function (array $args) {
                $row=Service_Erp310_Workforce::instance()->save_employee($args, NULL);
                return array('summary'=>'Сотрудник сохранён: '.$row->personnel_no, 'id'=>(int)$row->id, 'uuid'=>$row->uuid, 'personnel_no'=>$row->personnel_no);
            },
        ),
        'workforce.employee.dismiss' => array(
            'title' => 'Уволить сотрудника',
            'description' => 'Меняет кадровый статус и закрывает текущее назначение последним рабочим днём. Учётная запись сохраняется для аудита.',
            'scope' => 'erp310.manage', 'mutating' => TRUE, 'destructive' => TRUE, 'idempotent' => TRUE, 'approval' => TRUE,
            'schema' => $s(array('id'=>$f('integer','ID сотрудника'),'dismissed_at'=>$f('string','Последний рабочий день ГГГГ-ММ-ДД')),array('id','dismissed_at')),
            'handler' => function (array $args) {
                $row=Service_Erp310_Workforce::instance()->dismiss_employee($args['id'],$args['dismissed_at'],NULL);
                return array('summary'=>'Сотрудник '.$row->personnel_no.' уволен '.$row->dismissed_at,'id'=>(int)$row->id,'status'=>$row->status,'dismissed_at'=>$row->dismissed_at);
            },
        ),
    ),
);
