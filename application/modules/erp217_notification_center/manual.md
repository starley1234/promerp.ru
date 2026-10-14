# Исправление ошибок в модуле ERP217 (Центр уведомлений)

В ходе работы модуля были выявлены ошибки валидации и несоответствие структуры БД и ORM-моделей при логировании транзакций.

## Ошибка 1: ORM_Validation_Exception в Model_Erp217_Log

**Проблема:** При попытке сохранить лог в методе `_log_transaction` сервиса `Bus.php` возникает исключение валидации. Это происходит из-за того, что в модели `Model_Erp217_Log` поле `user_id` помечено как `not_empty`, но при системных уведомлениях или ошибках оно может не заполняться корректно, либо не проходит проверку `digit` для системного пользователя (ID 0).

**Где исправить:** `/home/c/cs11853/test.promerp.ru/application/modules/erp217_notification_center/classes/Model/Erp217/Log.php`

**Как исправить:** Измените правила валидации, разрешив `user_id` быть равным 0 (системный процесс).

```php
// Было:
'user_id' => array(array('not_empty'), array('digit')),

// Стало:
'user_id' => array(array('digit')),
```

---

## Ошибка 2: The notification_id property does not exist

**Проблема:** В логах отображается ошибка: `The notification_id property does not exist in the Model_Erp217_Log class`. 
Согласно миграции `Migration_001`, колонка в таблице `erp_217_logs` называется `queue_id`, однако в модели `Model_Erp217_Log` и в сервисе `Bus.php` используется имя `notification_id`.

**Где исправить:** 
1. `/home/c/cs11853/test.promerp.ru/application/modules/erp217_notification_center/classes/Model/Erp217/Log.php`
2. `/home/c/cs11853/test.promerp.ru/application/modules/erp217_notification_center/classes/Service/Erp217/Bus.php`

**Как исправить:**

1. В файле **Model/Erp217/Log.php** обновите связь `$_belongs_to` и правила валидации, чтобы они указывали на реальную колонку в БД (`queue_id`):

```php
// В $_belongs_to
'notification' => array(
    'model'       => 'Erp217_Notification',
    'foreign_key' => 'queue_id', // Изменено с notification_id на queue_id
),

// В rules()
'queue_id' => array(array('not_empty'), array('digit')), // Изменено с notification_id
```

2. В файле **Service/Erp217/Bus.php** в методе `_log_transaction` (строка ~275) измените присвоение:

```php
// Было:
$log->notification_id = $notification->id;

// Стало:
$log->queue_id = $notification->id;
```

3. В файле **Model/Erp217/Log.php** в статическом методе `record` (строка ~80) также измените присвоение:

```php
// Было:
$log->notification_id = $notification->id;

// Стало:
$log->queue_id = $notification->id;
```

---

## Ошибка 3: Ошибка в Task_Erp217_Process (Пустой блок try)

**Проблема:** В файле задачи `Process.php` основной код вызова сервиса находится вне блока `try-catch`, из-за чего ошибки не логируются должным образом, а сам блок `try` пуст.

**Где исправить:** `/home/c/cs11853/test.promerp.ru/application/modules/erp217_notification_center/classes/Task/Erp217/Process.php`

**Как исправить:** Перенесите логику внутрь блока `try`:

```php
protected function _execute(array $params)
{
    try 
    {
        $limit = $params['limit'];
        $bus = Service_Erp217_Bus::instance();
        $bus->process_notification($limit);
        Minion_CLI::write("Notification queue processing completed.");
    }
    catch (Exception $e)
    {
        Minion_CLI::write("Error processing notification queue: " . $e->getMessage());
        Kohana_Log::instance()->add(Log::ERROR, 'Task_Erp217_Process Error: ' . $e->getMessage());
    }
}